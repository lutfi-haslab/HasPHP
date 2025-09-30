<?php

namespace Hasphp\App\Core;

use Hasphp\App\Providers\ServiceProvider;

class Application extends Container
{
    /**
     * The HasPHP framework version.
     */
    const VERSION = '2.0.0';
    
    /**
     * The base path for the HasPHP installation.
     */
    protected string $basePath;
    
    /**
     * Indicates if the application has been bootstrapped before.
     */
    protected bool $hasBeenBootstrapped = false;
    
    /**
     * Indicates if the application has "booted".
     */
    protected bool $booted = false;
    
    /**
     * The array of booting callbacks.
     */
    protected array $bootingCallbacks = [];
    
    /**
     * The array of booted callbacks.
     */
    protected array $bootedCallbacks = [];
    
    /**
     * The array of terminating callbacks.
     */
    protected array $terminatingCallbacks = [];
    
    /**
     * All of the registered service providers.
     */
    protected array $serviceProviders = [];
    
    /**
     * The names of the loaded service providers.
     */
    protected array $loadedProviders = [];
    
    /**
     * The deferred services and their providers.
     */
    protected array $deferredServices = [];
    
    /**
     * A custom callback used to configure Monolog.
     */
    protected ?\Closure $monologConfigurator = null;
    
    /**
     * The environment file to load during bootstrapping.
     */
    protected ?string $environmentFile = null;
    
    /**
     * Indicates if the application is running in the console.
     */
    protected ?bool $isRunningInConsole = null;
    
    /**
     * Create a new HasPHP application instance.
     */
    public function __construct(?string $basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }
        
        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();
    }
    
    /**
     * Get the version number of the application.
     */
    public function version(): string
    {
        return static::VERSION;
    }
    
    /**
     * Register the basic bindings into the container.
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);
        
        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->singleton('container', Container::class);
    }
    
    /**
     * Register all of the base service providers.
     */
    protected function registerBaseServiceProviders(): void
    {
        // Register core service providers here as they're created
    }
    
    /**
     * Register the core container aliases.
     */
    protected function registerCoreContainerAliases(): void
    {
        foreach ([
            'app' => [self::class, Container::class],
            'container' => [Container::class],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }
    
    /**
     * Set the base path for the application.
     */
    public function setBasePath(string $basePath): self
    {
        $this->basePath = rtrim($basePath, '\/');
        
        $this->bindPathsInContainer();
        
        return $this;
    }
    
    /**
     * Bind all of the application paths in the container.
     */
    protected function bindPathsInContainer(): void
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.config', $this->configPath());
        $this->instance('path.public', $this->publicPath());
        $this->instance('path.storage', $this->storagePath());
        $this->instance('path.database', $this->databasePath());
        $this->instance('path.resources', $this->resourcePath());
        $this->instance('path.bootstrap', $this->bootstrapPath());
    }
    
    /**
     * Get the path to the application "app" directory.
     */
    public function path(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'app' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
    
    /**
     * Get the base path of the HasPHP installation.
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
    
    /**
     * Get the path to the bootstrap directory.
     */
    public function bootstrapPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'bootstrap' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
    
    /**
     * Get the path to the application configuration files.
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
    
    /**
     * Get the path to the database directory.
     */
    public function databasePath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'database' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
    
    /**
     * Get the path to the public / web directory.
     */
    public function publicPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'public' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
    
    /**
     * Get the path to the resources directory.
     */
    public function resourcePath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'resources' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
    
    /**
     * Get the path to the storage directory.
     */
    public function storagePath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
    
    /**
     * Register a service provider with the application.
     */
    public function register($provider, bool $force = false): ServiceProvider
    {
        if (($registered = $this->getProvider($provider)) && ! $force) {
            return $registered;
        }
        
        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }
        
        $provider->register();
        
        // If there are bindings / singletons set as properties on the provider we
        // will spin through them and register them with the application, which
        // serves as a convenience layer while registering a lot of bindings.
        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }
        
        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $this->singleton($key, $value);
            }
        }
        
        $this->markAsRegistered($provider);
        
        // If the application has already booted, we will call this boot method on
        // the provider class so it has an opportunity to do its boot logic and
        // will be ready for any usage by this developer's application logic.
        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }
        
        return $provider;
    }
    
    /**
     * Get the registered service provider instance if it exists.
     */
    public function getProvider($provider): ?ServiceProvider
    {
        return array_values($this->getProviders($provider))[0] ?? null;
    }
    
    /**
     * Get the registered service provider instances if any exist.
     */
    public function getProviders($provider): array
    {
        $name = is_string($provider) ? $provider : get_class($provider);
        
        return array_filter($this->serviceProviders, function ($value) use ($name) {
            return $value instanceof $name;
        });
    }
    
    /**
     * Resolve a service provider instance from the class name.
     */
    public function resolveProvider(string $provider): ServiceProvider
    {
        return new $provider($this);
    }
    
    /**
     * Mark the given provider as registered.
     */
    protected function markAsRegistered(ServiceProvider $provider): void
    {
        $this->serviceProviders[] = $provider;
        
        $this->loadedProviders[get_class($provider)] = true;
    }
    
    /**
     * Boot the application's service providers.
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }
        
        // Once the application has booted we will also fire some "booted" callbacks
        // for any listeners that need to do work after this initial booting gets
        // finished. This is useful when ordering the boot-up processes properly.
        $this->fireAppCallbacks($this->bootingCallbacks);
        
        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });
        
        $this->booted = true;
        
        $this->fireAppCallbacks($this->bootedCallbacks);
    }
    
    /**
     * Boot the given service provider.
     */
    protected function bootProvider(ServiceProvider $provider): void
    {
        $provider->callBootingCallbacks();
        
        if (method_exists($provider, 'boot')) {
            $this->resolve([$provider, 'boot']);
        }
        
        $provider->callBootedCallbacks();
    }
    
    /**
     * Register a new boot listener.
     */
    public function booting(callable $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }
    
    /**
     * Register a new "booted" listener.
     */
    public function booted(callable $callback): void
    {
        $this->bootedCallbacks[] = $callback;
        
        if ($this->isBooted()) {
            $callback($this);
        }
    }
    
    /**
     * Call the booting callbacks for the application.
     */
    protected function fireAppCallbacks(array &$callbacks): void
    {
        $index = 0;
        
        while ($index < count($callbacks)) {
            $callbacks[$index]($this);
            $index++;
        }
    }
    
    /**
     * Determine if the application has booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }
    
    /**
     * Get the service providers that have been loaded.
     */
    public function getLoadedProviders(): array
    {
        return $this->loadedProviders;
    }
    
    /**
     * Determine if the application is running in the console.
     */
    public function runningInConsole(): bool
    {
        if ($this->isRunningInConsole === null) {
            $this->isRunningInConsole = \Phar::running() || php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
        }
        
        return $this->isRunningInConsole;
    }
    
    /**
     * Determine if the application is running unit tests.
     */
    public function runningUnitTests(): bool
    {
        return $this->bound('env') && $this->resolve('env') === 'testing';
    }
    
    /**
     * Get the current application environment.
     */
    public function environment(...$environments): bool|string
    {
        if (count($environments) > 0) {
            $patterns = is_array($environments[0]) ? $environments[0] : $environments;
            
            return str_is($patterns, $this->resolve('env'));
        }
        
        return $this->resolve('env');
    }
    
    /**
     * Determine if application is in local environment.
     */
    public function isLocal(): bool
    {
        return $this->environment('local');
    }
    
    /**
     * Determine if application is in production environment.
     */
    public function isProduction(): bool
    {
        return $this->environment('production');
    }
    
    /**
     * Detect the application's current environment.
     */
    public function detectEnvironment(callable $callback): string
    {
        $args = $_SERVER['argv'] ?? null;
        
        return $this->resolve('env', $callback($args));
    }
    
    /**
     * Register a terminating callback with the application.
     */
    public function terminating(callable $callback): self
    {
        $this->terminatingCallbacks[] = $callback;
        
        return $this;
    }
    
    /**
     * Terminate the application.
     */
    public function terminate(): void
    {
        $index = 0;
        
        while ($index < count($this->terminatingCallbacks)) {
            $this->resolve($this->terminatingCallbacks[$index]);
            $index++;
        }
    }
}
