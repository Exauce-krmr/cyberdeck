<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Session::start(Config::get('app.session_days'));

$services = (require BASE_PATH . '/app/bootstrap.php')(Database::connect(Config::get('app.db')));
(require BASE_PATH . '/app/routes.php')($services);

app()->run();
