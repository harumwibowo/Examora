<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Lapisan inti: database, autentikasi, CSRF, view.
 * ------------------------------------------------------------------ */

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $c = $GLOBALS['config'] ?? [];
        if (empty($c['db']['name'])) {
            throw new RuntimeException('Aplikasi belum dikonfigurasi. Jalankan setup terlebih dahulu.');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['db']['host'] ?? '127.0.0.1',
            (int) ($c['db']['port'] ?? 3306),
            $c['db']['name']
        );
        self::$pdo = new PDO($dsn, (string) ($c['db']['user'] ?? ''), (string) ($c['db']['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        self::$pdo->exec("SET time_zone = '+07:00'");
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::q($sql, $params)->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::q($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            $result = $fn($pdo);
            if ($own) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

final class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['auth'] ?? null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return isset($u['id']) ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isStaff(): bool
    {
        $u = self::user();
        return $u !== null && in_array($u['role'] ?? '', ['superadmin', 'guru'], true);
    }

    public static function isSuperadmin(): bool
    {
        return (self::user()['role'] ?? '') === 'superadmin';
    }

    public static function isSiswa(): bool
    {
        return (self::user()['type'] ?? '') === 'siswa';
    }

    public static function nama(): string
    {
        return (string) (self::user()['nama'] ?? '');
    }

    public static function staff(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'] = [
            'type' => 'staff',
            'id' => (int) $user['id'],
            'nama' => $user['nama'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        // Akun staf tetap memakai cookie sesi (hilang saat browser ditutup)
        // agar aman di komputer bersama. Siswa memakai cookie berumur panjang
        // supaya browser HP yang dimatikan sistem tidak membuat mereka keluar.
        self::cookieHanyaBrowser();
    }

    /** Kirim ulang cookie sesi tanpa masa berlaku (session cookie). */
    private static function cookieHanyaBrowser(): void
    {
        if (headers_sent() || !ini_get('session.use_cookies')) {
            return;
        }
        setcookie(session_name(), session_id(), [
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => is_https(),
        ]);
    }

    public static function siswa(array $siswa): void
    {
        session_regenerate_id(true);
        $sid = bin2hex(random_bytes(16));
        try {
            DB::exec('UPDATE siswa SET session_id = ?, last_login_at = ? WHERE id = ?', [$sid, now(), $siswa['id']]);
            DB::exec(
                'INSERT INTO siswa_sesi (id_siswa, sid, php_sid, browser, user_agent, ip, login_at, last_seen)
                 VALUES (?,?,?,?,?,?,?,?)',
                [
                    (int) $siswa['id'], $sid, mb_substr(session_id(), 0, 128), browser_family(),
                    mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                    client_ip(), now(), now(),
                ]
            );
        } catch (Throwable) {
        }
        $_SESSION['auth'] = [
            'type' => 'siswa',
            'id' => (int) $siswa['id'],
            'nama' => $siswa['nama_siswa'],
            'username' => $siswa['username'],
            'id_kelas' => (int) $siswa['id_kelas'],
            'sid' => $sid,
            'browser' => browser_family(),
        ];
    }

    /** Sesi login siswa dianggap kedaluwarsa setelah tidak ada aktivitas selama ini (detik). */
    public const SESI_SISWA_IDLE = 7200;

    /** Hapus baris sesi yang sudah tidak mungkin aktif (idle atau sesi PHP hilang). */
    public static function pruneSesiSiswa(?int $idSiswa = null): void
    {
        $batas = date('Y-m-d H:i:s', time() - self::SESI_SISWA_IDLE);
        try {
            if ($idSiswa !== null) {
                DB::exec('DELETE FROM siswa_sesi WHERE id_siswa = ? AND last_seen < ?', [$idSiswa, $batas]);
            } else {
                DB::exec('DELETE FROM siswa_sesi WHERE last_seen < ?', [$batas]);
            }
            // Pemeriksaan file sesi PHP hanya untuk sesi yang sudah beberapa
            // menit tidak aktif. Jika session.save_path tidak terbaca dari
            // sisi aplikasi, kesalahan deteksi tidak boleh mengeluarkan siswa
            // yang sedang mengerjakan ujian.
            $cekFile = date('Y-m-d H:i:s', time() - 600);
            $sql = 'SELECT id, php_sid FROM siswa_sesi WHERE last_seen < ?';
            $params = [$cekFile];
            if ($idSiswa !== null) {
                $sql .= ' AND id_siswa = ?';
                $params[] = $idSiswa;
            }
            $rows = DB::all($sql, $params);
            $mati = [];
            foreach ($rows as $row) {
                if (!self::sesiPhpHidup((string) ($row['php_sid'] ?? ''))) {
                    $mati[] = (int) $row['id'];
                }
            }
            if ($mati !== []) {
                $in = implode(',', array_fill(0, count($mati), '?'));
                DB::exec('DELETE FROM siswa_sesi WHERE id IN (' . $in . ')', $mati);
            }
        } catch (Throwable) {
        }
    }

    /** Apakah file sesi PHP masih ada (hanya untuk handler "files"). */
    private static function sesiPhpHidup(string $phpSid): bool
    {
        if ($phpSid === '' || (string) ini_get('session.save_handler') !== 'files') {
            return true; // tidak bisa diperiksa — anggap masih hidup
        }
        $path = (string) session_save_path();
        if ($path === '') {
            return true;
        }
        // Format multi-path "N;/path" memakai segmen terakhir.
        if (str_contains($path, ';')) {
            $bagian = explode(';', $path);
            $path = (string) end($bagian);
        }
        $path = rtrim($path, "/\\");
        // Bila folder tidak terbaca (izin/mount berbeda), jangan anggap sesi mati.
        if ($path === '' || !is_dir($path) || !is_readable($path)) {
            return true;
        }
        return is_file($path . DIRECTORY_SEPARATOR . 'sess_' . $phpSid);
    }

    /** Jumlah perangkat yang sedang login untuk satu siswa. */
    public static function jumlahSesiSiswa(int $idSiswa): int
    {
        self::pruneSesiSiswa($idSiswa);
        return (int) DB::scalar('SELECT COUNT(*) FROM siswa_sesi WHERE id_siswa = ?', [$idSiswa]);
    }

    /** Hapus semua sesi login siswa (dipakai saat reset oleh admin/guru). */
    public static function hapusSesiSiswa(?int $idSiswa = null): int
    {
        if ($idSiswa === null) {
            $n = DB::exec('DELETE FROM siswa_sesi');
            DB::exec('UPDATE siswa SET session_id = NULL');
            return $n;
        }
        $n = DB::exec('DELETE FROM siswa_sesi WHERE id_siswa = ?', [$idSiswa]);
        DB::exec('UPDATE siswa SET session_id = NULL WHERE id = ?', [$idSiswa]);
        return $n;
    }

    public static function logout(): void
    {
        $u = self::user();
        if ($u && ($u['type'] ?? '') === 'siswa') {
            try {
                DB::exec('DELETE FROM siswa_sesi WHERE sid = ?', [(string) ($u['sid'] ?? '')]);
                DB::exec('UPDATE siswa SET session_id = NULL WHERE id = ? AND session_id = ?', [$u['id'], $u['sid'] ?? '']);
            } catch (Throwable) {
            }
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    /** Cek bahwa sesi siswa masih terdaftar aktif dan belum direset. */
    public static function validSiswaSession(): bool
    {
        $u = self::user();
        if (!$u || ($u['type'] ?? '') !== 'siswa') {
            return false;
        }
        $sid = (string) ($u['sid'] ?? '');
        if ($sid === '') {
            return false;
        }
        $idSiswa = (int) $u['id'];
        try {
            if (DB::scalar('SELECT status FROM siswa WHERE id = ?', [$idSiswa]) !== 'Aktif') {
                return false;
            }
            $row = DB::one('SELECT browser FROM siswa_sesi WHERE sid = ? AND id_siswa = ?', [$sid, $idSiswa]);
            if (!$row) {
                // Sesi lama (sebelum ada tabel siswa_sesi): pindahkan sekali.
                $lama = DB::scalar('SELECT session_id FROM siswa WHERE id = ?', [$idSiswa]);
                if (!is_string($lama) || $lama === '' || !hash_equals($lama, $sid)) {
                    return false;
                }
                DB::exec(
                    'INSERT INTO siswa_sesi (id_siswa, sid, php_sid, browser, user_agent, ip, login_at, last_seen)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [
                        $idSiswa, $sid, mb_substr(session_id(), 0, 128), (string) ($u['browser'] ?? ''),
                        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                        client_ip(), now(), now(),
                    ]
                );
                $row = ['browser' => (string) ($u['browser'] ?? '')];
            }
            // Ikat sesi ke keluarga browser saat login untuk mencegah cookie dipindah
            // ke browser lain. Sesi lama tanpa data browser tetap diizinkan.
            $terikat = (string) ($row['browser'] ?? '');
            if ($terikat !== '' && $terikat !== browser_family()) {
                DB::exec('DELETE FROM siswa_sesi WHERE sid = ?', [$sid]);
                self::logSesi('sesi ditolak: browser berubah dari "' . $terikat . '" menjadi "' . browser_family() . '"');
                return false;
            }
            DB::exec('UPDATE siswa_sesi SET last_seen = ? WHERE sid = ?', [now(), $sid]);
        } catch (Throwable $e) {
            // Gangguan database sesaat (koneksi putus, deadlock, server sibuk)
            // tidak boleh mengeluarkan siswa dari ujian. Sesi PHP-nya sendiri
            // tetap sah; pemeriksaan diulang pada request berikutnya.
            self::logSesi('validSiswaSession gagal: ' . $e->getMessage());
            return true;
        }
        return true;
    }

    /** Catat kejadian sesi siswa ke log agar penyebab logout bisa ditelusuri. */
    private static function logSesi(string $pesan): void
    {
        @file_put_contents(
            STORAGE_PATH . '/logs/php-error.log',
            sprintf("[%s] [sesi siswa #%s] %s\n", date('c'), (string) (self::user()['id'] ?? '-'), $pesan),
            FILE_APPEND
        );
    }

    public static function requireStaff(): void
    {
        if (!self::isStaff()) {
            flash('warning', 'Silakan masuk terlebih dahulu.');
            redirect('login');
        }
    }

    public static function requireSuperadmin(): void
    {
        self::requireStaff();
        if (!self::isSuperadmin()) {
            http_response_code(403);
            View::render('errors/403', ['pesan' => 'Halaman ini hanya untuk superadmin.'], 'layout');
            exit;
        }
    }

    public static function requireSiswa(): void
    {
        if (!self::isSiswa()) {
            flash('warning', 'Silakan masuk sebagai siswa.');
            redirect('masuk_siswa');
        }
        if (!self::validSiswaSession()) {
            self::logout();
            flash('warning', 'Sesi Anda berakhir karena tidak ada aktivitas terlalu lama, akun dipakai di perangkat/browser lain, atau sesi telah direset. Silakan masuk kembali.');
            redirect('masuk_siswa');
        }
    }

    public static function canManageSoal(array $soal): bool
    {
        if (self::isSuperadmin()) {
            return true;
        }
        $idUser = self::id();
        if ($idUser === null || !self::isStaff()) {
            return false;
        }
        if ($idUser === (int) ($soal['id_guru'] ?? 0)) {
            return true;
        }
        $idSoal = (int) ($soal['id'] ?? 0);
        if ($idSoal <= 0) {
            return false;
        }
        // Kolaborator: guru yang diberi akses penuh oleh pemilik ujian.
        try {
            return (bool) DB::scalar(
                'SELECT 1 FROM soal_kolaborator WHERE id_soal = ? AND id_user = ?',
                [$idSoal, $idUser]
            );
        } catch (Throwable) {
            return false;
        }
    }

    /** Apakah pengguna saat ini pemilik ujian (bukan hanya kolaborator)? */
    public static function pemilikSoal(array $soal): bool
    {
        return self::isSuperadmin() || (self::id() !== null && self::id() === (int) ($soal['id_guru'] ?? 0));
    }
}

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function check(): void
    {
        $sent = (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($sent === '' || !hash_equals(self::token(), $sent)) {
            http_response_code(419);
            View::render('errors/419', [], 'layout');
            exit;
        }
    }
}

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        $data['flashes'] = take_flashes();
        $data['authUser'] = Auth::user();
        $data['sekolah'] = [
            'nama' => setting('nama_sekolah', APP_NAME),
            'npsn' => setting('npsn', ''),
            'alamat' => setting('alamat', ''),
            'logo' => setting('logo_sekolah', ''),
            'tahun_ajaran' => setting('tahun_ajaran', ''),
            'kkm' => setting('kkm', '75'),
        ];

        extract($data, EXTR_SKIP);
        $viewFile = VIEW_PATH . '/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException('View tidak ditemukan: ' . $view);
        }
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }
        $layoutFile = VIEW_PATH . '/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new RuntimeException('Layout tidak ditemukan: ' . $layout);
        }
        require $layoutFile;
    }

    public static function partial(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require VIEW_PATH . '/' . $view . '.php';
    }
}

final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $key, string $label): self
    {
        $v = trim((string) ($this->data[$key] ?? ''));
        if ($v === '') {
            $this->errors[$key] = $label . ' wajib diisi.';
        }
        return $this;
    }

    public function email(string $key, string $label): self
    {
        $v = trim((string) ($this->data[$key] ?? ''));
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$key] = $label . ' tidak valid.';
        }
        return $this;
    }

    public function min(string $key, int $min, string $label): self
    {
        $v = (string) ($this->data[$key] ?? '');
        if (mb_strlen($v) < $min) {
            $this->errors[$key] = $label . ' minimal ' . $min . ' karakter.';
        }
        return $this;
    }

    public function intBetween(string $key, int $min, int $max, string $label): self
    {
        $v = filter_var($this->data[$key] ?? null, FILTER_VALIDATE_INT);
        if ($v === false || $v < $min || $v > $max) {
            $this->errors[$key] = $label . ' harus antara ' . $min . ' dan ' . $max . '.';
        }
        return $this;
    }

    public function same(string $key, string $other, string $label): self
    {
        if ((string) ($this->data[$key] ?? '') !== (string) ($this->data[$other] ?? '')) {
            $this->errors[$key] = $label . ' tidak sama.';
        }
        return $this;
    }

    public function add(string $key, string $message): self
    {
        $this->errors[$key] = $message;
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): string
    {
        return (string) (reset($this->errors) ?: '');
    }
}

final class Abort extends RuntimeException
{
}
