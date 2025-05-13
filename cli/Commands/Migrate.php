<?php
namespace Hasphp\Cli\Commands;

use Hasphp\App\Core\DB\Drivers\SQLiteDriver;

class Migrate {
    public static function run() {
        $driver = new SQLiteDriver();
        $migrations = glob(__DIR__ . '/../../database/migrations/*.php');

        foreach ($migrations as $migration) {
            $queries = require $migration;
            foreach ((array) $queries as $query) {
                $driver->exec($query);
            }
        }

        echo "Migrations executed.\n";
    }
}