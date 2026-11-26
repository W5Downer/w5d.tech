<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Account, ACL, Affiliate, Auth, Billing, Certificate, Content, Csrf, DB, Gateways, Mail, Payments, Qcm, RateLimit, Request, Response, Variables, ViewEnv};

/**
 * Affiliate programme, certification and the QCM engine: account actions, public verification, owner-portal screens.
 * Account panels qcm / certificates / affiliate are rendered by Accounts::panel (data from panelData()).
 */
final class Programs
{
    private static function m(string $en, string $fr, string $ar): array { return Accounts::m($en, $fr, $ar); }

    private static function back(string $to, string $kind, array $msg): Response
    {
        Accounts::flash($kind, $msg);
        return Response::redirect(url($to));
    }

    private static function services(): array
    {
        $o = [];
        foreach (Content::all('services') as $s) $o[$s['slug']] = '#' . str_pad((string)$s['id'], 3, '0', STR_PAD_LEFT) . ' ' . t($s['title'], 'en');
        return $o;
    }

    /* ======================= account panels ======================= */
    public static function panelData(string $panel, array $u): array
    {
        $uid = (int)$u['id'];
        if (!DB::available()) return [];
        if ($panel === 'qcm') {
            $rules = [];
            foreach (Qcm::rules() as $r) $rules[] = ['r' => $r, 'c' => Qcm::check($uid, $r), 'pending' => (float)$r['fee'] > 0 ? Qcm::pendingPass($uid, (int)$r['id']) : null];
            return ['q_on' => Qcm::enabled(), 'q_rules' => $rules, 'q_open' => Qcm::open($uid), 'q_level' => Certificate::level($uid), 'q_services' => self::services(),
                'q_hist' => DB::all('SELECT id, purpose, level, service_slug, status, score_pct, passed, correct, total, started_at FROM qcm_attempts WHERE user_id = ? ORDER BY id DESC LIMIT 15', [$uid]),
                'q_tasks' => DB::all('SELECT id, title, service_slug, status, note, created_at FROM qcm_tasks WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$uid])];
        }
        if ($panel === 'certificates') return ['c_rows' => Certificate::forUser($uid), 'c_level' => Certificate::level($uid), 'c_fees' => Billing::certFees(), 'c_open' => DB::all("SELECT number, amount, currency FROM invoices WHERE kind = 'cert' AND status = 'sent' AND user_id = ? ORDER BY id DESC LIMIT 5", [$uid])];
        $a = Affiliate::forUser($uid);
        return ['a_on' => Affiliate::enabled(), 'a' => $a, 'a_stats' => $a ? Affiliate::stats((int)$a['id']) : null,
            'a_link' => $a ? Affiliate::link((string)$a['code'], '/') : null,
            'a_refs' => $a ? DB::all('SELECT kind, commission, status, created_at FROM affiliate_referrals WHERE affiliate_id = ? ORDER BY id DESC LIMIT 20', [$a['id']]) : [],
            'a_pays' => $a ? DB::all('SELECT amount, status, txn_ref, note, created_at FROM affiliate_payouts WHERE affiliate_id = ? ORDER BY id DESC LIMIT 10', [$a['id']]) : []];
    }

    /* ======================= QCM: account ======================= */
    public static function qcmStart(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        if ($r = Accounts::guard($q, 'qcm-start', '/account/qcm/', 40, 600)) return $r;
        $rule = Qcm::rule((int)($q->post['rule'] ?? 0));
        if (!$rule) return self::back('/account/qcm/', 'error', self::m('This round does not exist any more.', 'Cette session n’existe plus.', 'هذه الجولة لم تعد موجودة.'));
        [$id, $err] = Qcm::start(Account::id(), $rule);
        if ($id) return Response::redirect(url('/account/qcm/attempt/' . $id . '/'));
        return self::back('/account/qcm/', 'error', match ($err) {
            'disabled' => self::m('QCM rounds are paused for now.', 'Les sessions QCM sont suspendues pour le moment.', 'جولات الاختبار متوقفة حاليا.'),
            'requirements' => self::m('Some minimum requirements are not met yet: see the checklist on the round.', 'Certaines conditions minimales ne sont pas encore remplies : voyez la liste sur la session.', 'بعض الشروط الدنيا لم تتحقق بعد: راجع القائمة في الجولة.'),
            default => self::m('The question bank for this level is not ready yet. Try again later.', 'La banque de questions de ce niveau n’est pas encore prête. Réessayez plus tard.', 'بنك أسئلة هذا المستوى غير جاهز بعد. حاول لاحقا.'),
        });
    }

    public static function qcmAttempt(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        $a = Qcm::attempt(Account::id(), (int)($q->params['id'] ?? 0));
        if (!$a) return Pages::notFound($q);
        if ($a['status'] === 'open' && !Qcm::inWindow($a)) $a = Qcm::finish($a, null, true);
        $d = ['a' => $a, 'meta' => Qcm::meta($a), 'title' => tr3('QCM round', 'Session QCM', 'جولة اختبار')];
        if ($a['status'] === 'open') return Accounts::page($q, 'account.qcm-attempt', $d + ['items' => Qcm::items($a), 'ans' => Qcm::answersOf($a), 'left' => Qcm::secondsLeft($a)]);
        $cert = $a['cert_code'] ? Certificate::find((string)$a['cert_code']) : null;
        return Accounts::page($q, 'account.qcm-attempt', $d + ['review' => Qcm::review($a), 'cert' => $cert]);
    }

    public static function qcmAnswer(Request $q): Response
    {
        if (!Account::check()) return Accounts::jsonOut(['ok' => false, 'error' => 'auth'], 401);
        if (!Csrf::check($q)) return Accounts::jsonOut(['ok' => false, 'error' => 'csrf'], 419);
        if (!RateLimit::hit('qcm-ans:' . Account::id(), 600, 600)) return Accounts::jsonOut(['ok' => false, 'error' => 'rate'], 429);
        $a = Qcm::attempt(Account::id(), (int)($q->post['attempt'] ?? 0));
        if (!$a) return Accounts::jsonOut(['ok' => false, 'error' => 'missing'], 404);
        $ok = Qcm::saveAnswer($a, (string)($q->post['qid'] ?? ''), is_array($q->post['choice'] ?? null) ? $q->post['choice'] : []);
        return Accounts::jsonOut(['ok' => $ok, 'left' => Qcm::secondsLeft($a)], $ok ? 200 : 409);
    }

    public static function qcmSubmit(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        $id = (int)($q->params['id'] ?? 0);
        $back = '/account/qcm/attempt/' . $id . '/';
        if ($r = Accounts::guard($q, 'qcm-submit', $back, 60, 600)) return $r;
        $a = Qcm::attempt(Account::id(), $id);
        if (!$a) return Pages::notFound($q);
        if ($a['status'] === 'open') Qcm::finish($a, is_array($q->post['a'] ?? null) ? $q->post['a'] : []);
        return Response::redirect(url($back));
    }

    public static function qcmBuy(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        if ($r = Accounts::guard($q, 'qcm-buy', '/account/qcm/', 20, 3600)) return $r;
        $rule = Qcm::rule((int)($q->post['rule'] ?? 0));
        if (!$rule || (float)$rule['fee'] <= 0) return self::back('/account/qcm/', 'error', self::m('No fee is due for this round.', 'Aucun frais n’est dû pour cette session.', 'لا رسوم على هذه الجولة.'));
        if (!Gateways::any()) return self::back('/account/qcm/', 'error', self::m('Payments are not open yet.', 'Les paiements ne sont pas encore ouverts.', 'الدفع غير مفتوح بعد.'));
        $u = Account::row();
        $p = Qcm::buyPass(Account::id(), $rule);
        if (!$u || !$p) return self::back('/account/qcm/', 'error', self::m('No fee is due for this round.', 'Aucun frais n’est dû pour cette session.', 'لا رسوم على هذه الجولة.'));
        return Response::redirect(Billing::link(Billing::invoiceForPass($p, $u, (string)$rule['name'])));
    }

    /** Old fee-payment address: the fee is an invoice now, paid on the one payment page of the platform. */
    public static function qcmPay(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        $ref = (string)($q->params['ref'] ?? '');
        $p = preg_match('/^QP-[A-F0-9]{8}$/', $ref) ? DB::one('SELECT p.*, r.name AS rule_name FROM qcm_passes p LEFT JOIN qcm_rules r ON r.id = p.rule_id WHERE p.ref = ? AND p.user_id = ?', [$ref, Account::id()]) : null;
        $u = Account::row();
        if (!$p || !$u) return Pages::notFound($q);
        if (in_array($p['status'], ['verified', 'used'], true)) return self::back('/account/invoices/', 'ok', self::m('This fee is already verified.', 'Ces frais sont déjà vérifiés.', 'تم التحقق من هذه الرسوم مسبقا.'));
        if (!Gateways::any()) return Pages::notFound($q);
        return Response::redirect(Billing::link(Billing::invoiceForPass($p, $u, (string)($p['rule_name'] ?? ''))));
    }

    public static function qcmTask(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        $back = '/account/qcm/';
        if ($r = Accounts::guard($q, 'qcm-task', $back, 8, 3600)) return $r;
        $title = trim((string)($q->post['title'] ?? '')); $ev = trim((string)($q->post['evidence'] ?? '')); $svc = (string)($q->post['service_slug'] ?? '');
        $e = [];
        if (mb_strlen($title) < 3 || mb_strlen($title) > 120) $e['title'] = self::m('Give the task a title (3 to 120 characters).', 'Donnez un titre à la tâche (3 à 120 caractères).', 'أعط المهمة عنوانا (من 3 إلى 120 حرفا).');
        if (mb_strlen($ev) < 10 || mb_strlen($ev) > 1200) $e['evidence'] = self::m('Describe what you did and where a reviewer can see it (10 to 1200 characters).', 'Décrivez ce que vous avez fait et où un relecteur peut le voir (10 à 1200 caractères).', 'صف ما أنجزته وأين يراه المراجع (من 10 إلى 1200 حرف).');
        if ($svc !== '' && !Qcm::validSlug($svc)) $svc = '';
        if ($e) return Accounts::bounce($q, $back, $e);
        DB::insert('qcm_tasks', ['country_id' => 'tn', 'user_id' => Account::id(), 'service_slug' => $svc, 'title' => $title, 'evidence' => $ev, 'status' => 'submitted']);
        Mail::staff('QCM task to review', ['title' => $title, 'user' => (string)(Account::user()['email'] ?? '')]);
        return self::back($back, 'ok', self::m('Task submitted. A reviewer will approve or reject it.', 'Tâche soumise. Un relecteur l’approuvera ou la refusera.', 'أُرسلت المهمة. سيقبلها مراجع أو يرفضها.'));
    }

    /* ======================= affiliate: account ======================= */
    public static function affApply(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        $back = '/account/affiliate/';
        if ($r = Accounts::guard($q, 'aff-apply', $back, 10, 3600)) return $r;
        if (!Affiliate::enabled()) return self::back($back, 'error', self::m('The affiliate programme is paused.', 'Le programme d’affiliation est suspendu.', 'برنامج الإحالة متوقف.'));
        $e = [];
        $pitch = trim((string)($q->post['pitch'] ?? ''));
        if (mb_strlen($pitch) > 600) $e['pitch'] = self::m('Keep it under 600 characters.', 'Moins de 600 caractères.', 'أقل من 600 حرف.');
        $raw = trim((string)($q->post['payout_gsm'] ?? ''));
        $gsm = $raw === '' ? '' : Payments::tnGsm($raw);
        if ($raw !== '' && !$gsm) $e['payout_gsm'] = self::m('Use a Tunisian D17 number: +216 followed by 8 digits.', 'Utilisez un numéro D17 tunisien : +216 suivi de 8 chiffres.', 'استخدم رقم D17 تونسيا: +216 متبوعا بـ 8 أرقام.');
        if (empty($q->post['accept'])) $e['accept'] = self::m('Please accept the programme rules.', 'Veuillez accepter les règles du programme.', 'يرجى قبول قواعد البرنامج.');
        if ($e) return Accounts::bounce($q, $back, $e);
        $a = Affiliate::forUser(Account::id());
        if ($a) {
            DB::q('UPDATE affiliates SET pitch = ?, payout_gsm = ? WHERE id = ?', [mb_substr($pitch, 0, 600), (string)$gsm, $a['id']]);
            return self::back($back, 'ok', self::m('Profile saved.', 'Profil enregistré.', 'تم حفظ الملف.'));
        }
        $id = Affiliate::apply(Account::id(), $pitch, (string)$gsm);
        $row = DB::one('SELECT * FROM affiliates WHERE id = ?', [$id]);
        if ($row && $row['status'] === 'pending') Mail::staff('Affiliate application', ['code' => (string)$row['code'], 'user' => (string)(Account::user()['email'] ?? '')]);
        return self::back($back, 'ok', $row && $row['status'] === 'active'
            ? self::m('You are in. Your link is ready.', 'C’est fait. Votre lien est prêt.', 'تم الانضمام. رابطك جاهز.')
            : self::m('Application received. The owner approves each affiliate by hand; we will notify you.', 'Candidature reçue. Le propriétaire approuve chaque affilié à la main ; nous vous préviendrons.', 'وصل الطلب. يوافق المالك على كل مُحيل يدويا وسنخطرك.'));
    }

    public static function affPayout(Request $q): Response
    {
        if ($r = Accounts::need($q)) return $r;
        $back = '/account/affiliate/';
        if ($r = Accounts::guard($q, 'aff-payout', $back, 6, 3600)) return $r;
        $a = Affiliate::forUser(Account::id());
        if (!$a) return self::back($back, 'error', self::m('Join the programme first.', 'Rejoignez d’abord le programme.', 'انضم إلى البرنامج أولا.'));
        [$ok, $err] = Affiliate::requestPayout((int)$a['id']);
        if ($ok) return self::back($back, 'ok', self::m('Payout requested. The owner pays by D17 and records the reference.', 'Paiement demandé. Le propriétaire paie par D17 et enregistre la référence.', 'طُلب الصرف. يدفع المالك عبر D17 ويسجل المرجع.'));
        return self::back($back, 'error', match ($err) {
            'inactive' => self::m('Your affiliate profile is not active.', 'Votre profil d’affilié n’est pas actif.', 'ملف الإحالة غير نشط.'),
            'open' => self::m('A payout request is already open.', 'Une demande de paiement est déjà ouverte.', 'يوجد طلب صرف مفتوح.'),
            'gsm' => self::m('Add your D17 payout number first.', 'Ajoutez d’abord votre numéro D17 de paiement.', 'أضف رقم D17 للصرف أولا.'),
            default => self::m('The available balance is below the minimum payout.', 'Le solde disponible est inférieur au minimum de paiement.', 'الرصيد المتاح أقل من الحد الأدنى للصرف.'),
        });
    }

    /* ======================= certificates: public ======================= */
    private static function verifyLimit(Request $q): bool
    {
        return RateLimit::hit('cert-verify:' . $q->ip(), max(1, (int)Variables::get('cert.verify_per_10min', 30)), 600);
    }

    public static function certVerify(Request $q): Response
    {
        $code = strtoupper(trim((string)$q->input('code', '')));
        $rec = null; $state = '';
        if ($code !== '') {
            if (!self::verifyLimit($q)) $state = 'limited';
            elseif (($c = DB::available() ? Certificate::find($code) : null)) { $rec = Certificate::publicRecord($c); $state = 'found'; }
            else $state = 'missing';
        }
        ViewEnv::share('canonicalPath', '/certification/verify/');
        return Response::html(view('pages.cert-verify', ['title' => tr3('Verify a certificate', 'Vérifier un certificat', 'التحقق من شهادة'), 'code' => $code, 'rec' => $rec, 'state' => $state, 'robots' => $code !== '' ? 'noindex,nofollow' : 'index,follow']), $state === 'limited' ? 429 : 200);
    }

    public static function certPrint(Request $q): Response
    {
        $code = strtoupper((string)($q->params['code'] ?? ''));
        $c = DB::available() ? Certificate::find($code) : null;
        if (!$c) return Pages::notFound($q);
        ViewEnv::share('canonicalPath', '/certificate/' . $code . '/');
        return Response::html(view('pages.certificate', ['title' => tr3('Certificate', 'Certificat', 'شهادة') . ' ' . $code, 'rec' => Certificate::publicRecord($c), 'verify' => abs_url('/certification/verify/') . '?code=' . $code]))->withHeaders(['X-Robots-Tag' => 'noindex, nofollow']);
    }

    public static function certApi(Request $q): Response
    {
        $code = strtoupper((string)($q->params['code'] ?? ''));
        $h = ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store'];
        if (!self::verifyLimit($q)) return Response::json(['error' => 'rate_limited'], 429, $h);
        $c = DB::available() ? Certificate::find($code) : null;
        return $c ? Response::json(['certificate' => Certificate::publicRecord($c)], 200, $h) : Response::json(['error' => 'not_found'], 404, $h);
    }

    /* ======================= owner portal: shared ======================= */
    private static function flashA(string $kind, string $msg): void { Admin::flash($kind, $msg); }

    /** Permission + CSRF gate for every portal POST. Returns a Response to send, or null to continue. */
    private static function gate(Request $q, string $perm, string $back): ?Response
    {
        if ($r = Auth::require($perm)) return $r;
        if (!Csrf::check($q)) { self::flashA('error', __('msg.csrf')); return Response::redirect(admin_url($back)); }
        return null;
    }

    private static function who(): string { return (string)(Auth::user()['name'] ?? 'staff'); }

    /* ======================= owner portal: QCM bank ======================= */
    public static function vBank(Request $q): array
    {
        $svc = self::services();
        $slug = (string)($q->query['service'] ?? '');
        if (!isset($svc[$slug])) $slug = (string)array_key_first($svc);
        $lvl = (int)($q->query['level'] ?? 0);
        if (!in_array($lvl, Qcm::LEVELS, true)) $lvl = 0;
        $all = Qcm::bank($slug);
        $rows = array_values(array_filter($all, fn($x) => $lvl === 0 || (int)$x['level'] === $lvl));
        $per = 25; $pages = max(1, (int)ceil(count($rows) / $per));
        $page = max(1, min($pages, (int)($q->query['page'] ?? 1)));
        $edit = null;
        if (!empty($q->query['edit'])) foreach ($all as $x) if ($x['id'] === (string)$q->query['edit']) $edit = $x;
        $perLevel = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($all as $x) if (!empty($x['active'])) $perLevel[(int)$x['level']] = ($perLevel[(int)$x['level']] ?? 0) + 1;
        return ['kind' => 'qcm-bank', 'services' => $svc, 'slug' => $slug, 'level' => $lvl, 'rows' => array_slice($rows, ($page - 1) * $per, $per), 'total' => count($rows), 'page' => $page, 'pages' => $pages,
            'edit' => $edit, 'new' => isset($q->query['new']), 'per_level' => $perLevel, 'target' => (int)Variables::get('qcm.bank_target', 1000), 'generated' => \W5D\QcmGen::enabled() ? \W5D\QcmGen::perLevel() : 0,
            'can' => ['create' => Auth::can('qcm.create'), 'update' => Auth::can('qcm.update'), 'delete' => Auth::can('qcm.delete')]];
    }

    /** Build a question from the editor form (strings only; the engine validates it). */
    private static function questionFromPost(Request $q, string $id): array
    {
        $p = $q->post; $tri = fn(string $k) => ['en' => trim((string)($p[$k . '_en'] ?? '')), 'fr' => trim((string)($p[$k . '_fr'] ?? '')), 'ar' => trim((string)($p[$k . '_ar'] ?? ''))];
        $choices = [];
        foreach (Qcm::IDS as $c) {
            $t = $tri('c' . $c);
            if ($t['en'] === '' && $t['fr'] === '' && $t['ar'] === '') continue;
            $choices[] = ['id' => $c, 't' => $t];
        }
        $ans = array_values(array_unique(array_filter(array_map('strval', (array)($p['answer'] ?? [])), fn($x) => in_array($x, Qcm::IDS, true))));
        sort($ans);
        $out = ['id' => $id, 'level' => (int)($p['level'] ?? 0), 'type' => (string)($p['type'] ?? ''), 'active' => ($p['active'] ?? '') === '1',
            'tags' => array_slice(array_values(array_filter(array_map(fn($t) => preg_replace('/[^a-z0-9-]/', '', strtolower(trim($t))), explode(',', (string)($p['tags'] ?? ''))))), 0, 6),
            'q' => $tri('q'), 'choices' => $choices, 'answer' => $ans];
        $ex = $tri('x');
        if ($ex['en'] !== '' || $ex['fr'] !== '' || $ex['ar'] !== '') $out['explain'] = $ex;
        return $out;
    }

    public static function bankSave(Request $q): Response
    {
        $slug = (string)($q->post['service'] ?? '');
        $id = trim((string)($q->post['id'] ?? ''));
        $perm = $id !== '' ? 'qcm.update' : 'qcm.create';
        $back = 'qcm-bank/?service=' . rawurlencode($slug);
        if ($r = self::gate($q, $perm, $back)) return $r;
        if (!Qcm::validSlug($slug)) { self::flashA('error', 'Unknown service.'); return Response::redirect(admin_url('qcm-bank/')); }
        $bank = Qcm::bank($slug);
        $idx = null;
        foreach ($bank as $i => $x) if ($x['id'] === $id) $idx = $i;
        if ($id !== '' && $idx === null) { self::flashA('error', 'That question does not exist any more.'); return Response::redirect(admin_url($back)); }
        $lvl = (int)($q->post['level'] ?? 0);
        $newId = $id !== '' ? $id : (in_array($lvl, Qcm::LEVELS, true) ? Qcm::nextId($slug, $lvl) : '');
        $qq = self::questionFromPost($q, $newId);
        if ($id !== '' && (int)$qq['level'] !== (int)$bank[$idx]['level']) $qq['level'] = (int)$bank[$idx]['level']; // the id carries the level: keep them aligned
        $err = $newId === '' ? ['level must be 1 to 4'] : Qcm::validate($qq);
        if ($err) {
            self::flashA('error', 'Not saved: ' . implode('; ', array_slice($err, 0, 4)) . '.');
            return Response::redirect(admin_url($back . ($id !== '' ? '&edit=' . rawurlencode($id) : '&new=1')));
        }
        if ($idx === null) $bank[] = $qq; else $bank[$idx] = $qq;
        try { Qcm::save($slug, $bank); } catch (\Throwable $e) { self::flashA('error', 'Could not write the bank file.'); return Response::redirect(admin_url($back)); }
        Admin::audit($idx === null ? 'qcm.create' : 'qcm.update', $newId);
        self::flashA('ok', ($idx === null ? 'Question added: ' : 'Question saved: ') . $newId);
        return Response::redirect(admin_url($back . '&level=' . (int)$qq['level']));
    }

    public static function bankToggle(Request $q): Response
    {
        $slug = (string)($q->post['service'] ?? ''); $id = (string)($q->post['id'] ?? '');
        $back = 'qcm-bank/?service=' . rawurlencode($slug) . '&level=' . (int)($q->post['level'] ?? 0) . '&page=' . max(1, (int)($q->post['page'] ?? 1));
        if ($r = self::gate($q, 'qcm.update', $back)) return $r;
        if (!Qcm::validSlug($slug)) return Response::redirect(admin_url('qcm-bank/'));
        $bank = Qcm::bank($slug); $hit = false;
        foreach ($bank as $i => $x) if ($x['id'] === $id) { $bank[$i]['active'] = empty($x['active']); $hit = true; }
        if ($hit) { Qcm::save($slug, $bank); Admin::audit('qcm.toggle', $id); self::flashA('ok', 'Updated ' . $id); }
        return Response::redirect(admin_url($back));
    }

    public static function bankDelete(Request $q): Response
    {
        $slug = (string)($q->post['service'] ?? ''); $id = (string)($q->post['id'] ?? '');
        $back = 'qcm-bank/?service=' . rawurlencode($slug) . '&level=' . (int)($q->post['level'] ?? 0);
        if ($r = self::gate($q, 'qcm.delete', $back)) return $r;
        if (!Qcm::validSlug($slug)) return Response::redirect(admin_url('qcm-bank/'));
        $bank = Qcm::bank($slug); $keep = array_values(array_filter($bank, fn($x) => $x['id'] !== $id));
        if (count($keep) !== count($bank)) { Qcm::save($slug, $keep); Admin::audit('qcm.delete', $id); self::flashA('ok', 'Deleted ' . $id . '. Rounds already started keep their own copy.'); }
        return Response::redirect(admin_url($back));
    }

    /** Import a JSON array (or {questions:[…]}) into one service. All-or-nothing: one invalid question rejects the file. */
    public static function bankImport(Request $q): Response
    {
        $slug = (string)($q->post['service'] ?? '');
        $back = 'qcm-bank/?service=' . rawurlencode($slug);
        if ($r = self::gate($q, 'qcm.create', $back)) return $r;
        if (!Qcm::validSlug($slug)) { self::flashA('error', 'Unknown service.'); return Response::redirect(admin_url('qcm-bank/')); }
        $raw = (string)($q->post['json'] ?? '');
        if (strlen($raw) > 2_000_000) { self::flashA('error', 'File too large (2 MB max). Import in several parts.'); return Response::redirect(admin_url($back)); }
        $j = json_decode($raw, true);
        $list = is_array($j) && isset($j['questions']) && is_array($j['questions']) ? $j['questions'] : (is_array($j) && array_is_list($j) ? $j : null);
        if ($list === null || !$list) { self::flashA('error', 'The JSON must be an array of questions, or an object with a "questions" array.'); return Response::redirect(admin_url($back)); }
        if (count($list) > 5000) { self::flashA('error', 'At most 5000 questions per import.'); return Response::redirect(admin_url($back)); }
        $seen = []; $errs = [];
        foreach ($list as $i => $x) {
            if (!is_array($x)) { $errs[] = '#' . ($i + 1) . ': not an object'; continue; }
            $x['active'] = array_key_exists('active', $x) ? (bool)$x['active'] : true;
            $list[$i] = $x;
            $e = Qcm::validate($x);
            if (!str_starts_with((string)($x['id'] ?? ''), $slug . '.L')) $e[] = 'id must start with ' . $slug . '.L';
            elseif (preg_match('/\.L(\d)\./', (string)$x['id'], $m) && (int)$m[1] !== (int)($x['level'] ?? 0)) $e[] = 'id level and level differ';
            if (isset($seen[$x['id'] ?? ''])) $e[] = 'duplicate id in the file';
            $seen[$x['id'] ?? ''] = 1;
            if ($e) $errs[] = ($x['id'] ?? '#' . ($i + 1)) . ': ' . implode(', ', array_slice($e, 0, 2));
        }
        if ($errs) { self::flashA('error', 'Nothing imported. ' . count($errs) . ' invalid question(s). First: ' . implode(' | ', array_slice($errs, 0, 3))); return Response::redirect(admin_url($back)); }
        $replace = ($q->post['mode'] ?? 'merge') === 'replace';
        if ($replace && !Auth::can('qcm.delete')) { self::flashA('error', 'Replacing a whole bank needs the delete permission.'); return Response::redirect(admin_url($back)); }
        $bank = $replace ? [] : Qcm::bank($slug);
        $pos = []; foreach ($bank as $i => $x) $pos[$x['id']] = $i;
        $added = 0; $upd = 0;
        foreach ($list as $x) {
            if (isset($pos[$x['id']])) { $bank[$pos[$x['id']]] = $x; $upd++; } else { $bank[] = $x; $pos[$x['id']] = count($bank) - 1; $added++; }
        }
        usort($bank, fn($a, $b) => strnatcmp($a['id'], $b['id']));
        try { Qcm::save($slug, $bank); } catch (\Throwable) { self::flashA('error', 'Could not write the bank file.'); return Response::redirect(admin_url($back)); }
        Admin::audit('qcm.import', $slug, ($replace ? 'replace' : 'merge') . " +$added ~$upd");
        self::flashA('ok', "Imported: $added added, $upd updated" . ($replace ? ' (bank replaced).' : '.'));
        return Response::redirect(admin_url($back));
    }

    public static function bankExport(Request $q): Response
    {
        if ($r = Auth::require('qcm')) return $r;
        $slug = (string)($q->query['service'] ?? '');
        if (!Qcm::validSlug($slug)) return Pages::notFound($q);
        $txt = json_encode(['schema' => 1, 'service' => $slug, 'speciality' => '', 'questions' => Qcm::bank($slug)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return Response::text((string)$txt, 'application/json', 200)->withHeaders(['Content-Disposition' => 'attachment; filename="qcm-' . $slug . '.json"', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    /** Render an owner-portal screen of one of the programme menus through the generic menu view. */
    public static function adminPage(string $slug, array $v): Response
    {
        $menu = null;
        foreach (ACL::matrix()['menus'] as $m) if ($m['slug'] === $slug) $menu = $m;
        if (!$menu) return Pages::notFound(new Request());
        $res = Admin::page('admin.menu', ['title' => t($menu['label']), 'active' => $slug, 'menu' => $menu, 'v' => $v, 'flash' => $_SESSION['_admin_flash'] ?? null, 'back' => $slug]);
        return $res;
    }

    /* ======================= owner portal: QCM review (fees + tasks) ======================= */
    public static function vReview(): array
    {
        return ['kind' => 'qcm-review', 'stats' => Qcm::stats(),
            'passes' => DB::all("SELECT p.id, p.ref, p.amount, p.currency, COALESCE((SELECT y.sender_gsm FROM payments y JOIN invoices v ON v.id = y.invoice_id WHERE v.source_ref = p.ref ORDER BY y.id DESC LIMIT 1), p.sender_gsm) AS sender_gsm, COALESCE((SELECT y.txn_ref FROM payments y JOIN invoices v ON v.id = y.invoice_id WHERE v.source_ref = p.ref ORDER BY y.id DESC LIMIT 1), p.txn_ref) AS txn_ref, p.status, p.note, p.created_at, u.name, u.email, r.name AS rule FROM qcm_passes p JOIN users u ON u.id = p.user_id LEFT JOIN qcm_rules r ON r.id = p.rule_id WHERE p.status != 'awaiting' ORDER BY (p.status = 'pending') DESC, p.id DESC LIMIT 100"),
            'tasks' => DB::all("SELECT t.id, t.title, t.service_slug, t.evidence, t.status, t.note, t.created_at, u.name, u.email FROM qcm_tasks t JOIN users u ON u.id = t.user_id ORDER BY (t.status = 'submitted') DESC, t.id DESC LIMIT 100"),
            'can' => Auth::can('qcm.update')];
    }

    public static function passReview(Request $q): Response
    {
        if ($r = self::gate($q, 'qcm.update', 'qcm-review/')) return $r;
        $id = (int)($q->post['id'] ?? 0); $ok = ($q->post['decision'] ?? '') === 'verify';
        $note = mb_substr(trim((string)($q->post['note'] ?? '')), 0, 300);
        $p = DB::one("SELECT p.*, u.email, u.name, u.lang FROM qcm_passes p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.status = 'pending'", [$id]);
        if (!$p) { self::flashA('error', 'That fee is not waiting for review any more.'); return Response::redirect(admin_url('qcm-review/')); }
        if (!$ok && mb_strlen($note) < 3) { self::flashA('error', 'Give the person a short reason when rejecting.'); return Response::redirect(admin_url('qcm-review/')); }
        // The fee is an invoice with a payment: settle that payment (marks the invoice paid, verifies the pass, e-mails the buyer).
        $inv = Billing::forSource('qcm_pass', (string)$p['ref']);
        $pay = $inv ? DB::one("SELECT id FROM payments WHERE invoice_id = ? AND status = 'pending' AND txn_ref IS NOT NULL ORDER BY id DESC", [$inv['id']]) : null;
        if ($pay) {
            Payments::settle((int)$pay['id'], $ok, self::who(), $note);
            if (!$ok) DB::q('UPDATE qcm_passes SET note = ? WHERE id = ?', [$note ?: null, $id]);
            Admin::audit('qcm.pass', (string)$p['ref'], $ok ? 'verified' : 'rejected');
            self::flashA('ok', $p['ref'] . ' → ' . ($ok ? 'verified' : 'rejected'));
            return Response::redirect(admin_url('qcm-review/'));
        }
        DB::q('UPDATE qcm_passes SET status = ?, note = ?, verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE id = ?', [$ok ? 'verified' : 'rejected', $note ?: null, self::who(), $id]);
        if ($ok) Affiliate::onPayment((string)$p['email'], (string)$p['ref'], (float)$p['amount']);
        Mail::send((string)$p['email'], 'qcm_pass_status', ['name' => $p['name'], 'ref' => $p['ref'], 'status' => $ok ? 'verified' : 'rejected', 'amount' => $p['amount'], 'currency' => $p['currency'], 'note' => $note, 'url' => abs_url('/account/qcm/')], $p['lang'] ?? null);
        Account::notify((int)$p['user_id'], $ok ? tr3('QCM fee verified', 'Frais QCM vérifiés', 'تم التحقق من رسوم الاختبار') : tr3('QCM fee rejected', 'Frais QCM refusés', 'رُفضت رسوم الاختبار'), (string)$p['ref'], '/account/qcm/');
        Admin::audit('qcm.pass', (string)$p['ref'], $ok ? 'verified' : 'rejected');
        self::flashA('ok', $p['ref'] . ' → ' . ($ok ? 'verified' : 'rejected'));
        return Response::redirect(admin_url('qcm-review/'));
    }

    public static function taskReview(Request $q): Response
    {
        if ($r = self::gate($q, 'qcm.update', 'qcm-review/')) return $r;
        $id = (int)($q->post['id'] ?? 0); $ok = ($q->post['decision'] ?? '') === 'approve';
        $note = mb_substr(trim((string)($q->post['note'] ?? '')), 0, 300);
        $t = DB::one("SELECT * FROM qcm_tasks WHERE id = ? AND status = 'submitted'", [$id]);
        if (!$t) { self::flashA('error', 'That task is not waiting for review any more.'); return Response::redirect(admin_url('qcm-review/')); }
        if (!$ok && mb_strlen($note) < 3) { self::flashA('error', 'Give the person a short reason when rejecting.'); return Response::redirect(admin_url('qcm-review/')); }
        DB::q('UPDATE qcm_tasks SET status = ?, note = ?, reviewer = ? WHERE id = ?', [$ok ? 'approved' : 'rejected', $note ?: null, self::who(), $id]);
        Account::notify((int)$t['user_id'], $ok ? tr3('Task approved', 'Tâche approuvée', 'قُبلت المهمة') : tr3('Task rejected', 'Tâche refusée', 'رُفضت المهمة'), (string)$t['title'], '/account/qcm/');
        Admin::audit('qcm.task', '#' . $id, $ok ? 'approved' : 'rejected');
        self::flashA('ok', 'Task #' . $id . ' → ' . ($ok ? 'approved' : 'rejected'));
        return Response::redirect(admin_url('qcm-review/'));
    }

    /* ======================= owner portal: affiliates ======================= */
    public static function vAffiliates(): array
    {
        $rows = DB::all("SELECT a.id, a.code, a.status, a.pitch, a.payout_gsm, a.created_at, u.name, u.email,
            (SELECT COUNT(*) FROM affiliate_clicks c WHERE c.affiliate_id = a.id) AS clicks,
            (SELECT COUNT(*) FROM users x WHERE x.referred_by = a.id AND x.email_verified_at IS NOT NULL) AS signups,
            (SELECT COALESCE(SUM(commission),0) FROM affiliate_referrals r WHERE r.affiliate_id = a.id AND r.status IN ('pending','approved')) AS owed
            FROM affiliates a JOIN users u ON u.id = a.user_id ORDER BY (a.status = 'pending') DESC, a.id DESC LIMIT 200");
        return ['kind' => 'affiliates', 'enabled' => Affiliate::enabled(), 'affiliates' => $rows,
            'refs' => DB::all("SELECT r.id, r.kind, r.commission, r.status, r.created_at, a.code FROM affiliate_referrals r JOIN affiliates a ON a.id = r.affiliate_id WHERE r.status = 'pending' ORDER BY r.id DESC LIMIT 100"),
            'payouts' => DB::all("SELECT p.id, p.amount, p.gsm, p.status, p.txn_ref, p.note, p.created_at, a.code FROM affiliate_payouts p JOIN affiliates a ON a.id = p.affiliate_id ORDER BY (p.status = 'requested') DESC, p.id DESC LIMIT 100"),
            'can' => Auth::can('affiliates.update')];
    }

    public static function affStatus(Request $q): Response
    {
        if ($r = self::gate($q, 'affiliates.update', 'affiliates/')) return $r;
        $id = (int)($q->post['id'] ?? 0); $st = (string)($q->post['status'] ?? '');
        $a = Affiliate::setStatus($id, $st);
        if (!$a) { self::flashA('error', 'Nothing to change.'); return Response::redirect(admin_url('affiliates/')); }
        Mail::send((string)$a['email'], 'affiliate_status', ['name' => $a['name'], 'code' => $a['code'], 'status' => $st, 'url' => abs_url('/account/affiliate/')], $a['lang'] ?? null);
        Account::notify((int)$a['user_id'], tr3('Affiliate status', 'Statut d’affilié', 'حالة الإحالة'), (string)$a['code'] . ' · ' . $st, '/account/affiliate/');
        Admin::audit('affiliate.status', (string)$a['code'], $st);
        self::flashA('ok', $a['code'] . ' → ' . $st);
        return Response::redirect(admin_url('affiliates/'));
    }

    public static function refDecide(Request $q): Response
    {
        if ($r = self::gate($q, 'affiliates.update', 'affiliates/')) return $r;
        $id = (int)($q->post['id'] ?? 0); $ok = ($q->post['decision'] ?? '') === 'approve';
        if (Affiliate::decide($id, $ok)) { Admin::audit('affiliate.referral', '#' . $id, $ok ? 'approved' : 'rejected'); self::flashA('ok', 'Referral #' . $id . ' → ' . ($ok ? 'approved' : 'rejected')); }
        else self::flashA('error', 'That referral was already decided.');
        return Response::redirect(admin_url('affiliates/'));
    }

    public static function payoutSettle(Request $q): Response
    {
        if ($r = self::gate($q, 'affiliates.update', 'affiliates/')) return $r;
        $id = (int)($q->post['id'] ?? 0); $paid = ($q->post['decision'] ?? '') === 'paid';
        $txn = strtoupper(trim((string)($q->post['txn_ref'] ?? ''))); $note = mb_substr(trim((string)($q->post['note'] ?? '')), 0, 300);
        if ($paid && !preg_match('/^[A-Z0-9\-]{6,32}$/', $txn)) { self::flashA('error', 'Record the D17 transaction reference (6 to 32 letters or digits) when marking a payout as paid.'); return Response::redirect(admin_url('affiliates/')); }
        if (!$paid && mb_strlen($note) < 3) { self::flashA('error', 'Give the affiliate a short reason when rejecting.'); return Response::redirect(admin_url('affiliates/')); }
        $p = Affiliate::settlePayout($id, $paid, $txn, $note);
        if (!$p) { self::flashA('error', 'That payout was already settled.'); return Response::redirect(admin_url('affiliates/')); }
        Mail::send((string)$p['email'], 'payout_status', ['name' => $p['name'], 'amount' => $p['amount'], 'currency' => (string)Variables::get('money.currency', 'TND'), 'status' => $paid ? 'paid' : 'rejected', 'txn' => $txn, 'note' => $note, 'url' => abs_url('/account/affiliate/')], $p['lang'] ?? null);
        Account::notify((int)$p['user_id'], tr3('Payout update', 'Mise à jour du paiement', 'تحديث الصرف'), number_format((float)$p['amount'], 2) . ' · ' . ($paid ? 'paid' : 'rejected'), '/account/affiliate/');
        Admin::audit('affiliate.payout', '#' . $id, $paid ? 'paid ' . $txn : 'rejected');
        self::flashA('ok', 'Payout #' . $id . ' → ' . ($paid ? 'paid' : 'rejected'));
        return Response::redirect(admin_url('affiliates/'));
    }

    /* ======================= owner portal: certificates ======================= */
    public static function vCertificates(): array
    {
        $rows = DB::all("SELECT c.id, c.code, c.service_slug, c.level, c.score, c.status, c.issued_at, c.expires_at, c.revoke_reason, c.issued_by, u.name, u.email FROM certificates c JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT 200");
        foreach ($rows as &$r) $r['state'] = Certificate::state($r);
        return ['kind' => 'certificates', 'rows' => $rows, 'services' => self::services(), 'levels' => array_map(fn($l) => t($l['label'], 'en'), Certificate::levels()), 'holders' => Certificate::holders(),
            'users' => DB::all("SELECT id, name, email FROM users WHERE active = 1 AND email_verified_at IS NOT NULL ORDER BY id DESC LIMIT 200"), 'can' => Auth::can('certificates.update')];
    }

    public static function certIssue(Request $q): Response
    {
        if ($r = self::gate($q, 'certificates.update', 'certificates/')) return $r;
        $uid = (int)($q->post['user_id'] ?? 0); $svc = (string)($q->post['service'] ?? ''); $lvl = (int)($q->post['level'] ?? 0);
        $u = DB::one('SELECT id FROM users WHERE id = ? AND active = 1', [$uid]);
        if (!$u || !Qcm::validSlug($svc) || !in_array($lvl, Qcm::LEVELS, true)) { self::flashA('error', 'Choose a verified account, a service and a level.'); return Response::redirect(admin_url('certificates/')); }
        $code = Certificate::issue($uid, $svc, $lvl, null, null, 'owner:' . self::who());
        Admin::audit('certificate.issue', $code, $svc . ' L' . $lvl);
        self::flashA('ok', 'Issued ' . $code);
        return Response::redirect(admin_url('certificates/'));
    }

    public static function certRevoke(Request $q): Response
    {
        if ($r = self::gate($q, 'certificates.update', 'certificates/')) return $r;
        $id = (int)($q->post['id'] ?? 0); $why = trim((string)($q->post['reason'] ?? ''));
        if (mb_strlen($why) < 5) { self::flashA('error', 'A reason of at least 5 characters is required: it is shown to anyone who verifies the code.'); return Response::redirect(admin_url('certificates/')); }
        if (Certificate::revoke($id, $why)) { Admin::audit('certificate.revoke', '#' . $id, $why); self::flashA('ok', 'Certificate #' . $id . ' revoked.'); }
        else self::flashA('error', 'That certificate is not valid any more.');
        return Response::redirect(admin_url('certificates/'));
    }
}
