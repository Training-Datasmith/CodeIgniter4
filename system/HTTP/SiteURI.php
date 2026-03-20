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

use Code_Igniter\Exceptions\BadMethodCallException;
use Code_Igniter\Exceptions\Config_Exception;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Config\App;
/**
 * URI for the application site
 *
 * @see \CodeIgniter\HTTP\SiteURITest
 */
class Site_Uri extends URI
{
    /**
     * The current baseURL.
     */
    private readonly URI $base_url;
    /**
     * The path part of baseURL.
     *
     * The baseURL "http://example.com/" → '/'
     * The baseURL "http://localhost:8888/ci431/public/" → '/ci431/public/'
     */
    private string $base_path_without_index_page;
    /**
     * The Index File.
     */
    private readonly string $index_page;
    /**
     * List of URI segments in baseURL and indexPage.
     *
     * If the URI is "http://localhost:8888/ci431/public/index.php/test?a=b",
     * and the baseURL is "http://localhost:8888/ci431/public/", then:
     *   $baseSegments = [
     *       0 => 'ci431',
     *       1 => 'public',
     *       2 => 'index.php',
     *   ];
     */
    private array $base_segments;
    /**
     * List of URI segments after indexPage.
     *
     * The word "URI Segments" originally means only the URI path part relative
     * to the baseURL.
     *
     * If the URI is "http://localhost:8888/ci431/public/index.php/test?a=b",
     * and the baseURL is "http://localhost:8888/ci431/public/", then:
     *   $segments = [
     *       0 => 'test',
     *   ];
     *
     * @var array<int, string>
     *
     * @deprecated This property will be private.
     */
    protected $segments;
    /**
     * URI path relative to baseURL.
     *
     * If the baseURL contains sub folders, this value will be different from
     * the current URI path.
     *
     * This value never starts with '/'.
     */
    private string $route_path;
    /**
     * @param string              $relativePath URI path relative to baseURL. May include
     *                                          queries or fragments.
     * @param string|null         $host         Optional current hostname.
     * @param 'http'|'https'|null $scheme       Optional scheme. 'http' or 'https'.
     */
    public function __construct(App $config_app, string $relative_path = '', ?string $host = null, ?string $scheme = null)
    {
        $this->index_page = $config_app->index_page;
        $this->base_url = $this->determine_base_url($config_app, $host, $scheme);
        $this->set_base_path();
        // Fix routePath, query, fragment
        [$route_path, $query, $fragment] = $this->parse_relative_path($relative_path);
        // Fix indexPage and routePath
        $index_page_route_path = $this->get_index_page_route_path($route_path);
        // Fix the current URI
        $uri = $this->base_url . $index_page_route_path;
        // applyParts
        $parts = parse_url($uri);
        if ($parts === false) {
            throw Http_Exception::for_unable_to_parse_uri($uri);
        }
        $parts['query'] = $query;
        $parts['fragment'] = $fragment;
        $this->apply_parts($parts);
        $this->set_route_path($route_path);
    }
    private function parse_relative_path(string $relative_path): array
    {
        $parts = parse_url('http://dummy/' . $relative_path);
        if ($parts === false) {
            throw Http_Exception::for_unable_to_parse_uri($relative_path);
        }
        $route_path = $relative_path === '/' ? '/' : ltrim($parts['path'], '/');
        $query = $parts['query'] ?? '';
        $fragment = $parts['fragment'] ?? '';
        return [$route_path, $query, $fragment];
    }
    private function determine_base_url(App $config_app, ?string $host, ?string $scheme): URI
    {
        $base_url = $this->normalize_base_url($config_app);
        $uri = new URI($base_url);
        // Update scheme
        if ($scheme !== null && $scheme !== '') {
            $uri->set_scheme($scheme);
        } elseif ($config_app->force_global_secure_requests) {
            $uri->set_scheme('https');
        }
        // Update host
        if ($host !== null) {
            $uri->set_host($host);
        }
        return $uri;
    }
    private function get_index_page_route_path(string $route_path): string
    {
        // Remove starting slash unless it is `/`.
        if ($route_path !== '' && $route_path[0] === '/' && $route_path !== '/') {
            $route_path = ltrim($route_path, '/');
        }
        // Check for an index page
        $index_page = '';
        if ($this->index_page !== '') {
            $index_page = $this->index_page;
            // Check if we need a separator
            if ($route_path !== '' && $route_path[0] !== '/' && $route_path[0] !== '?') {
                $index_page .= '/';
            }
        }
        $index_page_route_path = $index_page . $route_path;
        if ($index_page_route_path === '/') {
            $index_page_route_path = '';
        }
        return $index_page_route_path;
    }
    private function normalize_base_url(App $config_app): string
    {
        // It's possible the user forgot a trailing slash on their
        // baseURL, so let's help them out.
        $base_url = rtrim($config_app->base_url, '/ ') . '/';
        // Validate baseURL
        if (filter_var($base_url, FILTER_VALIDATE_URL) === false) {
            throw new Config_Exception('Config\App::$baseURL "' . $base_url . '" is not a valid URL.');
        }
        return $base_url;
    }
    /**
     * Sets basePathWithoutIndexPage and baseSegments.
     */
    private function set_base_path(): void
    {
        $this->base_path_without_index_page = $this->base_url->get_path();
        $this->base_segments = $this->convert_to_segments($this->base_path_without_index_page);
        if ($this->index_page !== '') {
            $this->base_segments[] = $this->index_page;
        }
    }
    /**
     * @deprecated
     */
    public function set_base_url(string $base_url): void
    {
        throw new BadMethodCallException('Cannot use this method.');
    }
    /**
     * @deprecated
     */
    public function set_uri(?string $uri = null)
    {
        throw new BadMethodCallException('Cannot use this method.');
    }
    /**
     * Returns the baseURL.
     *
     * @interal
     */
    public function get_base_url(): string
    {
        return (string) $this->base_url;
    }
    /**
     * Returns the URI path relative to baseURL.
     *
     * @return string The Route path.
     */
    public function get_route_path(): string
    {
        return $this->route_path;
    }
    /**
     * Formats the URI as a string.
     */
    public function __toString(): string
    {
        return static::create_uri_string($this->get_scheme(), $this->get_authority(), $this->get_path(), $this->get_query(), $this->get_fragment());
    }
    /**
     * Sets the route path (and segments).
     *
     * @return $this
     */
    public function set_path(string $path)
    {
        $this->set_route_path($path);
        return $this;
    }
    /**
     * Sets the route path (and segments).
     */
    private function set_route_path(string $route_path): void
    {
        $route_path = $this->filter_path($route_path);
        $index_page_route_path = $this->get_index_page_route_path($route_path);
        $this->path = $this->base_path_without_index_page . $index_page_route_path;
        $this->route_path = ltrim($route_path, '/');
        $this->segments = $this->convert_to_segments($this->route_path);
    }
    /**
     * Converts path to segments
     */
    private function convert_to_segments(string $path): array
    {
        $temp_path = trim($path, '/');
        return $temp_path === '' ? [] : explode('/', $temp_path);
    }
    /**
     * Sets the path portion of the URI based on segments.
     *
     * @return $this
     *
     * @deprecated This method will be private.
     */
    public function refresh_path()
    {
        $all_segments = array_merge($this->base_segments, $this->segments);
        $this->path = '/' . $this->filter_path(implode('/', $all_segments));
        if ($this->route_path === '/' && $this->path !== '/') {
            $this->path .= '/';
        }
        $this->route_path = $this->filter_path(implode('/', $this->segments));
        return $this;
    }
    /**
     * Saves our parts from a parse_url() call.
     *
     * @param array{
     *  host?: string,
     *  user?: string,
     *  path?: string,
     *  query?: string,
     *  fragment?: string,
     *  scheme?: string,
     *  port?: int,
     *  pass?: string,
     * } $parts
     */
    protected function apply_parts(array $parts): void
    {
        if (isset($parts['host']) && $parts['host'] !== '') {
            $this->host = $parts['host'];
        }
        if (isset($parts['user']) && $parts['user'] !== '') {
            $this->user = $parts['user'];
        }
        if (isset($parts['path']) && $parts['path'] !== '') {
            $this->path = $this->filter_path($parts['path']);
        }
        if (isset($parts['query']) && $parts['query'] !== '') {
            $this->set_query($parts['query']);
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $this->fragment = $parts['fragment'];
        }
        if (isset($parts['scheme'])) {
            $this->set_scheme(rtrim($parts['scheme'], ':/'));
        } else {
            $this->set_scheme('http');
        }
        if (isset($parts['port'])) {
            // Valid port numbers are enforced by earlier parse_url or setPort()
            $this->port = $parts['port'];
        }
        if (isset($parts['pass'])) {
            $this->password = $parts['pass'];
        }
    }
    /**
     * For base_url() helper.
     *
     * @param array|string $relativePath URI string or array of URI segments.
     * @param string|null  $scheme       URI scheme. E.g., http, ftp. If empty
     *                                   string '' is set, a protocol-relative
     *                                   link is returned.
     */
    public function base_url($relative_path = '', ?string $scheme = null): string
    {
        $relative_path = $this->stringify_relative_path($relative_path);
        $config = clone config(App::class);
        $config->index_page = '';
        $host = $this->get_host();
        $uri = new self($config, $relative_path, $host, $scheme);
        // Support protocol-relative links
        if ($scheme === '') {
            return substr((string) $uri, strlen($uri->get_scheme()) + 1);
        }
        return (string) $uri;
    }
    /**
     * @param array|string $relativePath URI string or array of URI segments
     */
    private function stringify_relative_path($relative_path): string
    {
        if (is_array($relative_path)) {
            $relative_path = implode('/', $relative_path);
        }
        return $relative_path;
    }
    /**
     * For site_url() helper.
     *
     * @param array|string $relativePath URI string or array of URI segments.
     * @param string|null  $scheme       URI scheme. E.g., http, ftp. If empty
     *                                   string '' is set, a protocol-relative
     *                                   link is returned.
     * @param App|null     $config       Alternate configuration to use.
     */
    public function site_url($relative_path = '', ?string $scheme = null, ?App $config = null): string
    {
        $relative_path = $this->stringify_relative_path($relative_path);
        // Check current host.
        $host = $config instanceof App ? null : $this->get_host();
        $config ??= config(App::class);
        $uri = new self($config, $relative_path, $host, $scheme);
        // Support protocol-relative links
        if ($scheme === '') {
            return substr((string) $uri, strlen($uri->get_scheme()) + 1);
        }
        return (string) $uri;
    }
}