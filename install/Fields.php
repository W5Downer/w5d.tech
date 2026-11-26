<?php
declare(strict_types=1);

namespace W5D\Install;

if (!defined('ROOT')) { http_response_code(404); exit; }

/**
 * The wizard steps and their fields. Every field is one production setting of .env (the `env` key is printed next to the label),
 * so the wizard is the .env file in the form of questions. Texts are written by hand in English, French and Arabic.
 *
 * field: env, type (text|email|number|password|select|checkbox|fixed|radio), label, help, options (select/radio: value => [label, description]),
 *        req (bool), secret (never sent back to the browser), min/max (number), ph (placeholder)
 */
final class Fields
{
    public const ORDER = ['requirements', 'site', 'team', 'storage', 'mail', 'payments', 'brand', 'cron', 'review', 'done'];

    /** Portal paths that would collide with public routes or that are easy to guess. */
    public const RESERVED_MOUNT = ['admin', 'panel', 'panel-admin', 'dashboard', 'install', 'cron', 'api', 'assets', 'account', 'pay', 'invoice', 'plans', 'services', 'projects', 'contact', 'language', 'lang', 'sitemap', 'robots', 'login', 'logout'];

    public static function steps(): array
    {
        return [
            'requirements' => [
                'title' => tr3('Requirements', 'Prérequis', 'المتطلبات'),
                'lead' => tr3('Before anything is written, the installer checks this server: the PHP version, the PHP extensions the site needs and the folders it must write to. Red lines must be fixed first; amber lines only switch off an optional feature.', 'Avant d\'écrire quoi que ce soit, l\'installateur vérifie ce serveur : la version de PHP, les extensions PHP dont le site a besoin et les dossiers où il doit écrire. Les lignes rouges sont à corriger d\'abord ; les lignes orange désactivent seulement une fonction facultative.', 'قبل كتابة أي شيء يفحص المثبّت هذا الخادم: إصدار PHP والامتدادات التي يحتاجها الموقع والمجلدات التي يجب أن يكتب فيها. السطور الحمراء يجب إصلاحها أولا؛ أما البرتقالية فتعطّل ميزة اختيارية فقط.'),
                'fields' => [],
            ],
            'site' => [
                'title' => tr3('Site address', 'Adresse du site', 'عنوان الموقع'),
                'lead' => tr3('The address of the site and the secret address of the owner portal. These become APP_ENV=production, APP_URL, APP_BASE_PATH, APP_LANG and ADMIN_URL_PATH in the .env file.', 'L\'adresse du site et l\'adresse secrète du portail du propriétaire. Elles deviennent APP_ENV=production, APP_URL, APP_BASE_PATH, APP_LANG et ADMIN_URL_PATH dans le fichier .env.', 'عنوان الموقع والعنوان السري لبوابة المالك. يصبحان APP_ENV=production و APP_URL و APP_BASE_PATH و APP_LANG و ADMIN_URL_PATH في ملف .env.'),
                'fields' => [
                    ['env' => 'APP_URL', 'type' => 'text', 'req' => true, 'ph' => 'https://example.org',
                     'label' => tr3('Public address of the site', 'Adresse publique du site', 'العنوان العام للموقع'),
                     'help' => tr3('The https address visitors type, without a path and without a trailing slash, for example https://example.org. It is used in links, the sitemap and e-mails. Plain http is accepted only for localhost.', 'L\'adresse https que les visiteurs saisissent, sans chemin et sans barre oblique finale, par exemple https://example.org. Elle sert aux liens, au sitemap et aux e-mails. Le http simple n\'est accepté que pour localhost.', 'عنوان https الذي يكتبه الزوار، بلا مسار وبلا شرطة مائلة في النهاية، مثل https://example.org. يُستعمل في الروابط وخريطة الموقع ورسائل البريد. لا يُقبل http العادي إلا مع localhost.')],
                    ['env' => 'APP_BASE_PATH', 'type' => 'text', 'req' => false, 'ph' => '',
                     'label' => tr3('Sub-folder (almost always empty)', 'Sous-dossier (presque toujours vide)', 'مجلد فرعي (فارغ في الغالب)'),
                     'help' => tr3('Leave empty when the site is at the root of its domain. Fill it, for example /shop, only if the site lives in a sub-folder and you opened this wizard there.', 'Laissez vide si le site est à la racine de son domaine. Remplissez-le, par exemple /shop, seulement si le site est dans un sous-dossier et que vous avez ouvert cet assistant à cet endroit.', 'اتركه فارغا إن كان الموقع في جذر نطاقه. املأه، مثل /shop، فقط إن كان الموقع في مجلد فرعي وفتحت هذا المعالج هناك.')],
                    ['env' => 'APP_LANG', 'type' => 'select', 'req' => true,
                     'label' => tr3('Default language', 'Langue par défaut', 'اللغة الافتراضية'),
                     'help' => tr3('Used when a visitor\'s browser does not say which of the three languages it prefers.', 'Utilisée quand le navigateur d\'un visiteur n\'indique pas laquelle des trois langues il préfère.', 'تُستعمل حين لا يحدد متصفح الزائر أيّ اللغات الثلاث يفضّل.'),
                     'options' => ['en' => ['English'], 'fr' => ['Français'], 'ar' => ['العربية']]],
                    ['env' => 'ADMIN_URL_PATH', 'type' => 'text', 'req' => true, 'ph' => 'p-0123456789ab',
                     'label' => tr3('Secret portal path', 'Chemin secret du portail', 'مسار البوابة السري'),
                     'help' => tr3('The owner portal answers only at this path, for example https://example.org/p-0123456789ab/. 6 to 60 letters, digits, - or _; not admin, panel or dashboard. A random one is proposed: keep it, and share it only with the team.', 'Le portail du propriétaire ne répond qu\'à ce chemin, par exemple https://example.org/p-0123456789ab/. 6 à 60 lettres, chiffres, - ou _ ; pas admin, panel ni dashboard. Un chemin aléatoire est proposé : gardez-le et ne le partagez qu\'avec l\'équipe.', 'لا تستجيب بوابة المالك إلا على هذا المسار، مثل https://example.org/p-0123456789ab/. من 6 إلى 60 حرفا أو رقما أو - أو _؛ وليس admin أو panel أو dashboard. يُقترح مسار عشوائي: احتفظ به ولا تشاركه إلا مع الفريق.')],
                ],
            ],
            'team' => [
                'title' => tr3('Owner and team sign-in', 'Propriétaire et connexion de l\'équipe', 'المالك ودخول الفريق'),
                'lead' => tr3('Who owns the portal and how the team signs in: with a password, or with a 6-digit code sent by e-mail to an address on the team domain. These become OWNER_EMAIL, STAFF_EMAIL_DOMAIN, WEBMASTER_PASSWORD_HASH, STAFF_CODE_TTL_MIN and STAFF_IDLE_MINUTES.', 'Qui possède le portail et comment l\'équipe se connecte : avec un mot de passe, ou avec un code à 6 chiffres envoyé par e-mail à une adresse du domaine de l\'équipe. Ils deviennent OWNER_EMAIL, STAFF_EMAIL_DOMAIN, WEBMASTER_PASSWORD_HASH, STAFF_CODE_TTL_MIN et STAFF_IDLE_MINUTES.', 'من يملك البوابة وكيف يدخل الفريق: بكلمة مرور أو برمز من 6 أرقام يُرسل بالبريد إلى عنوان على نطاق الفريق. يصبح هذا OWNER_EMAIL و STAFF_EMAIL_DOMAIN و WEBMASTER_PASSWORD_HASH و STAFF_CODE_TTL_MIN و STAFF_IDLE_MINUTES.'),
                'fields' => [
                    ['env' => 'OWNER_EMAIL', 'type' => 'email', 'req' => true, 'ph' => 'owner@example.org',
                     'label' => tr3('Owner e-mail', 'E-mail du propriétaire', 'بريد المالك'),
                     'help' => tr3('A mailbox you can really read, on the team domain below. It is the first account of the portal.', 'Une boîte e-mail que vous pouvez vraiment lire, sur le domaine d\'équipe ci-dessous. C\'est le premier compte du portail.', 'صندوق بريد تستطيع قراءته فعلا، على نطاق الفريق أدناه. هو أول حساب في البوابة.')],
                    ['env' => 'STAFF_EMAIL_DOMAIN', 'type' => 'text', 'req' => false, 'ph' => 'example.org',
                     'label' => tr3('Team e-mail domain', 'Domaine e-mail de l\'équipe', 'نطاق بريد الفريق'),
                     'help' => tr3('Only addresses on this domain can be added to the team. Leave empty to use the domain of the owner e-mail.', 'Seules les adresses de ce domaine peuvent rejoindre l\'équipe. Laissez vide pour utiliser le domaine de l\'e-mail du propriétaire.', 'لا يمكن إضافة إلى الفريق إلا العناوين على هذا النطاق. اتركه فارغا لاستعمال نطاق بريد المالك.')],
                    ['env' => 'WEBMASTER_PASSWORD_HASH', 'type' => 'password', 'req' => false, 'secret' => true,
                     'label' => tr3('Owner password', 'Mot de passe du propriétaire', 'كلمة مرور المالك'),
                     'help' => tr3('At least 12 characters. Only a hash is written to .env; the password itself is never stored. Required when e-mail is set to "log" (nothing is sent), otherwise optional: without it the owner signs in with the e-mailed code.', 'Au moins 12 caractères. Seul un hash est écrit dans .env ; le mot de passe lui-même n\'est jamais conservé. Obligatoire quand l\'e-mail est réglé sur « log » (rien n\'est envoyé), sinon facultatif : sans lui le propriétaire se connecte avec le code reçu par e-mail.', '12 حرفا على الأقل. تُكتب في .env بصمة فقط ولا تُحفظ كلمة المرور نفسها. إلزامية حين يكون البريد على "log" (لا يُرسل شيء) وإلا فاختيارية: بدونها يدخل المالك بالرمز المرسل بالبريد.')],
                    ['env' => '', 'name' => 'password2', 'type' => 'password', 'req' => false, 'secret' => true,
                     'label' => tr3('Repeat the password', 'Répétez le mot de passe', 'أعد كتابة كلمة المرور'),
                     'help' => tr3('Type it again to be sure there is no typing mistake.', 'Saisissez-le encore pour éviter une faute de frappe.', 'اكتبها مرة أخرى للتأكد من خلوها من خطأ الكتابة.')],
                    ['env' => 'STAFF_CODE_TTL_MIN', 'type' => 'number', 'req' => true, 'min' => 3, 'max' => 30,
                     'label' => tr3('E-mailed code validity (minutes)', 'Validité du code reçu par e-mail (minutes)', 'صلاحية الرمز المرسل بالبريد (بالدقائق)'),
                     'help' => tr3('From 3 to 30 minutes.', 'De 3 à 30 minutes.', 'من 3 إلى 30 دقيقة.')],
                    ['env' => 'STAFF_IDLE_MINUTES', 'type' => 'number', 'req' => true, 'min' => 5, 'max' => 120,
                     'label' => tr3('Automatic sign-out after inactivity (minutes)', 'Déconnexion automatique après inactivité (minutes)', 'الخروج التلقائي بعد الخمول (بالدقائق)'),
                     'help' => tr3('From 5 to 120 minutes. A session never lasts more than 12 hours in any case.', 'De 5 à 120 minutes. Une session ne dure jamais plus de 12 heures de toute façon.', 'من 5 إلى 120 دقيقة. ولا تدوم الجلسة أكثر من 12 ساعة في كل الأحوال.')],
                ],
            ],
            'storage' => [
                'title' => tr3('Database and storage', 'Base de données et stockage', 'قاعدة البيانات والتخزين'),
                'lead' => tr3('Where the data lives and what the nightly jobs do with it. These become DB_DRIVER, DB_PATH, BACKUP_DRIVER, ARCHIVE_DRIVER and UPLOAD_MAX_MB. The database file is created and filled in the last step.', 'Où vivent les données et ce que les tâches de nuit en font. Ils deviennent DB_DRIVER, DB_PATH, BACKUP_DRIVER, ARCHIVE_DRIVER et UPLOAD_MAX_MB. Le fichier de base de données est créé et rempli à la dernière étape.', 'أين تعيش البيانات وماذا تفعل بها مهام الليل. يصبح هذا DB_DRIVER و DB_PATH و BACKUP_DRIVER و ARCHIVE_DRIVER و UPLOAD_MAX_MB. يُنشأ ملف قاعدة البيانات ويُملأ في الخطوة الأخيرة.'),
                'fields' => [
                    ['env' => 'DB_DRIVER', 'type' => 'fixed', 'value' => 'sqlite',
                     'label' => tr3('Database engine', 'Moteur de base de données', 'محرك قاعدة البيانات'),
                     'help' => tr3('SQLite, one file, no database server to run. It is the only engine this build supports.', 'SQLite, un seul fichier, aucun serveur de base de données à exploiter. C\'est le seul moteur que cette version prend en charge.', 'SQLite، ملف واحد ولا خادم قاعدة بيانات لتشغيله. وهو المحرك الوحيد الذي تدعمه هذه النسخة.')],
                    ['env' => 'DB_PATH', 'type' => 'text', 'req' => true, 'ph' => 'storage/w5d.sqlite',
                     'label' => tr3('Database file', 'Fichier de base de données', 'ملف قاعدة البيانات'),
                     'help' => tr3('A path inside the project folder, or an absolute path outside the public folder. Keep the default unless you know why not.', 'Un chemin dans le dossier du projet, ou un chemin absolu hors du dossier public. Gardez la valeur par défaut sauf raison précise.', 'مسار داخل مجلد المشروع أو مسار مطلق خارج المجلد العام. أبقِ القيمة الافتراضية إلا لسبب واضح.')],
                    ['env' => 'BACKUP_DRIVER', 'type' => 'select', 'req' => true,
                     'label' => tr3('Backups', 'Sauvegardes', 'النسخ الاحتياطية'),
                     'help' => tr3('Both write a consistent copy to storage/backups/latest.sqlite. With "external" you promise to copy that file off the server yourself; a copy on the same server is not a backup.', 'Les deux écrivent une copie cohérente dans storage/backups/latest.sqlite. Avec « external » vous vous engagez à copier ce fichier hors du serveur ; une copie sur le même serveur n\'est pas une sauvegarde.', 'كلاهما يكتب نسخة متسقة في storage/backups/latest.sqlite. مع "external" تتعهد بنسخ ذلك الملف خارج الخادم بنفسك؛ فنسخة على الخادم نفسه ليست نسخة احتياطية.'),
                     'options' => ['external' => [tr3('external: I copy the file off the server', 'external : je copie le fichier hors du serveur', 'external: أنسخ الملف خارج الخادم')], 'local' => [tr3('local: keep it on this server only', 'local : le garder sur ce serveur seulement', 'local: أبقيه على هذا الخادم فقط')]]],
                    ['env' => 'ARCHIVE_DRIVER', 'type' => 'select', 'req' => true,
                     'label' => tr3('Old weekly reports', 'Anciens rapports hebdomadaires', 'التقارير الأسبوعية القديمة'),
                     'help' => tr3('What the weekly clean-up does with reports older than the current week.', 'Ce que le nettoyage hebdomadaire fait des rapports plus anciens que la semaine en cours.', 'ما يفعله التنظيف الأسبوعي بالتقارير الأقدم من الأسبوع الحالي.'),
                     'options' => ['prune' => [tr3('prune: delete them', 'prune : les supprimer', 'prune: احذفها')], 'local' => [tr3('local: save a file in storage/archive first, then delete', 'local : enregistrer d\'abord un fichier dans storage/archive, puis supprimer', 'local: احفظ ملفا في storage/archive أولا ثم احذف')]]],
                    ['env' => 'UPLOAD_MAX_MB', 'type' => 'number', 'req' => true, 'min' => 1, 'max' => 20,
                     'label' => tr3('Largest upload (MB)', 'Plus gros téléversement (Mo)', 'أكبر حجم رفع (ميغابايت)'),
                     'help' => tr3('From 1 to 20. The PHP limits upload_max_filesize and post_max_size of the host also apply.', 'De 1 à 20. Les limites PHP upload_max_filesize et post_max_size de l\'hébergeur s\'appliquent aussi.', 'من 1 إلى 20. وتسري أيضا حدود PHP لدى المضيف: upload_max_filesize و post_max_size.')],
                ],
            ],
            'mail' => [
                'title' => tr3('Outgoing e-mail', 'E-mail sortant', 'البريد الصادر'),
                'lead' => tr3('The site sends sign-in codes, receipts and invoices. These become MAIL_DRIVER, MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_REPLY_TO, MAIL_FOOTER_ADDRESS and CONTACT_EMAIL. Nothing is sent during the installation.', 'Le site envoie des codes de connexion, des reçus et des factures. Ils deviennent MAIL_DRIVER, MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_REPLY_TO, MAIL_FOOTER_ADDRESS et CONTACT_EMAIL. Rien n\'est envoyé pendant l\'installation.', 'يرسل الموقع رموز الدخول والإيصالات والفواتير. يصبح هذا MAIL_DRIVER و MAIL_HOST و MAIL_PORT و MAIL_ENCRYPTION و MAIL_USERNAME و MAIL_PASSWORD و MAIL_FROM_ADDRESS و MAIL_FROM_NAME و MAIL_REPLY_TO و MAIL_FOOTER_ADDRESS و CONTACT_EMAIL. لا يُرسل شيء أثناء التثبيت.'),
                'fields' => [
                    ['env' => 'MAIL_DRIVER', 'type' => 'select', 'req' => true,
                     'label' => tr3('How mail is sent', 'Mode d\'envoi', 'طريقة الإرسال'),
                     'help' => tr3('smtp uses the mail server of your host or provider (recommended). mail uses the PHP mail() function of the host. log only writes messages to storage/logs: nothing leaves the server, so nobody can receive a sign-in code.', 'smtp utilise le serveur de messagerie de votre hébergeur ou fournisseur (recommandé). mail utilise la fonction PHP mail() de l\'hébergeur. log écrit seulement les messages dans storage/logs : rien ne quitte le serveur, donc personne ne peut recevoir de code de connexion.', 'يستعمل smtp خادم البريد لدى مضيفك أو مزوّدك (موصى به). ويستعمل mail دالة PHP mail() لدى المضيف. أما log فيكتب الرسائل في storage/logs فقط: لا يخرج شيء من الخادم فلا يستطيع أحد استلام رمز دخول.'),
                     'options' => ['smtp' => ['smtp'], 'mail' => ['mail'], 'log' => ['log']]],
                    ['env' => 'MAIL_HOST', 'type' => 'text', 'req' => false, 'ph' => 'smtp.example.org',
                     'label' => tr3('SMTP server', 'Serveur SMTP', 'خادم SMTP'),
                     'help' => tr3('Required for smtp.', 'Obligatoire pour smtp.', 'مطلوب مع smtp.')],
                    ['env' => 'MAIL_PORT', 'type' => 'number', 'req' => false, 'min' => 1, 'max' => 65535,
                     'label' => tr3('SMTP port', 'Port SMTP', 'منفذ SMTP'),
                     'help' => tr3('Usually 587 with tls or 465 with ssl.', 'En général 587 avec tls ou 465 avec ssl.', 'عادة 587 مع tls أو 465 مع ssl.')],
                    ['env' => 'MAIL_ENCRYPTION', 'type' => 'select', 'req' => false,
                     'label' => tr3('Encryption', 'Chiffrement', 'التشفير'),
                     'help' => tr3('tls = STARTTLS (587), ssl = implicit TLS (465), none = no encryption (avoid).', 'tls = STARTTLS (587), ssl = TLS implicite (465), none = sans chiffrement (à éviter).', 'tls = STARTTLS (587) وssl = TLS ضمني (465) وnone = بلا تشفير (يُفضّل تجنبه).'),
                     'options' => ['tls' => ['tls'], 'ssl' => ['ssl'], 'none' => ['none']]],
                    ['env' => 'MAIL_USERNAME', 'type' => 'text', 'req' => false, 'ph' => '',
                     'label' => tr3('SMTP user name', 'Nom d\'utilisateur SMTP', 'اسم مستخدم SMTP'),
                     'help' => tr3('Often the full mailbox address. Empty when the server needs no sign-in.', 'Souvent l\'adresse complète de la boîte. Vide si le serveur n\'exige pas de connexion.', 'غالبا عنوان الصندوق كاملا. فارغ إن كان الخادم لا يطلب تسجيل دخول.')],
                    ['env' => 'MAIL_PASSWORD', 'type' => 'password', 'req' => false, 'secret' => true,
                     'label' => tr3('SMTP password', 'Mot de passe SMTP', 'كلمة مرور SMTP'),
                     'help' => tr3('Written to .env (mode 600) because the site needs it to send. It is never shown again in this wizard; leave empty to keep the one already typed.', 'Écrit dans .env (mode 600) car le site en a besoin pour envoyer. Il n\'est plus jamais réaffiché dans cet assistant ; laissez vide pour garder celui déjà saisi.', 'تُكتب في .env (الصلاحية 600) لأن الموقع يحتاجها للإرسال. لا تظهر ثانية في هذا المعالج؛ اتركها فارغة للإبقاء على المكتوبة سابقا.')],
                    ['env' => 'MAIL_FROM_ADDRESS', 'type' => 'email', 'req' => false, 'ph' => 'no-reply@example.org',
                     'label' => tr3('Sender address', 'Adresse d\'expédition', 'عنوان المرسل'),
                     'help' => tr3('Use a domain that has SPF and DKIM, or messages land in spam.', 'Utilisez un domaine avec SPF et DKIM, sinon les messages finissent dans les indésirables.', 'استعمل نطاقا مضبوطا عليه SPF وDKIM وإلا ذهبت الرسائل إلى البريد المزعج.')],
                    ['env' => 'MAIL_FROM_NAME', 'type' => 'text', 'req' => false, 'ph' => 'W5D',
                     'label' => tr3('Sender name', 'Nom d\'expéditeur', 'اسم المرسل'),
                     'help' => tr3('Shown before the sender address in the inbox.', 'Affiché avant l\'adresse d\'expédition dans la boîte de réception.', 'يظهر قبل عنوان المرسل في صندوق الوارد.')],
                    ['env' => 'MAIL_REPLY_TO', 'type' => 'email', 'req' => false, 'ph' => 'owner@example.org',
                     'label' => tr3('Reply-to address', 'Adresse de réponse', 'عنوان الرد'),
                     'help' => tr3('Where replies go. Defaults to the owner e-mail.', 'Où vont les réponses. Par défaut l\'e-mail du propriétaire.', 'إلى أين تذهب الردود. الافتراضي بريد المالك.')],
                    ['env' => 'MAIL_FOOTER_ADDRESS', 'type' => 'text', 'req' => false, 'ph' => '',
                     'label' => tr3('Postal / company line in every e-mail', 'Ligne postale / société dans chaque e-mail', 'السطر البريدي / الشركة في كل رسالة'),
                     'help' => tr3('The real postal address or company line, printed in the footer of every e-mail (anti-spam rules). Required unless mail is set to "log".', 'La vraie adresse postale ou la mention de société, imprimée au pied de chaque e-mail (règles anti-spam). Obligatoire sauf si l\'envoi est réglé sur « log ».', 'العنوان البريدي الحقيقي أو سطر الشركة، يُطبع في ذيل كل رسالة (قواعد مكافحة الرسائل المزعجة). مطلوب ما لم يكن الإرسال على "log".')],
                    ['env' => 'CONTACT_EMAIL', 'type' => 'email', 'req' => false, 'ph' => 'owner@example.org',
                     'label' => tr3('Public contact address', 'Adresse de contact publique', 'عنوان التواصل العام'),
                     'help' => tr3('Where the contact form messages are sent. Defaults to the owner e-mail.', 'Où sont envoyés les messages du formulaire de contact. Par défaut l\'e-mail du propriétaire.', 'إلى أين تُرسل رسائل نموذج التواصل. الافتراضي بريد المالك.')],
                ],
            ],
            'payments' => [
                'title' => tr3('Payments', 'Paiements', 'المدفوعات'),
                'lead' => tr3('No money moves during the installation and PAYMENT_MODE stays "demo". D17 is a manual method: customers send a transfer and the team verifies it. Online providers (Flouci, Konnect, Paymee) are configured later in the portal, where their keys are stored encrypted.', 'Aucun argent ne circule pendant l\'installation et PAYMENT_MODE reste « demo ». D17 est une méthode manuelle : le client envoie un virement et l\'équipe le vérifie. Les prestataires en ligne (Flouci, Konnect, Paymee) se configurent plus tard dans le portail, où leurs clés sont stockées chiffrées.', 'لا يتحرك أي مال أثناء التثبيت ويبقى PAYMENT_MODE على "demo". D17 طريقة يدوية: يرسل العميل حوالة ويتحقق منها الفريق. أما المزوّدون عبر الإنترنت (Flouci وKonnect وPaymee) فيُضبطون لاحقا في البوابة حيث تُخزَّن مفاتيحهم مشفّرة.'),
                'fields' => [
                    ['env' => 'PAYMENT_MODE', 'type' => 'fixed', 'value' => 'demo',
                     'label' => tr3('Payment mode', 'Mode de paiement', 'وضع الدفع'),
                     'help' => tr3('Fixed to "demo" by the installer. Change it only after your accountant and a provider contract say so.', 'Fixé à « demo » par l\'installateur. Ne le changez qu\'après accord de votre comptable et d\'un contrat avec un prestataire.', 'يثبّته المثبّت على "demo". لا تغيّره إلا بعد موافقة محاسبك وعقد مع مزوّد.')],
                    ['env' => 'PAYMENT_D17_ENABLED', 'type' => 'checkbox', 'req' => false,
                     'label' => tr3('Offer D17 (Tunisia, manual verification)', 'Proposer D17 (Tunisie, vérification manuelle)', 'إتاحة D17 (تونس، تحقق يدوي)'),
                     'help' => tr3('Leave off if you do not receive D17 transfers.', 'Laissez désactivé si vous ne recevez pas de virements D17.', 'اتركه مغلقا إن كنت لا تستقبل حوالات D17.')],
                    ['env' => 'PAYMENT_D17_GSM', 'type' => 'text', 'req' => false, 'ph' => '+216XXXXXXXX',
                     'label' => tr3('D17 wallet number', 'Numéro du portefeuille D17', 'رقم محفظة D17'),
                     'help' => tr3('The number customers send money to, written +216 followed by 8 digits. Required when D17 is on.', 'Le numéro vers lequel les clients envoient l\'argent, écrit +216 suivi de 8 chiffres. Obligatoire si D17 est activé.', 'الرقم الذي يرسل إليه العملاء المال، يُكتب +216 ثم 8 أرقام. مطلوب عند تفعيل D17.')],
                    ['env' => 'PAYMENT_D17_NAME', 'type' => 'text', 'req' => false, 'ph' => '',
                     'label' => tr3('D17 account holder name', 'Nom du titulaire D17', 'اسم صاحب حساب D17'),
                     'help' => tr3('Shown to the payer so they can check who they are paying. Required when D17 is on.', 'Affiché au payeur pour qu\'il vérifie qui il paie. Obligatoire si D17 est activé.', 'يظهر للدافع ليتأكد ممن يدفع له. مطلوب عند تفعيل D17.')],
                    ['env' => 'PAYMENT_D17_NOTE', 'type' => 'text', 'req' => false, 'ph' => '',
                     'label' => tr3('Extra instruction for the payer', 'Consigne supplémentaire pour le payeur', 'تعليمات إضافية للدافع'),
                     'help' => tr3('Optional, one line.', 'Facultatif, une ligne.', 'اختياري، سطر واحد.')],
                ],
            ],
            'brand' => [
                'title' => tr3('Organisation and social profiles', 'Organisation et profils sociaux', 'المؤسسة والملفات الاجتماعية'),
                'lead' => tr3('The name and public profiles used in search results, structured data and share buttons. These become SEO_ORG_NAME, SEO_ORG_EMAIL, SOCIAL_SHARE_VIA and the SOCIAL_PROFILE_* settings. Every field is optional except the name.', 'Le nom et les profils publics utilisés dans les résultats de recherche, les données structurées et les boutons de partage. Ils deviennent SEO_ORG_NAME, SEO_ORG_EMAIL, SOCIAL_SHARE_VIA et les réglages SOCIAL_PROFILE_*. Tous les champs sont facultatifs sauf le nom.', 'الاسم والملفات العامة المستعملة في نتائج البحث والبيانات المهيكلة وأزرار المشاركة. يصبح هذا SEO_ORG_NAME و SEO_ORG_EMAIL و SOCIAL_SHARE_VIA وإعدادات SOCIAL_PROFILE_*. كل الحقول اختيارية عدا الاسم.'),
                'fields' => [
                    ['env' => 'SEO_ORG_NAME', 'type' => 'text', 'req' => true, 'ph' => 'W5D TECH',
                     'label' => tr3('Organisation name', 'Nom de l\'organisation', 'اسم المؤسسة'),
                     'help' => tr3('Use the name you are really entitled to use. Do not invent a registration number.', 'Utilisez le nom que vous avez réellement le droit d\'utiliser. N\'inventez pas de numéro d\'immatriculation.', 'استعمل الاسم الذي يحق لك استعماله فعلا. ولا تختلق رقم تسجيل.')],
                    ['env' => 'SEO_ORG_EMAIL', 'type' => 'email', 'req' => false, 'ph' => 'owner@example.org',
                     'label' => tr3('Organisation e-mail', 'E-mail de l\'organisation', 'بريد المؤسسة'),
                     'help' => tr3('Published in the structured data of the site. Defaults to the owner e-mail.', 'Publié dans les données structurées du site. Par défaut l\'e-mail du propriétaire.', 'يُنشر في البيانات المهيكلة للموقع. الافتراضي بريد المالك.')],
                    ['env' => 'SOCIAL_SHARE_VIA', 'type' => 'text', 'req' => false, 'ph' => 'your_handle',
                     'label' => tr3('X (Twitter) handle', 'Compte X (Twitter)', 'حساب X (تويتر)'),
                     'help' => tr3('Without the @ sign.', 'Sans le signe @.', 'بلا علامة @.')],
                    ['env' => 'SOCIAL_PROFILE_FACEBOOK', 'type' => 'text', 'req' => false, 'ph' => 'https://www.facebook.com/your-page',
                     'label' => tr3('Facebook page', 'Page Facebook', 'صفحة فيسبوك'), 'help' => tr3('A full https address, or empty.', 'Une adresse https complète, ou vide.', 'عنوان https كامل أو فارغ.')],
                    ['env' => 'SOCIAL_PROFILE_X', 'type' => 'text', 'req' => false, 'ph' => 'https://x.com/your_handle',
                     'label' => tr3('X profile', 'Profil X', 'حساب X'), 'help' => tr3('A full https address, or empty.', 'Une adresse https complète, ou vide.', 'عنوان https كامل أو فارغ.')],
                    ['env' => 'SOCIAL_PROFILE_LINKEDIN', 'type' => 'text', 'req' => false, 'ph' => 'https://www.linkedin.com/company/your-company',
                     'label' => tr3('LinkedIn page', 'Page LinkedIn', 'صفحة لينكدإن'), 'help' => tr3('A full https address, or empty.', 'Une adresse https complète, ou vide.', 'عنوان https كامل أو فارغ.')],
                    ['env' => 'SOCIAL_PROFILE_GITHUB', 'type' => 'text', 'req' => false, 'ph' => 'https://github.com/your-organisation',
                     'label' => tr3('GitHub organisation', 'Organisation GitHub', 'مؤسسة جيت هاب'), 'help' => tr3('A full https address, or empty.', 'Une adresse https complète, ou vide.', 'عنوان https كامل أو فارغ.')],
                    ['env' => 'SOCIAL_PROFILE_INSTAGRAM', 'type' => 'text', 'req' => false, 'ph' => 'https://www.instagram.com/your_handle',
                     'label' => tr3('Instagram profile', 'Profil Instagram', 'حساب إنستغرام'), 'help' => tr3('A full https address, or empty.', 'Une adresse https complète, ou vide.', 'عنوان https كامل أو فارغ.')],
                    ['env' => 'SOCIAL_PROFILE_YOUTUBE', 'type' => 'text', 'req' => false, 'ph' => 'https://www.youtube.com/@your-channel',
                     'label' => tr3('YouTube channel', 'Chaîne YouTube', 'قناة يوتيوب'), 'help' => tr3('A full https address, or empty.', 'Une adresse https complète, ou vide.', 'عنوان https كامل أو فارغ.')],
                ],
            ],
            'cron' => [
                'title' => tr3('Scheduled jobs', 'Tâches planifiées', 'المهام المجدولة'),
                'lead' => tr3('The site needs 6 small recurring jobs: re-check online payments, send renewal invoices, refresh dashboards, make a database copy, clean up weekly and watch storage. Normally the host\'s cron runs them. If your host has no cron, pick one of the other two ways. This becomes CRON_MODE (and CRON_TOKEN for the web way).', 'Le site a besoin de 6 petites tâches récurrentes : revérifier les paiements en ligne, envoyer les factures de renouvellement, actualiser les tableaux de bord, copier la base, nettoyer chaque semaine et surveiller le stockage. Normalement le cron de l\'hébergeur les lance. Si votre hébergeur n\'a pas de cron, choisissez l\'une des deux autres voies. Cela devient CRON_MODE (et CRON_TOKEN pour la voie web).', 'يحتاج الموقع إلى 6 مهام صغيرة متكررة: إعادة فحص المدفوعات الإلكترونية وإرسال فواتير التجديد وتحديث لوحات المتابعة ونسخ قاعدة البيانات والتنظيف الأسبوعي ومراقبة التخزين. عادة يشغّلها cron لدى المضيف. وإن لم يكن لدى مضيفك cron فاختر إحدى الطريقتين الأخريين. يصبح هذا CRON_MODE (و CRON_TOKEN للطريقة عبر الويب).'),
                'fields' => [
                    ['env' => 'CRON_MODE', 'type' => 'radio', 'req' => true,
                     'label' => tr3('How will the jobs run?', 'Comment les tâches seront-elles lancées ?', 'كيف ستُشغَّل المهام؟'),
                     'help' => '',
                     'options' => [
                        'system' => [tr3('My host has cron (crontab, or a "Cron jobs" screen in the hosting panel)', 'Mon hébergeur a cron (crontab, ou un écran « Tâches cron » dans le panneau)', 'لدى مضيفي cron (crontab أو شاشة "المهام المجدولة" في لوحة الاستضافة)'),
                                     tr3('Best option. You add one line (php bin/cron.php) that runs every 5 minutes; the last page shows it with your real paths.', 'La meilleure option. Vous ajoutez une seule ligne (php bin/cron.php) qui s\'exécute toutes les 5 minutes ; la dernière page l\'affiche avec vos vrais chemins.', 'أفضل خيار. تضيف سطرا واحدا (php bin/cron.php) يعمل كل 5 دقائق؛ وتعرضه الصفحة الأخيرة بمساراتك الحقيقية.')],
                        'web' => [tr3('No cron, but I can use a free outside timer (cron-job.org, UptimeRobot, a monitoring service)', 'Pas de cron, mais je peux utiliser un minuteur externe gratuit (cron-job.org, UptimeRobot, un service de supervision)', 'لا cron لكن أستطيع استعمال مؤقّت خارجي مجاني (cron-job.org أو UptimeRobot أو خدمة مراقبة)'),
                                  tr3('The installer creates a long secret address. The outside service opens it every 5 minutes and the due jobs run. Reliable, and nothing to install on the host.', 'L\'installateur crée une longue adresse secrète. Le service externe l\'ouvre toutes les 5 minutes et les tâches échues s\'exécutent. Fiable, et rien à installer sur l\'hébergement.', 'ينشئ المثبّت عنوانا سريا طويلا. تفتحه الخدمة الخارجية كل 5 دقائق فتُشغَّل المهام المستحقة. موثوق ولا يلزم تثبيت شيء على الاستضافة.')],
                        'request' => [tr3('Neither: run the jobs while people visit the site', 'Ni l\'un ni l\'autre : lancer les tâches pendant les visites du site', 'لا هذا ولا ذاك: شغّل المهام أثناء زيارات الموقع'),
                                      tr3('Last resort. After a page has been sent, one due job runs, at most once a minute. Nothing runs while nobody visits, so the nightly copy can be late on a quiet site.', 'Dernier recours. Après l\'envoi d\'une page, une tâche échue s\'exécute, au plus une fois par minute. Rien ne tourne sans visite : la copie de nuit peut être en retard sur un site peu visité.', 'الملاذ الأخير. بعد إرسال صفحة تُشغَّل مهمة مستحقة واحدة، مرة في الدقيقة على الأكثر. لا يعمل شيء ما دام لا أحد يزور الموقع، فقد تتأخر نسخة الليل في موقع قليل الزيارة.')],
                     ]],
                ],
            ],
            'review' => [
                'title' => tr3('Review and install', 'Vérifier et installer', 'المراجعة والتثبيت'),
                'lead' => tr3('Check the values below. Pressing Install writes the .env file (mode 600), creates and fills the database, creates the signing key, prepares the page cache and locks this installer. Secrets are never shown.', 'Vérifiez les valeurs ci-dessous. Le bouton Installer écrit le fichier .env (mode 600), crée et remplit la base, crée la clé de signature, prépare le cache des pages et verrouille cet installateur. Les secrets ne sont jamais affichés.', 'راجع القيم أدناه. يكتب زر التثبيت ملف .env (الصلاحية 600) وينشئ قاعدة البيانات ويملؤها وينشئ مفتاح التوقيع ويجهّز ذاكرة الصفحات ويقفل هذا المثبّت. لا تُعرض الأسرار أبدا.'),
                'fields' => [],
            ],
            'done' => [
                'title' => tr3('Finished', 'Terminé', 'اكتمل'),
                'lead' => '',
                'fields' => [],
            ],
        ];
    }
}
