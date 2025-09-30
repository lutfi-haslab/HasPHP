<?php

namespace Hasphp\App\Core;

use Closure;
use ReflectionClass;
use ReflectionParameter;
use InvalidArgumentException;
use ReflectionException;

class Container
{
    private static ?Container $instance = null;
    
    /**
     * The container's bindings.
     */
    protected array $bindings = [];
    
    /**
     * The container's shared instances.
     */
    protected array $instances = [];
    
    /**
     * The container's aliases.
     */
    protected array $aliases = [];
    
    /**
     * Get the globally available instance of the container.
     */
    public static function getInstance(): Container
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }
    
    /**
     * Set the shared instance of the container.
     */
    public static function setInstance(?Container $container = null): ?Container
    {
        $previous = static::$instance;
        static::$instance = $container;
        return $previous;
    }
    
    /**
     * Register a binding with the container.
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $this->dropStaleInstances($abstract);
        
        // If no concrete type was given, we will simply set the concrete type to the
        // abstract type. After that, the concrete type to be registered as shared
        // without being forced to state their classes in both of the parameters.
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        
        // If the factory is not a Closure, it means it is just a class name which is
        // bound into this container to the abstract type and we will just wrap it
        // up inside its own Closure to give us more convenience when extending.
        if (! $concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }
        
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }
    
    /**
     * Register a shared binding in the container.
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }
    
    /**
     * Register an existing instance as shared in the container.
     */
    public function instance(string $abstract, $instance): void
    {
        $this->removeAbstractAlias($abstract);
        
        unset($this->aliases[$abstract]);
        
        $this->instances[$abstract] = $instance;
    }
    
    /**
     * Alias a type to a different name.
     */
    public function alias(string $abstract, string $alias): void
    {
        if ($alias === $abstract) {
            throw new InvalidArgumentException("[{$abstract}] is aliased to itself.");
        }
        
        $this->aliases[$alias] = $abstract;
    }
    
    /**
     * Resolve the given type from the container.
     */
    public function resolve(string $abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);
        
        // If an instance of the type is currently being managed as a singleton we'll
        // just return an existing instance instead of instantiating new instances
        // so the developer can keep using the same objects instance every time.
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        $concrete = $this->getConcrete($abstract);
        
        // We're ready to instantiate an instance of the concrete type registered for
        // the binding. This will instantiate the types, as well as resolve any of
        // its "nested" dependencies recursively until all have gotten resolved.
        $object = $this->build($concrete, $parameters);
        
        // If the requested type is registered as a singleton we'll want to cache off
        // the instances in "memory" so we can return it later without creating an
        // entirely new instance of an object on each subsequent request for it.
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }
        
        return $object;
    }
    
    /**
     * Get the concrete type for a given abstract.
     */
    protected function getConcrete(string $abstract): mixed
    {
        // If we don't have a registered resolver or concrete for the type, we'll just
        // assume each type is a concrete name and will attempt to resolve it as is
        // since the container should be able to resolve concretes automatically.
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }
        
        return $abstract;
    }
    
    /**
     * Instantiate a concrete instance of the given type.
     */
    protected function build($concrete, array $parameters = [])
    {
        // If the concrete type is actually a Closure, we will just execute it and
        // hand back the results of the functions, which allows functions to be
        // used as resolvers for more fine-tuned resolution of these objects.
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }
        
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException("Target class [{$concrete}] does not exist.", 0, $e);
        }
        
        // If the type is not instantiable, the developer is attempting to resolve
        // an abstract type such as an Interface or Abstract Class and there is
        // no binding registered for the abstractions so we need to bail out.
        if (! $reflector->isInstantiable()) {
            throw new InvalidArgumentException("Target [{$concrete}] is not instantiable.");
        }
        
        $constructor = $reflector->getConstructor();
        
        // If there are no constructors, that means there are no dependencies then
        // we can just resolve the instances of the objects right away, without
        // resolving any other types or dependencies out of these containers.
        if (is_null($constructor)) {
            return new $concrete;
        }
        
        $dependencies = $constructor->getParameters();
        
        // Once we have all the constructor's parameters we can create each of the
        // dependency instances and then use the reflection instances to make a
        // new instance of this class, injecting the created dependencies in.
        $instances = $this->resolveDependencies($dependencies, $parameters);
        
        return $reflector->newInstanceArgs($instances);
    }
    
    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     */
    protected function resolveDependencies(array $dependencies, array $parameters): array
    {
        $results = [];
        
        foreach ($dependencies as $dependency) {
            // If the dependency has a corresponding override in the parameter array, we will use
            // that instead as the value. Otherwise we will continue with this dependency's
            // resolution and add it to the results array for returning to the caller.
            if ($this->hasParameterOverride($dependency, $parameters)) {
                $results[] = $parameters[$dependency->getName()];
                continue;
            }
            
            // If the class is null, it means the dependency is a string or some other
            // primitive type which we can not resolve since it is not a class and
            // we will just bomb out with an error since we have no-where to go.
            $result = is_null($dependency->getType()) ? $this->resolvePrimitive($dependency) : $this->resolveClass($dependency);
            
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * Determine if the given dependency has a parameter override.
     */
    protected function hasParameterOverride(ReflectionParameter $dependency, array $parameters): bool
    {
        return array_key_exists($dependency->getName(), $parameters);
    }
    
    /**
     * Resolve a non-class hinted primitive dependency.
     */
    protected function resolvePrimitive(ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        
        throw new InvalidArgumentException("Unresolvable dependency [{$parameter}] in class {$parameter->getDeclaringClass()->getName()}");
    }
    
    /**
     * Resolve a class based dependency from the container.
     */
    protected function resolveClass(ReflectionParameter $parameter)
    {
        try {
            $className = $parameter->getType() && !$parameter->getType()->isBuiltin() 
                ? $parameter->getType()->getName() 
                : null;
                
            if ($className) {
                return $this->resolve($className);
            }
        } catch (InvalidArgumentException $e) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            
            throw $e;
        }
        
        throw new InvalidArgumentException("Unresolvable dependency [{$parameter}] in class {$parameter->getDeclaringClass()->getName()}");
    }
    
    /**
     * Get the alias for an abstract if available.
     */
    protected function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }
    
    /**
     * Get the Closure to be used when building a type.
     */
    protected function getClosure(string $abstract, string $concrete): Closure
    {
        return function ($container, $parameters = []) use ($abstract, $concrete) {
            if ($abstract == $concrete) {
                return $container->build($concrete, $parameters);
            }
            
            return $container->resolve($concrete, $parameters);
        };
    }
    
    /**
     * Determine if the given abstract type has been bound.
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || 
               isset($this->instances[$abstract]) || 
               $this->isAlias($abstract);
    }
    
    /**
     * Determine if the given abstract type has been resolved.
     */
    public function resolved(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);
        
        return isset($this->instances[$abstract]);
    }
    
    /**
     * Determine if a given type is shared.
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || 
               (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true);
    }
    
    /**
     * Determine if a given string is an alias.
     */
    protected function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }
    
    /**
     * Remove an alias from the contextual binding alias cache.
     */
    protected function removeAbstractAlias(string $searched): void
    {
        if (! isset($this->aliases[$searched])) {
            return;
        }
        
        foreach ($this->aliases as $alias => $abstract) {
            if ($abstract == $searched) {
                unset($this->aliases[$alias]);
            }
        }
    }
    
    /**
     * Drop all of the stale instances and aliases.
     */
    protected function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }
    
    /**
     * Clear all bindings and resolved instances.
     */
    public function flush(): void
    {
        $this->aliases = [];
        $this->resolved = [];
        $this->bindings = [];
        $this->instances = [];
    }
}
