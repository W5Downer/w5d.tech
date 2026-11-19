<?php
/**
 * Descriptions + usage for every documented item, shown in each audience's dashboard ("API & Docs").
 * Parts (config/apidocs/*.php): public[operationId] · session[path] · panels[account panel] · screens[menu slug] · kinds[screen kind]
 * Endpoint lists, fields and CRUD rights are NOT repeated here: they are generated from Docs::endpoints(),
 * ApiGuide::sessionOps() and config/resources.php, so the documentation cannot drift from the code.
 */
return [
    'public' => require __DIR__ . '/apidocs/public.php',
    'session' => require __DIR__ . '/apidocs/session.php',
    'panels' => require __DIR__ . '/apidocs/panels.php',
    'screens' => (require __DIR__ . '/apidocs/screens.php')['screens'],
    'kinds' => (require __DIR__ . '/apidocs/screens.php')['kinds'],
];
