<?php
namespace Hasphp\App\Core\DB;

use Hasphp\App\Core\DB\Drivers\SQLiteDriver;
use PDO;

class QueryBuilder {
    private PDO $pdo;
    private string $table = '';
    private array $wheres = [];

    public function __construct() {
        $this->pdo = (new SQLiteDriver())->pdo();
    }

    public function table(string $table): self {
        $this->table = $table;
        return $this;
    }

    public function where(string $column, $value): self {
        $this->wheres[] = [$column, $value];
        return $this;
    }

    public function get(): array {
        $sql = "SELECT * FROM {$this->table}";
        if (!empty($this->wheres)) {
            $conditions = array_map(fn($w) => "{$w[0]} = ?", $this->wheres);
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_column($this->wheres, 1));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $data): bool {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} ($columns) VALUES ($placeholders)");
        return $stmt->execute(array_values($data));
    }
}