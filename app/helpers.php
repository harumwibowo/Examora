<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Helper global
 * ------------------------------------------------------------------ */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function base_url(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $dir === '' ? '' : $dir;
}

/**
 * Bangun URL internal. Semua tautan memakai front controller agar
 * kompatibel dengan shared hosting tanpa mod_rewrite.
 */
function url(string $route = 'home', array $params = []): string
{
    $params = array_merge(['r' => $route], $params);
    return base_url() . '/index.php?' . http_build_query($params);
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $url = base_url() . '/assets/' . $path;
    // Cache-busting: versi mengikuti waktu ubah berkas agar browser tidak
    // memakai CSS/JS lama setelah aplikasi diperbarui.
    $file = BASE_PATH . '/assets/' . $path;
    if (is_file($file)) {
        $url .= '?v=' . (int) filemtime($file);
    }
    return $url;
}

/**
 * URL favicon aplikasi. Logo sekolah dipakai bila tersedia.
 */
function favicon_url(): string
{
    return url('favicon');
}

function favicon_tags(): string
{
    $href = e(favicon_url());
    return '<link rel="icon" href="' . $href . '">' . "\n"
        . '<link rel="apple-touch-icon" href="' . $href . '">';
}

function redirect(string $route, array $params = [], int $code = 302): never
{
    header('Location: ' . url($route, $params), true, $code);
    exit;
}

function back(string $fallback = 'dashboard'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && str_starts_with($ref, 'http')) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function post(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function str_input(string $key, string $default = '', ?array $src = null): string
{
    $src ??= $_POST;
    return trim((string) ($src[$key] ?? $default));
}

function int_input(string $key, int $default = 0, ?array $src = null): int
{
    $src ??= $_POST;
    return (int) ($src[$key] ?? $default);
}

function json_out(array $payload, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function fmt_tanggal(?string $dt, bool $withTime = true): string
{
    if (!$dt) {
        return '-';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return '-';
    }
    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

function fmt_nilai(mixed $n, int $dec = 2): string
{
    if ($n === null || $n === '') {
        return '—';
    }
    return number_format((float) $n, $dec, ',', '.');
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, mixed $default = ''): string
{
    $v = $_SESSION['_old'][$key] ?? $default;
    return is_scalar($v) ? (string) $v : (string) $default;
}

function keep_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function random_password(int $len = 6): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function token_ujian(int $len = 6): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function audit(string $aksi, ?string $entitas = null, ?int $entitasId = null, array $detail = []): void
{
    try {
        $u = Auth::user();
        DB::insert(
            'INSERT INTO audit_log (user_id, peran, aksi, entitas, entitas_id, detail, ip, waktu)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $u['id'] ?? null,
                $u['role'] ?? ($u['type'] ?? null),
                $aksi,
                $entitas,
                $entitasId,
                $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
                client_ip(),
                now(),
            ]
        );
    } catch (Throwable) {
        // audit tidak boleh menggagalkan aksi utama
    }
}

function rate_limit(string $key, int $max = 5, int $seconds = 900): bool
{
    $file = STORAGE_PATH . '/cache/rl_' . sha1($key . '|' . client_ip()) . '.json';
    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0775, true);
    }
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data)) {
            $hits = array_values(array_filter($data, static fn ($t) => is_int($t) && $t > $now - $seconds));
        }
    }
    if (count($hits) >= $max) {
        return false;
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return true;
}

function rate_limit_clear(string $key): void
{
    $file = STORAGE_PATH . '/cache/rl_' . sha1($key . '|' . client_ip()) . '.json';
    @unlink($file);
}

function kartu_encrypt(string $plain): string
{
    $key = (string) ($GLOBALS['config']['app_key'] ?? '');
    if ($key === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        return '';
    }
    return base64_encode($iv . $tag . $cipher);
}

function kartu_decrypt(?string $payload): string
{
    if (!$payload) {
        return '';
    }
    $key = (string) ($GLOBALS['config']['app_key'] ?? '');
    if ($key === '') {
        return '';
    }
    $raw = base64_decode($payload, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (DB::all('SELECT kunci, nilai FROM pengaturan') as $row) {
                $cache[$row['kunci']] = $row['nilai'];
            }
        } catch (Throwable) {
            $cache = [];
        }
    }
    return (string) ($cache[$key] ?? $default);
}

function set_setting(string $key, string $value): void
{
    DB::exec(
        'INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)',
        [$key, $value]
    );
}

/**
 * Daftar jenjang yang didukung: kunci => label.
 */
function jenjang_options(): array
{
    return [
        'SD'    => 'SD',
        'SMP'   => 'SMP',
        'SMA'   => 'SMA',
        'SMK'   => 'SMK',
        'MA'    => 'MA',
        'PAKET' => 'Paket Umum',
    ];
}

/**
 * Jenjang sekolah aktif (pengaturan `jenjang`). Nilai tak dikenal kembali ke SMA.
 */
function jenjang(): string
{
    $j = strtoupper(setting('jenjang', 'SMA'));
    return isset(jenjang_options()[$j]) ? $j : 'SMA';
}

/**
 * Daftar opsi tingkat (romawi) sesuai jenjang aktif.
 * Satu-satunya sumber daftar: dipakai untuk validasi dan dropdown.
 */
function tingkat_options(): array
{
    return match (jenjang()) {
        'SD'  => ['I', 'II', 'III', 'IV', 'V', 'VI'],
        'SMP' => ['VII', 'VIII', 'IX'],
        default => ['X', 'XI', 'XII'],
    };
}

/**
 * Pilihan jenjang/tingkat untuk target ujian: tingkat standar sesuai
 * jenjang sekolah digabung dengan tingkat yang benar-benar dipakai kelas.
 */
function tingkat_kelas_options(): array
{
    $opsi = [];
    foreach (tingkat_options() as $t) {
        $opsi[$t] = $t;
    }
    try {
        foreach (DB::all('SELECT DISTINCT tingkat FROM kelas ORDER BY tingkat') as $row) {
            $t = trim((string) ($row['tingkat'] ?? ''));
            if ($t !== '' && $t !== '-') {
                $opsi[$t] = $t;
            }
        }
    } catch (Throwable) {
        // DB belum siap.
    }
    return array_values($opsi);
}

function status_badge(string $status): string
{
    $map = [
        'Aktif' => 'success',
        'Draft' => 'secondary',
        'Arsip' => 'dark',
        'Berlangsung' => 'warning',
        'Selesai' => 'success',
        'Menunggu Koreksi' => 'info',
        'Dibatalkan' => 'danger',
        'Belum' => 'secondary',
        'Sebagian' => 'warning',
        'Tidak Aktif' => 'secondary',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $cls . '">' . e($status) . '</span>';
}

/**
 * Daftar agama untuk target peserta ujian. Menggabungkan daftar umum
 * dengan nilai agama yang sudah dipakai pada data siswa agar data lama
 * (mis. "Kristen Protestan") tetap bisa dipilih.
 */
function agama_options(): array
{
    static $options = null;
    if ($options !== null) {
        return $options;
    }

    $standar = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'];
    $dipakai = [];
    try {
        $rows = DB::all(
            "SELECT DISTINCT TRIM(agama) AS agama FROM siswa
              WHERE agama IS NOT NULL AND TRIM(agama) <> ''"
        );
        foreach ($rows as $row) {
            $dipakai[] = (string) $row['agama'];
        }
    } catch (Throwable) {
        // DB belum siap (halaman instalasi) — pakai daftar umum saja.
    }

    $unik = [];
    foreach (array_merge($standar, $dipakai) as $nilai) {
        $nilai = trim((string) $nilai);
        if ($nilai === '') {
            continue;
        }
        $kunci = mb_strtolower($nilai);
        if (!isset($unik[$kunci])) {
            $unik[$kunci] = $nilai;
        }
    }

    $options = array_values($unik);
    return $options;
}

/** Nama target agama untuk ditampilkan; kosong berarti semua agama. */
function agama_label(?string $target): string
{
    $target = trim((string) $target);
    return $target === '' ? 'Semua agama' : $target;
}

/** Bandingkan agama tanpa membedakan huruf besar/kecil dan spasi tepi. */
function agama_sama(?string $a, ?string $b): bool
{
    return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
}

/** Apakah siswa dengan agama tertentu memenuhi target agama ujian? */
function agama_cocok(?string $agamaSiswa, ?string $target): bool
{
    $target = trim((string) $target);
    return $target === '' || agama_sama($agamaSiswa, $target);
}

function opsi_huruf(int $n): array
{
    return array_slice(['A', 'B', 'C', 'D', 'E'], 0, max(2, min(5, $n)));
}

/** Daftar keluarga browser yang dapat dipilih untuk membatasi login siswa. */
function browser_families(): array
{
    return [
        'chrome' => 'Google Chrome / Chromium',
        'firefox' => 'Mozilla Firefox',
        'edge' => 'Microsoft Edge',
        'safari' => 'Safari',
        'opera' => 'Opera',
        'samsung' => 'Samsung Internet',
        'seb' => 'Safe Exam Browser',
    ];
}

/** Nama pendek browser untuk ditampilkan pada input teks pengaturan. */
function browser_short_names(): array
{
    return [
        'chrome' => 'chrome',
        'firefox' => 'firefox',
        'edge' => 'edge',
        'safari' => 'safari',
        'opera' => 'opera',
        'samsung' => 'samsung',
        'seb' => 'SEB',
    ];
}

/**
 * Nama lain yang diterima pada input teks pembatasan browser.
 * Kunci mengikuti browser_families().
 */
function browser_aliases(): array
{
    return [
        'chrome' => ['chrome', 'google chrome', 'chromium', 'crios', 'gc'],
        'firefox' => ['firefox', 'mozilla firefox', 'mozilla', 'fxios'],
        'edge' => ['edge', 'microsoft edge', 'ms edge', 'msedge', 'edg'],
        'safari' => ['safari', 'apple safari'],
        'opera' => ['opera', 'opera mini', 'opera gx', 'opr'],
        'samsung' => ['samsung', 'samsung internet', 'samsung browser', 'samsungbrowser'],
        'seb' => ['seb', 'safe exam browser', 'safeexambrowser', 'safe-exam-browser', 'safeexambrowser/seb'],
    ];
}

/** Terjemahkan satu potongan teks menjadi kunci keluarga browser ('' bila tak dikenal). */
function browser_key_from_text(string $token): string
{
    $token = mb_strtolower(trim($token));
    if ($token === '') {
        return '';
    }
    // Buang keterangan versi, mis. "chrome 120" atau "SEB 3.4".
    $tanpaVersi = trim((string) preg_replace('/[\s\/]+v?[0-9][0-9.]*$/', '', $token));
    if ($tanpaVersi !== '') {
        $token = $tanpaVersi;
    }
    if (array_key_exists($token, browser_families())) {
        return $token;
    }
    foreach (browser_aliases() as $key => $aliases) {
        if (in_array($token, $aliases, true)) {
            return $key;
        }
    }
    return '';
}

/**
 * Baca input teks bebas (mis. "chrome, SEB") menjadi daftar kunci browser.
 *
 * @return list<string>
 */
function browser_parse_text(?string $text): array
{
    $out = [];
    foreach ((array) preg_split('/[,;|\n]+/', (string) $text) as $part) {
        $key = browser_key_from_text((string) $part);
        if ($key !== '' && !in_array($key, $out, true)) {
            $out[] = $key;
        }
    }
    return $out;
}

/** Potongan teks yang tidak dikenali pada input pembatasan browser. */
function browser_unknown_text(?string $text): array
{
    $out = [];
    foreach ((array) preg_split('/[,;|\n]+/', (string) $text) as $part) {
        $part = trim((string) $part);
        if ($part !== '' && browser_key_from_text($part) === '') {
            $out[] = $part;
        }
    }
    return $out;
}

/** Ubah daftar kunci browser menjadi teks untuk mengisi input. */
function browser_list_text(array $list): string
{
    $short = browser_short_names();
    $out = [];
    foreach ($list as $key) {
        $key = (string) $key;
        $out[] = $short[$key] ?? $key;
    }
    return implode(', ', $out);
}

/** Deteksi keluarga browser dari User-Agent. Urutan penting untuk SEB/Edge/Opera/Samsung. */
function browser_family(?string $userAgent = null): string
{
    $ua = strtolower($userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return 'unknown';
    }
    // Safe Exam Browser menambahkan penanda "SEB/..." pada User-Agent.
    if (preg_match('#(^|[\s;(])seb($|[\s;/)0-9])#', $ua) === 1
        || str_contains($ua, 'safeexam')
        || str_contains($ua, 'safe exam browser')) {
        return 'seb';
    }
    if (str_contains($ua, 'edg/') || str_contains($ua, 'edga/') || str_contains($ua, 'edgios/') || str_contains($ua, 'edge/')) {
        return 'edge';
    }
    if (str_contains($ua, 'opr/') || str_contains($ua, 'opios')
        || str_contains($ua, 'opera mini') || str_contains($ua, 'opera mobi')
        || str_contains($ua, 'opera')) {
        return 'opera';
    }
    if (str_contains($ua, 'samsungbrowser')) {
        return 'samsung';
    }
    if (str_contains($ua, 'firefox/') || str_contains($ua, 'fxios/')) {
        return 'firefox';
    }
    if (str_contains($ua, 'chrome/') || str_contains($ua, 'crios/') || str_contains($ua, 'chromium/')) {
        return 'chrome';
    }
    if (str_contains($ua, 'safari/')) {
        return 'safari';
    }
    return 'other';
}

function browser_label(string $family): string
{
    $labels = browser_families();
    return $labels[$family] ?? 'Browser lain / tidak dikenal';
}

/**
 * Daftar browser yang diizinkan untuk login siswa.
 * Kosong berarti semua browser diperbolehkan.
 *
 * @return list<string>
 */
function browser_siswa_allowed_list(): array
{
    $raw = trim(setting('browser_siswa', ''));
    if ($raw === '') {
        return [];
    }
    // Format lama tersimpan sebagai JSON array; format baru berupa teks "chrome, SEB".
    if (str_starts_with($raw, '[')) {
        $list = json_decode($raw, true);
        if (is_array($list)) {
            return browser_parse_text(implode(', ', array_filter($list, 'is_scalar')));
        }
    }
    return browser_parse_text($raw);
}

/** Apakah User-Agent saat ini boleh dipakai siswa untuk login? */
function browser_siswa_diizinkan(?string $userAgent = null): bool
{
    $allowed = browser_siswa_allowed_list();
    if ($allowed === []) {
        return true;
    }
    return in_array(browser_family($userAgent), $allowed, true);
}

function flash_success(string $m): void
{
    flash('success', $m);
}

/**
 * Pengaturan pengawasan ujian yang berlaku untuk semua ujian.
 * Hanya superadmin yang dapat mengubahnya di halaman Pengaturan.
 *
 * @return array{deteksi_tab:int, maks_pelanggaran:int, aksi_pelanggaran:string}
 */
function pengaturan_pengawasan(): array
{
    $deteksi = setting('deteksi_tab', '1') === '0' ? 0 : 1;
    $maks = (int) setting('maks_pelanggaran', '3');
    $aksi = trim(setting('aksi_pelanggaran', 'peringatan'));
    if (!in_array($aksi, ['peringatan', 'kunci', 'akhiri'], true)) {
        $aksi = 'peringatan';
    }
    return [
        'deteksi_tab' => $deteksi,
        'maks_pelanggaran' => max(1, min(20, $maks)),
        'aksi_pelanggaran' => $aksi,
    ];
}

/** Batas jumlah perangkat/peramban yang boleh login bersamaan untuk satu siswa. */
function maks_login_siswa(): int
{
    $n = (int) trim(setting('maks_login_siswa', '1'));
    return max(1, min(20, $n));
}

function flash_error(string $m): void
{
    flash('danger', $m);
}

function base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Potongan SQL syarat "ujian dapat diakses pengguna saat ini" (milik sendiri
 * atau sebagai kolaborator). Mengembalikan [sql, params].
 * Alias harus berupa nama tabel/alias sederhana (hanya huruf/angka/underscore).
 *
 * @return array{0:string, 1:array}
 */
function soal_akses_sql(string $alias = 's'): array
{
    if (Auth::isSuperadmin()) {
        return ['1=1', []];
    }
    $a = (string) preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
    if ($a === '') {
        $a = 's';
    }
    $idUser = (int) (Auth::id() ?? 0);
    return [
        '(' . $a . '.id_guru = ? OR EXISTS (SELECT 1 FROM soal_kolaborator skl'
        . ' WHERE skl.id_soal = ' . $a . '.id AND skl.id_user = ?))',
        [$idUser, $idUser],
    ];
}

/**
 * Inisial nama untuk avatar: 2 huruf dari kata pertama & kedua
 * ("Alif Muhammad" → "AM"), atau 2 huruf pertama bila hanya satu kata
 * ("Examora" → "EX"). Nama kosong → "?".
 */
function inisial_nama(?string $nama, int $panjang = 2): string
{
    $bersih = trim((string) preg_replace('/\s+/', ' ', (string) $nama));
    if ($bersih === '') {
        return '?';
    }
    $panjang = max(1, min(3, $panjang));
    $kata = preg_split('/\s+/', $bersih) ?: [];
    if (count($kata) >= 2) {
        $ini = '';
        foreach (array_slice($kata, 0, $panjang) as $k) {
            $ini .= mb_substr($k, 0, 1);
        }
        return mb_strtoupper($ini);
    }
    return mb_strtoupper(mb_substr($bersih, 0, $panjang));
}

/**
 * Kode HTTP dokumen PDF Google Drive dari sisi server (0 = tidak dapat dicek).
 * Dipakai untuk memperingatkan guru bila berkas belum dibagikan publik.
 *
 * Catatan: endpoint tautan tampilan (/file/d/ID/view) selalu membalas 401 untuk
 * permintaan server tanpa cookie — termasuk pada berkas yang sudah publik —
 * sehingga pengecekan memakai endpoint unduhan yang sama dengan halaman ujian
 * siswa (uc?export=download).
 */
function drive_pdf_status(string $url, string $resourceKey = ''): int
{
    $fileId = drive_file_id($url);
    if ($fileId === '' || !function_exists('curl_init')) {
        return 0;
    }
    $key = trim($resourceKey) !== '' ? trim($resourceKey) : drive_file_key($url);
    $unduh = 'https://drive.google.com/uc?export=download&id=' . rawurlencode($fileId);
    if ($key !== '') {
        $unduh .= '&resourcekey=' . rawurlencode($key);
    }

    $ch = curl_init($unduh);
    if ($ch === false) {
        return 0;
    }
    curl_setopt_array($ch, [
        CURLOPT_RANGE => '0-1023',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0 || !is_string($body)) {
        return 0;
    }
    if ($code === 200 || $code === 206) {
        if (str_starts_with($body, '%PDF')) {
            return 200;
        }
        // Google membalas halaman login / "minta akses" dengan status 200.
        if (preg_match('#accounts\.google\.com/(v3/signin|ServiceLogin)|You need access|Anda memerlukan akses|Request access|Minta akses#i', $body)) {
            return 401;
        }
        // Halaman konfirmasi unduhan (berkas besar) tetap bisa diunduh aplikasi.
        return 200;
    }
    return $code;
}

/** Ambil ID berkas dari tautan Google Drive (file/d/ID, open?id=, uc?id=). */
function drive_file_id(string $url): string
{
    $url = trim($url);
    // Input boleh berupa ID saja (tanpa URL), mis. 1AbCdEfGhIjKlMnOpQrStUv.
    if (preg_match('#^[A-Za-z0-9_-]{10,}$#', $url)) {
        return $url;
    }
    foreach ([
        '#drive\.google\.com/file/d/([A-Za-z0-9_-]{10,})#',
        '#drive\.google\.com/open\?id=([A-Za-z0-9_-]{10,})#',
        '#drive\.google\.com/uc\?[^"\'\s]*id=([A-Za-z0-9_-]{10,})#',
        '#drive\.usercontent\.google\.com/download\?[^"\'\s]*id=([A-Za-z0-9_-]{10,})#',
    ] as $re) {
        if (preg_match($re, $url, $m)) {
            return $m[1];
        }
    }
    return '';
}

/** Susun tautan Drive baku dari ID (+ resource key bila ada). */
function drive_canonical_link(string $fileId, string $resourceKey = ''): string
{
    $fileId = trim($fileId);
    if ($fileId === '') {
        return '';
    }
    $url = 'https://drive.google.com/file/d/' . rawurlencode($fileId) . '/view';
    if (trim($resourceKey) !== '') {
        $url .= '?resourcekey=' . rawurlencode(trim($resourceKey));
    }
    return $url;
}

/** Resource key Google Drive (wajib untuk berkas lama yang di-share via link). */
function drive_file_key(string $url): string
{
    if (preg_match('#[?&]resourcekey=([A-Za-z0-9_-]+)#', $url, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Jenis tautan yang ditempel guru: 'berkas' (PDF/berkas Drive), 'folder',
 * 'dokumen' (Docs/Sheets/Slides), 'tidak_dikenal', atau '' bila kosong.
 * Dipakai agar pesan galat menyebut masalah sebenarnya — folder dan Google Docs
 * tidak akan pernah bisa diunduh sebagai PDF walaupun sudah dibagikan publik.
 */
function drive_tautan_jenis(string $url): string
{
    $u = trim($url);
    if ($u === '') {
        return '';
    }
    if (preg_match('#drive\.google\.com/drive/folders/#i', $u)) {
        return 'folder';
    }
    if (preg_match('#docs\.google\.com/(document|spreadsheet|spreadsheets|presentation|forms|file)/#i', $u)) {
        return 'dokumen';
    }
    return drive_file_id($u) !== '' ? 'berkas' : 'tidak_dikenal';
}

/** Lokasi cache PDF ujian di storage/cache/pdf. */
function pdf_cache_path(array $soal): string
{
    $kunci = (string) ($soal['id'] ?? '') . '|' . (string) ($soal['link_pdf'] ?? '');
    return STORAGE_PATH . '/cache/pdf/' . hash('sha256', $kunci) . '.pdf';
}

/**
 * Unduh URL ke berkas (streaming, hemat memori) dan pastikan isinya PDF.
 *
 * @return array{ok:bool, status:int, error:string}
 */
function pdf_download_to(string $url, string $tujuan, int $timeout = 30): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'error' => 'ekstensi cURL tidak aktif'];
    }
    $fh = @fopen($tujuan, 'wb');
    if ($fh === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'berkas cache tidak dapat ditulis'];
    }
    $ch = curl_init($url);
    if ($ch === false) {
        fclose($fh);
        return ['ok' => false, 'status' => 0, 'error' => 'cURL gagal'];
    }
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
    ]);
    $jalan = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = (string) curl_error($ch);
    curl_close($ch);
    fclose($fh);

    if ($jalan === false) {
        return ['ok' => false, 'status' => $status, 'error' => $error !== '' ? $error : 'gagal terhubung'];
    }
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'status' => $status, 'error' => 'HTTP ' . $status];
    }
    $kepala = @file_get_contents($tujuan, false, null, 0, 4);
    if ($kepala !== '%PDF') {
        return ['ok' => false, 'status' => $status, 'error' => 'bukan berkas PDF'];
    }
    return ['ok' => true, 'status' => $status, 'error' => ''];
}

/**
 * Ambil PDF Google Drive ke berkas, termasuk melewati halaman konfirmasi
 * "Download anyway" yang muncul pada berkas besar.
 *
 * @return array{ok:bool, status:int, error:string}
 */
function drive_pdf_download(string $fileId, string $tujuan, string $resourceKey = '', int $timeout = 30): array
{
    $url = 'https://drive.google.com/uc?export=download&id=' . rawurlencode($fileId);
    if ($resourceKey !== '') {
        $url .= '&resourcekey=' . rawurlencode($resourceKey);
    }
    $hasil = pdf_download_to($url, $tujuan, $timeout);
    if ($hasil['ok']) {
        return $hasil;
    }

    $html = (string) @file_get_contents($tujuan, false, null, 0, 400000);
    if ($html === '') {
        return $hasil;
    }
    // Google membalas halaman login/konten privat dengan status 200.
    if (preg_match('#accounts\.google\.com/(v3/signin|ServiceLogin)|Izinkan Google Drive mengakses cookie#i', $html)) {
        return ['ok' => false, 'status' => 401, 'error' => 'berkas belum dibagikan publik'];
    }
    $aksi = '';
    $params = [];
    if (preg_match('#<form[^>]+action="([^"]+)"#i', $html, $m)) {
        $aksi = html_entity_decode($m[1], ENT_QUOTES);
        if (preg_match_all('#<input[^>]+name="([^"]+)"[^>]*value="([^"]*)"#i', $html, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $in) {
                $params[$in[1]] = html_entity_decode($in[2], ENT_QUOTES);
            }
        }
    }
    if ($aksi !== '' && $params !== []) {
        $url2 = $aksi . (str_contains($aksi, '?') ? '&' : '?') . http_build_query($params);
        $hasil2 = pdf_download_to($url2, $tujuan, $timeout);
        if ($hasil2['ok']) {
            return $hasil2;
        }
        $hasil = $hasil2;
    } elseif (preg_match('#confirm=([0-9A-Za-z_-]{4,})#', $html, $m)) {
        $url2 = $url . '&confirm=' . rawurlencode($m[1]);
        $hasil2 = pdf_download_to($url2, $tujuan, $timeout);
        if ($hasil2['ok']) {
            return $hasil2;
        }
        $hasil = $hasil2;
    }
    return $hasil;
}

/* ------------------------------------------------------------------
 * Komposisi soal & bobot
 *
 * Satu ujian menyimpan daftar blok pada kolom soal.komposisi (JSON):
 *   [{"tipe":"PG","dari":1,"sampai":30,"bobot":60},
 *    {"tipe":"Isian","dari":31,"sampai":35,"bobot":20},
 *    {"tipe":"Uraian","dari":36,"sampai":40,"bobot":20}]
 * Blok menentukan jenis soal untuk setiap nomor urut sekaligus bobot
 * nilai kelompoknya. Ujian lama (komposisi NULL) otomatis diturunkan
 * dari jml_pg/jml_uraian + bobot_pg/bobot_uraian.
 * ------------------------------------------------------------------ */

/** Jenis soal yang didukung lembar jawaban. */
function tipe_soal_options(): array
{
    return [
        'PG' => 'Pilihan Ganda',
        'PG_Kompleks' => 'PG Kompleks',
        'Benar_Salah' => 'Benar / Salah',
        'Menjodohkan' => 'Menjodohkan',
        'Isian' => 'Isian Singkat',
        'Uraian' => 'Uraian',
    ];
}

function tipe_soal_valid(string $tipe): bool
{
    return array_key_exists($tipe, tipe_soal_options());
}

function tipe_soal_label(string $tipe): string
{
    return tipe_soal_options()[$tipe] ?? $tipe;
}

/** Baca $_SESSION['_old'] berupa array (untuk field komposisi yang bertingkat). */
function old_array(string $key, array $default = []): array
{
    $v = $_SESSION['_old'][$key] ?? null;
    return is_array($v) ? $v : $default;
}

/**
 * Blok komposisi yang tersimpan eksplisit pada ujian.
 * Mengembalikan [] bila belum ada (ujian lama) atau datanya rusak.
 */
function komposisi_tersimpan(array $soal): array
{
    $raw = $soal['komposisi'] ?? null;
    $arr = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (!is_array($arr)) {
        return [];
    }
    $blok = [];
    foreach ($arr as $b) {
        if (!is_array($b)) {
            continue;
        }
        if (!komposisi_blok_valid($b)) {
            continue;
        }
        $blok[] = komposisi_blok_normal((array) $b);
    }
    usort($blok, static fn (array $a, array $c): int => $a['dari'] <=> $c['dari']);
    return $blok;
}

/** @param array<string,mixed> $b */
function komposisi_blok_valid(array $b): bool
{
    return tipe_soal_valid((string) ($b['tipe'] ?? ''))
        && (int) ($b['dari'] ?? 0) >= 1
        && (int) ($b['sampai'] ?? 0) >= (int) ($b['dari'] ?? 0);
}

/** @param array<string,mixed> $b @return array{tipe:string,dari:int,sampai:int,bobot:float} */
function komposisi_blok_normal(array $b): array
{
    return [
        'tipe' => (string) $b['tipe'],
        'dari' => (int) $b['dari'],
        'sampai' => (int) $b['sampai'],
        'bobot' => max(0.0, round((float) ($b['bobot'] ?? 0), 2)),
    ];
}

/**
 * Komposisi efektif untuk penilaian/tampilan: blok tersimpan,
 * atau turunan dari kolom lama (PG 1..jml_pg, Uraian sisanya).
 */
function komposisi_efektif(array $soal): array
{
    $blok = komposisi_tersimpan($soal);
    if ($blok !== []) {
        return $blok;
    }
    $jmlPg = max(0, (int) ($soal['jml_pg'] ?? 0));
    $jmlUraian = max(0, (int) ($soal['jml_uraian'] ?? 0));
    $bobotPg = max(0.0, (float) ($soal['bobot_pg'] ?? 0));
    $bobotUraian = max(0.0, (float) ($soal['bobot_uraian'] ?? 0));
    $blok = [];
    if ($jmlPg > 0) {
        $blok[] = ['tipe' => 'PG', 'dari' => 1, 'sampai' => $jmlPg, 'bobot' => $jmlUraian > 0 ? $bobotPg : 100.0];
    }
    if ($jmlUraian > 0) {
        $blok[] = [
            'tipe' => 'Uraian',
            'dari' => $jmlPg + 1,
            'sampai' => $jmlPg + $jmlUraian,
            'bobot' => $jmlPg > 0 ? $bobotUraian : 100.0,
        ];
    }
    return $blok;
}

/** Nomor urut tertinggi pada komposisi (0 bila belum diatur). */
function komposisi_total(array $soal): int
{
    $total = 0;
    foreach (komposisi_efektif($soal) as $b) {
        $total = max($total, (int) $b['sampai']);
    }
    return $total;
}

/** Jenis soal untuk satu nomor urut. */
function komposisi_tipe(array $soal, int $nomor): string
{
    foreach (komposisi_efektif($soal) as $b) {
        if ($nomor >= (int) $b['dari'] && $nomor <= (int) $b['sampai']) {
            return (string) $b['tipe'];
        }
    }
    return 'PG';
}

/** Jumlah soal per jenis: ['PG' => 30, 'Uraian' => 5]. */
function komposisi_jumlah(array $soal): array
{
    $hasil = [];
    foreach (komposisi_efektif($soal) as $b) {
        $n = (int) $b['sampai'] - (int) $b['dari'] + 1;
        $hasil[$b['tipe']] = ($hasil[$b['tipe']] ?? 0) + $n;
    }
    return $hasil;
}

/** Bobot per jenis (total): ['PG' => 60, 'Uraian' => 40]. */
function komposisi_bobot(array $soal): array
{
    $hasil = [];
    foreach (komposisi_efektif($soal) as $b) {
        $hasil[$b['tipe']] = round(($hasil[$b['tipe']] ?? 0) + (float) $b['bobot'], 2);
    }
    return $hasil;
}

/** Label ringkas: "30 Pilihan Ganda · 5 Uraian". */
function komposisi_label(array $soal): string
{
    $bagian = [];
    foreach (komposisi_efektif($soal) as $b) {
        $n = (int) $b['sampai'] - (int) $b['dari'] + 1;
        $bagian[] = $n . ' ' . tipe_soal_label((string) $b['tipe']);
    }
    return implode(' · ', $bagian);
}

/**
 * Validasi komposisi dari input form.
 * @param array<int|string,array<string,mixed>> $rows
 * @return array{blok: array<int,array{tipe:string,dari:int,sampai:int,bobot:float}>, errors: string[]}
 */
function komposisi_validasi(array $rows): array
{
    $errors = [];
    $blok = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $tipe = (string) ($row['tipe'] ?? '');
        $dari = (int) ($row['dari'] ?? 0);
        $sampai = (int) ($row['sampai'] ?? 0);
        $bobot = (float) str_replace(',', '.', (string) ($row['bobot'] ?? '0'));
        if ($dari <= 0 && $sampai <= 0) {
            continue; // baris kosong
        }
        if (!tipe_soal_valid($tipe)) {
            $errors[] = 'Jenis soal pada salah satu baris komposisi tidak dikenal.';
            continue;
        }
        if ($dari < 1 || $sampai < $dari) {
            $errors[] = 'Nomor urut soal tidak valid (nomor awal minimal 1 dan tidak melebihi nomor akhir).';
            continue;
        }
        if ($bobot < 0 || $bobot > 100) {
            $errors[] = 'Bobot setiap jenis soal harus antara 0 dan 100.';
            continue;
        }
        $blok[] = ['tipe' => $tipe, 'dari' => $dari, 'sampai' => $sampai, 'bobot' => round($bobot, 2)];
    }

    if ($blok === []) {
        $errors[] = 'Komposisi soal wajib diisi minimal satu jenis.';
        return ['blok' => [], 'errors' => $errors];
    }

    usort($blok, static fn (array $a, array $c): int => $a['dari'] <=> $c['dari']);

    $harus = 1;
    foreach ($blok as $b) {
        if ($b['dari'] !== $harus) {
            $errors[] = 'Nomor urut soal harus berurutan mulai 1 tanpa celah atau tumpang tindih '
                . '(periksa nomor ' . $harus . ').';
            break;
        }
        $harus = $b['sampai'] + 1;
    }

    $totalBobot = 0.0;
    foreach ($blok as $b) {
        $totalBobot += (float) $b['bobot'];
    }
    if (round($totalBobot, 2) <= 0) {
        // Semua nol: bagi rata supaya ujian tetap punya bobot.
        $bobotRata = round(100 / count($blok), 2);
        foreach ($blok as $i => $b) {
            $blok[$i]['bobot'] = $bobotRata;
        }
    } elseif (abs($totalBobot - 100) > 0.01) {
        $errors[] = 'Total bobot seluruh jenis soal harus 100% (saat ini ' . fmt_nilai($totalBobot) . '%).';
    }

    return ['blok' => $blok, 'errors' => array_values(array_unique($errors))];
}
