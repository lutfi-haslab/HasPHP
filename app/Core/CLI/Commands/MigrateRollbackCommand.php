<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;
use Hasphp\App\Core\DB\DatabaseManager;

/**
 * Migrate Rollback Command
 * Rollback database migrations
 */
class MigrateRollbackCommand extends Command
{
    protected string $name = 'migrate:rollback';
    protected string $description = 'Rollback database migrations';

    private DatabaseManager $db;

    public function __construct()
    {
        $this->addOption('step', '', true, 'Number of migrations to rollback');
        $this->addOption('batch', '', true, 'Rollback specific batch');
        $this->addOption('force', 'f', false, 'Force the operation in production');
        $this->addOption('pretend', 'p', false, 'Show the queries that would be run');
    }

    public function handle(): int
    {
        try {
            // Initialize database
            $this->initializeDatabase();
            
            $step = $this->option('step');
            $batch = $this->option('batch');
            $pretend = $this->option('pretend');
            
            if ($batch) {
                $migrationsToRollback = $this->getMigrationsByBatch((int) $batch);
            } else {
                $steps = $step ? (int) $step : 1;
                $migrationsToRollback = $this->getLastMigrations($steps);
            }
            
            if (empty($migrationsToRollback)) {
                $this->info("Nothing to rollback.");
                return 0;
            }
            
            $this->info("Rolling back migrations...");
            $this->line();
            
            foreach (array_reverse($migrationsToRollback) as $migration) {
                $this->rollbackMigration($migration, $pretend);
            }
            
            $this->line();
            $this->success("Rollback completed successfully!");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Rollback failed: " . $e->getMessage());
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
     * Get migrations by batch
     */
    private function getMigrationsByBatch(int $batch): array
    {
        return $this->db->query(
            "SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC",
            [$batch]
        )->fetchAll();
    }

    /**
     * Get last migrations
     */
    private function getLastMigrations(int $steps): array
    {
        return $this->db->query(
            "SELECT migration FROM migrations ORDER BY id DESC LIMIT ?",
            [$steps]
        )->fetchAll();
    }

    /**
     * Rollback a single migration
     */
    private function rollbackMigration(array $migrationRecord, bool $pretend = false): void
    {
        $migrationName = $migrationRecord['migration'];
        $migrationFile = glob("database/migrations/*_{$migrationName}.php")[0] ?? null;
        
        if (!$migrationFile) {
            $this->warning("Migration file not found for: {$migrationName}");
            return;
        }
        
        try {
            // Include the migration file
            require_once $migrationFile;
            
            // Get the migration class name
            $className = $this->getMigrationClassName($migrationName);
            
            if (!class_exists($className)) {
                throw new \Exception("Migration class {$className} not found");
            }
            
            // Create instance and run down method
            $migration = new $className();
            
            if ($pretend) {
                $this->warning("Would rollback: {$migrationName}");
                return;
            }
            
            if (method_exists($migration, 'down')) {
                $migration->down();
                
                // Remove the migration record
                $this->db->query(
                    "DELETE FROM migrations WHERE migration = ?",
                    [$migrationName]
                );
                
                $this->success("Rolled back: {$migrationName}");
            } else {
                $this->warning("No 'down' method found in {$className}");
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to rollback {$migrationName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get migration class name from filename
     */
    private function getMigrationClassName(string $filename): string
    {
        // Remove timestamp prefix
        $parts = explode('_', $filename);
        $nameParts = array_slice($parts, 4); // Skip date and time parts
        $name = implode('_', $nameParts);
        
        // Convert to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }
}
