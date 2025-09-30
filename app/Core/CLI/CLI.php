<?php

namespace Hasphp\App\Core\CLI;

/**
 * Core CLI class for handling command-line operations
 */
class CLI
{
    private static array $arguments = [];
    private static array $options = [];
    private static array $commands = [];

    // Colors for output
    const COLOR_RED = "\033[31m";
    const COLOR_GREEN = "\033[32m";
    const COLOR_YELLOW = "\033[33m";
    const COLOR_BLUE = "\033[34m";
    const COLOR_MAGENTA = "\033[35m";
    const COLOR_CYAN = "\033[36m";
    const COLOR_WHITE = "\033[37m";
    const COLOR_RESET = "\033[0m";

    /**
     * Initialize CLI with arguments
     */
    public static function init(array $argv): void
    {
        self::parseArguments($argv);
        self::registerCommands();
    }

    /**
     * Parse command line arguments
     */
    private static function parseArguments(array $argv): void
    {
        $script = array_shift($argv); // Remove script name
        
        $currentArg = 0;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                // Long option
                $option = substr($arg, 2);
                if (str_contains($option, '=')) {
                    [$name, $value] = explode('=', $option, 2);
                    self::$options[$name] = $value;
                } else {
                    self::$options[$option] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                // Short option
                $option = substr($arg, 1);
                self::$options[$option] = true;
            } else {
                // Positional argument
                if ($currentArg === 0) {
                    self::$arguments['command'] = $arg;
                } else {
                    self::$arguments["arg{$currentArg}"] = $arg;
                }
                $currentArg++;
            }
        }
    }

    /**
     * Register all available commands
     */
    private static function registerCommands(): void
    {
        self::$commands = [
            'make:controller' => \Hasphp\App\Core\CLI\Commands\MakeControllerCommand::class,
            'make:model' => \Hasphp\App\Core\CLI\Commands\MakeModelCommand::class,
            'make:migration' => \Hasphp\App\Core\CLI\Commands\MakeMigrationCommand::class,
            'migrate' => \Hasphp\App\Core\CLI\Commands\MigrateCommand::class,
            'migrate:rollback' => \Hasphp\App\Core\CLI\Commands\MigrateRollbackCommand::class,
            'migrate:status' => \Hasphp\App\Core\CLI\Commands\MigrateStatusCommand::class,
            'serve' => \Hasphp\App\Core\CLI\Commands\ServeCommand::class,
            'server:start' => \Hasphp\App\Core\CLI\Commands\ServerStartCommand::class,
            'server:stop' => \Hasphp\App\Core\CLI\Commands\ServerStopCommand::class,
            'server:status' => \Hasphp\App\Core\CLI\Commands\ServerStatusCommand::class,
            'list' => \Hasphp\App\Core\CLI\Commands\ListCommand::class,
            'help' => \Hasphp\App\Core\CLI\Commands\HelpCommand::class,
        ];
    }

    /**
     * Run CLI application
     */
    public static function run(): int
    {
        $command = self::argument('command');

        if (!$command) {
            self::showLogo();
            self::showHelp();
            return 0;
        }

        if (!isset(self::$commands[$command])) {
            self::error("Command '{$command}' not found.");
            self::info("Run 'php artisan list' to see available commands.");
            return 1;
        }

        try {
            $commandClass = self::$commands[$command];
            $commandInstance = new $commandClass();
            
            if (self::option('help') || self::option('h')) {
                $commandInstance->showHelp();
                return 0;
            }

            return $commandInstance->handle();
        } catch (\Exception $e) {
            self::error("Error executing command: " . $e->getMessage());
            if (self::option('verbose') || self::option('v')) {
                self::line($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Get argument value
     */
    public static function argument(string $name): ?string
    {
        return self::$arguments[$name] ?? null;
    }

    /**
     * Get option value
     */
    public static function option(string $name): mixed
    {
        return self::$options[$name] ?? null;
    }

    /**
     * Get all registered commands
     */
    public static function getCommands(): array
    {
        return self::$commands;
    }

    /**
     * Output colored text
     */
    private static function colorize(string $text, string $color): string
    {
        return $color . $text . self::COLOR_RESET;
    }

    /**
     * Output success message
     */
    public static function success(string $message): void
    {
        echo self::colorize("✅ " . $message, self::COLOR_GREEN) . PHP_EOL;
    }

    /**
     * Output error message
     */
    public static function error(string $message): void
    {
        echo self::colorize("❌ " . $message, self::COLOR_RED) . PHP_EOL;
    }

    /**
     * Output info message
     */
    public static function info(string $message): void
    {
        echo self::colorize("ℹ️  " . $message, self::COLOR_BLUE) . PHP_EOL;
    }

    /**
     * Output warning message
     */
    public static function warning(string $message): void
    {
        echo self::colorize("⚠️  " . $message, self::COLOR_YELLOW) . PHP_EOL;
    }

    /**
     * Output plain text
     */
    public static function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Ask user for input
     */
    public static function ask(string $question, string $default = ''): string
    {
        $prompt = self::colorize($question, self::COLOR_CYAN);
        if ($default) {
            $prompt .= self::colorize(" [{$default}]", self::COLOR_YELLOW);
        }
        $prompt .= ': ';
        
        echo $prompt;
        $input = trim(fgets(STDIN));
        
        return $input ?: $default;
    }

    /**
     * Ask user for confirmation
     */
    public static function confirm(string $question, bool $default = false): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        $prompt = self::colorize($question, self::COLOR_CYAN) . 
                  self::colorize(" [{$defaultText}]", self::COLOR_YELLOW) . ': ';
        
        echo $prompt;
        $input = strtolower(trim(fgets(STDIN)));
        
        if ($input === '') {
            return $default;
        }
        
        return in_array($input, ['y', 'yes', '1', 'true']);
    }

    /**
     * Show HasPHP logo
     */
    private static function showLogo(): void
    {
        $logo = self::colorize("
   _   _           ______ _   _ _____  
  | | | |          | ___ \ | | |  _  | 
  | |_| | __ _ ___ | |_/ / |_| | | | | 
  |  _  |/ _` / __||  __/|  _  | | | | 
  | | | | (_| \__ \| |   | | | \ \_/ / 
  \_| |_/\__,_|___/\_|   \_| |_/\___/  
                                      
  HasPHP Framework - CLI Tool
", self::COLOR_CYAN);
        
        echo $logo . PHP_EOL;
    }

    /**
     * Show general help
     */
    private static function showHelp(): void
    {
        self::info("Usage:");
        self::line("  php artisan <command> [options] [arguments]");
        self::line();
        
        self::info("Available Commands:");
        self::line("  make:controller    Create a new controller");
        self::line("  make:model         Create a new model");
        self::line("  make:migration     Create a new migration");
        self::line("  migrate            Run database migrations");
        self::line("  migrate:rollback   Rollback database migrations");
        self::line("  migrate:status     Show migration status");
        self::line("  serve              Start development server");
        self::line("  server:start       Start production server");
        self::line("  server:stop        Stop all servers");
        self::line("  server:status      Show server status");
        self::line("  list               List all available commands");
        self::line("  help               Show help information");
        self::line();
        
        self::info("Global Options:");
        self::line("  -h, --help         Show help for command");
        self::line("  -v, --verbose      Show detailed output");
        self::line();
        
        self::info("Examples:");
        self::line("  php artisan make:controller UserController");
        self::line("  php artisan make:model User");
        self::line("  php artisan make:migration create_users_table");
        self::line("  php artisan migrate");
        self::line("  php artisan serve --port=9000");
    }
}
