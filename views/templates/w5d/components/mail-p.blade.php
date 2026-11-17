@props(['muted' => false])
<p class="{{ $muted ? 't-muted' : 't-body' }}" style="margin:0 0 14px;font-size:{{ $muted ? '13' : '15' }}px;line-height:1.7;color:{{ $muted ? '#64748b' : '#334155' }};">{{ $slot }}</p>
