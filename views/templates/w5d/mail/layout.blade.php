<!DOCTYPE html>
<html lang="{{ lang() }}" dir="{{ dir_attr() }}">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{{ $subject ?? 'W5D' }}</title></head>
<body style="margin:0;padding:0;background:#070711;font-family:Inter,Segoe UI,Arial,Tahoma,sans-serif;color:#e2e8f0;" dir="{{ dir_attr() }}">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#070711;padding:24px 12px;"><tr><td align="center">
  <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#0b0b18;border:1px solid #1f1f3a;border-radius:16px;overflow:hidden;">
    <tr><td style="background:linear-gradient(135deg,#7c3aed,#6366f1,#22d3ee);padding:18px 24px;text-align:{{ lang() === 'ar' ? 'right' : 'left' }};">
      <span style="display:inline-block;background:rgba(255,255,255,.18);color:#fff;font-weight:700;border-radius:10px;padding:6px 10px;font-size:14px;">W5</span>
      <span style="color:#fff;font-weight:700;font-size:18px;margin:0 8px;">W5D</span>
    </td></tr>
    <tr><td style="padding:28px 24px;font-size:15px;line-height:1.65;color:#e2e8f0;text-align:{{ lang() === 'ar' ? 'right' : 'left' }};">
      @yield('content')
    </td></tr>
    <tr><td style="padding:16px 24px;border-top:1px solid #1f1f3a;font-size:12px;line-height:1.55;color:#94a3b8;text-align:{{ lang() === 'ar' ? 'right' : 'left' }};">
      {{ __('limits.jobs') }}<br>{{ __('limits.returns') }}<br>
      <a href="{{ abs_url('/') }}" style="color:#a78bfa;">{{ parse_url(abs_url('/'), PHP_URL_HOST) ?: 'w5d.tech' }}</a>
    </td></tr>
  </table>
</td></tr></table>
</body></html>
