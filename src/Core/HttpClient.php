<?php

namespace App\Core;

/**
 * HTTP Client for making external API calls
 * Provides a consistent interface for cURL operations across services
 */
class HttpClient
{
    private int $connectTimeout = 2;
    private int $timeout = 5;
    private ?string $lastError = null;
    
    /**
     * Set connection timeout in seconds
     */
    public function setConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;
        return $this;
    }
    
    /**
     * Set execution timeout in seconds
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
    
    /**
     * Make POST request
     * 
     * @param string $url API endpoint URL
     * @param array|string $data Request payload (will be JSON encoded if array)
     * @param array $headers HTTP headers
     * @return array Response with 'success', 'status', 'body', and optional 'error'
     */
    public function post(string $url, $data, array $headers = []): array
    {
        $this->lastError = null;
        
        $ch = curl_init($url);
        
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => is_array($data) ? json_encode($data) : $data,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER => $headers
        ];
        
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->lastError = "cURL error: $error";
            return [
                'success' => false,
                'status' => 0,
                'body' => null,
                'error' => $this->lastError
            ];
        }
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'body' => $response,
            'error' => $httpCode >= 200 && $httpCode < 300 ? null : "HTTP $httpCode"
        ];
    }
    
    /**
     * Make GET request
     * 
     * @param string $url API endpoint URL
     * @param array $headers HTTP headers
     * @return array Response with 'success', 'status', 'body', and optional 'error'
     */
    public function get(string $url, array $headers = []): array
    {
        $this->lastError = null;
        
        $ch = curl_init($url);
        
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER => $headers
        ];
        
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->lastError = "cURL error: $error";
            return [
                'success' => false,
                'status' => 0,
                'body' => null,
                'error' => $this->lastError
            ];
        }
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'body' => $response,
            'error' => $httpCode >= 200 && $httpCode < 300 ? null : "HTTP $httpCode"
        ];
    }
    
    /**
     * Get last error message
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
