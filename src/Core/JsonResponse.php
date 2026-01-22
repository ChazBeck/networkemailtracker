<?php

namespace App\Core;

/**
 * JSON Response helper for standardized HTTP responses
 */
class JsonResponse
{
    private $data;
    private int $statusCode;
    private array $headers = [];
    
    /**
     * Create new JSON response
     * 
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     */
    public function __construct($data, int $statusCode = 200)
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
    }
    
    /**
     * Create success response
     * 
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code (default 200)
     * @return self
     */
    public static function success($data, int $statusCode = 200): self
    {
        return new self($data, $statusCode);
    }
    
    /**
     * Create error response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code (default 400)
     * @param array $details Optional additional error details
     * @return self
     */
    public static function error(string $message, int $statusCode = 400, array $details = []): self
    {
        $data = ['success' => false, 'error' => $message];
        
        if (!empty($details)) {
            $data['details'] = $details;
        }
        
        return new self($data, $statusCode);
    }
    
    /**
     * Create accepted response (202)
     * 
     * @param mixed $data Response data
     * @return self
     */
    public static function accepted($data): self
    {
        return new self($data, 202);
    }
    
    /**
     * Create not found response (404)
     * 
     * @param string $message Error message
     * @return self
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::error($message, 404);
    }
    
    /**
     * Create server error response (500)
     * 
     * @param string $message Error message
     * @return self
     */
    public static function serverError(string $message = 'Internal server error'): self
    {
        return self::error($message, 500);
    }
    
    /**
     * Create bad request response (400)
     * 
     * @param string $message Error message
     * @return self
     */
    public static function badRequest(string $message): self
    {
        return self::error($message, 400);
    }
    
    /**
     * Add custom header
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return self
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }
    
    /**
     * Send the response
     */
    public function send(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json');
        
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        
        echo json_encode($this->data);
    }
    
    /**
     * Get the response data (for testing)
     */
    public function getData()
    {
        return $this->data;
    }
    
    /**
     * Get the status code (for testing)
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
