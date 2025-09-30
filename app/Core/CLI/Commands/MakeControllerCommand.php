<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;

/**
 * Make Controller Command
 * Creates a new controller class
 */
class MakeControllerCommand extends Command
{
    protected string $name = 'make:controller';
    protected string $description = 'Create a new controller';

    public function __construct()
    {
        $this->addArgument('name', true, 'Name of the controller');
        $this->addOption('api', '', false, 'Create an API controller');
        $this->addOption('resource', 'r', false, 'Create a resource controller');
        $this->addOption('force', 'f', false, 'Overwrite existing file');
    }

    public function handle(): int
    {
        $name = $this->argument('arg1');
        
        if (!$name) {
            $this->error('Controller name is required');
            $this->showHelp();
            return 1;
        }

        // Clean up the name and ensure it ends with 'Controller'
        $name = $this->formatControllerName($name);
        
        // Determine controller type
        $isApi = $this->option('api');
        $isResource = $this->option('resource');
        
        if ($isApi) {
            return $this->createApiController($name);
        } elseif ($isResource) {
            return $this->createResourceController($name);
        } else {
            return $this->createBasicController($name);
        }
    }

    /**
     * Create basic controller
     */
    private function createBasicController(string $name): int
    {
        $path = "app/Controllers/{$name}.php";
        
        if ($this->fileExists($path) && !$this->option('force')) {
            $this->error("Controller {$name} already exists!");
            $this->info("Use --force to overwrite");
            return 1;
        }

        $content = $this->getStub('controller', [
            'ControllerName' => $name,
            'namespace' => 'Hasphp\\App\\Controllers',
        ]);

        return $this->writeFile($path, $content) ? 0 : 1;
    }

    /**
     * Create API controller
     */
    private function createApiController(string $name): int
    {
        $path = "app/Controllers/Api/{$name}.php";
        
        if ($this->fileExists($path) && !$this->option('force')) {
            $this->error("API Controller {$name} already exists!");
            $this->info("Use --force to overwrite");
            return 1;
        }

        $content = $this->getStub('api-controller', [
            'ControllerName' => $name,
            'namespace' => 'Hasphp\\App\\Controllers\\Api',
        ]);

        return $this->writeFile($path, $content) ? 0 : 1;
    }

    /**
     * Create resource controller
     */
    private function createResourceController(string $name): int
    {
        $path = "app/Controllers/{$name}.php";
        
        if ($this->fileExists($path) && !$this->option('force')) {
            $this->error("Resource Controller {$name} already exists!");
            $this->info("Use --force to overwrite");
            return 1;
        }

        $content = $this->getStub('resource-controller', [
            'ControllerName' => $name,
            'namespace' => 'Hasphp\\App\\Controllers',
            'modelName' => str_replace('Controller', '', $name),
            'modelVariable' => strtolower(str_replace('Controller', '', $name)),
        ]);

        return $this->writeFile($path, $content) ? 0 : 1;
    }

    /**
     * Format controller name
     */
    private function formatControllerName(string $name): string
    {
        $name = str_replace(['/', '\\'], '', $name);
        $name = ucfirst($name);
        
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }
        
        return $name;
    }
}
