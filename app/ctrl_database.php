<?php
declare(strict_types=1);

/**
 * Pengelolaan database: hapus data terpilih, backup, dan restore.
 * Seluruh aksi hanya untuk superadmin.
 */
final class DatabaseController
{
    private const BACKUP_MARKER = '-- EXAMORA-BACKUP v1';
    private const MAX_RESTORE_BYTES = 67108864; // 64 MB

    /** Urutan tabel mengikuti dependensi foreign key. */
    private const TABLE_ORDER = [
        'users',
        'kelas',
        'siswa',
        'soal',
        'soal_kelas',
        'soal_jenjang',
        'soal_siswa',
        'butir_soal',
        'riwayat_ujian',
        'jawaban_siswa',
        'log_pelanggaran',
        'audit_log',
        'pengaturan',
    ];

    /** Bagian data yang bisa dipilih saat membuat backup. */
    private const BACKUP_GROUPS = [
        'riwayat' => [
            'label' => 'Hasil & Riwayat Ujian',
            'desc' => 'riwayat_ujian, jawaban_siswa, log_pelanggaran',
            'tables' => ['riwayat_ujian', 'jawaban_siswa', 'log_pelanggaran'],
        ],
        'soal' => [
            'label' => 'Ujian & Bank Soal',
            'desc' => 'soal, butir_soal, target peserta, dan kolaborator',
            'tables' => ['soal', 'butir_soal', 'soal_kelas', 'soal_jenjang', 'soal_siswa', 'soal_kolaborator'],
        ],
        'siswa' => [
            'label' => 'Data Siswa',
            'desc' => 'siswa dan sesi login siswa',
            'tables' => ['siswa', 'siswa_sesi'],
        ],
        'kelas' => [
            'label' => 'Data Kelas',
            'desc' => 'kelas',
            'tables' => ['kelas'],
        ],
        'akun' => [
            'label' => 'Akun Pengguna',
            'desc' => 'users (guru dan superadmin)',
            'tables' => ['users'],
        ],
        'audit' => [
            'label' => 'Audit Log',
            'desc' => 'audit_log',
            'tables' => ['audit_log'],
        ],
        'pengaturan' => [
            'label' => 'Pengaturan Aplikasi',
            'desc' => 'pengaturan (identitas sekolah, KKM, pengawasan ujian)',
            'tables' => ['pengaturan'],
        ],
    ];

    public function index(): void
    {
        Auth::requireSuperadmin();

        $counts = [
            'riwayat' => (int) DB::scalar('SELECT COUNT(*) FROM riwayat_ujian'),
            'soal' => (int) DB::scalar('SELECT COUNT(*) FROM soal'),
            'siswa' => (int) DB::scalar('SELECT COUNT(*) FROM siswa'),
            'kelas' => (int) DB::scalar('SELECT COUNT(*) FROM kelas'),
            'guru' => (int) DB::scalar("SELECT COUNT(*) FROM users WHERE role = 'guru'"),
            'audit' => (int) DB::scalar('SELECT COUNT(*) FROM audit_log'),
            'pelanggaran' => (int) DB::scalar('SELECT COUNT(*) FROM log_pelanggaran'),
        ];

        View::render('pengaturan/database', [
            'counts' => $counts,
            'backupGroups' => self::BACKUP_GROUPS,
            'uploadMax' => $this->uploadLimitLabel(),
        ]);
    }

    public function hapus(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();

        $boleh = ['riwayat', 'soal', 'siswa', 'kelas', 'guru', 'audit', 'pelanggaran'];
        $pilihan = array_values(array_intersect($boleh, (array) ($_POST['hapus'] ?? [])));
        $konfirmasi = strtoupper(trim((string) post('konfirmasi', '')));

        if ($pilihan === []) {
            flash_error('Pilih minimal satu jenis data yang ingin dihapus.');
            redirect('database');
        }
        if ($konfirmasi !== 'HAPUS') {
            flash_error('Ketik HAPUS pada kolom konfirmasi untuk melanjutkan.');
            redirect('database');
        }

        $jumlah = [];
        DB::tx(function () use ($pilihan, &$jumlah): void {
            if (in_array('riwayat', $pilihan, true)) {
                $jumlah['riwayat'] = DB::exec('DELETE FROM riwayat_ujian');
            }
            if (in_array('soal', $pilihan, true)) {
                $jumlah['soal'] = DB::exec('DELETE FROM soal');
            }
            if (in_array('siswa', $pilihan, true)) {
                $jumlah['siswa'] = DB::exec('DELETE FROM siswa');
            }
            if (in_array('kelas', $pilihan, true)) {
                $jumlah['kelas'] = DB::exec('DELETE FROM kelas');
            }
            if (in_array('guru', $pilihan, true)) {
                $jumlah['guru'] = DB::exec("DELETE FROM users WHERE role = 'guru'");
            }
            if (in_array('pelanggaran', $pilihan, true)) {
                $jumlah['pelanggaran'] = DB::exec('DELETE FROM log_pelanggaran');
            }
            if (in_array('audit', $pilihan, true)) {
                $jumlah['audit'] = DB::exec('DELETE FROM audit_log');
            }
        });

        audit('database_hapus', null, null, ['bagian' => $pilihan, 'jumlah' => $jumlah]);

        $total = array_sum($jumlah);
        flash_success('Data terpilih berhasil dihapus (' . number_format($total, 0, ',', '.') . ' baris).');
        redirect('database');
    }

    public function backup(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();

        $pilihan = array_values(array_intersect(
            array_keys(self::BACKUP_GROUPS),
            (array) ($_POST['backup'] ?? [])
        ));
        if ($pilihan === []) {
            flash_error('Pilih minimal satu bagian data yang ingin dibackup.');
            redirect('database');
        }

        set_time_limit(0);

        $pdo = DB::pdo();
        $tables = $this->backupTables($pdo, $pilihan);
        if ($tables === []) {
            flash_error('Tidak ada tabel yang dapat dibackup untuk pilihan tersebut.');
            redirect('database');
        }
        $namaDb = (string) (($GLOBALS['config']['db']['name'] ?? 'database'));
        $namaFile = 'examora-backup-' . date('Ymd-His') . '.sql';

        audit('database_backup', null, null, ['bagian' => $pilihan]);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $namaFile . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo self::BACKUP_MARKER . "\n";
        echo '-- Database: ' . $namaDb . "\n";
        echo '-- Bagian: ' . implode(', ', $pilihan) . "\n";
        echo '-- Dibuat: ' . date('Y-m-d H:i:s') . " (Asia/Jakarta)\n";
        echo '-- Aplikasi: ' . APP_NAME . ' v' . DB_UJIAN_VERSION . "\n";
        echo "SET NAMES utf8mb4;\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $safe = $this->quoteIdent($table);
            $createRow = $pdo->query('SHOW CREATE TABLE ' . $safe)->fetch(PDO::FETCH_NUM);
            $create = (string) ($createRow[1] ?? '');
            if ($create === '') {
                continue;
            }

            echo "DROP TABLE IF EXISTS {$safe};\n";
            echo $create . ";\n\n";

            $columns = [];
            $binary = [];
            foreach ($pdo->query('SHOW COLUMNS FROM ' . $safe)->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[] = (string) $col['Field'];
                $type = strtolower((string) ($col['Type'] ?? ''));
                $binary[(string) $col['Field']] = str_contains($type, 'binary') || str_contains($type, 'blob');
            }
            if ($columns === []) {
                continue;
            }

            $colSql = implode(', ', array_map(fn (string $c): string => $this->quoteIdent($c), $columns));
            $rowsSql = [];
            $result = $pdo->query('SELECT * FROM ' . $safe);

            while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $this->sqlValue($pdo, $row[$column] ?? null, (bool) ($binary[$column] ?? false));
                }
                $rowsSql[] = '(' . implode(', ', $values) . ')';

                if (count($rowsSql) >= 40) {
                    echo 'INSERT INTO ' . $safe . ' (' . $colSql . ") VALUES\n"
                        . implode(",\n", $rowsSql) . ";\n";
                    $rowsSql = [];
                }
            }
            if ($rowsSql !== []) {
                echo 'INSERT INTO ' . $safe . ' (' . $colSql . ") VALUES\n"
                    . implode(",\n", $rowsSql) . ";\n";
            }
            echo "\n";
        }

        echo "SET FOREIGN_KEY_CHECKS=1;\n";
        exit;
    }

    public function restore(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();

        if (empty($_POST['yakin'])) {
            flash_error('Centang pernyataan konfirmasi sebelum melakukan restore.');
            redirect('database');
        }

        set_time_limit(0);
        @ini_set('memory_limit', '256M');
        if (empty($_FILES['backup']['name']) || ($_FILES['backup']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash_error('Pilih file backup .sql terlebih dahulu.');
            redirect('database');
        }
        if ((int) $_FILES['backup']['size'] > self::MAX_RESTORE_BYTES) {
            flash_error('Ukuran file backup maksimal 64 MB.');
            redirect('database');
        }

        $isi = (string) file_get_contents((string) $_FILES['backup']['tmp_name']);
        $awal = ltrim($isi);
        if (strncmp($awal, self::BACKUP_MARKER, strlen(self::BACKUP_MARKER)) !== 0) {
            flash_error('File bukan backup Examora yang valid (header tidak dikenal).');
            redirect('database');
        }

        $statements = $this->splitSql($isi);
        if ($statements === []) {
            flash_error('File backup tidak berisi pernyataan SQL.');
            redirect('database');
        }

        $pdo = DB::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $selesai = 0;
        try {
            foreach ($statements as $sql) {
                if (trim($sql) === '') {
                    continue;
                }
                $pdo->exec($sql);
                $selesai++;
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e) {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
            }
            audit('database_restore_gagal', null, null, ['pernyataan' => $selesai]);
            flash_error('Restore gagal pada pernyataan ke-' . ($selesai + 1) . ': ' . $e->getMessage());
            redirect('database');
        }

        audit('database_restore', null, null, ['pernyataan' => $selesai]);
        flash_success('Database berhasil dipulihkan dari backup (' . number_format($selesai, 0, ',', '.') . ' pernyataan).');
        redirect('database');
    }

    /**
     * Daftar tabel yang ikut backup sesuai bagian yang dipilih.
     * Bila semua bagian dipilih, tabel lain di luar daftar (mis. hasil migrasi
     * baru) tetap disertakan agar backup penuh tidak kehilangan data.
     *
     * @param list<string> $pilihan
     * @return list<string>
     */
    private function backupTables(PDO $pdo, array $pilihan): array
    {
        $ada = $this->tables($pdo);
        $semua = count($pilihan) === count(self::BACKUP_GROUPS);
        $dipilih = [];
        foreach ($pilihan as $key) {
            foreach (self::BACKUP_GROUPS[$key]['tables'] as $tabel) {
                $dipilih[$tabel] = true;
            }
        }
        $hasil = [];
        foreach ($ada as $tabel) {
            if ($semua || isset($dipilih[$tabel])) {
                $hasil[] = $tabel;
            }
        }
        return $hasil;
    }

    /** @return list<string> */
    private function tables(PDO $pdo): array
    {
        $ada = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $ada = array_map('strval', $ada);
        $urut = [];
        foreach (self::TABLE_ORDER as $t) {
            if (in_array($t, $ada, true)) {
                $urut[] = $t;
            }
        }
        sort($ada);
        foreach ($ada as $t) {
            if (!in_array($t, $urut, true)) {
                $urut[] = $t;
            }
        }
        return $urut;
    }

    private function quoteIdent(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function sqlValue(PDO $pdo, mixed $value, bool $binary): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if ($binary) {
            $raw = (string) $value;
            return $raw === '' ? "''" : '0x' . bin2hex($raw);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return $pdo->quote((string) $value);
    }

    /**
     * Pisahkan dump SQL menjadi pernyataan tanpa memecah titik koma di dalam
     * string, identifier, atau komentar.
     *
     * @return list<string>
     */
    private function splitSql(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($lineComment) {
                $buf .= $ch;
                if ($ch === "\n") {
                    $lineComment = false;
                }
                continue;
            }
            if ($blockComment) {
                $buf .= $ch;
                if ($ch === '*' && $next === '/') {
                    $buf .= $next;
                    $i++;
                    $blockComment = false;
                }
                continue;
            }
            if ($quote !== null) {
                $buf .= $ch;
                if ($ch === '\\') {
                    if ($next !== '') {
                        $buf .= $next;
                        $i++;
                    }
                    continue;
                }
                if ($ch === $quote) {
                    if ($quote === "'" && $next === "'") {
                        $buf .= $next;
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === '-' && $next === '-') {
                $lineComment = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === '#') {
                $lineComment = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $blockComment = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === ';') {
                $stmt = trim($buf);
                if ($stmt !== '') {
                    $out[] = $stmt;
                }
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }

        $stmt = trim($buf);
        if ($stmt !== '') {
            $out[] = $stmt;
        }
        return $out;
    }

    private function uploadLimitLabel(): string
    {
        $parse = static function (string $v): int {
            $v = trim($v);
            if ($v === '') {
                return 0;
            }
            $unit = strtolower(substr($v, -1));
            $num = (float) $v;
            return (int) match ($unit) {
                'g' => $num * 1024 * 1024 * 1024,
                'm' => $num * 1024 * 1024,
                'k' => $num * 1024,
                default => $num,
            };
        };
        $limits = array_filter([
            $parse((string) ini_get('upload_max_filesize')),
            $parse((string) ini_get('post_max_size')),
        ], static fn (int $n): bool => $n > 0);

        if ($limits === []) {
            return '64 MB';
        }
        $min = min(min($limits), self::MAX_RESTORE_BYTES);
        return $min >= 1048576
            ? round($min / 1048576, 1) . ' MB'
            : round($min / 1024) . ' KB';
    }
}
