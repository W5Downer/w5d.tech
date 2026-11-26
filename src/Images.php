<?php
declare(strict_types=1);

namespace W5D;

/**
 * Owner image library: any uploaded picture (or every picture inside a ZIP) is re-encoded with GD into ONE uniform size
 * and the .webp format. Re-encoding is also the safety net: what is stored is always a fresh WebP made from decoded pixels,
 * never the uploaded bytes, so a polyglot or a script hidden in a "picture" cannot survive.
 *
 * Needs the PHP `gd` extension built with WebP (and `zip` for ZIP files). Both are checked by bin/preflight.php and the
 * System screen; when one is missing the tool says so instead of failing half way.
 */
final class Images
{
    public const DIR = '/htdocs/assets/_base/images/library';
    public const URL = 'images/library/';
    public const EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const MAX_BYTES = 10 * 1024 * 1024;      // one picture
    public const MAX_PIXELS = 16_000_000;           // decoded size guard (decompression bombs, memory)
    public const MAX_ZIP_FILES = 200;               // entries read from one ZIP
    public const MAX_ZIP_TOTAL = 150 * 1024 * 1024; // sum of the pictures inside one ZIP
    public const MAX_OUT = 300;                     // pictures produced by one request

    public static function gdOk(): bool { return extension_loaded('gd') && function_exists('imagewebp') && function_exists('imagecreatefromstring'); }
    public static function zipOk(): bool { return class_exists(\ZipArchive::class); }
    public static function dir(): string { return ROOT . self::DIR; }

    /** What the server will accept, for the screen. */
    public static function limits(): array
    {
        $b = static function (string $v): int {
            $n = (int)$v; $u = strtolower(substr(trim($v), -1));
            return match ($u) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
        };
        return ['upload' => $b((string)ini_get('upload_max_filesize')), 'post' => $b((string)ini_get('post_max_size')), 'files' => (int)ini_get('max_file_uploads'),
            'picture' => self::MAX_BYTES, 'pixels' => self::MAX_PIXELS, 'zip_files' => self::MAX_ZIP_FILES];
    }

    public static function slug(string $name): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $name)), PATHINFO_FILENAME);
        $base = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', (string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base)));
        $base = trim($base, '-');
        return $base === '' ? 'image' : substr($base, 0, 60);
    }

    public static function validFile(string $f): bool { return (bool)preg_match('/^[a-z0-9][a-z0-9-]{0,63}(?:-\d{1,3})?\.webp$/', $f); }

    /**
     * Decode `$bytes`, fit it to $w x $h and return [webp bytes|null, error|null, source width, source height].
     * $fit: cover (fill the frame, centre-crop) or contain (whole picture, transparent bars).
     */
    public static function convert(string $bytes, int $w, int $h, string $fit = 'cover', int $quality = 80): array
    {
        if (!self::gdOk()) return [null, 'The server has no GD/WebP support.', 0, 0];
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) return [null, 'Larger than ' . (self::MAX_BYTES >> 20) . ' MB or empty.', 0, 0];
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) return [null, 'Not a JPEG, PNG, GIF or WebP picture.', 0, 0];
        [$sw, $sh] = [(int)$info[0], (int)$info[1]];
        if ($sw < 1 || $sh < 1 || $sw * $sh > self::MAX_PIXELS) return [null, 'Too many pixels (' . $sw . 'x' . $sh . '; the limit is ' . number_format(self::MAX_PIXELS / 1e6, 0) . ' megapixels).', $sw, $sh];
        $src = @imagecreatefromstring($bytes);
        if (!$src) return [null, 'The picture could not be decoded.', $sw, $sh];
        // Phones store the rotation in EXIF: honour it so every output is upright.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data') && function_exists('imagerotate')) {
            try {
                $ex = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
                $deg = match ((int)($ex['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
                if ($deg !== 0 && ($rot = @imagerotate($src, $deg, 0))) { $src = $rot; [$sw, $sh] = [imagesx($src), imagesy($src)]; }
            } catch (\Throwable) {}
        }
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagefill($dst, 0, 0, (int)imagecolorallocatealpha($dst, 0, 0, 0, 127));
        if ($fit === 'contain') {
            $s = min($w / $sw, $h / $sh); $dw = max(1, (int)round($sw * $s)); $dh = max(1, (int)round($sh * $s));
            imagecopyresampled($dst, $src, intdiv($w - $dw, 2), intdiv($h - $dh, 2), 0, 0, $dw, $dh, $sw, $sh);
        } else {
            $s = max($w / $sw, $h / $sh); $cw = max(1, (int)round($w / $s)); $ch = max(1, (int)round($h / $s));
            imagecopyresampled($dst, $src, 0, 0, intdiv($sw - $cw, 2), intdiv($sh - $ch, 2), $w, $h, $cw, $ch);
        }
        ob_start(); $ok = imagewebp($dst, null, max(30, min(95, $quality))); $out = (string)ob_get_clean();
        unset($src, $dst);
        return $ok && $out !== '' ? [$out, null, $sw, $sh] : [null, 'WebP encoding failed.', $sw, $sh];
    }

    /** Store WebP bytes under a safe, unique name; returns the file name. */
    public static function save(string $name, string $webp): string
    {
        $dir = self::dir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $base = self::slug($name); $f = $base . '.webp'; $i = 2;
        while (is_file($dir . '/' . $f)) $f = $base . '-' . $i++ . '.webp';
        if (file_put_contents($dir . '/' . $f, $webp, LOCK_EX) === false) throw new \RuntimeException('Cannot write to ' . self::DIR);
        @chmod($dir . '/' . $f, 0644);
        return $f;
    }

    /** @return list<array{file:string,bytes:int,w:int,h:int,at:int}> newest first */
    public static function library(): array
    {
        $rows = [];
        foreach (glob(self::dir() . '/*.webp') ?: [] as $p) {
            $sz = @getimagesize($p);
            $rows[] = ['file' => basename($p), 'bytes' => (int)filesize($p), 'w' => (int)($sz[0] ?? 0), 'h' => (int)($sz[1] ?? 0), 'at' => (int)filemtime($p)];
        }
        usort($rows, fn($a, $b) => $b['at'] <=> $a['at'] ?: strcmp($a['file'], $b['file']));
        return $rows;
    }

    public static function delete(string $file): bool
    {
        return self::validFile($file) && is_file(self::dir() . '/' . $file) && @unlink(self::dir() . '/' . $file);
    }

    /**
     * Pictures inside a ZIP, read straight into memory (nothing is extracted to disk, so path tricks such as ../ cannot matter).
     * @return array{0:list<array{name:string,bytes:string}>,1:list<array{name:string,error:string}>} [pictures, skipped]
     */
    public static function zipPictures(string $path): array
    {
        $pics = []; $skip = [];
        if (!self::zipOk()) return [[], [['name' => basename($path), 'error' => 'The server has no ZIP support.']]];
        $z = new \ZipArchive();
        if ($z->open($path, \ZipArchive::RDONLY) !== true) return [[], [['name' => 'ZIP', 'error' => 'Not a readable ZIP file.']]];
        $total = 0;
        $n = min($z->numFiles, 5000);
        for ($i = 0; $i < $n; $i++) {
            $st = $z->statIndex($i); if (!$st) continue;
            $name = (string)$st['name'];
            if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/') || str_starts_with(basename($name), '.')) continue;
            if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXT, true)) { $skip[] = ['name' => basename($name), 'error' => 'Not a picture type we accept (JPEG, PNG, GIF, WebP).']; continue; }
            if (count($pics) >= self::MAX_ZIP_FILES) { $skip[] = ['name' => basename($name), 'error' => 'Skipped: more than ' . self::MAX_ZIP_FILES . ' pictures in the ZIP.']; continue; }
            if ((int)$st['size'] > self::MAX_BYTES) { $skip[] = ['name' => basename($name), 'error' => 'Larger than ' . (self::MAX_BYTES >> 20) . ' MB.']; continue; }
            if ($total + (int)$st['size'] > self::MAX_ZIP_TOTAL) { $skip[] = ['name' => basename($name), 'error' => 'Skipped: the ZIP holds more than ' . (self::MAX_ZIP_TOTAL >> 20) . ' MB of pictures.']; continue; }
            $fp = $z->getStream($name); if (!$fp) { $skip[] = ['name' => basename($name), 'error' => 'Could not be read (encrypted or damaged).']; continue; }
            $buf = (string)stream_get_contents($fp, self::MAX_BYTES + 1); fclose($fp);   // never trust the declared size
            if (strlen($buf) > self::MAX_BYTES) { $skip[] = ['name' => basename($name), 'error' => 'Larger than ' . (self::MAX_BYTES >> 20) . ' MB.']; continue; }
            $total += strlen($buf);
            $pics[] = ['name' => basename(str_replace('\\', '/', $name)), 'bytes' => $buf];
        }
        $z->close();
        return [$pics, $skip];
    }
}
