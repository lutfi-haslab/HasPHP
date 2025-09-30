<?php

namespace Hasphp\App\Core\DB\Drivers;

use PDO;
use PDOException;

class SQLiteDriver extends BaseDriver
{
    /**
     * Create the PDO connection.
     */
    protected function createConnection(): PDO
    {
        $dsn = $this->getDsn();
        $options = $this->getOptions();
        
        try {
            $pdo = new PDO($dsn, null, null, $options);
            
            // Enable foreign key constraints
            $pdo->exec('PRAGMA foreign_keys = ON');
            
            // Enable WAL mode for better concurrency
            $pdo->exec('PRAGMA journal_mode = WAL');
            
            // Set synchronous mode for better performance
            $pdo->exec('PRAGMA synchronous = NORMAL');
            
            return $pdo;
        } catch (PDOException $e) {
            throw new \Exception("Could not connect to SQLite database: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * Get the DSN string for the connection.
     */
    protected function getDsn(): string
    {
        $database = $this->config['database'] ?? __DIR__ . '/../../../../database/database.sqlite';
        
        // Handle in-memory database
        if ($database === ':memory:') {
            return 'sqlite::memory:';
        }
        
        // Ensure the database directory exists
        $directory = dirname($database);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        return "sqlite:{$database}";
    }
    
    /**
     * Get the database name from the configuration.
     */
    public function getDatabaseName(): string
    {
        $database = $this->config['database'] ?? 'database.sqlite';
        return basename($database, '.sqlite');
    }
    
    /**
     * Get the PDO connection options.
     */
    protected function getOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $this->config['timeout'] ?? 60,
        ];
    }
    
    /**
     * Enable or disable foreign key constraints.
     */
    public function setForeignKeyChecks(bool $enabled): void
    {
        $value = $enabled ? 'ON' : 'OFF';
        $this->statement("PRAGMA foreign_keys = {$value}");
    }
    
    /**
     * Get SQLite version information.
     */
    public function getVersion(): string
    {
        return $this->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
    
    /**
     * Get the size of the database file in bytes.
     */
    public function getDatabaseSize(): int
    {
        $database = $this->config['database'] ?? __DIR__ . '/../../../../database/database.sqlite';
        
        if ($database === ':memory:') {
            // For in-memory databases, we can't get file size
            return 0;
        }
        
        return file_exists($database) ? filesize($database) : 0;
    }
    
    /**
     * Get table information for the current database.
     */
    public function getTables(): array
    {
        return $this->select(
            "SELECT name as table_name, sql as table_sql 
             FROM sqlite_master 
             WHERE type = 'table' AND name NOT LIKE 'sqlite_%' 
             ORDER BY name"
        );
    }
    
    /**
     * Get column information for a specific table.
     */
    public function getColumns(string $table): array
    {
        return $this->select("PRAGMA table_info({$table})");
    }
    
    /**
     * Get index information for a specific table.
     */
    public function getIndexes(string $table): array
    {
        return $this->select("PRAGMA index_list({$table})");
    }
    
    /**
     * Vacuum the database to reclaim space.
     */
    public function vacuum(): bool
    {
        return $this->statement('VACUUM');
    }
    
    /**
     * Analyze the database for query optimization.
     */
    public function analyze(): bool
    {
        return $this->statement('ANALYZE');
    }
    
    /**
     * Check the integrity of the database.
     */
    public function integrityCheck(): array
    {
        return $this->select('PRAGMA integrity_check');
    }
    
    /**
     * Backup the database to another file.
     */
    public function backup(string $destination): bool
    {
        try {
            $backup = new PDO("sqlite:{$destination}");
            $backup->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Simple backup by dumping and restoring
            $tables = $this->getTables();
            
            foreach ($tables as $table) {
                $tableName = $table['table_name'];
                
                // Create table
                $backup->exec($table['table_sql']);
                
                // Copy data
                $data = $this->select("SELECT * FROM {$tableName}");
                
                if (!empty($data)) {
                    $columns = array_keys($data[0]);
                    $placeholders = str_repeat('?,', count($columns) - 1) . '?';
                    $insertSql = "INSERT INTO {$tableName} (" . implode(',', $columns) . ") VALUES ({$placeholders})";
                    
                    $stmt = $backup->prepare($insertSql);
                    
                    foreach ($data as $row) {
                        $stmt->execute(array_values($row));
                    }
                }
            }
            
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Failed to backup database: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
}