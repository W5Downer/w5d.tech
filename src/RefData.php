<?php
declare(strict_types=1);

namespace W5D;

/** Mirrors data/content into DB-side reference tables (countries, regions, roles, permissions, services_country). Idempotent. */
final class RefData
{
    public static function sync(): string
    {
        $c = Content::file('countries/tn.json');
        DB::q('INSERT OR REPLACE INTO countries(country_id, iso, name_en, currency, timezone) VALUES (?,?,?,?,?)', [$c['iso'], strtoupper($c['iso']), $c['names']['en'], $c['currency'], $c['timezone']]);
        foreach ($c['regions'] as $r) {
            DB::q('INSERT OR REPLACE INTO regions(country_id, slug, name_en, name_fr, name_ar, quota) VALUES (?,?,?,?,?,?)', [$c['iso'], $r['slug'], $r['name']['en'], $r['name']['fr'], $r['name']['ar'], 0]);
        }
        $matrix = require ROOT . '/config/roles.php';
        foreach ($matrix['roles'] as $role => $perms) {
            DB::q('INSERT OR REPLACE INTO roles(country_id, slug, label) VALUES (?,?,?)', [$c['iso'], $role, ucfirst($role)]);
            foreach ($perms as $p) DB::q('INSERT OR IGNORE INTO permissions(country_id, role_slug, perm) VALUES (?,?,?)', [$c['iso'], $role, $p]);
        }
        foreach (Content::all('services') as $s) {
            DB::q('INSERT OR IGNORE INTO services_country(country_id, service_slug, active, seats) VALUES (?,?,?,?)', [$c['iso'], $s['slug'], 0, 0]);
        }
        return 'synced: ' . count($c['regions']) . ' regions, ' . count($matrix['roles']) . ' roles, ' . Content::count('services') . ' services';
    }
}
