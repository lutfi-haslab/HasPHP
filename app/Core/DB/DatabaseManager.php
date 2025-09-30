<?php

namespace Hasphp\App\Core\DB;

use Hasphp\App\Core\DB\Drivers\DatabaseDriver;
use Hasphp\App\Core\DB\Drivers\SQLiteDriver;
use Hasphp\App\Core\DB\Drivers\MySQLDriver;
use Hasphp\App\Core\DB\Drivers\PostgreSQLDriver;

class DatabaseManager
{
    /**
     * The database connections.
     */
    protected array $connections = [];
    
    /**
     * The database connection configurations.
     */
    protected array $config;
    
    /**
     * Create a new database manager instance.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * Get a database connection instance.
     */
    public function connection(?string $name = null): DatabaseDriver
    {
        $name = $name ?: $this->getDefaultConnection();
        
        // If we haven't created this connection, we'll create it based on the config
        // provided in the application. Once we've created the connections we will set the
        // reconnector instance on it so that we can reconnect if it gets disconnected.
        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->configure(
                $this->makeConnection($name), $name
            );
        }
        
        return $this->connections[$name];
    }
    
    /**
     * Make the database connection instance.
     */
    protected function makeConnection(string $name): DatabaseDriver
    {
        $config = $this->configuration($name);
        
        return match ($config['driver']) {
            'sqlite' => new SQLiteDriver($config, $name),
            'mysql' => new MySQLDriver($config, $name),
            'pgsql', 'postgresql' => new PostgreSQLDriver($config, $name),
            default => throw new \InvalidArgumentException("Unsupported driver [{$config['driver']}]."),
        };
    }
    
    /**
     * Get the configuration for a connection.
     */
    protected function configuration(string $name): array
    {
        $name = $name ?: $this->getDefaultConnection();
        
        $connections = $this->config['connections'] ?? [];
        
        if (is_null($config = $connections[$name])) {
            throw new \InvalidArgumentException("Database connection [{$name}] not configured.");
        }
        
        return $config;
    }
    
    /**
     * Configure the connection.
     */
    protected function configure(DatabaseDriver $connection, string $name): DatabaseDriver
    {
        // Additional configuration can be added here
        return $connection;
    }
    
    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'default';
    }
    
    /**
     * Set the default connection name.
     */
    public function setDefaultConnection(string $name): void
    {
        $this->config['default'] = $name;
    }
    
    /**
     * Disconnect from the given database.
     */
    public function disconnect(?string $name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();
        
        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }
    
    /**
     * Reconnect to the given database.
     */
    public function reconnect(?string $name = null): DatabaseDriver
    {
        $this->disconnect($name);
        
        return $this->connection($name);
    }
    
    /**
     * Get all of the created connections.
     */
    public function getConnections(): array
    {
        return $this->connections;
    }
    
    /**
     * Set the database connection configuration.
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
    
    /**
     * Get a query builder instance for a table.
     */
    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return (new QueryBuilder($this->connection($connection)))->table($table);
    }
    
    /**
     * Create a database with default configuration.
     */
    public static function createDefault(): self
    {
        return new self([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => __DIR__ . '/../../../database/database.sqlite',
                    'timeout' => 60,
                ],
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'database' => 'hasphp',
                    'username' => 'root',
                    'password' => '',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
                'pgsql' => [
                    'driver' => 'pgsql',
                    'host' => 'localhost',
                    'port' => 5432,
                    'database' => 'hasphp',
                    'username' => 'postgres',
                    'password' => '',
                    'charset' => 'utf8',
                    'schema' => 'public',
                ],
            ],
        ]);
    }
}
