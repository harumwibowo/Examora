<?php
declare(strict_types=1);

final class AdminController
{
    /* ------------------------------------------------------------
     * Dashboard
     * ---------------------------------------------------------- */
    public function dashboard(): void
    {
        Auth::requireStaff();
        $isSuper = Auth::isSuperadmin();
        $guruId = (int) Auth::id();

        $totalKelas = (int) DB::scalar('SELECT COUNT(*) FROM kelas');
        if ($isSuper) {
            $totalSiswa = (int) DB::scalar('SELECT COUNT(*) FROM siswa');
        } else {
            $totalSiswa = (int) DB::scalar(
                'SELECT COUNT(DISTINCT sw.id) FROM siswa sw
                 JOIN kelas k ON k.id = sw.id_kelas
                 WHERE EXISTS (
                    SELECT 1 FROM soal s WHERE (s.id_guru = ? OR EXISTS (
                        SELECT 1 FROM soal_kolaborator skl WHERE skl.id_soal = s.id AND skl.id_user = ?)) AND (
                        (s.target_mode = \'kelas\' AND EXISTS (
                            SELECT 1 FROM soal_kelas sk WHERE sk.id_soal = s.id AND sk.id_kelas = sw.id_kelas))
                        OR (s.target_mode = \'jenjang\' AND EXISTS (
                            SELECT 1 FROM soal_jenjang sj WHERE sj.id_soal = s.id AND sj.tingkat = k.tingkat))
                        OR (s.target_mode = \'siswa\' AND EXISTS (
                            SELECT 1 FROM soal_siswa ss WHERE ss.id_soal = s.id AND ss.id_siswa = sw.id))
                    )
                    AND (s.agama_target IS NULL OR TRIM(s.agama_target) = \'\'
                         OR LOWER(TRIM(s.agama_target)) = LOWER(TRIM(sw.agama)))
                 )',
                [$guruId, $guruId]
            );
        }

        [$aksesSql, $aksesParams] = soal_akses_sql('s');
        $ujianAktif = (int) DB::scalar("SELECT COUNT(*) FROM soal s WHERE s.status = 'Aktif' AND " . $aksesSql, $aksesParams);
        $berlangsung = (int) DB::scalar(
            "SELECT COUNT(*) FROM riwayat_ujian ru JOIN soal s ON s.id = ru.id_soal
             WHERE ru.status = 'Berlangsung' AND " . $aksesSql,
            $aksesParams
        );
        $menunggu = (int) DB::scalar(
            "SELECT COUNT(*) FROM riwayat_ujian ru JOIN soal s ON s.id = ru.id_soal
             WHERE ru.status = 'Menunggu Koreksi' AND " . $aksesSql,
            $aksesParams
        );

        $resetRequest = (int) DB::scalar('SELECT COUNT(*) FROM siswa WHERE request_reset = 1');

        $recent = DB::all(
            'SELECT s.*, u.nama AS guru_nama,
                    (SELECT COUNT(*) FROM riwayat_ujian r WHERE r.id_soal = s.id AND r.waktu_selesai IS NOT NULL) AS peserta
             FROM soal s JOIN users u ON u.id = s.id_guru
             WHERE ' . $aksesSql . '
             ORDER BY s.created_at DESC LIMIT 6',
            $aksesParams
        );

        View::render('dashboard', compact(
            'totalKelas', 'totalSiswa', 'ujianAktif',
            'berlangsung', 'menunggu', 'resetRequest', 'recent', 'isSuper'
        ));
    }

    /* ------------------------------------------------------------
     * Akun guru
     * ---------------------------------------------------------- */
    public function guruIndex(): void
    {
        Auth::requireSuperadmin();
        $q = str_input('q', '', $_GET);
        if ($q !== '') {
            $rows = DB::all(
                "SELECT u.*, (SELECT COUNT(*) FROM soal s WHERE s.id_guru = u.id) AS jumlah_soal
                 FROM users u WHERE u.role = 'guru' AND (u.nama LIKE ? OR u.email LIKE ?)
                 ORDER BY u.nama",
                ['%' . $q . '%', '%' . $q . '%']
            );
        } else {
            $rows = DB::all(
                "SELECT u.*, (SELECT COUNT(*) FROM soal s WHERE s.id_guru = u.id) AS jumlah_soal
                 FROM users u WHERE u.role = 'guru' ORDER BY u.nama"
            );
        }
        View::render('guru/index', ['rows' => $rows, 'q' => $q, 'imporReport' => $this->takeReport('impor_report_guru')]);
    }

    public function guruForm(): void
    {
        Auth::requireSuperadmin();
        $id = int_input('id', 0, $_GET);
        $row = $id > 0 ? DB::one('SELECT * FROM users WHERE id = ? AND role = ?', [$id, 'guru']) : null;
        View::render('guru/form', ['row' => $row]);
    }

    public function guruSave(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $id = int_input('id');
        $nama = str_input('nama');
        $email = strtolower(str_input('email'));
        $password = (string) post('password', '');
        $aktif = int_input('aktif', 1);

        $v = new Validator($_POST);
        $v->required('nama', 'Nama')->required('email', 'Email')->email('email', 'Email');
        if ($v->fails()) {
            flash_error($v->first());
            keep_old($_POST);
            redirect('guru_form', $id ? ['id' => $id] : []);
        }

        $dupe = DB::one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id]);
        if ($dupe) {
            flash_error('Email sudah dipakai akun lain.');
            keep_old($_POST);
            redirect('guru_form', $id ? ['id' => $id] : []);
        }

        if ($id > 0) {
            $target = DB::one('SELECT * FROM users WHERE id = ? AND role = ?', [$id, 'guru']);
            if (!$target) {
                throw new Abort('Akun guru tidak ditemukan.');
            }
            if ($password !== '') {
                if (strlen($password) < 8) {
                    flash_error('Password minimal 8 karakter.');
                    redirect('guru_form', ['id' => $id]);
                }
                DB::exec('UPDATE users SET nama=?, email=?, aktif=?, password_hash=? WHERE id=?',
                    [$nama, $email, $aktif, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                DB::exec('UPDATE users SET nama=?, email=?, aktif=? WHERE id=?', [$nama, $email, $aktif, $id]);
            }
            audit('guru_ubah', 'users', $id);
            flash_success('Data guru diperbarui.');
        } else {
            if (strlen($password) < 8) {
                flash_error('Password minimal 8 karakter.');
                keep_old($_POST);
                redirect('guru_form');
            }
            $newId = DB::insert(
                'INSERT INTO users (nama, email, password_hash, role, aktif) VALUES (?,?,?,?,?)',
                [$nama, $email, password_hash($password, PASSWORD_DEFAULT), 'guru', $aktif]
            );
            audit('guru_tambah', 'users', $newId);
            flash_success('Akun guru dibuat.');
        }
        clear_old();
        redirect('guru');
    }

    public function guruToggle(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $id = int_input('id');
        $row = DB::one('SELECT * FROM users WHERE id = ? AND role = ?', [$id, 'guru']);
        if ($row) {
            $baru = (int) $row['aktif'] === 1 ? 0 : 1;
            DB::exec('UPDATE users SET aktif = ? WHERE id = ?', [$baru, $id]);
            audit('guru_status', 'users', $id, ['aktif' => $baru]);
            flash_success('Status guru diperbarui.');
        }
        redirect('guru');
    }

    public function guruDelete(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $id = int_input('id');
        $jml = (int) DB::scalar('SELECT COUNT(*) FROM soal WHERE id_guru = ?', [$id]);
        if ($jml > 0) {
            flash_error('Guru masih memiliki ' . $jml . ' ujian. Pindahkan atau hapus ujian tersebut terlebih dahulu.');
            redirect('guru');
        }
        DB::exec('DELETE FROM users WHERE id = ? AND role = ?', [$id, 'guru']);
        audit('guru_hapus', 'users', $id);
        flash_success('Akun guru dihapus.');
        redirect('guru');
    }

    public function guruImport(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $rows = $this->csvRows($_FILES['csv'] ?? null, 'guru');

        $map = [];
        $start = 0;
        if ($this->looksLikeHeader($rows[0])) {
            $map = $this->columnMap($rows[0], [
                'nama' => ['nama', 'nama guru', 'nama lengkap', 'name', 'full name'],
                'email' => ['email', 'e-mail', 'surel', 'alamat email', 'email address'],
                'password' => ['password', 'pass', 'sandi', 'kata sandi'],
                'status' => ['status', 'aktif', 'keaktifan', 'active'],
            ]);
            $start = 1;
        }

        $ok = 0;
        $gagal = [];
        $akun = [];

        for ($i = $start; $i < count($rows); $i++) {
            $cols = $rows[$i];
            $email = $this->rowEmail($cols, $map['email'] ?? null);

            $emailIdx = null;
            foreach ($cols as $idx => $cell) {
                if (strtolower(trim((string) $cell)) === $email) {
                    $emailIdx = $idx;
                    break;
                }
            }

            $nama = trim((string) ($cols[$map['nama'] ?? 0] ?? ''));
            if ($nama === '' || filter_var(strtolower($nama), FILTER_VALIDATE_EMAIL)) {
                $nama = '';
                foreach ($cols as $idx => $cell) {
                    $cell = trim((string) $cell);
                    if ($cell === '' || $idx === $emailIdx || filter_var(strtolower($cell), FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    $nama = $cell;
                    break;
                }
            }
            if ($nama === '') {
                $gagal[] = 'Baris ' . ($i + 1) . ': nama kosong (' . $this->rowPreview($cols) . ').';
                continue;
            }
            if ($email === '') {
                $gagal[] = 'Baris ' . ($i + 1) . ': email tidak ditemukan (' . $this->rowPreview($cols) . ').';
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $gagal[] = 'Baris ' . ($i + 1) . ': email "' . $email . '" tidak valid.';
                continue;
            }
            if (DB::one('SELECT id FROM users WHERE email = ?', [$email])) {
                $gagal[] = 'Baris ' . ($i + 1) . ': email "' . $email . '" sudah dipakai.';
                continue;
            }

            $pass = trim((string) ($cols[$map['password'] ?? -1] ?? ''));
            $statusRaw = trim((string) ($cols[$map['status'] ?? -1] ?? ''));
            if ($emailIdx !== null) {
                if ($pass === '') {
                    $pass = trim((string) ($cols[$emailIdx + 1] ?? ''));
                }
                if ($statusRaw === '') {
                    $statusRaw = trim((string) ($cols[$emailIdx + 2] ?? ''));
                }
            }
            if ($pass !== '' && strlen($pass) < 8) {
                $gagal[] = 'Baris ' . ($i + 1) . ': password minimal 8 karakter.';
                continue;
            }
            $aktif = in_array(strtolower($statusRaw), ['0', 'tidak', 'tidak aktif', 'nonaktif', 'non-aktif', 'non aktif'], true) ? 0 : 1;
            if ($pass === '') {
                $pass = random_password(8);
            }

            try {
                DB::insert(
                    'INSERT INTO users (nama, email, password_hash, role, aktif) VALUES (?,?,?,?,?)',
                    [$nama, $email, password_hash($pass, PASSWORD_DEFAULT), 'guru', $aktif]
                );
            } catch (Throwable $e) {
                $gagal[] = 'Baris ' . ($i + 1) . ': ' . $e->getMessage();
                continue;
            }
            $akun[] = ['nama' => $nama, 'email' => $email, 'password' => $pass];
            $ok++;
        }

        audit('guru_impor', 'users', null, ['berhasil' => $ok, 'gagal' => count($gagal)]);
        $_SESSION['impor_report_guru'] = [
            'judul' => 'Hasil Impor Data Guru',
            'ok' => $ok,
            'gagal' => $gagal,
            'akun' => $akun,
        ];
        redirect('guru', ['impor' => 1]);
    }

    public function guruTemplate(): void
    {
        Auth::requireSuperadmin();
        $csv = "Nama;Email;Password;Status\nBudi Santoso;budi.santoso@sekolah.id;rahasia123;Aktif\nSiti Aminah;siti.aminah@sekolah.id;;Aktif\n";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template-guru.csv"');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }

    /* ------------------------------------------------------------
     * Kelas
     * ---------------------------------------------------------- */
    public function kelasIndex(): void
    {
        Auth::requireSuperadmin();
        $opsiTingkat = tingkat_options();
        $ph = implode(',', array_fill(0, count($opsiTingkat), '?'));
        $rows = DB::all(
            "SELECT k.*, (SELECT COUNT(*) FROM siswa s WHERE s.id_kelas = k.id) AS jumlah_siswa
             FROM kelas k
             ORDER BY (k.tingkat = '-'), (FIELD(k.tingkat, {$ph}) = 0), FIELD(k.tingkat, {$ph}), k.nama_kelas",
            array_merge($opsiTingkat, $opsiTingkat)
        );
        View::render('kelas/index', ['rows' => $rows, 'imporReport' => $this->takeReport('impor_report_kelas')]);
    }

    public function kelasSave(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $id = int_input('id');
        $nama = str_input('nama_kelas');
        $tingkat = str_input('tingkat', '-');
        if ($nama === '') {
            flash_error('Nama kelas wajib diisi.');
            redirect('kelas');
        }
        if ($tingkat !== '-' && !in_array($tingkat, tingkat_options(), true)) {
            // Nilai lama di luar daftar jenjang aktif boleh dipertahankan apa adanya,
            // nilai kiriman yang asing dipaksa '-' agar data tetap rapi.
            $tersimpan = $id > 0 ? (string) DB::scalar('SELECT tingkat FROM kelas WHERE id = ?', [$id]) : '';
            if ($tingkat !== $tersimpan) {
                $tingkat = '-';
            }
        }
        $dupe = DB::one('SELECT id FROM kelas WHERE nama_kelas = ? AND id <> ?', [$nama, $id]);
        if ($dupe) {
            flash_error('Nama kelas sudah dipakai.');
            redirect('kelas');
        }
        if ($id > 0) {
            DB::exec('UPDATE kelas SET nama_kelas = ?, tingkat = ? WHERE id = ?', [$nama, $tingkat, $id]);
            audit('kelas_ubah', 'kelas', $id);
            flash_success('Kelas diperbarui.');
        } else {
            $newId = DB::insert('INSERT INTO kelas (nama_kelas, tingkat) VALUES (?,?)', [$nama, $tingkat]);
            audit('kelas_tambah', 'kelas', $newId);
            flash_success('Kelas ditambahkan.');
        }
        redirect('kelas');
    }

    public function kelasDelete(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $id = int_input('id');
        $jml = (int) DB::scalar('SELECT COUNT(*) FROM siswa WHERE id_kelas = ?', [$id]);
        if ($jml > 0) {
            flash_error('Kelas masih memiliki ' . $jml . ' siswa. Pindahkan atau hapus siswanya terlebih dahulu.');
            redirect('kelas');
        }
        DB::exec('DELETE FROM kelas WHERE id = ?', [$id]);
        audit('kelas_hapus', 'kelas', $id);
        flash_success('Kelas dihapus.');
        redirect('kelas');
    }

    public function kelasImport(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $rows = $this->csvRows($_FILES['csv'] ?? null, 'kelas');

        $map = [];
        $start = 0;
        if ($this->looksLikeHeader($rows[0])) {
            $map = $this->columnMap($rows[0], [
                'nama' => ['nama kelas', 'kelas', 'nama', 'name'],
                'tingkat' => ['tingkat', 'jenjang', 'kelas tingkat', 'grade', 'level'],
            ]);
            $start = 1;
        }

        $ok = 0;
        $gagal = [];
        $baris = [];

        for ($i = $start; $i < count($rows); $i++) {
            $cols = $rows[$i];
            $nama = trim((string) ($cols[$map['nama'] ?? 0] ?? ''));
            if ($nama === '') {
                $gagal[] = 'Baris ' . ($i + 1) . ': nama kelas kosong (' . $this->rowPreview($cols) . ').';
                continue;
            }
            $tingkatRaw = trim((string) ($cols[$map['tingkat'] ?? 1] ?? ''));
            if ($tingkatRaw === '') {
                $namaIdx = $map['nama'] ?? 0;
                for ($j = $namaIdx + 1; $j < count($cols); $j++) {
                    $cand = trim((string) $cols[$j]);
                    if ($cand === '' || $cand === $nama) {
                        continue;
                    }
                    $tingkatRaw = $cand;
                    break;
                }
            }
            if (DB::one('SELECT id FROM kelas WHERE nama_kelas = ?', [$nama])) {
                $gagal[] = 'Baris ' . ($i + 1) . ': kelas "' . $nama . '" sudah ada.';
                continue;
            }
            $tingkat = $this->normalizeTingkat($tingkatRaw);

            try {
                DB::insert('INSERT INTO kelas (nama_kelas, tingkat) VALUES (?,?)', [$nama, $tingkat]);
            } catch (Throwable $e) {
                $gagal[] = 'Baris ' . ($i + 1) . ': ' . $e->getMessage();
                continue;
            }
            $baris[] = ['nama' => $nama, 'tingkat' => $tingkat];
            $ok++;
        }

        audit('kelas_impor', 'kelas', null, ['berhasil' => $ok, 'gagal' => count($gagal)]);
        $_SESSION['impor_report_kelas'] = [
            'judul' => 'Hasil Impor Data Kelas',
            'ok' => $ok,
            'gagal' => $gagal,
            'baris' => $baris,
        ];
        redirect('kelas', ['impor' => 1]);
    }

    public function kelasTemplate(): void
    {
        Auth::requireSuperadmin();
        $csv = "Nama Kelas;Tingkat\nX IPA 1;X\nX IPA 2;X\nXI IPS 1;XI\n";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template-kelas.csv"');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }

    /* ------------------------------------------------------------
     * Siswa
     * ---------------------------------------------------------- */
    public function siswaIndex(): void
    {
        Auth::requireSuperadmin();
        $q = str_input('q', '', $_GET);
        $idKelas = int_input('id_kelas', 0, $_GET);
        $status = str_input('status', '', $_GET);

        $sql = 'SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON k.id = s.id_kelas WHERE 1=1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (s.nama_siswa LIKE ? OR s.username LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($idKelas > 0) {
            $sql .= ' AND s.id_kelas = ?';
            $params[] = $idKelas;
        }
        if ($status !== '') {
            $sql .= ' AND s.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY k.nama_kelas, s.nama_siswa LIMIT 600';

        $rows = DB::all($sql, $params);
        foreach ($rows as &$row) {
            $row['password_teks'] = kartu_decrypt($row['password_kartu'] ?? null);
        }
        unset($row);
        $kelas = DB::all('SELECT * FROM kelas ORDER BY nama_kelas');
        $resetCount = (int) DB::scalar('SELECT COUNT(*) FROM siswa WHERE request_reset = 1');
        $imporReport = $_SESSION['impor_report'] ?? null;
        unset($_SESSION['impor_report']);
        View::render('siswa/index', compact('rows', 'kelas', 'q', 'idKelas', 'status', 'resetCount', 'imporReport'));
    }

    public function siswaForm(): void
    {
        Auth::requireSuperadmin();
        $id = int_input('id', 0, $_GET);
        $row = $id > 0 ? DB::one('SELECT * FROM siswa WHERE id = ?', [$id]) : null;
        $kelas = DB::all('SELECT * FROM kelas ORDER BY nama_kelas');
        View::render('siswa/form', ['row' => $row, 'kelas' => $kelas]);
    }

    private function uniqueUsername(): string
    {
        do {
            $u = 'U' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        } while (DB::scalar('SELECT 1 FROM siswa WHERE username = ?', [$u]));
        return $u;
    }

    public function siswaSave(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $id = int_input('id');
        $nama = str_input('nama_siswa');
        $idKelas = int_input('id_kelas');
        $agama = str_input('agama');
        $username = str_input('username');
        $password = (string) post('password', '');
        $status = str_input('status', 'Aktif');

        if ($nama === '' || $idKelas <= 0) {
            flash_error('Nama dan kelas wajib diisi.');
            keep_old($_POST);
            redirect('siswa_form', $id ? ['id' => $id] : []);
        }
        if (!DB::one('SELECT id FROM kelas WHERE id = ?', [$idKelas])) {
            flash_error('Kelas tidak ditemukan.');
            redirect('siswa_form', $id ? ['id' => $id] : []);
        }
        if (!in_array($status, ['Aktif', 'Tidak Aktif'], true)) {
            $status = 'Aktif';
        }
        if ($username !== '' && !preg_match('/^[A-Za-z0-9._-]{4,20}$/', $username)) {
            flash_error('Username 4–20 karakter, hanya huruf, angka, titik, garis bawah, atau strip.');
            keep_old($_POST);
            redirect('siswa_form', $id ? ['id' => $id] : []);
        }

        if ($id > 0) {
            $target = DB::one('SELECT * FROM siswa WHERE id = ?', [$id]);
            if (!$target) {
                throw new Abort('Siswa tidak ditemukan.');
            }
            $usernameFinal = $username !== '' ? $username : $target['username'];
            $dupe = DB::one('SELECT id FROM siswa WHERE username = ? AND id <> ?', [$usernameFinal, $id]);
            if ($dupe) {
                flash_error('Username sudah dipakai siswa lain.');
                redirect('siswa_form', ['id' => $id]);
            }
            DB::exec('UPDATE siswa SET nama_siswa=?, id_kelas=?, agama=?, username=?, status=? WHERE id=?',
                [$nama, $idKelas, $agama, $usernameFinal, $status, $id]);
            if ($password !== '') {
                if (strlen($password) < 5) {
                    flash_error('Password minimal 5 karakter.');
                    redirect('siswa_form', ['id' => $id]);
                }
                DB::exec('UPDATE siswa SET password_hash=?, password_kartu=? WHERE id=?', [
                    password_hash($password, PASSWORD_DEFAULT), kartu_encrypt($password), $id,
                ]);
            }
            audit('siswa_ubah', 'siswa', $id);
            flash_success('Data siswa diperbarui.');
        } else {
            $usernameFinal = $username !== '' ? $username : $this->uniqueUsername();
            if (DB::one('SELECT id FROM siswa WHERE username = ?', [$usernameFinal])) {
                flash_error('Username sudah dipakai siswa lain.');
                redirect('siswa_form');
            }
            if ($password === '') {
                $password = random_password(6);
            }
            $newId = DB::insert(
                'INSERT INTO siswa (username, password_hash, password_kartu, nama_siswa, id_kelas, agama, status)
                 VALUES (?,?,?,?,?,?,?)',
                [$usernameFinal, password_hash($password, PASSWORD_DEFAULT), kartu_encrypt($password), $nama, $idKelas, $agama, $status]
            );
            audit('siswa_tambah', 'siswa', $newId);
            flash_success('Siswa ditambahkan. Username: ' . $usernameFinal . ' — Password: ' . $password);
        }
        clear_old();
        redirect('siswa');
    }

    public function siswaDelete(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $id = int_input('id');
        DB::exec('DELETE FROM siswa WHERE id = ?', [$id]);
        audit('siswa_hapus', 'siswa', $id);
        flash_success('Siswa dihapus.');
        redirect('siswa');
    }

    public function siswaImport(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $rows = $this->csvRows($_FILES['csv'] ?? null, 'siswa');

        $header = array_map(static fn ($x) => strtolower(trim((string) $x)), $rows[0]);
        $map = [];
        $start = 0;
        if ($this->looksLikeHeader($rows[0])) {
            $map = $this->columnMap($rows[0], [
                'nama' => ['nama', 'nama siswa', 'nama lengkap', 'name'],
                'username' => ['username', 'nama pengguna', 'user', 'user name'],
                'kelas' => ['kelas', 'nama kelas', 'class'],
                'agama' => ['agama', 'religion'],
            ]);
            $start = 1;
        }
        $hasUsername = isset($map['username'])
            || in_array('username', $header, true)
            || in_array('nama pengguna', $header, true)
            || count($header) >= 4;

        $ok = 0;
        $gagal = [];
        $akun = [];

        for ($i = $start; $i < count($rows); $i++) {
            $cols = $rows[$i];
            $nama = trim((string) ($cols[$map['nama'] ?? 0] ?? ''));
            $username = isset($map['username']) ? trim((string) ($cols[$map['username']] ?? '')) : '';
            $kelasNama = isset($map['kelas'])
                ? trim((string) ($cols[$map['kelas']] ?? ''))
                : trim((string) ($cols[$hasUsername ? 2 : 1] ?? ''));
            $agama = isset($map['agama'])
                ? trim((string) ($cols[$map['agama']] ?? ''))
                : trim((string) ($cols[$hasUsername ? 3 : 2] ?? ''));

            if ($nama === '') {
                $gagal[] = 'Baris ' . ($i + 1) . ': nama kosong (' . $this->rowPreview($cols) . ').';
                continue;
            }
            if ($username !== '' && !preg_match('/^[A-Za-z0-9._-]{4,20}$/', $username)) {
                $gagal[] = 'Baris ' . ($i + 1) . ': username "' . $username . '" tidak valid.';
                continue;
            }
            if ($kelasNama === '') {
                $gagal[] = 'Baris ' . ($i + 1) . ': kelas kosong (' . $this->rowPreview($cols) . ').';
                continue;
            }

            try {
                $kelas = DB::one('SELECT id FROM kelas WHERE nama_kelas = ?', [$kelasNama]);
                if (!$kelas) {
                    $kelasId = DB::insert('INSERT INTO kelas (nama_kelas, tingkat) VALUES (?,?)', [$kelasNama, '-']);
                } else {
                    $kelasId = (int) $kelas['id'];
                }

                if ($username === '') {
                    $username = $this->uniqueUsername();
                }
                if (DB::one('SELECT id FROM siswa WHERE username = ?', [$username])) {
                    $gagal[] = 'Baris ' . ($i + 1) . ': username "' . $username . '" sudah ada.';
                    continue;
                }

                $pass = random_password(6);
                DB::insert(
                    'INSERT INTO siswa (username, password_hash, password_kartu, nama_siswa, id_kelas, agama, status)
                     VALUES (?,?,?,?,?,?,?)',
                    [$username, password_hash($pass, PASSWORD_DEFAULT), kartu_encrypt($pass), $nama, $kelasId, $agama, 'Aktif']
                );
                $akun[] = ['nama' => $nama, 'username' => $username, 'password' => $pass, 'kelas' => $kelasNama];
                $ok++;
            } catch (Throwable $e) {
                $gagal[] = 'Baris ' . ($i + 1) . ': ' . $e->getMessage();
            }
        }

        audit('siswa_impor', 'siswa', null, ['berhasil' => $ok, 'gagal' => count($gagal)]);
        $_SESSION['impor_report'] = ['ok' => $ok, 'gagal' => $gagal, 'akun' => $akun];
        redirect('siswa', ['impor' => 1]);
    }

    /**
     * Validasi unggahan CSV lalu kembalikan baris-barisnya.
     * Toleran terhadap BOM, UTF-16, pemisah ';' / ',' / tab, tanda kutip,
     * pemisah full-width, dan baris yang dibungkus tanda kutip.
     */
    private function csvRows(?array $file, string $route): array
    {
        if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash_error('Pilih file CSV terlebih dahulu.');
            redirect($route);
        }
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            flash_error('Ukuran file maksimal 5 MB.');
            redirect($route);
        }

        $content = (string) file_get_contents($file['tmp_name']);
        if (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF")) {
            $content = (string) mb_convert_encoding($content, 'UTF-8', 'UTF-16');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = str_replace(['；', '，', '＂'], [';', ',', '"'], $content);

        $rows = $this->parseCsv($content, $this->detectCsvDelimiter($content));
        if ($rows === []) {
            flash_error('File CSV kosong.');
            redirect($route);
        }
        return $rows;
    }

    private function detectCsvDelimiter(string $content): string
    {
        $counts = [';' => 0, ',' => 0, "\t" => 0];
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $sample = 0;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $inQuotes = false;
            $len = strlen($line);
            for ($i = 0; $i < $len; $i++) {
                $ch = $line[$i];
                if ($ch === '"') {
                    $inQuotes = !$inQuotes;
                    continue;
                }
                if (!$inQuotes && array_key_exists($ch, $counts)) {
                    $counts[$ch]++;
                }
            }
            if (++$sample >= 5) {
                break;
            }
        }
        arsort($counts);
        $best = (string) array_key_first($counts);
        return $counts[$best] > 0 ? $best : ';';
    }

    /**
     * Parser CSV RFC-4180 sederhana: mendukung tanda kutip, kutip ganda
     * (escape), dan baris multi-baris di dalam kutip.
     */
    private function parseCsv(string $content, string $delimiter): array
    {
        $rows = [];
        $row = [];
        $cell = '';
        $inQuotes = false;
        $len = strlen($content);

        for ($i = 0; $i < $len; $i++) {
            $ch = $content[$i];
            if ($inQuotes) {
                if ($ch === '"') {
                    if ($i + 1 < $len && $content[$i + 1] === '"') {
                        $cell .= '"';
                        $i++;
                    } else {
                        $inQuotes = false;
                    }
                } else {
                    $cell .= $ch;
                }
                continue;
            }
            if ($ch === '"') {
                $inQuotes = true;
                continue;
            }
            if ($ch === $delimiter) {
                $row[] = $cell;
                $cell = '';
                continue;
            }
            if ($ch === "\n" || $ch === "\r") {
                if ($ch === "\r" && $i + 1 < $len && $content[$i + 1] === "\n") {
                    $i++;
                }
                if ($cell !== '' || $row !== []) {
                    $row[] = $cell;
                    $rows[] = $row;
                }
                $row = [];
                $cell = '';
                continue;
            }
            $cell .= $ch;
        }
        if ($cell !== '' || $row !== []) {
            $row[] = $cell;
            $rows[] = $row;
        }

        $out = [];
        foreach ($rows as $r) {
            $r = array_map(static fn ($c) => trim((string) $c), $r);
            if (implode('', $r) === '') {
                continue;
            }
            // Baris yang seluruhnya dibungkus kutip tetap bisa dipecah:
            // pilih pemisah yang menghasilkan kolom paling banyak.
            if (count($r) === 1) {
                $best = $r;
                $bestCount = 1;
                foreach (array_unique([$delimiter, ';', ',', "\t"]) as $alt) {
                    if (!str_contains($r[0], $alt)) {
                        continue;
                    }
                    $parts = array_map('trim', explode($alt, $r[0]));
                    if (count($parts) > $bestCount) {
                        $best = $parts;
                        $bestCount = count($parts);
                    }
                }
                $r = $best;
            }
            $out[] = $r;
        }
        return $out;
    }

    private function looksLikeHeader(array $row): bool
    {
        $known = ['nama', 'nama guru', 'nama lengkap', 'name', 'email', 'e-mail', 'password',
            'status', 'nama kelas', 'kelas', 'tingkat', 'jenjang'];
        foreach ($row as $cell) {
            if (in_array(strtolower(trim((string) $cell)), $known, true)) {
                return true;
            }
        }
        return false;
    }

    private function columnMap(array $header, array $aliases): array
    {
        $map = [];
        foreach (array_values($header) as $i => $label) {
            $label = strtolower(trim((string) $label));
            foreach ($aliases as $key => $list) {
                if (!isset($map[$key]) && in_array($label, $list, true)) {
                    $map[$key] = $i;
                }
            }
        }
        return $map;
    }

    /**
     * Ambil email dari kolom yang dipetakan, atau dari sel mana pun pada baris.
     */
    private function rowEmail(array $row, ?int $preferred): string
    {
        if ($preferred !== null && isset($row[$preferred])) {
            $v = strtolower(trim((string) $row[$preferred]));
            if (filter_var($v, FILTER_VALIDATE_EMAIL)) {
                return $v;
            }
        }
        foreach ($row as $cell) {
            $cell = strtolower(trim((string) $cell));
            if (filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                return $cell;
            }
        }
        return $preferred !== null ? strtolower(trim((string) ($row[$preferred] ?? ''))) : '';
    }

    private function rowPreview(array $row, int $max = 6): string
    {
        $cells = array_slice($row, 0, $max);
        return implode(' | ', array_map(static fn ($c) => (string) $c, $cells));
    }

    private function takeReport(string $key): ?array
    {
        $report = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return is_array($report) ? $report : null;
    }

    private function normalizeTingkat(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '' || $value === '-') {
            return '-';
        }
        $opsi = tingkat_options();
        if (in_array($value, $opsi, true)) {
            return $value;
        }
        $alias = [
            '1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '5' => 'V', '6' => 'VI',
            '7' => 'VII', '8' => 'VIII', '9' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
        ];
        $angka = $alias[$value] ?? $value;
        return in_array($angka, $opsi, true) ? $angka : '-';
    }

    public function siswaTemplate(): void
    {
        Auth::requireSuperadmin();
        $format = (string) ($_GET['format'] ?? '1');
        if ($format === '2') {
            $csv = "Nama;Username;Kelas;Agama\nBudi Santoso;budisantoso;X IPA 1;Islam\nSiti Aminah;;X IPA 1;Islam\n";
        } else {
            $csv = "Nama;Kelas;Agama\nBudi Santoso;X IPA 1;Islam\nSiti Aminah;X IPA 1;Islam\n";
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template-siswa-' . $format . '.csv"');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }

    public function siswaRegenerate(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $idKelas = int_input('id_kelas');
        $sql = 'SELECT * FROM siswa';
        $params = [];
        if ($idKelas > 0) {
            $sql .= ' WHERE id_kelas = ?';
            $params[] = $idKelas;
        }
        $rows = DB::all($sql, $params);
        $akun = [];
        foreach ($rows as $s) {
            $pass = random_password(6);
            DB::exec('UPDATE siswa SET password_hash=?, password_kartu=?, session_id=NULL WHERE id=?', [
                password_hash($pass, PASSWORD_DEFAULT), kartu_encrypt($pass), $s['id'],
            ]);
            Auth::hapusSesiSiswa((int) $s['id']);
            $akun[] = ['nama' => $s['nama_siswa'], 'username' => $s['username'], 'password' => $pass];
        }
        audit('siswa_regenerate', 'siswa', null, ['jumlah' => count($akun)]);
        $_SESSION['impor_report'] = ['ok' => count($akun), 'gagal' => [], 'akun' => $akun, 'judul' => 'Akun berhasil diregenerasi'];
        redirect('siswa', ['impor' => 1]);
    }

    public function siswaResetSession(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $jumlah = Auth::hapusSesiSiswa();
        audit('siswa_reset_sesi', null, null, ['sesi' => $jumlah]);
        flash_success('Semua sesi login siswa telah direset.');
        redirect('siswa');
    }

    /* ------------------------------------------------------------
     * Reset login siswa (sesi perangkat)
     * ---------------------------------------------------------- */
    public function loginSiswa(): void
    {
        Auth::requireStaff();
        Auth::pruneSesiSiswa();

        $q = trim(str_input('q', '', $_GET));
        $idKelas = int_input('id_kelas', 0, $_GET);
        $status = str_input('status', '', $_GET);
        if (!in_array($status, ['Aktif', 'Tidak Aktif'], true)) {
            $status = '';
        }

        $sql = 'SELECT ss.*, s.nama_siswa, s.username, s.status, k.nama_kelas
                  FROM siswa_sesi ss
                  JOIN siswa s ON s.id = ss.id_siswa
                  JOIN kelas k ON k.id = s.id_kelas';
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(s.nama_siswa LIKE ? OR s.username LIKE ? OR k.nama_kelas LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($idKelas > 0) {
            $where[] = 's.id_kelas = ?';
            $params[] = $idKelas;
        }
        if ($status !== '') {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ss.last_seen DESC LIMIT 500';

        $sesi = DB::all($sql, $params);
        $kelas = DB::all('SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas');
        $jumlahSiswa = (int) DB::scalar('SELECT COUNT(DISTINCT id_siswa) FROM siswa_sesi');
        $jumlahSesi = (int) DB::scalar('SELECT COUNT(*) FROM siswa_sesi');
        View::render('siswa/login_sesi', compact('sesi', 'q', 'idKelas', 'status', 'kelas', 'jumlahSiswa', 'jumlahSesi'));
    }

    /** Filter halaman reset login yang dibawa saat redirect (dari POST atau GET). */
    private function filterLoginSiswa(?array $src = null): array
    {
        $out = [];
        $q = trim(str_input('q', '', $src ?? $_POST));
        if ($q !== '') {
            $out['q'] = $q;
        }
        $idKelas = int_input('id_kelas', 0, $src ?? $_POST);
        if ($idKelas > 0) {
            $out['id_kelas'] = $idKelas;
        }
        $status = str_input('status', '', $src ?? $_POST);
        if (in_array($status, ['Aktif', 'Tidak Aktif'], true)) {
            $out['status'] = $status;
        }
        return $out;
    }

    public function loginSiswaReset(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $sesi = $id > 0 ? DB::one('SELECT * FROM siswa_sesi WHERE id = ?', [$id]) : null;
        if (!$sesi) {
            flash_error('Sesi login tidak ditemukan atau sudah berakhir.');
            redirect('login_siswa', $this->filterLoginSiswa());
        }
        DB::exec('DELETE FROM siswa_sesi WHERE id = ?', [$id]);
        audit('login_siswa_keluarkan', 'siswa', (int) $sesi['id_siswa'], ['ip' => $sesi['ip']]);
        flash_success('Sesi login siswa dikeluarkan. Siswa dapat login lagi di perangkat lain.');
        redirect('login_siswa', $this->filterLoginSiswa());
    }

    /** Reset login massal untuk sesi-sesi yang dicentang. */
    public function loginSiswaResetMassal(): void
    {
        Auth::requireStaff();
        Csrf::check();

        $pilihan = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['pilih'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        if ($pilihan === []) {
            flash_error('Pilih dulu sesi login yang ingin direset.');
            redirect('login_siswa', $this->filterLoginSiswa());
        }
        if (count($pilihan) > 1000) {
            $pilihan = array_slice($pilihan, 0, 1000);
        }

        $in = implode(',', array_fill(0, count($pilihan), '?'));
        $rows = DB::all('SELECT id, id_siswa FROM siswa_sesi WHERE id IN (' . $in . ')', $pilihan);
        $jumlah = DB::exec('DELETE FROM siswa_sesi WHERE id IN (' . $in . ')', $pilihan);
        audit('login_siswa_reset_massal', null, null, [
            'sesi' => $jumlah,
            'siswa' => count(array_unique(array_map(static fn (array $r): int => (int) $r['id_siswa'], $rows))),
        ]);
        flash_success($jumlah . ' sesi login siswa telah direset.');
        redirect('login_siswa', $this->filterLoginSiswa());
    }

    public function loginSiswaResetSiswa(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id_siswa');
        if ($id <= 0) {
            flash_error('Siswa tidak ditemukan.');
            redirect('login_siswa', $this->filterLoginSiswa());
        }
        $jumlah = Auth::hapusSesiSiswa($id);
        audit('login_siswa_reset', 'siswa', $id, ['sesi' => $jumlah]);
        flash_success('Semua sesi login siswa tersebut telah direset.');
        redirect('login_siswa', $this->filterLoginSiswa());
    }

    public function loginSiswaResetSemua(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $jumlah = Auth::hapusSesiSiswa();
        audit('login_siswa_reset_semua', null, null, ['sesi' => $jumlah]);
        flash_success('Seluruh sesi login siswa telah direset (' . $jumlah . ' sesi).');
        redirect('login_siswa');
    }

    public function siswaToggleAll(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $status = str_input('status', 'Aktif');
        if (!in_array($status, ['Aktif', 'Tidak Aktif'], true)) {
            $status = 'Aktif';
        }
        $idKelas = int_input('id_kelas');
        if ($idKelas > 0) {
            DB::exec('UPDATE siswa SET status = ? WHERE id_kelas = ?', [$status, $idKelas]);
        } else {
            DB::exec('UPDATE siswa SET status = ?', [$status]);
        }
        audit('siswa_status_massal', 'siswa', null, ['status' => $status]);
        flash_success('Status siswa diperbarui.');
        redirect('siswa');
    }

    public function siswaDeleteAll(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $konfirmasi = strtoupper(str_input('konfirmasi'));
        if ($konfirmasi !== 'HAPUS') {
            flash_error('Ketik HAPUS untuk mengonfirmasi penghapusan massal.');
            redirect('siswa');
        }
        $idKelas = int_input('id_kelas');
        if ($idKelas > 0) {
            DB::exec('DELETE FROM siswa WHERE id_kelas = ?', [$idKelas]);
        } else {
            DB::exec('DELETE FROM siswa');
        }
        audit('siswa_hapus_massal', 'siswa', null, ['id_kelas' => $idKelas]);
        flash_success('Data siswa dihapus.');
        redirect('siswa');
    }

    public function siswaResetPassword(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        $id = int_input('id');
        $s = DB::one('SELECT * FROM siswa WHERE id = ?', [$id]);
        if (!$s) {
            throw new Abort('Siswa tidak ditemukan.');
        }
        $pass = random_password(6);
        DB::exec('UPDATE siswa SET password_hash=?, password_kartu=?, request_reset=0, session_id=NULL WHERE id=?', [
            password_hash($pass, PASSWORD_DEFAULT), kartu_encrypt($pass), $id,
        ]);
        Auth::hapusSesiSiswa($id);
        audit('siswa_reset_password', 'siswa', $id);
        flash_success('Password baru untuk ' . $s['nama_siswa'] . ': ' . $pass . ' (catat sekarang).');
        redirect('siswa');
    }

    public function siswaKartu(): void
    {
        Auth::requireSuperadmin();
        $idKelas = int_input('id_kelas', 0, $_GET);
        $sql = 'SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON k.id = s.id_kelas';
        $params = [];
        if ($idKelas > 0) {
            $sql .= ' WHERE s.id_kelas = ?';
            $params[] = $idKelas;
        }
        $sql .= ' ORDER BY k.nama_kelas, s.nama_siswa';
        $rows = DB::all($sql, $params);
        foreach ($rows as &$r) {
            $r['password_teks'] = kartu_decrypt($r['password_kartu'] ?? null);
        }
        unset($r);
        View::render('cetak/kartu', ['rows' => $rows], null);
    }

    public function siswaExport(): void
    {
        Auth::requireSuperadmin();
        $rows = DB::all(
            'SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON k.id = s.id_kelas
             ORDER BY k.nama_kelas, s.nama_siswa'
        );
        $data = [['No', 'Nama Siswa', 'Username', 'Password', 'Kelas', 'Agama', 'Status']];
        $i = 1;
        foreach ($rows as $r) {
            $data[] = [
                $i++,
                $r['nama_siswa'],
                $r['username'],
                kartu_decrypt($r['password_kartu'] ?? null),
                $r['nama_kelas'],
                (string) ($r['agama'] ?? ''),
                $r['status'],
            ];
        }
        (new Xlsx('Data Siswa'))->addSheet('Siswa', $data)->save('data-siswa-' . date('Ymd') . '.xlsx');
    }

    /* ------------------------------------------------------------
     * Pengaturan, audit, profil
     * ---------------------------------------------------------- */
    public function pengaturan(): void
    {
        Auth::requireSuperadmin();
        View::render('pengaturan/sekolah', [
            'nilai' => DB::all('SELECT * FROM pengaturan ORDER BY kunci'),
        ]);
    }

    public function pengaturanSave(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();

        foreach (['nama_sekolah', 'npsn', 'alamat', 'tahun_ajaran', 'kkm', 'pengumuman'] as $key) {
            if (array_key_exists($key, $_POST)) {
                set_setting($key, trim((string) $_POST[$key]));
            }
        }

        $jenjang = strtoupper(str_input('jenjang'));
        if (isset(jenjang_options()[$jenjang])) {
            set_setting('jenjang', $jenjang);
        }

        $browserTeks = trim((string) ($_POST['browser_siswa_teks'] ?? ''));
        $browserSiswa = browser_parse_text($browserTeks);
        set_setting('browser_siswa', browser_list_text($browserSiswa));
        $browserAsing = browser_unknown_text($browserTeks);
        if ($browserAsing !== []) {
            flash('warning', 'Browser tidak dikenal dan diabaikan: ' . implode(', ', $browserAsing)
                . '. Pilihan yang tersedia: ' . implode(', ', array_values(browser_short_names())) . '.');
        }

        // Pengawasan ujian berlaku untuk semua ujian (khusus superadmin).
        set_setting('deteksi_tab', str_input('deteksi_tab', '1') === '0' ? '0' : '1');
        set_setting('maks_pelanggaran', (string) max(1, min(20, int_input('maks_pelanggaran', 3))));
        $aksiPelanggaran = str_input('aksi_pelanggaran', 'peringatan');
        if (!in_array($aksiPelanggaran, ['peringatan', 'kunci', 'akhiri'], true)) {
            $aksiPelanggaran = 'peringatan';
        }
        set_setting('aksi_pelanggaran', $aksiPelanggaran);

        // Batas perangkat yang boleh login bersamaan untuk satu siswa.
        set_setting('maks_login_siswa', (string) max(1, min(20, int_input('maks_login_siswa', 1))));

        if (!empty($_FILES['logo']['name']) && ($_FILES['logo']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $tmp = $_FILES['logo']['tmp_name'];
            // getimagesize() tidak dapat memvalidasi SVG, jadi hanya format raster yang diizinkan.
            $info = @getimagesize($tmp);
            $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            if ($mime === '' || !isset($allowed[$mime])) {
                flash_error('Logo harus berupa PNG, JPG, GIF, atau WEBP.');
                redirect('pengaturan');
            }
            if ((int) $_FILES['logo']['size'] > 2 * 1024 * 1024) {
                flash_error('Ukuran logo maksimal 2 MB.');
                redirect('pengaturan');
            }
            $ext = $allowed[$mime];
            $name = 'logo_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dir = BASE_PATH . '/assets/uploads';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
                flash_error('Gagal menyimpan logo.');
                redirect('pengaturan');
            }
            set_setting('logo_sekolah', $name);
        }

        audit('pengaturan_ubah');
        flash_success('Pengaturan disimpan.');
        redirect('pengaturan');
    }

    public function audit(): void
    {
        Auth::requireSuperadmin();
        $rows = DB::all('SELECT * FROM audit_log ORDER BY id DESC LIMIT 300');
        View::render('pengaturan/audit', ['rows' => $rows]);
    }

    public function profil(): void
    {
        Auth::requireStaff();
        View::render('profil', []);
    }

    public function profilSave(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $lama = (string) post('lama', '');
        $baru = (string) post('baru', '');
        $ulang = (string) post('ulang', '');

        $u = DB::one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        if (!$u || !password_verify($lama, (string) $u['password_hash'])) {
            flash_error('Password lama tidak sesuai.');
            redirect('profil');
        }
        if (strlen($baru) < 8) {
            flash_error('Password baru minimal 8 karakter.');
            redirect('profil');
        }
        if ($baru !== $ulang) {
            flash_error('Konfirmasi password baru tidak sama.');
            redirect('profil');
        }
        DB::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $u['id']]);
        audit('password_ubah', 'users', (int) $u['id']);
        flash_success('Password diperbarui.');
        redirect('profil');
    }
}
