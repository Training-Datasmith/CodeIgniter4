<?php

declare (strict_types=1);
/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */
namespace Code_Igniter\Router\Attributes;

use Attribute;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\I18n\Time;
/**
 * Cache Attribute
 *
 * Caches the response of a controller method at the server level for a specified duration.
 * This is server-side caching to avoid expensive operations, not browser-level caching.
 *
 * Usage:
 * ```php
 * #[Cache(for: 3600)] // Cache for 1 hour
 * #[Cache(for: 300, key: 'custom_key')] // Cache with custom key
 * ```
 *
 * Limitations:
 * - Only caches GET requests; POST, PUT, DELETE, and other methods are ignored
 * - Streaming responses or file downloads may not cache properly
 * - Cache key includes HTTP method, path, query string, and possibly user_id(), but not request headers
 * - Does not automatically invalidate related cache entries
 * - Cookies set in the response are cached and reused for all subsequent requests
 * - Large responses may impact cache storage performance
 * - Browser Cache-Control headers do not affect server-side caching behavior
 *
 * Security Considerations:
 * - Ensure cache backend is properly secured and not accessible publicly
 * - Be aware that authorization checks happen before cache lookup
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Cache implements Route_Attribute_Interface
{
    public function __construct(public int $for = 3600, public ?string $key = null)
    {
    }
    public function before(Request_Interface $request): Request_Interface|Response_Interface|null
    {
        // Only cache GET requests
        if ($request->get_method() !== 'GET') {
            return null;
        }
        // Check cache before controller execution
        $cache_key = $this->key ?? $this->generate_cache_key($request);
        $cached = cache($cache_key);
        // Validate cached data structure
        if ($cached !== null && (is_array($cached) && isset($cached['body'], $cached['headers'], $cached['status']))) {
            $response = service('response');
            $response->set_body($cached['body']);
            $response->set_status_code($cached['status']);
            // Mark response as served from cache to prevent re-caching
            $response->set_header('X-Cached-Response', 'true');
            // Restore headers from cached array of header name => value strings
            foreach ($cached['headers'] as $name => $value) {
                $response->set_header($name, $value);
            }
            $time = Time::now()->get_timestamp();
            $response->set_header('Age', (string) ($time - ($cached['timestamp'] ?? $time)));
            return $response;
        }
        return null;
        // Continue to controller
    }
    public function after(Request_Interface $request, Response_Interface $response): ?Response_Interface
    {
        // Don't re-cache if response was already served from cache
        if ($response->has_header('X-Cached-Response')) {
            // Remove the marker header before sending response
            $response->remove_header('X-Cached-Response');
            return null;
        }
        // Only cache GET requests
        if ($request->get_method() !== 'GET') {
            return null;
        }
        $cache_key = $this->key ?? $this->generate_cache_key($request);
        // Convert Header objects to strings for caching
        $headers = [];
        foreach ($response->headers() as $name => $header) {
            // Handle both single Header and array of Headers
            if (is_array($header)) {
                // Multiple headers with same name
                $values = [];
                foreach ($header as $h) {
                    $values[] = $h->get_value_line();
                }
                $headers[$name] = implode(', ', $values);
            } else {
                // Single header
                $headers[$name] = $header->get_value_line();
            }
        }
        $data = ['body' => $response->get_body(), 'headers' => $headers, 'status' => $response->get_status_code(), 'timestamp' => Time::now()->get_timestamp()];
        cache()->save($cache_key, $data, $this->for);
        return $response;
    }
    protected function generate_cache_key(Request_Interface $request): string
    {
        return 'route_cache_' . hash('xxh128', $request->get_method() . $request->get_uri()->get_path() . $request->get_uri()->get_query() . (function_exists('user_id') ? user_id() : ''));
    }
}