<?php
declare(strict_types=1);

namespace W5D;

/** D17 (La Poste Tunisienne wallet) manual payments. Tunisian residents only; every payment is verified by an administrator. */
final class Payments
{
    public static function enabled(): bool { return Env::bool('PAYMENT_D17_ENABLED') && self::gsm() !== ''; }
    public static function gsm(): string { $g = trim((string)Env::get('PAYMENT_D17_GSM', '')); return preg_match('/\d{8}/', $g) ? $g : ''; }
    public static function holder(): string { return (string)Env::get('PAYMENT_D17_NAME', 'W5D TECH'); }
    public static function note(): string { return (string)Env::get('PAYMENT_D17_NOTE', ''); }

    /** Normalise a Tunisian GSM to +216XXXXXXXX or null. Mobile ranges start with 2, 4, 5 or 9. */
    public static function tnGsm(string $raw): ?string
    {
        $d = preg_replace('/[\s().-]+/', '', $raw);
        if (preg_match('/^(?:\+|00)?216([2459]\d{7})$/', $d, $m) || preg_match('/^([2459]\d{7})$/', $d, $m)) return '+216' . $m[1];
        return null;
    }

    public static function sig(string $orderRef): string { return Secret::sign('pay:' . $orderRef); }
    public static function link(string $orderRef): string { return url('/pay/' . $orderRef . '/') . '?s=' . self::sig($orderRef); }
    public static function absLink(string $orderRef): string { return Env::baseUrl() . self::link($orderRef); }

    /** Instructions block for mail/pages. */
    public static function instructions(float $amount): array
    {
        return ['gsm' => self::gsm(), 'name' => self::holder(), 'amount' => $amount, 'note' => self::note()];
    }

    public static function create(string $orderRef, int $investorId, float $amount): string
    {
        $ref = 'PAY-' . strtoupper(bin2hex(random_bytes(4)));
        DB::insert('payments', ['ref' => $ref, 'order_ref' => $orderRef, 'investor_id' => $investorId, 'method' => 'd17', 'amount' => round($amount, 2), 'currency' => (string)Variables::get('money.currency', 'TND'), 'status' => 'pending']);
        return $ref;
    }
}
