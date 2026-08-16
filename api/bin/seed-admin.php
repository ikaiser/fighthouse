<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Admin;

\App\bootstrapApp();

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (!$username || !$password) {
    fwrite(STDERR, "Uso: php bin/seed-admin.php <username> <password>\n");
    exit(1);
}

Admin::updateOrCreate(
    ['username' => $username],
    ['password_hash' => password_hash($password, PASSWORD_BCRYPT)]
);

echo "Admin '{$username}' creato/aggiornato.\n";
