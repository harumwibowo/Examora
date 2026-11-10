<?php
declare(strict_types=1);

/**
 * Pembaruan aplikasi langsung dari repository resmi.
 * Hanya superadmin, selalu lewat token CSRF, dan selalu mencatat audit log.
 */
final class UpdateController
{
    /** Berkas versi terbaru di cabang utama repository. */
    private const URL_VERSI = 'https://raw.githubusercontent.com/harumwibowo/Examora/main/VERSION';

    /** Cadangan bila berkas VERSION tidak ada di repository. */
    private const URL_RILIS = 'https://api.github.com/repos/harumwibowo/Examora/releases/latest';

    /** Paket ZIP isi repository (untuk instalasi hasil unduhan ZIP). */
    private const URL_ZIP = 'https://github.com/harumwibowo/Examora/archive/refs/heads/main.zip';

    /** Berkas yang tidak boleh ditimpa saat update. */
    private const LAYAK_TIMPA = ['config/config.php', 'install.lock'];

    /** Folder yang tidak boleh diubah saat update: data, unggahan, dan history git. */
    private const FOLDER_AMAN = ['storage', 'assets/uploads', '.git'];

    public function index(): void
    {
        Auth::requireSuperadmin();

        $lokal = self::versiLokal();
        $hasil = $_SESSION['update_hasil'] ?? null;

        View::render('update', [
            'title' => 'Update Aplikasi',
            'lokal' => $lokal,
            'mode' => $this->deteksiMode(),
            'hasil' => $hasil,
            'tersedia' => is_array($hasil)
                && ($hasil['remote'] ?? '') !== ''
                && version_compare((string) $hasil['remote'], $lokal, '>'),
        ]);
    }

    /** Cek versi terbaru ke repository, lalu tampilkan hasilnya. */
    public function cek(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();

        $lokal = self::versiLokal();
        $hasil = ['waktu' => now(), 'lokal' => $lokal, 'remote' => '', 'gagal' => ''];

        try {
            $hasil['remote'] = $this->versiRemote();
        } catch (Throwable $e) {
            $hasil['gagal'] = $e->getMessage();
        }

        $_SESSION['update_hasil'] = $hasil;
        audit('update_cek', null, null, [
            'lokal' => $lokal,
            'remote' => $hasil['remote'],
            'gagal' => $hasil['gagal'],
        ]);
        redirect('update');
    }

    /** Jalankan update dari repository. */
    public function jalan(): void
    {
        Auth::requireSuperadmin();
        Csrf::check();
        @set_time_limit(0);

        $lokal = self::versiLokal();

        try {
            $remote = $this->versiRemote();
            if (version_compare($remote, $lokal, '<=')) {
                throw new RuntimeException('Belum ada pembaruan. Versi terpasang sudah v' . $lokal . '.');
            }

            $mode = $this->deteksiMode();
            if ($mode === 'manual') {
                throw new RuntimeException(
                    'Server tidak menyediakan Git maupun dukungan ekstraksi ZIP. Perbarui manual: unduh paket '
                    . 'ZIP dari GitHub lalu timpa folder aplikasi, kecuali config/config.php, install.lock, '
                    . 'storage/, dan assets/uploads/.'
                );
            }

            $backup = $this->cadangkan();
            $detail = $mode === 'git' ? $this->perbaruiGit() : $this->perbaruiZip();

            $baru = self::versiLokal();
            if (version_compare($baru, $lokal, '<=')) {
                throw new RuntimeException(
                    'Berkas sudah disalin tetapi versi masih v' . $baru . '. Periksa izin tulis folder aplikasi. '
                    . 'Kode lama tersimpan di ' . $backup . ' untuk dikembalikan.'
                );
            }

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            unset($_SESSION['update_hasil']);

            audit('update_jalan', null, null, ['dari' => $lokal, 'ke' => $baru, 'mode' => $mode, 'backup' => $backup]);
            flash_success(
                'Aplikasi diperbarui dari v' . $lokal . ' ke v' . $baru . ' — ' . $detail
                . '. Kode lama disimpan di ' . $backup . ' bila perlu dikembalikan.'
            );
        } catch (Throwable $e) {
            audit('update_gagal', null, null, ['dari' => $lokal, 'pesan' => $e->getMessage()]);
            flash_error('Update gagal: ' . $e->getMessage());
        }

        redirect('update');
    }

    /* ------------------------------------------------------------------ */
    /* Informasi versi                                                      */
    /* ------------------------------------------------------------------ */

    public static function versiLokal(): string
    {
        $file = BASE_PATH . '/VERSION';
        if (is_file($file)) {
            $v = trim((string) file_get_contents($file));
            if (preg_match('/^\d+\.\d+(\.\d+)?$/', $v)) {
                return $v;
            }
        }

        return DB_UJIAN_VERSION;
    }

    private function versiRemote(): string
    {
        try {
            $isi = trim($this->unduhTeks(self::URL_VERSI));
            if (preg_match('/^\d+\.\d+(\.\d+)?$/', $isi)) {
                return $isi;
            }
        } catch (Throwable) {
            // Berkas VERSION belum ada di repository, lanjut ke sumber cadangan.
        }

        $j = $this->unduhTeks(self::URL_RILIS);
        $data = json_decode($j, true);
        $tag = (string) ($data['tag_name'] ?? '');
        if (preg_match('/v?(\d+\.\d+(?:\.\d+)?)/', $tag, $m)) {
            return $m[1];
        }

        throw new RuntimeException('Versi terbaru tidak ditemukan di repository GitHub.');
    }

    /** @return 'git'|'zip'|'manual' */
    private function deteksiMode(): string
    {
        if (is_dir(BASE_PATH . '/.git') && $this->gitTersedia()) {
            return 'git';
        }
        if (class_exists('ZipArchive') || function_exists('gzinflate')) {
            return 'zip';
        }

        return 'manual';
    }

    private function gitTersedia(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $out = [];
        $rc = 1;
        @exec('git --version 2>&1', $out, $rc);

        return $rc === 0 && stripos(implode(' ', $out), 'git version') !== false;
    }

    /* ------------------------------------------------------------------ */
    /* Penerapan update                                                     */
    /* ------------------------------------------------------------------ */

    /** @return string rincian hasil */
    private function perbaruiGit(): string
    {
        $out = [];
        $rc = 1;
        @exec('git pull --ff-only --no-edit origin main 2>&1', $out, $rc);
        $pesan = trim(implode(' ', $out));
        if ($rc !== 0) {
            throw new RuntimeException(
                'Git gagal menarik pembaruan: ' . ($pesan !== '' ? $pesan : 'kode keluar ' . $rc)
                . '. Bila repository memiliki perubahan lokal, perbaiki dulu dengan git status.'
            );
        }

        return 'diperbarui lewat git pull --ff-only';
    }

    /** @return string rincian hasil */
    private function perbaruiZip(): string
    {
        $zipFile = STORAGE_PATH . '/update-src.zip';
        $dirSrc = STORAGE_PATH . '/update-src';

        $this->unduhFile(self::URL_ZIP, $zipFile);
        $this->hapusDirektori($dirSrc);
        if (!is_dir($dirSrc) && !@mkdir($dirSrc, 0775, true)) {
            throw new RuntimeException('Folder kerja update tidak dapat dibuat: ' . $dirSrc);
        }

        $this->ekstrakZip($zipFile, $dirSrc);
        @unlink($zipFile);

        $akar = $dirSrc;
        $isi = array_values(array_diff(scandir($dirSrc) ?: [], ['.', '..']));
        if (count($isi) === 1 && is_dir($dirSrc . '/' . $isi[0])) {
            $akar = $dirSrc . '/' . $isi[0];
        }
        if (!is_file($akar . '/index.php') || !is_dir($akar . '/app')) {
            $this->hapusDirektori($dirSrc);
            throw new RuntimeException('Struktur paket update tidak dikenali.');
        }

        try {
            $jml = $this->salin($akar, '', self::LAYAK_TIMPA, self::FOLDER_AMAN, BASE_PATH);
        } catch (Throwable $e) {
            $this->hapusDirektori($dirSrc);
            throw $e;
        }
        $this->hapusDirektori($dirSrc);

        return $jml . ' berkas disalin dari paket GitHub';
    }

    /**
     * Ekstrak paket ZIP. Memakai ZipArchive bila tersedia, jika tidak memakai
     * parser ZIP sederhana + gzinflate sehingga update tetap jalan di hosting
     * yang tidak mengaktifkan ekstensi zip.
     */
    private function ekstrakZip(string $file, string $ke): void
    {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($file) !== true) {
                throw new RuntimeException('Paket update yang diunduh tidak dapat dibuka. Coba lagi beberapa saat.');
            }
            $zip->extractTo($ke);
            $zip->close();

            return;
        }

        $this->ekstrakZipManual($file, $ke);
    }

    private function ekstrakZipManual(string $file, string $ke): void
    {
        if (!function_exists('gzinflate')) {
            throw new RuntimeException('Ekstraksi ZIP tidak didukung server (ZipArchive dan gzinflate tidak ada).');
        }
        $data = @file_get_contents($file);
        if (!is_string($data) || strlen($data) < 22) {
            throw new RuntimeException('Paket update yang diunduh tidak valid.');
        }

        $eocd = strrpos($data, "PK\x05\x06");
        if ($eocd === false) {
            throw new RuntimeException('Struktur paket update tidak dikenali.');
        }
        $jumlah = unpack('v', substr($data, $eocd + 10, 2))[1];
        $offCd = unpack('V', substr($data, $eocd + 16, 4))[1];

        $i = (int) $offCd;
        $tanpa = 0;
        for ($n = 0; $n < $jumlah; $n++) {
            if (substr($data, $i, 4) !== "PK\x01\x02") {
                break;
            }
            $metode = unpack('v', substr($data, $i + 10, 2))[1];
            $cSize = unpack('V', substr($data, $i + 20, 4))[1];
            $off = unpack('V', substr($data, $i + 42, 4))[1];
            $namaLen = unpack('v', substr($data, $i + 28, 2))[1];
            $extraLen = unpack('v', substr($data, $i + 30, 2))[1];
            $komenLen = unpack('v', substr($data, $i + 32, 2))[1];
            $nama = str_replace('\\', '/', substr($data, $i + 46, $namaLen));
            $i += 46 + $namaLen + $extraLen + $komenLen;

            if ($nama === '' || str_ends_with($nama, '/') || str_starts_with($nama, '/')
                || preg_match('#(^|/)\.\.(/|$)#', $nama)) {
                continue;
            }

            if (substr($data, $off, 4) !== "PK\x03\x04") {
                continue;
            }
            $lNamaLen = unpack('v', substr($data, $off + 26, 2))[1];
            $lExtraLen = unpack('v', substr($data, $off + 28, 2))[1];
            $isi = substr($data, $off + 30 + $lNamaLen + $lExtraLen, $cSize);

            if ($metode === 8) {
                $isi = @gzinflate($isi);
            } elseif ($metode !== 0) {
                $tanpa++;
                continue;
            }
            if (!is_string($isi)) {
                throw new RuntimeException('Gagal dekompresi berkas dalam paket update: ' . $nama);
            }

            $tujuan = $ke . '/' . $nama;
            $dir = dirname($tujuan);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Folder tidak dapat dibuat saat ekstraksi: ' . dirname($nama));
            }
            if (@file_put_contents($tujuan, $isi) === false) {
                throw new RuntimeException('Gagal menulis hasil ekstraksi: ' . $nama);
            }
        }

        if ($jumlah > 0 && $i === (int) $offCd && $tanpa === 0) {
            throw new RuntimeException('Paket update tidak berisi berkas apa pun.');
        }
    }

    /**
     * Salin berkas dari $dir (dengan path relatif $rel) ke $ke.
     * File dalam $lewatFile dan folder dalam $lewatDir dilewati.
     *
     * @param list<string> $lewatFile
     * @param list<string> $lewatDir
     */
    private function salin(string $dir, string $rel, array $lewatFile, array $lewatDir, string $ke): int
    {
        $n = 0;
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $r = $rel === '' ? $item : $rel . '/' . $item;
            $from = $dir . '/' . $item;

            if (in_array($r, $lewatFile, true)) {
                continue;
            }
            if (is_dir($from)) {
                if (in_array($r, $lewatDir, true)) {
                    continue;
                }
                $tujuan = $ke . '/' . $r;
                if (!is_dir($tujuan) && !@mkdir($tujuan, 0775, true) && !is_dir($tujuan)) {
                    throw new RuntimeException('Folder tidak dapat dibuat: ' . $r);
                }
                $n += $this->salin($from, $r, $lewatFile, $lewatDir, $ke);
                continue;
            }

            $tujuan = $ke . '/' . $r;
            $dirTujuan = dirname($tujuan);
            if (!is_dir($dirTujuan) && !@mkdir($dirTujuan, 0775, true) && !is_dir($dirTujuan)) {
                throw new RuntimeException('Folder tidak dapat dibuat: ' . dirname($r));
            }
            if (!@copy($from, $tujuan)) {
                throw new RuntimeException(
                    'Berkas tidak dapat ditulis: ' . $r . '. Periksa izin tulis folder aplikasi.'
                );
            }
            $n++;
        }

        return $n;
    }

    /**
     * Salinan kode lama ke storage/ sebagai cadangan pemulihan.
     * config/config.php dan install.lock ikut disimpan supaya rollback lengkap.
     *
     * @return string path relatif di bawah storage/
     */
    private function cadangkan(): string
    {
        $rel = 'backup-kode-' . date('Ymd-His');
        $dest = STORAGE_PATH . '/' . $rel;
        if (!is_dir($dest) && !@mkdir($dest, 0775, true) && !is_dir($dest)) {
            throw new RuntimeException('Cadangan kode gagal dibuat: ' . $dest . ' tidak dapat ditulis.');
        }

        $this->salin(BASE_PATH, '', ['storage'], ['storage', '.git'], $dest);
        $this->batasiCadangan();

        return 'storage/' . $rel;
    }

    /** Simpan maksimal 3 cadangan kode terbaru. */
    private function batasiCadangan(): void
    {
        $dirs = glob(STORAGE_PATH . '/backup-kode-*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        $lebih = count($dirs) - 3;
        for ($i = 0; $i < $lebih; $i++) {
            $this->hapusDirektori($dirs[$i]);
        }
    }

    private function hapusDirektori(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->hapusDirektori($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /* ------------------------------------------------------------------ */
    /* Utilitas jaringan                                                    */
    /* ------------------------------------------------------------------ */

    private function unduhTeks(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => APP_NAME . '/' . DB_UJIAN_VERSION,
            ]);
            $isi = (string) curl_exec($ch);
            $err = curl_error($ch);
            $kode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($isi === '' || $kode >= 400) {
                throw new RuntimeException('Tidak dapat menghubungi GitHub: ' . ($err !== '' ? $err : 'HTTP ' . $kode));
            }

            return $isi;
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Ekstensi curl mati dan allow_url_fopen nonaktif; koneksi ke GitHub diblokir.');
        }
        $isi = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 20]]));
        if (!is_string($isi) || $isi === '') {
            $terakhir = error_get_last();
            throw new RuntimeException('Tidak dapat menghubungi GitHub: ' . ($terakhir['message'] ?? 'tanpa keterangan'));
        }

        return $isi;
    }

    private function unduhFile(string $url, string $tujuan): void
    {
        $data = $this->unduhTeks($url);
        if (strlen($data) < 100) {
            throw new RuntimeException('Paket update tidak valid (unduhan terlalu kecil).');
        }
        if (@file_put_contents($tujuan, $data, LOCK_EX) === false) {
            throw new RuntimeException('Hasil unduhan tidak dapat disimpan ke ' . $tujuan . '.');
        }
    }
}
