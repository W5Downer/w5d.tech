<?php
/** The 5 transactional e-mail types. Template = views/templates/w5d/mail/{template}.blade.php (en/fr/ar, RTL for ar). */
return [
    'types' => [
        'verify_email'   => ['subject' => ['en' => 'Confirm your W5D e-mail address', 'fr' => 'Confirmez votre adresse e-mail W5D', 'ar' => 'أكّد عنوان بريدك الإلكتروني في W5D']],
        'password_reset' => ['subject' => ['en' => 'Reset your W5D password', 'fr' => 'Réinitialisez votre mot de passe W5D', 'ar' => 'أعد تعيين كلمة مرور W5D']],
        'order_receipt'  => ['subject' => ['en' => 'Your W5D pledge :ref', 'fr' => 'Votre engagement W5D :ref', 'ar' => 'التزامك في W5D :ref']],
        'payment_status' => ['subject' => ['en' => 'W5D payment :ref: :status', 'fr' => 'Paiement W5D :ref : :status', 'ar' => 'دفعة W5D :ref: :status']],
        'form_ack'       => ['subject' => ['en' => 'We received your message', 'fr' => 'Nous avons bien reçu votre message', 'ar' => 'استلمنا رسالتك']],
    ],
];
