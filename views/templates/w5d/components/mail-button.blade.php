@props(['href', 'label'])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="btn" style="margin:24px 0 10px;"><tr>
  <td align="center" bgcolor="#6d28d9" style="background:#6d28d9;background-image:linear-gradient(135deg,#7c3aed,#6366f1);border-radius:12px;">
    <a href="{{ $href }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">{{ $label }}</a>
  </td></tr></table>
<p class="t-muted" style="margin:0 0 6px;font-size:12px;color:#64748b;word-break:break-all;">{{ tr3('Button not working? Copy this link into your browser:', 'Le bouton ne fonctionne pas ? Copiez ce lien dans votre navigateur :', 'الزر لا يعمل؟ انسخ هذا الرابط إلى متصفحك:') }}<br><a href="{{ $href }}" style="color:#6d28d9;">{{ $href }}</a></p>
