<?php
namespace Hasphp\App\Core\DB\Drivers;

use PDO;

class SQLiteDriver {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = new PDO('sqlite:' . __DIR__ . '/../../../../database/database.sqlite');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function pdo(): PDO {
        return $this->pdo;
    }

    public function exec(string $sql): bool {
        return $this->pdo->exec($sql) !== false;
    }
}