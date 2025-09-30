<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;
use Hasphp\App\Core\CLI\CLI;

/**
 * List Command
 * Show all available commands
 */
class ListCommand extends Command
{
    protected string $name = 'list';
    protected string $description = 'List all available commands';

    public function handle(): int
    {
        $this->info("HasPHP Framework Commands");
        $this->line("========================");
        $this->line();
        
        $commands = CLI::getCommands();
        
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
        
        foreach ($categories as $category => $categoryCommands) {
            $this->success($category . ':');
            foreach ($categoryCommands as $command => $description) {
                if (isset($commands[$command])) {
                    $this->line("  {$command}" . str_repeat(' ', 20 - strlen($command)) . $description);
                }
            }
            $this->line();
        }
        
        $this->info("Usage:");
        $this->line("  php artisan <command> [options] [arguments]");
        $this->line();
        $this->info("For help with a specific command:");
        $this->line("  php artisan <command> --help");
        
        return 0;
    }
}
