<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;

/**
 * Make Model Command
 * Creates a new model class
 */
class MakeModelCommand extends Command
{
    protected string $name = 'make:model';
    protected string $description = 'Create a new model';

    public function __construct()
    {
        $this->addArgument('name', true, 'Name of the model');
        $this->addOption('migration', 'm', false, 'Also create a migration');
        $this->addOption('controller', 'c', false, 'Also create a controller');
        $this->addOption('resource', 'r', false, 'Also create a resource controller');
        $this->addOption('api', 'a', false, 'Also create an API controller');
        $this->addOption('force', 'f', false, 'Overwrite existing file');
    }

    public function handle(): int
    {
        $name = $this->argument('arg1');
        
        if (!$name) {
            $this->error('Model name is required');
            $this->showHelp();
            return 1;
        }

        // Clean up the name
        $name = $this->formatModelName($name);
        
        // Create the model
        $result = $this->createModel($name);
        
        if ($result !== 0) {
            return $result;
        }

        // Create additional resources if requested
        if ($this->option('migration')) {
            $this->createMigration($name);
        }

        if ($this->option('controller')) {
            $this->createController($name, false, false);
        } elseif ($this->option('resource')) {
            $this->createController($name, true, false);
        } elseif ($this->option('api')) {
            $this->createController($name, false, true);
        }

        return 0;
    }

    /**
     * Create model
     */
    private function createModel(string $name): int
    {
        $path = "app/Models/{$name}.php";
        
        if ($this->fileExists($path) && !$this->option('force')) {
            $this->error("Model {$name} already exists!");
            $this->info("Use --force to overwrite");
            return 1;
        }

        $content = $this->getStub('model', [
            'ModelName' => $name,
            'namespace' => 'Hasphp\\App\\Models',
            'tableName' => $this->getTableName($name),
        ]);

        return $this->writeFile($path, $content) ? 0 : 1;
    }

    /**
     * Create migration
     */
    private function createMigration(string $modelName): void
    {
        $tableName = $this->getTableName($modelName);
        $migrationName = "create_{$tableName}_table";
        
        $migrationCommand = new MakeMigrationCommand();
        // Simulate command arguments
        $_SERVER['argv'] = ['artisan', 'make:migration', $migrationName, '--create=' . $tableName];
        $migrationCommand->handle();
    }

    /**
     * Create controller
     */
    private function createController(string $modelName, bool $resource = false, bool $api = false): void
    {
        $controllerName = $modelName . 'Controller';
        
        $this->info("Creating controller: {$controllerName}");
        
        $controllerCommand = new MakeControllerCommand();
        
        if ($api) {
            $path = "app/Controllers/Api/{$controllerName}.php";
            $content = $this->getStub('api-controller', [
                'ControllerName' => $controllerName,
                'namespace' => 'Hasphp\\App\\Controllers\\Api',
            ]);
        } elseif ($resource) {
            $path = "app/Controllers/{$controllerName}.php";
            $content = $this->getStub('resource-controller', [
                'ControllerName' => $controllerName,
                'namespace' => 'Hasphp\\App\\Controllers',
                'modelName' => $modelName,
                'modelVariable' => strtolower($modelName),
            ]);
        } else {
            $path = "app/Controllers/{$controllerName}.php";
            $content = $this->getStub('controller', [
                'ControllerName' => $controllerName,
                'namespace' => 'Hasphp\\App\\Controllers',
            ]);
        }

        $this->writeFile($path, $content);
    }

    /**
     * Format model name
     */
    private function formatModelName(string $name): string
    {
        return ucfirst(str_replace(['/', '\\'], '', $name));
    }

    /**
     * Get table name from model name
     */
    private function getTableName(string $modelName): string
    {
        // Convert PascalCase to snake_case and pluralize
        $tableName = strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($modelName)));
        
        // Simple pluralization
        if (str_ends_with($tableName, 'y')) {
            $tableName = substr($tableName, 0, -1) . 'ies';
        } elseif (str_ends_with($tableName, 's')) {
            $tableName = $tableName . 'es';
        } else {
            $tableName = $tableName . 's';
        }
        
        return $tableName;
    }
}
