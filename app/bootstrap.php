<?php
declare(strict_types=1);

/**
 * Examora — bootstrap aplikasi.
 * PHP 8.3 native, tanpa framework, tanpa Composer.
 */

define('APP_NAME', 'Examora');
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('VIEW_PATH', BASE_PATH . '/views');
define('STORAGE_PATH', BASE_PATH . '/storage');
/**
 * Versi aplikasi dibaca dari berkas VERSION di root instalasi.
 * Berkas ini juga dipakai menu Update untuk membandingkan dengan repository.
 */
define(
    'DB_UJIAN_VERSION',
    is_file(dirname(__DIR__) . '/VERSION')
        ? trim((string) file_get_contents(dirname(__DIR__) . '/VERSION'))
        : '1.1.0'
);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Jakarta');

$configFile = BASE_PATH . '/config/config.php';
$GLOBALS['config'] = is_file($configFile) ? (array) require $configFile : [];

require APP_PATH . '/helpers.php';
require APP_PATH . '/core.php';
require APP_PATH . '/penilaian.php';
require APP_PATH . '/xlsx.php';

$debug = (bool) ($GLOBALS['config']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (!is_dir(STORAGE_PATH . '/logs')) {
    @mkdir(STORAGE_PATH . '/logs', 0775, true);
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(static function (Throwable $e): void {
    $id = bin2hex(random_bytes(6));
    $line = sprintf("[%s] %s: %s in %s:%d\n%s\n", date('c'), $id, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    @file_put_contents(STORAGE_PATH . '/logs/error.log', $line, FILE_APPEND);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if ((bool) ($GLOBALS['config']['debug'] ?? false)) {
        echo '<pre style="padding:1rem;font:13px/1.5 monospace">' . e($line) . '</pre>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Terjadi kesalahan</title>'
            . '<div style="font-family:system-ui;padding:3rem;max-width:640px;margin:auto">'
            . '<h1 style="color:#234E52">Terjadi kesalahan</h1>'
            . '<p>Sistem mencatat kesalahan ini. Silakan hubungi administrator.</p>'
            . '<p style="color:#718096;font-size:13px">Kode: ' . e($id) . '</p></div>';
    }
    exit;
});

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

/* ------------------------------------------------------------------
 * Sesi PHP.
 *
 * 1. File sesi disimpan di storage/sessions. Folder sesi bawaan PHP
 *    (mis. C:/laragon84/tmp atau /var/lib/php/sessions) biasanya dipakai
 *    bersama aplikasi lain dan dibersihkan garbage collector dengan
 *    gc_maxlifetime lebih pendek — sesi siswa aktif pun bisa terhapus
 *    sehingga siswa tiba-tiba ter-logout walau masih mengerjakan ujian.
 * 2. session.lazy_write (default) tidak memperbarui mtime file sesi
 *    selama isi sesi tidak berubah. Karena itu gc_maxlifetime dibuat
 *    panjang agar sesi yang masih dipakai tidak dianggap kedaluwarsa.
 * 3. Cookie sesi diberi masa berlaku supaya menutup browser atau
 *    proses browser yang dimatikan sistem (umum di HP) tidak langsung
 *    menghapus login. Sesi tetap dibatasi di server: idle 2 jam,
 *    logout, atau reset oleh admin/guru.
 * ------------------------------------------------------------------ */
$sessConf = (array) ($GLOBALS['config']['session'] ?? []);
$sessLifetime = max(3600, (int) ($sessConf['cookie_lifetime'] ?? 43200));
$sessGcMax = max($sessLifetime, (int) ($sessConf['gc_maxlifetime'] ?? 43200));

$sessDir = STORAGE_PATH . '/sessions';
if (!is_dir($sessDir)) {
    @mkdir($sessDir, 0775, true);
}
if (is_dir($sessDir) && is_writable($sessDir)) {
    session_save_path($sessDir);
} else {
    @file_put_contents(
        STORAGE_PATH . '/logs/php-error.log',
        sprintf("[%s] folder sesi tidak dapat ditulis: %s (memakai folder bawaan PHP)\n", date('c'), $sessDir),
        FILE_APPEND
    );
}

ini_set('session.gc_maxlifetime', (string) $sessGcMax);
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');
ini_set('session.use_strict_mode', '1');

session_name('EXAMORASESS');
session_set_cookie_params([
    'lifetime' => $sessLifetime,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => is_https(),
]);
session_start();

ob_start();

/* ------------------------------------------------------------------
 * Migrasi skema ringan.
 * Kolom kelas.tingkat semula ENUM('X','XI','XII','-') — kini mengikuti
 * jenjang sekolah, sehingga dikonversi ke VARCHAR(12) sekali jalan lalu
 * ditandai di tabel pengaturan. Kegagalan (mis. saat halaman instalasi
 * atau DB belum siap) tidak menggagalkan request.
 * ------------------------------------------------------------------ */
$tingkatType = null;
try {
    if (DB::scalar("SELECT nilai FROM pengaturan WHERE kunci = 'schema_kelas_tingkat'") !== 'varchar') {
        $tingkatType = (string) DB::scalar(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kelas' AND COLUMN_NAME = 'tingkat'"
        );
    }
} catch (Throwable) {
    $tingkatType = null; // DB/skema belum siap — biarkan.
}

if (is_string($tingkatType) && $tingkatType !== '') {
    if (stripos($tingkatType, 'enum') === 0) {
        try {
            DB::exec("ALTER TABLE kelas MODIFY tingkat VARCHAR(12) NOT NULL DEFAULT '-'");
            set_setting('schema_kelas_tingkat', 'varchar');
        } catch (Throwable $e) {
            @file_put_contents(
                STORAGE_PATH . '/logs/php-error.log',
                sprintf("[%s] migrasi kelas.tingkat gagal: %s\n", date('c'), $e->getMessage()),
                FILE_APPEND
            );
        }
    } else {
        try {
            set_setting('schema_kelas_tingkat', 'varchar');
        } catch (Throwable) {
            // tandai nanti pada request berikutnya
        }
    }
}

/* Migrasi soal.agama_target — target peserta per agama (opsional). */
$agamaTargetMarker = null;
try {
    $agamaTargetMarker = DB::scalar("SELECT nilai FROM pengaturan WHERE kunci = 'schema_soal_agama'");
} catch (Throwable) {
    $agamaTargetMarker = null; // DB/skema belum siap.
}

if ($agamaTargetMarker !== '1') {
    try {
        $adaTabelSoal = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal'"
        );
        if ($adaTabelSoal > 0) {
            $adaKolom = (int) DB::scalar(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal' AND COLUMN_NAME = 'agama_target'"
            );
            if ($adaKolom === 0) {
                DB::exec('ALTER TABLE soal ADD COLUMN agama_target VARCHAR(20) NULL AFTER mapel');
            }
            set_setting('schema_soal_agama', '1');
        }
    } catch (Throwable $e) {
        @file_put_contents(
            STORAGE_PATH . '/logs/php-error.log',
            sprintf("[%s] migrasi soal.agama_target gagal: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND
        );
    }
}

/* Migrasi soal.min_pengerjaan — durasi minimal sebelum tombol kumpulkan aktif. */
$minPengerjaanMarker = null;
try {
    $minPengerjaanMarker = DB::scalar("SELECT nilai FROM pengaturan WHERE kunci = 'schema_soal_min_pengerjaan'");
} catch (Throwable) {
    $minPengerjaanMarker = null;
}

if ($minPengerjaanMarker !== '1') {
    try {
        $adaTabelSoal = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal'"
        );
        if ($adaTabelSoal > 0) {
            $adaKolom = (int) DB::scalar(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal' AND COLUMN_NAME = 'min_pengerjaan'"
            );
            if ($adaKolom === 0) {
                DB::exec('ALTER TABLE soal ADD COLUMN min_pengerjaan INT NOT NULL DEFAULT 0 AFTER durasi');
            }
            set_setting('schema_soal_min_pengerjaan', '1');
        }
    } catch (Throwable $e) {
        @file_put_contents(
            STORAGE_PATH . '/logs/php-error.log',
            sprintf("[%s] migrasi soal.min_pengerjaan gagal: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND
        );
    }
}

/* Migrasi kunci layar anti-curang: status kunci disimpan di server agar
   tidak bisa dibuka hanya dengan memuat ulang halaman. */
$lockMarker = null;
try {
    $lockMarker = DB::scalar("SELECT nilai FROM pengaturan WHERE kunci = 'schema_riwayat_terkunci'");
} catch (Throwable) {
    $lockMarker = null;
}

if ($lockMarker !== '1') {
    try {
        $adaTabelRiwayat = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'riwayat_ujian'"
        );
        if ($adaTabelRiwayat > 0) {
            $kolom = [
                'terkunci' => 'ALTER TABLE riwayat_ujian ADD COLUMN terkunci TINYINT(1) NOT NULL DEFAULT 0 AFTER pelanggaran',
                'dikunci_pada' => 'ALTER TABLE riwayat_ujian ADD COLUMN dikunci_pada DATETIME NULL AFTER terkunci',
            ];
            foreach ($kolom as $nama => $sql) {
                $adaKolom = (int) DB::scalar(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'riwayat_ujian' AND COLUMN_NAME = ?",
                    [$nama]
                );
                if ($adaKolom === 0) {
                    DB::exec($sql);
                }
            }
            set_setting('schema_riwayat_terkunci', '1');
        }
    } catch (Throwable $e) {
        @file_put_contents(
            STORAGE_PATH . '/logs/php-error.log',
            sprintf("[%s] migrasi riwayat_ujian.terkunci gagal: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND
        );
    }
}

/* Migrasi target peserta fleksibel: per jenjang, per kelas, atau per siswa. */
$targetModeMarker = null;
try {
    $targetModeMarker = DB::scalar("SELECT nilai FROM pengaturan WHERE kunci = 'schema_soal_target'");
} catch (Throwable) {
    $targetModeMarker = null;
}

if ($targetModeMarker !== '1') {
    try {
        $adaTabelSoal = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal'"
        );
        if ($adaTabelSoal > 0) {
            $adaKolom = (int) DB::scalar(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal' AND COLUMN_NAME = 'target_mode'"
            );
            if ($adaKolom === 0) {
                DB::exec("ALTER TABLE soal ADD COLUMN target_mode ENUM('kelas','jenjang','siswa') NOT NULL DEFAULT 'kelas' AFTER agama_target");
            }
            DB::exec(
                'CREATE TABLE IF NOT EXISTS soal_jenjang (
                    id_soal INT UNSIGNED NOT NULL,
                    tingkat VARCHAR(12) NOT NULL,
                    PRIMARY KEY (id_soal, tingkat),
                    CONSTRAINT fk_sj_soal FOREIGN KEY (id_soal) REFERENCES soal(id) ON DELETE CASCADE
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            DB::exec(
                'CREATE TABLE IF NOT EXISTS soal_siswa (
                    id_soal  INT UNSIGNED NOT NULL,
                    id_siswa INT UNSIGNED NOT NULL,
                    PRIMARY KEY (id_soal, id_siswa),
                    CONSTRAINT fk_ss_soal  FOREIGN KEY (id_soal)  REFERENCES soal(id)  ON DELETE CASCADE,
                    CONSTRAINT fk_ss_siswa FOREIGN KEY (id_siswa) REFERENCES siswa(id) ON DELETE CASCADE
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            set_setting('schema_soal_target', '1');
        }
    } catch (Throwable $e) {
        @file_put_contents(
            STORAGE_PATH . '/logs/php-error.log',
            sprintf("[%s] migrasi target peserta gagal: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND
        );
    }
}

/* Migrasi siswa_sesi — dukungan banyak perangkat login + batas login siswa.
   Satu baris = satu sesi login siswa; dihapus saat logout atau reset admin. */
$sesiSiswaMarker = null;
try {
    $sesiSiswaMarker = DB::scalar("SELECT nilai FROM pengaturan WHERE kunci = 'schema_siswa_sesi'");
} catch (Throwable) {
    $sesiSiswaMarker = null;
}

if ($sesiSiswaMarker !== '1') {
    try {
        $adaTabelSiswa = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'siswa'"
        );
        if ($adaTabelSiswa > 0) {
            DB::exec(
                'CREATE TABLE IF NOT EXISTS siswa_sesi (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    id_siswa   INT UNSIGNED NOT NULL,
                    sid        CHAR(32) NOT NULL,
                    php_sid    VARCHAR(128) NULL,
                    browser    VARCHAR(20) NULL,
                    user_agent VARCHAR(250) NULL,
                    ip         VARCHAR(45) NULL,
                    login_at   DATETIME NOT NULL,
                    last_seen  DATETIME NOT NULL,
                    UNIQUE KEY uq_sesi_sid (sid),
                    KEY idx_sesi_siswa (id_siswa, last_seen),
                    CONSTRAINT fk_sesi_siswa FOREIGN KEY (id_siswa) REFERENCES siswa(id) ON DELETE CASCADE
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            set_setting('schema_siswa_sesi', '1');
        }
    } catch (Throwable $e) {
        @file_put_contents(
            STORAGE_PATH . '/logs/php-error.log',
            sprintf("[%s] migrasi tabel siswa_sesi gagal: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND
        );
    }
}

/* Migrasi mode soal: aplikasi eksklusif PDF Google Drive.
   Mode "ketik langsung" (NATIVE) dan "campuran" (CAMPURAN) dihapus; data lama
   diubah menjadi PDF agar konsisten dengan validasi baru. */
$pdfOnlyMarker = null;
try {
    $pdfOnlyMarker = DB::scalar("SELECT nilai FROM pengaturan WHERE kunci = 'schema_soal_pdf_only'");
} catch (Throwable) {
    $pdfOnlyMarker = null;
}

if ($pdfOnlyMarker !== '1') {
    try {
        $adaTabelSoal = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal'"
        );
        if ($adaTabelSoal > 0) {
            DB::exec("ALTER TABLE soal MODIFY mode_soal VARCHAR(20) NOT NULL DEFAULT 'PDF'");
            DB::exec("UPDATE soal SET mode_soal = 'PDF' WHERE mode_soal <> 'PDF' OR mode_soal IS NULL");
            set_setting('schema_soal_pdf_only', '1');
        }
    } catch (Throwable $e) {
        @file_put_contents(
            STORAGE_PATH . '/logs/php-error.log',
            sprintf("[%s] migrasi mode soal PDF gagal: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND
        );
    }
}

/* Migrasi soal_kolaborator — guru lain yang diberi akses penuh ke satu ujian. */
$kolabMarker = null;
try {
    $kolabMarker = DB::scalar("SELECT nilai FROM pengaturan WHERE kunci = 'schema_soal_kolaborator'");
} catch (Throwable) {
    $kolabMarker = null;
}

if ($kolabMarker !== '1') {
    try {
        $adaSoal = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal'"
        );
        $adaUsers = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
        );
        if ($adaSoal > 0 && $adaUsers > 0) {
            DB::exec(
                'CREATE TABLE IF NOT EXISTS soal_kolaborator (
                    id_soal    INT UNSIGNED NOT NULL,
                    id_user    INT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_soal, id_user),
                    KEY idx_kolab_user (id_user),
                    CONSTRAINT fk_kolab_soal FOREIGN KEY (id_soal) REFERENCES soal(id) ON DELETE CASCADE,
                    CONSTRAINT fk_kolab_user FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            set_setting('schema_soal_kolaborator', '1');
        }
    } catch (Throwable $e) {
        @file_put_contents(
            STORAGE_PATH . '/logs/php-error.log',
            sprintf("[%s] migrasi tabel soal_kolaborator gagal: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND
        );
    }
}

/* Migrasi soal.komposisi — daftar blok jenis soal + bobot (JSON):
   [{"tipe":"PG","dari":1,"sampai":30,"bobot":60}, ...]
   Ujian lama tanpa kolom ini tetap dinilai memakai jml_pg/jml_uraian. */
$komposisiMarker = null;
try {
    $komposisiMarker = DB::scalar("SELECT nilai FROM pengaturan WHERE kunci = 'schema_soal_komposisi'");
} catch (Throwable) {
    $komposisiMarker = null;
}

if ($komposisiMarker !== '1') {
    try {
        $adaSoal = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal'"
        );
        if ($adaSoal > 0) {
            $adaKolom = (int) DB::scalar(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soal' AND COLUMN_NAME = 'komposisi'"
            );
            if ($adaKolom === 0) {
                DB::exec('ALTER TABLE soal ADD COLUMN komposisi TEXT NULL AFTER bobot_uraian');
            }
            set_setting('schema_soal_komposisi', '1');
        }
    } catch (Throwable $e) {
        @file_put_contents(
            STORAGE_PATH . '/logs/php-error.log',
            sprintf("[%s] migrasi soal.komposisi gagal: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND
        );
    }
}
