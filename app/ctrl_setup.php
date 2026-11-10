<?php
declare(strict_types=1);

final class SetupController
{
    public function handle(): void
    {
        if (is_file(BASE_PATH . '/install.lock')) {
            http_response_code(403);
            echo 'Instalasi sudah selesai. Hapus install.lock hanya jika Anda benar-benar ingin mengulang instalasi.';
            return;
        }

        $errors = [];
        $done = false;

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
                    $pdo = new PDO(
                        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort),
                        $dbUser,
                        $dbPass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                    $pdo->exec('USE `' . $dbName . '`');

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
                    file_put_contents(BASE_PATH . '/config/config.php', $php, LOCK_EX);

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
}
