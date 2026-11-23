<!DOCTYPE html>
<html lang="en" dir="ltr" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Offline — W5D</title>
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#070711">
  <script src="{{ asset('js/theme-init.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
  <link rel="stylesheet" href="{{ asset('css/attention.css') }}">
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-ink-950 dark:text-slate-200 antialiased">
  {{-- One static page for everybody: it is cached by the service worker, so it carries all three languages and no personal data. --}}
  <main id="main" class="min-h-screen flex items-center justify-center p-4" data-offline-page>
    <div class="w-full max-w-lg rounded-2xl border border-amber-500/40 bg-amber-500/10 p-6 sm:p-8 space-y-6">
      <div class="flex items-center gap-3"><img src="{{ asset('images/logo.svg') }}" alt="" width="40" height="19" class="h-8 w-auto shrink-0"><h1 class="font-display text-2xl font-bold">W5D · Offline / Hors ligne / غير متصل</h1></div>
      <section lang="en" dir="ltr"><h2 class="font-semibold">You are offline</h2><p class="mt-1 text-sm">The portal needs a connection: pages and forms are never stored on this device, so nothing private can be read from here. Nothing you typed was sent. Reconnect and the portal reopens by itself.</p></section>
      <section lang="fr" dir="ltr"><h2 class="font-semibold">Vous êtes hors ligne</h2><p class="mt-1 text-sm">Le portail exige une connexion : pages et formulaires ne sont jamais conservés sur cet appareil, rien de privé ne peut donc être lu d'ici. Rien de ce que vous avez saisi n'a été envoyé. Reconnectez-vous et le portail se rouvre tout seul.</p></section>
      <section lang="ar" dir="rtl"><h2 class="font-semibold">أنت غير متصل</h2><p class="mt-1 text-sm">تحتاج البوابة إلى اتصال: لا تُخزَّن الصفحات والنماذج على هذا الجهاز ولذلك لا يمكن قراءة أي شيء خاص من هنا. لم يُرسل شيء مما كتبته. أعد الاتصال وستُفتح البوابة تلقائيا.</p></section>
      <div class="flex flex-wrap items-center gap-3">
        <button type="button" class="btn-focus" data-offline-retry>Try again · Réessayer · أعد المحاولة</button>
        <span class="text-xs text-slate-500" data-offline-status role="status" aria-live="polite"></span>
      </div>
    </div>
  </main>
  <script src="{{ asset('js/portal.js') }}" defer></script>
</body>
</html>
