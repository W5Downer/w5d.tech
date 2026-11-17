@props(['rows' => []])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg-box" style="margin:6px 0 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
  @foreach($rows as $i => $r)
  <tr><td class="t-muted" style="padding:11px 16px;font-size:13px;color:#64748b;{{ $i ? 'border-top:1px solid #e2e8f0;' : '' }}">{{ $r[0] }}</td>
      <td class="t-main" align="{{ lang() === 'ar' ? 'left' : 'right' }}" style="padding:11px 16px;font-size:14px;font-weight:600;color:#0f172a;{{ $i ? 'border-top:1px solid #e2e8f0;' : '' }}">{{ $r[1] }}</td></tr>
  @endforeach
</table>
