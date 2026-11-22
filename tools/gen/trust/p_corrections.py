from lib import *

FORM = {"type": "form", "id": "co-form", "tier": "e", "focus": 4, "action": "/contact/",
 "title": {"en": "Report an error", "fr": "Signaler une erreur", "ar": "الإبلاغ عن خطأ"},
 "submit": {"en": "Send", "fr": "Envoyer", "ar": "إرسال"},
 "fields": [
  {"name": "name", "label": {"en": "Full name", "fr": "Nom complet", "ar": "الاسم الكامل"}, "type": "text", "required": True, "span": 1},
  {"name": "email", "label": {"en": "Email", "fr": "E-mail", "ar": "البريد الإلكتروني"}, "type": "email", "required": True, "span": 1},
  {"name": "desk", "label": {"en": "Desk", "fr": "Assistance", "ar": "مكتب"}, "type": "select", "required": True, "span": 1,
   "options": {"corrections": {"en": "Corrections", "fr": "Corrections", "ar": "التصحيحات"}}},
  {"name": "subject", "label": {"en": "Page or figure", "fr": "Page ou figure", "ar": "الصفحة أو الشكل"}, "type": "text", "required": True, "span": 1},
  {"name": "message", "label": {"en": "What is wrong, and what is your source?", "fr": "Qu’est-ce qui est erroné et quelle est votre source ?", "ar": "ما الخطأ، وما مصدرك؟"}, "type": "textarea", "required": True, "span": 2, "rows": 5}]}

PAGE = page('trust-corrections',
 ("Corrections", "Corrections", "التصحيحات"),
 ("How to report a wrong figure, a broken claim or an unfair sample result, what we do with it within {{ quality.correction_sla_days }} days, and how corrections are published.",
  "Comment signaler un chiffre erroné, une affirmation inexacte ou un résultat d’échantillon contestable, ce que nous en faisons sous {{ quality.correction_sla_days }} jours et comment les corrections sont publiées.",
  "كيف تبلّغ عن رقم خاطئ أو ادعاء غير صحيح أو نتيجة عيّنة غير منصفة، وماذا نفعل به خلال {{ quality.correction_sla_days }} يومًا، وكيف تُنشر التصويبات."),
 [
 hero('co-hero', ("Wrong? **Tell us.**", "Une erreur ? **Dites-le-nous.**", "هل من خطأ؟ **أخبرنا.**"),
  ("A site that publishes figures will sometimes get one wrong. What matters is whether the error can be reported by anyone, answered by a person and corrected in the open. This is the desk that does that.",
   "Un site qui publie des chiffres en aura parfois un faux. Ce qui compte, c’est que l’erreur puisse être signalée par n’importe qui, traitée par une personne et corrigée au grand jour. Ce pôle fait cela.",
   "الموقع الذي ينشر أرقامًا سيخطئ في بعضها أحيانًا. والمهم أن يستطيع أي شخص الإبلاغ عن الخطأ، وأن يجيب عنه إنسان، وأن يُصحَّح علنًا. وهذا المكتب يقوم بذلك."),
  ("Corrections", "Corrections", "التصويبات"),
  [(("Open the form", "Ouvrir le formulaire", "افتح النموذج"), '#co-form', 'peak', True)], 'calm'),

 prose('co-why', ("Why a corrections desk", "Pourquoi un pôle des corrections", "لماذا مكتب للتصويبات"), [
  ("Most of the numbers on this site come from a single settings file, which keeps pages consistent with each other. That is a strength, because a number is changed in one place, but it also means that one wrong number can be wrong everywhere at once. Outside readers are the best defence we have: a student who knows a regional figure, an accountant who spots a rate, a researcher who recognises a source that was misread. We would much rather hear about it than have it go unnoticed.",
   "La plupart des chiffres de ce site proviennent d’un fichier de paramètres unique, ce qui garde les pages cohérentes entre elles. C’est une force, puisqu’un chiffre se change à un seul endroit, mais cela signifie aussi qu’un chiffre faux peut l’être partout à la fois. Les lecteurs extérieurs sont notre meilleure défense : un étudiant qui connaît un chiffre régional, un comptable qui repère un taux, un chercheur qui reconnaît une source mal lue. Nous préférons de loin l’apprendre que de le laisser passer.",
   "تأتي معظم الأرقام في هذا الموقع من ملف إعدادات واحد، وهذا يُبقي الصفحات متسقة فيما بينها. وهي قوة لأن الرقم يُغيَّر في مكان واحد، لكنها تعني أيضًا أن رقمًا واحدًا خاطئًا قد يكون خاطئًا في كل مكان دفعة واحدة. والقراء من الخارج أفضل دفاع لدينا: طالب يعرف رقمًا إقليميًا، ومحاسب يلاحظ نسبة، وباحث يتعرف على مصدر أُسيء فهمه. ونفضّل كثيرًا أن نسمع بذلك على أن يمر دون أن يُلاحظ."),
  ("Corrections are also a promise to the people who rely on us. The [editorial policy](/trust/editorial/) says that we show our sources and our dates; the desk is how that promise is tested by someone other than us. A page that never changes may be a page that nobody is checking.",
   "Les corrections sont aussi une promesse faite à ceux qui s’appuient sur nous. La [politique éditoriale](/trust/editorial/) dit que nous montrons nos sources et nos dates ; le pôle est la façon dont cette promesse est mise à l’épreuve par quelqu’un d’autre que nous. Une page qui ne change jamais est peut-être une page que personne ne vérifie.",
   "والتصويبات وعد كذلك لمن يعتمدون علينا. فـ[السياسة التحريرية](/trust/editorial/) تقول إننا نعرض مصادرنا وتواريخنا؛ والمكتب هو الطريقة التي يُختبر بها هذا الوعد على يد غيرنا. وصفحة لا تتغير أبدًا قد تكون صفحة لا يراجعها أحد.")]),

 prose('co-what', ("What you can report", "Ce que vous pouvez signaler", "ما الذي يمكنك الإبلاغ عنه"), [
  ("Anything on the site that you believe is wrong, unclear or unfair is in scope, and you do not need to be a member to report it. The most useful reports fall into a few kinds, and the list below helps you choose the words.",
   "Tout ce que vous croyez erroné, peu clair ou inéquitable sur le site relève du pôle, et il n’est pas nécessaire d’être membre pour le signaler. Les signalements les plus utiles se rangent en quelques types, et la liste ci-dessous vous aide à choisir vos mots.",
   "كل ما تظن أنه خاطئ أو غير واضح أو غير منصف في الموقع يدخل في اختصاص المكتب، ولا يلزمك أن تكون عضوًا للإبلاغ عنه. وأنفع البلاغات تقع في بضعة أنواع، والقائمة التالية تساعدك في اختيار عباراتك.")],
  [("**A wrong figure.** A number, a rate, a date or a count that disagrees with its source or with another page of ours.",
    "**Un chiffre erroné.** Un nombre, un taux, une date ou un décompte qui contredit sa source ou une autre de nos pages.",
    "**رقم خاطئ.** عدد أو نسبة أو تاريخ أو تعداد يخالف مصدره أو صفحة أخرى من صفحاتنا."),
   ("**A claim without support.** A sentence that sounds certain but that you cannot find a source for.",
    "**Une affirmation sans appui.** Une phrase qui sonne comme certaine mais dont vous ne trouvez pas la source.",
    "**ادعاء بلا سند.** جملة تبدو جازمة ولا تجد لها مصدرًا."),
   ("**A translation problem.** A French or Arabic sentence that says something different from the English, or that reads badly to a native speaker.",
    "**Un problème de traduction.** Une phrase française ou arabe qui dit autre chose que l’anglais, ou qui sonne mal pour un locuteur natif.",
    "**مشكلة ترجمة.** جملة فرنسية أو عربية تقول غير ما تقوله الإنجليزية أو تبدو رديئة لمتحدث أصلي."),
   ("**An unfair sample result.** A result of the [{{ quality.sampling_percent }}% sampling](/trust/sampling/) that you believe is mistaken, together with the source that supports you.",
    "**Un résultat d’échantillon inéquitable.** Un résultat de l’[échantillonnage à {{ quality.sampling_percent }} %](/trust/sampling/) que vous croyez erroné, avec la source qui vous donne raison.",
    "**نتيجة عيّنة غير منصفة.** نتيجة من [عيّنات {{ quality.sampling_percent }}%](/trust/sampling/) ترى أنها خاطئة، مع المصدر الذي يسندك."),
   ("**A content-rights issue.** Material that you believe is yours and is used without credit. See the [imprint](/trust/imprint/) for how intellectual property is handled.",
    "**Un problème de droits sur un contenu.** Un contenu que vous estimez être le vôtre et utilisé sans mention. Voir les [mentions légales](/trust/imprint/) pour le traitement de la propriété intellectuelle.",
    "**مسألة حقوق محتوى.** مادة ترى أنها لك وتُستعمل دون نسبة. انظر [الإشعار القانوني](/trust/imprint/) لمعرفة كيف تُعالج الملكية الفكرية.")]),

 FORM,

 steps('co-how', ("How a correction is handled", "Comment une correction est traitée", "كيف يُعالَج التصويب"), [
  (("You report", "Vous signalez", "تبلّغ"),
   ("Use the form on this page, name the page or the figure and give your source if you have one. You receive a reference so that you can follow up. A report with a source is dealt with faster, but a report without one is still read.",
    "Utilisez le formulaire de cette page, nommez la page ou le chiffre et donnez votre source si vous en avez une. Vous recevez une référence pour le suivi. Un signalement sourcé est traité plus vite, mais un signalement sans source est quand même lu.",
    "استخدم النموذج في هذه الصفحة وسمِّ الصفحة أو الرقم وأعطِ مصدرك إن وُجد. وتحصل على مرجع للمتابعة. والبلاغ المسنود بمصدر يُعالج أسرع، لكن البلاغ بلا مصدر يُقرأ أيضًا.")),
  (("We check", "Nous vérifions", "نتحقق"),
   ("A person other than the author of the original text reads the report, goes to the source and decides whether it is right, partly right or mistaken. You are told the answer and the reason within {{ quality.correction_sla_days }} days.",
    "Une personne autre que l’auteur du texte initial lit le signalement, va à la source et décide s’il est juste, en partie juste ou erroné. On vous communique la réponse et le motif sous {{ quality.correction_sla_days }} jours.",
    "يقرأ البلاغ شخص غير كاتب النص الأصلي، ويرجع إلى المصدر، ويقرر إن كان صحيحًا أو صحيحًا جزئيًا أو خاطئًا. ويُبلَّغ صاحبه بالجواب وسببه خلال {{ quality.correction_sla_days }} يومًا.")),
  (("We publish", "Nous publions", "ننشر"),
   ("When we were wrong, the page is changed and the change is dated. A correction that matters, such as a figure that people may have used to decide, is noted on the page itself rather than replaced silently.",
    "Quand nous avions tort, la page est modifiée et la modification est datée. Une correction qui compte, comme un chiffre sur lequel on a pu fonder une décision, est signalée sur la page elle-même plutôt que remplacée en silence.",
    "حين نكون مخطئين تُعدَّل الصفحة ويُؤرَّخ التعديل. والتصويب المهم، كرقم ربما بنى الناس عليه قرارًا، يُشار إليه في الصفحة نفسها بدل أن يُستبدل بصمت."))]),

 table('co-times', ("What to expect, and when", "À quoi vous attendre, et quand", "ما الذي تتوقعه ومتى"),
  [("Stage", "Étape", "المرحلة"), ("Target", "Objectif", "الهدف"), ("What you get", "Ce que vous recevez", "ما تحصل عليه")],
  [[("Acknowledgement", "Accusé de réception", "الإقرار بالاستلام"), ("{{ legal.ack_hours }} hours, working days", "{{ legal.ack_hours }} heures, jours ouvrés", "{{ legal.ack_hours }} ساعة في أيام العمل"), ("A reference number and the confirmation that a person will read it.", "Un numéro de référence et la confirmation qu’une personne le lira.", "رقم مرجعي وتأكيد بأن شخصًا سيقرؤه.")],
   [("Decision", "Décision", "القرار"), ("{{ quality.correction_sla_days }} days", "{{ quality.correction_sla_days }} jours", "{{ quality.correction_sla_days }} يومًا"), ("Right, partly right or mistaken, with the reason and the source we used.", "Juste, en partie juste ou erroné, avec le motif et la source que nous avons utilisée.", "صحيح أو صحيح جزئيًا أو خاطئ، مع السبب والمصدر الذي استخدمناه.")],
   [("Publication", "Publication", "النشر"), ("With the decision when we were wrong", "Avec la décision quand nous avions tort", "مع القرار حين نكون مخطئين"), ("The corrected page and a dated note where the change matters.", "La page corrigée et une note datée là où le changement compte.", "الصفحة المصحَّحة وملاحظة مؤرخة حيث يكون التغيير مهمًا.")],
   [("If you disagree", "Si vous n’êtes pas d’accord", "إن لم تتفق معنا"), ("A second reading", "Une seconde lecture", "قراءة ثانية"), ("Reply to the same reference. The second reading is by someone who did not give the first answer.", "Répondez à la même référence. La seconde lecture est faite par quelqu’un qui n’a pas donné la première réponse.", "ردّ على المرجع نفسه. وتتولى القراءة الثانية شخص لم يعطِ الجواب الأول.")]],
  ("These are targets we aim to meet, not a legal guarantee. If we miss one, tell us through the same reference and we will say why.", "Ce sont des objectifs que nous visons, non une garantie juridique. Si nous en manquons un, dites-le-nous via la même référence et nous dirons pourquoi.", "هذه أهداف نسعى إلى بلوغها لا ضمان قانونيًا. وإن فاتنا أحدها فأخبرنا عبر المرجع نفسه وسنقول السبب.")),

 prose('co-log', ("What we keep, and what is public", "Ce que nous conservons, et ce qui est public", "ما نحتفظ به وما هو علني"), [
  ("Your name and e-mail address are used only to answer you and are kept for the period in the [privacy notice](/trust/privacy/). They are not published. What we publish is the correction itself: the page, the old and new wording, and the date. If you want to be credited for the report, say so in the message; otherwise you stay anonymous.",
   "Votre nom et votre adresse e-mail ne servent qu’à vous répondre et sont conservés pendant la durée indiquée dans l’[avis de confidentialité](/trust/privacy/). Ils ne sont pas publiés. Ce que nous publions, c’est la correction elle-même : la page, l’ancienne et la nouvelle formulation, et la date. Si vous souhaitez être cité pour ce signalement, dites-le dans le message ; sinon vous restez anonyme.",
   "يُستخدم اسمك وبريدك الإلكتروني للرد عليك فقط، ويُحفظان للمدة المذكورة في [إشعار الخصوصية](/trust/privacy/). ولا يُنشران. ما ننشره هو التصويب نفسه: الصفحة والصياغة القديمة والجديدة والتاريخ. وإن أردت أن تُذكر في البلاغ فقل ذلك في رسالتك، وإلا بقيت مجهولًا."),
  ("A log of significant corrections is kept so that a pattern can be seen. If the same kind of error appears several times, the question is no longer one number but why the process let it through, and the answer is a change to the process, not only to the number. When that happens we will say so here.",
   "Un journal des corrections significatives est tenu pour que l’on puisse voir une tendance. Si le même type d’erreur revient plusieurs fois, la question n’est plus un chiffre mais pourquoi le processus l’a laissé passer, et la réponse est un changement du processus, pas seulement du chiffre. Quand cela arrivera, nous le dirons ici.",
   "يُحتفظ بسجل للتصويبات المهمة ليمكن رؤية النمط. فإذا تكرر نوع الخطأ نفسه مرات، لم يعد السؤال عن رقم واحد بل عن سبب سماح العملية بمروره، والجواب تغيير في العملية لا في الرقم وحده. وحين يحدث ذلك سنقوله هنا."),
  ("There is one exception to openness. A report that contains personal data of a third party, or that concerns a security weakness, is not published in its original form. Weaknesses go through the process on the [security page](/trust/security/), and requests about your own data go through [your data rights](/trust/data-rights/).",
   "Il y a une exception à la transparence. Un signalement contenant des données personnelles d’un tiers, ou concernant une faille de sécurité, n’est pas publié sous sa forme d’origine. Les failles suivent la procédure de la [page de sécurité](/trust/security/), et les demandes sur vos propres données passent par [vos droits sur les données](/trust/data-rights/).",
   "ثمة استثناء واحد من العلنية. فالبلاغ الذي يتضمن بيانات شخصية لطرف ثالث أو يتعلق بضعف أمني لا يُنشر بصيغته الأصلية. فالثغرات تتبع الإجراء المبيّن في [صفحة الأمان](/trust/security/)، وطلبات بياناتك الشخصية تمر عبر [حقوقك في بياناتك](/trust/data-rights/).")]),

 prose('co-world', ("Locally in Tunisia, and globally", "Localement en Tunisie, et à l’échelle mondiale", "محليًا في تونس وعالميًا"), [
  ("You can write in Arabic, French or English, from Tunisia or from anywhere else, and the desk answers in the language you used. A reader in Sousse and a funder in another country go through the same steps and are given the same timing. We do not ask where you live, and your answer does not depend on it.",
   "Vous pouvez écrire en arabe, en français ou en anglais, depuis la Tunisie ou d’ailleurs, et le pôle répond dans la langue que vous avez employée. Un lecteur de Sousse et un financeur d’un autre pays suivent les mêmes étapes avec les mêmes délais. Nous ne demandons pas où vous vivez et la réponse n’en dépend pas.",
   "يمكنك الكتابة بالعربية أو الفرنسية أو الإنجليزية من تونس أو من أي مكان آخر، ويجيب المكتب بلغتك. فالقارئ في سوسة والممول في بلد آخر يمرّان بالخطوات نفسها وبالمواعيد نفسها. ولا نسأل أين تعيش ولا يتوقف جوابك على ذلك."),
  ("Some corrections touch regulated subjects: a tax rate, a legal deadline, a fee that depends on a Tunisian rule. For those, the reviewer checks the official text and, where the rule is unclear, asks a Tunisian accountant or lawyer before changing the page. This is also why the legal pages carry a review date and say that they are not legal advice.",
   "Certaines corrections touchent des sujets réglementés : un taux d’imposition, un délai légal, des frais qui dépendent d’une règle tunisienne. Pour celles-là, le relecteur vérifie le texte officiel et, quand la règle est peu claire, interroge un comptable ou un avocat tunisien avant de modifier la page. C’est aussi pourquoi les pages juridiques portent une date de revue et disent qu’elles ne sont pas un avis juridique.",
   "تمس بعض التصويبات موضوعات منظَّمة: نسبة ضريبية أو أجل قانوني أو رسم يتوقف على قاعدة تونسية. وفي هذه الحالات يتحقق المراجع من النص الرسمي، وحين تكون القاعدة غير واضحة يسأل محاسبًا أو محاميًا تونسيًا قبل تعديل الصفحة. ولهذا أيضًا تحمل الصفحات القانونية تاريخ مراجعة وتقول إنها ليست استشارة قانونية."),
  VERIFY]),

 faq('co-faq', ("Corrections, asked plainly", "Les corrections, en questions simples", "التصويبات بأسئلة مباشرة"), [
  (("Do I have to be a member?", "Dois-je être membre ?", "هل يجب أن أكون عضوًا؟"), ("No. Anyone can report an error. You only need an e-mail address so that we can answer you.", "Non. N’importe qui peut signaler une erreur. Il suffit d’une adresse e-mail pour que nous puissions vous répondre.", "لا. يستطيع أي شخص الإبلاغ عن خطأ. يكفي بريد إلكتروني لنتمكن من الرد عليك.")),
  (("What if I have no source?", "Et si je n’ai pas de source ?", "وماذا لو لم يكن لدي مصدر؟"), ("Report anyway and say what makes you doubt it. A doubt without a source is still a useful signal, but a source makes the decision faster.", "Signalez quand même et dites ce qui vous fait douter. Un doute sans source reste un signal utile, mais une source accélère la décision.", "أبلغ مع ذلك وقل ما يجعلك تشك. فالشك بلا مصدر إشارة مفيدة أيضًا، لكن المصدر يسرّع القرار.")),
  (("Will my report be published?", "Mon signalement sera-t-il publié ?", "هل سيُنشر بلاغي؟"), ("The correction is published, not your message and not your name, unless you ask to be credited.", "La correction est publiée, pas votre message ni votre nom, sauf si vous demandez à être cité.", "يُنشر التصويب لا رسالتك ولا اسمك، إلا إذا طلبت أن تُذكر.")),
  (("What if you disagree with me?", "Et si vous n’êtes pas d’accord avec moi ?", "وماذا لو لم تتفقوا معي؟"), ("You get the reason and the source we used, and you can ask for a second reading by someone else.", "Vous recevez le motif et la source utilisée, et vous pouvez demander une seconde lecture par quelqu’un d’autre.", "تحصل على السبب والمصدر الذي استخدمناه، ويمكنك طلب قراءة ثانية من شخص آخر.")),
  (("Is this the place to report a security flaw?", "Est-ce le bon endroit pour signaler une faille de sécurité ?", "هل هذا مكان الإبلاغ عن ثغرة أمنية؟"), ("Use the process on the security page instead, so that details are not made public before a fix.", "Utilisez plutôt la procédure de la page de sécurité, afin que les détails ne deviennent pas publics avant un correctif.", "استخدم بدلًا من ذلك الإجراء المبيّن في صفحة الأمان كي لا تصبح التفاصيل علنية قبل الإصلاح."))]),

 prose('co-related', ("Related pages", "Pages liées", "صفحات ذات صلة"), [
  ("The desk is one part of how W5D tries to earn trust. The [editorial policy](/trust/editorial/) sets the standard that corrections are measured against, the [statement of what W5D promises](/trust/statement/) lists what we claim, and the [sampling method](/trust/sampling/) explains the second look at reports. The [status page](/status/) shows what is live and what is not. All of them are listed on the [trust index](/trust/).",
   "Le pôle est une des façons dont W5D tente de mériter la confiance. La [politique éditoriale](/trust/editorial/) fixe la norme à laquelle se mesurent les corrections, la [déclaration de ce que W5D promet](/trust/statement/) liste ce que nous affirmons, et la [méthode d’échantillonnage](/trust/sampling/) explique le second regard sur les rapports. La [page de statut](/status/) montre ce qui est actif et ce qui ne l’est pas. Toutes sont listées dans l’[index de confiance](/trust/).",
   "المكتب جزء من سعي W5D إلى كسب الثقة. فـ[السياسة التحريرية](/trust/editorial/) تضع المعيار الذي تُقاس به التصويبات، و[بيان ما تعد به W5D](/trust/statement/) يسرد ما ندّعيه، و[طريقة العيّنات](/trust/sampling/) تشرح النظرة الثانية إلى التقارير. و[صفحة الحالة](/status/) تبيّن ما هو قائم وما ليس كذلك. وكلها مدرجة في [فهرس الثقة](/trust/).")]),

 cta_block('co-final', ("Spotted a mistake? Send it.", "Vous avez repéré une erreur ? Envoyez-la.", "لاحظت خطأ؟ أرسله."),
  ("It takes a minute, and it makes the next reader’s page more accurate.", "Cela prend une minute et rend la page du prochain lecteur plus exacte.", "يستغرق الأمر دقيقة ويجعل صفحة القارئ التالي أدق."),
  [(("Open the form", "Ouvrir le formulaire", "افتح النموذج"), '#co-form', 'peak', True), (("Read the editorial policy", "Lire la politique éditoriale", "اقرأ السياسة التحريرية"), '/trust/editorial/', 'ghost', False)]),
 ])
