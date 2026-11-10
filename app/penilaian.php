<?php
declare(strict_types=1);

/**
 * Mesin penilaian Examora.
 * Sumber kebenaran tunggal untuk semua perhitungan nilai.
 */
final class Penilaian
{
    public static function normalisasi(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        $s = (string) preg_replace('/[.,;:!?]+$/u', '', $s);
        return $s;
    }

    private static function boolOf(mixed $v): ?bool
    {
        if (is_bool($v)) {
            return $v;
        }
        $s = strtoupper(trim((string) $v));
        if (in_array($s, ['B', 'BENAR', 'TRUE', '1', 'T', 'Y', 'YA'], true)) {
            return true;
        }
        if (in_array($s, ['S', 'SALAH', 'FALSE', '0', 'F', 'N', 'TIDAK'], true)) {
            return false;
        }
        return null;
    }

    /**
     * Hitung skor satu butir.
     * @return array{skor: float, benar: ?bool, kosong: bool}
     */
    public static function nilaiButir(array $b, mixed $jawaban): array
    {
        $tipe = (string) $b['tipe_soal'];
        $kunci = is_array($b['kunci'] ?? null)
            ? $b['kunci']
            : (json_decode((string) ($b['kunci'] ?? ''), true) ?: []);
        $sb = (float) $b['skor_benar'];
        $ss = (float) $b['skor_salah'];
        $maks = (float) ($b['poin_maks'] ?? $sb);

        $kosong = $jawaban === null || $jawaban === '' || $jawaban === [] || $jawaban === '[]';
        if ($kosong) {
            return ['skor' => 0.0, 'benar' => null, 'kosong' => true];
        }

        switch ($tipe) {
            case 'PG':
                $k = strtoupper(trim((string) ($kunci['jawaban'] ?? '')));
                $j = strtoupper(trim((string) (is_array($jawaban) ? '' : $jawaban)));
                $ok = $k !== '' && $j === $k;
                return ['skor' => $ok ? $sb : $ss, 'benar' => $ok, 'kosong' => false];

            case 'PG_Kompleks':
                $k = array_map(static fn ($x) => strtoupper(trim((string) $x)), (array) ($kunci['jawaban'] ?? []));
                $j = array_map(static fn ($x) => strtoupper(trim((string) $x)), is_array($jawaban) ? $jawaban : [$jawaban]);
                sort($k);
                sort($j);
                $ok = $k !== [] && $k === $j;
                return ['skor' => $ok ? $sb : $ss, 'benar' => $ok, 'kosong' => false];

            case 'Benar_Salah':
                $items = array_values((array) $kunci);
                $n = count($items);
                if ($n === 0) {
                    return ['skor' => 0.0, 'benar' => null, 'kosong' => false];
                }
                $j = is_array($jawaban) ? array_values($jawaban) : [];
                $skor = 0.0;
                $semua = true;
                $ada = false;
                foreach ($items as $i => $item) {
                    $kunciBool = self::boolOf($item['kunci'] ?? null);
                    $jwb = self::boolOf($j[$i] ?? null);
                    if ($jwb === null) {
                        $semua = false;
                        continue;
                    }
                    $ada = true;
                    if ($jwb === $kunciBool) {
                        $skor += $sb / $n;
                    } else {
                        $skor += $ss / $n;
                        $semua = false;
                    }
                }
                if (!$ada) {
                    return ['skor' => 0.0, 'benar' => null, 'kosong' => true];
                }
                if ($ss < 0 && $skor < 0) {
                    $skor = 0.0;
                }
                return ['skor' => round($skor, 4), 'benar' => $semua, 'kosong' => false];

            case 'Menjodohkan':
                $items = array_values((array) $kunci);
                $n = count($items);
                if ($n === 0) {
                    return ['skor' => 0.0, 'benar' => null, 'kosong' => false];
                }
                $j = is_array($jawaban) ? $jawaban : [];
                $skor = 0.0;
                $semua = true;
                $ada = false;
                foreach ($items as $i => $item) {
                    $benar = self::normalisasi((string) ($item['kanan'] ?? ''));
                    $pilih = self::normalisasi((string) ($j[$i] ?? ($j[(string) $i] ?? '')));
                    if ($pilih === '') {
                        $semua = false;
                        continue;
                    }
                    $ada = true;
                    if ($pilih === $benar) {
                        $skor += $sb / $n;
                    } else {
                        $skor += $ss / $n;
                        $semua = false;
                    }
                }
                if (!$ada) {
                    return ['skor' => 0.0, 'benar' => null, 'kosong' => true];
                }
                if ($ss < 0 && $skor < 0) {
                    $skor = 0.0;
                }
                return ['skor' => round($skor, 4), 'benar' => $semua, 'kosong' => false];

            case 'Isian':
                $alt = array_map(
                    static fn ($x) => self::normalisasi((string) $x),
                    (array) ($kunci['jawaban'] ?? [])
                );
                $j = self::normalisasi(is_array($jawaban) ? (string) reset($jawaban) : (string) $jawaban);
                $ok = $j !== '' && in_array($j, $alt, true);
                return ['skor' => $ok ? $sb : $ss, 'benar' => $ok, 'kosong' => false];

            case 'Uraian':
                return ['skor' => 0.0, 'benar' => null, 'kosong' => false];
        }

        return ['skor' => 0.0, 'benar' => null, 'kosong' => true];
    }

    /**
     * Hitung ulang satu riwayat ujian dan simpan hasilnya.
     * @return array<string,mixed>
     */
    public static function hitung(int $idRiwayat): array
    {
        $r = DB::one(
            'SELECT ru.*, s.jenis_ujian, s.bobot_pg, s.bobot_uraian, s.jml_pg, s.jml_uraian, s.komposisi
             FROM riwayat_ujian ru JOIN soal s ON s.id = ru.id_soal
             WHERE ru.id = ?',
            [$idRiwayat]
        );
        if (!$r) {
            throw new Abort('Riwayat ujian tidak ditemukan.');
        }

        $butir = DB::all('SELECT * FROM butir_soal WHERE id_soal = ? ORDER BY nomor_soal', [$r['id_soal']]);
        $jawaban = [];
        foreach (DB::all('SELECT * FROM jawaban_siswa WHERE id_riwayat = ?', [$idRiwayat]) as $row) {
            $jawaban[(int) $row['nomor_soal']] = $row;
        }

        /* Kelompok penilaian mengikuti komposisi soal: satu kelompok per jenis
           soal (dengan rentang nomor + bobot). Ujian lama tanpa komposisi
           otomatis menjadi dua kelompok: objektif (bobot_pg) dan uraian.
           Nilai akhir = jumlah (nilai kelompok x bobot) / total bobot. */
        $komposisi = komposisi_efektif([
            'komposisi' => $r['komposisi'] ?? null,
            'jml_pg' => $r['jml_pg'] ?? 0,
            'jml_uraian' => $r['jml_uraian'] ?? 0,
            'bobot_pg' => $r['bobot_pg'] ?? 0,
            'bobot_uraian' => $r['bobot_uraian'] ?? 0,
        ]);

        $kelompok = [];
        foreach ($komposisi as $kb) {
            $kelompok[] = [
                'tipe' => (string) $kb['tipe'],
                'uraian' => (string) $kb['tipe'] === 'Uraian',
                'bobot' => (float) $kb['bobot'],
                'dari' => (int) $kb['dari'],
                'sampai' => (int) $kb['sampai'],
                'skor' => 0.0,
                'maks' => 0.0,
                'dinilai' => 0,
                'belum' => 0,
            ];
        }

        $indeksNomor = static function (int $nomor) use ($kelompok): ?int {
            foreach ($kelompok as $i => $g) {
                if ($nomor >= $g['dari'] && $nomor <= $g['sampai']) {
                    return $i;
                }
            }
            return null;
        };

        foreach ($butir as $b) {
            $nomor = (int) $b['nomor_soal'];
            $row = $jawaban[$nomor] ?? null;
            $i = $indeksNomor($nomor);
            if ($i === null) {
                continue; // butir di luar komposisi (ujian pernah diubah)
            }

            if ($b['tipe_soal'] === 'Uraian') {
                $maks = max(0.0, (float) $b['poin_maks']);
                $kelompok[$i]['maks'] += $maks;
                if ($row && $row['skor'] !== null) {
                    $kelompok[$i]['skor'] += max(0.0, (float) $row['skor']);
                    $kelompok[$i]['dinilai']++;
                } else {
                    $kelompok[$i]['belum']++;
                }
                continue;
            }

            $kelompok[$i]['maks'] += max(0.0, (float) $b['skor_benar']);
            $jawabanMentah = $row['jawaban'] ?? null;
            if ($jawabanMentah !== null) {
                $decoded = json_decode((string) $jawabanMentah, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $jawabanMentah = $decoded;
                }
            }
            $hasil = self::nilaiButir($b, $jawabanMentah);
            $kelompok[$i]['skor'] += $hasil['skor'];

            if ($row) {
                DB::exec(
                    'UPDATE jawaban_siswa SET skor = ?, benar = ? WHERE id = ?',
                    [$hasil['skor'], $hasil['benar'] === null ? null : ($hasil['benar'] ? 1 : 0), $row['id']]
                );
            }
        }

        $adaUraian = false;
        $uraianBelum = 0;
        $uraianDinilai = 0;
        $lengkap = true;
        $objSum = 0.0;
        $objBobot = 0.0;
        $urSum = 0.0;
        $urBobot = 0.0;

        foreach ($kelompok as $g) {
            if ($g['maks'] <= 0) {
                continue; // jenis soal tanpa butir tidak memengaruhi nilai
            }
            if ($g['uraian']) {
                $adaUraian = true;
                $uraianBelum += $g['belum'];
                $uraianDinilai += $g['dinilai'];
                if ($g['belum'] > 0) {
                    $lengkap = false;
                    continue;
                }
                $nilai = round(min(100.0, max(0.0, $g['skor'] / $g['maks'] * 100)), 2);
                $urSum += $nilai * $g['bobot'];
                $urBobot += $g['bobot'];
                continue;
            }
            $skor = max(0.0, $g['skor']);
            $nilai = round(min(100.0, max(0.0, $skor / $g['maks'] * 100)), 2);
            $objSum += $nilai * $g['bobot'];
            $objBobot += $g['bobot'];
        }

        $nilaiPg = $objBobot > 0 ? round($objSum / $objBobot, 2) : 0.0;
        $nilaiUraian = ($adaUraian && $uraianBelum === 0 && $urBobot > 0)
            ? round($urSum / $urBobot, 2)
            : null;

        $bobotEfektif = $objBobot + $urBobot;
        $nilaiAkhir = null;
        if ($lengkap && $bobotEfektif > 0) {
            $nilaiAkhir = round(($objSum + $urSum) / $bobotEfektif, 2);
        }

        if (!$adaUraian) {
            $statusKoreksi = 'Selesai';
        } elseif ($uraianBelum === 0) {
            $statusKoreksi = 'Selesai';
        } elseif ($uraianDinilai > 0) {
            $statusKoreksi = 'Sebagian';
        } else {
            $statusKoreksi = 'Belum';
        }

        $status = $r['status'];
        if ($r['waktu_selesai'] !== null) {
            $status = $nilaiAkhir === null ? 'Menunggu Koreksi' : 'Selesai';
        }

        DB::exec(
            'UPDATE riwayat_ujian
             SET nilai_pg = ?, nilai_uraian = ?, nilai_akhir = ?, status_koreksi = ?, status = ?
             WHERE id = ?',
            [$nilaiPg, $nilaiUraian, $nilaiAkhir, $statusKoreksi, $status, $idRiwayat]
        );

        return [
            'nilai_pg' => $nilaiPg,
            'nilai_uraian' => $nilaiUraian,
            'nilai_akhir' => $nilaiAkhir,
            'status_koreksi' => $statusKoreksi,
            'status' => $status,
            'uraian_belum' => $uraianBelum,
        ];
    }

    public static function hitungUlangSoal(int $idSoal): int
    {
        $rows = DB::all('SELECT id FROM riwayat_ujian WHERE id_soal = ?', [$idSoal]);
        foreach ($rows as $row) {
            self::hitung((int) $row['id']);
        }
        return count($rows);
    }

    /** Nilai terbaik seorang siswa untuk satu ujian (untuk laporan). */
    public static function nilaiTerbaik(int $idSoal, int $idSiswa): ?array
    {
        return DB::one(
            "SELECT * FROM riwayat_ujian
             WHERE id_soal = ? AND id_siswa = ? AND waktu_selesai IS NOT NULL
             ORDER BY (nilai_akhir IS NULL) ASC, nilai_akhir DESC, waktu_selesai ASC
             LIMIT 1",
            [$idSoal, $idSiswa]
        );
    }
}
