<?php
declare(strict_types=1);

final class NilaiController
{
    private function soalMilik(int $id): array
    {
        $s = DB::one('SELECT * FROM soal WHERE id = ?', [$id]);
        if (!$s) {
            throw new Abort('Ujian tidak ditemukan.');
        }
        if (!Auth::canManageSoal($s)) {
            throw new Abort('Anda tidak berhak mengakses ujian ini.');
        }
        return $s;
    }

    private function daftarSoal(): array
    {
        [$aksesSql, $params] = soal_akses_sql('s');
        return DB::all(
            'SELECT s.*, u.nama AS guru_nama FROM soal s JOIN users u ON u.id = s.id_guru
              WHERE ' . $aksesSql . ' ORDER BY s.created_at DESC',
            $params
        );
    }

    /** Kelas yang tercakup target peserta ujian (kelas/jenjang/siswa). */
    private function targetKelasList(array $soal): array
    {
        $mode = (string) ($soal['target_mode'] ?? 'kelas');
        if ($mode === 'jenjang') {
            return DB::all(
                'SELECT DISTINCT k.* FROM kelas k JOIN soal_jenjang sj ON sj.tingkat = k.tingkat
                  WHERE sj.id_soal = ? ORDER BY k.nama_kelas',
                [(int) $soal['id']]
            );
        }
        if ($mode === 'siswa') {
            return DB::all(
                'SELECT DISTINCT k.* FROM siswa sw JOIN kelas k ON k.id = sw.id_kelas
                 JOIN soal_siswa ss ON ss.id_siswa = sw.id
                 WHERE ss.id_soal = ? ORDER BY k.nama_kelas',
                [(int) $soal['id']]
            );
        }
        return DB::all(
            'SELECT k.* FROM kelas k JOIN soal_kelas sk ON sk.id_kelas = k.id
              WHERE sk.id_soal = ? ORDER BY k.nama_kelas',
            [(int) $soal['id']]
        );
    }

    /** Potongan SQL syarat siswa termasuk target ujian. Alias harus literal internal. */
    private function targetPesertaSql(array $soal, string $alias = 's'): array
    {
        $mode = (string) ($soal['target_mode'] ?? 'kelas');
        $id = (int) $soal['id'];
        if ($mode === 'jenjang') {
            return [
                "EXISTS (SELECT 1 FROM soal_jenjang sj JOIN kelas kt ON kt.tingkat = sj.tingkat
                          WHERE sj.id_soal = ? AND kt.id = {$alias}.id_kelas)",
                [$id],
            ];
        }
        if ($mode === 'siswa') {
            return [
                "EXISTS (SELECT 1 FROM soal_siswa ss WHERE ss.id_soal = ? AND ss.id_siswa = {$alias}.id)",
                [$id],
            ];
        }
        return [
            "EXISTS (SELECT 1 FROM soal_kelas sk WHERE sk.id_soal = ? AND sk.id_kelas = {$alias}.id_kelas)",
            [$id],
        ];
    }

    public function ujian(): void
    {
        Auth::requireStaff();
        $idSoal = int_input('id_soal', 0, $_GET);
        $idKelas = int_input('id_kelas', 0, $_GET);
        $daftar = $this->daftarSoal();

        if ($idSoal <= 0) {
            View::render('nilai/ujian', [
                'daftar' => $daftar, 'soal' => null, 'kelasList' => [], 'rows' => [], 'idKelas' => 0, 'stat' => null,
            ]);
            return;
        }

        $soal = $this->soalMilik($idSoal);
        $targetAgama = trim((string) ($soal['agama_target'] ?? ''));
        $kelasList = $this->targetKelasList($soal);
        [$targetSql, $targetParams] = $this->targetPesertaSql($soal, 's');

        $sql = 'SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON k.id = s.id_kelas
                WHERE ' . $targetSql;
        $params = $targetParams;
        if ($targetAgama !== '') {
            $sql .= ' AND LOWER(TRIM(s.agama)) = ?';
            $params[] = mb_strtolower($targetAgama);
        }
        if ($idKelas > 0) {
            $sql .= ' AND s.id_kelas = ?';
            $params[] = $idKelas;
        }
        $sql .= ' ORDER BY k.nama_kelas, s.nama_siswa';
        $siswa = DB::all($sql, $params);

        $riwayat = DB::all(
            'SELECT * FROM riwayat_ujian WHERE id_soal = ? ORDER BY id',
            [$idSoal]
        );
        $perSiswa = [];
        foreach ($riwayat as $r) {
            $sid = (int) $r['id_siswa'];
            $perSiswa[$sid][] = $r;
        }

        $rows = [];
        $nilaiList = [];
        foreach ($siswa as $s) {
            $best = null;
            $aktif = null;
            foreach ($perSiswa[(int) $s['id']] ?? [] as $r) {
                if ($r['status'] === 'Berlangsung') {
                    $aktif = $r;
                }
                if ($r['nilai_akhir'] !== null) {
                    if ($best === null || (float) $r['nilai_akhir'] > (float) $best['nilai_akhir']) {
                        $best = $r;
                    }
                } elseif ($best === null && $r['waktu_selesai'] !== null) {
                    $best = $r;
                }
            }
            if ($best && $best['nilai_akhir'] !== null) {
                $nilaiList[] = (float) $best['nilai_akhir'];
            }
            $rows[] = ['siswa' => $s, 'best' => $best, 'aktif' => $aktif, 'percobaan' => count($perSiswa[(int) $s['id']] ?? [])];
        }

        $stat = null;
        if ($nilaiList !== []) {
            sort($nilaiList);
            $n = count($nilaiList);
            $stat = [
                'jumlah' => $n,
                'rata' => array_sum($nilaiList) / $n,
                'min' => $nilaiList[0],
                'max' => $nilaiList[$n - 1],
                'median' => $n % 2 === 0 ? ($nilaiList[$n / 2 - 1] + $nilaiList[$n / 2]) / 2 : $nilaiList[intdiv($n, 2)],
                'tuntas' => count(array_filter($nilaiList, static fn ($v) => $v >= (float) setting('kkm', '75'))),
            ];
        }

        View::render('nilai/ujian', compact('daftar', 'soal', 'kelasList', 'rows', 'idKelas', 'stat'));
    }

    public function kelas(): void
    {
        Auth::requireStaff();
        $idKelas = int_input('id_kelas', 0, $_GET);
        $kelasList = DB::all('SELECT * FROM kelas ORDER BY nama_kelas');
        if ($idKelas <= 0) {
            View::render('nilai/kelas', ['kelasList' => $kelasList, 'kelas' => null, 'ujian' => [], 'siswa' => [], 'matriks' => []]);
            return;
        }
        $kelas = DB::one('SELECT * FROM kelas WHERE id = ?', [$idKelas]);
        if (!$kelas) {
            throw new Abort('Kelas tidak ditemukan.');
        }

        $targetSql = "(
            (s.target_mode = 'kelas' AND EXISTS (
                SELECT 1 FROM soal_kelas sk WHERE sk.id_soal = s.id AND sk.id_kelas = ?))
            OR (s.target_mode = 'jenjang' AND EXISTS (
                SELECT 1 FROM soal_jenjang sj WHERE sj.id_soal = s.id AND sj.tingkat = ?))
            OR (s.target_mode = 'siswa' AND EXISTS (
                SELECT 1 FROM soal_siswa ss JOIN siswa sw ON sw.id = ss.id_siswa
                 WHERE ss.id_soal = s.id AND sw.id_kelas = ?))
        )";
        $targetParams = [$idKelas, (string) $kelas['tingkat'], $idKelas];

        [$aksesSql, $aksesParams] = soal_akses_sql('s');
        $ujian = DB::all(
            "SELECT s.* FROM soal s WHERE s.status <> 'Draft' AND " . $targetSql
                . ' AND ' . $aksesSql . ' ORDER BY s.created_at',
            array_merge($targetParams, $aksesParams)
        );

        $siswa = DB::all('SELECT * FROM siswa WHERE id_kelas = ? ORDER BY nama_siswa', [$idKelas]);
        $matriks = [];
        foreach ($siswa as $s) {
            $matriks[(int) $s['id']] = [];
            foreach ($ujian as $u) {
                if (!agama_cocok($s['agama'], $u['agama_target'] ?? null)) {
                    continue;
                }
                if (($u['target_mode'] ?? 'kelas') === 'siswa'
                    && !DB::one('SELECT 1 FROM soal_siswa WHERE id_soal = ? AND id_siswa = ?', [$u['id'], $s['id']])) {
                    continue;
                }
                $best = DB::one(
                    'SELECT nilai_akhir FROM riwayat_ujian
                     WHERE id_soal = ? AND id_siswa = ? AND nilai_akhir IS NOT NULL
                     ORDER BY nilai_akhir DESC LIMIT 1',
                    [$u['id'], $s['id']]
                );
                $matriks[(int) $s['id']][(int) $u['id']] = $best['nilai_akhir'] ?? null;
            }
        }

        View::render('nilai/kelas', compact('kelasList', 'kelas', 'ujian', 'siswa', 'matriks'));
    }

    public function bukaKunci(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $r = DB::one(
            'SELECT ru.*, sw.nama_siswa FROM riwayat_ujian ru
             JOIN siswa sw ON sw.id = ru.id_siswa WHERE ru.id = ?',
            [$id]
        );
        if (!$r) {
            throw new Abort('Sesi ujian tidak ditemukan.');
        }
        $this->soalMilik((int) $r['id_soal']);
        if ($r['waktu_selesai'] !== null || $r['status'] !== 'Berlangsung') {
            flash_error('Sesi ujian ini sudah selesai dan tidak dapat dibuka kuncinya.');
            redirect('nilai', ['id_soal' => (int) $r['id_soal']]);
        }
        if ((int) ($r['terkunci'] ?? 0) !== 1) {
            flash('info', 'Sesi ujian ini tidak sedang terkunci.');
            redirect('nilai', ['id_soal' => (int) $r['id_soal']]);
        }

        DB::exec('UPDATE riwayat_ujian SET terkunci = 0, dikunci_pada = NULL WHERE id = ?', [$id]);
        audit('ujian_buka_kunci', 'riwayat_ujian', $id, [
            'id_soal' => (int) $r['id_soal'],
            'id_siswa' => (int) $r['id_siswa'],
        ]);
        flash_success(
            'Kunci ujian ' . $r['nama_siswa'] . ' dibuka. Siswa dapat melanjutkan setelah memuat ulang halaman.'
        );
        redirect('nilai', ['id_soal' => (int) $r['id_soal']]);
    }

    public function koreksi(): void
    {
        Auth::requireStaff();
        $id = int_input('id', 0, $_GET);
        $r = DB::one('SELECT * FROM riwayat_ujian WHERE id = ?', [$id]);
        if (!$r) {
            throw new Abort('Riwayat tidak ditemukan.');
        }
        $soal = $this->soalMilik((int) $r['id_soal']);
        $siswa = DB::one('SELECT * FROM siswa WHERE id = ?', [$r['id_siswa']]);

        $butirUraian = DB::all("SELECT * FROM butir_soal WHERE id_soal = ? AND tipe_soal = 'Uraian' ORDER BY nomor_soal", [$r['id_soal']]);
        $jawaban = [];
        foreach (DB::all('SELECT * FROM jawaban_siswa WHERE id_riwayat = ?', [$id]) as $j) {
            $jawaban[(int) $j['nomor_soal']] = $j;
        }

        View::render('nilai/koreksi', compact('r', 'soal', 'siswa', 'butirUraian', 'jawaban'));
    }

    public function koreksiMassal(): void
    {
        Auth::requireStaff();
        $idSoal = int_input('id_soal', 0, $_GET);
        $nomor = int_input('nomor', 0, $_GET);
        $daftar = $this->daftarSoal();
        if ($idSoal <= 0) {
            View::render('nilai/koreksi_massal', [
                'daftar' => $daftar, 'soal' => null, 'butirUraian' => [], 'nomor' => 0, 'items' => [],
            ]);
            return;
        }
        $soal = $this->soalMilik($idSoal);
        $butirUraian = DB::all("SELECT * FROM butir_soal WHERE id_soal = ? AND tipe_soal = 'Uraian' ORDER BY nomor_soal", [$idSoal]);
        $items = [];
        if ($nomor > 0) {
            $items = DB::all(
                'SELECT j.*, ru.id AS rid, sw.nama_siswa, k.nama_kelas
                 FROM jawaban_siswa j
                 JOIN riwayat_ujian ru ON ru.id = j.id_riwayat
                 JOIN siswa sw ON sw.id = ru.id_siswa
                 JOIN kelas k ON k.id = sw.id_kelas
                 WHERE ru.id_soal = ? AND j.nomor_soal = ? AND ru.waktu_selesai IS NOT NULL
                 ORDER BY k.nama_kelas, sw.nama_siswa',
                [$idSoal, $nomor]
            );
        }
        View::render('nilai/koreksi_massal', compact('daftar', 'soal', 'butirUraian', 'nomor', 'items'));
    }

    public function koreksiSimpan(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $skor = (array) ($_POST['skor'] ?? []);
        $catatan = (array) ($_POST['catatan'] ?? []);
        $kembali = str_input('kembali', 'nilai');

        $riwayatTersentuh = [];
        foreach ($skor as $idJawaban => $nilai) {
            $idJawaban = (int) $idJawaban;
            $row = DB::one(
                'SELECT j.*, b.poin_maks, b.tipe_soal, ru.id_soal, ru.id AS rid
                 FROM jawaban_siswa j
                 JOIN riwayat_ujian ru ON ru.id = j.id_riwayat
                 JOIN butir_soal b ON b.id_soal = ru.id_soal AND b.nomor_soal = j.nomor_soal
                 WHERE j.id = ?',
                [$idJawaban]
            );
            if (!$row || $row['tipe_soal'] !== 'Uraian') {
                continue;
            }
            $soal = $this->soalMilik((int) $row['id_soal']);
            $nilai = max(0.0, min(100.0, (float) $nilai));
            $raw = round($nilai / 100 * (float) $row['poin_maks'], 2);
            DB::exec('UPDATE jawaban_siswa SET skor = ?, catatan = ? WHERE id = ?', [
                $raw, mb_substr(trim((string) ($catatan[$idJawaban] ?? '')), 0, 250), $idJawaban,
            ]);
            $riwayatTersentuh[(int) $row['rid']] = true;
        }

        foreach (array_keys($riwayatTersentuh) as $rid) {
            Penilaian::hitung((int) $rid);
        }

        audit('koreksi_simpan', 'riwayat_ujian', null, ['jumlah' => count($skor)]);
        flash_success('Koreksi disimpan. Nilai diperbarui.');
        if ($kembali === 'massal') {
            redirect('koreksi_massal', ['id_soal' => int_input('id_soal'), 'nomor' => int_input('nomor')]);
        }
        redirect('nilai', ['id_soal' => int_input('id_soal')]);
    }

    public function regrade(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $idSoal = int_input('id_soal');
        if ($idSoal > 0) {
            $this->soalMilik($idSoal);
            $n = Penilaian::hitungUlangSoal($idSoal);
        } else {
            $daftar = $this->daftarSoal();
            $n = 0;
            foreach ($daftar as $s) {
                $n += Penilaian::hitungUlangSoal((int) $s['id']);
            }
        }
        audit('regrade', 'soal', $idSoal > 0 ? $idSoal : null, ['riwayat' => $n]);
        flash_success('Re-grade selesai untuk ' . $n . ' riwayat ujian.');
        redirect('nilai', $idSoal > 0 ? ['id_soal' => $idSoal] : []);
    }

    public function hapus(): void
    {
        Auth::requireStaff();
        Csrf::check();
        $id = int_input('id');
        $r = DB::one('SELECT * FROM riwayat_ujian WHERE id = ?', [$id]);
        if (!$r) {
            throw new Abort('Riwayat tidak ditemukan.');
        }
        $this->soalMilik((int) $r['id_soal']);
        DB::exec('DELETE FROM riwayat_ujian WHERE id = ?', [$id]);
        audit('hasil_hapus', 'riwayat_ujian', $id);
        flash_success('Hasil ujian siswa dihapus.');
        redirect('nilai', ['id_soal' => $r['id_soal']]);
    }

    public function ekspor(): void
    {
        Auth::requireStaff();
        $idSoal = int_input('id_soal', 0, $_GET);
        $idKelas = int_input('id_kelas', 0, $_GET);
        $soal = $this->soalMilik($idSoal);

        [$targetSql, $targetParams] = $this->targetPesertaSql($soal, 's');
        $sql = 'SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON k.id = s.id_kelas
                WHERE ' . $targetSql;
        $params = $targetParams;
        $targetAgama = trim((string) ($soal['agama_target'] ?? ''));
        if ($targetAgama !== '') {
            $sql .= ' AND LOWER(TRIM(s.agama)) = ?';
            $params[] = mb_strtolower($targetAgama);
        }
        if ($idKelas > 0) {
            $sql .= ' AND s.id_kelas = ?';
            $params[] = $idKelas;
        }
        $sql .= ' ORDER BY k.nama_kelas, s.nama_siswa';

        $header = ['No', 'Nama Siswa', 'Kelas', 'Username', 'Percobaan', 'Nilai PG', 'Nilai Uraian', 'Skor Akhir', 'Status'];
        $data = [$header];
        $no = 1;
        foreach (DB::all($sql, $params) as $s) {
            $best = DB::one(
                'SELECT * FROM riwayat_ujian WHERE id_soal = ? AND id_siswa = ? AND waktu_selesai IS NOT NULL
                 ORDER BY (nilai_akhir IS NULL) ASC, nilai_akhir DESC LIMIT 1',
                [$idSoal, $s['id']]
            );
            $jumlah = (int) DB::scalar('SELECT COUNT(*) FROM riwayat_ujian WHERE id_soal = ? AND id_siswa = ?', [$idSoal, $s['id']]);
            $data[] = [
                $no++,
                $s['nama_siswa'],
                $s['nama_kelas'],
                $s['username'],
                $jumlah,
                $best['nilai_pg'] !== null ? (float) $best['nilai_pg'] : '',
                $best['nilai_uraian'] !== null ? (float) $best['nilai_uraian'] : '',
                $best['nilai_akhir'] !== null ? (float) $best['nilai_akhir'] : '',
                $best['status'] ?? 'Belum mengerjakan',
            ];
        }

        (new Xlsx('Hasil Ujian'))
            ->addSheet('Nilai', $data)
            ->save('nilai-' . preg_replace('/[^A-Za-z0-9]+/', '-', $soal['nama_soal']) . '-' . date('Ymd') . '.xlsx');
    }

    public function analisis(): void
    {
        Auth::requireStaff();
        $idSoal = int_input('id_soal', 0, $_GET);
        $daftar = $this->daftarSoal();
        $soal = null;
        $butir = [];
        if ($idSoal > 0) {
            $soal = $this->soalMilik($idSoal);
            $butir = DB::all('SELECT * FROM butir_soal WHERE id_soal = ? ORDER BY nomor_soal', [$idSoal]);
            foreach ($butir as &$b) {
                $total = (int) DB::scalar(
                    "SELECT COUNT(*) FROM jawaban_siswa j JOIN riwayat_ujian r ON r.id = j.id_riwayat
                     WHERE r.id_soal = ? AND j.nomor_soal = ? AND r.waktu_selesai IS NOT NULL",
                    [$idSoal, $b['nomor_soal']]
                );
                $benar = 0;
                $salah = 0;
                $kosong = 0;
                foreach (DB::all(
                    "SELECT j.* FROM jawaban_siswa j JOIN riwayat_ujian r ON r.id = j.id_riwayat
                     WHERE r.id_soal = ? AND j.nomor_soal = ? AND r.waktu_selesai IS NOT NULL",
                    [$idSoal, $b['nomor_soal']]
                ) as $j) {
                    if ($j['jawaban'] === null || $j['jawaban'] === 'null') {
                        $kosong++;
                    } elseif ((int) $j['benar'] === 1) {
                        $benar++;
                    } else {
                        $salah++;
                    }
                }
                $b['stat_total'] = $total;
                $b['stat_benar'] = $benar;
                $b['stat_salah'] = $salah;
                $b['stat_kosong'] = $kosong;
                $b['persen'] = $total > 0 ? round($benar / $total * 100, 1) : 0;
            }
            unset($b);
        }
        View::render('nilai/analisis', compact('daftar', 'soal', 'butir', 'idSoal'));
    }
}
