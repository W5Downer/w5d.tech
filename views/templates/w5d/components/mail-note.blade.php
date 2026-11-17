@props(['tone' => 'info'])
@php $c = ['info' => ['#eef2ff', '#c7d2fe', '#3730a3'], 'warn' => ['#fffbeb', '#fde68a', '#92400e'], 'ok' => ['#ecfdf5', '#a7f3d0', '#065f46'], 'bad' => ['#fef2f2', '#fecaca', '#991b1b']][$tone] ?? ['#eef2ff', '#c7d2fe', '#3730a3']; @endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 16px;"><tr><td style="background:{{ $c[0] }};border:1px solid {{ $c[1] }};border-radius:12px;padding:12px 16px;font-size:14px;line-height:1.6;color:{{ $c[2] }};">{{ $slot }}</td></tr></table>
