<?php

namespace Hasphp\App\Core\DB\Drivers;

use PDO;
use PDOStatement;
use PDOException;

abstract class BaseDriver implements DatabaseDriver
{
    /**
     * The PDO connection instance.
     */
    protected ?PDO $pdo = null;
    
    /**
     * The database connection configuration.
     */
    protected array $config;
    
    /**
     * The name of the connection.
     */
    protected string $name;
    
    /**
     * The number of active transactions.
     */
    protected int $transactions = 0;
    
    /**
     * Create a new database driver instance.
     */
    public function __construct(array $config, string $name = 'default')
    {
        $this->config = $config;
        $this->name = $name;
    }
    
    /**
     * Get the PDO connection instance.
     */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->createConnection();
        }
        
        return $this->pdo;
    }
    
    /**
     * Get the database connection configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): bool
    {
        if ($this->transactions == 0) {
            try {
                $result = $this->pdo()->beginTransaction();
            } catch (PDOException $e) {
                throw new \Exception("Failed to begin transaction: " . $e->getMessage(), $e->getCode(), $e);
            }
        }
        
        $this->transactions++;
        
        return $result ?? true;
    }
    
    /**
     * Commit a database transaction.
     */
    public function commit(): bool
    {
        if ($this->transactions == 1) {
            try {
                $result = $this->pdo()->commit();
            } catch (PDOException $e) {
                throw new \Exception("Failed to commit transaction: " . $e->getMessage(), $e->getCode(), $e);
            }
        }
        
        $this->transactions = max(0, $this->transactions - 1);
        
        return $result ?? true;
    }
    
    /**
     * Rollback a database transaction.
     */
    public function rollback(): bool
    {
        if ($this->transactions == 1) {
            try {
                $result = $this->pdo()->rollBack();
            } catch (PDOException $e) {
                throw new \Exception("Failed to rollback transaction: " . $e->getMessage(), $e->getCode(), $e);
            }
            
            $this->transactions = 0;
        } else {
            $this->transactions = max(0, $this->transactions - 1);
        }
        
        return $result ?? true;
    }
    
    /**
     * Execute a statement and return the number of affected rows.
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->pdo()->prepare($query);
            $statement->execute($bindings);
            
            return $statement->rowCount();
        });
    }
    
    /**
     * Run a select statement and return a single result.
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        $records = $this->select($query, $bindings);
        
        return array_shift($records);
    }
    
    /**
     * Run a select statement against the database.
     */
    public function select(string $query, array $bindings = []): array
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->pdo()->prepare($query);
            $statement->execute($bindings);
            
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        });
    }
    
    /**
     * Run an insert statement against the database.
     */
    public function insert(string $query, array $bindings = []): bool
    {
        return $this->statement($query, $bindings);
    }
    
    /**
     * Run an update statement against the database.
     */
    public function update(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }
    
    /**
     * Run a delete statement against the database.
     */
    public function delete(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }
    
    /**
     * Execute a statement against the database.
     */
    public function statement(string $query, array $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            return $this->pdo()->prepare($query)->execute($bindings);
        });
    }
    
    /**
     * Get the last inserted ID.
     */
    public function lastInsertId(): string|int
    {
        return $this->pdo()->lastInsertId();
    }
    
    /**
     * Determine if the connection is in a transaction.
     */
    public function inTransaction(): bool
    {
        return $this->pdo()->inTransaction();
    }
    
    /**
     * Get the database connection name.
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Set the query timeout in seconds.
     */
    public function setTimeout(int $seconds): void
    {
        $this->pdo()->setAttribute(PDO::ATTR_TIMEOUT, $seconds);
    }
    
    /**
     * Disconnect from the database.
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }
    
    /**
     * Reconnect to the database.
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->pdo();
    }
    
    /**
     * Run a SQL statement and log its execution context.
     */
    protected function run(string $query, array $bindings, \Closure $callback)
    {
        try {
            $result = $callback($query, $bindings);
        } catch (PDOException $e) {
            throw new \Exception(
                "Database query failed: " . $e->getMessage() . " (Query: {$query})",
                $e->getCode(),
                $e
            );
        }
        
        return $result;
    }
    
    /**
     * Create the PDO connection.
     */
    abstract protected function createConnection(): PDO;
    
    /**
     * Get the DSN string for the connection.
     */
    abstract protected function getDsn(): string;
    
    /**
     * Get the database name from the configuration.
     */
    abstract public function getDatabaseName(): string;
}
