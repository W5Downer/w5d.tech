<?php
// php bin/rollup.php — aggregates raw tables into rollups_daily (the ONLY table dashboards/APIs read).
require __DIR__ . '/_boot.php';
use W5D\DB;
$day = date('Y-m-d');
$row = fn(string $macro, string $where, array $p) => [
    (int)DB::val("SELECT COUNT(*) FROM applicants WHERE country_id='tn' $where", $p),
    (int)DB::val("SELECT COUNT(*) FROM applicants WHERE country_id='tn' AND status IN ('verified','screened','assigned','l1','l2','l3','l4','certified') $where", $p),
    (int)DB::val("SELECT COUNT(*) FROM applicants WHERE country_id='tn' AND status='certified' $where", $p),
];
$sets = [['*', '', []]];
foreach (['cultures', 'built', 'sciences', 'governance', 'technology'] as $m) $sets[] = [$m, 'AND macro_slug = ?', [$m]];
foreach ($sets as [$macro, $where, $p]) {
    [$reg, $ver, $cert] = $row($macro, $where, $p);
    $sub = $macro === '*' ? '' : ' AND service_slug IN (SELECT slug FROM (SELECT 1 AS slug) WHERE 0)';
    $rs = (int)DB::val("SELECT COUNT(*) FROM weekly_reports WHERE country_id='tn' AND status IN ('submitted','approved')" . ($macro === '*' ? '' : ' AND 0'));
    $ra = (int)DB::val("SELECT COUNT(*) FROM weekly_reports WHERE country_id='tn' AND status='approved'" . ($macro === '*' ? '' : ' AND 0'));
    DB::q('INSERT INTO rollups_daily(country_id, day, macro_slug, registered, verified, certified, reports_submitted, reports_approved) VALUES (?,?,?,?,?,?,?,?)
           ON CONFLICT(country_id, day, macro_slug) DO UPDATE SET registered=excluded.registered, verified=excluded.verified, certified=excluded.certified, reports_submitted=excluded.reports_submitted, reports_approved=excluded.reports_approved',
        ['tn', $day, $macro, $reg, $ver, $cert, $rs, $ra]);
}
echo "rollup ok for $day\n";
