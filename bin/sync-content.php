<?php
// php bin/sync-content.php — mirrors data/content into DB-side reference tables (countries, regions, roles, permissions, services_country).
require __DIR__ . '/_boot.php';
use W5D\{DB, Content};
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
echo "synced: " . count($c['regions']) . " regions, " . count($matrix['roles']) . " roles, " . Content::count('services') . " services\n";
