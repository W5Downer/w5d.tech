@php
  $errors = $errors ?? ($_SESSION['_errors'] ?? []);
  $regs = \W5D\Content::file('countries/tn.json')['regions'] ?? [];
@endphp
<x-section :d="$d">
  <form method="post" action="{{ url($d['action']) }}" class="max-w-3xl rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-5 sm:p-8" novalidate>
    {!! csrf_field() !!}
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
    @if(!empty($_SESSION['_flash_error']))<p class="mb-4 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ __('msg.error') }}</p>@endif
    <div class="grid gap-4 sm:grid-cols-2">
      @foreach($d['fields'] as $f)
        @php
          $opts = $f['options'] ?? [];
          if (($f['name'] ?? '') === 'region') { $opts = ['' => ['en' => '—']]; foreach ($regs as $r) $opts[$r['slug']] = $r['name']; }
          $val = (string)($_GET[$f['name']] ?? '');
        @endphp
        <div class="{{ ($f['span'] ?? 1) == 2 ? 'sm:col-span-2' : '' }}">
          <x-form-field :name="$f['name']" :label="$f['label']" :type="$f['type'] ?? 'text'" :required="!empty($f['required'])" :options="$opts" :hint="$f['hint'] ?? ''" :errors="$errors" :rows="$f['rows'] ?? 4" :value="$val" />
        </div>
      @endforeach
    </div>
    <div class="mt-6 flex flex-wrap items-center gap-4">
      <button type="submit" class="btn-peak">{{ t($d['submit']) }}</button>
      <a class="btn-link text-sm" href="{{ url('/trust/statement/') }}">{{ __('cta.read_limits') }}</a>
    </div>
  </form>
</x-section>
