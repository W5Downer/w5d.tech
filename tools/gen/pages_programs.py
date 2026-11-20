#!/usr/bin/env python3
"""Writes data/content/pages/{affiliate,certification}.json (tri-lingual, numbers only via {{ tokens }})."""
import json, os
ROOT = os.path.join(os.path.dirname(__file__), '..', '..')
def L(en, fr, ar): return {'en': en, 'fr': fr, 'ar': ar}
def cta(label, href, style='ghost', arrow=False):
    d = {'label': label, 'href': href, 'style': style}
    if arrow: d['arrow'] = True
    return d

affiliate = {
 'slug': 'affiliate',
 'title': L('Affiliate programme', 'Programme d’affiliation', 'برنامج الإحالة'),
 'description': L('Share your link, earn a declared commission on verified payments. No promise of income.',
                  'Partagez votre lien et percevez une commission déclarée sur les paiements vérifiés. Aucune promesse de revenu.',
                  'شارك رابطك واحصل على عمولة معلنة عن المدفوعات الموثقة. دون وعد بدخل.'),
 'sections': [
  {'type': 'hero', 'id': 'aff-hero', 'variant': 'classic', 'gradient': 'calm',
   'eyebrow': L('Affiliate programme', 'Programme d’affiliation', 'برنامج الإحالة'),
   'title': L('Bring someone who fits. **Be paid for the proof, not the promise.**',
              'Amenez une personne qui a sa place. **Soyez rémunéré pour la preuve, pas pour la promesse.**',
              'اجلب شخصا مناسبا. **تُكافأ على الإثبات لا على الوعد.**'),
   'lead': L('Every account gets a personal link. When someone who arrived through it confirms an e-mail, pays a verified part or earns a certificate, your balance records it, with a date and a reason.',
             'Chaque compte reçoit un lien personnel. Lorsqu’une personne arrivée par ce lien confirme son e-mail, règle une part vérifiée ou obtient un certificat, votre solde l’enregistre avec une date et un motif.',
             'يحصل كل حساب على رابط شخصي. حين يؤكد شخص وصل عبره بريده، أو يدفع حصة موثقة، أو ينال شهادة، يسجّل رصيدك ذلك بتاريخ وسبب.'),
   'ctas': [cta(L('Open my affiliate panel', 'Ouvrir mon espace d’affiliation', 'افتح لوحة الإحالة'), '/account/affiliate/', 'peak', True),
            cta(L('How the money works', 'Comment l’argent circule', 'كيف تعمل المبالغ'), '#aff-rates')]},
  {'type': 'kpis', 'id': 'aff-rates', 'tier': 'e', 'focus': 3,
   'items': [
     {'value': '{{ affiliate.commission_pct|pct }}', 'label': L('of a referred investor’s verified payment', 'du paiement vérifié d’un investisseur parrainé', 'من المدفوعات الموثقة للمستثمر المُحال')},
     {'value': '{{ affiliate.signup_bonus|money }}', 'label': L('when a referred account confirms its e-mail', 'lorsqu’un compte parrainé confirme son e-mail', 'عند تأكيد الحساب المُحال لبريده')},
     {'value': '{{ affiliate.cert_bonus|money }}', 'label': L('when a referred account earns its first certificate', 'lorsqu’un compte parrainé obtient son premier certificat', 'عند نيل الحساب المُحال شهادته الأولى')},
     {'value': '{{ affiliate.cookie_days }}', 'label': L('days your link is remembered', 'jours de mémorisation de votre lien', 'يوما يُحفظ فيها رابطك')}]},
  {'type': 'steps', 'id': 'aff-how', 'tier': 'i', 'focus': 3,
   'title': L('Four steps, all visible in your panel', 'Quatre étapes, toutes visibles dans votre espace', 'أربع خطوات، كلها ظاهرة في لوحتك'),
   'items': [
     {'title': L('Apply', 'Candidatez', 'قدّم طلبك'), 'body': L('Sign in, open the affiliate panel and apply. A person reviews each application unless the owner has opened automatic approval.', 'Connectez-vous, ouvrez l’espace d’affiliation et candidatez. Une personne examine chaque demande, sauf si le propriétaire a ouvert l’approbation automatique.', 'سجّل الدخول وافتح لوحة الإحالة وقدّم طلبك. يراجع شخص كل طلب ما لم يفتح المالك الموافقة التلقائية.')},
     {'title': L('Share your link', 'Partagez votre lien', 'شارك رابطك'), 'body': L('Copy the link or your code. Clicks are counted without storing anything about the visitor beyond a hashed, short-lived marker.', 'Copiez le lien ou votre code. Les clics sont comptés sans conserver sur le visiteur autre chose qu’un marqueur haché et éphémère.', 'انسخ الرابط أو رمزك. تُحسب النقرات دون حفظ شيء عن الزائر سوى علامة مجزّأة قصيرة العمر.')},
     {'title': L('Wait for the hold', 'Attendez la période de rétention', 'انتظر فترة الاحتجاز'), 'body': L('A commission stays pending for {{ affiliate.hold_days }} days so that a reversed or failed payment never leaves a debt behind.', 'Une commission reste en attente {{ affiliate.hold_days }} jours afin qu’un paiement annulé ou échoué ne laisse aucune dette.', 'تبقى العمولة معلّقة {{ affiliate.hold_days }} يوما حتى لا يترك دفع مُلغى أو فاشل أي دين.')},
     {'title': L('Request a payout', 'Demandez un versement', 'اطلب صرفا'), 'body': L('From {{ affiliate.min_payout|money }} you can request a payout. The owner settles it by hand and records the reference.', 'À partir de {{ affiliate.min_payout|money }}, vous pouvez demander un versement. Le propriétaire le règle à la main et en consigne la référence.', 'ابتداء من {{ affiliate.min_payout|money }} يمكنك طلب صرف. يسوّيه المالك يدويا ويسجّل مرجعه.')}]},
  {'type': 'prose', 'id': 'aff-rules', 'tier': 'e', 'focus': 3,
   'title': L('The rules, in plain words', 'Les règles, en termes simples', 'القواعد بلغة واضحة'),
   'paragraphs': [
     L('Commission is a declared rate set by the owner and shown here from the live settings. It applies only to payments the programme has verified. It is not a salary, not a guaranteed income and not a share of the programme.', 'La commission est un taux déclaré, fixé par le propriétaire et affiché ici à partir des réglages en vigueur. Elle ne s’applique qu’aux paiements vérifiés par le programme. Ce n’est ni un salaire, ni un revenu garanti, ni une part du programme.', 'العمولة نسبة معلنة يحددها المالك وتظهر هنا من الإعدادات الحالية. تنطبق فقط على المدفوعات التي تحقق منها البرنامج. وهي ليست راتبا ولا دخلا مضمونا ولا حصة في البرنامج.'),
     L('You cannot earn from your own account or from accounts you control. Self-referrals, duplicate accounts and misleading promotion are refused, and an affiliate who breaks these rules can be suspended.', 'Vous ne pouvez rien percevoir sur votre propre compte ni sur des comptes que vous contrôlez. Les auto-parrainages, les comptes en double et la promotion trompeuse sont refusés, et un affilié qui enfreint ces règles peut être suspendu.', 'لا يمكنك الكسب من حسابك أو من حسابات تتحكم فيها. تُرفض الإحالة الذاتية والحسابات المكررة والترويج المضلِّل، ويمكن تعليق من يخالف ذلك.'),
     L('Say what the programme is. It is a path to certified expert work in Tunisia, not a job offer. Never promise a candidate a post, a result or a return.', 'Dites ce qu’est le programme : un parcours vers un travail d’expert certifié en Tunisie, pas une offre d’emploi. Ne promettez jamais à un candidat un poste, un résultat ou un rendement.', 'قل ما هو البرنامج: مسار نحو عمل خبير معتمد في تونس وليس عرض وظيفة. لا تعد أي مرشح بمنصب أو نتيجة أو عائد.')]},
  {'type': 'faq', 'id': 'aff-faq', 'tier': 'e', 'focus': 2,
   'title': L('Affiliate questions', 'Questions sur l’affiliation', 'أسئلة عن الإحالة'),
   'items': [
     {'q': L('Is it free to join?', 'L’inscription est-elle gratuite ?', 'هل الانضمام مجاني؟'), 'a': L('Yes. You only need an account with a confirmed e-mail.', 'Oui. Il suffit d’un compte dont l’e-mail est confirmé.', 'نعم. يكفي حساب ببريد مؤكد.')},
     {'q': L('What if the referred person already had an account?', 'Et si la personne parrainée avait déjà un compte ?', 'ماذا لو كان للشخص المُحال حساب مسبق؟'), 'a': L('Only accounts created after clicking your link are attributed. The first link in the {{ affiliate.cookie_days }}-day window wins.', 'Seuls les comptes créés après un clic sur votre lien sont rattachés. Le premier lien dans la fenêtre de {{ affiliate.cookie_days }} jours l’emporte.', 'تُنسب فقط الحسابات المنشأة بعد النقر على رابطك. ويفوز أول رابط ضمن نافذة {{ affiliate.cookie_days }} يوما.')},
     {'q': L('Why is my commission pending?', 'Pourquoi ma commission est-elle en attente ?', 'لماذا عمولتي معلّقة؟'), 'a': L('It waits {{ affiliate.hold_days }} days and for the payment to be verified. After that it becomes payable.', 'Elle attend {{ affiliate.hold_days }} jours et la vérification du paiement. Ensuite, elle devient payable.', 'تنتظر {{ affiliate.hold_days }} يوما وتحقق الدفع. بعدها تصبح قابلة للصرف.')},
     {'q': L('Can the rates change?', 'Les taux peuvent-ils changer ?', 'هل يمكن أن تتغير النسب؟'), 'a': L('Yes. The owner can change them; a change applies to later events, never to what is already recorded.', 'Oui. Le propriétaire peut les modifier ; un changement s’applique aux événements futurs, jamais à ce qui est déjà enregistré.', 'نعم. يمكن للمالك تغييرها، ويسري التغيير على الأحداث اللاحقة لا على ما سُجّل.')}]},
  {'type': 'cta', 'id': 'aff-final', 'gradient': 'peak',
   'title': L('Ready to **share your link?**', 'Prêt à **partager votre lien ?**', 'هل أنت مستعد **لمشاركة رابطك؟**'),
   'body': L('Applications are reviewed by a person. Nothing here is a promise of income.', 'Les candidatures sont examinées par une personne. Rien ici n’est une promesse de revenu.', 'يراجع شخص الطلبات. لا شيء هنا وعد بدخل.'),
   'ctas': [cta(L('Apply in my account', 'Candidater depuis mon compte', 'قدّم من حسابي'), '/account/affiliate/', 'peak', True)]},
 ]}

certification = {
 'slug': 'certification',
 'title': L('Certification and QCM', 'Certification et QCM', 'الاعتماد واختبارات QCM'),
 'description': L('Timed multiple-choice rounds for each service and level: practice, certification and upgrade. Every certificate can be verified by anyone.',
                  'Sessions à choix multiples chronométrées pour chaque service et niveau : entraînement, certification et montée de niveau. Chaque certificat est vérifiable par tous.',
                  'جولات اختيار من متعدد محددة الوقت لكل خدمة ومستوى: تدريب واعتماد وترقية. يمكن لأي شخص التحقق من كل شهادة.'),
 'sections': [
  {'type': 'hero', 'id': 'cert-hero', 'variant': 'classic', 'gradient': 'sciences',
   'eyebrow': L('Certification', 'Certification', 'الاعتماد'),
   'title': L('Ten questions. Ten minutes. **A certificate anyone can check.**', 'Dix questions. Dix minutes. **Un certificat que chacun peut vérifier.**', 'عشرة أسئلة. عشر دقائق. **وشهادة يستطيع أي شخص التحقق منها.**'),
   'lead': L('Each of the {{ counts.services }} services has its own question bank. Practise for free, then sit a graded round when you meet the requirements the owner has set for your level.', 'Chacun des {{ counts.services }} services dispose de sa propre banque de questions. Entraînez-vous gratuitement, puis passez une session notée lorsque vous remplissez les conditions fixées par le propriétaire pour votre niveau.', 'لكل خدمة من الخدمات الـ{{ counts.services }} بنك أسئلة خاص بها. تدرّب مجانا ثم اجتز جولة مصحَّحة حين تستوفي الشروط التي حدّدها المالك لمستواك.'),
   'ctas': [cta(L('Open my QCM rounds', 'Ouvrir mes sessions QCM', 'افتح جولاتي'), '/account/qcm/', 'peak', True),
            cta(L('Verify a certificate', 'Vérifier un certificat', 'تحقق من شهادة'), '/certification/verify/')]},
  {'type': 'steps', 'id': 'cert-purposes', 'tier': 'i', 'focus': 3,
   'title': L('Three kinds of round', 'Trois types de session', 'ثلاثة أنواع من الجولات'),
   'items': [
     {'title': L('Practice', 'Entraînement', 'تدريب'), 'body': L('Free. A random service at your level, the right answers and explanations shown at the end. No certificate.', 'Gratuit. Un service tiré au hasard à votre niveau, avec les bonnes réponses et explications à la fin. Aucun certificat.', 'مجاني. خدمة عشوائية في مستواك مع عرض الإجابات الصحيحة والشروح في النهاية. دون شهادة.'), 'meta': L('Free', 'Gratuit', 'مجاني')},
     {'title': L('Certification', 'Certification', 'اعتماد'), 'body': L('A graded round that can issue a certificate. It may require a minimum level, a practice score, a task or a number of affiliates.', 'Une session notée pouvant délivrer un certificat. Elle peut exiger un niveau minimal, un score d’entraînement, une tâche ou un nombre d’affiliés.', 'جولة مصحَّحة قد تُصدر شهادة. وقد تتطلب حدا أدنى للمستوى أو نتيجة تدريب أو مهمة أو عددا من المُحالين.'), 'meta': L('Graded', 'Notée', 'مصحَّحة')},
     {'title': L('Upgrade', 'Montée de niveau', 'ترقية'), 'body': L('A graded round at the next level for an existing certificate holder. A fee for a new attempt may apply, set by the owner.', 'Une session notée au niveau supérieur pour un titulaire de certificat. Des frais pour une nouvelle tentative peuvent s’appliquer, fixés par le propriétaire.', 'جولة مصحَّحة في المستوى التالي لحامل شهادة. قد تُطبَّق رسوم لمحاولة جديدة يحددها المالك.'), 'meta': L('Graded', 'Notée', 'مصحَّحة')}]},
  {'type': 'steps', 'id': 'cert-levels', 'tier': 'i', 'focus': 3,
   'title': L('{{ cert.levels }} levels, one bank per service', '{{ cert.levels }} niveaux, une banque par service', '{{ cert.levels }} مستويات، وبنك لكل خدمة'),
   'items': [
     {'title': L('L1 Foundations', 'L1 Fondamentaux', 'L1 الأساسيات'), 'body': L('Vocabulary, evidence rules and the boundary of the service.', 'Vocabulaire, règles de preuve et limites du service.', 'المفردات وقواعد الأدلة وحدود الخدمة.')},
     {'title': L('L2 Practice', 'L2 Pratique', 'L2 الممارسة'), 'body': L('Applied judgement on realistic cases.', 'Jugement appliqué sur des cas réalistes.', 'حكم تطبيقي على حالات واقعية.')},
     {'title': L('L3 Review', 'L3 Revue', 'L3 المراجعة'), 'body': L('Checking other people’s work against a rubric.', 'Vérifier le travail d’autrui à l’aide d’une grille.', 'مراجعة عمل الآخرين وفق معيار.')},
     {'title': L('L4 Lead', 'L4 Direction', 'L4 القيادة'), 'body': L('Method, risk and sign-off for a team.', 'Méthode, risque et validation pour une équipe.', 'المنهج والمخاطر والاعتماد لفريق.')}]},
  {'type': 'prose', 'id': 'cert-rules', 'tier': 'e', 'focus': 3,
   'title': L('How a round is scored', 'Comment une session est notée', 'كيف تُصحَّح الجولة'),
   'paragraphs': [
     L('A question with one right answer scores when you pick it. A question with several right answers scores only when you tick exactly the right set: a partly right answer scores zero. The pass mark is shown on every round.', 'Une question à réponse unique rapporte un point si vous la choisissez. Une question à réponses multiples ne rapporte que si vous cochez exactement le bon ensemble : une réponse partielle vaut zéro. Le seuil de réussite est affiché sur chaque session.', 'السؤال بإجابة واحدة يُحتسب إذا اخترتها. والسؤال بعدة إجابات صحيحة لا يُحتسب إلا إذا حدّدت المجموعة الصحيحة تماما: الإجابة الجزئية تساوي صفرا. تظهر نسبة النجاح في كل جولة.'),
     L('The timer is kept by the server. Your answers are saved as you go; when time ends the round closes and is scored with what was saved. In graded rounds the right answers are not revealed, so the bank stays fair for the next person.', 'Le chronomètre est tenu par le serveur. Vos réponses sont enregistrées au fil de l’eau ; à la fin du temps, la session est close et notée avec ce qui a été enregistré. Dans les sessions notées, les bonnes réponses ne sont pas révélées, afin que la banque reste équitable pour la personne suivante.', 'يحتفظ الخادم بالمؤقت. تُحفظ إجاباتك أثناء التقدم، وعند انتهاء الوقت تُغلق الجولة وتُصحَّح بما حُفظ. لا تُكشف الإجابات الصحيحة في الجولات المصحَّحة ليبقى البنك عادلا للشخص التالي.'),
     L('A certificate is valid for {{ cert.validity_months }} months. Its code can be checked by anyone; the public record shows the service, level and dates and masks the holder’s name.', 'Un certificat est valable {{ cert.validity_months }} mois. Son code peut être vérifié par tous ; la fiche publique indique le service, le niveau et les dates, et masque le nom du titulaire.', 'الشهادة صالحة {{ cert.validity_months }} شهرا. يمكن لأي شخص التحقق من رمزها، ويعرض السجل العام الخدمة والمستوى والتواريخ ويخفي اسم الحامل.'),
     L('A certificate proves that you passed a round at a date. It is not a licence, a degree or a promise of a post.', 'Un certificat prouve que vous avez réussi une session à une date donnée. Ce n’est ni une licence, ni un diplôme, ni la promesse d’un poste.', 'تثبت الشهادة أنك نجحت في جولة بتاريخ معين. وهي ليست رخصة ولا شهادة جامعية ولا وعدا بمنصب.')]},
  {'type': 'definitions', 'id': 'cert-conditions', 'tier': 'x', 'focus': 2,
   'title': L('Requirements the owner can set', 'Conditions que le propriétaire peut fixer', 'شروط يمكن للمالك تحديدها'),
   'items': [
     {'term': L('Minimum level', 'Niveau minimal', 'الحد الأدنى للمستوى'), 'def': L('A certificate at a lower level first.', 'Un certificat de niveau inférieur d’abord.', 'شهادة من مستوى أدنى أولا.')},
     {'term': L('Practice score', 'Score d’entraînement', 'نتيجة التدريب'), 'def': L('A best practice score before a graded round.', 'Un meilleur score d’entraînement avant une session notée.', 'أفضل نتيجة تدريب قبل جولة مصحَّحة.')},
     {'term': L('Task', 'Tâche', 'مهمة'), 'def': L('A short deliverable reviewed by a person.', 'Un court livrable examiné par une personne.', 'منتج قصير تراجعه جهة بشرية.')},
     {'term': L('Affiliates', 'Affiliés', 'المُحالون'), 'def': L('A number of confirmed referred accounts.', 'Un nombre de comptes parrainés confirmés.', 'عدد من الحسابات المُحالة المؤكدة.')},
     {'term': L('Fee', 'Frais', 'رسوم'), 'def': L('A paid pass for a new round, verified like any other payment.', 'Un accès payant pour une nouvelle session, vérifié comme tout autre paiement.', 'تصريح مدفوع لجولة جديدة يُتحقق منه كأي دفع آخر.')},
     {'term': L('Waiting time', 'Délai d’attente', 'مدة الانتظار'), 'def': L('A pause between attempts and a daily limit.', 'Une pause entre les tentatives et une limite quotidienne.', 'فترة بين المحاولات وحد يومي.')}]},
  {'type': 'faq', 'id': 'cert-faq', 'tier': 'e', 'focus': 2,
   'title': L('Certification questions', 'Questions sur la certification', 'أسئلة عن الاعتماد'),
   'items': [
     {'q': L('Can I choose the service?', 'Puis-je choisir le service ?', 'هل أختار الخدمة؟'), 'a': L('Rounds draw a random service at your level, so the result reflects the whole level and not one favourite topic. The owner can also target a round at one service.', 'Les sessions tirent un service au hasard à votre niveau, afin que le résultat reflète le niveau entier et non un sujet préféré. Le propriétaire peut aussi cibler une session sur un service.', 'تسحب الجولات خدمة عشوائية في مستواك ليعكس الناتج المستوى كله لا موضوعا مفضلا. ويمكن للمالك أيضا تخصيص جولة لخدمة بعينها.')},
     {'q': L('What if the timer ends?', 'Que se passe-t-il si le chronomètre se termine ?', 'ماذا لو انتهى المؤقت؟'), 'a': L('The round closes and is scored with the answers already saved.', 'La session est close et notée avec les réponses déjà enregistrées.', 'تُغلق الجولة وتُصحَّح بالإجابات المحفوظة.')},
     {'q': L('How does someone check my certificate?', 'Comment quelqu’un vérifie-t-il mon certificat ?', 'كيف يتحقق شخص من شهادتي؟'), 'a': L('With the code, on the verification page or through the public JSON endpoint. The name is masked.', 'Avec le code, sur la page de vérification ou via le point d’accès JSON public. Le nom est masqué.', 'بالرمز في صفحة التحقق أو عبر نقطة JSON العامة. الاسم مخفي.')},
     {'q': L('Can a certificate be withdrawn?', 'Un certificat peut-il être retiré ?', 'هل يمكن سحب شهادة؟'), 'a': L('Yes. If it was issued in error or obtained unfairly it is revoked, and the public record then says so.', 'Oui. S’il a été délivré par erreur ou obtenu de façon déloyale, il est révoqué et la fiche publique l’indique.', 'نعم. إن صدرت بالخطأ أو نيلت بغير وجه حق تُلغى ويذكر السجل العام ذلك.')}]},
  {'type': 'cta', 'id': 'cert-final', 'gradient': 'peak',
   'title': L('Start with a **free practice round.**', 'Commencez par un **entraînement gratuit.**', 'ابدأ **بجولة تدريب مجانية.**'),
   'body': L('You need an account. A practice round costs nothing and teaches you the format.', 'Un compte est nécessaire. Un entraînement ne coûte rien et vous apprend le format.', 'تحتاج إلى حساب. التدريب بلا مقابل ويعلّمك الصيغة.'),
   'ctas': [cta(L('Open my QCM rounds', 'Ouvrir mes sessions QCM', 'افتح جولاتي'), '/account/qcm/', 'peak', True)]},
 ]}

for p in (affiliate, certification):
    with open(os.path.join(ROOT, 'data/content/pages', p['slug'] + '.json'), 'w', encoding='utf-8') as f:
        json.dump(p, f, ensure_ascii=False, indent=1); f.write('\n')
print('ok')
