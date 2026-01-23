<?php

namespace App\Core;

/**
 * Dependency Injection Container
 * 
 * Manages service instantiation and dependencies
 */
class Container
{
    private array $services = [];
    private array $singletons = [];
    
    /**
     * Register a service factory
     * 
     * @param string $name Service identifier
     * @param callable $factory Factory function that creates the service
     */
    public function register(string $name, callable $factory): void
    {
        $this->services[$name] = $factory;
    }
    
    /**
     * Register a singleton service
     * Service will be instantiated once and reused
     * 
     * @param string $name Service identifier
     * @param callable $factory Factory function
     */
    public function singleton(string $name, callable $factory): void
    {
        $this->services[$name] = $factory;
        $this->singletons[$name] = null;
    }
    
    /**
     * Get a service instance
     * 
     * @param string $name Service identifier
     * @return mixed Service instance
     * @throws \RuntimeException If service not found
     */
    public function get(string $name): mixed
    {
        if (!isset($this->services[$name])) {
            throw new \RuntimeException("Service '$name' not found in container");
        }
        
        // Return singleton if already instantiated
        if (array_key_exists($name, $this->singletons)) {
            if ($this->singletons[$name] === null) {
                $this->singletons[$name] = $this->services[$name]($this);
            }
            return $this->singletons[$name];
        }
        
        // Create new instance
        return $this->services[$name]($this);
    }
    
    /**
     * Check if service is registered
     * 
     * @param string $name Service identifier
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }
    
    /**
     * Set a pre-instantiated service
     * Useful for testing with mocks
     * 
     * @param string $name Service identifier
     * @param mixed $instance Service instance
     */
    public function set(string $name, mixed $instance): void
    {
        $this->services[$name] = fn() => $instance;
        $this->singletons[$name] = $instance;
    }
}
