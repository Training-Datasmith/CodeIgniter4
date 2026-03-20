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
namespace Code_Igniter\HTTP;

use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Code_Igniter\Superglobals;
use Config\App;
/**
 * Creates SiteURI using superglobals.
 *
 * This class also updates superglobal $_SERVER and $_GET.
 *
 * @see \CodeIgniter\HTTP\SiteURIFactoryTest
 */
final readonly class Site_Uri_Factory
{
    public function __construct(private App $app_config, private Superglobals $superglobals)
    {
    }
    /**
     * Create the current URI object from superglobals.
     *
     * This method updates superglobal $_SERVER and $_GET.
     */
    public function create_from_globals(): Site_Uri
    {
        $route_path = $this->detect_route_path();
        return $this->create_uri_from_route_path($route_path);
    }
    /**
     * Create the SiteURI object from URI string.
     *
     * @internal Used for testing purposes only.
     * @testTag
     */
    public function create_from_string(string $uri): Site_Uri
    {
        // Validate URI
        if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
            throw Http_Exception::for_unable_to_parse_uri($uri);
        }
        $parts = parse_url($uri);
        if ($parts === false) {
            throw Http_Exception::for_unable_to_parse_uri($uri);
        }
        $query = $fragment = '';
        if (isset($parts['query'])) {
            $query = '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $fragment = '#' . $parts['fragment'];
        }
        $relative_path = ($parts['path'] ?? '') . $query . $fragment;
        $host = $this->get_valid_host($parts['host']);
        return new Site_Uri($this->app_config, $relative_path, $host, $parts['scheme']);
    }
    /**
     * Detects the current URI path relative to baseURL based on the URIProtocol
     * Config setting.
     *
     * @param string $protocol URIProtocol
     *
     * @return string The route path
     *
     * @internal Used for testing purposes only.
     * @testTag
     */
    public function detect_route_path(string $protocol = ''): string
    {
        if ($protocol === '') {
            $protocol = $this->app_config->uri_protocol;
        }
        $route_path = match ($protocol) {
            'REQUEST_URI' => $this->parse_request_uri(),
            'QUERY_STRING' => $this->parse_query_string(),
            default => $this->superglobals->server($protocol) ?? $this->parse_request_uri(),
        };
        return $route_path === '/' || $route_path === '' ? '/' : ltrim($route_path, '/');
    }
    /**
     * Will parse the REQUEST_URI and automatically detect the URI from it,
     * fixing the query string if necessary.
     *
     * This method updates superglobal $_SERVER and $_GET.
     *
     * @return string The route path (before normalization).
     */
    private function parse_request_uri(): string
    {
        if ($this->superglobals->server('REQUEST_URI') === null || $this->superglobals->server('SCRIPT_NAME') === null) {
            return '';
        }
        // parse_url() returns false if no host is present, but the path or query
        // string contains a colon followed by a number. So we attach a dummy
        // host since REQUEST_URI does not include the host. This allows us to
        // parse out the query string and path.
        $parts = parse_url('http://dummy' . $this->superglobals->server('REQUEST_URI'));
        $query = $parts['query'] ?? '';
        $path = $parts['path'] ?? '';
        // Strip the SCRIPT_NAME path from the URI
        if ($path !== '' && $this->superglobals->server('SCRIPT_NAME') !== '' && pathinfo($this->superglobals->server('SCRIPT_NAME'), PATHINFO_EXTENSION) === 'php') {
            // Compare each segment, dropping them until there is no match
            $segments = explode('/', rawurldecode($path));
            $keep = explode('/', $path);
            foreach (explode('/', $this->superglobals->server('SCRIPT_NAME')) as $i => $segment) {
                // If these segments are not the same then we're done
                if (!isset($segments[$i]) || $segment !== $segments[$i]) {
                    break;
                }
                array_shift($keep);
            }
            $path = implode('/', $keep);
        }
        // Cleanup: if indexPage is still visible in the path, remove it
        if ($this->app_config->index_page !== '' && str_starts_with($path, $this->app_config->index_page)) {
            $remaining_path = substr($path, strlen($this->app_config->index_page));
            // Only remove if followed by '/' (route) or nothing (root)
            if ($remaining_path === '' || str_starts_with($remaining_path, '/')) {
                $path = ltrim($remaining_path, '/');
            }
        }
        // This section ensures that even on servers that require the URI to
        // contain the query string (Nginx) a correct URI is found, and also
        // fixes the QUERY_STRING Server var and $_GET array.
        if (trim($path, '/') === '' && str_starts_with($query, '/')) {
            $parts = explode('?', $query, 2);
            $path = $parts[0];
            $new_query = $query[1] ?? '';
            $this->superglobals->set_server('QUERY_STRING', $new_query);
        } else {
            $this->superglobals->set_server('QUERY_STRING', $query);
        }
        // Update our global GET for values likely to have been changed
        parse_str($this->superglobals->server('QUERY_STRING'), $get);
        $this->superglobals->set_get_array($get);
        return URI::remove_dot_segments($path);
    }
    /**
     * Will parse QUERY_STRING and automatically detect the URI from it.
     *
     * This method updates superglobal $_SERVER and $_GET.
     *
     * @return string The route path (before normalization).
     */
    private function parse_query_string(): string
    {
        $query = $this->superglobals->server('QUERY_STRING') ?? (string) getenv('QUERY_STRING');
        if (trim($query, '/') === '') {
            return '/';
        }
        if (str_starts_with($query, '/')) {
            $parts = explode('?', $query, 2);
            $path = $parts[0];
            $new_query = $parts[1] ?? '';
            $this->superglobals->set_server('QUERY_STRING', $new_query);
        } else {
            $path = $query;
        }
        // Update our global GET for values likely to have been changed
        parse_str($this->superglobals->server('QUERY_STRING'), $get);
        $this->superglobals->set_get_array($get);
        return URI::remove_dot_segments($path);
    }
    /**
     * Create current URI object.
     *
     * @param string $routePath URI path relative to baseURL
     */
    private function create_uri_from_route_path(string $route_path): Site_Uri
    {
        $query = $this->superglobals->server('QUERY_STRING') ?? '';
        $relative_path = $query !== '' ? $route_path . '?' . $query : $route_path;
        return new Site_Uri($this->app_config, $relative_path, $this->get_host());
    }
    /**
     * @return string|null The current hostname. Returns null if no valid host.
     */
    private function get_host(): ?string
    {
        $http_host_port = $this->superglobals->server('HTTP_HOST') ?? null;
        if ($http_host_port !== null) {
            [$http_host] = explode(':', $http_host_port, 2);
            return $this->get_valid_host($http_host);
        }
        return null;
    }
    /**
     * @return string|null The valid hostname. Returns null if not valid.
     */
    private function get_valid_host(string $host): ?string
    {
        if (in_array($host, $this->app_config->allowed_hostnames, true)) {
            return $host;
        }
        return null;
    }
}