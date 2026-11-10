<?php
declare(strict_types=1);

final class SoalController
{
    public function index(): void
    {
        Auth::requireStaff();
        $sql = 'SELECT s.*, u.nama AS guru_nama,
                       (SELECT COUNT(*) FROM soal_kelas sk WHERE sk.id_soal = s.id) AS jumlah_kelas,
                       (SELECT COUNT(*) FROM soal_jenjang sj WHERE sj.id_soal = s.id) AS jumlah_jenjang,
                       (SELECT COUNT(*) FROM soal_siswa ss WHERE ss.id_soal = s.id) AS jumlah_siswa,
                       (SELECT GROUP_CONCAT(sj.tingkat ORDER BY sj.tingkat SEPARATOR \', \') FROM soal_jenjang sj WHERE sj.id_soal = s.id) AS label_jenjang,
                       (SELECT COUNT(*) FROM butir_soal b WHERE b.id_soal = s.id) AS jumlah_kunci,
                       (SELECT COUNT(*) FROM soal_kolaborator kl WHERE kl.id_soal = s.id) AS jumlah_kolaborator,
                       (SELECT COUNT(*) FROM riwayat_ujian r WHERE r.id_soal = s.id AND r.waktu_selesai IS NOT NULL) AS peserta
                FROM soal s JOIN users u ON u.id = s.id_guru';
        [$aksesSql, $params] = soal_akses_sql('s');
        $sql .= ' WHERE ' . $aksesSql . ' ORDER BY s.created_at DESC';
        $rows = DB::all($sql, $params);
        View::render('soal/index', ['rows' => $rows]);
    }

    public function form(): void
    {
        Auth::requireStaff();
        $id = int_input('id', 0, $_GET);
        $row = null;
        if ($id > 0) {
            $row = DB::one('SELECT * FROM soal WHERE id = ?', [$id]);
            if (!$row) {
                throw new Abort('Ujian tidak ditemukan.');
            }
            if (!Auth::canManageSoal($row)) {
                throw new Abort('Anda tidak berhak mengubah ujian ini.');
            }
        }
        $kelas = DB::all('SELECT * FROM kelas ORDER BY nama_kelas');
        $terpilih = [];
        $terpilihJenjang = [];
        $terpilihSiswa = [];
        if ($row) {
            $terpilih = array_map(
                static fn ($r) => (int) $r['id_kelas'],
                DB::all('SELECT id_kelas FROM soal_kelas WHERE id_soal = ?', [$id])
            );
            $terpilihJenjang = array_map(
                static fn ($r) => (string) $r['tingkat'],
                DB::all('SELECT tingkat FROM soal_jenjang WHERE id_soal = ? ORDER BY tingkat', [$id])
            );
            $terpilihSiswa = array_map(
                static fn ($r) => (int) $r['id_siswa'],
                DB::all('SELECT id_siswa FROM soal_siswa WHERE id_soal = ?', [$id])
            );
        }
        $jumlahButir = $row ? (int) DB::scalar('SELECT COUNT(*) FROM butir_soal WHERE id_soal = ?', [$id]) : 0;
        $siswaList = DB::all(
            "SELECT s.id, s.nama_siswa, s.id_kelas, s.agama, k.nama_kelas, k.tingkat
               FROM siswa s JOIN kelas k ON k.id = s.id_kelas
              WHERE s.status = 'Aktif'
              ORDER BY k.nama_kelas, s.nama_siswa"
        );
        $jenjangList = tingkat_kelas_options();
        View::render('soal/form', compact(
            'row', 'kelas', 'terpilih', 'terpilihJenjang', 'terpilihSiswa',
            'jumlahButir', 'siswaList', 'jenjangList'
        ));
    }

    public function save(): void
    {
        Auth::requireStaff();
        Csrf::check();

        $id = int_input('id');
        // Aplikasi ini eksklusif PDF Google Drive: tidak ada mode ketik langsung/campuran.
        $mode = 'PDF';
        $lama = $id > 0 ? DB::one('SELECT * FROM soal WHERE id = ?', [$id]) : null;
        if ($lama && !Auth::canManageSoal($lama)) {
            throw new Abort('Anda tidak berhak mengubah ujian ini.');
        }
        // Jenis ujian ditentukan otomatis dari komposisi soal di bawah.
        $nama = trim(str_input('nama_soal'));
        if ($nama === '' && $lama) {
            $nama = (string) $lama['nama_soal'];
        }
        $mapel = str_input('mapel');
        if ($nama === '') {
            $nama = $this->judulOtomatis($mapel);
        }
        $agamaTarget = trim(str_input('agama_target'));
        $targetMode = str_input('target_mode', 'kelas');
        $jenjangTarget = array_values(array_unique(array_filter(array_map(
            static fn ($x) => trim((string) $x),
            (array) ($_POST['target_jenjang'] ?? [])
        ), static fn ($x) => $x !== '')));
        $siswaTarget = array_values(array_unique(array_map('intval', (array) ($_POST['id_siswa'] ?? []))));
        $masukanPdf = str_input('link_pdf');
        $fileIdPdf = drive_file_id($masukanPdf);
        $resourceKeyPdf = drive_file_key($masukanPdf);
        if ($resourceKeyPdf === '' && $lama) {
            // Form hanya menampilkan ID; resource key lama dipertahankan.
            $resourceKeyPdf = drive_file_key((string) ($lama['link_pdf'] ?? ''));
        }
        $link = drive_canonical_link($fileIdPdf, $resourceKeyPdf);
        $durasi = int_input('durasi', 60);
        $minPengerjaan = int_input('min_pengerjaan', 0);
        $opsiPg = int_input('opsi_pg', 4);

        // Komposisi soal: daftar blok "jenis soal + rentang nomor urut + bobot".
        $tipeArr = (array) ($_POST['komposisi_tipe'] ?? []);
        $dariArr = (array) ($_POST['komposisi_dari'] ?? []);
        $sampaiArr = (array) ($_POST['komposisi_sampai'] ?? []);
        $bobotArr = (array) ($_POST['komposisi_bobot'] ?? []);
        $komposisiInput = [];
        foreach (array_values($tipeArr) as $i => $t) {
            $komposisiInput[] = [
                'tipe' => (string) $t,
                'dari' => $dariArr[$i] ?? 0,
                'sampai' => $sampaiArr[$i] ?? 0,
                'bobot' => $bobotArr[$i] ?? 0,
            ];
        }
        $hasilKomposisi = komposisi_validasi($komposisiInput);
        $komposisi = $hasilKomposisi['blok'];
        $errorKomposisi = $hasilKomposisi['errors'];

        // Kolom lama (jml_pg/jml_uraian/bobot) tetap disinkronkan agar
        // tampilan dan laporan lama tetap konsisten.
        $jmlPg = 0;
        $jmlUraian = 0;
        $bobotPg = 0.0;
        $bobotUraian = 0.0;
        $totalButir = 0;
        foreach ($komposisi as $b) {
            $n = (int) $b['sampai'] - (int) $b['dari'] + 1;
            if ($b['tipe'] === 'Uraian') {
                $jmlUraian += $n;
                $bobotUraian += (float) $b['bobot'];
            } else {
                $jmlPg += $n;
                $bobotPg += (float) $b['bobot'];
            }
            $totalButir = max($totalButir, (int) $b['sampai']);
        }
        $jenis = count(array_unique(array_column($komposisi, 'tipe'))) > 1 ? 'Kompleks' : 'Standar';
        $komposisiJson = $komposisi !== []
            ? json_encode(array_values($komposisi), JSON_UNESCAPED_UNICODE)
            : null;
        // Perubahan komposisi berarti kunci lama bisa tidak lagi sesuai
        // (jenis/nomor bergeser), sehingga status Aktif harus ditinjau ulang.
        $komposisiBerubah = false;
        if ($lama) {
            $blokLama = komposisi_tersimpan($lama);
            $komposisiBerubah = $blokLama !== [] && $blokLama !== $komposisi;
        }
        $kesempatan = str_input('kesempatan', 'Sekali Saja');
        $tampilNilai = str_input('tampil_nilai', 'Tidak');
        $status = str_input('status', 'Draft');
        $mulai = str_input('mulai_at');
        $selesai = str_input('selesai_at');
        // Pengawasan ujian diatur admin di halaman Pengaturan, bukan per ujian.
        $kaw = pengaturan_pengawasan();
        $deteksi = $kaw['deteksi_tab'];
        $maksPelanggaran = $kaw['maks_pelanggaran'];
        $aksiPelanggaran = $kaw['aksi_pelanggaran'];
        $kelasIds = array_map('intval', (array) ($_POST['id_kelas'] ?? []));

        if (!in_array($kesempatan, ['Sekali Saja', 'Dapat Diulang'], true)) {
            $kesempatan = 'Sekali Saja';
        }
        if (!in_array($tampilNilai, ['Ya', 'Tidak'], true)) {
            $tampilNilai = 'Tidak';
        }
        if (!in_array($status, ['Aktif', 'Draft', 'Arsip'], true)) {
            $status = 'Draft';
        }
        if (!in_array($aksiPelanggaran, ['peringatan', 'kunci', 'akhiri'], true)) {
            $aksiPelanggaran = 'peringatan';
        }
        if (!in_array($targetMode, ['kelas', 'jenjang', 'siswa'], true)) {
            $targetMode = 'kelas';
        }
        $jmlPg = max(0, $jmlPg);
        $jmlUraian = max(0, $jmlUraian);
        $bobotPg = round(max(0.0, $bobotPg), 2);
        $bobotUraian = round(max(0.0, $bobotUraian), 2);
        $durasi = max(1, $durasi);
        $minPengerjaan = max(0, $minPengerjaan);
        $opsiPg = in_array($opsiPg, [3, 4, 5], true) ? $opsiPg : 4;
        $maksPelanggaran = max(1, min(20, $maksPelanggaran));

        $errors = [];
        if ($nama === '') {
            $errors[] = 'Judul ujian wajib diisi.';
        }
        if ($errorKomposisi !== []) {
            $errors = array_merge($errors, $errorKomposisi);
        }
        if ($fileIdPdf === '') {
            $errors[] = 'ID file Google Drive wajib diisi (contoh: 1AbCdEfGhIjKlMnOpQrStUv). '
                . 'Boleh juga menempel tautan lengkap dari tombol Bagikan.';
        }
        if ($mulai !== '' && $selesai !== '' && strtotime($mulai) > strtotime($selesai)) {
            $errors[] = 'Waktu selesai tidak boleh sebelum waktu mulai.';
        }
        if ($minPengerjaan > $durasi) {
            $errors[] = 'Durasi minimal pengerjaan tidak boleh melebihi durasi ujian.';
        }

        $jumlahButir = $id > 0 && $totalButir > 0
            ? (int) DB::scalar(
                'SELECT COUNT(*) FROM butir_soal WHERE id_soal = ? AND nomor_soal BETWEEN 1 AND ?',
                [$id, $totalButir]
            )
            : 0;
        if ($kelasIds === [] && $targetMode === 'kelas') {
            $errors[] = 'Pilih minimal satu kelas peserta.';
        }
        if ($targetMode === 'jenjang') {
            $jenjangValid = tingkat_kelas_options();
            $jenjangTarget = array_values(array_filter(
                $jenjangTarget,
                static fn ($t) => in_array($t, $jenjangValid, true)
            ));
            if ($jenjangTarget === []) {
                $errors[] = 'Pilih minimal satu jenjang peserta.';
            }
        }
        if ($targetMode === 'siswa') {
            $siswaValid = array_map(
                static fn ($r) => (int) $r['id'],
                DB::all("SELECT id FROM siswa WHERE status = 'Aktif'")
            );
            $siswaTarget = array_values(array_intersect($siswaTarget, $siswaValid));
            if ($siswaTarget === []) {
                $errors[] = 'Pilih minimal satu siswa peserta.';
            }
        }
        if ($agamaTarget !== '') {
            $canon = null;
            foreach (agama_options() as $opt) {
                if (agama_sama($opt, $agamaTarget)) {
                    $canon = $opt;
                    break;
                }
            }
            if ($canon === null) {
                $errors[] = 'Target agama tidak valid.';
            } else {
                $agamaTarget = $canon;
            }
        }

        // Status Aktif hanya sah bila seluruh kunci jawaban sudah ada (sama dengan
        // aturan soal_toggle). Perubahan komposisi tidak lagi menghapus butir,
        // tetapi butir di luar rentang nomor baru tidak dihitung.
        $butirEfektif = $jumlahButir;
        $statusTurun = false;
        $alasanTurun = '';
        if ($status === 'Aktif' && $komposisiBerubah) {
            $status = 'Draft';
            $statusTurun = true;
            $alasanTurun = 'komposisi soal berubah, periksa kembali kunci jawaban';
        }
        if ($status === 'Aktif' && $butirEfektif < $totalButir) {
            $status = 'Draft';
            $statusTurun = true;
            $alasanTurun = 'kunci jawaban belum lengkap (' . $butirEfektif . '/' . $totalButir . ')';
        }

        if ($errors !== []) {
            flash_error(implode(' ', $errors));
            keep_old($_POST);
            redirect('soal_form', $id > 0 ? ['id' => $id] : []);
        }

        $data = [
            $jenis, $mode, $nama, $mapel, $agamaTarget !== '' ? $agamaTarget : null, $targetMode,
            $jmlPg, $opsiPg, $jmlUraian,
            $bobotPg, $bobotUraian, $komposisiJson,
            $durasi, $minPengerjaan, $link, $tampilNilai, $kesempatan, $deteksi,
            $maksPelanggaran, $aksiPelanggaran, $mulai !== '' ? $mulai : null, $selesai !== '' ? $selesai : null,
            $status,
        ];

        $soalId = DB::tx(function () use ($id, $data, $kelasIds, $jenjangTarget, $siswaTarget, $targetMode, $lama, $jenis, $jumlahButir) {
            if ($id > 0) {
                DB::exec(
                    'UPDATE soal SET jenis_ujian=?, mode_soal=?, nama_soal=?, mapel=?, agama_target=?, target_mode=?,
                     jml_pg=?, opsi_pg=?, jml_uraian=?, bobot_pg=?, bobot_uraian=?, komposisi=?, durasi=?, min_pengerjaan=?, link_pdf=?,
                     tampil_nilai=?, kesempatan=?, deteksi_tab=?, maks_pelanggaran=?,
                     aksi_pelanggaran=?, mulai_at=?, selesai_at=?, status=? WHERE id=?',
                    array_merge($data, [$id])
                );
                DB::exec('DELETE FROM soal_kelas WHERE id_soal = ?', [$id]);
                DB::exec('DELETE FROM soal_jenjang WHERE id_soal = ?', [$id]);
                DB::exec('DELETE FROM soal_siswa WHERE id_soal = ?', [$id]);
                $soalId = $id;
            } else {
                $kode = $this->uniqueToken();
                $soalId = DB::insert(
                    'INSERT INTO soal (jenis_ujian, mode_soal, nama_soal, mapel, agama_target, target_mode, jml_pg, opsi_pg, jml_uraian,
                     bobot_pg, bobot_uraian, komposisi, durasi, min_pengerjaan, link_pdf, tampil_nilai, kesempatan,
                     deteksi_tab, maks_pelanggaran, aksi_pelanggaran, mulai_at, selesai_at,
                     status, kode_soal, id_guru)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    array_merge($data, [$kode, Auth::id()])
                );
            }
            if ($targetMode === 'kelas') {
                foreach (array_unique($kelasIds) as $kid) {
                    if ($kid > 0 && DB::one('SELECT id FROM kelas WHERE id = ?', [$kid])) {
                        DB::exec('INSERT IGNORE INTO soal_kelas (id_soal, id_kelas) VALUES (?,?)', [$soalId, $kid]);
                    }
                }
            } elseif ($targetMode === 'jenjang') {
                foreach (array_unique($jenjangTarget) as $tingkat) {
                    DB::exec('INSERT IGNORE INTO soal_jenjang (id_soal, tingkat) VALUES (?,?)', [$soalId, $tingkat]);
                }
            } else {
                foreach (array_unique($siswaTarget) as $sid) {
                    DB::exec('INSERT IGNORE INTO soal_siswa (id_soal, id_siswa) VALUES (?,?)', [$soalId, $sid]);
                }
            }
            audit($id > 0 ? 'soal_ubah' : 'soal_tambah', 'soal', (int) $soalId);
            return (int) $soalId;
        });

        clear_old();
        $pesan = $id > 0 ? 'Ujian diperbarui.' : 'Ujian dibuat. Silakan isi kunci jawaban.';
        // Cek cepat (4 detik) apakah PDF Google Drive bisa dibuka publik.
        // Hanya memperingatkan, tidak memblokir penyimpanan.
        $peringatanPdf = $this->peringatanPdf($masukanPdf, $link);
        if ($peringatanPdf !== '') {
            flash('warning', $peringatanPdf);
        }
        if ($statusTurun) {
            flash('warning', $pesan . ' Status diturunkan ke Draft karena ' . $alasanTurun
                . '. Periksa kunci lalu aktifkan kembali.');
        } else {
            flash_success($pesan);
        }
        redirect('kunci', ['id' => $soalId]);
    }

    /**
     * Peringatan bila dokumen soal Google Drive tidak dapat dibuka publik.
     * Mengembalikan string kosong bila tidak ada masalah. Penyimpanan tidak
     * pernah diblokir — guru tetap bisa menyimpan sambil memperbaiki tautan.
     */
    private function peringatanPdf(string $masukan, string $link): string
    {
        if (trim($masukan) === '') {
            return '';
        }
        $jenis = drive_tautan_jenis($masukan);
        if ($jenis === 'folder') {
            return 'Tautan yang disalin menunjuk ke FOLDER, bukan berkas PDF. '
                . 'Buka berkas PDF-nya (bukan foldernya) → Bagikan → "Siapa saja yang memiliki link" (Viewer) → Salin tautan.';
        }
        if ($jenis === 'dokumen') {
            return 'Tautan menunjuk ke Google Docs/Sheets/Slides, bukan berkas PDF. '
                . 'Ekspor ke PDF (File → Download → PDF), unggah hasilnya ke Drive, lalu tempel tautan berkas PDF itu.';
        }
        if ($link === '') {
            return 'Tautan/ID Google Drive tidak dikenali. Salin tautan lengkap berkas PDF dari Drive '
                . '(berakhiran .../file/d/<ID>/view), bukan hanya sebagian teksnya.';
        }
        $statusPdf = drive_pdf_status($link);
        if (!in_array($statusPdf, [401, 403, 404], true)) {
            return '';
        }
        $alasan = match ($statusPdf) {
            404 => 'berkas tidak ditemukan (mungkin sudah dihapus)',
            403 => 'berkas menolak akses — periksa pengaturan pembagian',
            default => 'Google meminta login untuk membuka berkas ini',
        };
        return 'ID file tersimpan, tetapi Google menolak akses (HTTP ' . $statusPdf . '): ' . $alasan . '. '
            . 'Cek cepat: buka tautan di jendela penyamaran (tanpa login) — bila masih diminta login, berkas memang belum publik. '
            . 'Perbaikinya: Google Drive → Bagikan → "Siapa saja yang memiliki link" (Viewer), lalu Salin tautan dan tempel '
            . 'tautan lengkapnya di sini (bila ada keterangan resourcekey=..., biarkan — itu wajib untuk berkas lama). '
            . 'Bila akun Drive Anda Google Drive sekolah (Workspace), pembagian ke luar organisasi mungkin dibatasi admin.';
    }

    /** Judul ujian otomatis karena field judul dihapus dari form. */
    private function judulOtomatis(string $mapel): string
    {
        $mapel = trim($mapel);
        return $mapel !== '' ? 'Ujian ' . $mapel : 'Ujian ' . date('d/m/Y');
    }

    private function uniqueToken(): string
    {
        do {
            $t = token_ujian(6);
        } while (DB::scalar('SELECT 1 FROM soal WHERE kode_soal = ?', [$t]));
        return $t;
    }

    /**
     * Sajikan dokumen soal PDF dari domain aplikasi sendiri (same-origin).
     * Menghindari iframe Google Drive yang gagal saat cookie pihak ketiga
     * diblokir atau berkas belum siap dipratinjau.
     */
    public function pdf(): void
    {
        if (!Auth::check()) {
            $this->pdfError(401, 'Sesi berakhir', 'Silakan masuk kembali untuk membuka dokumen soal.');
            return;
        }
        $id = int_input('id', 0, $_GET);
        $soal = $id > 0 ? DB::one('SELECT * FROM soal WHERE id = ?', [$id]) : null;
        if (!$soal) {
            $this->pdfError(404, 'Ujian tidak ditemukan', 'Dokumen yang diminta tidak tersedia.');
            return;
        }
        if (!Auth::isStaff()) {
            $punya = DB::scalar(
                'SELECT 1 FROM riwayat_ujian WHERE id_soal = ? AND id_siswa = ? LIMIT 1',
                [$id, Auth::id()]
            );
            if (!$punya) {
                $this->pdfError(403, 'Tidak berhak', 'Dokumen ini bukan untuk akun Anda.');
                return;
            }
        }

        $fileId = drive_file_id((string) ($soal['link_pdf'] ?? ''));
        if ($fileId === '') {
            $this->pdfError(404, 'Tautan PDF belum diatur', 'Guru belum memasang tautan Google Drive yang valid.');
            return;
        }

        $cache = pdf_cache_path($soal);
        $segar = is_file($cache) && (int) filesize($cache) > 800 && (int) filemtime($cache) > time() - 12 * 3600;
        if (!$segar) {
            $dir = dirname($cache);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $tmp = $cache . '.tmp';
            $unduh = drive_pdf_download($fileId, $tmp, drive_file_key((string) $soal['link_pdf']));
            if ($unduh['ok']) {
                @rename($tmp, $cache);
            } else {
                @unlink($tmp);
                $adaCacheLama = is_file($cache) && (int) filesize($cache) > 800;
                if (!$adaCacheLama) {
                    $pesan = match ($unduh['status']) {
                        401, 403 => 'Google Drive menolak akses. Pastikan berkas dibagikan '
                            . '"Siapa saja yang memiliki link" (Viewer).',
                        404 => 'Berkas PDF tidak ditemukan di Google Drive (mungkin sudah dihapus).',
                        default => 'Dokumen belum dapat diunduh (' . $unduh['error'] . '). Periksa koneksi server.',
                    };
                    $this->pdfError(502, 'Dokumen soal belum bisa dibuka', $pesan, (string) $soal['link_pdf']);
                    return;
                }
            }
        }

        if (!is_file($cache) || (int) filesize($cache) < 800) {
            $this->pdfError(502, 'Dokumen soal belum bisa dibuka', 'Berkas PDF tidak tersedia.', (string) $soal['link_pdf']);
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($cache));
        header('Content-Disposition: inline; filename="soal-' . (int) $soal['id'] . '.pdf"');
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($cache);
        exit;
    }

    /** Halaman galat sederhana di dalam iframe dokumen. */
    private function pdfError(int $status, string $judul, string $pesan, string $drive = ''): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');
        $tautan = $drive !== ''
            ? '<a class="btn" href="' . e($drive) . '" target="_blank" rel="noopener">Buka di Google Drive</a>'
            : '';
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;'
            . 'display:grid;place-items:center;min-height:100vh;padding:1.5rem}'
            . '.box{max-width:34rem;text-align:center}'
            . 'h1{font-size:1.15rem;margin:.2rem 0 .5rem}'
            . 'p{color:#94a3b8;font-size:.9rem;line-height:1.55;margin:0 0 1rem}'
            . '.btn{display:inline-block;background:#0ea5a4;color:#04211f;text-decoration:none;font-weight:700;'
            . 'padding:.5rem .9rem;border-radius:.5rem;font-size:.85rem}</style>'
            . '<div class="box"><div style="font-size:2.2rem">📄</div>'
            . '<h1>' . e($judul) . '</h1><p>' . e($pesan) . '</p>' . $tautan . '</div>';
        exit;
    }

    /** Halaman kelola kolaborator ujian (khusus pemilik/superadmin). */
    public function kolaborator(): void
    {
        Auth::requireStaff();
        $id = int_input('id', 0, $_GET);
        $soal = $id > 0 ? DB::one('SELECT * FROM soal WHERE id = ?', [$id]) : null;
        if (!$soal) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        if (!Auth::pemilikSoal($soal)) {
            throw new Abort('Hanya pemilik ujian yang dapat mengatur kolaborator.');
        }
        $pemilik = DB::one('SELECT id, nama, email FROM users WHERE id = ?', [(int) $soal['id_guru']]);
        $kolaborator = DB::all(
            'SELECT u.id, u.nama, u.email, u.aktif, kl.created_at
               FROM soal_kolaborator kl JOIN users u ON u.id = kl.id_user
              WHERE kl.id_soal = ? ORDER BY u.nama',
            [$id]
        );
        $terpilih = array_map(static fn ($k) => (int) $k['id'], $kolaborator);
        $kandidat = DB::all(
            "SELECT id, nama, email FROM users
              WHERE role = 'guru' AND aktif = 1 AND id <> ? ORDER BY nama",
            [(int) $soal['id_guru']]
        );
        View::render('soal/kolaborator', compact('soal', 'pemilik', 'kolaborator', 'terpilih', 'kandidat'));
    }

    public function kolaboratorTambah(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $soal = $id > 0 ? DB::one('SELECT * FROM soal WHERE id = ?', [$id]) : null;
        if (!$soal) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        if (!Auth::pemilikSoal($soal)) {
            throw new Abort('Hanya pemilik ujian yang dapat menambah kolaborator.');
        }
        $pilihan = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['id_guru'] ?? [])),
            static fn (int $uid): bool => $uid > 0 && $uid !== (int) $soal['id_guru']
        )));
        if ($pilihan === []) {
            flash_error('Pilih minimal satu guru untuk dijadikan kolaborator.');
            redirect('soal_kolaborator', ['id' => $id]);
        }
        $valid = array_map('intval', array_column(
            DB::all("SELECT id FROM users WHERE role = 'guru' AND aktif = 1"),
            'id'
        ));
        $ditambah = 0;
        foreach ($pilihan as $uid) {
            if (!in_array($uid, $valid, true)) {
                continue;
            }
            DB::exec('INSERT IGNORE INTO soal_kolaborator (id_soal, id_user) VALUES (?,?)', [$id, $uid]);
            $ditambah++;
        }
        audit('soal_kolaborator_tambah', 'soal', $id, ['jumlah' => $ditambah]);
        flash_success($ditambah > 0
            ? $ditambah . ' kolaborator ditambahkan. Mereka kini punya akses penuh ke ujian ini.'
            : 'Tidak ada kolaborator baru yang ditambahkan.');
        redirect('soal_kolaborator', ['id' => $id]);
    }

    public function kolaboratorHapus(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $idUser = int_input('id_user');
        $soal = $id > 0 ? DB::one('SELECT * FROM soal WHERE id = ?', [$id]) : null;
        if (!$soal) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        if (!Auth::pemilikSoal($soal)) {
            throw new Abort('Hanya pemilik ujian yang dapat menghapus kolaborator.');
        }
        DB::exec('DELETE FROM soal_kolaborator WHERE id_soal = ? AND id_user = ?', [$id, $idUser]);
        audit('soal_kolaborator_hapus', 'soal', $id, ['id_user' => $idUser]);
        flash_success('Kolaborator dikeluarkan dari ujian ini.');
        redirect('soal_kolaborator', ['id' => $id]);
    }

    public function delete(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $row = DB::one('SELECT * FROM soal WHERE id = ?', [$id]);
        if (!$row) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        if (!Auth::canManageSoal($row)) {
            throw new Abort('Anda tidak berhak menghapus ujian ini.');
        }
        DB::exec('DELETE FROM soal WHERE id = ?', [$id]);
        audit('soal_hapus', 'soal', $id);
        flash_success('Ujian dan seluruh data terkait dihapus.');
        redirect('soal');
    }

    public function toggle(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $row = DB::one('SELECT * FROM soal WHERE id = ?', [$id]);
        if (!$row) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        if (!Auth::canManageSoal($row)) {
            throw new Abort('Anda tidak berhak mengubah ujian ini.');
        }
        $baru = $row['status'] === 'Aktif' ? 'Draft' : 'Aktif';
        if ($baru === 'Aktif') {
            $jmlKunci = (int) DB::scalar('SELECT COUNT(*) FROM butir_soal WHERE id_soal = ?', [$id]);
            $harus = komposisi_total($row);
            if ($jmlKunci < $harus) {
                flash_error('Kunci jawaban belum lengkap (' . $jmlKunci . '/' . $harus . '). Lengkapi sebelum mengaktifkan.');
                redirect('soal');
            }
        }
        DB::exec('UPDATE soal SET status = ? WHERE id = ?', [$baru, $id]);
        audit('soal_status', 'soal', $id, ['status' => $baru]);
        flash_success('Status ujian menjadi ' . $baru . '.');
        redirect('soal');
    }

    public function duplicate(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $row = DB::one('SELECT * FROM soal WHERE id = ?', [$id]);
        if (!$row || !Auth::canManageSoal($row)) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        $newId = DB::tx(function () use ($row) {
            $kode = $this->uniqueToken();
            $newId = DB::insert(
                'INSERT INTO soal (kode_soal, jenis_ujian, mode_soal, nama_soal, mapel, agama_target, target_mode, jml_pg, opsi_pg, jml_uraian,
                 bobot_pg, bobot_uraian, komposisi, durasi, min_pengerjaan, link_pdf, tampil_nilai, kesempatan,
                 deteksi_tab, maks_pelanggaran, aksi_pelanggaran, mulai_at, selesai_at, status, id_guru)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $kode, $row['jenis_ujian'], 'PDF', $row['nama_soal'] . ' (Salinan)', $row['mapel'],
                    $row['agama_target'] ?? null, $row['target_mode'] ?? 'kelas',
                    $row['jml_pg'], $row['opsi_pg'], $row['jml_uraian'], $row['bobot_pg'], $row['bobot_uraian'],
                    $row['komposisi'] ?? null,
                    $row['durasi'], $row['min_pengerjaan'] ?? 0, $row['link_pdf'], $row['tampil_nilai'], $row['kesempatan'],
                    $row['deteksi_tab'], $row['maks_pelanggaran'],
                    $row['aksi_pelanggaran'], null, null, 'Draft', Auth::id(),
                ]
            );
            foreach (DB::all('SELECT * FROM butir_soal WHERE id_soal = ?', [$row['id']]) as $b) {
                DB::exec(
                    'INSERT INTO butir_soal (id_soal, nomor_soal, tipe_soal, pertanyaan, opsi, kunci, rubrik, pembahasan,
                     skor_benar, skor_salah, poin_maks) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [$newId, $b['nomor_soal'], $b['tipe_soal'], $b['pertanyaan'], $b['opsi'], $b['kunci'], $b['rubrik'],
                     $b['pembahasan'], $b['skor_benar'], $b['skor_salah'], $b['poin_maks']]
                );
            }
            DB::exec('INSERT IGNORE INTO soal_kelas (id_soal, id_kelas) SELECT ?, id_kelas FROM soal_kelas WHERE id_soal = ?', [$newId, $row['id']]);
            DB::exec('INSERT IGNORE INTO soal_jenjang (id_soal, tingkat) SELECT ?, tingkat FROM soal_jenjang WHERE id_soal = ?', [$newId, $row['id']]);
            DB::exec('INSERT IGNORE INTO soal_siswa (id_soal, id_siswa) SELECT ?, id_siswa FROM soal_siswa WHERE id_soal = ?', [$newId, $row['id']]);
            audit('soal_duplikat', 'soal', $newId, ['dari' => (int) $row['id']]);
            return $newId;
        });
        flash_success('Ujian diduplikasi sebagai draft.');
        redirect('kunci', ['id' => $newId]);
    }

    /* ------------------------------------------------------------
     * Editor kunci
     * ---------------------------------------------------------- */
    public function kunci(): void
    {
        Auth::requireStaff();
        $id = int_input('id', 0, $_GET);
        $soal = DB::one('SELECT * FROM soal WHERE id = ?', [$id]);
        if (!$soal) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        if (!Auth::canManageSoal($soal)) {
            throw new Abort('Anda tidak berhak mengubah ujian ini.');
        }
        $butir = [];
        foreach (DB::all('SELECT * FROM butir_soal WHERE id_soal = ? ORDER BY nomor_soal', [$id]) as $b) {
            $butir[(int) $b['nomor_soal']] = $b;
        }
        $total = komposisi_total($soal);
        $opsi = opsi_huruf((int) $soal['opsi_pg']);
        $komposisi = komposisi_efektif($soal);
        $pakaiKomposisi = komposisi_tersimpan($soal) !== [];
        View::render('kunci/editor', compact('soal', 'butir', 'total', 'opsi', 'komposisi', 'pakaiKomposisi'));
    }

    public function kunciSave(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $soal = DB::one('SELECT * FROM soal WHERE id = ?', [$id]);
        if (!$soal) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        if (!Auth::canManageSoal($soal)) {
            throw new Abort('Anda tidak berhak mengubah ujian ini.');
        }

        $jmlPg = (int) $soal['jml_pg'];
        $total = komposisi_total($soal);
        $isKompleks = $soal['jenis_ujian'] === 'Kompleks';
        $pakaiKomposisi = komposisi_tersimpan($soal) !== [];
        $opsiHuruf = opsi_huruf((int) $soal['opsi_pg']);
        // Soal hanya dari PDF: teks pertanyaan tidak lagi diisi, tapi nilai lama dipertahankan.
        $pertanyaanLama = [];
        $opsiLama = [];
        foreach (DB::all('SELECT nomor_soal, pertanyaan, opsi FROM butir_soal WHERE id_soal = ?', [$id]) as $lb) {
            $pertanyaanLama[(int) $lb['nomor_soal']] = $lb['pertanyaan'];
            $opsiLamaArr = is_string($lb['opsi']) ? json_decode($lb['opsi'], true) : null;
            $opsiLama[(int) $lb['nomor_soal']] = is_array($opsiLamaArr) ? $opsiLamaArr : null;
        }

        $rows = [];
        for ($n = 1; $n <= $total; $n++) {
            $tipe = $pakaiKomposisi
                ? komposisi_tipe($soal, $n)
                : ($isKompleks
                    ? (string) ($_POST['tipe'][$n] ?? 'PG')
                    : ($n <= $jmlPg ? 'PG' : 'Uraian'));
            if (!tipe_soal_valid($tipe)) {
                $tipe = 'PG';
            }
            if (!$pakaiKomposisi && !$isKompleks && $n > $jmlPg) {
                $tipe = 'Uraian';
            }

            $skorBenar = (float) ($_POST['skor_benar'][$n] ?? 1);
            $skorSalah = (float) ($_POST['skor_salah'][$n] ?? 0);
            if ($skorBenar <= 0) {
                $skorBenar = 1;
            }
            $poinMaks = (float) ($_POST['poin_maks'][$n] ?? $skorBenar);
            if ($poinMaks <= 0) {
                $poinMaks = $skorBenar;
            }
            $pertanyaan = trim((string) ($_POST['pertanyaan'][$n] ?? ($pertanyaanLama[$n] ?? '')));
            $pembahasan = trim((string) ($_POST['pembahasan'][$n] ?? ''));

            $kunci = null;
            $opsiJson = null;

            if ($tipe === 'PG') {
                $k = strtoupper(trim((string) ($_POST['pg_kunci'][$n] ?? '')));
                if ($k === '') {
                    continue;
                }
                if (!in_array($k, $opsiHuruf, true)) {
                    continue;
                }
                $kunci = ['jawaban' => $k];
                $opsiTeks = (array) ($_POST['opsi'][$n] ?? []);
                if (array_filter($opsiTeks)) {
                    $opsiJson = array_values(array_map('strval', $opsiTeks));
                } elseif (!isset($_POST['opsi'][$n])) {
                    // Field "Teks opsi" sudah dihapus dari form: teks lama dipertahankan.
                    $opsiJson = $opsiLama[$n] ?? null;
                }
            } elseif ($tipe === 'PG_Kompleks') {
                $ks = array_values(array_filter(array_map(
                    static fn ($x) => strtoupper(trim((string) $x)),
                    (array) ($_POST['pgk_kunci'][$n] ?? [])
                )));
                if ($ks === []) {
                    continue;
                }
                $kunci = ['jawaban' => $ks];
                $opsiTeks = (array) ($_POST['opsi'][$n] ?? []);
                if (array_filter($opsiTeks)) {
                    $opsiJson = array_values(array_map('strval', $opsiTeks));
                } elseif (!isset($_POST['opsi'][$n])) {
                    $opsiJson = $opsiLama[$n] ?? null;
                }
            } elseif ($tipe === 'Benar_Salah') {
                $pernyataan = array_values((array) ($_POST['bs_pernyataan'][$n] ?? []));
                $kunciArr = array_values((array) ($_POST['bs_kunci'][$n] ?? []));
                $items = [];
                foreach ($pernyataan as $i => $p) {
                    $p = trim((string) $p);
                    if ($p === '') {
                        continue;
                    }
                    $items[] = ['pernyataan' => $p, 'kunci' => ((string) ($kunciArr[$i] ?? '1')) === '1'];
                }
                if ($items === []) {
                    continue;
                }
                $kunci = $items;
            } elseif ($tipe === 'Menjodohkan') {
                $kiri = array_values((array) ($_POST['mj_kiri'][$n] ?? []));
                $kanan = array_values((array) ($_POST['mj_kanan'][$n] ?? []));
                $items = [];
                foreach ($kiri as $i => $k) {
                    $k = trim((string) $k);
                    $v = trim((string) ($kanan[$i] ?? ''));
                    if ($k === '' || $v === '') {
                        continue;
                    }
                    $items[] = ['kiri' => $k, 'kanan' => $v];
                }
                if ($items === []) {
                    continue;
                }
                $kunci = $items;
            } elseif ($tipe === 'Isian') {
                $alt = array_values(array_filter(array_map('trim', (array) ($_POST['isian'][$n] ?? []))));
                if ($alt === []) {
                    continue;
                }
                $kunci = ['jawaban' => $alt];
            } else { // Uraian
                $rubrik = trim((string) ($_POST['rubrik'][$n] ?? ''));
                $kunci = $rubrik !== '' ? ['rubrik' => $rubrik] : ['rubrik' => ''];
            }

            $rows[] = [
                'nomor' => $n,
                'tipe' => $tipe,
                'pertanyaan' => $pertanyaan !== '' ? $pertanyaan : null,
                'opsi' => $opsiJson ? json_encode($opsiJson, JSON_UNESCAPED_UNICODE) : null,
                'kunci' => json_encode($kunci, JSON_UNESCAPED_UNICODE),
                'rubrik' => $tipe === 'Uraian' ? trim((string) ($_POST['rubrik'][$n] ?? '')) : null,
                'pembahasan' => $pembahasan !== '' ? $pembahasan : null,
                'skor_benar' => $skorBenar,
                'skor_salah' => $skorSalah,
                'poin_maks' => $tipe === 'Uraian' ? $poinMaks : $skorBenar,
            ];
        }

        if ($rows === []) {
            flash_error('Tidak ada kunci yang dapat disimpan. Periksa kembali isian Anda.');
            redirect('kunci', ['id' => $id]);
        }

        DB::tx(function () use ($id, $rows) {
            DB::exec('DELETE FROM butir_soal WHERE id_soal = ?', [$id]);
            foreach ($rows as $r) {
                DB::exec(
                    'INSERT INTO butir_soal (id_soal, nomor_soal, tipe_soal, pertanyaan, opsi, kunci, rubrik, pembahasan,
                     skor_benar, skor_salah, poin_maks) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [$id, $r['nomor'], $r['tipe'], $r['pertanyaan'], $r['opsi'], $r['kunci'], $r['rubrik'],
                     $r['pembahasan'], $r['skor_benar'], $r['skor_salah'], $r['poin_maks']]
                );
            }
            audit('kunci_simpan', 'soal', $id, ['jumlah' => count($rows)]);
        });

        flash_success('Kunci jawaban disimpan (' . count($rows) . '/' . $total . ' nomor).');
        redirect('kunci', ['id' => $id]);
    }

    public function kunciReset(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $soal = DB::one('SELECT * FROM soal WHERE id = ?', [$id]);
        if (!$soal || !Auth::canManageSoal($soal)) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        DB::exec('DELETE FROM butir_soal WHERE id_soal = ?', [$id]);
        audit('kunci_reset', 'soal', $id);
        flash_success('Seluruh kunci jawaban direset.');
        redirect('kunci', ['id' => $id]);
    }
}
