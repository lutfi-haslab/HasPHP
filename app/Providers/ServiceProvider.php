<?php

namespace Hasphp\App\Providers;

use Hasphp\App\Core\Container;

abstract class ServiceProvider
{
    /**
     * The container instance.
     */
    protected Container $container;
    
    /**
     * All of the registered booted callbacks.
     */
    protected array $bootedCallbacks = [];
    
    /**
     * All of the registered booting callbacks.
     */
    protected array $bootingCallbacks = [];
    
    /**
     * Create a new service provider instance.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
    
    /**
     * Register any application services.
     */
    abstract public function register(): void;
    
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
    
    /**
     * Register a booting callback to be run before the "boot" method is called.
     */
    public function booting(callable $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }
    
    /**
     * Register a booted callback to be run after the "boot" method is called.
     */
    public function booted(callable $callback): void
    {
        $this->bootedCallbacks[] = $callback;
    }
    
    /**
     * Call the registered booting callbacks.
     */
    public function callBootingCallbacks(): void
    {
        $index = 0;
        
        while ($index < count($this->bootingCallbacks)) {
            $this->container->resolve($this->bootingCallbacks[$index]);
            $index++;
        }
    }
    
    /**
     * Call the registered booted callbacks.
     */
    public function callBootedCallbacks(): void
    {
        $index = 0;
        
        while ($index < count($this->bootedCallbacks)) {
            $this->container->resolve($this->bootedCallbacks[$index]);
            $index++;
        }
    }
    
    /**
     * Determine if the provider is deferred.
     */
    public function isDeferred(): bool
    {
        return false;
    }
    
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
