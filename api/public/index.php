<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use function App\bootstrapApp;

$app = bootstrapApp();
$app->run();
