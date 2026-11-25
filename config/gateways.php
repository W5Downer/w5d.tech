<?php
/**
 * Payment methods. Everything here is owner-configurable in the portal (Payment methods menu); nothing is secret in this file.
 * kind: manual = the buyer sends money and a person verifies; online = hosted payment page + server-side verification.
 * Field types: bool | text | secret | select | int | pct (0-100, up to 3 decimals) | dec (amount, up to 3 decimals) | url. A "req" field must be filled for the method to be offered.
 * Secrets are stored encrypted (src/Settings.php) and are never shown again after saving.
 */
$tri = fn(string $en, string $fr, string $ar): array => ['en' => $en, 'fr' => $fr, 'ar' => $ar];
// Fee fields shared by the manual local wallets (defaults 0: the sender usually pays the operator, not us; the owner can change it).
$localFees = fn(string $pct = '0', string $fixed = '0'): array => [
    ['k' => 'fee_pct', 't' => 'pct', 'label' => 'Transaction fee (% of the amount)', 'default' => $pct, 'min' => 0, 'max' => 20],
    ['k' => 'fee_fixed', 't' => 'dec', 'label' => 'Fixed fee per transaction (TND)', 'default' => $fixed, 'min' => 0, 'max' => 100],
    ['k' => 'fee_payer', 't' => 'select', 'label' => 'Who bears the fee', 'options' => ['seller' => 'We absorb it (the buyer pays the published price)', 'buyer' => 'Added to the buyer total (we receive the full price)'], 'default' => 'seller'],
];
return [
    'd17' => [
        'name' => 'D17 (La Poste Tunisienne wallet)', 'kind' => 'manual', 'tn_only' => true, 'icon' => 'smartphone',
        'doc' => 'Manual: the buyer sends the amount to your D17 number and gives the transaction reference; an administrator verifies it. Tunisian residents only.',
        'sender' => 'gsm', 'show' => [[$tri('Send to', 'Envoyer au', 'أرسل إلى'), 'gsm', true], [$tri('Account holder', 'Titulaire', 'صاحب الحساب'), 'holder', false]],
        'guide' => [
            'what' => $tri('D17 is the wallet of La Poste Tunisienne. A buyer who has D17 sends money to your D17 number from the app.', 'D17 est le portefeuille de La Poste Tunisienne. Un acheteur qui a D17 envoie l’argent à votre numéro D17 depuis l’application.', 'D17 هي محفظة البريد التونسي. المشتري الذي لديه D17 يرسل المال إلى رقمك من التطبيق.'),
            'owner' => [$tri('Open a D17 account in the D17 app (identity card and your postal account).', 'Ouvrez un compte D17 dans l’application D17 (carte d’identité et compte postal).', 'افتح حساب D17 في التطبيق (بطاقة التعريف والحساب البريدي).'), $tri('Enter your D17 mobile number here and switch the method on.', 'Saisissez ici votre numéro mobile D17 et activez le moyen.', 'أدخل هنا رقم هاتف D17 الخاص بك وفعّل الوسيلة.'), $tri('When a buyer submits a reference, find the same amount and reference in the D17 app history, then press Verify in Payments.', 'Quand un acheteur soumet une référence, retrouvez le même montant et la même référence dans l’historique D17, puis cliquez sur Vérifier dans Paiements.', 'عند إرسال المشتري لمرجع، ابحث عن المبلغ والمرجع نفسيهما في سجل D17 ثم اضغط تحقق في المدفوعات.')],
            'buyer' => [$tri('Open D17, choose “send money”, enter our number and the exact amount.', 'Ouvrez D17, choisissez « envoyer de l’argent », saisissez notre numéro et le montant exact.', 'افتح D17 واختر «إرسال مال» وأدخل رقمنا والمبلغ بالضبط.'), $tri('Copy the transaction reference D17 shows, then paste it on our payment page.', 'Copiez la référence que D17 affiche, puis collez-la sur notre page de paiement.', 'انسخ مرجع العملية الذي يعرضه D17 ثم الصقه في صفحة الدفع لدينا.')],
            'check' => $tri('Same amount, same reference, same sender number, received today. Never verify on a screenshot alone.', 'Même montant, même référence, même numéro émetteur, reçu aujourd’hui. Ne vérifiez jamais sur une simple capture d’écran.', 'المبلغ نفسه والمرجع نفسه ورقم المرسل نفسه واستلام اليوم. لا تتحقق بلقطة شاشة وحدها.'),
        ],
        'env' => ['enabled' => 'PAYMENT_D17_ENABLED', 'gsm' => 'PAYMENT_D17_GSM', 'holder' => 'PAYMENT_D17_NAME', 'note' => 'PAYMENT_D17_NOTE'],
        'fields' => [
            ['k' => 'gsm', 't' => 'text', 'label' => 'D17 receiving number (+216…)', 'req' => true, 'pattern' => '/^\+?\d{8,15}$/'],
            ['k' => 'holder', 't' => 'text', 'label' => 'Account holder name', 'default' => 'W5D TECH'],
            ['k' => 'note', 't' => 'text', 'label' => 'Note shown to the buyer', 'default' => ''],
            ['k' => 'fee_pct', 't' => 'pct', 'label' => 'Transaction fee (% of the amount)', 'default' => '1', 'min' => 0, 'max' => 20],
            ['k' => 'fee_fixed', 't' => 'dec', 'label' => 'Fixed fee per transaction (TND)', 'default' => '0', 'min' => 0, 'max' => 100],
            ['k' => 'fee_payer', 't' => 'select', 'label' => 'Who bears the fee', 'options' => ['seller' => 'We absorb it (the buyer pays the published price)', 'buyer' => 'Added to the buyer total (we receive the full price)'], 'default' => 'seller'],
        ],
        'fee_note' => 'Default 1 %. La Poste Tunisienne publishes a merchant tariff that can be lower (free for very small payments, about half a percent above a small threshold): confirm the rate in your own D17 merchant agreement and edit it here.',
    ],
    'bank' => [
        'name' => 'Bank transfer (RIB / IBAN)', 'kind' => 'manual', 'icon' => 'landmark', 'sender' => 'name',
        'show' => [[$tri('Bank', 'Banque', 'البنك'), 'bank_name', false], [$tri('Account holder', 'Titulaire', 'صاحب الحساب'), 'holder', false], [$tri('RIB / IBAN', 'RIB / IBAN', 'RIB / IBAN'), 'iban', true], ['BIC / SWIFT', 'bic', true]],
        'guide' => [
            'what' => $tri('A classic transfer from the buyer’s bank to your company account.', 'Un virement classique de la banque de l’acheteur vers le compte de votre société.', 'تحويل بنكي عادي من بنك المشتري إلى حساب شركتك.'),
            'owner' => [$tri('Enter the holder name and the RIB/IBAN of the account that receives the money (a company account once the company exists).', 'Saisissez le titulaire et le RIB/IBAN du compte qui reçoit l’argent (un compte de société une fois la société créée).', 'أدخل اسم صاحب الحساب وRIB/IBAN للحساب المستقبِل (حساب شركة بعد تأسيسها).'), $tri('When a buyer submits the transfer reference, look for it on your bank statement, then Verify.', 'Quand un acheteur soumet la référence du virement, cherchez-la sur votre relevé bancaire, puis Vérifier.', 'عند إرسال المشتري مرجع التحويل ابحث عنه في كشف حسابك ثم تحقق.')],
            'buyer' => [$tri('Make a transfer to the RIB/IBAN shown, with the amount and, in the transfer label, the order reference.', 'Faites un virement vers le RIB/IBAN indiqué, avec le montant et, dans le libellé, la référence de commande.', 'حوّل إلى RIB/IBAN المعروض بالمبلغ وضع مرجع الطلب في بيان التحويل.'), $tri('Enter the bank’s transfer reference on our payment page.', 'Saisissez la référence du virement donnée par la banque sur notre page de paiement.', 'أدخل مرجع التحويل الذي يعطيه البنك في صفحة الدفع لدينا.')],
            'check' => $tri('Funds visible on the statement with the same amount; transfers can take one or two working days.', 'Fonds visibles sur le relevé avec le même montant ; un virement peut prendre un à deux jours ouvrés.', 'الأموال ظاهرة في الكشف بالمبلغ نفسه؛ قد يستغرق التحويل يوما أو يومين عمل.'),
        ],
        'doc' => 'Manual: the buyer wires the amount to your account and enters the transfer reference; an administrator verifies it on your bank statement.',
        'fields' => [
            ['k' => 'bank_name', 't' => 'text', 'label' => 'Bank name', 'default' => ''],
            ['k' => 'holder', 't' => 'text', 'label' => 'Account holder', 'req' => true],
            ['k' => 'iban', 't' => 'text', 'label' => 'RIB / IBAN', 'req' => true, 'pattern' => '/^[A-Za-z0-9 ]{10,40}$/'],
            ['k' => 'bic', 't' => 'text', 'label' => 'BIC / SWIFT (optional)', 'pattern' => '/^[A-Za-z0-9]{0,11}$/'],
            ['k' => 'note', 't' => 'text', 'label' => 'Note shown to the buyer', 'default' => ''],
            ['k' => 'fee_pct', 't' => 'pct', 'label' => 'Transaction fee (% of the amount)', 'default' => '0', 'min' => 0, 'max' => 20],
            ['k' => 'fee_fixed', 't' => 'dec', 'label' => 'Fixed fee per transaction (TND)', 'default' => '0', 'min' => 0, 'max' => 100],
            ['k' => 'fee_payer', 't' => 'select', 'label' => 'Who bears the fee', 'options' => ['seller' => 'We absorb it (the buyer pays the published price)', 'buyer' => 'Added to the buyer total (we receive the full price)'], 'default' => 'seller'],
        ],
        'fee_note' => 'Default 0: incoming transfers are normally free; the cost, if any, depends on your bank (check its tariff for incoming transfers).',
    ],
    'flouci' => [
        'name' => 'Flouci (business API)', 'kind' => 'online', 'icon' => 'wallet', 'currency' => 'TND', 'unit' => 'millimes',
        'doc' => 'Hosted page (Flouci wallet, bank card/e-dinar). Auth: Bearer PUBLIC:PRIVATE. Same base URL for sandbox and production: only the keys change. Webhook is signed (X-Flouci-Signature) with your private token and always re-verified server-side. Docs: docs.flouci.com.',
        'webhook' => true,
        'fields' => [
            ['k' => 'public', 't' => 'text', 'label' => 'Public token (app token)', 'req' => true, 'pattern' => '/^[A-Za-z0-9_\-\.]{6,200}$/'],
            ['k' => 'private', 't' => 'secret', 'label' => 'Private token (app secret)', 'req' => true],
            ['k' => 'base_url', 't' => 'url', 'label' => 'API base URL', 'default' => 'https://developers.flouci.com'],
            ['k' => 'webhook_version', 't' => 'select', 'label' => 'Webhook format', 'options' => ['2' => '2 — signed JSON POST (recommended)', '1' => '1 — signed GET'], 'default' => '2'],
            ['k' => 'accept_card', 't' => 'bool', 'label' => 'Accept bank cards on the Flouci page', 'default' => '1'],
            ['k' => 'timeout', 't' => 'int', 'label' => 'Payment session timeout (seconds)', 'default' => '1200', 'min' => 120, 'max' => 86400],
            ['k' => 'fee_pct', 't' => 'pct', 'label' => 'Transaction fee: Tunisian cards and wallets (% of the amount)', 'default' => '1.3', 'min' => 0, 'max' => 20],
            ['k' => 'fee_intl_pct', 't' => 'pct', 'label' => 'Transaction fee: international cards (% of the amount, information only)', 'default' => '3', 'min' => 0, 'max' => 20],
            ['k' => 'fee_fixed', 't' => 'dec', 'label' => 'Fixed fee per transaction (TND)', 'default' => '0', 'min' => 0, 'max' => 100],
            ['k' => 'fee_payer', 't' => 'select', 'label' => 'Who bears the fee', 'options' => ['seller' => 'We absorb it (the buyer pays the published price)', 'buyer' => 'Added to the buyer total (we receive the full price)'], 'default' => 'seller'],
        ],
        'fee_note' => 'Published by Flouci (business offer): 1.3 % excl. VAT for Tunisian cards and wallets, 3 % excl. VAT for international cards, no set-up fee. VAT is charged on top of the fee: check your contract.',
    ],
    'konnect' => [
        'name' => 'Konnect (wallet, card, e-DINAR)', 'kind' => 'online', 'icon' => 'credit-card', 'currency' => 'TND', 'unit' => 'millimes',
        'doc' => 'Hosted page. Header x-api-key; receiverWalletId from your Konnect dashboard. The webhook only carries a payment reference: the status is always re-read from Konnect. Docs: docs.konnect.network.',
        'webhook' => true,
        'fields' => [
            ['k' => 'mode', 't' => 'select', 'label' => 'Environment', 'options' => ['sandbox' => 'Sandbox (test money)', 'live' => 'Live (real money)'], 'default' => 'sandbox'],
            ['k' => 'api_key', 't' => 'secret', 'label' => 'API key', 'req' => true],
            ['k' => 'wallet_id', 't' => 'text', 'label' => 'Receiver wallet ID', 'req' => true, 'pattern' => '/^[A-Za-z0-9_\-]{6,80}$/'],
            ['k' => 'base_url', 't' => 'url', 'label' => 'API base URL override (leave empty for the default of the environment)', 'default' => ''],
            ['k' => 'lifespan', 't' => 'int', 'label' => 'Payment lifespan (minutes)', 'default' => '20', 'min' => 5, 'max' => 1440],
            ['k' => 'fee_pct', 't' => 'pct', 'label' => 'Transaction fee: Tunisian cards and wallets (% of the amount)', 'default' => '1.3', 'min' => 0, 'max' => 20],
            ['k' => 'fee_intl_pct', 't' => 'pct', 'label' => 'Transaction fee: international cards (% of the amount, information only)', 'default' => '2.9', 'min' => 0, 'max' => 20],
            ['k' => 'fee_fixed', 't' => 'dec', 'label' => 'Fixed fee per transaction (TND)', 'default' => '0', 'min' => 0, 'max' => 100],
            ['k' => 'fee_payer', 't' => 'select', 'label' => 'Who bears the fee', 'options' => ['seller' => 'We absorb it (the buyer pays the published price)', 'buyer' => 'Added to the buyer total (we receive the full price)'], 'default' => 'seller'],
        ],
        'fee_note' => 'Published by Konnect: 1.3 % for Tunisian cards and e-DINAR, 2.9 % for international cards, no subscription. Withdrawals to your bank carry a separate fee (about 2 TND per transfer) that is not a per-sale fee: check your Konnect dashboard.',
    ],
    'paymee' => [
        'name' => 'Paymee (wallet, card, e-DINAR)', 'kind' => 'online', 'icon' => 'banknote', 'currency' => 'TND', 'unit' => 'dinars',
        'doc' => 'Hosted page. Header Authorization: Token API_TOKEN; vendor = your account ID. Webhook check_sum = md5(token + status + API token) is checked, then the status is re-read from Paymee. Docs: paymee.tn.',
        'webhook' => true,
        'fields' => [
            ['k' => 'mode', 't' => 'select', 'label' => 'Environment', 'options' => ['sandbox' => 'Sandbox (test money)', 'live' => 'Live (real money)'], 'default' => 'sandbox'],
            ['k' => 'api_token', 't' => 'secret', 'label' => 'API token', 'req' => true],
            ['k' => 'vendor', 't' => 'text', 'label' => 'Vendor (account ID)', 'req' => true, 'pattern' => '/^[A-Za-z0-9_\-]{1,40}$/'],
            ['k' => 'base_url', 't' => 'url', 'label' => 'API base URL override (leave empty for the default of the environment)', 'default' => ''],
            ['k' => 'fee_pct', 't' => 'pct', 'label' => 'Transaction fee: Tunisian cards and wallets (% of the amount)', 'default' => '1.9', 'min' => 0, 'max' => 20],
            ['k' => 'fee_intl_pct', 't' => 'pct', 'label' => 'Transaction fee: international cards (% of the amount, information only)', 'default' => '2.9', 'min' => 0, 'max' => 20],
            ['k' => 'fee_fixed', 't' => 'dec', 'label' => 'Fixed fee per transaction (TND)', 'default' => '0', 'min' => 0, 'max' => 100],
            ['k' => 'fee_payer', 't' => 'select', 'label' => 'Who bears the fee', 'options' => ['seller' => 'We absorb it (the buyer pays the published price)', 'buyer' => 'Added to the buyer total (we receive the full price)'], 'default' => 'seller'],
        ],
        'fee_note' => 'Rates reported for Paymee in 2026: 1.9 % for Tunisian cards and e-DINAR, 2.9 % for international cards. These come from secondary sources: confirm them in your Paymee contract and edit here.',
    ],

    // ------------------------------------------------------------------ local client-to-client (P2P) wallets: manual, verified by a person
    'flouci_p2p' => [
        'name' => 'Flouci wallet (send to our Flouci number)', 'kind' => 'manual', 'tn_only' => true, 'icon' => 'wallet', 'sender' => 'gsm',
        'doc' => 'Manual, like D17: the buyer sends the amount from their Flouci wallet to your Flouci number and gives the transaction reference; an administrator verifies it in the Flouci app. This is the wallet-to-wallet transfer, not the Flouci business API (that one is the separate “Flouci” method).',
        'show' => [[$tri('Send to (Flouci number)', 'Envoyer au (numéro Flouci)', 'أرسل إلى (رقم Flouci)'), 'gsm', true], [$tri('Account holder', 'Titulaire', 'صاحب الحساب'), 'holder', false]],
        'guide' => [
            'what' => $tri('Flouci is a Tunisian wallet app. Two Flouci users can send each other money by phone number, instantly, from the app.', 'Flouci est un portefeuille mobile tunisien. Deux utilisateurs Flouci peuvent s’envoyer de l’argent par numéro de téléphone, instantanément, depuis l’application.', 'Flouci محفظة تونسية على الهاتف. يستطيع مستخدمان تبادل المال برقم الهاتف فورا من التطبيق.'),
            'owner' => [$tri('Install Flouci and create your personal or business wallet (identity card); complete the identity level that allows receiving.', 'Installez Flouci et créez votre portefeuille personnel ou professionnel (carte d’identité) ; complétez le niveau d’identité qui autorise la réception.', 'ثبّت Flouci وأنشئ محفظتك الشخصية أو المهنية (بطاقة التعريف) وأكمل مستوى الهوية الذي يسمح بالاستقبال.'), $tri('Enter your Flouci phone number and holder name here, switch the method on.', 'Saisissez ici votre numéro Flouci et le nom du titulaire, puis activez le moyen.', 'أدخل رقم Flouci واسم صاحب المحفظة هنا وفعّل الوسيلة.'), $tri('Check the daily limits and the fee of your wallet level in the Flouci app: a transfer above the limit will fail for the buyer.', 'Vérifiez les plafonds journaliers et les frais de votre niveau de portefeuille dans l’application : un transfert au-dessus du plafond échouera pour l’acheteur.', 'راجع السقف اليومي ورسوم مستوى محفظتك في التطبيق: التحويل فوق السقف يفشل عند المشتري.'), $tri('For each submitted reference, open the transaction history in Flouci, match amount + reference + sender number, then press Verify.', 'Pour chaque référence soumise, ouvrez l’historique Flouci, faites correspondre montant + référence + numéro émetteur, puis Vérifier.', 'لكل مرجع مُرسل افتح سجل Flouci وطابق المبلغ والمرجع ورقم المرسل ثم اضغط تحقق.')],
            'buyer' => [$tri('In Flouci choose “send money”, enter our Flouci number and the exact amount.', 'Dans Flouci choisissez « envoyer de l’argent », saisissez notre numéro Flouci et le montant exact.', 'في Flouci اختر «إرسال مال» وأدخل رقم Flouci لدينا والمبلغ بالضبط.'), $tri('Copy the transaction number from the confirmation, then paste it here.', 'Copiez le numéro de transaction de la confirmation, puis collez-le ici.', 'انسخ رقم العملية من التأكيد ثم الصقه هنا.')],
            'check' => $tri('Wallet-to-wallet transfers are instant: if it is not in the history within minutes, do not verify.', 'Les transferts de portefeuille à portefeuille sont instantanés : s’il n’est pas dans l’historique après quelques minutes, ne vérifiez pas.', 'التحويل بين المحافظ فوري: إن لم يظهر في السجل بعد دقائق فلا تتحقق.'),
        ],
        'fields' => array_merge([
            ['k' => 'gsm', 't' => 'text', 'label' => 'Flouci receiving number (+216…)', 'req' => true, 'pattern' => '/^\+?\d{8,15}$/'],
            ['k' => 'holder', 't' => 'text', 'label' => 'Wallet holder name', 'default' => 'W5D TECH'],
            ['k' => 'note', 't' => 'text', 'label' => 'Note shown to the buyer', 'default' => ''],
        ], $localFees('0', '0')),
        'fee_note' => 'Default 0 for you. Flouci’s own fee for the sender depends on the wallet level and the amount (small “à Dourou” transfers cost the sender a few millimes; larger transfers to other institutions carry a percentage). These come from Flouci web pages and can change: check the tariff in your Flouci app, and only set a fee here if you decide to pass a cost on.',
    ],
    'mobiflouss' => [
        'name' => 'Mobiflouss (Ooredoo, e-DINAR SMART)', 'kind' => 'manual', 'tn_only' => true, 'icon' => 'smartphone', 'sender' => 'gsm',
        'doc' => 'Manual: the buyer dials *112# on an Ooredoo line, uses the Mobiflouss menu to send money from their e-DINAR SMART card to your registered number, and gives the transaction reference.',
        'show' => [[$tri('Send to (Mobiflouss number)', 'Envoyer au (numéro Mobiflouss)', 'أرسل إلى (رقم Mobiflouss)'), 'gsm', true], [$tri('Account holder', 'Titulaire', 'صاحب الحساب'), 'holder', false]],
        'guide' => [
            'what' => $tri('Mobiflouss is the mobile payment service of Ooredoo with La Poste Tunisienne: money moves between e-DINAR SMART cards through a USSD menu (*112#), no data connection needed.', 'Mobiflouss est le service de paiement mobile d’Ooredoo avec La Poste Tunisienne : l’argent circule entre cartes e-DINAR SMART par un menu USSD (*112#), sans connexion de données.', 'Mobiflouss خدمة دفع عبر الهاتف من Ooredoo والبريد التونسي: ينتقل المال بين بطاقات e-DINAR SMART عبر قائمة USSD (*112#) دون إنترنت.'),
            'owner' => [$tri('Get an e-DINAR SMART card at a post office (identity card; the card has a small issue fee and an annual fee set by La Poste).', 'Obtenez une carte e-DINAR SMART dans un bureau de poste (carte d’identité ; la carte a un petit frais d’émission et un frais annuel fixés par La Poste).', 'احصل على بطاقة e-DINAR SMART من مكتب بريد (بطاقة التعريف؛ للبطاقة رسم إصدار صغير ورسم سنوي تحددهما البريد).'), $tri('Register the line: dial *112# on your Ooredoo SIM, follow the menu, give your identity-card number and the card number. Use that line’s number here.', 'Enregistrez la ligne : composez *112# avec votre SIM Ooredoo, suivez le menu, donnez le numéro de carte d’identité et celui de la carte. Utilisez ici le numéro de cette ligne.', 'سجّل الخط: اطلب *112# بشريحة Ooredoo واتبع القائمة وأدخل رقم بطاقة التعريف ورقم البطاقة. استعمل رقم هذا الخط هنا.'), $tri('Enter that number and the holder name here, switch the method on.', 'Saisissez ce numéro et le titulaire ici, puis activez le moyen.', 'أدخل هذا الرقم واسم الحامل هنا وفعّل الوسيلة.'), $tri('For each reference: dial *112#, open the transaction history (or check the SMS), match amount + reference, then press Verify.', 'Pour chaque référence : composez *112#, ouvrez l’historique (ou vérifiez le SMS), faites correspondre montant + référence, puis Vérifier.', 'لكل مرجع: اطلب *112# وافتح السجل (أو راجع الرسالة القصيرة) وطابق المبلغ والمرجع ثم تحقق.')],
            'buyer' => [$tri('On an Ooredoo line registered to Mobiflouss, dial *112#, choose the transfer option, enter our number and the exact amount, confirm with your PIN.', 'Avec une ligne Ooredoo inscrite à Mobiflouss, composez *112#, choisissez le transfert, saisissez notre numéro et le montant exact, confirmez avec votre code PIN.', 'من خط Ooredoo مسجل في Mobiflouss اطلب *112# واختر التحويل وأدخل رقمنا والمبلغ بالضبط وأكّد برمز PIN.'), $tri('Keep the SMS confirmation and paste its transaction number here.', 'Gardez le SMS de confirmation et collez son numéro de transaction ici.', 'احتفظ برسالة التأكيد والصق رقم العملية هنا.')],
            'check' => $tri('The sender pays an operator fee per transfer (reported around 1 TND). Verify the amount received, not the amount sent.', 'L’émetteur paie des frais d’opérateur par transfert (environ 1 TND selon les sources). Vérifiez le montant reçu, pas le montant envoyé.', 'يدفع المرسل رسم المشغّل لكل تحويل (نحو 1 دينار حسب المصادر). تحقق من المبلغ المستلَم لا المرسَل.'),
        ],
        'fields' => array_merge([
            ['k' => 'gsm', 't' => 'text', 'label' => 'Mobiflouss receiving number (+216…)', 'req' => true, 'pattern' => '/^\+?\d{8,15}$/'],
            ['k' => 'holder', 't' => 'text', 'label' => 'Card holder name', 'default' => 'W5D TECH'],
            ['k' => 'note', 't' => 'text', 'label' => 'Note shown to the buyer', 'default' => ''],
        ], $localFees('0', '0')),
        'fee_note' => 'Operator tariff reported by web sources: about 1 TND fixed per transfer, charged to the sender; card issue and annual fees apply to the card holder. Not verified against a signed contract: check Ooredoo and La Poste, then set a fee here only if you pass it on.',
    ],
    'mobimoney' => [
        'name' => 'Mobimoney (Orange, e-DINAR SMART)', 'kind' => 'manual', 'tn_only' => true, 'icon' => 'smartphone', 'sender' => 'gsm',
        'doc' => 'Manual: the buyer dials *119# on an Orange line and sends money to your registered number with Mobimoney, then gives the transaction reference.',
        'show' => [[$tri('Send to (Mobimoney number)', 'Envoyer au (numéro Mobimoney)', 'أرسل إلى (رقم Mobimoney)'), 'gsm', true], [$tri('Account holder', 'Titulaire', 'صاحب الحساب'), 'holder', false]],
        'guide' => [
            'what' => $tri('Mobimoney is Orange Tunisie’s mobile money service on an e-DINAR SMART card, used from the *119# menu.', 'Mobimoney est le service d’argent mobile d’Orange Tunisie sur carte e-DINAR SMART, utilisé depuis le menu *119#.', 'Mobimoney خدمة الأموال عبر الهاتف من Orange تونس على بطاقة e-DINAR SMART وتُستعمل من قائمة *119#.'),
            'owner' => [$tri('Get an e-DINAR SMART card at a post office and register your Orange line to Mobimoney (dial *119# and follow the menu).', 'Obtenez une carte e-DINAR SMART dans un bureau de poste et inscrivez votre ligne Orange à Mobimoney (composez *119# et suivez le menu).', 'احصل على بطاقة e-DINAR SMART من مكتب بريد وسجّل خط Orange في Mobimoney (اطلب *119# واتبع القائمة).'), $tri('Enter the registered number and holder name here, switch the method on.', 'Saisissez ici le numéro inscrit et le titulaire, puis activez le moyen.', 'أدخل الرقم المسجل واسم الحامل هنا وفعّل الوسيلة.'), $tri('For each reference, check the Mobimoney history or SMS, match amount + reference, then press Verify.', 'Pour chaque référence, vérifiez l’historique ou le SMS Mobimoney, faites correspondre montant + référence, puis Vérifier.', 'لكل مرجع راجع سجل Mobimoney أو الرسالة وطابق المبلغ والمرجع ثم تحقق.')],
            'buyer' => [$tri('On an Orange line registered to Mobimoney, dial *119#, choose the transfer option, enter our number and the exact amount, confirm with your PIN.', 'Avec une ligne Orange inscrite à Mobimoney, composez *119#, choisissez le transfert, saisissez notre numéro et le montant exact, confirmez avec votre code PIN.', 'من خط Orange مسجل في Mobimoney اطلب *119# واختر التحويل وأدخل رقمنا والمبلغ بالضبط وأكّد برمز PIN.'), $tri('Paste the transaction number from the SMS here.', 'Collez ici le numéro de transaction du SMS.', 'الصق رقم العملية من الرسالة هنا.')],
            'check' => $tri('Check the amount received (the sender may pay an operator fee on top).', 'Vérifiez le montant reçu (l’émetteur peut payer des frais d’opérateur en plus).', 'تحقق من المبلغ المستلَم (قد يدفع المرسل رسم المشغّل زيادة).'),
        ],
        'fields' => array_merge([
            ['k' => 'gsm', 't' => 'text', 'label' => 'Mobimoney receiving number (+216…)', 'req' => true, 'pattern' => '/^\+?\d{8,15}$/'],
            ['k' => 'holder', 't' => 'text', 'label' => 'Card holder name', 'default' => 'W5D TECH'],
            ['k' => 'note', 't' => 'text', 'label' => 'Note shown to the buyer', 'default' => ''],
        ], $localFees('0', '0')),
        'fee_note' => 'Operator tariffs are published by Orange and La Poste and change: check them and set a fee here only if you pass a cost on.',
    ],
    'telecomoney' => [
        'name' => 'Telecomoney (Tunisie Telecom)', 'kind' => 'manual', 'tn_only' => true, 'icon' => 'smartphone', 'sender' => 'gsm',
        'doc' => 'Manual: the buyer sends money from their Telecomoney wallet (Tunisie Telecom) to your number and gives the transaction reference. Tunisie Telecom presents it as sending money to relatives whatever their operator.',
        'show' => [[$tri('Send to (Telecomoney number)', 'Envoyer au (numéro Telecomoney)', 'أرسل إلى (رقم Telecomoney)'), 'gsm', true], [$tri('Account holder', 'Titulaire', 'صاحب الحساب'), 'holder', false]],
        'guide' => [
            'what' => $tri('Telecomoney is the mobile wallet of Tunisie Telecom. It has per-day transfer counts and amount bands set by the operator.', 'Telecomoney est le portefeuille mobile de Tunisie Telecom. Le nombre de transferts par jour et les tranches de montants sont fixés par l’opérateur.', 'Telecomoney محفظة Tunisie Telecom. عدد التحويلات اليومية وشرائح المبالغ يحددها المشغّل.'),
            'owner' => [$tri('Open a Telecomoney wallet with Tunisie Telecom (identity card) and check the limits of your wallet level.', 'Ouvrez un portefeuille Telecomoney chez Tunisie Telecom (carte d’identité) et vérifiez les plafonds de votre niveau.', 'افتح محفظة Telecomoney لدى Tunisie Telecom (بطاقة التعريف) وراجع سقوف مستواك.'), $tri('Enter the wallet number and holder name here, switch the method on.', 'Saisissez ici le numéro du portefeuille et le titulaire, puis activez le moyen.', 'أدخل رقم المحفظة واسم الحامل هنا وفعّل الوسيلة.'), $tri('For each reference, check the wallet history or SMS, match amount + reference, then Verify.', 'Pour chaque référence, vérifiez l’historique ou le SMS, faites correspondre montant + référence, puis Vérifier.', 'لكل مرجع راجع السجل أو الرسالة وطابق المبلغ والمرجع ثم تحقق.')],
            'buyer' => [$tri('In the Telecomoney menu or app choose “send money”, enter our number and the exact amount, confirm.', 'Dans le menu ou l’application Telecomoney choisissez « envoyer de l’argent », saisissez notre numéro et le montant exact, confirmez.', 'في قائمة Telecomoney أو تطبيقها اختر «إرسال مال» وأدخل رقمنا والمبلغ بالضبط وأكّد.'), $tri('Paste the transaction number here.', 'Collez ici le numéro de transaction.', 'الصق رقم العملية هنا.')],
            'check' => $tri('Amounts above the per-transfer band must be split: ask the buyer to use another method instead.', 'Les montants au-dessus de la tranche par transfert doivent être scindés : proposez plutôt un autre moyen à l’acheteur.', 'المبالغ فوق شريحة التحويل الواحد يجب تقسيمها: اقترح على المشتري وسيلة أخرى.'),
        ],
        'fields' => array_merge([
            ['k' => 'gsm', 't' => 'text', 'label' => 'Telecomoney receiving number (+216…)', 'req' => true, 'pattern' => '/^\+?\d{8,15}$/'],
            ['k' => 'holder', 't' => 'text', 'label' => 'Wallet holder name', 'default' => 'W5D TECH'],
            ['k' => 'note', 't' => 'text', 'label' => 'Note shown to the buyer', 'default' => ''],
        ], $localFees('0', '0')),
        'fee_note' => 'Tunisie Telecom publishes per-transfer fees by amount band: check them and set a fee here only if you pass a cost on.',
    ],
    'poste' => [
        'name' => 'La Poste (RIP postal account or Mandat Minute)', 'kind' => 'manual', 'icon' => 'landmark', 'sender' => 'name',
        'doc' => 'Manual: the buyer pays into your postal account (RIP / CCP) by transfer or at a post office, or sends a Mandat Minute that you collect, then gives the transfer or mandat reference.',
        'show' => [[$tri('Account holder', 'Titulaire', 'صاحب الحساب'), 'holder', false], [$tri('RIP (postal account identity)', 'RIP (relevé d’identité postale)', 'RIP (الهوية البريدية)'), 'rip', true], [$tri('Mandat Minute to', 'Mandat Minute à', 'حوالة سريعة إلى'), 'mandat_name', false]],
        'guide' => [
            'what' => $tri('Two ways to pay a person or company at La Poste Tunisienne: a transfer to the postal account using its RIP, or a Mandat Minute (cash sent through a post office with a confidential code).', 'Deux façons de payer une personne ou une société à La Poste Tunisienne : un virement vers le compte postal avec son RIP, ou un Mandat Minute (espèces envoyées via un bureau de poste avec un code confidentiel).', 'طريقتان للدفع إلى شخص أو شركة عبر البريد التونسي: تحويل إلى الحساب البريدي برقم RIP أو حوالة سريعة (نقد يُرسل عبر مكتب بريد برمز سري).'),
            'owner' => [$tri('Get the RIP of your postal account from your La Poste app, card or post office (a statement of the account identity) and enter it here with the holder name.', 'Obtenez le RIP de votre compte postal depuis l’application La Poste, votre carte ou un bureau (relevé d’identité du compte) et saisissez-le ici avec le titulaire.', 'احصل على RIP حسابك البريدي من تطبيق البريد أو بطاقتك أو مكتب البريد وأدخله هنا مع اسم صاحب الحساب.'), $tri('If you accept Mandat Minute, write the name that appears on your identity card in “Mandat Minute to”.', 'Si vous acceptez le Mandat Minute, écrivez dans « Mandat Minute à » le nom figurant sur votre carte d’identité.', 'إن قبلت الحوالة السريعة فاكتب في «حوالة سريعة إلى» الاسم الظاهر على بطاقة تعريفك.'), $tri('RIP transfers: match amount + reference in your postal statement, then Verify. Mandat Minute: collect it (identity card + the 12-digit confidential code the buyer gives you), then Verify.', 'Virements RIP : faites correspondre montant + référence sur votre relevé postal, puis Vérifier. Mandat Minute : encaissez-le (carte d’identité + code confidentiel à 12 chiffres donné par l’acheteur), puis Vérifier.', 'تحويلات RIP: طابق المبلغ والمرجع في كشفك البريدي ثم تحقق. الحوالة السريعة: اقبضها (بطاقة التعريف + الرمز السري ذو 12 رقما الذي يعطيك المشتري) ثم تحقق.')],
            'buyer' => [$tri('Either send a transfer to the RIP shown, or send a Mandat Minute to the name shown (post office or e-DINAR/La Poste services).', 'Soit envoyez un virement au RIP indiqué, soit envoyez un Mandat Minute au nom indiqué (bureau de poste ou services e-DINAR/La Poste).', 'إما أرسل تحويلا إلى RIP المعروض وإما أرسل حوالة سريعة إلى الاسم المعروض (مكتب بريد أو خدمات e-DINAR/البريد).'), $tri('Enter the transfer reference, or the mandat number, on our payment page. Do not write the confidential code anywhere except on the mandat you hand over.', 'Saisissez sur notre page de paiement la référence du virement ou le numéro du mandat. N’écrivez le code confidentiel nulle part ailleurs que sur le mandat remis.', 'أدخل في صفحتنا مرجع التحويل أو رقم الحوالة. لا تكتب الرمز السري في أي مكان آخر غير الحوالة المسلّمة.')],
            'check' => $tri('La Poste charges a commission by amount band for mandats and some transfers; limits differ between the app and the counter. Postal transfers may take a working day.', 'La Poste prélève une commission par tranche de montant pour les mandats et certains virements ; les plafonds diffèrent entre l’application et le guichet. Un virement postal peut prendre un jour ouvré.', 'يقتطع البريد عمولة حسب شريحة المبلغ للحوالات وبعض التحويلات؛ تختلف السقوف بين التطبيق والشباك. قد يستغرق التحويل البريدي يوم عمل.'),
        ],
        'fields' => array_merge([
            ['k' => 'holder', 't' => 'text', 'label' => 'Postal account holder', 'req' => true],
            ['k' => 'rip', 't' => 'text', 'label' => 'RIP (postal account identity)', 'req' => true, 'pattern' => '/^[0-9 ]{10,30}$/'],
            ['k' => 'mandat_name', 't' => 'text', 'label' => 'Name for Mandat Minute (as on the identity card, optional)', 'default' => ''],
            ['k' => 'note', 't' => 'text', 'label' => 'Note shown to the buyer', 'default' => ''],
        ], $localFees('0', '0')),
        'fee_note' => 'La Poste Tunisienne publishes commissions by amount band (for example for Mandat Minute). They are usually paid by the sender; set a fee here only if you pass a cost on.',
    ],
];
