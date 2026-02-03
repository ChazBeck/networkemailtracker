<?php

namespace App\Services;

/**
 * LinkedInUrlNormalizer
 * 
 * Normalizes LinkedIn profile URLs to a consistent format for thread deduplication
 * Strips tracking parameters, standardizes format, handles edge cases
 */
class LinkedInUrlNormalizer
{
    /**
     * Normalize a LinkedIn URL to standard format
     * 
     * @param string $url Raw LinkedIn URL
     * @return string Normalized URL or original if not a valid LinkedIn URL
     */
    public function normalize(string $url): string
    {
        // Trim whitespace
        $url = trim($url);
        
        // Return empty if empty input
        if (empty($url)) {
            return '';
        }
        
        // Add https:// if no protocol specified
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        
        // Parse URL
        $parsed = parse_url($url);
        
        if (!$parsed || !isset($parsed['host'])) {
            return $url; // Return original if can't parse
        }
        
        // Check if it's a LinkedIn domain
        $host = strtolower($parsed['host']);
        if (!$this->isLinkedInDomain($host)) {
            return $url; // Not a LinkedIn URL, return as-is
        }
        
        // Get path
        $path = $parsed['path'] ?? '/';
        
        // Remove trailing slashes
        $path = rtrim($path, '/');
        
        // Normalize path
        $normalizedPath = $this->normalizePath($path);
        
        // Build normalized URL
        $normalizedUrl = 'https://www.linkedin.com' . $normalizedPath;
        
        return $normalizedUrl;
    }
    
    /**
     * Check if domain is a LinkedIn domain
     * 
     * @param string $host Domain name
     * @return bool True if LinkedIn domain
     */
    private function isLinkedInDomain(string $host): bool
    {
        $linkedInDomains = [
            'linkedin.com',
            'www.linkedin.com',
            'mobile.linkedin.com',
            'm.linkedin.com',
            'touch.www.linkedin.com'
        ];
        
        // Check exact match
        if (in_array($host, $linkedInDomains)) {
            return true;
        }
        
        // Check if ends with linkedin.com (for regional domains like uk.linkedin.com)
        if (str_ends_with($host, '.linkedin.com')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Normalize the path component of LinkedIn URL
     * 
     * @param string $path URL path
     * @return string Normalized path
     */
    private function normalizePath(string $path): string
    {
        // Convert to lowercase for consistency
        $path = strtolower($path);
        
        // Handle profile URLs: /in/username
        if (preg_match('#^/in/([a-z0-9\-]+)#i', $path, $matches)) {
            return '/in/' . $matches[1];
        }
        
        // Handle company URLs: /company/companyname
        if (preg_match('#^/company/([a-z0-9\-]+)#i', $path, $matches)) {
            return '/company/' . $matches[1];
        }
        
        // Handle Sales Navigator profile URLs: /sales/people/...
        if (preg_match('#^/sales/people/([^/,]+)#i', $path, $matches)) {
            // Sales Navigator URLs often have additional parameters, extract the ID
            return '/sales/people/' . $matches[1];
        }
        
        // Handle Sales Navigator lead URLs: /sales/lead/...
        if (preg_match('#^/sales/lead/([^/,]+)#i', $path, $matches)) {
            return '/sales/lead/' . $matches[1];
        }
        
        // Handle numeric profile IDs: /profile/view?id=12345
        if (str_contains($path, '/profile/view')) {
            // This is an old format, try to preserve ID
            if (preg_match('#[?&]id=([0-9]+)#', $path, $matches)) {
                return '/profile/view?id=' . $matches[1];
            }
        }
        
        // Return cleaned path if no specific pattern matched
        return $path;
    }
    
    /**
     * Extract username/identifier from normalized LinkedIn URL
     * 
     * @param string $url Normalized LinkedIn URL
     * @return string|null Username or identifier, null if can't extract
     */
    public function extractIdentifier(string $url): ?string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        
        // Extract from /in/username
        if (preg_match('#^/in/([a-z0-9\-]+)#i', $path, $matches)) {
            return $matches[1];
        }
        
        // Extract from /company/companyname
        if (preg_match('#^/company/([a-z0-9\-]+)#i', $path, $matches)) {
            return $matches[1];
        }
        
        // Extract from sales URLs
        if (preg_match('#^/sales/(people|lead)/([^/,]+)#i', $path, $matches)) {
            return $matches[2];
        }
        
        return null;
    }
    
    /**
     * Validate if URL is a valid LinkedIn profile/company URL
     * 
     * @param string $url URL to validate
     * @return bool True if valid LinkedIn profile URL
     */
    public function isValid(string $url): bool
    {
        $normalized = $this->normalize($url);
        
        // Must start with LinkedIn domain
        if (!str_starts_with($normalized, 'https://www.linkedin.com/')) {
            return false;
        }
        
        // Must have a valid path pattern
        $parsed = parse_url($normalized);
        $path = $parsed['path'] ?? '';
        
        $validPatterns = [
            '#^/in/[a-z0-9\-]+$#i',              // Profile
            '#^/company/[a-z0-9\-]+$#i',         // Company
            '#^/sales/people/[^/,]+$#i',         // Sales Navigator profile
            '#^/sales/lead/[^/,]+$#i',           // Sales Navigator lead
            '#^/profile/view\?id=[0-9]+$#i'      // Legacy profile
        ];
        
        foreach ($validPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }
}
