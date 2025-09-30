<?php

namespace Hasphp\App\Core\CLI;

/**
 * Base CLI Command class
 * All CLI commands should extend this class
 */
abstract class Command
{
    protected string $name;
    protected string $description;
    protected array $arguments = [];
    protected array $options = [];

    /**
     * Execute the command
     */
    abstract public function handle(): int;

    /**
     * Get command name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get command description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Add argument definition
     */
    protected function addArgument(string $name, bool $required = false, string $description = ''): void
    {
        $this->arguments[$name] = [
            'required' => $required,
            'description' => $description
        ];
    }

    /**
     * Add option definition
     */
    protected function addOption(string $name, string $shortcut = '', bool $hasValue = false, string $description = ''): void
    {
        $this->options[$name] = [
            'shortcut' => $shortcut,
            'hasValue' => $hasValue,
            'description' => $description
        ];
    }

    /**
     * Get argument value
     */
    protected function argument(string $name): ?string
    {
        return CLI::argument($name);
    }

    /**
     * Get option value
     */
    protected function option(string $name): mixed
    {
        return CLI::option($name);
    }

    /**
     * Output success message
     */
    protected function success(string $message): void
    {
        CLI::success($message);
    }

    /**
     * Output error message
     */
    protected function error(string $message): void
    {
        CLI::error($message);
    }

    /**
     * Output info message
     */
    protected function info(string $message): void
    {
        CLI::info($message);
    }

    /**
     * Output warning message
     */
    protected function warning(string $message): void
    {
        CLI::warning($message);
    }

    /**
     * Output plain text
     */
    protected function line(string $message = ''): void
    {
        CLI::line($message);
    }

    /**
     * Ask user for input
     */
    protected function ask(string $question, string $default = ''): string
    {
        return CLI::ask($question, $default);
    }

    /**
     * Ask user for confirmation
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        return CLI::confirm($question, $default);
    }

    /**
     * Show command help
     */
    public function showHelp(): void
    {
        $this->info("Usage:");
        $this->line("  php artisan {$this->name}");
        
        if (!empty($this->arguments)) {
            $this->line();
            $this->info("Arguments:");
            foreach ($this->arguments as $name => $config) {
                $required = $config['required'] ? '<required>' : '<optional>';
                $this->line("  {$name} {$required} - {$config['description']}");
            }
        }

        if (!empty($this->options)) {
            $this->line();
            $this->info("Options:");
            foreach ($this->options as $name => $config) {
                $shortcut = $config['shortcut'] ? "-{$config['shortcut']}, " : "";
                $this->line("  {$shortcut}--{$name} - {$config['description']}");
            }
        }
    }

    /**
     * Create directory if it doesn't exist
     */
    protected function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            $this->info("Created directory: {$path}");
        }
    }

    /**
     * Write content to file
     */
    protected function writeFile(string $path, string $content): bool
    {
        $directory = dirname($path);
        $this->ensureDirectory($directory);

        if (file_put_contents($path, $content) !== false) {
            $this->success("Created: {$path}");
            return true;
        }

        $this->error("Failed to create: {$path}");
        return false;
    }

    /**
     * Check if file exists
     */
    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Get stub content and replace placeholders
     */
    protected function getStub(string $stubName, array $replacements = []): string
    {
        $stubPath = __DIR__ . "/stubs/{$stubName}.stub";
        
        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub file not found: {$stubPath}");
        }

        $content = file_get_contents($stubPath);
        
        foreach ($replacements as $placeholder => $replacement) {
            $content = str_replace("{{" . $placeholder . "}}", $replacement, $content);
        }

        return $content;
    }
}
