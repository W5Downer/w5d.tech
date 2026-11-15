<?php
declare(strict_types=1);

/** Single front controller. Document root = htdocs/. Everything else lives one level up (never web-served). */
define('ROOT', dirname(__DIR__));
require ROOT . '/src/autoload.php';

use W5D\{App};

App::run();
