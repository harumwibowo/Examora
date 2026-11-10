<?php
declare(strict_types=1);

final class UjianController
{
    /* ------------------------------------------------------------
     * Dashboard siswa
     * ---------------------------------------------------------- */
    public function dashboard(): void
    {
        Auth::requireSiswa();
        $u = Auth::user();
        $idSiswa = (int) $u['id'];
        $idKelas = (int) $u['id_kelas'];
        $agamaSiswa = trim((string) DB::scalar('SELECT agama FROM siswa WHERE id = ?', [$idSiswa]));

        $ujian = DB::all(
            "SELECT s.*, u.nama AS guru_nama
             FROM soal s
             JOIN users u ON u.id = s.id_guru
             WHERE s.status = 'Aktif'
               AND (s.mulai_at IS NULL OR s.mulai_at <= NOW())
               AND (s.selesai_at IS NULL OR s.selesai_at >= NOW())
               AND (
                    (s.target_mode = 'kelas' AND EXISTS (
                        SELECT 1 FROM soal_kelas sk WHERE sk.id_soal = s.id AND sk.id_kelas = ?))
                 OR (s.target_mode = 'jenjang' AND EXISTS (
                        SELECT 1 FROM soal_jenjang sj JOIN kelas k ON k.tingkat = sj.tingkat
                         WHERE sj.id_soal = s.id AND k.id = ?))
                 OR (s.target_mode = 'siswa' AND EXISTS (
                        SELECT 1 FROM soal_siswa ss WHERE ss.id_soal = s.id AND ss.id_siswa = ?))
               )
               AND (s.agama_target IS NULL OR TRIM(s.agama_target) = ''
                    OR LOWER(TRIM(s.agama_target)) = ?)
             ORDER BY s.created_at DESC",
            [$idKelas, $idKelas, $idSiswa, mb_strtolower($agamaSiswa)]
        );

        foreach ($ujian as &$row) {
            $riwayat = DB::all(
                'SELECT * FROM riwayat_ujian WHERE id_soal = ? AND id_siswa = ? ORDER BY percobaan_ke DESC',
                [$row['id'], $idSiswa]
            );
            $row['riwayat'] = $riwayat;
            $row['percobaan'] = count($riwayat);
            $row['aktif'] = null;
            $row['terbaik'] = null;
            $terakhirKirim = null;
            foreach ($riwayat as $r) {
                if ($r['status'] === 'Berlangsung') {
                    $row['aktif'] = $r;
                }
                // $riwayat urut percobaan_ke DESC -> yang pertama adalah terkirim terakhir.
                if ($r['waktu_selesai'] !== null && $terakhirKirim === null) {
                    $terakhirKirim = $r;
                }
                if ($r['nilai_akhir'] !== null
                    && ($row['terbaik'] === null || (float) $r['nilai_akhir'] > (float) $row['terbaik']['nilai_akhir'])) {
                    $row['terbaik'] = $r;
                }
            }
            // Belum ada nilai (mis. menunggu koreksi esai) -> pakai percobaan terakhir
            // yang sudah dikumpulkan agar siswa tetap bisa membuka halaman hasil.
            if ($row['terbaik'] === null) {
                $row['terbaik'] = $terakhirKirim;
            }
            // boleh = siswa masih boleh memulai percobaan baru.
            // Sudah ada attempt berjalan -> tampil "Lanjutkan" (dihandle view lebih dulu).
            $row['boleh'] = $row['aktif'] !== null
                || $row['kesempatan'] === 'Dapat Diulang'
                || (int) $row['percobaan'] === 0;
            if ($row['kesempatan'] === 'Sekali Saja' && $row['percobaan'] > 0) {
                $row['boleh'] = false;
            }
        }
        unset($row);

        $totalSelesai = (int) DB::scalar(
            'SELECT COUNT(*) FROM riwayat_ujian WHERE id_siswa = ? AND waktu_selesai IS NOT NULL',
            [$idSiswa]
        );

        View::render('siswa_dashboard', compact('ujian', 'totalSelesai'), 'layout_siswa');
    }

    private function ambilUjian(int $idSoal, int $idKelas): array
    {
        $soal = DB::one(
            "SELECT s.*, u.nama AS guru_nama FROM soal s JOIN users u ON u.id = s.id_guru
             WHERE s.id = ? AND s.status = 'Aktif'",
            [$idSoal]
        );
        if (!$soal) {
            throw new Abort('Ujian tidak tersedia atau belum diaktifkan.');
        }
        $targetAgama = trim((string) ($soal['agama_target'] ?? ''));
        $u = Auth::user();
        $agamaSiswa = (string) DB::scalar('SELECT agama FROM siswa WHERE id = ?', [(int) $u['id']]);
        if (!agama_cocok($agamaSiswa, $targetAgama)) {
            throw new Abort('Ujian ini khusus untuk siswa beragama ' . $targetAgama . '.');
        }
        if (!$this->targetCocok($soal, (int) $u['id'], $idKelas)) {
            throw new Abort('Ujian ini tidak ditujukan untuk kelas atau akun Anda.');
        }
        if ($soal['mulai_at'] && strtotime($soal['mulai_at']) > time()) {
            throw new Abort('Ujian belum dimulai. Jadwal: ' . fmt_tanggal($soal['mulai_at']));
        }
        if ($soal['selesai_at'] && strtotime($soal['selesai_at']) < time()) {
            throw new Abort('Ujian sudah berakhir pada ' . fmt_tanggal($soal['selesai_at']) . '.');
        }
        return $soal;
    }

    /** Cocokkan target peserta (kelas/jenjang/siswa) dengan siswa yang login. */
    private function targetCocok(array $soal, int $idSiswa, int $idKelas): bool
    {
        $mode = (string) ($soal['target_mode'] ?? 'kelas');
        if ($mode === 'jenjang') {
            return (bool) DB::one(
                'SELECT 1 FROM soal_jenjang sj JOIN kelas k ON k.tingkat = sj.tingkat
                  WHERE sj.id_soal = ? AND k.id = ?',
                [(int) $soal['id'], $idKelas]
            );
        }
        if ($mode === 'siswa') {
            return (bool) DB::one(
                'SELECT 1 FROM soal_siswa WHERE id_soal = ? AND id_siswa = ?',
                [(int) $soal['id'], $idSiswa]
            );
        }
        return (bool) DB::one(
            'SELECT 1 FROM soal_kelas WHERE id_soal = ? AND id_kelas = ?',
            [(int) $soal['id'], $idKelas]
        );
    }

    public function konfirmasi(): void
    {
        Auth::requireSiswa();
        $u = Auth::user();
        $id = int_input('id', 0, $_GET);
        $soal = $this->ambilUjian($id, (int) $u['id_kelas']);
        $riwayat = DB::all(
            'SELECT * FROM riwayat_ujian WHERE id_soal = ? AND id_siswa = ? ORDER BY percobaan_ke DESC',
            [$id, $u['id']]
        );
        $aktif = null;
        foreach ($riwayat as $r) {
            if ($r['status'] === 'Berlangsung') {
                $aktif = $r;
            }
        }
        if ($soal['kesempatan'] === 'Sekali Saja' && $riwayat !== []) {
            flash_error('Ujian ini hanya dapat dikerjakan satu kali.');
            redirect('siswa_home');
        }
        View::render('ujian/konfirmasi', compact('soal', 'riwayat', 'aktif'), 'layout_siswa');
    }

    public function mulai(): void
    {
        Auth::requireSiswa();
        Csrf::check();
        $u = Auth::user();
        $id = int_input('id');
        $soal = $this->ambilUjian($id, (int) $u['id_kelas']);
        $token = strtoupper(str_input('token'));

        if ($token !== strtoupper((string) $soal['kode_soal'])) {
            flash_error('Token ujian salah.');
            redirect('ujian', ['id' => $id]);
        }

        $sedang = DB::one(
            "SELECT id FROM riwayat_ujian WHERE id_siswa = ? AND status = 'Berlangsung' LIMIT 1",
            [$u['id']]
        );
        if ($sedang) {
            redirect('ujian_kerjakan', ['id' => $sedang['id']]);
        }

        $riwayat = DB::all(
            'SELECT * FROM riwayat_ujian WHERE id_soal = ? AND id_siswa = ?',
            [$id, $u['id']]
        );
        if ($soal['kesempatan'] === 'Sekali Saja' && $riwayat !== []) {
            throw new Abort('Ujian ini hanya dapat dikerjakan satu kali.');
        }
        $percobaan = 0;
        foreach ($riwayat as $r) {
            $percobaan = max($percobaan, (int) $r['percobaan_ke']);
        }

        $menit = max(1, (int) $soal['durasi']);
        $mulai = date('Y-m-d H:i:s');
        $batas = date('Y-m-d H:i:s', strtotime($mulai) + $menit * 60);

        $idRiwayat = DB::insert(
            'INSERT INTO riwayat_ujian (id_soal, id_siswa, percobaan_ke, waktu_mulai, batas_waktu, status, ip, user_agent)
             VALUES (?,?,?,?,?,?,?,?)',
            [$id, $u['id'], $percobaan + 1, $mulai, $batas, 'Berlangsung', client_ip(),
             mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250)]
        );
        audit('ujian_mulai', 'riwayat_ujian', $idRiwayat, ['id_soal' => $id]);
        redirect('ujian_kerjakan', ['id' => $idRiwayat]);
    }

    private function riwayatMilikSaya(int $idRiwayat, bool $harusBerlangsung = true): array
    {
        $u = Auth::user();
        $r = DB::one(
            'SELECT ru.*, s.nama_soal, s.mapel, s.kode_soal, s.jenis_ujian, s.mode_soal, s.link_pdf, s.durasi,
                    s.min_pengerjaan,
                    s.opsi_pg, s.jml_pg, s.jml_uraian, s.tampil_nilai, s.kesempatan,
                    s.deteksi_tab, s.maks_pelanggaran, s.aksi_pelanggaran, s.bobot_pg, s.bobot_uraian, s.status AS status_soal
             FROM riwayat_ujian ru JOIN soal s ON s.id = ru.id_soal
             WHERE ru.id = ?',
            [$idRiwayat]
        );
        if (!$r || (int) $r['id_siswa'] !== (int) $u['id']) {
            throw new Abort('Sesi ujian tidak ditemukan.');
        }
        if ($harusBerlangsung && $r['status'] !== 'Berlangsung') {
            throw new Abort('Sesi ujian ini sudah selesai.');
        }
        return $r;
    }

    public function kerjakan(): void
    {
        Auth::requireSiswa();
        $id = int_input('id', 0, $_GET);
        $r = $this->riwayatMilikSaya($id);

        if (strtotime($r['batas_waktu']) <= time()) {
            $this->finalisasi($r);
            flash('info', 'Waktu ujian telah habis. Jawaban Anda otomatis dikumpulkan.');
            redirect('ujian_hasil', ['id' => $id]);
        }

        if ((int) ($r['terkunci'] ?? 0) === 1) {
            View::render('ujian/terkunci', ['r' => $r], 'layout_siswa');
            return;
        }

        $butir = DB::all('SELECT * FROM butir_soal WHERE id_soal = ? ORDER BY nomor_soal', [$r['id_soal']]);
        $jawaban = [];
        foreach (DB::all('SELECT * FROM jawaban_siswa WHERE id_riwayat = ?', [$id]) as $j) {
            $jawaban[(int) $j['nomor_soal']] = json_decode((string) $j['jawaban'], true);
        }

        $soalPayload = [];
        foreach ($butir as $b) {
            $item = [
                'nomor' => (int) $b['nomor_soal'],
                'tipe' => $b['tipe_soal'],
                'pertanyaan' => null,
            ];
            $kunci = json_decode((string) $b['kunci'], true) ?: [];
            $opsi = json_decode((string) ($b['opsi'] ?? ''), true);
            if (in_array($b['tipe_soal'], ['PG', 'PG_Kompleks'], true)) {
                if (is_array($opsi) && $opsi !== []) {
                    $item['opsi'] = array_values($opsi);
                } else {
                    $item['opsi'] = array_map(static fn ($h) => 'Pilihan ' . $h, opsi_huruf((int) $r['opsi_pg']));
                }
                $item['huruf'] = opsi_huruf((int) $r['opsi_pg']);
            }
            if ($b['tipe_soal'] === 'Benar_Salah') {
                $item['pernyataan'] = array_map(static fn ($x) => (string) ($x['pernyataan'] ?? ''), (array) $kunci);
            }
            if ($b['tipe_soal'] === 'Menjodohkan') {
                $item['kiri'] = array_map(static fn ($x) => (string) ($x['kiri'] ?? ''), (array) $kunci);
                $kanan = array_map(static fn ($x) => (string) ($x['kanan'] ?? ''), (array) $kunci);
                shuffle($kanan);
                $item['pilihan_kanan'] = $kanan;
            }
            $soalPayload[] = $item;
        }

        $jawabanJson = [];
        foreach ($jawaban as $n => $v) {
            $jawabanJson[(string) $n] = $v;
        }

        $minPengerjaan = max(0, min((int) $r['durasi'], (int) ($r['min_pengerjaan'] ?? 0)));
        $kaw = pengaturan_pengawasan();
        $payload = [
            'id_riwayat' => (int) $id,
            'batas_waktu' => $r['batas_waktu'],
            'min_selesai' => $minPengerjaan > 0
                ? date('Y-m-d H:i:s', strtotime((string) $r['waktu_mulai']) + $minPengerjaan * 60)
                : null,
            'server_time' => now(),
            'soal' => $soalPayload,
            'jawaban' => $jawabanJson,
            'csrf' => Csrf::token(),
            'deteksi_tab' => $kaw['deteksi_tab'],
            'maks_pelanggaran' => $kaw['maks_pelanggaran'],
            'aksi_pelanggaran' => $kaw['aksi_pelanggaran'],
            'pelanggaran' => (int) $r['pelanggaran'],
            'mode' => 'PDF',
            'api' => [
                'jawab' => url('api_jawab'),
                'selesai' => url('api_selesai'),
                'status' => url('api_status'),
                'pelanggaran' => url('api_pelanggaran'),
                'hasil' => url('ujian_hasil', ['id' => (int) $id]),
            ],
        ];

        View::render('ujian/kerjakan', [
            'r' => $r,
            'payload' => $payload,
            'total' => count($soalPayload),
        ], 'layout_ujian');
    }

    private function finalisasi(array $r): void
    {
        if ($r['waktu_selesai'] !== null) {
            return;
        }
        DB::exec(
            'UPDATE riwayat_ujian SET waktu_selesai = ?, terkunci = 0, dikunci_pada = NULL
              WHERE id = ? AND waktu_selesai IS NULL',
            [now(), $r['id']]
        );
        Penilaian::hitung((int) $r['id']);
        audit('ujian_selesai', 'riwayat_ujian', (int) $r['id'], ['id_soal' => (int) $r['id_soal']]);
    }

    public function selesaiPage(): void
    {
        Auth::requireSiswa();
        $id = int_input('id', 0, $_GET);
        $r = DB::one('SELECT * FROM riwayat_ujian WHERE id = ? AND id_siswa = ?', [$id, Auth::id()]);
        if (!$r) {
            throw new Abort('Riwayat tidak ditemukan.');
        }
        View::render('ujian/selesai', ['r' => $r], 'layout_siswa');
    }

    public function hasil(): void
    {
        Auth::requireSiswa();
        $id = int_input('id', 0, $_GET);
        $r = DB::one(
            'SELECT ru.*, s.nama_soal, s.tampil_nilai, s.bobot_pg, s.bobot_uraian, s.jenis_ujian
             FROM riwayat_ujian ru JOIN soal s ON s.id = ru.id_soal
             WHERE ru.id = ? AND ru.id_siswa = ?',
            [$id, Auth::id()]
        );
        if (!$r) {
            throw new Abort('Riwayat tidak ditemukan.');
        }
        if ($r['waktu_selesai'] === null) {
            redirect('ujian_kerjakan', ['id' => $id]);
        }
        View::render('ujian/hasil', ['r' => $r], 'layout_siswa');
    }

    /* ------------------------------------------------------------
     * API ujian
     * ---------------------------------------------------------- */
    private function apiGuard(bool $harusBerlangsung = true): array
    {
        if (!Auth::isSiswa() || !Auth::validSiswaSession()) {
            json_out(['ok' => false, 'pesan' => 'Sesi tidak valid. Silakan masuk kembali.'], 401);
        }
        Csrf::check();
        $id = (int) ($_POST['id_riwayat'] ?? 0);
        if ($id <= 0) {
            json_out(['ok' => false, 'pesan' => 'Parameter id_riwayat wajib.'], 422);
        }
        return $this->riwayatMilikSaya($id, $harusBerlangsung);
    }

    public function apiMulai(): void
    {
        $r = $this->apiGuard();
        $this->apiTolakTerkunci($r);
        json_out(['ok' => true]);
    }

    public function apiJawab(): void
    {
        $r = $this->apiGuard();
        $this->apiTolakTerkunci($r);
        $nomor = (int) ($_POST['nomor'] ?? 0);
        $b = DB::one('SELECT * FROM butir_soal WHERE id_soal = ? AND nomor_soal = ?', [$r['id_soal'], $nomor]);
        if (!$b) {
            json_out(['ok' => false, 'pesan' => 'Nomor soal tidak ditemukan.'], 404);
        }

        $raw = $_POST['jawaban'] ?? null;
        $jawaban = $this->normalisasiJawaban($b['tipe_soal'], $raw);

        $json = json_encode($jawaban, JSON_UNESCAPED_UNICODE);
        DB::exec(
            'INSERT INTO jawaban_siswa (id_riwayat, nomor_soal, jawaban, waktu_jawab)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE jawaban = VALUES(jawaban), waktu_jawab = VALUES(waktu_jawab)',
            [$r['id'], $nomor, $json, now()]
        );

        if ($b['tipe_soal'] === 'Uraian') {
            // Esai tidak dinilai otomatis: biarkan skor NULL agar menunggu koreksi guru.
            DB::exec(
                'UPDATE jawaban_siswa SET skor = NULL, benar = NULL WHERE id_riwayat = ? AND nomor_soal = ?',
                [$r['id'], $nomor]
            );
        } else {
            $hasil = Penilaian::nilaiButir($b, $jawaban);
            DB::exec(
                'UPDATE jawaban_siswa SET skor = ?, benar = ? WHERE id_riwayat = ? AND nomor_soal = ?',
                [$hasil['skor'], $hasil['benar'] === null ? null : ($hasil['benar'] ? 1 : 0), $r['id'], $nomor]
            );
        }

        $terjawab = (int) DB::scalar(
            'SELECT COUNT(*) FROM jawaban_siswa WHERE id_riwayat = ? AND jawaban IS NOT NULL',
            [$r['id']]
        );

        json_out([
            'ok' => true,
            'tersimpan' => date('H:i:s'),
            'terjawab' => $terjawab,
            'sisa_detik' => max(0, strtotime($r['batas_waktu']) - time()),
        ]);
    }

    private function normalisasiJawaban(string $tipe, mixed $raw): mixed
    {
        if ($tipe === 'PG') {
            $v = strtoupper(trim((string) (is_array($raw) ? '' : $raw)));
            return in_array($v, ['A', 'B', 'C', 'D', 'E'], true) ? $v : null;
        }
        if ($tipe === 'PG_Kompleks') {
            $arr = array_values(array_filter(array_map(
                static fn ($x) => strtoupper(trim((string) $x)),
                is_array($raw) ? $raw : []
            )));
            $arr = array_values(array_intersect($arr, ['A', 'B', 'C', 'D', 'E']));
            return $arr === [] ? null : $arr;
        }
        if ($tipe === 'Benar_Salah') {
            $arr = [];
            foreach ((array) $raw as $v) {
                $s = strtoupper(trim((string) $v));
                if ($s === '1' || $s === 'B' || $s === 'TRUE') {
                    $arr[] = true;
                } elseif ($s === '0' || $s === 'S' || $s === 'FALSE') {
                    $arr[] = false;
                } else {
                    $arr[] = null;
                }
            }
            return $arr === [] ? null : $arr;
        }
        if ($tipe === 'Menjodohkan') {
            $arr = [];
            foreach ((array) $raw as $v) {
                $v = trim((string) $v);
                $arr[] = $v;
            }
            return $arr === [] ? null : $arr;
        }
        if ($tipe === 'Uraian') {
            $v = trim((string) (is_array($raw) ? '' : $raw));
            return $v === '' ? null : mb_substr($v, 0, 20000);
        }
        $v = trim((string) (is_array($raw) ? '' : $raw));
        return $v === '' ? null : mb_substr($v, 0, 500);
    }

    public function apiSelesai(): void
    {
        // Idempoten: mengumpulkan sesi yang sudah finalisasi (mis. oleh aksi
        // anti-curang 'akhiri' atau keduanya berbarengan) tetap dijawab ok.
        $r = $this->apiGuard(false);

        // Sesi terkunci tidak boleh dikumpulkan manual. Pengecualian: waktu ujian
        // sudah habis, sehingga finalisasi otomatis tetap berjalan.
        if ($r['waktu_selesai'] === null
            && (int) ($r['terkunci'] ?? 0) === 1
            && time() < strtotime((string) $r['batas_waktu'])) {
            json_out([
                'ok' => false,
                'terkunci' => true,
                'pesan' => 'Ujian sedang dikunci. Hubungi guru/pengawas untuk membuka kunci.',
            ], 423);
        }

        // Tombol kumpulkan dikunci sampai durasi minimal pengerjaan terpenuhi.
        // Timer habis dan aksi anti-curang tetap boleh finalisasi lebih awal.
        $minPengerjaan = max(0, min((int) $r['durasi'], (int) ($r['min_pengerjaan'] ?? 0)));
        $bukaPada = strtotime((string) $r['waktu_mulai']) + $minPengerjaan * 60;
        if ($r['waktu_selesai'] === null && $minPengerjaan > 0 && time() < $bukaPada) {
            $sisa = max(1, $bukaPada - time());
            $menit = intdiv($sisa, 60);
            $detik = $sisa % 60;
            json_out([
                'ok' => false,
                'pesan' => 'Durasi minimal pengerjaan belum terpenuhi. Tombol kumpulkan terbuka dalam '
                    . ($menit > 0 ? $menit . ' menit ' : '') . $detik . ' detik.',
            ], 422);
        }

        $this->finalisasi($r);
        $baru = DB::one('SELECT * FROM riwayat_ujian WHERE id = ?', [$r['id']]);
        json_out([
            'ok' => true,
            'status' => $baru['status'],
            'redirect' => url('ujian_hasil', ['id' => $r['id']]),
        ]);
    }

    public function apiStatus(): void
    {
        $r = $this->apiGuard(false);
        if ((int) ($r['terkunci'] ?? 0) === 1 && strtotime((string) $r['batas_waktu']) > time()) {
            json_out([
                'ok' => false,
                'terkunci' => true,
                'pesan' => 'Ujian sedang dikunci. Hubungi guru/pengawas untuk membuka kunci.',
            ], 423);
        }
        $terjawab = (int) DB::scalar(
            'SELECT COUNT(*) FROM jawaban_siswa WHERE id_riwayat = ? AND jawaban IS NOT NULL',
            [$r['id']]
        );
        $sisa = max(0, strtotime($r['batas_waktu']) - time());
        if ($sisa <= 0) {
            $this->finalisasi($r);
        }
        $baru = DB::one('SELECT waktu_selesai, status FROM riwayat_ujian WHERE id = ?', [$r['id']]);
        json_out([
            'ok' => true,
            'sisa_detik' => $sisa,
            'terjawab' => $terjawab,
            'selesai' => $baru['waktu_selesai'] !== null,
            'status' => $baru['waktu_selesai'] !== null ? $baru['status'] : 'Berlangsung',
        ]);
    }

    public function apiPelanggaran(): void
    {
        $r = $this->apiGuard();
        $jenis = mb_substr(str_input('jenis', 'pindah_tab'), 0, 50);
        DB::exec('INSERT INTO log_pelanggaran (id_riwayat, jenis, detail, waktu) VALUES (?,?,?,?)',
            [$r['id'], $jenis, mb_substr((string) post('detail', ''), 0, 250), now()]);
        DB::exec('UPDATE riwayat_ujian SET pelanggaran = pelanggaran + 1 WHERE id = ?', [$r['id']]);
        $baru = (int) DB::scalar('SELECT pelanggaran FROM riwayat_ujian WHERE id = ?', [$r['id']]);
        $kaw = pengaturan_pengawasan();
        $aksi = 'peringatan';
        if ($baru >= $kaw['maks_pelanggaran']) {
            $aksi = $kaw['aksi_pelanggaran'];
        }
        if ($aksi === 'kunci') {
            // Simpan kunci di server. Memuat ulang halaman tidak akan membuka
            // kunci; hanya guru/pengawas yang dapat membukanya.
            DB::exec(
                'UPDATE riwayat_ujian SET terkunci = 1, dikunci_pada = ? WHERE id = ? AND terkunci = 0',
                [now(), $r['id']]
            );
        } elseif ($aksi === 'akhiri') {
            $this->finalisasi($r);
        }
        $terkunci = (int) DB::scalar('SELECT terkunci FROM riwayat_ujian WHERE id = ?', [$r['id']]);
        json_out(['ok' => true, 'pelanggaran' => $baru, 'aksi' => $aksi, 'terkunci' => $terkunci]);
    }

    /** Tolak semua aktivitas ujian selama sesi masih terkunci. */
    private function apiTolakTerkunci(array $r): void
    {
        if ((int) ($r['terkunci'] ?? 0) !== 1) {
            return;
        }
        if (strtotime((string) $r['batas_waktu']) <= time()) {
            $this->finalisasi($r);
            json_out([
                'ok' => false,
                'terkunci' => true,
                'selesai' => true,
                'pesan' => 'Waktu ujian telah habis. Jawaban Anda telah dikumpulkan.',
            ], 423);
        }
        json_out([
            'ok' => false,
            'terkunci' => true,
            'pesan' => 'Ujian sedang dikunci. Hubungi guru/pengawas untuk membuka kunci.',
        ], 423);
    }
}
