<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;
use Hasphp\App\Core\CLI\CLI;

/**
 * Help Command
 * Show help information
 */
class HelpCommand extends Command
{
    protected string $name = 'help';
    protected string $description = 'Show help information';

    public function __construct()
    {
        $this->addArgument('command', false, 'Command to get help for');
    }

    public function handle(): int
    {
        $command = $this->argument('arg1');
        
        if ($command) {
            return $this->showCommandHelp($command);
        }
        
        return $this->showGeneralHelp();
    }

    /**
     * Show help for specific command
     */
    private function showCommandHelp(string $commandName): int
    {
        $commands = CLI::getCommands();
        
        if (!isset($commands[$commandName])) {
            $this->error("Command '{$commandName}' not found.");
            return 1;
        }
        
        try {
            $commandClass = $commands[$commandName];
            $commandInstance = new $commandClass();
            $commandInstance->showHelp();
            return 0;
        } catch (\Exception $e) {
            $this->error("Error showing help for {$commandName}: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show general help
     */
    private function showGeneralHelp(): int
    {
        $this->info("HasPHP Framework - CLI Tool");
        $this->line("===========================");
        $this->line();
        
        $this->info("Usage:");
        $this->line("  php artisan <command> [options] [arguments]");
        $this->line();
        
        $this->info("Available Commands:");
        
        // Group commands by category
        $categories = [
            'Generation' => [
                'make:controller' => 'Create a new controller',
                'make:model' => 'Create a new model',
                'make:migration' => 'Create a new migration',
            ],
            'Database' => [
                'migrate' => 'Run database migrations',
                'migrate:rollback' => 'Rollback database migrations',
                'migrate:status' => 'Show migration status',
            ],
            'Server' => [
                'serve' => 'Start development server',
                'server:start' => 'Start production server',
                'server:stop' => 'Stop all servers',
                'server:status' => 'Show server status',
            ],
            'General' => [
                'list' => 'List all available commands',
                'help' => 'Show help information',
            ]
        ];
        
        $commands = CLI::getCommands();
        
        foreach ($categories as $category => $categoryCommands) {
            $this->line();
            $this->success($category . ':');
            foreach ($categoryCommands as $command => $description) {
                if (isset($commands[$command])) {
                    $this->line("  {$command}" . str_repeat(' ', 20 - strlen($command)) . $description);
                }
            }
        }
        
        $this->line();
        $this->info("Global Options:");
        $this->line("  -h, --help         Show help for command");
        $this->line("  -v, --verbose      Show detailed output");
        $this->line();
        
        $this->info("Examples:");
        $this->line("  php artisan make:controller UserController");
        $this->line("  php artisan make:model User --migration");
        $this->line("  php artisan migrate");
        $this->line("  php artisan serve --port=9000");
        $this->line("  php artisan help make:controller");
        $this->line();
        
        $this->info("For help with a specific command:");
        $this->line("  php artisan help <command>");
        $this->line("  php artisan <command> --help");
        
        return 0;
    }
}
