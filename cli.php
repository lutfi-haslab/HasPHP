<?php
require __DIR__ . '/vendor/autoload.php';

use Hasphp\Cli\Commands\Migrate;
use Hasphp\Cli\Commands\Seed;

$command = $argv[1] ?? null;

switch ($command) {
    case 'migrate':
        Migrate::run();
        break;
    case 'seed':
        Seed::run();
        break;
    default:
        echo "Usage: php cli.php [migrate|seed]\n";
        break;
}