<?php
declare(strict_types=1);

/**
 * Brand assets — favicon aplikasi.
 * Memakai logo sekolah bila sudah diunggah, diubah ukuran menjadi ikon
 * persegi kecil dan di-cache di storage/cache agar tidak berat.
 */
final class BrandController
{
    public function favicon(): void
    {
        $logo = trim(setting('logo_sekolah'));
        $source = '';
        if ($logo !== '') {
            $candidate = BASE_PATH . '/assets/uploads/' . basename($logo);
            if (is_file($candidate)) {
                $source = $candidate;
            }
        }

        if ($source === '') {
            $this->serve(BASE_PATH . '/assets/img/favicon.svg', 'image/svg+xml');
            return;
        }

        if (!extension_loaded('gd')) {
            $info = @getimagesize($source);
            $mime = is_array($info) ? (string) ($info['mime'] ?? '') : 'image/png';
            $this->serve($source, $mime !== '' ? $mime : 'image/png');
            return;
        }

        $cacheDir = STORAGE_PATH . '/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $cacheFile = $cacheDir . '/favicon-' . md5_file($source) . '.png';

        if (!is_file($cacheFile)) {
            $png = $this->squarePng($source, 64);
            if ($png === null) {
                $this->serve($source, 'image/png');
                return;
            }
            if (@file_put_contents($cacheFile, $png) === false) {
                header('Content-Type: image/png');
                header('Content-Length: ' . strlen($png));
                echo $png;
                exit;
            }
        }

        $this->serve($cacheFile, 'image/png');
    }

    /**
     * Skala gambar ke dalam kanvas persegi transparan dengan padding.
     */
    private function squarePng(string $path, int $size): ?string
    {
        $info = @getimagesize($path);
        if (!is_array($info)) {
            return null;
        }
        $mime = (string) ($info['mime'] ?? '');
        $src = match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
        if (!$src instanceof GdImage) {
            return null;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) {
            imagedestroy($src);
            return null;
        }

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
        imagealphablending($canvas, true);

        $pad = max(1, (int) round($size * 0.04));
        $box = $size - ($pad * 2);
        $scale = min($box / $sw, $box / $sh);
        $dw = max(1, (int) round($sw * $scale));
        $dh = max(1, (int) round($sh * $scale));
        $dx = (int) round(($size - $dw) / 2);
        $dy = (int) round(($size - $dh) / 2);

        imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);

        ob_start();
        imagepng($canvas, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);
        return $png !== '' ? $png : null;
    }

    private function serve(string $file, string $mime): void
    {
        if (!is_file($file)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Favicon tidak ditemukan.';
            exit;
        }
        $mtime = (int) filemtime($file);
        $etag = '"' . md5($file . $mtime) . '"';
        $ifNone = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($ifNone !== '' && $ifNone === $etag) {
            http_response_code(304);
            header('ETag: ' . $etag);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
