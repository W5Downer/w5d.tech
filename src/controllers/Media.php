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
        if ($len > 0 && $lim['post'] > 0 && $len > $lim['post'] && !$_POST) { Admin::flash('error', tr3('That upload is bigger than the server accepts in one go (', 'Cet envoi dépasse ce que le serveur accepte en une fois (', 'هذا الرفع أكبر مما يقبله الخادم دفعة واحدة (') . self::mb($lim['post']) . tr3('). Send fewer pictures at a time, or a smaller ZIP.', '). Envoyez moins d’images à la fois, ou un ZIP plus petit.', '). أرسل صورا أقل في كل مرة أو ملف ZIP أصغر.')); return Response::redirect(self::back()); }
        if (!Csrf::check($q)) { Admin::flash('error', __('msg.csrf')); return Response::redirect(self::back()); }
        if (!Images::gdOk()) { Admin::flash('error', tr3('This server cannot make WebP pictures: the PHP "gd" extension with WebP support is missing. Ask the host to enable it (see System).', 'Ce serveur ne peut pas créer d’images WebP : l’extension PHP « gd » avec prise en charge WebP est absente. Demandez à l’hébergeur de l’activer (voir Système).', 'لا يستطيع هذا الخادم إنشاء صور WebP: إضافة PHP «gd» مع دعم WebP غير موجودة. اطلب من المضيف تفعيلها (انظر النظام).')); return Response::redirect(self::back()); }

        $w = max(64, min(4096, (int)($q->post['w'] ?? 1280))); $h = max(64, min(4096, (int)($q->post['h'] ?? 720)));
        $fit = ($q->post['fit'] ?? 'cover') === 'contain' ? 'contain' : 'cover';
        $quality = max(40, min(95, (int)($q->post['quality'] ?? 80)));

        $files = self::flatten($_FILES['files'] ?? null);
        if (!$files) { Admin::flash('error', tr3('Choose at least one picture or ZIP file.', 'Choisissez au moins une image ou un fichier ZIP.', 'اختر صورة واحدة على الأقل أو ملف ZIP.')); return Response::redirect(self::back()); }

        $inputs = []; $skipped = [];
        foreach ($files as $f) {
            $name = (string)$f['name'];
            if ($f['error'] !== UPLOAD_ERR_OK) { $skipped[] = ['name' => $name, 'error' => self::uploadError((int)$f['error'])]; continue; }
            if (!is_uploaded_file((string)$f['tmp_name'])) { $skipped[] = ['name' => $name, 'error' => tr3('Not a regular upload.', 'Envoi non valide.', 'رفع غير صالح.')]; continue; }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === 'zip') {
                [$pics, $sk] = Images::zipPictures((string)$f['tmp_name']);
                array_push($inputs, ...$pics); array_push($skipped, ...$sk);
            } elseif (in_array($ext, Images::EXT, true)) {
                $inputs[] = ['name' => $name, 'bytes' => (string)file_get_contents((string)$f['tmp_name'], false, null, 0, Images::MAX_BYTES + 1)];
            } else {
                $skipped[] = ['name' => $name, 'error' => tr3('Only JPEG, PNG, GIF, WebP pictures or a ZIP of them.', 'Uniquement des images JPEG, PNG, GIF, WebP ou un ZIP de celles-ci.', 'صور JPEG وPNG وGIF وWebP أو ملف ZIP منها فقط.')];
            }
        }

        $made = [];
        foreach ($inputs as $in) {
            if (count($made) >= Images::MAX_OUT) { $skipped[] = ['name' => $in['name'], 'error' => tr3('Skipped: at most ', 'Ignorée : au maximum ', 'تم التخطي: الحد الأقصى ') . Images::MAX_OUT . tr3(' pictures per upload.', ' images par envoi.', ' صورة في كل رفع.')]; continue; }
            [$webp, $err, $sw, $sh] = Images::convert($in['bytes'], $w, $h, $fit, $quality);
            if ($webp === null) { $skipped[] = ['name' => $in['name'], 'error' => (string)$err]; continue; }
            try { $file = Images::save($in['name'], $webp); }
            catch (\Throwable $e) { $skipped[] = ['name' => $in['name'], 'error' => tr3('Could not be saved (storage not writable).', 'Enregistrement impossible (stockage non inscriptible).', 'تعذر الحفظ (التخزين غير قابل للكتابة).')]; continue; }
            $made[] = ['name' => $in['name'], 'file' => $file, 'from' => $sw . 'x' . $sh, 'from_kb' => (int)round(strlen($in['bytes']) / 1024), 'to_kb' => (int)round(strlen($webp) / 1024)];
        }

        if ($made) Admin::audit('images.upload', (string)count($made), count($made) . ' picture(s) to ' . $w . 'x' . $h . ' webp (' . $fit . ', q' . $quality . '); ' . count($skipped) . ' skipped');
        $_SESSION['_img_report'] = ['made' => $made, 'skipped' => $skipped, 'w' => $w, 'h' => $h];
        Admin::flash($made ? 'ok' : 'error', $made ? count($made) . tr3(' picture(s) saved as ', ' image(s) enregistrée(s) en ', ' صورة حُفظت بقياس ') . $w . '×' . $h . ' WebP.' . ($skipped ? ' ' . count($skipped) . tr3(' skipped (see below).', ' ignorée(s) (voir ci-dessous).', ' تم تخطيها (انظر أدناه).') : '') : tr3('Nothing was saved (see below).', 'Rien n’a été enregistré (voir ci-dessous).', 'لم يُحفظ شيء (انظر أدناه).'));
        return Response::redirect(self::back());
    }

    /** POST /images/delete  (file) */
    public static function delete(Request $q): Response
    {
        if ($r = Auth::require('media')) return $r;
        if (!Csrf::check($q)) { Admin::flash('error', __('msg.csrf')); return Response::redirect(self::back()); }
        $f = (string)($q->post['file'] ?? '');
        if (Images::delete($f)) { Admin::audit('images.delete', $f); Admin::flash('ok', $f . tr3(' deleted. Pages that still point to it will show a broken picture.', ' supprimée. Les pages qui y renvoient encore afficheront une image cassée.', ' حُذفت. الصفحات التي ما زالت تشير إليها ستعرض صورة معطوبة.')); }
        else Admin::flash('error', tr3('No such picture.', 'Image introuvable.', 'الصورة غير موجودة.'));
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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => tr3('Larger than the server allows per file (', 'Plus grand que ce que le serveur autorise par fichier (', 'أكبر مما يسمح به الخادم لكل ملف (') . self::mb(Images::limits()['upload']) . ').',
            UPLOAD_ERR_PARTIAL => tr3('Only partly received: try again.', 'Reçu partiellement : réessayez.', 'وصل جزء منه فقط: حاول مرة أخرى.'),
            default => tr3('Upload failed (code ', 'Échec de l’envoi (code ', 'فشل الرفع (الرمز ') . $e . ').',
        };
    }

    private static function mb(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB'; }
}
