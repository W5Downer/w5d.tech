<x-section :d="$d">
  <div class="prose-w5 max-w-3xl {{ !empty($d['wide']) ? 'max-w-5xl' : '' }}">
    @foreach(($d['paragraphs'] ?? []) as $p)<p>{{ rich($p) }}</p>@endforeach
    @if(!empty($d['list']))<ul>@foreach($d['list'] as $li)<li>{{ rich($li) }}</li>@endforeach</ul>@endif
  </div>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>
