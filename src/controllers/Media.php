<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Auth, Csrf, Images, Request, Response};

/**
 * Owner image tool (menu "Image library"): drop pictures or a ZIP of pictures, every one is re-encoded to the same
 * pixel size as .webp. Owner only (perm "media"). Nothing the visitor controls ever reaches a file name or a path.
 */
final class Media
{
    private static function back(): string { return admin_url('images/'); }

    public static function view(): array
    {
        $rep = $_SESSION['_img_report'] ?? null; unset($_SESSION['_img_report']);
        return ['kind' => 'images', 'rows' => Images::library(), 'gd' => Images::gdOk(), 'zip' => Images::zipOk(), 'limits' => Images::limits(), 'report' => $rep, 'can' => Auth::can('media')];
    }

    /** POST /images/upload  (multipart: files[], w, h, fit, quality) */
    public static function upload(Request $q): Response
    {
        if ($r = Auth::require('media')) return $r;
        // When the body is bigger than post_max_size PHP drops $_POST and $_FILES: say that, not "bad token".
        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0); $lim = Images::limits();
        if ($len > 0 && $lim['post'] > 0 && $len > $lim['post'] && !$_POST) { Admin::flash('error', 'That upload is bigger than the server accepts in one go (' . self::mb($lim['post']) . '). Send fewer pictures at a time, or a smaller ZIP.'); return Response::redirect(self::back()); }
        if (!Csrf::check($q)) { Admin::flash('error', __('msg.csrf')); return Response::redirect(self::back()); }
        if (!Images::gdOk()) { Admin::flash('error', 'This server cannot make WebP pictures: the PHP "gd" extension with WebP support is missing. Ask the host to enable it (see System).'); return Response::redirect(self::back()); }

        $w = max(64, min(4096, (int)($q->post['w'] ?? 1280))); $h = max(64, min(4096, (int)($q->post['h'] ?? 720)));
        $fit = ($q->post['fit'] ?? 'cover') === 'contain' ? 'contain' : 'cover';
        $quality = max(40, min(95, (int)($q->post['quality'] ?? 80)));

        $files = self::flatten($_FILES['files'] ?? null);
        if (!$files) { Admin::flash('error', 'Choose at least one picture or ZIP file.'); return Response::redirect(self::back()); }

        $inputs = []; $skipped = [];
        foreach ($files as $f) {
            $name = (string)$f['name'];
            if ($f['error'] !== UPLOAD_ERR_OK) { $skipped[] = ['name' => $name, 'error' => self::uploadError((int)$f['error'])]; continue; }
            if (!is_uploaded_file((string)$f['tmp_name'])) { $skipped[] = ['name' => $name, 'error' => 'Not a regular upload.']; continue; }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === 'zip') {
                [$pics, $sk] = Images::zipPictures((string)$f['tmp_name']);
                array_push($inputs, ...$pics); array_push($skipped, ...$sk);
            } elseif (in_array($ext, Images::EXT, true)) {
                $inputs[] = ['name' => $name, 'bytes' => (string)file_get_contents((string)$f['tmp_name'], false, null, 0, Images::MAX_BYTES + 1)];
            } else {
                $skipped[] = ['name' => $name, 'error' => 'Only JPEG, PNG, GIF, WebP pictures or a ZIP of them.'];
            }
        }

        $made = [];
        foreach ($inputs as $in) {
            if (count($made) >= Images::MAX_OUT) { $skipped[] = ['name' => $in['name'], 'error' => 'Skipped: at most ' . Images::MAX_OUT . ' pictures per upload.']; continue; }
            [$webp, $err, $sw, $sh] = Images::convert($in['bytes'], $w, $h, $fit, $quality);
            if ($webp === null) { $skipped[] = ['name' => $in['name'], 'error' => (string)$err]; continue; }
            try { $file = Images::save($in['name'], $webp); }
            catch (\Throwable $e) { $skipped[] = ['name' => $in['name'], 'error' => 'Could not be saved (storage not writable).']; continue; }
            $made[] = ['name' => $in['name'], 'file' => $file, 'from' => $sw . 'x' . $sh, 'from_kb' => (int)round(strlen($in['bytes']) / 1024), 'to_kb' => (int)round(strlen($webp) / 1024)];
        }

        if ($made) Admin::audit('images.upload', (string)count($made), count($made) . ' picture(s) to ' . $w . 'x' . $h . ' webp (' . $fit . ', q' . $quality . '); ' . count($skipped) . ' skipped');
        $_SESSION['_img_report'] = ['made' => $made, 'skipped' => $skipped, 'w' => $w, 'h' => $h];
        Admin::flash($made ? 'ok' : 'error', $made ? count($made) . ' picture(s) saved as ' . $w . '×' . $h . ' WebP.' . ($skipped ? ' ' . count($skipped) . ' skipped (see below).' : '') : 'Nothing was saved (see below).');
        return Response::redirect(self::back());
    }

    /** POST /images/delete  (file) */
    public static function delete(Request $q): Response
    {
        if ($r = Auth::require('media')) return $r;
        if (!Csrf::check($q)) { Admin::flash('error', __('msg.csrf')); return Response::redirect(self::back()); }
        $f = (string)($q->post['file'] ?? '');
        if (Images::delete($f)) { Admin::audit('images.delete', $f); Admin::flash('ok', $f . ' deleted. Pages that still point to it will show a broken picture.'); }
        else Admin::flash('error', 'No such picture.');
        return Response::redirect(self::back());
    }

    /** $_FILES['files'] can be a single entry or the PHP "array of fields" shape. */
    private static function flatten(mixed $f): array
    {
        if (!is_array($f) || !isset($f['name'])) return [];
        if (!is_array($f['name'])) return $f['name'] === '' && ($f['error'] ?? 4) === UPLOAD_ERR_NO_FILE ? [] : [$f];
        $out = [];
        foreach ($f['name'] as $i => $n) {
            if ($n === '' && ($f['error'][$i] ?? 4) === UPLOAD_ERR_NO_FILE) continue;
            $out[] = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i] ?? '', 'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $f['size'][$i] ?? 0];
        }
        return $out;
    }

    private static function uploadError(int $e): string
    {
        return match ($e) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Larger than the server allows per file (' . self::mb(Images::limits()['upload']) . ').',
            UPLOAD_ERR_PARTIAL => 'Only partly received: try again.',
            default => 'Upload failed (code ' . $e . ').',
        };
    }

    private static function mb(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB'; }
}
