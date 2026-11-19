@php
  $rtl = lang() === 'ar';
  $al = $rtl ? 'right' : 'left';
  $host = parse_url(abs_url('/'), PHP_URL_HOST) ?: 'w5d.tech';
  $org = (string)\W5D\Env::get('SEO_ORG_NAME', 'W5D TECH');
  $addr = trim((string)\W5D\Env::get('MAIL_FOOTER_ADDRESS', 'Tunisia'));
  $isStaff = !empty($isStaff);
  $cat = $category ?? 'transactional';
@endphp
<!DOCTYPE html>
<html lang="{{ lang() }}" dir="{{ dir_attr() }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $subject ?? 'W5D' }}</title>
<style>
  body,table,td{font-family:Inter,'Segoe UI',Helvetica,Arial,Tahoma,sans-serif;}
  a{color:#6d28d9;}
  @media only screen and (max-width:620px){ .w-card{width:100% !important;border-radius:0 !important;} .px{padding-left:20px !important;padding-right:20px !important;} }
  @media (prefers-color-scheme: dark){
    .bg-page{background:#070711 !important;} .bg-card{background:#0f1020 !important;border-color:#23233f !important;} .bg-foot{background:#0b0b18 !important;border-color:#23233f !important;}
    .t-main{color:#ffffff !important;} .t-body{color:#e2e8f0 !important;} .t-muted{color:#94a3b8 !important;} .bg-box{background:#171832 !important;border-color:#2a2b4d !important;}
    a{color:#a78bfa !important;} .btn a{color:#ffffff !important;}
  }
</style>
</head>
<body class="bg-page" style="margin:0;padding:0;background:#f1f5f9;-webkit-text-size-adjust:100%;" dir="{{ dir_attr() }}">
<!--pre--><div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;font-size:1px;line-height:1px;">@yield('preheader', $subject ?? '')&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;</div><!--/pre-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg-page" style="background:#f1f5f9;"><tr><td align="center" style="padding:28px 12px;">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="w-card bg-card" style="width:600px;max-width:100%;background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;">
    <tr><td bgcolor="#6d4aff" class="px" style="background:#6d4aff;background-image:linear-gradient(135deg,#7c3aed 0%,#6366f1 55%,#06b6d4 100%);padding:22px 32px;text-align:{{ $al }};">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" dir="{{ dir_attr() }}"><tr>
        <td style="background:#ffffff;border-radius:12px;width:56px;height:40px;text-align:center;line-height:0;"><img src="{{ \W5D\Env::baseUrl() }}{{ asset('images/logo-240.png') }}" width="44" height="21" alt="W5D" style="display:inline-block;border:0;vertical-align:middle;margin:9px 6px;"></td>
        <td style="padding:0 12px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.2px;">W5D</td>
        <td style="color:rgba(255,255,255,.85);font-size:12px;padding-top:2px;">{{ __('brand.tagline') }}</td>
      </tr></table>
    </td></tr>
    <tr><td class="px t-body" style="padding:32px 32px 8px;font-size:15px;line-height:1.7;color:#334155;text-align:{{ $al }};">
      @yield('content')
    </td></tr>
    <tr><td class="px" style="padding:8px 32px 28px;text-align:{{ $al }};">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="bg-box" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;font-size:12px;line-height:1.6;color:#64748b;text-align:{{ $al }};">
        <span class="t-muted" style="color:#64748b;">{{ __('limits.jobs') }} {{ __('limits.returns') }}</span>
      </td></tr></table>
    </td></tr>
    <tr><td class="px bg-foot" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:22px 32px;font-size:12px;line-height:1.7;color:#64748b;text-align:{{ $al }};">
      <p class="t-main" style="margin:0 0 6px;font-weight:700;color:#0f172a;font-size:13px;">{{ $org }}</p>
      <p class="t-muted" style="margin:0 0 10px;color:#64748b;">{{ $addr }} · <a href="{{ abs_url('/') }}" style="color:#6d28d9;">{{ $host }}</a> ·
        <a href="{{ abs_url('/contact/') }}" style="color:#6d28d9;">{{ __('nav.contact') }}</a> ·
        <a href="{{ abs_url('/trust/privacy/') }}" style="color:#6d28d9;">{{ __('footer.privacy') }}</a> ·
        <a href="{{ abs_url('/trust/terms/') }}" style="color:#6d28d9;">{{ __('footer.terms') }}</a></p>
      @if($isStaff)
        <p class="t-muted" style="margin:0;color:#64748b;">{{ tr3('Internal notification for the W5D team.', 'Notification interne pour l’équipe W5D.', 'إشعار داخلي لفريق W5D.') }}</p>
      @elseif($cat === 'updates')
        <p class="t-muted" style="margin:0;color:#64748b;">{{ tr3('You receive this because', 'Vous recevez ce message car', 'تصلك هذه الرسالة لأن') }} <strong>{{ $to ?? '' }}</strong> {{ tr3('subscribed to W5D dated updates.', 'est inscrit aux mises à jour datées de W5D.', 'مشترك في تحديثات W5D المؤرخة.') }}
          <a href="{{ $unsub }}" style="color:#6d28d9;font-weight:600;">{{ tr3('Unsubscribe', 'Se désabonner', 'إلغاء الاشتراك') }}</a></p>
      @else
        <p class="t-muted" style="margin:0;color:#64748b;">{{ tr3('This is a service e-mail about an action you took on', 'Ceci est un e-mail de service concernant une action effectuée sur', 'هذه رسالة خدمة بخصوص إجراء قمت به على') }} {{ $host }} {{ tr3('with', 'avec', 'باستخدام') }} <strong>{{ $to ?? '' }}</strong>. {{ tr3('Service e-mails cannot be turned off.', 'Les e-mails de service ne peuvent pas être désactivés.', 'لا يمكن إيقاف رسائل الخدمة.') }}
          <a href="{{ $unsub }}" style="color:#6d28d9;font-weight:600;">{{ tr3('Unsubscribe from updates / e-mail preferences', 'Se désabonner des mises à jour / préférences e-mail', 'إلغاء الاشتراك في التحديثات / تفضيلات البريد') }}</a></p>
      @endif
    </td></tr>
  </table>
</td></tr></table>
</body></html>
