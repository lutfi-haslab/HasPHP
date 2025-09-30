<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;

/**
 * Make Migration Command
 * Creates a new database migration
 */
class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';
    protected string $description = 'Create a new migration';

    public function __construct()
    {
        $this->addArgument('name', true, 'Name of the migration');
        $this->addOption('create', '', true, 'Create a new table');
        $this->addOption('table', '', true, 'Modify an existing table');
        $this->addOption('force', 'f', false, 'Overwrite existing file');
    }

    public function handle(): int
    {
        $name = $this->argument('arg1');
        
        if (!$name) {
            $this->error('Migration name is required');
            $this->showHelp();
            return 1;
        }

        // Clean up the name
        $name = $this->formatMigrationName($name);
        
        // Generate timestamp
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$name}.php";
        $path = "database/migrations/{$fileName}";
        
        if ($this->fileExists($path) && !$this->option('force')) {
            $this->error("Migration {$name} already exists!");
            $this->info("Use --force to overwrite");
            return 1;
        }

        // Determine migration type
        $createTable = $this->option('create');
        $modifyTable = $this->option('table');
        
        if ($createTable) {
            $content = $this->createTableMigration($name, $createTable);
        } elseif ($modifyTable) {
            $content = $this->modifyTableMigration($name, $modifyTable);
        } else {
            $content = $this->createBasicMigration($name);
        }

        if ($this->writeFile($path, $content)) {
            $this->info("Migration created: {$fileName}");
            return 0;
        }

        return 1;
    }

    /**
     * Create table migration
     */
    private function createTableMigration(string $name, string $tableName): string
    {
        $className = $this->getMigrationClassName($name);
        
        return $this->getStub('create-migration', [
            'ClassName' => $className,
            'tableName' => $tableName,
        ]);
    }

    /**
     * Modify table migration
     */
    private function modifyTableMigration(string $name, string $tableName): string
    {
        $className = $this->getMigrationClassName($name);
        
        return $this->getStub('modify-migration', [
            'ClassName' => $className,
            'tableName' => $tableName,
        ]);
    }

    /**
     * Create basic migration
     */
    private function createBasicMigration(string $name): string
    {
        $className = $this->getMigrationClassName($name);
        
        return $this->getStub('basic-migration', [
            'ClassName' => $className,
        ]);
    }

    /**
     * Format migration name
     */
    private function formatMigrationName(string $name): string
    {
        return strtolower(str_replace([' ', '-'], '_', $name));
    }

    /**
     * Get migration class name
     */
    private function getMigrationClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }
}
