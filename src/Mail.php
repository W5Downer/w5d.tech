<?php
declare(strict_types=1);

namespace W5D;

/**
 * Transactional mail. MAIL_DRIVER = log (default: nothing leaves the server) | smtp | mail (PHP mail()).
 * Never throws into a request: failures are stored in mail_outbox and logged. No third-party library.
 */
final class Mail
{
    public static function types(): array { return (require ROOT . '/config/mail.php')['types']; }

    /** "updates" = non-essential (newsletter) and can be unsubscribed; "transactional" = service mail, always sent. */
    public static function category(string $type, array $data = []): string
    {
        return $type === 'form_ack' && ($data['kind'] ?? '') === 'newsletter' ? 'updates' : 'transactional';
    }

    public static function suppressed(string $email): bool
    {
        try { return (bool)DB::one('SELECT 1 FROM mail_suppressions WHERE email = ?', [mb_strtolower(trim($email))]); } catch (\Throwable) { return false; }
    }

    public static function suppress(string $email, string $source = 'link'): void
    {
        $e = mb_strtolower(trim($email));
        DB::q('INSERT OR IGNORE INTO mail_suppressions (email, scope, source) VALUES (?, ?, ?)', [$e, 'updates', $source]);
        DB::q('UPDATE newsletter_subscribers SET unsubscribed_at = CURRENT_TIMESTAMP WHERE email = ? AND unsubscribed_at IS NULL', [$e]);
    }

    public static function resubscribe(string $email): void
    {
        $e = mb_strtolower(trim($email));
        DB::q('DELETE FROM mail_suppressions WHERE email = ?', [$e]);
        DB::q('UPDATE newsletter_subscribers SET unsubscribed_at = NULL WHERE email = ?', [$e]);
    }

    /** Signed, stateless unsubscribe link for one address (no login needed, works from any mail client). */
    public static function unsubUrl(string $email, ?string $lang = null): string
    {
        $e = mb_strtolower(trim($email));
        return abs_url('/unsubscribe/') . '?e=' . rawurlencode($e) . '&s=' . Secret::sign('unsub:' . $e) . '&l=' . ($lang ?: Lang::current());
    }

    public static function unsubValid(string $email, string $sig): bool
    {
        return $sig !== '' && Secret::verify('unsub:' . mb_strtolower(trim($email)), $sig);
    }

    /** Plain-text alternative: links kept as "label (url)", head/preheader removed, whitespace tidied. */
    private static function toText(string $html): string
    {
        $h = preg_replace(['#<head>.*?</head>#is', '#<!--pre-->.*?<!--/pre-->#s', '#<style.*?</style>#is'], '', $html);
        $h = preg_replace(['#<(br|/p|/h1|/h2|/h3|/tr|/li|/table)\s*/?>#i', '#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is'], ["\n", '$2 ($1)'], $h);
        $h = preg_replace_callback('#(https?://\\S+) \\(\\1\\)#', fn($m) => $m[1], $h);
        $t = trim(html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8'));
        $t = preg_replace('/[ \t\x{00A0}]+/u', ' ', $t);
        $t = preg_replace('/ *\n */', "\n", $t);
        return preg_replace("/\n{3,}/", "\n\n", $t);
    }

    /** @return bool true when handed to the transport (or logged), false when skipped/failed */
    public static function send(string $to, string $type, array $data = [], ?string $lang = null): bool
    {
        $to = trim($to);
        $types = self::types();
        if (!isset($types[$type]) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        if (str_ends_with(strtolower($to), '@example.test')) return false; // seeded demo data never mails
        $lang = in_array($lang, Lang::SUPPORTED, true) ? $lang : Lang::current();
        $category = self::category($type, $data);
        if ($category === 'updates' && self::suppressed($to)) return false; // unsubscribed from non-essential mail
        $staff = !empty($data['staff']);
        $unsub = $staff ? null : self::unsubUrl($to, $lang);
        $driver = (string)Env::get('MAIL_DRIVER', 'log');
        try {
            [$subject, $html, $text] = Lang::using($lang, function () use ($types, $type, $data, $to, $unsub, $category, $staff) {
                $subject = t($types[$type]['subject']);
                foreach (['ref', 'status'] as $k) $subject = str_replace(':' . $k, (string)($data[$k] ?? ''), $subject);
                $html = view('mail.' . $type, $data + ['subject' => $subject, 'to' => $to, 'unsub' => $unsub, 'category' => $category, 'isStaff' => $staff]);
                return [$subject, $html, self::toText($html)];
            });
        } catch (\Throwable $e) {
            self::log('render failed ' . $type . ': ' . $e->getMessage());
            return false;
        }
        $id = self::record($to, $subject, $type, $lang, $driver, 'queued');
        try {
            $oneClick = $category === 'updates' ? $unsub : null; // RFC 8058 header only for non-essential mail
            if ($driver === 'smtp') self::smtp($to, $subject, $html, $text, $oneClick);
            elseif ($driver === 'mail') self::phpMail($to, $subject, $html, $text, $oneClick);
            else self::log("[$type] to=$to subject=$subject" . ($oneClick ? "\nList-Unsubscribe: <$oneClick>" : '') . "\n" . $text);
            self::mark($id, $driver === 'log' ? 'logged' : 'sent');
            return true;
        } catch (\Throwable $e) {
            self::mark($id, 'failed', $e->getMessage());
            self::log('send failed ' . $type . ' to ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Internal notice to the staff mailbox (CONTACT_EMAIL), English. */
    public static function staff(string $title, array $lines): void
    {
        $to = (string)Env::get('CONTACT_EMAIL', '');
        if ($to === '') return;
        self::send($to, 'form_ack', ['staff' => true, 'title' => $title, 'lines' => $lines, 'ref' => ''], 'en');
    }

    /* ---------------- transports ---------------- */
    private static function from(): array
    {
        $addr = (string)(Env::get('MAIL_FROM_ADDRESS') ?: Env::get('CONTACT_EMAIL', 'no-reply@localhost'));
        return [$addr, (string)Env::get('MAIL_FROM_NAME', 'W5D')];
    }

    private static function headers(string $to, string $subject, string $boundary, ?string $unsub = null): array
    {
        [$fa, $fn] = self::from();
        $domain = substr(strrchr($fa, '@') ?: '@localhost', 1);
        $h = [
            'Date: ' . date('r'), 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'From: ' . self::encName($fn) . " <$fa>", 'To: <' . $to . '>', 'Subject: ' . self::encHeader($subject), 'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"', 'X-Mailer: W5D', 'Auto-Submitted: auto-generated',
        ];
        if ($rt = (string)Env::get('MAIL_REPLY_TO', '')) $h[] = 'Reply-To: <' . $rt . '>';
        if ($unsub) {
            $h[] = 'List-Unsubscribe: <' . $unsub . '>' . (($m = (string)(Env::get('MAIL_FROM_ADDRESS') ?: Env::get('CONTACT_EMAIL', ''))) !== '' ? ', <mailto:' . $m . '?subject=unsubscribe>' : '');
            $h[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
        }
        return $h;
    }

    private static function encHeader(string $s): string { return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s; }
    private static function encName(string $s): string { return preg_match('/[^\x20-\x7e]/', $s) ? self::encHeader($s) : '"' . addcslashes($s, '"\\') . '"'; }

    private static function body(string $html, string $text, string $boundary): string
    {
        $part = fn(string $type, string $c) => "--$boundary\r\nContent-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($c));
        return $part('text/plain', $text) . $part('text/html', $html) . "--$boundary--\r\n";
    }

    private static function phpMail(string $to, string $subject, string $html, string $text, ?string $unsub = null): void
    {
        $b = 'w5d_' . bin2hex(random_bytes(8));
        $hs = array_values(array_filter(self::headers($to, $subject, $b, $unsub), fn($h) => !preg_match('/^(To|Subject):/', $h)));
        if (!@mail($to, self::encHeader($subject), self::body($html, $text, $b), implode("\r\n", $hs))) throw new \RuntimeException('mail() returned false');
    }

    private static function smtp(string $to, string $subject, string $html, string $text, ?string $unsub = null): void
    {
        $host = (string)Env::get('MAIL_HOST', '');
        if ($host === '') throw new \RuntimeException('MAIL_HOST is empty');
        $enc = strtolower((string)Env::get('MAIL_ENCRYPTION', 'tls')); // ssl (465) | tls (STARTTLS 587) | none
        $port = (int)(Env::get('MAIL_PORT') ?: ($enc === 'ssl' ? 465 : 587));
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) throw new \RuntimeException("connect $host:$port failed: $es");
        stream_set_timeout($fp, 12);
        $read = function () use ($fp): array {
            $code = 0; $all = '';
            while (($l = fgets($fp, 1024)) !== false) { $all .= $l; $code = (int)substr($l, 0, 3); if (strlen($l) < 4 || $l[3] === ' ') break; }
            return [$code, $all];
        };
        $cmd = function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            [$code, $all] = $read();
            if (!in_array($code, $ok, true)) throw new \RuntimeException('SMTP ' . (str_starts_with($c, 'AUTH') || strlen($c) > 60 ? 'auth/data' : strtok($c, ' ')) . ' rejected: ' . trim($all));
            return $all;
        };
        try {
            [$code] = $read(); if ($code !== 220) throw new \RuntimeException('bad SMTP greeting');
            $ehlo = 'EHLO ' . (parse_url(Env::baseUrl(), PHP_URL_HOST) ?: 'localhost');
            $caps = $cmd($ehlo, [250]);
            if ($enc === 'tls') {
                $cmd('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new \RuntimeException('STARTTLS failed');
                $caps = $cmd($ehlo, [250]);
            }
            $user = (string)Env::get('MAIL_USERNAME', ''); $pass = (string)Env::get('MAIL_PASSWORD', '');
            if ($user !== '') {
                if (stripos($caps, 'AUTH') !== false && stripos($caps, 'PLAIN') !== false) $cmd('AUTH PLAIN ' . base64_encode("\0$user\0$pass"), [235]);
                else { $cmd('AUTH LOGIN', [334]); $cmd(base64_encode($user), [334]); $cmd(base64_encode($pass), [235]); }
            }
            [$fa] = self::from();
            $cmd('MAIL FROM:<' . $fa . '>', [250]);
            $cmd('RCPT TO:<' . $to . '>', [250, 251]);
            $cmd('DATA', [354]);
            $b = 'w5d_' . bin2hex(random_bytes(8));
            $msg = implode("\r\n", self::headers($to, $subject, $b, $unsub)) . "\r\n\r\n" . self::body($html, $text, $b);
            $msg = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $msg));
            fwrite($fp, str_replace("\n", "\r\n", $msg) . "\r\n.\r\n");
            [$code, $all] = $read(); if ($code !== 250) throw new \RuntimeException('message rejected: ' . trim($all));
            @fwrite($fp, "QUIT\r\n");
        } finally { @fclose($fp); }
    }

    /* ---------------- bookkeeping ---------------- */
    private static function record(string $to, string $subject, string $type, string $lang, string $driver, string $status): ?int
    {
        try { return DB::insert('mail_outbox', ['to_email' => $to, 'subject' => mb_substr($subject, 0, 200), 'template' => $type, 'lang' => $lang, 'driver' => $driver, 'status' => $status]); } catch (\Throwable) { return null; }
    }
    private static function mark(?int $id, string $status, ?string $err = null): void
    {
        if (!$id) return;
        try { DB::q("UPDATE mail_outbox SET status = ?, error = ?, sent_at = CASE WHEN ? IN ('sent','logged') THEN CURRENT_TIMESTAMP ELSE NULL END WHERE id = ?", [$status, $err ? mb_substr($err, 0, 500) : null, $status, $id]); } catch (\Throwable) {}
    }
    private static function log(string $m): void
    {
        $dir = ROOT . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/mail-' . date('Y-m-d') . '.log', '[' . date('c') . '] ' . $m . "\n", FILE_APPEND);
    }
}
