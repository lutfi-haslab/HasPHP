<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;

/**
 * Server Start Command
 * Start production server
 */
class ServerStartCommand extends Command
{
    protected string $name = 'server:start';
    protected string $description = 'Start production server';

    public function __construct()
    {
        $this->addOption('port', 'p', true, 'Port to serve on (default: 8080)');
        $this->addOption('host', '', true, 'Host to serve on (default: 0.0.0.0)');
        $this->addOption('daemon', 'd', false, 'Run as daemon');
        $this->addOption('workers', 'w', true, 'Number of worker processes');
        $this->addOption('force-swoole', '', false, 'Force OpenSwoole server');
        $this->addOption('fallback', '', false, 'Use PHP built-in server');
    }

    public function handle(): int
    {
        $port = $this->option('port') ?? '8080';
        $host = $this->option('host') ?? '0.0.0.0';
        $daemon = $this->option('daemon');
        $workers = $this->option('workers');
        
        $this->info("Starting HasPHP production server...");
        $this->line();
        
        // Check server type
        if ($this->option('fallback')) {
            return $this->startPhpServer($port, $host);
        }
        
        if ($this->option('force-swoole') || extension_loaded('openswoole')) {
            return $this->startOpenSwooleServer($port, $host, $daemon, $workers);
        }
        
        $this->warning("OpenSwoole not available, falling back to PHP server");
        return $this->startPhpServer($port, $host);
    }

    /**
     * Start OpenSwoole server
     */
    private function startOpenSwooleServer(string $port, string $host, bool $daemon = false, ?string $workers = null): int
    {
        if (!extension_loaded('openswoole')) {
            $this->error("OpenSwoole extension not found!");
            return 1;
        }
        
        $this->success("Starting OpenSwoole production server...");
        $this->info("Host: {$host}");
        $this->info("Port: {$port}");
        $this->info("Daemon: " . ($daemon ? 'Yes' : 'No'));
        if ($workers) {
            $this->info("Workers: {$workers}");
        }
        $this->line();
        
        // Build command
        $command = "php openswoole-server.php";
        
        if ($daemon) {
            $command .= " --daemon";
        }
        
        // Create storage directory
        $this->ensureDirectory('storage/logs');
        
        if ($daemon) {
            $this->info("Server started as daemon");
            $this->info("Logs: storage/logs/swoole.log");
            $this->info("PID file: storage/swoole.pid");
        } else {
            $this->info("Press Ctrl+C to stop server");
        }
        
        passthru($command, $exitCode);
        return $exitCode;
    }

    /**
     * Start PHP built-in server
     */
    private function startPhpServer(string $port, string $host): int
    {
        $this->info("Starting PHP built-in server...");
        $this->info("Host: {$host}");
        $this->info("Port: {$port}");
        $this->warning("Note: PHP built-in server is not suitable for production!");
        $this->info("Consider installing OpenSwoole for production use");
        $this->line();
        $this->info("Press Ctrl+C to stop server");
        
        $command = "php -S {$host}:{$port} server.php";
        passthru($command, $exitCode);
        return $exitCode;
    }
}
