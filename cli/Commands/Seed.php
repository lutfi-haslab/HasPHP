<?php
namespace Hasphp\Cli\Commands;

use Hasphp\App\Core\DB\QueryBuilder;

class Seed {
    public static function run() {
        $db = new QueryBuilder();
        $db->table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => password_hash('secret', PASSWORD_BCRYPT),
        ]);

        echo "Database seeded.\n";
    }
}