<?php
/** Certificate levels (shared by QCM rules, certificates and the public pages). Level number = rule.level = certificate.level. */
$L = fn(string $en, string $fr, string $ar): array => ['en' => $en, 'fr' => $fr, 'ar' => $ar];
return [
    'levels' => [
        1 => ['label' => $L('Foundation', 'Fondation', 'أساسي'),
              'blurb' => $L('Knows the service, its deliverables and its declared boundary.', 'Connaît le service, ses livrables et sa limite déclarée.', 'يعرف الخدمة ومخرجاتها وحدودها المعلنة.')],
        2 => ['label' => $L('Practitioner', 'Praticien', 'ممارس'),
              'blurb' => $L('Applies the method to a case and spots the common risks.', 'Applique la méthode à un cas et repère les risques courants.', 'يطبّق المنهج على حالة ويكتشف المخاطر الشائعة.')],
        3 => ['label' => $L('Specialist', 'Spécialiste', 'مختص'),
              'blurb' => $L('Reads evidence and thresholds, and handles dependencies between services.', 'Lit les preuves et les seuils, et gère les dépendances entre services.', 'يقرأ الأدلة والعتبات ويدير الاعتماد بين الخدمات.')],
        4 => ['label' => $L('Lead expert', 'Expert référent', 'خبير رائد'),
              'blurb' => $L('Judges trade-offs, reviews other experts and decides when to stop work.', 'Arbitre les compromis, relit d’autres experts et décide quand arrêter un travail.', 'يوازن بين الخيارات ويراجع الخبراء الآخرين ويقرر متى يوقف العمل.')],
    ],
];
