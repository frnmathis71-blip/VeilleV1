<?php

/*
| Tâche planifiée OVHcloud (hébergement web) — à déclarer « toutes les heures ».
| Gestionnaire OVH : Hébergements > Tâches planifiées - Cron > Ajouter
|   Commande à exécuter : <dossier-du-site>/cron/veille.php   (ex. www/veille/cron/veille.php)
|   Langage : PHP 8.4
| Ce fichier est hors du dossier public : il n'est pas accessible depuis le web.
*/

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Interdit.');
}

define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$output = new ConsoleOutput;

// Without SSH (Perso offer), pending migrations of a new version are applied here. Nothing happens when up to date.
$status = $kernel->handle(new ArrayInput(['command' => 'migrate', '--force' => true]), $output);
if ($status === 0) {
    // The 15 watch categories (idempotent: existing ones and their settings are kept).
    $status = $kernel->handle(new ArrayInput(['command' => 'db:seed', '--class' => 'Database\\Seeders\\CategorySeeder', '--force' => true]), $output);
}
if ($status === 0) {
    $status = $kernel->handle(new ArrayInput(['command' => 'veille:cron']), $output);
}
$kernel->terminate(new ArrayInput([]), $status);

exit($status);
