#!/usr/bin/env python3
"""Adds explanatory sections (en/fr/ar) to thin pages. Idempotent: a section id already present is skipped.
Numbers only through {{ tokens }}. Honest tone: a path, not a job; no promised return."""
import json, os
ROOT = os.path.join(os.path.dirname(__file__), '..', '..')
def L(en, fr, ar): return {'en': en, 'fr': fr, 'ar': ar}
def prose(id, title, paras, tier='e', focus=3): return {'type': 'prose', 'id': id, 'tier': tier, 'focus': focus, 'title': title, 'paragraphs': paras}
def faq(id, title, items, tier='e', focus=2): return {'type': 'faq', 'id': id, 'tier': tier, 'focus': focus, 'title': title, 'items': [{'q': q, 'a': a} for q, a in items]}
def steps(id, title, items, tier='i', focus=3): return {'type': 'steps', 'id': id, 'tier': tier, 'focus': focus, 'title': title, 'items': [{'title': t, 'body': b} for t, b in items]}
def defs(id, title, items, tier='x', focus=2): return {'type': 'definitions', 'id': id, 'tier': tier, 'focus': focus, 'title': title, 'items': [{'term': t, 'def': d} for t, d in items]}

ADD = {}

ADD['checkout-done'] = [
 steps('done-next', L('What happens next', 'Et maintenant', 'ما الذي يحدث بعد ذلك'), [
  (L('Keep the reference', 'Conservez la référence', 'احتفظ بالمرجع'), L('The reference and the schedule are your proof. Your account shows them under Orders at any time.', 'La référence et le calendrier sont votre preuve. Votre compte les affiche à tout moment dans Commandes.', 'المرجع والجدول هما إثباتك. يعرضهما حسابك في الطلبات في أي وقت.')),
  (L('Pay, if payment is open', 'Payez, si le paiement est ouvert', 'ادفع إن كان الدفع مفتوحا'), L('With D17 you send the transfer yourself and enter its reference. A person checks it by hand; until then the status stays awaiting.', 'Avec D17, vous envoyez le virement vous-même et saisissez sa référence. Une personne le vérifie à la main ; d’ici là, le statut reste en attente.', 'مع D17 ترسل التحويل بنفسك وتدخل مرجعه. يتحقق منه شخص يدويا، وحتى ذلك الحين تبقى الحالة قيد الانتظار.')),
  (L('Follow the progress', 'Suivez l’avancement', 'تابع التقدم'), L('Status changes are e-mailed and listed in your notifications. The public status page shows the programme as it really is.', 'Les changements de statut sont envoyés par e-mail et listés dans vos notifications. La page de statut public montre le programme tel qu’il est.', 'تُرسل تغييرات الحالة بالبريد وتُدرج في إشعاراتك. وتعرض صفحة الحالة العامة البرنامج كما هو فعلا.'))]),
 faq('done-faq', L('After your pledge', 'Après votre engagement', 'بعد التزامك'), [
  (L('Can I change the schedule?', 'Puis-je modifier le calendrier ?', 'هل أستطيع تعديل الجدول؟'), L('A daily subscription can be paused, resumed or cancelled with its reference and key. A recorded pledge is not rewritten; place a new one instead.', 'Un abonnement journalier peut être suspendu, repris ou annulé avec sa référence et sa clé. Un engagement enregistré n’est pas réécrit ; faites-en un nouveau.', 'يمكن إيقاف الاشتراك اليومي أو استئنافه أو إلغاؤه بمرجعه ومفتاحه. لا يُعاد كتابة التزام مسجّل؛ قدّم التزاما جديدا.')),
  (L('Is a return promised?', 'Un rendement est-il promis ?', 'هل هناك عائد موعود؟'), L('No. A part is a dated contribution to a path. No return, resale value or post is promised.', 'Non. Une part est une contribution datée à un parcours. Aucun rendement, valeur de revente ni poste n’est promis.', 'لا. الحصة مساهمة مؤرخة في مسار. لا يُوعد بعائد ولا بقيمة إعادة بيع ولا بمنصب.'))])]

ADD['subscription-manage'] = [
 steps('mng-how', L('How to manage a subscription', 'Comment gérer un abonnement', 'كيف تدير اشتراكا'), [
  (L('Find it', 'Retrouvez-le', 'اعثر عليه'), L('Enter the reference (ORD-…) and the management key that was shown once when you created it.', 'Saisissez la référence (ORD-…) et la clé de gestion affichée une seule fois à la création.', 'أدخل المرجع (ORD-…) ومفتاح الإدارة الذي ظهر مرة واحدة عند الإنشاء.')),
  (L('Choose an action', 'Choisissez une action', 'اختر إجراء'), L('Pause, resume or cancel. Cancelling stops future charges and cannot be undone; you can start a new subscription instead.', 'Suspendre, reprendre ou annuler. Annuler arrête les prélèvements futurs et ne peut être défait ; vous pouvez en démarrer un nouveau.', 'إيقاف أو استئناف أو إلغاء. الإلغاء يوقف الخصومات المقبلة ولا يمكن التراجع عنه؛ يمكنك بدء اشتراك جديد.')),
  (L('Lost the key?', 'Clé perdue ?', 'فقدت المفتاح؟'), L('The key is stored only as a hash, so nobody can read it back. Sign in and open Orders, or write to the investors desk with your reference.', 'La clé n’est conservée que sous forme de hachage ; personne ne peut la relire. Connectez-vous et ouvrez Commandes, ou écrivez au bureau investisseurs avec votre référence.', 'يُحفظ المفتاح كبصمة مجزّأة فقط فلا يستطيع أحد قراءته. سجّل الدخول وافتح الطلبات أو راسل مكتب المستثمرين بمرجعك.'))])]

ADD['join-thanks'] = [
 steps('thx-next', L('What happens to your file', 'Ce qui arrive à votre dossier', 'ما يحدث لملفك'), [
  (L('Received', 'Reçu', 'مستلم'), L('Your file is recorded with a date. You get an e-mail and a notification in your account.', 'Votre dossier est enregistré avec une date. Vous recevez un e-mail et une notification dans votre compte.', 'يُسجَّل ملفك بتاريخ. يصلك بريد وإشعار في حسابك.')),
  (L('Checked by a person', 'Vérifié par une personne', 'يراجعه شخص'), L('Identity and diploma are checked by hand. Nothing is automatic and nothing is guaranteed.', 'L’identité et le diplôme sont vérifiés à la main. Rien n’est automatique et rien n’est garanti.', 'تُراجع الهوية والشهادة يدويا. لا شيء تلقائي ولا شيء مضمون.')),
  (L('Practise while you wait', 'Entraînez-vous en attendant', 'تدرّب أثناء الانتظار'), L('Open the QCM panel for free practice rounds on the {{ counts.services }} services. A certificate comes only from a passed graded round.', 'Ouvrez le panneau QCM pour des entraînements gratuits sur les {{ counts.services }} services. Un certificat ne vient que d’une session notée réussie.', 'افتح لوحة QCM لتدريب مجاني على الخدمات الـ{{ counts.services }}. لا تأتي الشهادة إلا من جولة مصحَّحة ناجحة.'))])]

ADD['checkout'] = [
 prose('chk-how', L('Before you commit', 'Avant de vous engager', 'قبل أن تلتزم'), [
  L('The price of a part is a public function of programme progress and time, capped. The wizard shows the unit price for every day of your schedule and the total before you confirm; nothing is hidden behind the last step.', 'Le prix d’une part est une fonction publique de l’avancement du programme et du temps, plafonnée. L’assistant affiche le prix unitaire de chaque jour de votre calendrier et le total avant confirmation ; rien n’est caché derrière la dernière étape.', 'سعر الحصة دالة علنية لتقدم البرنامج والزمن، ومسقوفة. يعرض المعالج سعر الوحدة لكل يوم من جدولك والمجموع قبل التأكيد؛ ولا شيء مخفي خلف الخطوة الأخيرة.'),
  L('You need a signed-in account so that the record belongs to someone. If you are not signed in, the popup keeps you on this step.', 'Un compte connecté est nécessaire pour que l’enregistrement appartienne à quelqu’un. Si vous n’êtes pas connecté, la fenêtre vous garde sur cette étape.', 'تحتاج إلى حساب مسجَّل ليكون السجل لشخص ما. إن لم تكن مسجلا فالنافذة تُبقيك في هذه الخطوة.'),
  L('A part funds certification and delivery capacity. It is not a loan, a share of a company or a job.', 'Une part finance la certification et la capacité de livraison. Ce n’est ni un prêt, ni une part de société, ni un emploi.', 'تموّل الحصة الاعتماد وقدرة التنفيذ. وهي ليست قرضا ولا حصة في شركة ولا وظيفة.')]),
 faq('chk-faq', L('Checkout questions', 'Questions sur la commande', 'أسئلة عن الطلب'), [
  (L('Is money taken when I confirm?', 'De l’argent est-il prélevé à la confirmation ?', 'هل يُخصم مال عند التأكيد؟'), L('Only if payments are open. With D17 you send the transfer yourself and a person verifies it. In demo mode nothing moves.', 'Seulement si les paiements sont ouverts. Avec D17, vous envoyez le virement et une personne le vérifie. En mode démo, rien ne bouge.', 'فقط إن كان الدفع مفتوحا. مع D17 ترسل التحويل ويتحقق منه شخص. في الوضع التجريبي لا يتحرك شيء.')),
  (L('Can I take a part every day?', 'Puis-je prendre une part chaque jour ?', 'هل أستطيع أخذ حصة كل يوم؟'), L('Yes, with the daily subscription. You can pause or cancel it with its reference and key.', 'Oui, avec l’abonnement journalier. Vous pouvez le suspendre ou l’annuler avec sa référence et sa clé.', 'نعم، بالاشتراك اليومي. يمكنك إيقافه أو إلغاؤه بمرجعه ومفتاحه.'))])]

ADD['newsletter'] = [
 prose('nl-promise', L('What we send, and what we never do', 'Ce que nous envoyons, et ce que nous ne faisons jamais', 'ما نرسله وما لا نفعله أبدا'), [
  L('Dated updates only: a status change, a published correction, a new level of the programme. No countdowns, no pressure, no daily noise.', 'Seulement des mises à jour datées : un changement de statut, une correction publiée, un nouveau niveau du programme. Pas de compte à rebours, pas de pression, pas de bruit quotidien.', 'تحديثات مؤرخة فقط: تغيّر حالة أو تصحيح منشور أو مستوى جديد للبرنامج. لا عدّ تنازلي ولا ضغط ولا ضجيج يومي.'),
  L('Every message carries a one-click unsubscribe link. Your e-mail is not sold or shared, and a person who unsubscribes stays unsubscribed.', 'Chaque message contient un lien de désabonnement en un clic. Votre e-mail n’est ni vendu ni partagé, et une personne désabonnée le reste.', 'تحمل كل رسالة رابط إلغاء اشتراك بنقرة واحدة. لا يُباع بريدك ولا يُشارك، ومن يلغي اشتراكه يبقى ملغيا.')])]

ADD['trust-corrections'] = [
 steps('co-how', L('How a correction is handled', 'Comment une correction est traitée', 'كيف تُعالج التصحيحات'), [
  (L('You report', 'Vous signalez', 'تبلّغ'), L('Name the page, quote the sentence and say what the evidence is. The form takes a minute.', 'Indiquez la page, citez la phrase et dites quelle est la preuve. Le formulaire prend une minute.', 'اذكر الصفحة واقتبس الجملة وبيّن الدليل. يستغرق النموذج دقيقة.')),
  (L('We check', 'Nous vérifions', 'نتحقق'), L('A person compares the claim with its source. If we are wrong, we say so.', 'Une personne compare l’affirmation à sa source. Si nous avons tort, nous le disons.', 'يقارن شخص الادعاء بمصدره. إن كنا مخطئين نقول ذلك.')),
  (L('We publish', 'Nous publions', 'ننشر'), L('The fix goes live with its date, and the reporter gets an answer within 14 days.', 'La correction est publiée avec sa date, et la personne reçoit une réponse sous 14 jours.', 'يُنشر التصحيح بتاريخه ويصل المبلّغ ردّ خلال 14 يوما.'))])]

ADD['join-eligibility'] = [
 prose('elig-what', L('What this check is, and is not', 'Ce qu’est ce contrôle, et ce qu’il n’est pas', 'ما هذا الفحص وما ليس هو'), [
  L('A thirty-second, indicative match between your diploma and field and the {{ counts.services }} services. It lists where your profile could fit; it does not admit or refuse anyone.', 'Une correspondance indicative en trente secondes entre votre diplôme et votre domaine et les {{ counts.services }} services. Elle liste où votre profil pourrait s’inscrire ; elle n’admet ni ne refuse personne.', 'مطابقة استرشادية في ثلاثين ثانية بين شهادتك ومجالك والخدمات الـ{{ counts.services }}. تُعدّد أين قد يناسب ملفك؛ ولا تقبل أحدا ولا ترفضه.'),
  L('Nothing you type here is stored against your name unless you then open an applicant file. A person confirms every match during verification.', 'Rien de ce que vous saisissez ici n’est enregistré à votre nom, sauf si vous ouvrez ensuite un dossier de candidat. Une personne confirme chaque correspondance lors de la vérification.', 'لا يُحفظ شيء مما تكتبه هنا باسمك ما لم تفتح لاحقا ملف مترشح. يؤكد شخص كل مطابقة أثناء التحقق.')])]

ADD['trust-security'] = [
 prose('sc-report', L('Reporting a vulnerability', 'Signaler une vulnérabilité', 'الإبلاغ عن ثغرة'), [
  L('Write to the security contact published in security.txt. Tell us what you found, how to reproduce it and what it could expose. Do not access other people’s data, and do not run denial-of-service tests.', 'Écrivez au contact sécurité publié dans security.txt. Dites-nous ce que vous avez trouvé, comment le reproduire et ce que cela pourrait exposer. N’accédez pas aux données d’autrui et ne lancez pas de tests de déni de service.', 'راسل جهة الأمان المنشورة في security.txt. أخبرنا بما وجدت وكيف تعيد إنتاجه وما قد يكشفه. لا تصل إلى بيانات الآخرين ولا تجرِ اختبارات حجب الخدمة.'),
  L('We acknowledge a report, fix what is real and credit the reporter if they wish. We do not threaten good-faith researchers.', 'Nous accusons réception, corrigeons ce qui est réel et citons la personne si elle le souhaite. Nous ne menaçons pas les chercheurs de bonne foi.', 'نؤكد استلام البلاغ ونصلح ما هو حقيقي وننسب الفضل للمبلّغ إن رغب. ولا نهدد الباحثين حسني النية.')])]

ADD['app-register'] = [
 steps('reg-next', L('After you register', 'Après l’inscription', 'بعد التسجيل'), [
  (L('Verification', 'Vérification', 'التحقق'), L('A person checks identity and diploma. You are told by e-mail and in your notifications.', 'Une personne vérifie l’identité et le diplôme. Vous êtes informé par e-mail et dans vos notifications.', 'يتحقق شخص من الهوية والشهادة. تُبلَّغ بالبريد وفي إشعاراتك.')),
  (L('Practice', 'Entraînement', 'التدريب'), L('Free QCM practice rounds are open from your account straight away.', 'Les entraînements QCM gratuits sont ouverts depuis votre compte dès maintenant.', 'جولات تدريب QCM المجانية مفتوحة من حسابك فورا.')),
  (L('Certification', 'Certification', 'الاعتماد'), L('A graded round can issue a certificate when you meet the requirements set for your level.', 'Une session notée peut délivrer un certificat lorsque vous remplissez les conditions de votre niveau.', 'قد تُصدر جولة مصحَّحة شهادة حين تستوفي شروط مستواك.'))]),
 faq('reg-faq', L('Applicant questions', 'Questions des candidats', 'أسئلة المترشحين'), [
  (L('Is registering a job offer?', 'S’inscrire est-il une offre d’emploi ?', 'هل التسجيل عرض وظيفة؟'), L('No. It opens a path to certification. Work follows certified headcount and funded capacity.', 'Non. Cela ouvre un parcours vers la certification. Le travail dépend des effectifs certifiés et des capacités financées.', 'لا. يفتح مسارا نحو الاعتماد. والعمل يتبع العدد المعتمد والقدرة الممولة.')),
  (L('Do I pay to register?', 'Dois-je payer pour m’inscrire ?', 'هل أدفع للتسجيل؟'), L('No. Registration and practice are free; a graded round may carry a fee the owner has set, shown before you start.', 'Non. L’inscription et l’entraînement sont gratuits ; une session notée peut comporter des frais fixés par le propriétaire, affichés avant de commencer.', 'لا. التسجيل والتدريب مجانيان؛ وقد تحمل الجولة المصحَّحة رسوما يحددها المالك وتظهر قبل البدء.'))])]

ADD['trust-terms'] = [
 defs('tm-key', L('The terms in one glance', 'Les conditions en un coup d’œil', 'الشروط في لمحة'), [
  (L('What a part is', 'Ce qu’est une part', 'ما هي الحصة'), L('A dated contribution to a certification path. Not a loan, share, deposit or job.', 'Une contribution datée à un parcours de certification. Ni prêt, ni action, ni dépôt, ni emploi.', 'مساهمة مؤرخة في مسار اعتماد. ليست قرضا ولا سهما ولا وديعة ولا وظيفة.')),
  (L('Refunds', 'Remboursements', 'الاستردادات'), L('Judged case by case in writing; nothing is promised in advance.', 'Examinés au cas par cas, par écrit ; rien n’est promis à l’avance.', 'تُبحث كل حالة كتابيا؛ ولا يُوعد بشيء مسبقا.')),
  (L('Your account', 'Votre compte', 'حسابك'), L('You are responsible for your password. We never ask for it.', 'Vous êtes responsable de votre mot de passe. Nous ne le demandons jamais.', 'أنت مسؤول عن كلمة مرورك. لا نطلبها أبدا.')),
  (L('Certificates', 'Certificats', 'الشهادات'), L('A certificate proves a passed round at a date. It can be revoked if it was issued in error or obtained unfairly.', 'Un certificat prouve une session réussie à une date. Il peut être révoqué s’il a été délivré par erreur ou obtenu de façon déloyale.', 'تثبت الشهادة جولة ناجحة بتاريخ. ويمكن إلغاؤها إن صدرت بالخطأ أو نيلت بغير وجه حق.')),
  (L('Referral commissions', 'Commissions de parrainage', 'عمولات الإحالة'), L('Declared rates on verified payments, paid by hand after a hold. Not an income promise.', 'Taux déclarés sur des paiements vérifiés, versés à la main après une période d’attente. Pas une promesse de revenu.', 'نسب معلنة على مدفوعات موثقة تُصرف يدويا بعد فترة احتجاز. ليست وعدا بدخل.'))])]

ADD['trust'] = [
 prose('tr-why', L('Why this section exists', 'Pourquoi cette section existe', 'لماذا يوجد هذا القسم'), [
  L('Most of what is published here is a plan, and a plan is easy to over-sell. Each page in this section says what we claim, what we do not, and how you can check.', 'L’essentiel de ce qui est publié ici est un plan, et un plan se survend facilement. Chaque page de cette section dit ce que nous affirmons, ce que nous n’affirmons pas, et comment vous pouvez vérifier.', 'معظم ما يُنشر هنا خطة، والخطة يسهل المبالغة في بيعها. تقول كل صفحة في هذا القسم ما ندّعيه وما لا ندّعيه وكيف تتحقق.'),
  L('Numbers on the site come from one settings file and from daily roll-ups. A zero is shown as a zero.', 'Les chiffres du site proviennent d’un seul fichier de réglages et de cumuls quotidiens. Un zéro est affiché comme un zéro.', 'تأتي أرقام الموقع من ملف إعدادات واحد ومن حصائل يومية. ويظهر الصفر صفرا.')])]

ADD['contact'] = [
 faq('con-faq', L('Before you write', 'Avant d’écrire', 'قبل أن تكتب'), [
  (L('How fast do you answer?', 'En combien de temps répondez-vous ?', 'ما سرعة ردكم؟'), L('A person reads every message. Corrections are answered within 14 days; other desks reply as soon as they can, without a promised delay.', 'Une personne lit chaque message. Les corrections reçoivent une réponse sous 14 jours ; les autres bureaux répondent dès que possible, sans délai promis.', 'يقرأ شخص كل رسالة. تُجاب التصحيحات خلال 14 يوما؛ وتردّ المكاتب الأخرى بأسرع ما يمكن دون موعد موعود.')),
  (L('Which desk should I pick?', 'Quel bureau choisir ?', 'أي مكتب أختار؟'), L('Pick the one closest to your question. If unsure, choose Partners; it is re-routed by hand.', 'Choisissez le plus proche de votre question. En cas de doute, prenez Partenaires ; le message est réorienté à la main.', 'اختر الأقرب إلى سؤالك. إن ترددت فاختر الشركاء؛ يُعاد توجيه الرسالة يدويا.')),
  (L('Is my message private?', 'Mon message est-il privé ?', 'هل رسالتي خاصة؟'), L('It is read by the team and kept only as long as needed to answer. See the privacy page.', 'Elle est lue par l’équipe et conservée le temps nécessaire pour répondre. Voir la page confidentialité.', 'يقرؤها الفريق وتُحفظ بقدر ما يلزم للرد. انظر صفحة الخصوصية.'))])]

ADD['countries-tunisia'] = [
 prose('tn-why', L('Why Tunisia first', 'Pourquoi la Tunisie d’abord', 'لماذا تونس أولا'), [
  L('The programme starts where its first certified experts live. Tunisia has {{ counts.governorates }} governorates; experts register from their own, and hubs, universities and coordinators are listed per region only once they are confirmed.', 'Le programme commence là où vivent ses premiers experts certifiés. La Tunisie compte {{ counts.governorates }} gouvernorats ; les experts s’inscrivent depuis le leur, et les pôles, universités et coordinateurs ne sont listés par région qu’une fois confirmés.', 'يبدأ البرنامج حيث يعيش أول خبرائه المعتمدين. لتونس {{ counts.governorates }} ولاية؛ يسجّل الخبراء من ولايتهم، ولا تُدرج المراكز والجامعات والمنسقون لكل جهة إلا بعد تأكيدهم.'),
  L('Payments run in Tunisian dinars through D17, verified by hand. Other countries come later, and none is announced before it is real.', 'Les paiements se font en dinars tunisiens via D17, vérifiés à la main. D’autres pays viendront plus tard, et aucun n’est annoncé avant d’être réel.', 'تتم المدفوعات بالدينار التونسي عبر D17 ويُتحقق منها يدويا. تأتي دول أخرى لاحقا ولا تُعلن قبل أن تتحقق.')])]

ADD['status'] = [
 prose('st-how', L('How to read this page', 'Comment lire cette page', 'كيف تقرأ هذه الصفحة'), [
  L('Every line has a status, a date and the evidence behind it. “Live” means a person can use it today; “building” and “concept” are not live and are labelled so.', 'Chaque ligne a un statut, une date et la preuve correspondante. « Actif » signifie qu’une personne peut l’utiliser aujourd’hui ; « en construction » et « concept » ne le sont pas et sont étiquetés ainsi.', 'لكل سطر حالة وتاريخ ودليل. «نشط» يعني أن شخصا يستطيع استخدامه اليوم؛ و«قيد الإنشاء» و«مفهوم» ليسا كذلك ويوسمان بذلك.'),
  L('The live figures come from daily roll-ups, not from targets. If a figure is zero, it is zero.', 'Les chiffres en direct viennent des cumuls quotidiens, pas des objectifs. Si un chiffre est zéro, il est zéro.', 'تأتي الأرقام الحالية من الحصائل اليومية لا من الأهداف. إن كان الرقم صفرا فهو صفر.')])]

ADD['trust-sampling'] = [
 faq('sp-faq', L('Sampling, asked plainly', 'L’échantillonnage, en questions simples', 'أخذ العينات بأسئلة مباشرة'), [
  (L('Why not check everything?', 'Pourquoi ne pas tout vérifier ?', 'لماذا لا نتحقق من كل شيء؟'), L('Full re-checking would cost more than the work it protects. A random sample is cheap and makes cheating risky.', 'Tout revérifier coûterait plus que le travail protégé. Un échantillon aléatoire est peu coûteux et rend la triche risquée.', 'إعادة فحص كل شيء ستكلف أكثر من العمل الذي تحميه. العينة العشوائية رخيصة وتجعل الغش مجازفة.')),
  (L('What happens when a sample fails?', 'Que se passe-t-il quand un échantillon échoue ?', 'ماذا يحدث عند فشل عينة؟'), L('The report is returned, the manager is told and the failure is counted in the quality figures.', 'Le rapport est renvoyé, le responsable est informé et l’échec est compté dans les chiffres de qualité.', 'يُعاد التقرير ويُبلَّغ المدير ويُحتسب الفشل في أرقام الجودة.'))])]

ADD['trust-privacy'] = [
 defs('pv-key', L('Your data, in short', 'Vos données, en bref', 'بياناتك باختصار'), [
  (L('What we keep', 'Ce que nous conservons', 'ما نحتفظ به'), L('Your account, your pledges and applications, your QCM attempts and certificates, and the messages you send us.', 'Votre compte, vos engagements et candidatures, vos tentatives QCM et certificats, et les messages que vous nous envoyez.', 'حسابك والتزاماتك وطلباتك ومحاولات QCM وشهاداتك والرسائل التي ترسلها إلينا.')),
  (L('What we never store', 'Ce que nous ne stockons jamais', 'ما لا نخزنه أبدا'), L('Your password in clear text, or the management key of a subscription in readable form.', 'Votre mot de passe en clair, ni la clé de gestion d’un abonnement sous forme lisible.', 'كلمة مرورك نصا صريحا أو مفتاح إدارة اشتراك بصيغة مقروءة.')),
  (L('Referral links', 'Liens de parrainage', 'روابط الإحالة'), L('A click is counted with a hashed, short-lived marker and a cookie that lasts {{ affiliate.cookie_days }} days.', 'Un clic est compté avec un marqueur haché et éphémère et un cookie valable {{ affiliate.cookie_days }} jours.', 'تُحسب النقرة بعلامة مجزّأة قصيرة العمر وملف تعريف يدوم {{ affiliate.cookie_days }} يوما.')),
  (L('Public certificate record', 'Fiche publique de certificat', 'سجل الشهادة العام'), L('Shows service, level and dates. The holder’s name is masked.', 'Indique service, niveau et dates. Le nom du titulaire est masqué.', 'يعرض الخدمة والمستوى والتواريخ. اسم الحامل مخفي.')),
  (L('Your rights', 'Vos droits', 'حقوقك'), L('Ask to see, correct or delete your data by writing to the contact desk.', 'Demandez à voir, corriger ou supprimer vos données en écrivant au bureau de contact.', 'اطلب الاطلاع على بياناتك أو تصحيحها أو حذفها بالكتابة إلى مكتب التواصل.'))])]

ADD['for'] = [
 faq('for-faq', L('Choosing a door', 'Choisir sa porte', 'اختيار بابك'), [
  (L('Can one account have several roles?', 'Un compte peut-il avoir plusieurs rôles ?', 'هل يمكن لحساب أن يحمل أدوارا عدة؟'), L('An account has one role at a time. You can use the same e-mail for an investor file and an applicant file; the role decides what the dashboard shows.', 'Un compte a un rôle à la fois. Vous pouvez utiliser le même e-mail pour un dossier d’investisseur et un dossier de candidat ; le rôle décide de ce que le tableau de bord affiche.', 'للحساب دور واحد في الوقت. يمكنك استخدام البريد نفسه لملف مستثمر وملف مترشح؛ والدور يحدد ما تعرضه اللوحة.')),
  (L('Where is the API documentation?', 'Où est la documentation API ?', 'أين وثائق API؟'), L('Inside your dashboard, written for your role, under API & Docs. It is private to signed-in people.', 'Dans votre tableau de bord, rédigée pour votre rôle, sous API et docs. Elle est réservée aux personnes connectées.', 'داخل لوحتك مكتوبة لدورك تحت API والوثائق. وهي خاصة بالمسجلين.'))])]

ADD['ontology'] = [
 prose('ont-how', L('How to use the ontology', 'Comment utiliser l’ontologie', 'كيف تستخدم الأنطولوجيا'), [
  L('Start from a macro work, open a service and read its deliverables, who does the work and how quality is checked. Every service sits at one layer and says which layer it speaks about.', 'Partez d’une macro-œuvre, ouvrez un service et lisez ses livrables, qui réalise le travail et comment la qualité est contrôlée. Chaque service se situe à un niveau et indique de quel niveau il parle.', 'ابدأ من عمل كبير وافتح خدمة واقرأ مخرجاتها ومن ينفذها وكيف تُراجع جودتها. تقع كل خدمة في طبقة وتذكر أي طبقة تتحدث عنها.'),
  L('The same data is available as JSON for developers, and each of the {{ counts.services }} services has its own QCM bank for certification.', 'Les mêmes données sont disponibles en JSON pour les développeurs, et chacun des {{ counts.services }} services possède sa propre banque QCM pour la certification.', 'البيانات نفسها متاحة بصيغة JSON للمطورين، ولكل خدمة من الخدمات الـ{{ counts.services }} بنك QCM خاص بها للاعتماد.')])]

ADD['join'] = [
 faq('join-faq', L('Joining, answered', 'Rejoindre, en réponses', 'الانضمام بأجوبة'), [
  (L('Do I need a diploma?', 'Faut-il un diplôme ?', 'هل أحتاج شهادة؟'), L('Each service names the diploma it expects. The eligibility check tells you which services your profile could match.', 'Chaque service indique le diplôme attendu. Le contrôle d’admissibilité vous dit quels services votre profil pourrait viser.', 'تذكر كل خدمة الشهادة المتوقعة. ويخبرك فحص الأهلية بالخدمات التي قد يناسبها ملفك.')),
  (L('What does it cost?', 'Combien cela coûte-t-il ?', 'كم تكلف؟'), L('Checking, registering and practising are free. A graded QCM round may have a fee set by the owner, shown before you start.', 'Vérifier, s’inscrire et s’entraîner sont gratuits. Une session QCM notée peut avoir des frais fixés par le propriétaire, affichés avant de commencer.', 'الفحص والتسجيل والتدريب مجانية. وقد تكون لجولة QCM المصحَّحة رسوم يحددها المالك وتظهر قبل البدء.')),
  (L('Is a job guaranteed?', 'Un emploi est-il garanti ?', 'هل الوظيفة مضمونة؟'), L('No. It is a path, not a job. Work follows certified headcount and funded capacity.', 'Non. C’est un parcours, pas un emploi. Le travail dépend des effectifs certifiés et des capacités financées.', 'لا. هو مسار لا وظيفة. والعمل يتبع العدد المعتمد والقدرة الممولة.'))])]

ADD['partners'] = [
 faq('par-faq', L('Commissioning a delivery', 'Confier une mission', 'تكليف بإنجاز'), [
  (L('What can I commission?', 'Que puis-je confier ?', 'ماذا أستطيع أن أكلّف؟'), L('Any of the {{ counts.services }} registered services, scoped as a deliverable with an acceptance rubric you agree beforehand.', 'N’importe lequel des {{ counts.services }} services enregistrés, cadré comme un livrable avec une grille d’acceptation convenue à l’avance.', 'أي خدمة من الخدمات الـ{{ counts.services }} المسجلة، محددة كمخرج بمعيار قبول تتفقون عليه مسبقا.')),
  (L('Who does the work?', 'Qui réalise le travail ?', 'من ينفذ العمل؟'), L('Certified experts, supervised by a manager, with a share of reports re-checked against source evidence.', 'Des experts certifiés, supervisés par un responsable, avec une part des rapports revérifiée à partir des preuves sources.', 'خبراء معتمدون يشرف عليهم مدير، وتُعاد مراجعة جزء من التقارير مقابل الأدلة المصدرية.')),
  (L('How do I start?', 'Comment commencer ?', 'كيف أبدأ؟'), L('Write to the partners desk with the service and the outcome you need. A person answers with scope and conditions.', 'Écrivez au bureau partenaires avec le service et le résultat attendu. Une personne répond avec un cadrage et des conditions.', 'راسل مكتب الشركاء بالخدمة والنتيجة التي تحتاجها. يرد شخص بنطاق وشروط.'))])]

ADD['about'] = [
 prose('abt-how', L('How we work', 'Notre façon de travailler', 'كيف نعمل'), [
  L('We publish the plan, the constants of the price function and the status of every project, each with a date. When something is only a concept, the page says so.', 'Nous publions le plan, les constantes de la fonction de prix et le statut de chaque projet, avec une date. Lorsqu’une chose n’est qu’un concept, la page le dit.', 'ننشر الخطة وثوابت دالة السعر وحالة كل مشروع بتاريخها. وحين يكون الشيء مجرد مفهوم تقول الصفحة ذلك.'),
  L('The team page names who is on the team today and which seats are open. Nothing is staffed on paper only.', 'La page équipe nomme qui est dans l’équipe aujourd’hui et quels postes sont ouverts. Rien n’est pourvu seulement sur le papier.', 'تسمّي صفحة الفريق من هو في الفريق اليوم والمقاعد المفتوحة. لا شيء يُشغَل على الورق فقط.')])]

ADD['careers-managers'] = [
 faq('mgr-faq', L('Managers, answered', 'Responsables, en réponses', 'المديرون بأجوبة'), [
  (L('What does a manager do?', 'Que fait un responsable ?', 'ماذا يفعل المدير؟'), L('Leads about {{ experts.team_size }} experts, sets a weekly rubric, signs a weekly report and answers for the quality of what the team delivers.', 'Dirige environ {{ experts.team_size }} experts, fixe une grille hebdomadaire, signe un rapport hebdomadaire et répond de la qualité de ce que l’équipe livre.', 'يقود نحو {{ experts.team_size }} خبيرا ويضع معيارا أسبوعيا ويوقّع تقريرا أسبوعيا ويُسأل عن جودة ما يسلّمه الفريق.')),
  (L('How is my application judged?', 'Comment ma candidature est-elle jugée ?', 'كيف يُحكم على طلبي؟'), L('The scenario answer is scored against a fixed rubric, then a person reads the whole file.', 'La réponse au scénario est notée selon une grille fixe, puis une personne lit l’ensemble du dossier.', 'تُقيَّم إجابة السيناريو وفق معيار ثابت ثم يقرأ شخص الملف كله.')),
  (L('Is a post promised?', 'Un poste est-il promis ?', 'هل المنصب موعود؟'), L('No. Posts follow certified headcount and funded capacity.', 'Non. Les postes dépendent des effectifs certifiés et des capacités financées.', 'لا. تتبع المناصب العدد المعتمد والقدرة الممولة.'))])]

ADD['faq'] = [
 faq('faq-programs', L('Certification, QCM and referrals', 'Certification, QCM et parrainage', 'الاعتماد وQCM والإحالة'), [
  (L('How does a QCM round work?', 'Comment fonctionne une session QCM ?', 'كيف تعمل جولة QCM؟'), L('Ten questions in ten minutes by default, drawn from a random service at your level. Your answers autosave and the server keeps the clock.', 'Dix questions en dix minutes par défaut, tirées d’un service au hasard à votre niveau. Vos réponses sont enregistrées et le serveur tient le chronomètre.', 'عشرة أسئلة في عشر دقائق افتراضيا من خدمة عشوائية في مستواك. تُحفظ إجاباتك والخادم يحتفظ بالمؤقت.')),
  (L('How is a certificate verified?', 'Comment un certificat est-il vérifié ?', 'كيف يُتحقق من شهادة؟'), L('Anyone can check its code on the verification page or through the public JSON endpoint. The holder’s name is masked.', 'Chacun peut vérifier son code sur la page de vérification ou via le point d’accès JSON public. Le nom du titulaire est masqué.', 'يستطيع أي شخص فحص رمزها في صفحة التحقق أو عبر نقطة JSON العامة. اسم الحامل مخفي.')),
  (L('How much does the affiliate programme pay?', 'Combien rapporte le programme d’affiliation ?', 'كم يدفع برنامج الإحالة؟'), L('A declared {{ affiliate.commission_pct|pct }} of a referred investor’s verified payment, plus small fixed bonuses, after a {{ affiliate.hold_days }}-day hold. It is not an income promise.', '{{ affiliate.commission_pct|pct }} déclarés du paiement vérifié d’un investisseur parrainé, plus de petits bonus fixes, après {{ affiliate.hold_days }} jours d’attente. Ce n’est pas une promesse de revenu.', '{{ affiliate.commission_pct|pct }} معلنة من الدفع الموثق لمستثمر مُحال، مع مكافآت ثابتة صغيرة بعد احتجاز {{ affiliate.hold_days }} يوما. وليست وعدا بدخل.'))])]

ADD['subscription'] = [
 prose('sub-how', L('How a daily subscription works', 'Comment fonctionne un abonnement journalier', 'كيف يعمل الاشتراك اليومي'), [
  L('You choose parts per day, a start date and a number of days. Each day has its own unit price from the published function, so the schedule is shown in full before you confirm.', 'Vous choisissez les parts par jour, une date de début et un nombre de jours. Chaque jour a son prix unitaire issu de la fonction publiée ; le calendrier est donc affiché en entier avant confirmation.', 'تختار الحصص يوميا وتاريخ البدء وعدد الأيام. لكل يوم سعر وحدة من الدالة المنشورة، فيُعرض الجدول كاملا قبل التأكيد.'),
  L('You can pause, resume or cancel with the reference and the key shown once at creation. Cancelling never touches what is already recorded.', 'Vous pouvez suspendre, reprendre ou annuler avec la référence et la clé affichée une fois à la création. Annuler ne touche jamais à ce qui est déjà enregistré.', 'يمكنك الإيقاف أو الاستئناف أو الإلغاء بالمرجع والمفتاح الذي ظهر مرة عند الإنشاء. ولا يمس الإلغاء ما سُجّل أصلا.')])]

ADD['how-it-works'] = [
 steps('how-path', L('Your path, from account to certificate', 'Votre parcours, du compte au certificat', 'مسارك من الحساب إلى الشهادة'), [
  (L('Create an account', 'Créez un compte', 'أنشئ حسابا'), L('Confirm your e-mail. Your dashboard opens with the panels for your role.', 'Confirmez votre e-mail. Votre tableau de bord s’ouvre avec les panneaux de votre rôle.', 'أكّد بريدك. تفتح لوحتك بلوحات دورك.')),
  (L('Practise', 'Entraînez-vous', 'تدرّب'), L('Free QCM rounds across the {{ counts.services }} services show you the format and the level.', 'Des sessions QCM gratuites sur les {{ counts.services }} services vous montrent le format et le niveau.', 'تُريك جولات QCM المجانية عبر الخدمات الـ{{ counts.services }} الصيغة والمستوى.')),
  (L('Sit a graded round', 'Passez une session notée', 'اجتز جولة مصحَّحة'), L('When you meet the requirements, a graded round can issue a certificate valid for {{ cert.validity_months }} months.', 'Lorsque vous remplissez les conditions, une session notée peut délivrer un certificat valable {{ cert.validity_months }} mois.', 'حين تستوفي الشروط قد تُصدر جولة مصحَّحة شهادة صالحة {{ cert.validity_months }} شهرا.')),
  (L('Move up', 'Montez en niveau', 'ارتقِ'), L('An upgrade round at the next level builds on your certificate. Delivery work follows certified headcount and funded capacity.', 'Une session de montée de niveau au palier suivant s’appuie sur votre certificat. Le travail de livraison dépend des effectifs certifiés et des capacités financées.', 'تبني جولة الترقية في المستوى التالي على شهادتك. ويتبع عمل التنفيذ العدد المعتمد والقدرة الممولة.'))])]

def main():
    for slug, secs in ADD.items():
        p = os.path.join(ROOT, 'data/content/pages', slug + '.json')
        d = json.load(open(p, encoding='utf-8'))
        have = {s.get('id') for s in d['sections']}
        new = [s for s in secs if s['id'] not in have]
        if not new: continue
        at = len(d['sections'])
        # keep a closing CTA / notice last
        while at > 0 and d['sections'][at - 1]['type'] in ('cta', 'notice', 'sticky-cta'): at -= 1
        d['sections'][at:at] = new
        with open(p, 'w', encoding='utf-8') as f:
            json.dump(d, f, ensure_ascii=False, indent=1); f.write('\n')
        print('+', slug, len(new))
main()
