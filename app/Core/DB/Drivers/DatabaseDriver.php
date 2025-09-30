<?php

namespace Hasphp\App\Core\DB\Drivers;

use PDO;

interface DatabaseDriver
{
    /**
     * Get the PDO connection instance.
     */
    public function pdo(): PDO;
    
    /**
     * Get the database connection configuration.
     */
    public function getConfig(): array;
    
    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): bool;
    
    /**
     * Commit a database transaction.
     */
    public function commit(): bool;
    
    /**
     * Rollback a database transaction.
     */
    public function rollback(): bool;
    
    /**
     * Execute a statement and return the number of affected rows.
     */
    public function affectingStatement(string $query, array $bindings = []): int;
    
    /**
     * Run a select statement and return a single result.
     */
    public function selectOne(string $query, array $bindings = []): ?array;
    
    /**
     * Run a select statement against the database.
     */
    public function select(string $query, array $bindings = []): array;
    
    /**
     * Run an insert statement against the database.
     */
    public function insert(string $query, array $bindings = []): bool;
    
    /**
     * Run an update statement against the database.
     */
    public function update(string $query, array $bindings = []): int;
    
    /**
     * Run a delete statement against the database.
     */
    public function delete(string $query, array $bindings = []): int;
    
    /**
     * Execute a statement against the database.
     */
    public function statement(string $query, array $bindings = []): bool;
    
    /**
     * Get the last inserted ID.
     */
    public function lastInsertId(): string|int;
    
    /**
     * Determine if the connection is in a transaction.
     */
    public function inTransaction(): bool;
    
    /**
     * Get the database connection name.
     */
    public function getName(): string;
    
    /**
     * Get the database name.
     */
    public function getDatabaseName(): string;
    
    /**
     * Set the query timeout in seconds.
     */
    public function setTimeout(int $seconds): void;
    
    /**
     * Disconnect from the database.
     */
    public function disconnect(): void;
    
    /**
     * Reconnect to the database.
     */
    public function reconnect(): void;
}
