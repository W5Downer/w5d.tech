#!/usr/bin/env python3
"""Writes data/content/pages/for.json + for-{audience}.json (en/fr/ar). Edit here or edit the JSON directly; both are fine."""
import json, os
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
OUT = f'{ROOT}/data/content/pages'
L = lambda en, fr, ar: {'en': en, 'fr': fr, 'ar': ar}
LIMIT = L('A part is an investment in a path, not a job. W5D does not promise employment, returns or resale value.',
          'Une part est un investissement dans un parcours, pas un emploi. W5D ne promet ni emploi, ni rendement, ni valeur de revente.',
          'الحصة مساهمة في مسار وليست وظيفة. لا تَعِد W5D بعمل أو عائد أو قيمة إعادة بيع.')
def cta(label, href, style='ghost', arrow=False):
    d = {'label': label, 'href': href, 'style': style}
    if arrow: d['arrow'] = True
    return d
SIGNIN = L('Sign in', 'Se connecter', 'تسجيل الدخول')
ACC = L('Create an account', 'Créer un compte', 'إنشاء حساب')

A = {}
A['owner'] = dict(icon='crown', grad='peak',
  title=L('For the owner', 'Pour le propriétaire', 'للمالك'), eyebrow=L('Owner', 'Propriétaire', 'المالك'),
  h=L('Run W5D from **one console.**', 'Pilotez W5D depuis **une seule console.**', 'أدِر W5D من **لوحة واحدة.**'),
  lead=L('The owner signs in with the credentials set in the server environment, sees every desk, every pledge and every payment, and decides what is verified.',
         'Le propriétaire se connecte avec les identifiants définis dans l’environnement du serveur, voit chaque bureau, chaque engagement et chaque paiement, et décide ce qui est vérifié.',
         'يدخل المالك بالبيانات المعرّفة في بيئة الخادم، ويرى كل مكتب وكل التزام وكل دفعة، ويقرر ما يتم التحقق منه.'),
  primary=cta(L('Open the owner sign-in', 'Ouvrir la connexion propriétaire', 'افتح دخول المالك'), '/account/login/', 'peak', True),
  second=cta(L('Read the declared limits', 'Lire les limites déclarées', 'اقرأ الحدود المعلنة'), '/trust/statement/'),
  cards=[('layout-dashboard', L('One dashboard', 'Un tableau de bord', 'لوحة واحدة'), L('Pledges, payments, applicants, managers, messages and the mail outbox in one place.', 'Engagements, paiements, candidats, managers, messages et boîte d’envoi au même endroit.', 'الالتزامات والدفعات والمتقدمون والمديرون والرسائل وصندوق البريد في مكان واحد.')),
         ('smartphone', L('Verify D17 payments by hand', 'Vérifier les paiements D17 à la main', 'تحقق من دفعات D17 يدويا'), L('Every D17 transfer waits for an explicit human check before a pledge is confirmed.', 'Chaque virement D17 attend une vérification humaine explicite avant la confirmation.', 'تنتظر كل حوالة D17 تحققا بشريا صريحا قبل تأكيد الالتزام.')),
         ('shield-check', L('Control access', 'Contrôler les accès', 'التحكم في الصلاحيات'), L('Roles and permissions are declared in one file. Nothing is hidden in code paths.', 'Les rôles et permissions sont déclarés dans un seul fichier.', 'تُعرَّف الأدوار والصلاحيات في ملف واحد.'))],
  steps=[(L('Sign in', 'Se connecter', 'سجّل الدخول'), L('Use the owner credentials from the server environment.', 'Utilisez les identifiants propriétaire de l’environnement du serveur.', 'استخدم بيانات المالك من بيئة الخادم.')),
         (L('Review the queue', 'Relire la file', 'راجع القائمة'), L('Payments to verify, applications to read, messages to answer.', 'Paiements à vérifier, candidatures à lire, messages à traiter.', 'دفعات للتحقق وطلبات للقراءة ورسائل للرد.')),
         (L('Publish the facts', 'Publier les faits', 'انشر الوقائع'), L('Update dated figures and corrections; nothing is promised before evidence.', 'Mettez à jour les chiffres datés et les corrections ; rien n’est promis avant preuve.', 'حدّث الأرقام المؤرخة والتصحيحات؛ لا وعود قبل الدليل.'))],
  final=L('Your console is one sign-in away.', 'Votre console est à une connexion.', 'لوحتك على بعد تسجيل دخول واحد.'),
  faq=[(L('Where are the owner credentials set?', 'Où sont définis les identifiants propriétaire ?', 'أين تُعرَّف بيانات المالك؟'), L('In the server environment file, never in the website content. The password is stored as a hash.', 'Dans le fichier d’environnement du serveur, jamais dans le contenu du site. Le mot de passe est stocké haché.', 'في ملف بيئة الخادم وليس في محتوى الموقع. تُخزَّن كلمة المرور مجزأة.'))])
A['admin'] = dict(icon='shield-check', grad='focus',
  title=L('For administrators', 'Pour les administrateurs', 'للمسؤولين'), eyebrow=L('Admin', 'Administrateur', 'المسؤول'),
  h=L('Keep the programme **honest and moving.**', 'Gardez le programme **honnête et en mouvement.**', 'حافظ على البرنامج **صادقا وفاعلا.**'),
  lead=L('Administrators are appointed by the owner. They verify payments, read applications, answer desks and keep the dated facts accurate.',
         'Les administrateurs sont nommés par le propriétaire. Ils vérifient les paiements, lisent les candidatures, répondent aux bureaux et maintiennent l’exactitude des faits datés.',
         'يعيّن المالك المسؤولين. يتحققون من الدفعات ويقرؤون الطلبات ويردّون على المكاتب ويبقون الوقائع المؤرخة دقيقة.'),
  primary=cta(SIGNIN, '/account/login/', 'peak', True),
  second=cta(L('Developer & API docs', 'Documentation développeurs et API', 'وثائق المطورين وواجهة API'), '/developers/'),
  cards=[('smartphone', L('Payments queue', 'File des paiements', 'قائمة الدفعات'), L('Check the D17 transaction reference, then verify or reject with a note. The investor is notified by e-mail.', 'Vérifiez la référence de transaction D17, puis validez ou rejetez avec une note. L’investisseur est notifié par e-mail.', 'تحقق من مرجع عملية D17 ثم أكّد أو ارفض مع ملاحظة. يصل إشعار بالبريد للمستثمر.')),
         ('badge-check', L('Applications', 'Candidatures', 'الطلبات'), L('Applications are read by people. Ranking only sets the reading order; nothing is rejected automatically.', 'Les candidatures sont lues par des personnes. Le classement fixe seulement l’ordre de lecture ; rien n’est rejeté automatiquement.', 'يقرأ الطلبات أشخاص. الترتيب لتحديد أولوية القراءة فقط ولا رفض آلي.')),
         ('mail', L('Desks & mail', 'Bureaux et courriers', 'المكاتب والبريد'), L('Messages arrive by desk. The outbox shows what was sent, logged or failed.', 'Les messages arrivent par bureau. La boîte d’envoi montre ce qui est envoyé, journalisé ou en échec.', 'تصل الرسائل حسب المكتب. يعرض الصندوق ما أُرسل أو سُجّل أو فشل.'))],
  steps=[(L('Sign in', 'Se connecter', 'سجّل الدخول'), L('Your administrator account is created by the owner.', 'Votre compte administrateur est créé par le propriétaire.', 'ينشئ المالك حساب المسؤول.')),
         (L('Work the queues', 'Traiter les files', 'عالج القوائم'), L('Payments first, then applications, then messages.', 'D’abord les paiements, puis les candidatures, puis les messages.', 'الدفعات أولا ثم الطلبات ثم الرسائل.')),
         (L('Leave a trace', 'Laisser une trace', 'اترك أثرا'), L('Every decision carries a note and a name.', 'Chaque décision porte une note et un nom.', 'كل قرار يحمل ملاحظة واسما.'))],
  final=L('Open the admin portal.', 'Ouvrez le portail d’administration.', 'افتح بوابة الإدارة.'),
  faq=[(L('Can an administrator change prices?', 'Un administrateur peut-il changer les prix ?', 'هل يمكن للمسؤول تغيير الأسعار؟'), L('No. The price comes only from the published pricing function and its configuration.', 'Non. Le prix provient uniquement de la fonction de prix publiée et de sa configuration.', 'لا. يأتي السعر فقط من دالة التسعير المنشورة وإعداداتها.'))])
A['leader'] = dict(icon='compass', grad='technology',
  title=L('For team leaders', 'Pour les chefs d’équipe', 'لقادة الفرق'), eyebrow=L('Leader', 'Leader', 'القائد'),
  h=L('Lead a macro work. **Read, review, certify.**', 'Dirigez une macro-œuvre. **Lire, relire, certifier.**', 'قُد عملا كبيرا. **اقرأ وراجع واعتمد.**'),
  lead=L('Leaders read the weekly work of experts in one macro work, review a fixed {{ quality.sampling_percent }}% random sample, and report what they found.',
         'Les leaders lisent le travail hebdomadaire des experts d’une macro-œuvre, relisent un échantillon aléatoire fixe de {{ quality.sampling_percent }} % et rapportent ce qu’ils trouvent.',
         'يقرأ القادة العمل الأسبوعي للخبراء في عمل كبير واحد، ويراجعون عينة عشوائية ثابتة نسبتها {{ quality.sampling_percent }}٪ ويبلّغون بما وجدوه.'),
  primary=cta(L('Apply as a manager', 'Postuler comme manager', 'قدّم كمدير'), '/careers/managers/', 'peak', True),
  second=cta(SIGNIN, '/account/login/'),
  cards=[('graduation-cap', L('Applications read by people', 'Candidatures lues par des personnes', 'طلبات يقرؤها أشخاص'), L('Your application is read in full. Ranking only sets the reading order; no automatic rejection.', 'Votre candidature est lue en entier. Le classement fixe seulement l’ordre de lecture ; aucun rejet automatique.', 'يُقرأ طلبك كاملا. الترتيب لأولوية القراءة فقط ولا رفض آلي.')),
         ('scale', L('A fixed audit rule', 'Une règle d’audit fixe', 'قاعدة تدقيق ثابتة'), L('The sample is {{ quality.sampling_percent }}% and random. It is not tuned per person.', 'L’échantillon est de {{ quality.sampling_percent }} % et aléatoire. Il n’est pas ajusté par personne.', 'العينة {{ quality.sampling_percent }}٪ وعشوائية. لا تُضبط حسب الشخص.')),
         ('briefcase', L('A role, not a promise', 'Un rôle, pas une promesse', 'دور وليس وعدا'), L('Management is a responsibility inside the programme. It is not an employment contract.', 'Manager est une responsabilité dans le programme, pas un contrat de travail.', 'الإدارة مسؤولية داخل البرنامج وليست عقد عمل.'))],
  steps=[(L('Apply', 'Postuler', 'قدّم'), L('Fill the manager form with your largest team led and a scenario answer.', 'Remplissez le formulaire manager avec votre plus grande équipe dirigée et une réponse de mise en situation.', 'املأ نموذج المدير مع أكبر فريق قدته وإجابة سيناريو.')),
         (L('Be read', 'Être lu', 'يُقرأ طلبك'), L('A human reader reviews your file and may write back.', 'Un lecteur humain examine votre dossier et peut vous répondre.', 'يراجع قارئ بشري ملفك وقد يراسلك.')),
         (L('Start with a sample', 'Commencer par un échantillon', 'ابدأ بعينة'), L('You review a small, dated sample before anything else.', 'Vous relisez d’abord un petit échantillon daté.', 'تراجع عينة صغيرة مؤرخة قبل أي شيء.'))],
  final=L('Lead with evidence.', 'Dirigez avec des preuves.', 'قُد بالأدلة.'),
  faq=[(L('Is this a job offer?', 'Est-ce une offre d’emploi ?', 'هل هذا عرض عمل؟'), L('No. A part is an investment in a path, not a job. W5D does not promise employment.', 'Non. Une part est un investissement dans un parcours, pas un emploi.', 'لا. الحصة مساهمة في مسار وليست وظيفة.'))])
A['member'] = dict(icon='users', grad='cultures',
  title=L('For members', 'Pour les membres', 'للأعضاء'), eyebrow=L('Member', 'Membre', 'العضو'),
  h=L('Certify first. **Then deliver weekly.**', 'Certifiez d’abord. **Livrez ensuite chaque semaine.**', 'اعتمد أولا. **ثم سلّم أسبوعيا.**'),
  lead=L('Members are graduates who registered, completed certification tasks and deliver a reviewable weekly unit in one of the {{ counts.services }} services.',
         'Les membres sont des diplômés inscrits qui ont accompli les tâches de certification et livrent une unité hebdomadaire vérifiable dans l’un des {{ counts.services }} services.',
         'الأعضاء خريجون سجّلوا وأكملوا مهام الاعتماد ويسلّمون وحدة أسبوعية قابلة للمراجعة في إحدى الخدمات الـ{{ counts.services }}.'),
  primary=cta(L('Check my eligibility', 'Vérifier mon admissibilité', 'تحقق من أهليتي'), '/join/eligibility/', 'peak', True),
  second=cta(L('Register now', 'S’inscrire', 'سجّل الآن'), '/app/register/'),
  cards=[('search', L('Find your service', 'Trouvez votre service', 'اعثر على خدمتك'), L('Diploma level and field give a short list of fitting services.', 'Le niveau et le domaine du diplôme donnent une courte liste de services adaptés.', 'يحدد مستوى الدبلوم ومجاله قائمة قصيرة من الخدمات.')),
         ('badge-check', L('Certification tasks', 'Tâches de certification', 'مهام الاعتماد'), L('Registration starts certification. It is not a job offer.', 'L’inscription lance la certification. Ce n’est pas une offre d’emploi.', 'يبدأ التسجيل الاعتماد وهو ليس عرض عمل.')),
         ('bell', L('Follow your file', 'Suivez votre dossier', 'تابع ملفك'), L('Your account shows each application status and notifies you of every change.', 'Votre compte affiche l’état de chaque candidature et vous notifie de chaque changement.', 'يعرض حسابك حالة كل طلب ويُعلمك بكل تغيير.'))],
  steps=[(L('Check', 'Vérifier', 'تحقق'), L('Thirty seconds to a short list of services.', 'Trente secondes pour une courte liste de services.', 'ثلاثون ثانية لقائمة خدمات قصيرة.')),
         (L('Register', 'S’inscrire', 'سجّل'), L('Open your applicant file and confirm your e-mail.', 'Ouvrez votre dossier de candidat et confirmez votre e-mail.', 'افتح ملف المتقدم وأكّد بريدك.')),
         (L('Certify, then deliver', 'Certifier puis livrer', 'اعتمد ثم سلّم'), L('Complete the tasks, then deliver weekly, reviewed by a leader.', 'Accomplissez les tâches, puis livrez chaque semaine, relu par un leader.', 'أكمل المهام ثم سلّم أسبوعيا بمراجعة قائد.'))],
  final=L('Start with a thirty-second check.', 'Commencez par une vérification de trente secondes.', 'ابدأ بفحص مدته ثلاثون ثانية.'),
  faq=[(L('How many experts are certified today?', 'Combien d’experts sont certifiés aujourd’hui ?', 'كم خبيرا معتمدا اليوم؟'), L('{{ d.experts_current }}. The figure {{ d.experts_target }} is a design equation, not a headcount.', '{{ d.experts_current }}. Le chiffre {{ d.experts_target }} est une équation de conception, pas un effectif.', '{{ d.experts_current }}. الرقم {{ d.experts_target }} معادلة تصميم وليس عدد موظفين.'))])
A['investor'] = dict(icon='coins', grad='peak',
  title=L('For investors', 'Pour les investisseurs', 'للمستثمرين'), eyebrow=L('Investor', 'Investisseur', 'المستثمر'),
  h=L('A part every day. **A path, not a promise.**', 'Une part chaque jour. **Un parcours, pas une promesse.**', 'حصة كل يوم. **مسار لا وعد.**'),
  lead=L('Today one part costs {{ price.day }} {{ money.currency }}. The price comes from a public function, so you can check it before you commit. No return is promised.',
         'Aujourd’hui une part coûte {{ price.day }} {{ money.currency }}. Le prix provient d’une fonction publique : vous pouvez le vérifier avant de vous engager. Aucun rendement n’est promis.',
         'تكلفة الحصة اليوم {{ price.day }} {{ money.currency }}. يأتي السعر من دالة عامة فيمكنك التحقق منه قبل الالتزام. لا عائد موعود.'),
  primary=cta(L('Take a part today', 'Prendre une part', 'اشتر حصة اليوم'), '/checkout/', 'peak', True),
  second=cta(L('Read the investor dossier', 'Lire le dossier investisseur', 'اقرأ ملف المستثمر'), '/investors/'),
  cards=[('line-chart', L('A public price', 'Un prix public', 'سعر عام'), L('The formula, the cap and today’s value are published. Compare before you pledge.', 'La formule, le plafond et la valeur du jour sont publiés. Comparez avant de vous engager.', 'الصيغة والسقف وقيمة اليوم منشورة. قارن قبل الالتزام.')),
         ('smartphone', L('Pay with D17 in Tunisia', 'Payer avec D17 en Tunisie', 'ادفع عبر D17 في تونس'), L('Send the D17 transfer, enter the reference, and an administrator verifies it by hand.', 'Envoyez le virement D17, saisissez la référence, un administrateur la vérifie à la main.', 'أرسل حوالة D17 وأدخل المرجع ويتحقق مسؤول يدويا.')),
         ('repeat', L('Daily or one-off', 'Quotidien ou ponctuel', 'يومي أو مرة واحدة'), L('Pledge a schedule of daily parts, or take a single part. You can manage it with your key.', 'Engagez un calendrier de parts quotidiennes, ou prenez une seule part. Gérez-le avec votre clé.', 'التزم بجدول حصص يومية أو بحصة واحدة وأدِره بمفتاحك.'))],
  steps=[(L('Verify', 'Vérifier', 'تحقق'), L('Read the declared limits and today’s price.', 'Lisez les limites déclarées et le prix du jour.', 'اقرأ الحدود المعلنة وسعر اليوم.')),
         (L('Pledge', 'S’engager', 'التزم'), L('Choose parts per day and number of days; see the full schedule.', 'Choisissez les parts par jour et le nombre de jours ; voyez le calendrier complet.', 'اختر الحصص يوميا وعدد الأيام وشاهد الجدول كاملا.')),
         (L('Pay and follow', 'Payer et suivre', 'ادفع وتابع'), L('Pay, then follow status and receipts in your account.', 'Payez, puis suivez l’état et les reçus dans votre compte.', 'ادفع ثم تابع الحالة والإيصالات في حسابك.'))],
  final=L('Take today’s part.', 'Prenez la part du jour.', 'اشتر حصة اليوم.'),
  faq=[(L('Is a return promised?', 'Un rendement est-il promis ?', 'هل العائد موعود؟'), L('No. No return, dividend or resale value is promised. Parts fund a published programme.', 'Non. Aucun rendement, dividende ni valeur de revente n’est promis. Les parts financent un programme publié.', 'لا. لا عائد ولا أرباح ولا قيمة إعادة بيع. تموّل الحصص برنامجا منشورا.'))])
A['customer'] = dict(icon='handshake', grad='built',
  title=L('For customers', 'Pour les clients', 'للعملاء'), eyebrow=L('Customer', 'Client', 'العميل'),
  h=L('Order reviewable work. **Weekly, certified.**', 'Commandez un travail vérifiable. **Hebdomadaire, certifié.**', 'اطلب عملا قابلا للمراجعة. **أسبوعيا ومعتمدا.**'),
  lead=L('Organisations can ask for one of the {{ counts.services }} services. Work arrives as a weekly package, checked by a leader and a fixed {{ quality.sampling_percent }}% sample.',
         'Les organisations peuvent demander l’un des {{ counts.services }} services. Le travail arrive en lot hebdomadaire, contrôlé par un leader et un échantillon fixe de {{ quality.sampling_percent }} %.',
         'يمكن للمؤسسات طلب إحدى الخدمات الـ{{ counts.services }}. يصل العمل في حزمة أسبوعية يراجعها قائد وعينة ثابتة نسبتها {{ quality.sampling_percent }}٪.'),
  primary=cta(L('Write to the partners desk', 'Écrire au bureau partenaires', 'اكتب لمكتب الشركاء'), '/contact/?desk=partners', 'peak', True),
  second=cta(L('Browse the services', 'Parcourir les services', 'تصفح الخدمات'), '/services/'),
  cards=[('layers', L('Five macro works', 'Cinq macro-œuvres', 'خمسة أعمال كبرى'), L('Built environment, cultures, governance, sciences and technology.', 'Cadre bâti, cultures, gouvernance, sciences et technologie.', 'البيئة المبنية والثقافات والحوكمة والعلوم والتقنية.')),
         ('scale', L('Honest scope', 'Périmètre honnête', 'نطاق صادق'), L('We answer with a public source when one exists, and say plainly what we cannot do.', 'Nous répondons avec une source publique quand elle existe et disons clairement ce que nous ne pouvons pas faire.', 'نجيب بمصدر عام عند وجوده ونقول بوضوح ما لا نستطيعه.')),
         ('mail', L('A real desk', 'Un vrai bureau', 'مكتب حقيقي'), L('Your message reaches a named desk and you get a reference by e-mail.', 'Votre message arrive à un bureau nommé et vous recevez une référence par e-mail.', 'تصل رسالتك إلى مكتب محدد وتحصل على مرجع بالبريد.'))],
  steps=[(L('Describe the need', 'Décrire le besoin', 'صف الحاجة'), L('Tell the partners desk which service or domain you need.', 'Indiquez au bureau partenaires le service ou le domaine voulu.', 'أخبر مكتب الشركاء بالخدمة أو المجال.')),
         (L('Get a scoped answer', 'Recevoir une réponse cadrée', 'احصل على رد محدد'), L('A person answers with scope, calendar and limits.', 'Une personne répond avec périmètre, calendrier et limites.', 'يرد شخص بالنطاق والجدول والحدود.')),
         (L('Receive weekly packages', 'Recevoir des lots hebdomadaires', 'استلم حزما أسبوعية'), L('Each package is reviewed before it reaches you.', 'Chaque lot est relu avant de vous parvenir.', 'تُراجع كل حزمة قبل وصولها إليك.'))],
  final=L('Tell us what you need built.', 'Dites-nous ce que vous voulez construire.', 'أخبرنا بما تريد بناءه.'),
  faq=[(L('Is there a fixed price list?', 'Existe-t-il une grille de prix fixe ?', 'هل توجد قائمة أسعار ثابتة؟'), L('Customer scope is agreed in writing with the partners desk. Nothing is quoted on this page.', 'Le périmètre client est convenu par écrit avec le bureau partenaires. Rien n’est chiffré sur cette page.', 'يُتفق على نطاق العميل كتابيا مع مكتب الشركاء. لا تسعير في هذه الصفحة.'))])
A['user'] = dict(icon='user-round', grad='sciences',
  title=L('For visitors and users', 'Pour les visiteurs et utilisateurs', 'للزوار والمستخدمين'), eyebrow=L('User', 'Utilisateur', 'المستخدم'),
  h=L('New here? **Start with the facts.**', 'Nouveau ici ? **Commencez par les faits.**', 'جديد هنا؟ **ابدأ بالوقائع.**'),
  lead=L('W5D funds certified paths across five macro works. Look around freely; create an account when you want to follow a pledge, an application or a payment.',
         'W5D finance des parcours certifiés dans cinq macro-œuvres. Explorez librement ; créez un compte quand vous voulez suivre un engagement, une candidature ou un paiement.',
         'تموّل W5D مسارات معتمدة عبر خمسة أعمال كبرى. تصفّح بحرية، وأنشئ حسابا عندما تريد متابعة التزام أو طلب أو دفعة.'),
  primary=cta(ACC, '/account/register/', 'peak', True),
  second=cta(L('How it works', 'Comment ça marche', 'كيف يعمل'), '/how-it-works/'),
  cards=[('book-open', L('Understand', 'Comprendre', 'افهم'), L('How a part becomes a certified path, in plain words.', 'Comment une part devient un parcours certifié, en mots simples.', 'كيف تصبح الحصة مسارا معتمدا بكلمات بسيطة.')),
         ('user-round', L('One account, three roles', 'Un compte, trois rôles', 'حساب واحد وثلاثة أدوار'), L('Choose investor, customer or learner when you register. You can follow everything in one dashboard.', 'Choisissez investisseur, client ou apprenant à l’inscription. Tout se suit dans un seul tableau de bord.', 'اختر مستثمرا أو عميلا أو متعلما عند التسجيل وتابع كل شيء في لوحة واحدة.')),
         ('shield-check', L('Your data, your choice', 'Vos données, votre choix', 'بياناتك واختيارك'), L('Sign in with e-mail or a social account, and unsubscribe from non-essential mail at any time.', 'Connectez-vous par e-mail ou compte social, et désinscrivez-vous des e-mails non essentiels à tout moment.', 'سجّل الدخول بالبريد أو حساب اجتماعي وألغِ الاشتراك في الرسائل غير الأساسية متى شئت.'))],
  steps=[(L('Look around', 'Explorer', 'تجوّل'), L('Read how it works and the declared limits.', 'Lisez le fonctionnement et les limites déclarées.', 'اقرأ طريقة العمل والحدود المعلنة.')),
         (L('Create an account', 'Créer un compte', 'أنشئ حسابا'), L('Confirm your e-mail to activate it.', 'Confirmez votre e-mail pour l’activer.', 'أكّد بريدك لتفعيله.')),
         (L('Pick your door', 'Choisir sa porte', 'اختر بابك'), L('Invest, apply, or order work, whichever fits you.', 'Investir, candidater ou commander du travail, selon votre cas.', 'استثمر أو قدّم أو اطلب عملا حسب حالتك.'))],
  final=L('Create your free account.', 'Créez votre compte gratuit.', 'أنشئ حسابك المجاني.'),
  faq=[(L('Does an account cost anything?', 'Un compte est-il payant ?', 'هل الحساب مدفوع؟'), L('No. Creating an account is free. Taking a part is a separate, explicit step.', 'Non. Créer un compte est gratuit. Prendre une part est une étape distincte et explicite.', 'لا. إنشاء الحساب مجاني. شراء حصة خطوة منفصلة وصريحة.'))])

def page(k, a):
    secs = [
      {'type': 'hero', 'id': f'for-{k}-hero', 'variant': 'classic', 'gradient': a['grad'], 'eyebrow': a['eyebrow'], 'title': a['h'], 'lead': a['lead'],
       'ctas': [a['primary'], a['second']], 'note': LIMIT},
      {'type': 'cards', 'id': f'for-{k}-cards', 'tier': 'i', 'focus': 3, 'cols': 3, 'title': L('What you can do here', 'Ce que vous pouvez faire ici', 'ما يمكنك فعله هنا'),
       'items': [{'icon': i, 'title': t, 'body': b} for i, t, b in a['cards']]},
      {'type': 'steps', 'id': f'for-{k}-steps', 'tier': 'i', 'focus': 3, 'title': L('Your first three steps', 'Vos trois premières étapes', 'خطواتك الثلاث الأولى'),
       'items': [{'title': t, 'body': b} for t, b in a['steps']]},
      {'type': 'faq', 'id': f'for-{k}-faq', 'tier': 'i', 'focus': 2, 'title': L('Good to know', 'Bon à savoir', 'من المفيد أن تعرف'), 'items': [{'q': q, 'a': an} for q, an in a['faq']]},
      {'type': 'notice', 'id': f'for-{k}-limits', 'tier': 'i', 'focus': 2},
      {'type': 'cta', 'id': f'for-{k}-final', 'gradient': a['grad'], 'title': a['final'], 'body': LIMIT,
       'ctas': [dict(a['primary'], style='peak')]},
    ]
    return {'slug': f'for-{k}', 'title': L(a['title']['en'] + ' — W5D', a['title']['fr'] + ' — W5D', a['title']['ar'] + ' — W5D'),
            'description': a['lead'], 'sections': secs}
for k, a in A.items():
    json.dump(page(k, a), open(f'{OUT}/for-{k}.json', 'w', encoding='utf8'), ensure_ascii=False, indent=2)

order = ['investor', 'customer', 'member', 'leader', 'admin', 'owner', 'user']
idx = {'slug': 'for', 'title': L('Choose your door — W5D', 'Choisissez votre porte — W5D', 'اختر بابك — W5D'),
  'description': L('Seven audiences, seven homepages: owner, admin, leader, member, investor, customer and user. Each one shows the right next step.',
                   'Sept publics, sept pages d’accueil : propriétaire, admin, leader, membre, investisseur, client et utilisateur. Chacune montre la bonne prochaine étape.',
                   'سبعة جماهير وسبع صفحات رئيسية: المالك والمسؤول والقائد والعضو والمستثمر والعميل والمستخدم. تعرض كل صفحة الخطوة التالية المناسبة.'),
  'sections': [
    {'type': 'hero', 'id': 'for-hero', 'variant': 'editorial', 'gradient': 'peak', 'eyebrow': L('Audiences', 'Publics', 'الجماهير'),
     'title': L('One W5D. **Seven doors.**', 'Un seul W5D. **Sept portes.**', 'W5D واحدة. **سبعة أبواب.**'),
     'lead': L('Pick the door that matches why you are here. Every page ends with one clear next step.', 'Choisissez la porte qui correspond à votre raison de venir. Chaque page se termine par une prochaine étape claire.', 'اختر الباب الذي يناسب سبب زيارتك. تنتهي كل صفحة بخطوة تالية واضحة.'),
     'ctas': [cta(L('Take a part today', 'Prendre une part', 'اشتر حصة اليوم'), '/checkout/', 'peak', True), cta(SIGNIN, '/account/login/')], 'note': LIMIT},
    {'type': 'cards', 'id': 'for-cards', 'tier': 'e', 'focus': 3, 'cols': 3, 'title': L('Who are you?', 'Qui êtes-vous ?', 'من أنت؟'),
     'items': [{'icon': A[k]['icon'], 'title': A[k]['title'], 'body': A[k]['lead'], 'href': f'/for/{k}/', 'cta': L('Open', 'Ouvrir', 'افتح'), 'gradient': A[k]['grad'] if A[k]['grad'] in ('cultures', 'built', 'sciences', 'governance', 'technology') else 'technology'} for k in order]},
    {'type': 'notice', 'id': 'for-limits', 'tier': 'i', 'focus': 2},
    {'type': 'cta', 'id': 'for-final', 'gradient': 'peak', 'title': L('Not sure? Start with the facts.', 'Pas sûr ? Commencez par les faits.', 'غير متأكد؟ ابدأ بالوقائع.'),
     'body': LIMIT, 'ctas': [cta(L('How it works', 'Comment ça marche', 'كيف يعمل'), '/how-it-works/', 'peak', True)]},
  ]}
json.dump(idx, open(f'{OUT}/for.json', 'w', encoding='utf8'), ensure_ascii=False, indent=2)
print('ok', len(A) + 1)
