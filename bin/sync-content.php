<?php
// php bin/sync-content.php — mirrors data/content into DB-side reference tables (also runs automatically on a fresh DB).
require __DIR__ . '/_boot.php';
echo W5D\RefData::sync() . "\n";
