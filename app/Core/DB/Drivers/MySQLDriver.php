<?php

namespace Hasphp\App\Core\DB\Drivers;

use PDO;
use PDOException;

class MySQLDriver extends BaseDriver
{
    /**
     * Create the PDO connection.
     */
    protected function createConnection(): PDO
    {
        $dsn = $this->getDsn();
        $options = $this->getOptions();
        
        try {
            $pdo = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
            
            // Set MySQL specific configurations
            $pdo->exec("SET sql_mode='STRICT_TRANS_TABLES'");
            $pdo->exec("SET SESSION time_zone = '+00:00'");
            
            return $pdo;
        } catch (PDOException $e) {
            throw new \Exception("Could not connect to MySQL database: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * Get the DSN string for the connection.
     */
    protected function getDsn(): string
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'];
        $charset = $this->config['charset'] ?? 'utf8mb4';
        
        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }
    
    /**
     * Get the database name from the configuration.
     */
    public function getDatabaseName(): string
    {
        return $this->config['database'];
    }
    
    /**
     * Get the PDO connection options.
     */
    protected function getOptions(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . ($this->config['charset'] ?? 'utf8mb4') . " COLLATE " . ($this->config['collation'] ?? 'utf8mb4_unicode_ci'),
        ];
        
        // Enable SSL if configured
        if (isset($this->config['ssl_ca'])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $this->config['ssl_ca'];
        }
        
        if (isset($this->config['ssl_cert'])) {
            $options[PDO::MYSQL_ATTR_SSL_CERT] = $this->config['ssl_cert'];
        }
        
        if (isset($this->config['ssl_key'])) {
            $options[PDO::MYSQL_ATTR_SSL_KEY] = $this->config['ssl_key'];
        }
        
        return $options;
    }
    
    /**
     * Get the last inserted ID for MySQL.
     */
    public function lastInsertId(): string|int
    {
        return $this->pdo()->lastInsertId();
    }
    
    /**
     * Compile a select query for MySQL specific features.
     */
    public function compileSelect(array $bindings = []): string
    {
        // MySQL specific select compilation can be added here
        // For now, use the standard SQL compilation
        return parent::compileSelect($bindings);
    }
    
    /**
     * Get MySQL version information.
     */
    public function getVersion(): string
    {
        return $this->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
    
    /**
     * Check if the MySQL server supports JSON data type.
     */
    public function supportsJson(): bool
    {
        $version = $this->getVersion();
        return version_compare($version, '5.7.8', '>=');
    }
    
    /**
     * Enable or disable foreign key constraints.
     */
    public function setForeignKeyChecks(bool $enabled): void
    {
        $value = $enabled ? 1 : 0;
        $this->statement("SET FOREIGN_KEY_CHECKS = {$value}");
    }
    
    /**
     * Get the size of the database in bytes.
     */
    public function getDatabaseSize(): int
    {
        $database = $this->getDatabaseName();
        $result = $this->selectOne(
            "SELECT SUM(data_length + index_length) as size 
             FROM information_schema.tables 
             WHERE table_schema = ?", 
            [$database]
        );
        
        return (int) ($result['size'] ?? 0);
    }
    
    /**
     * Get table information for the current database.
     */
    public function getTables(): array
    {
        $database = $this->getDatabaseName();
        
        return $this->select(
            "SELECT table_name, table_comment, engine, table_collation
             FROM information_schema.tables 
             WHERE table_schema = ? 
             ORDER BY table_name",
            [$database]
        );
    }
    
    /**
     * Get column information for a specific table.
     */
    public function getColumns(string $table): array
    {
        $database = $this->getDatabaseName();
        
        return $this->select(
            "SELECT column_name, data_type, is_nullable, column_default, 
                    character_maximum_length, numeric_precision, numeric_scale,
                    column_key, extra, column_comment
             FROM information_schema.columns 
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ordinal_position",
            [$database, $table]
        );
    }
}
