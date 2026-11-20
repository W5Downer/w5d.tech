<?php
/** The transactional e-mail types. Template = views/templates/w5d/mail/{template}.blade.php (en/fr/ar, RTL for ar). */
return [
    'types' => [
        'verify_email'   => ['subject' => ['en' => 'Confirm your W5D e-mail address', 'fr' => 'Confirmez votre adresse e-mail W5D', 'ar' => 'أكّد عنوان بريدك الإلكتروني في W5D']],
        'password_reset' => ['subject' => ['en' => 'Reset your W5D password', 'fr' => 'Réinitialisez votre mot de passe W5D', 'ar' => 'أعد تعيين كلمة مرور W5D']],
        'order_receipt'  => ['subject' => ['en' => 'Your W5D pledge :ref', 'fr' => 'Votre engagement W5D :ref', 'ar' => 'التزامك في W5D :ref']],
        'payment_status' => ['subject' => ['en' => 'W5D payment :ref: :status', 'fr' => 'Paiement W5D :ref : :status', 'ar' => 'دفعة W5D :ref: :status']],
        'certificate_issued' => ['subject' => ['en' => 'Your W5D certificate', 'fr' => 'Votre certificat W5D', 'ar' => 'شهادتك من W5D']],
        'affiliate_status'   => ['subject' => ['en' => 'Your W5D affiliate profile', 'fr' => 'Votre profil d’affilié W5D', 'ar' => 'ملف الإحالة الخاص بك في W5D']],
        'payout_status'      => ['subject' => ['en' => 'Your W5D affiliate payout', 'fr' => 'Votre paiement d’affilié W5D', 'ar' => 'صرف عمولة الإحالة في W5D']],
        'qcm_pass_status'    => ['subject' => ['en' => 'W5D QCM fee :ref: :status', 'fr' => 'Frais QCM W5D :ref : :status', 'ar' => 'رسوم اختبار W5D :ref: :status']],
        'form_ack'       => ['subject' => ['en' => 'We received your message', 'fr' => 'Nous avons bien reçu votre message', 'ar' => 'استلمنا رسالتك']],
    ],
];
