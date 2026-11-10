<?php
declare(strict_types=1);

final class SetupController
{
    /**
     * Persyaratan server yang diperiksa sebelum instalasi.
     *
     * @return list<array{label: string, ok: bool, wajib: bool, hint: string}>
     */
    private function requirements(): array
    {
        $daftar = [[
            'label' => 'PHP 8.2 atau lebih baru',
            'ok' => PHP_VERSION_ID >= 80200,
            'wajib' => true,
            'hint' => 'Terdeteksi PHP ' . PHP_VERSION,
        ]];

        $ekstensi = [
            'pdo_mysql' => ['ekstensi pdo_mysql', true],
            'mbstring' => ['ekstensi mbstring', true],
            'openssl' => ['ekstensi openssl', true],
            'session' => ['ekstensi session', true],
            'json' => ['ekstensi json', true],
            'curl' => ['ekstensi curl untuk soal PDF Google Drive', false],
            'gd' => ['ekstensi gd untuk ubah ukuran logo', false],
        ];
        foreach ($ekstensi as $ext => [$label, $wajib]) {
            $ada = extension_loaded($ext);
            $daftar[] = [
                'label' => ucfirst($label),
                'ok' => $ada,
                'wajib' => $wajib,
                'hint' => $ada ? '' : ($wajib
                    ? 'Aktifkan ekstensi ini di php.ini hosting.'
                    : 'Opsional — fitur terkait tidak aktif bila ekstensi tidak ada.'),
            ];
        }

        $folder = [
            'config' => 'config/',
            'storage' => 'storage/',
            'storage/cache' => 'storage/cache/',
            'storage/logs' => 'storage/logs/',
            'storage/sessions' => 'storage/sessions/',
            'assets/uploads' => 'assets/uploads/',
        ];
        foreach ($folder as $path => $label) {
            $dir = BASE_PATH . '/' . $path;
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $ok = is_dir($dir) && is_writable($dir);
            $daftar[] = [
                'label' => 'Folder ' . $label . ' dapat ditulis',
                'ok' => $ok,
                'wajib' => true,
                'hint' => $ok ? '' : 'Beri izin tulis ke folder ini (mis. chmod 775).',
            ];
        }

        return $daftar;
    }

    /** @param list<array{label: string, ok: bool, wajib: bool, hint: string}> $daftar */
    private function requirementsTerpenuhi(array $daftar): bool
    {
        foreach ($daftar as $item) {
            if ($item['wajib'] && !$item['ok']) {
                return false;
            }
        }

        return true;
    }

    public function handle(): void
    {
        if (is_file(BASE_PATH . '/install.lock')) {
            http_response_code(403);
            echo 'Instalasi sudah selesai. Hapus install.lock hanya jika Anda benar-benar ingin mengulang instalasi.';
            return;
        }

        $errors = [];
        $done = false;
        $requirements = $this->requirements();
        $siap = $this->requirementsTerpenuhi($requirements);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::check();
            $dbHost = str_input('db_host', '127.0.0.1');
            $dbPort = int_input('db_port', 3306);
            $dbName = str_input('db_name', 'cbt_bebas');
            $dbUser = str_input('db_user', 'root');
            $dbPass = (string) post('db_pass', '');
            $namaSekolah = str_input('nama_sekolah', APP_NAME);
            $adminNama = str_input('admin_nama', 'Administrator');
            $adminEmail = str_input('admin_email');
            $adminPass = (string) post('admin_pass', '');
            $adminPass2 = (string) post('admin_pass2', '');

            if (!$siap) {
                $errors[] = 'Persyaratan server belum terpenuhi. Perbaiki item bertanda silang di atas, lalu muat ulang halaman ini.';
            }
            if ($dbName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
                $errors[] = 'Nama database hanya boleh huruf, angka, dan garis bawah.';
            }
            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email admin tidak valid.';
            }
            if (strlen($adminPass) < 8) {
                $errors[] = 'Password admin minimal 8 karakter.';
            }
            if ($adminPass !== $adminPass2) {
                $errors[] = 'Konfirmasi password admin tidak sama.';
            }
            if (!is_dir(BASE_PATH . '/config') || !is_writable(BASE_PATH . '/config')) {
                $errors[] = 'Folder config/ tidak dapat ditulis. Buat folder tersebut dan beri izin tulis.';
            }

            if ($errors === []) {
                try {
                    $pdo = $this->koneksiDatabase($dbHost, $dbPort, $dbName, $dbUser, $dbPass);

                    $schema = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
                    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [])) as $stmt) {
                        if ($stmt === '' || str_starts_with($stmt, '--')) {
                            continue;
                        }
                        $pdo->exec($stmt);
                    }

                    $appKey = bin2hex(random_bytes(32));
                    $config = [
                        'app_key' => $appKey,
                        'debug' => false,
                        'db' => [
                            'host' => $dbHost,
                            'port' => $dbPort,
                            'name' => $dbName,
                            'user' => $dbUser,
                            'pass' => $dbPass,
                        ],
                    ];
                    $php = "<?php\n// Dibuat otomatis oleh installer pada " . date('c') . "\nreturn " . var_export($config, true) . ";\n";
                    if (file_put_contents(BASE_PATH . '/config/config.php', $php, LOCK_EX) === false) {
                        throw new RuntimeException('Gagal menulis config/config.php. Periksa izin tulis folder config/.');
                    }

                    $GLOBALS['config'] = $config;

                    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                    $pdo->prepare('INSERT INTO users (nama, email, password_hash, role) VALUES (?,?,?,?)')
                        ->execute([$adminNama, $adminEmail, $hash, 'superadmin']);

                    $settings = [
                        'nama_sekolah' => $namaSekolah,
                        'npsn' => '',
                        'alamat' => '',
                        'logo_sekolah' => '',
                        'tahun_ajaran' => date('Y') . '/' . (date('Y') + 1),
                        'kkm' => '75',
                        'pengumuman' => 'Selamat datang di ' . APP_NAME . '. Ujian dapat dikerjakan langsung melalui browser.',
                        'browser_siswa' => '',
                        'deteksi_tab' => '1',
                        'maks_pelanggaran' => '3',
                        'aksi_pelanggaran' => 'peringatan',
                        'maks_login_siswa' => '1',
                    ];
                    $ins = $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
                    foreach ($settings as $k => $v) {
                        $ins->execute([$k, $v]);
                    }

                    file_put_contents(BASE_PATH . '/install.lock', date('c') . " terpasang\n", LOCK_EX);

                    $done = true;
                } catch (Throwable $e) {
                    $errors[] = 'Instalasi gagal: ' . $e->getMessage();
                }
            }
        }

        require VIEW_PATH . '/setup.php';
    }

    /**
     * Konek ke database tujuan. Bila database belum ada, coba dibuat otomatis;
     * bila akun hosting tidak berhak membuatnya, tampilkan pesan yang jelas.
     */
    private function koneksiDatabase(string $host, int $port, string $dbName, string $user, string $pass): PDO
    {
        $opsi = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);

        try {
            return new PDO($dsn . ';dbname=' . $dbName, $user, $pass, $opsi);
        } catch (PDOException $e) {
            $kode = (int) ($e->errorInfo[1] ?? 0);
            // 1049 = database belum ada; 1044 = belum ada atau user belum diberi akses.
            // Keduanya dicoba lewat jalur pembuatan database di bawah.
            if (!in_array($kode, [1044, 1049], true)) {
                throw new RuntimeException($kode === 1045
                    ? 'User atau password database ditolak. Periksa kembali kredensial dari panel hosting.'
                    : 'Tidak dapat terhubung ke server database: ' . $e->getMessage(), 0, $e);
            }
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, $opsi);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . $dbName . '`');

            return $pdo;
        } catch (PDOException $e) {
            $kode = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($kode, [1044, 1045], true)) {
                throw new RuntimeException(
                    'Database "' . $dbName . '" belum ada atau user database belum diberi akses. '
                    . 'Buat database tersebut lewat panel hosting (mis. cPanel → MySQL Databases), '
                    . 'beri user hak akses penuh, lalu isi nama database yang sama di form ini.',
                    0,
                    $e
                );
            }
            throw new RuntimeException('Gagal menyiapkan database: ' . $e->getMessage(), 0, $e);
        }
    }
}
