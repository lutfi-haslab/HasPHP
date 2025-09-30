<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;

/**
 * Serve Command
 * Start development server
 */
class ServeCommand extends Command
{
    protected string $name = 'serve';
    protected string $description = 'Start development server';

    public function __construct()
    {
        $this->addOption('port', 'p', true, 'Port to serve on (default: 8080)');
        $this->addOption('host', '', true, 'Host to serve on (default: 127.0.0.1)');
        $this->addOption('openswoole', '', false, 'Force OpenSwoole server');
        $this->addOption('fallback', '', false, 'Force PHP built-in server');
    }

    public function handle(): int
    {
        $port = $this->option('port') ?? '8080';
        $host = $this->option('host') ?? '127.0.0.1';
        
        $this->info("Starting HasPHP development server...");
        $this->line();
        
        // Check if specific server is requested
        if ($this->option('openswoole')) {
            return $this->startOpenSwooleServer($port, $host);
        }
        
        if ($this->option('fallback')) {
            return $this->startFallbackServer($port, $host);
        }
        
        // Auto-detect best server
        return $this->startBestServer($port, $host);
    }

    /**
     * Start OpenSwoole server
     */
    private function startOpenSwooleServer(string $port, string $host): int
    {
        if (!extension_loaded('openswoole')) {
            $this->error("OpenSwoole extension not found!");
            $this->info("Install OpenSwoole first or use --fallback option");
            return 1;
        }
        
        $this->success("Starting OpenSwoole server...");
        $this->info("Server: http://{$host}:{$port}");
        $this->info("Press Ctrl+C to stop");
        $this->line();
        
        // Check if port is available
        if ($this->isPortInUse($port)) {
            $this->killPortProcess($port);
        }
        
        // Start OpenSwoole server
        $command = "php openswoole-server.php --port={$port} --host={$host}";
        passthru($command, $exitCode);
        
        return $exitCode;
    }

    /**
     * Start fallback server
     */
    private function startFallbackServer(string $port, string $host): int
    {
        $this->info("Starting PHP built-in server...");
        $this->info("Server: http://{$host}:{$port}");
        $this->info("Press Ctrl+C to stop");
        $this->line();
        
        // Check if port is available
        if ($this->isPortInUse($port)) {
            $this->killPortProcess($port);
        }
        
        // Start PHP built-in server
        $command = "php -S {$host}:{$port} server.php";
        passthru($command, $exitCode);
        
        return $exitCode;
    }

    /**
     * Start best available server
     */
    private function startBestServer(string $port, string $host): int
    {
        if (extension_loaded('openswoole')) {
            $this->success("OpenSwoole detected - using high-performance server");
            return $this->startOpenSwooleServer($port, $host);
        } else {
            $this->warning("OpenSwoole not found - using PHP built-in server");
            $this->info("For better performance, install OpenSwoole extension");
            return $this->startFallbackServer($port, $host);
        }
    }

    /**
     * Check if port is in use
     */
    private function isPortInUse(string $port): bool
    {
        $command = "lsof -Pi :{$port} -sTCP:LISTEN -t";
        $output = shell_exec($command);
        return !empty(trim($output));
    }

    /**
     * Kill process using port
     */
    private function killPortProcess(string $port): void
    {
        $this->warning("Port {$port} is in use. Attempting to free it...");
        
        $command = "lsof -Pi :{$port} -sTCP:LISTEN -t";
        $pid = trim(shell_exec($command));
        
        if ($pid) {
            if ($this->confirm("Kill process {$pid}?", true)) {
                shell_exec("kill -9 {$pid}");
                $this->success("Process {$pid} killed");
                sleep(1);
            } else {
                $this->error("Cannot start server while port is in use");
                exit(1);
            }
        }
    }
}
