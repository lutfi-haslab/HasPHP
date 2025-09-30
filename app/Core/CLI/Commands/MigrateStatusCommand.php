<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;
use Hasphp\App\Core\DB\DatabaseManager;

/**
 * Migrate Status Command
 * Show migration status
 */
class MigrateStatusCommand extends Command
{
    protected string $name = 'migrate:status';
    protected string $description = 'Show migration status';

    private DatabaseManager $db;

    public function handle(): int
    {
        try {
            // Initialize database
            $this->initializeDatabase();
            
            // Create migrations table if it doesn't exist
            $this->createMigrationsTable();
            
            // Get all migration files
            $migrationFiles = glob('database/migrations/*.php');
            sort($migrationFiles);
            
            // Get executed migrations
            $executed = $this->db->query("SELECT migration, batch, executed_at FROM migrations ORDER BY id")->fetchAll();
            $executedMigrations = array_column($executed, 'migration');
            
            if (empty($migrationFiles)) {
                $this->info("No migrations found.");
                return 0;
            }
            
            $this->info("Migration Status");
            $this->line("===============");
            $this->line();
            
            // Show header
            $this->line(sprintf("%-8s %-20s %-40s %-20s", "Status", "Batch", "Migration", "Executed At"));
            $this->line(str_repeat("-", 90));
            
            foreach ($migrationFiles as $file) {
                $migrationName = basename($file, '.php');
                
                if (in_array($migrationName, $executedMigrations)) {
                    // Find execution details
                    $details = array_filter($executed, function($row) use ($migrationName) {
                        return $row['migration'] === $migrationName;
                    });
                    $details = reset($details);
                    
                    $status = "✅ Ran";
                    $batch = $details['batch'] ?? 'N/A';
                    $executedAt = $details['executed_at'] ?? 'N/A';
                } else {
                    $status = "❌ Pending";
                    $batch = "-";
                    $executedAt = "-";
                }
                
                $this->line(sprintf("%-8s %-20s %-40s %-20s", 
                    $status, 
                    $batch, 
                    $this->truncateString($migrationName, 40),
                    $executedAt
                ));
            }
            
            $this->line();
            
            // Show summary
            $totalMigrations = count($migrationFiles);
            $ranMigrations = count($executedMigrations);
            $pendingMigrations = $totalMigrations - $ranMigrations;
            
            $this->info("Summary:");
            $this->line("  Total migrations: {$totalMigrations}");
            $this->line("  Ran: {$ranMigrations}");
            $this->line("  Pending: {$pendingMigrations}");
            
            if ($pendingMigrations > 0) {
                $this->line();
                $this->warning("Run 'php artisan migrate' to execute pending migrations.");
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Failed to get migration status: " . $e->getMessage());
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
     * Create migrations table if it doesn't exist
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
     * Truncate string to specified length
     */
    private function truncateString(string $str, int $length): string
    {
        if (strlen($str) <= $length) {
            return $str;
        }
        
        return substr($str, 0, $length - 3) . '...';
    }
}
