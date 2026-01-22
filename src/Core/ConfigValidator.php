<?php

namespace App\Core;

/**
 * Configuration validator for required environment variables
 * Validates configuration at application startup
 */
class ConfigValidator
{
    private array $errors = [];
    
    /**
     * Validate that required environment variables are set and not empty
     * 
     * @param array $requiredKeys Array of required environment variable names
     * @throws ConfigurationException if any required variables are missing
     */
    public function validateRequired(array $requiredKeys): void
    {
        $this->errors = [];
        
        foreach ($requiredKeys as $key) {
            if (!isset($_ENV[$key]) || trim($_ENV[$key]) === '') {
                $this->errors[] = "Required environment variable '$key' is not set or is empty";
            }
        }
        
        if (!empty($this->errors)) {
            throw new ConfigurationException(
                "Configuration validation failed:\n  - " . implode("\n  - ", $this->errors)
            );
        }
    }
    
    /**
     * Validate optional variables (log warnings if missing but don't throw)
     * 
     * @param array $optionalKeys Array of optional environment variable names
     * @param callable|null $logger Optional logger function to call with warnings
     * @return array List of missing optional keys
     */
    public function validateOptional(array $optionalKeys, ?callable $logger = null): array
    {
        $missing = [];
        
        foreach ($optionalKeys as $key) {
            if (!isset($_ENV[$key]) || trim($_ENV[$key]) === '') {
                $missing[] = $key;
                if ($logger) {
                    $logger("Optional environment variable '$key' is not set - some features may be disabled");
                }
            }
        }
        
        return $missing;
    }
    
    /**
     * Validate format of an environment variable (e.g., URL, email, numeric)
     * 
     * @param string $key Environment variable name
     * @param string $pattern Regex pattern to validate against
     * @param string $description Human-readable description of expected format
     * @throws ConfigurationException if format is invalid
     */
    public function validateFormat(string $key, string $pattern, string $description): void
    {
        if (!isset($_ENV[$key]) || trim($_ENV[$key]) === '') {
            return; // Skip format validation if not set (use validateRequired first)
        }
        
        $value = $_ENV[$key];
        
        if (!preg_match($pattern, $value)) {
            throw new ConfigurationException(
                "Environment variable '$key' has invalid format. Expected: $description, Got: $value"
            );
        }
    }
    
    /**
     * Get all validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

/**
 * Configuration exception thrown when validation fails
 */
class ConfigurationException extends \Exception
{
}
