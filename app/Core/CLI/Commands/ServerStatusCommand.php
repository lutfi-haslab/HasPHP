<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;

/**
 * Server Status Command
 * Show server status
 */
class ServerStatusCommand extends Command
{
    protected string $name = 'server:status';
    protected string $description = 'Show server status';

    public function handle(): int
    {
        $this->info("HasPHP Server Status");
        $this->line("===================");
        $this->line();
        
        // Check OpenSwoole extension
        $this->checkOpenSwooleExtension();
        $this->line();
        
        // Check running processes
        $this->checkRunningProcesses();
        $this->line();
        
        // Check port status
        $this->checkPortStatus();
        
        return 0;
    }

    /**
     * Check OpenSwoole extension status
     */
    private function checkOpenSwooleExtension(): void
    {
        $this->info("Extension Status:");
        
        if (extension_loaded('openswoole')) {
            $this->success("✅ OpenSwoole: Available");
            
            // Get OpenSwoole version if available
            if (function_exists('swoole_version')) {
                $version = swoole_version();
                $this->line("   Version: {$version}");
            }
        } else {
            $this->warning("⚠️  OpenSwoole: Not installed");
            $this->line("   Install with: php artisan server:install");
        }
    }

    /**
     * Check running processes
     */
    private function checkRunningProcesses(): void
    {
        $this->info("Running Processes:");
        
        // Check OpenSwoole servers
        $swooleProcesses = $this->findProcesses('openswoole-server.php');
        if (!empty($swooleProcesses)) {
            $this->success("✅ OpenSwoole servers: " . count($swooleProcesses) . " running");
            foreach ($swooleProcesses as $pid) {
                $this->line("   PID: {$pid}");
            }
        } else {
            $this->line("❌ OpenSwoole servers: Not running");
        }
        
        // Check PHP built-in servers
        $phpProcesses = $this->findProcesses('php -S.*server.php');
        if (!empty($phpProcesses)) {
            $this->success("✅ PHP built-in servers: " . count($phpProcesses) . " running");
            foreach ($phpProcesses as $pid) {
                $this->line("   PID: {$pid}");
            }
        } else {
            $this->line("❌ PHP built-in servers: Not running");
        }
        
        // Check for PID file
        $pidFile = 'storage/swoole.pid';
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($this->isProcessRunning($pid)) {
                $this->info("📄 PID file: {$pidFile} (PID: {$pid})");
            } else {
                $this->warning("📄 Stale PID file: {$pidFile}");
            }
        }
    }

    /**
     * Check port status
     */
    private function checkPortStatus(): void
    {
        $this->info("Port Status:");
        
        $commonPorts = [8080, 8000, 9000, 3000];
        
        foreach ($commonPorts as $port) {
            if ($this->isPortInUse($port)) {
                $this->success("✅ Port {$port}: In use");
                
                // Try to get process info
                $processInfo = $this->getPortProcessInfo($port);
                if ($processInfo) {
                    $this->line("   Process: {$processInfo}");
                }
            } else {
                $this->line("❌ Port {$port}: Available");
            }
        }
    }

    /**
     * Find processes by pattern
     */
    private function findProcesses(string $pattern): array
    {
        $command = "pgrep -f '{$pattern}'";
        $output = shell_exec($command);
        
        if (empty($output)) {
            return [];
        }
        
        return array_filter(array_map('trim', explode("\n", $output)));
    }

    /**
     * Check if process is running
     */
    private function isProcessRunning(string $pid): bool
    {
        $command = "kill -0 {$pid} 2>/dev/null";
        exec($command, $output, $exitCode);
        return $exitCode === 0;
    }

    /**
     * Check if port is in use
     */
    private function isPortInUse(int $port): bool
    {
        $command = "lsof -Pi :{$port} -sTCP:LISTEN -t";
        $output = shell_exec($command);
        return !empty(trim($output));
    }

    /**
     * Get process info for port
     */
    private function getPortProcessInfo(int $port): ?string
    {
        $command = "lsof -Pi :{$port} -sTCP:LISTEN";
        $output = shell_exec($command);
        
        if (empty($output)) {
            return null;
        }
        
        $lines = explode("\n", $output);
        if (count($lines) < 2) {
            return null;
        }
        
        // Parse the second line (first data line)
        $parts = preg_split('/\s+/', $lines[1]);
        if (count($parts) >= 2) {
            return "{$parts[0]} (PID: {$parts[1]})";
        }
        
        return null;
    }
}
