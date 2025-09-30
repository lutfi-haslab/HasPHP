<?php

namespace Hasphp\App\Core\CLI\Commands;

use Hasphp\App\Core\CLI\Command;

/**
 * Server Stop Command
 * Stop running servers
 */
class ServerStopCommand extends Command
{
    protected string $name = 'server:stop';
    protected string $description = 'Stop all running servers';

    public function __construct()
    {
        $this->addOption('force', 'f', false, 'Force kill servers');
    }

    public function handle(): int
    {
        $this->info("Stopping HasPHP servers...");
        $this->line();
        
        $stopped = 0;
        $force = $this->option('force');
        
        // Stop OpenSwoole servers
        $stopped += $this->stopOpenSwooleServers($force);
        
        // Stop PHP built-in servers
        $stopped += $this->stopPhpServers($force);
        
        if ($stopped > 0) {
            $this->success("Stopped {$stopped} server(s)");
        } else {
            $this->info("No running servers found");
        }
        
        return 0;
    }

    /**
     * Stop OpenSwoole servers
     */
    private function stopOpenSwooleServers(bool $force = false): int
    {
        $stopped = 0;
        
        // Check for PID file
        $pidFile = 'storage/swoole.pid';
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                if ($this->stopProcess($pid, $force)) {
                    $this->success("Stopped OpenSwoole server (PID: {$pid})");
                    $stopped++;
                }
                unlink($pidFile);
            } else {
                // Clean up stale PID file
                unlink($pidFile);
            }
        }
        
        // Find running OpenSwoole processes
        $processes = $this->findProcesses('openswoole-server.php');
        foreach ($processes as $pid) {
            if ($this->stopProcess($pid, $force)) {
                $this->success("Stopped OpenSwoole process (PID: {$pid})");
                $stopped++;
            }
        }
        
        return $stopped;
    }

    /**
     * Stop PHP built-in servers
     */
    private function stopPhpServers(bool $force = false): int
    {
        $stopped = 0;
        
        // Find PHP built-in server processes
        $processes = $this->findProcesses('php -S.*server.php');
        foreach ($processes as $pid) {
            if ($this->stopProcess($pid, $force)) {
                $this->success("Stopped PHP server (PID: {$pid})");
                $stopped++;
            }
        }
        
        return $stopped;
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
     * Stop a process
     */
    private function stopProcess(string $pid, bool $force = false): bool
    {
        if (!$this->isProcessRunning($pid)) {
            return false;
        }
        
        // Try graceful shutdown first
        $signal = $force ? 'KILL' : 'TERM';
        $command = "kill -{$signal} {$pid} 2>/dev/null";
        exec($command, $output, $exitCode);
        
        if ($exitCode === 0) {
            // Wait a moment for graceful shutdown
            if (!$force) {
                sleep(2);
                
                // If still running, force kill
                if ($this->isProcessRunning($pid)) {
                    $command = "kill -KILL {$pid} 2>/dev/null";
                    exec($command);
                }
            }
            return true;
        }
        
        return false;
    }
}
