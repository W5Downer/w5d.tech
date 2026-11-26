@php
  $L = $v['limits'];
  $mb = fn($b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB';
  $act = admin_url('images/');
@endphp
@if(!$v['gd'])
  <p class="mb-5 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="alert">{{ icon('triangle-alert', 'w-4 h-4 inline') }} {{ tr3('This server has no GD extension with WebP support, so pictures cannot be converted. Ask the host to enable the PHP "gd" extension (with WebP), then reload this page.', 'Ce serveur n’a pas l’extension GD avec WebP : les images ne peuvent pas être converties. Demandez à l’hébergeur d’activer l’extension PHP « gd » (avec WebP), puis rechargez la page.', 'لا يملك هذا الخادم امتداد GD مع دعم WebP، فلا يمكن تحويل الصور. اطلب من المستضيف تفعيل امتداد PHP «gd» (مع WebP) ثم أعد تحميل الصفحة.') }}</p>
@endif
@if(!$v['zip'])
  <p class="mb-5 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">{{ tr3('ZIP files are not available on this server (PHP "zip" extension missing). Pictures can still be dropped one by one.', 'Les fichiers ZIP ne sont pas disponibles sur ce serveur (extension PHP « zip » absente). Vous pouvez toujours déposer les images une par une.', 'ملفات ZIP غير متاحة على هذا الخادم (امتداد PHP «zip» غير مثبت). ما زال بإمكانك إسقاط الصور واحدة واحدة.') }}</p>
@endif

@if($v['can'])
<form method="post" action="{{ $act . 'upload' }}" enctype="multipart/form-data" class="mb-8 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 sm:p-5 grid gap-4" data-img-form novalidate>
  {!! csrf_field() !!}
  <label for="img-files" class="img-drop" data-dropzone tabindex="0">
    {{ icon('upload', 'w-8 h-8') }}
    <span class="font-semibold">{{ tr3('Drop pictures or a ZIP here', 'Déposez des images ou un ZIP ici', 'أسقط الصور أو ملف ZIP هنا') }}</span>
    <span class="text-xs text-slate-600 dark:text-slate-400">{{ tr3('or click to choose. JPEG, PNG, GIF, WebP' . ($v['zip'] ? ' or ZIP' : ''), 'ou cliquez pour choisir. JPEG, PNG, GIF, WebP' . ($v['zip'] ? ' ou ZIP' : ''), 'أو انقر للاختيار. JPEG وPNG وGIF وWebP' . ($v['zip'] ? ' أو ZIP' : '')) }}</span>
    <span class="text-xs tabular-nums" data-drop-list aria-live="polite"></span>
    <input id="img-files" class="sr-only" type="file" name="files[]" multiple accept="image/jpeg,image/png,image/gif,image/webp{{ $v['zip'] ? ',.zip,application/zip' : '' }}" data-drop-input>
  </label>
  <div class="grid gap-3 sm:grid-cols-4">
    <div><label class="block text-xs mb-1" for="img-w">{{ tr3('Width (px)', 'Largeur (px)', 'العرض (بكسل)') }}</label><input id="img-w" class="field" name="w" type="number" inputmode="numeric" min="64" max="4096" value="1280" data-size="w"></div>
    <div><label class="block text-xs mb-1" for="img-h">{{ tr3('Height (px)', 'Hauteur (px)', 'الارتفاع (بكسل)') }}</label><input id="img-h" class="field" name="h" type="number" inputmode="numeric" min="64" max="4096" value="720" data-size="h"></div>
    <div><label class="block text-xs mb-1" for="img-fit">{{ tr3('Fit', 'Cadrage', 'الملاءمة') }}</label><select id="img-fit" class="field" name="fit"><option value="cover">{{ tr3('Fill the frame (centre crop)', 'Remplir le cadre (recadrage centré)', 'ملء الإطار (قص مركزي)') }}</option><option value="contain">{{ tr3('Whole picture (transparent bars)', 'Image entière (bandes transparentes)', 'الصورة كاملة (أشرطة شفافة)') }}</option></select></div>
    <div><label class="block text-xs mb-1" for="img-q">{{ tr3('Quality', 'Qualité', 'الجودة') }}</label><select id="img-q" class="field" name="quality"><option value="70">70</option><option value="80" selected>80</option><option value="90">90</option></select></div>
  </div>
  <div class="flex flex-wrap gap-2 items-center text-xs">
    <span class="text-slate-600 dark:text-slate-400">{{ tr3('Presets', 'Préréglages', 'إعدادات جاهزة') }}:</span>
    <button type="button" class="btn-ghost rounded-lg px-3 py-1.5" data-preset="1280x720">16:9 · 1280×720</button>
    <button type="button" class="btn-ghost rounded-lg px-3 py-1.5" data-preset="640x360">16:9 · 640×360</button>
    <button type="button" class="btn-ghost rounded-lg px-3 py-1.5" data-preset="1200x630">1200×630</button>
    <button type="button" class="btn-ghost rounded-lg px-3 py-1.5" data-preset="800x800">1:1 · 800×800</button>
  </div>
  <div><button class="btn-peak" type="submit">{{ icon('image', 'w-4 h-4') }} {{ tr3('Resize and convert to WebP', 'Redimensionner et convertir en WebP', 'تغيير الحجم والتحويل إلى WebP') }}</button>
    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">{{ tr3('Every picture gets exactly the same size and is re-encoded as .webp; the original is not kept. Limits on this server: ' . $mb($L['picture']) . ' per picture, ' . $mb($L['upload']) . ' per file, ' . $mb($L['post']) . ' per upload, up to ' . $L['files'] . ' files at a time' . ($v['zip'] ? ', up to ' . $L['zip_files'] . ' pictures per ZIP.' : '.'), 'Chaque image reçoit exactement la même taille et est ré-encodée en .webp ; l’original n’est pas conservé. Limites de ce serveur : ' . $mb($L['picture']) . ' par image, ' . $mb($L['upload']) . ' par fichier, ' . $mb($L['post']) . ' par envoi, jusqu’à ' . $L['files'] . ' fichiers à la fois' . ($v['zip'] ? ', jusqu’à ' . $L['zip_files'] . ' images par ZIP.' : '.'), 'تحصل كل صورة على الحجم نفسه تماما وتُحفظ بصيغة .webp؛ لا يُحتفظ بالأصل. حدود هذا الخادم: ' . $mb($L['picture']) . ' للصورة، ' . $mb($L['upload']) . ' للملف، ' . $mb($L['post']) . ' للرفعة، حتى ' . $L['files'] . ' ملفا في المرة' . ($v['zip'] ? '، وحتى ' . $L['zip_files'] . ' صورة في كل ZIP.' : '.')) }}</p></div>
</form>
@endif

@if(!empty($v['report']))
  @php $rep = $v['report']; @endphp
  <section class="mb-8 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 sm:p-5" aria-label="{{ tr3('Result of the last upload', 'Résultat du dernier envoi', 'نتيجة آخر رفع') }}">
    <h2 class="font-semibold mb-3">{{ tr3('Last upload', 'Dernier envoi', 'آخر رفع') }}: {{ count($rep['made']) }} {{ tr3('saved', 'enregistrée(s)', 'محفوظة') }}, {{ count($rep['skipped']) }} {{ tr3('skipped', 'ignorée(s)', 'متجاهلة') }}</h2>
    @if($rep['made'])<ul class="text-sm space-y-1 mb-3">@foreach($rep['made'] as $m)<li class="tabular-nums"><span class="font-mono text-xs">{{ $m['file'] }}</span> <span class="text-slate-600 dark:text-slate-400">← {{ $m['name'] }} · {{ $m['from'] }} · {{ $m['from_kb'] }} KB → {{ $m['to_kb'] }} KB</span></li>@endforeach</ul>@endif
    @if($rep['skipped'])<ul class="text-sm space-y-1 text-red-700 dark:text-red-300">@foreach($rep['skipped'] as $m)<li><span class="font-mono text-xs">{{ $m['name'] }}</span>: {{ $m['error'] }}</li>@endforeach</ul>@endif
  </section>
@endif

@if(empty($v['rows']))
  <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ tr3('No picture in the library yet.', 'Aucune image dans la bibliothèque pour l’instant.', 'لا توجد صور في المكتبة بعد.') }}</p>
@else
  <ul class="grid gap-4 grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
  @foreach($v['rows'] as $r)
    @php $u = asset(\W5D\Images::URL . $r['file']); @endphp
    <li class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 overflow-hidden flex flex-col">
      <img src="{{ $u }}" width="{{ $r['w'] }}" height="{{ $r['h'] }}" alt="" loading="lazy" decoding="async" class="w-full aspect-[16/9] object-contain bg-slate-100 dark:bg-white/5">
      <div class="p-3 text-xs flex flex-col gap-2">
        <p class="font-mono break-all">{{ $r['file'] }}</p>
        <p class="tabular-nums text-slate-600 dark:text-slate-400">{{ $r['w'] }}×{{ $r['h'] }} · {{ round($r['bytes'] / 1024) }} KB</p>
        <p><input class="field text-xs" readonly value="/assets/_base/{{ \W5D\Images::URL . $r['file'] }}" aria-label="{{ tr3('Address of', 'Adresse de', 'عنوان') }} {{ $r['file'] }}" data-select-all></p>
        @if($v['can'])
        <form method="post" action="{{ $act . 'delete' }}" data-confirm="{{ tr3('Delete this picture? Pages that use it will show a broken image.', 'Supprimer cette image ? Les pages qui l’utilisent afficheront une image cassée.', 'حذف هذه الصورة؟ ستعرض الصفحات التي تستخدمها صورة مكسورة.') }}">{!! csrf_field() !!}<input type="hidden" name="file" value="{{ $r['file'] }}"><button class="btn-ghost rounded-lg px-3 py-1.5 text-xs" type="submit">{{ icon('trash-2', 'w-3.5 h-3.5 inline') }} {{ tr3('Delete', 'Supprimer', 'حذف') }}</button></form>
        @endif
      </div>
    </li>
  @endforeach
  </ul>
@endif
