<?php

namespace Hasphp\App\Core\DB\Drivers;

use PDO;
use PDOException;

class PostgreSQLDriver extends BaseDriver
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
            
            // Set PostgreSQL specific configurations
            $pdo->exec("SET search_path TO " . ($this->config['schema'] ?? 'public'));
            $pdo->exec("SET timezone = 'UTC'");
            
            return $pdo;
        } catch (PDOException $e) {
            throw new \Exception("Could not connect to PostgreSQL database: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * Get the DSN string for the connection.
     */
    protected function getDsn(): string
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 5432;
        $database = $this->config['database'];
        
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        
        if (isset($this->config['charset'])) {
            $dsn .= ";options='--client_encoding=" . $this->config['charset'] . "'";
        }
        
        if (isset($this->config['sslmode'])) {
            $dsn .= ";sslmode=" . $this->config['sslmode'];
        }
        
        return $dsn;
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
        ];
        
        // Set statement timeout if configured
        if (isset($this->config['statement_timeout'])) {
            $options[PDO::ATTR_TIMEOUT] = $this->config['statement_timeout'];
        }
        
        return $options;
    }
    
    /**
     * Get the last inserted ID for PostgreSQL using sequence.
     */
    public function lastInsertId(): string|int
    {
        // PostgreSQL uses sequences, so we need to specify the sequence name
        // This is a simplified version - in practice, you'd get the sequence name from the table
        return $this->pdo()->lastInsertId();
    }
    
    /**
     * Get PostgreSQL version information.
     */
    public function getVersion(): string
    {
        return $this->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
    
    /**
     * Check if the PostgreSQL server supports specific features.
     */
    public function supportsJsonb(): bool
    {
        $version = $this->getVersion();
        return version_compare($version, '9.4', '>=');
    }
    
    /**
     * Get the current database size in bytes.
     */
    public function getDatabaseSize(): int
    {
        $database = $this->getDatabaseName();
        $result = $this->selectOne("SELECT pg_database_size(?) as size", [$database]);
        
        return (int) ($result['size'] ?? 0);
    }
    
    /**
     * Get table information for the current database.
     */
    public function getTables(): array
    {
        $schema = $this->config['schema'] ?? 'public';
        
        return $this->select(
            "SELECT table_name, table_type
             FROM information_schema.tables 
             WHERE table_schema = ? 
             ORDER BY table_name",
            [$schema]
        );
    }
    
    /**
     * Get column information for a specific table.
     */
    public function getColumns(string $table): array
    {
        $schema = $this->config['schema'] ?? 'public';
        
        return $this->select(
            "SELECT column_name, data_type, is_nullable, column_default,
                    character_maximum_length, numeric_precision, numeric_scale
             FROM information_schema.columns 
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ordinal_position",
            [$schema, $table]
        );
    }
    
    /**
     * Get index information for a specific table.
     */
    public function getIndexes(string $table): array
    {
        $schema = $this->config['schema'] ?? 'public';
        
        return $this->select(
            "SELECT 
                i.relname as index_name,
                a.attname as column_name,
                ix.indisunique as is_unique,
                ix.indisprimary as is_primary
             FROM pg_class t
             JOIN pg_index ix ON t.oid = ix.indrelid
             JOIN pg_class i ON i.oid = ix.indexrelid
             JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE n.nspname = ? AND t.relname = ?
             ORDER BY i.relname, a.attname",
            [$schema, $table]
        );
    }
    
    /**
     * Get foreign key information for a specific table.
     */
    public function getForeignKeys(string $table): array
    {
        $schema = $this->config['schema'] ?? 'public';
        
        return $this->select(
            "SELECT
                kcu.column_name,
                ccu.table_name AS foreign_table_name,
                ccu.column_name AS foreign_column_name,
                rc.constraint_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
                ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage AS ccu
                ON ccu.constraint_name = tc.constraint_name
            JOIN information_schema.referential_constraints AS rc
                ON tc.constraint_name = rc.constraint_name
            WHERE constraint_type = 'FOREIGN KEY' 
                AND tc.table_schema = ?
                AND tc.table_name = ?",
            [$schema, $table]
        );
    }
    
    /**
     * Execute VACUUM on the database.
     */
    public function vacuum(): bool
    {
        return $this->statement('VACUUM');
    }
    
    /**
     * Execute ANALYZE on the database.
     */
    public function analyze(): bool
    {
        return $this->statement('ANALYZE');
    }
    
    /**
     * Get active connections count.
     */
    public function getActiveConnections(): int
    {
        $database = $this->getDatabaseName();
        $result = $this->selectOne(
            "SELECT count(*) as connections 
             FROM pg_stat_activity 
             WHERE datname = ?",
            [$database]
        );
        
        return (int) ($result['connections'] ?? 0);
    }
    
    /**
     * Get database statistics.
     */
    public function getDatabaseStats(): array
    {
        $database = $this->getDatabaseName();
        
        return $this->selectOne(
            "SELECT 
                numbackends as active_connections,
                xact_commit as transactions_committed,
                xact_rollback as transactions_rolled_back,
                blks_read as blocks_read,
                blks_hit as blocks_hit,
                tup_returned as tuples_returned,
                tup_fetched as tuples_fetched,
                tup_inserted as tuples_inserted,
                tup_updated as tuples_updated,
                tup_deleted as tuples_deleted
             FROM pg_stat_database 
             WHERE datname = ?",
            [$database]
        ) ?? [];
    }
    
    /**
     * Create a database backup using pg_dump equivalent query.
     */
    public function exportSchema(): string
    {
        $schema = $this->config['schema'] ?? 'public';
        $tables = $this->getTables();
        
        $dump = "-- PostgreSQL Schema Export\n";
        $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            $tableName = $table['table_name'];
            
            // Get table structure
            $columns = $this->getColumns($tableName);
            $dump .= "-- Table: {$tableName}\n";
            $dump .= "CREATE TABLE {$tableName} (\n";
            
            $columnDefs = [];
            foreach ($columns as $column) {
                $def = "    " . $column['column_name'] . " " . $column['data_type'];
                if ($column['is_nullable'] === 'NO') {
                    $def .= " NOT NULL";
                }
                if (!is_null($column['column_default'])) {
                    $def .= " DEFAULT " . $column['column_default'];
                }
                $columnDefs[] = $def;
            }
            
            $dump .= implode(",\n", $columnDefs);
            $dump .= "\n);\n\n";
        }
        
        return $dump;
    }
    
    /**
     * Set the statement timeout for queries.
     */
    public function setStatementTimeout(int $milliseconds): void
    {
        $this->statement("SET statement_timeout = {$milliseconds}");
    }
    
    /**
     * Enable or disable autocommit mode.
     */
    public function setAutocommit(bool $enabled): void
    {
        if ($enabled) {
            $this->statement('SET autocommit = ON');
        } else {
            $this->statement('SET autocommit = OFF');
        }
    }
}
