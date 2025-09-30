<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;
use Hasphp\App\Core\DB\DatabaseManager;

/**
 * Migrate Command
 * Run database migrations
 */
class MigrateCommand extends Command
{
    protected string $name = 'migrate';
    protected string $description = 'Run database migrations';

    private DatabaseManager $db;

    public function __construct()
    {
        $this->addOption('step', '', true, 'Number of migrations to run');
        $this->addOption('force', 'f', false, 'Force the operation to run in production');
        $this->addOption('pretend', 'p', false, 'Show the queries that would be run');
    }

    public function handle(): int
    {
        try {
            // Initialize database
            $this->initializeDatabase();
            
            // Create migrations table if it doesn't exist
            $this->createMigrationsTable();
            
            // Get pending migrations
            $pendingMigrations = $this->getPendingMigrations();
            
            if (empty($pendingMigrations)) {
                $this->info("Nothing to migrate.");
                return 0;
            }
            
            $this->info("Running migrations...");
            $this->line();
            
            $step = $this->option('step');
            $pretend = $this->option('pretend');
            
            if ($step) {
                $pendingMigrations = array_slice($pendingMigrations, 0, (int) $step);
            }
            
            foreach ($pendingMigrations as $migration) {
                $this->runMigration($migration, $pretend);
            }
            
            $this->line();
            $this->success("Migration completed successfully!");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Initialize database connection
     */
    private function initializeDatabase(): void
    {
        $app = require __DIR__ . '/../../../../bootstrap/app.php';
        $this->db = $app->resolve('db');
    }

    /**
     * Create migrations table
     */
    private function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->db->query($sql);
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations(): array
    {
        // Get all migration files
        $migrationFiles = glob('database/migrations/*.php');
        sort($migrationFiles);
        
        // Get executed migrations
        $executed = $this->db->query("SELECT migration FROM migrations")->fetchAll();
        $executedMigrations = array_column($executed, 'migration');
        
        // Filter pending migrations
        $pendingMigrations = [];
        foreach ($migrationFiles as $file) {
            $migrationName = basename($file, '.php');
            if (!in_array($migrationName, $executedMigrations)) {
                $pendingMigrations[] = $file;
            }
        }
        
        return $pendingMigrations;
    }

    /**
     * Run a single migration
     */
    private function runMigration(string $migrationFile, bool $pretend = false): void
    {
        $migrationName = basename($migrationFile, '.php');
        
        try {
            // Include the migration file
            require_once $migrationFile;
            
            // Get the migration class name
            $className = $this->getMigrationClassName($migrationName);
            
            if (!class_exists($className)) {
                throw new \Exception("Migration class {$className} not found in {$migrationFile}");
            }
            
            // Create instance and run up method
            $migration = new $className();
            
            if ($pretend) {
                $this->warning("Would run: {$migrationName}");
                return;
            }
            
            if (method_exists($migration, 'up')) {
                $migration->up();
                
                // Record the migration
                $batch = $this->getNextBatchNumber();
                $this->db->query(
                    "INSERT INTO migrations (migration, batch) VALUES (?, ?)",
                    [$migrationName, $batch]
                );
                
                $this->success("Migrated: {$migrationName}");
            } else {
                $this->warning("No 'up' method found in {$className}");
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to run {$migrationName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get migration class name from filename
     */
    private function getMigrationClassName(string $filename): string
    {
        // Remove timestamp prefix (e.g., "2023_12_01_123456_create_users_table" -> "create_users_table")
        $parts = explode('_', $filename);
        $nameParts = array_slice($parts, 4); // Skip date and time parts
        $name = implode('_', $nameParts);
        
        // Convert to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    /**
     * Get next batch number
     */
    private function getNextBatchNumber(): int
    {
        $result = $this->db->query("SELECT MAX(batch) as max_batch FROM migrations")->fetch();
        return ($result['max_batch'] ?? 0) + 1;
    }
}
