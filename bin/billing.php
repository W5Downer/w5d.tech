<?php
// php bin/billing.php
// Plan renewals: sends the renewal invoice a few days before a plan period ends, stops plans cancelled at period end,
// and lets a plan lapse when its renewal is not paid after the grace period (variables billing.renew_days_before / billing.grace_days).
// Nothing is ever charged by itself: the buyer pays the invoice like any other bill. Safe to run often (cron: once a day).
require __DIR__ . '/_boot.php';

$r = \W5D\Billing::runRenewals();
echo 'renewal invoices: ' . $r['invoiced'] . ', lapsed: ' . $r['expired'] . ', stopped at period end: ' . $r['cancelled'] . "\n";
