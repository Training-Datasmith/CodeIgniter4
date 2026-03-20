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
namespace Code_Igniter\Cache;

use Code_Igniter\Exceptions\RuntimeException;
use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Header;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Response_Interface;
use Config\Cache as CacheConfig;
/**
 * Web Page Caching
 *
 * @see \CodeIgniter\Cache\ResponseCacheTest
 */
final class Response_Cache
{
    /**
     * Whether to take the URL query string into consideration when generating
     * output cache files. Valid options are:
     *
     *    false      = Disabled
     *    true       = Enabled, take all query parameters into account.
     *                 Please be aware that this may result in numerous cache
     *                 files generated for the same page over and over again.
     *    array('q') = Enabled, but only take into account the specified list
     *                 of query parameters.
     *
     * @var bool|list<string>
     */
    private array|bool $cache_query_string = false;
    /**
     * Cache time to live (TTL) in seconds.
     */
    private int $ttl = 0;
    public function __construct(Cache_Config $config, private readonly Cache_Interface $cache)
    {
        $this->cache_query_string = $config->cache_query_string;
    }
    public function set_ttl(int $ttl): self
    {
        $this->ttl = $ttl;
        return $this;
    }
    /**
     * Generates the cache key to use from the current request.
     *
     * @internal for testing purposes only
     */
    public function generate_cache_key(Cli_Request|Incoming_Request $request): string
    {
        if ($request instanceof Cli_Request) {
            return md5($request->get_path());
        }
        $uri = clone $request->get_uri();
        $query = (bool) $this->cache_query_string ? $uri->get_query(is_array($this->cache_query_string) ? ['only' => $this->cache_query_string] : []) : '';
        return md5($request->get_method() . ':' . $uri->set_fragment('')->set_query($query));
    }
    /**
     * Caches the response.
     */
    public function make(Cli_Request|Incoming_Request $request, Response_Interface $response): bool
    {
        if ($this->ttl === 0) {
            return true;
        }
        $headers = [];
        foreach ($response->headers() as $name => $value) {
            if ($value instanceof Header) {
                $headers[$name] = $value->get_value_line();
            } else {
                foreach ($value as $header) {
                    $headers[$name][] = $header->get_value_line();
                }
            }
        }
        return $this->cache->save($this->generate_cache_key($request), serialize(['headers' => $headers, 'output' => $response->get_body(), 'status' => $response->get_status_code(), 'reason' => $response->get_reason_phrase()]), $this->ttl);
    }
    /**
     * Gets the cached response for the request.
     */
    public function get(Cli_Request|Incoming_Request $request, Response_Interface $response): ?Response_Interface
    {
        $cached_response = $this->cache->get($this->generate_cache_key($request));
        if (is_string($cached_response) && $cached_response !== '') {
            $cached_response = unserialize($cached_response, ['allowed_classes' => false]);
            if (!is_array($cached_response) || !isset($cached_response['output']) || !isset($cached_response['headers'])) {
                throw new RuntimeException('Error unserializing page cache');
            }
            $headers = $cached_response['headers'];
            $output = $cached_response['output'];
            $status = $cached_response['status'] ?? 200;
            $reason = $cached_response['reason'] ?? '';
            // Clear all default headers
            foreach (array_keys($response->headers()) as $key) {
                $response->remove_header($key);
            }
            // Set cached headers
            foreach ($headers as $name => $value) {
                $response->set_header($name, $value);
            }
            $response->set_body($output);
            $response->set_status_code($status, $reason);
            return $response;
        }
        return null;
    }
}