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

use Code_Igniter\Exceptions\Config_Exception;
use Config\Cors as CorsConfig;
/**
 * Cross-Origin Resource Sharing (CORS)
 *
 * @see \CodeIgniter\HTTP\CorsTest
 */
class Cors
{
    /**
     * @var array{
     *     allowedOrigins: list<string>,
     *     allowedOriginsPatterns: list<string>,
     *     supportsCredentials: bool,
     *     allowedHeaders: list<string>,
     *     exposedHeaders: list<string>,
     *     allowedMethods: list<string>,
     *     maxAge: int,
     * }
     */
    private array $config = ['allowedOrigins' => [], 'allowedOriginsPatterns' => [], 'supportsCredentials' => false, 'allowedHeaders' => [], 'exposedHeaders' => [], 'allowedMethods' => [], 'maxAge' => 7200];
    /**
     * @param array{
     *     allowedOrigins?: list<string>,
     *     allowedOriginsPatterns?: list<string>,
     *     supportsCredentials?: bool,
     *     allowedHeaders?: list<string>,
     *     exposedHeaders?: list<string>,
     *     allowedMethods?: list<string>,
     *     maxAge?: int,
     * }|CorsConfig|null $config
     */
    public function __construct($config = null)
    {
        $config ??= config(Cors_Config::class);
        if ($config instanceof Cors_Config) {
            $config = $config->default;
        }
        $this->config = array_merge($this->config, $config);
    }
    /**
     * Creates a new instance by config name.
     */
    public static function factory(string $config_name = 'default'): self
    {
        $config = config(Cors_Config::class)->{$config_name};
        return new self($config);
    }
    /**
     * Whether if the request is a preflight request.
     */
    public function is_preflight_request(Incoming_Request $request): bool
    {
        return $request->is('OPTIONS') && $request->has_header('Access-Control-Request-Method');
    }
    /**
     * Handles the preflight request, and returns the response.
     */
    public function handle_preflight_request(Request_Interface $request, Response_Interface $response): Response_Interface
    {
        $response->set_status_code(204);
        $this->set_allow_origin($request, $response);
        if ($response->has_header('Access-Control-Allow-Origin')) {
            $this->set_allow_headers($response);
            $this->set_allow_methods($response);
            $this->set_allow_max_age($response);
            $this->set_allow_credentials($response);
        }
        return $response;
    }
    private function check_wildcard(string $name, int $count): void
    {
        if (in_array('*', $this->config[$name], true) && $count > 1) {
            throw new Config_Exception("If wildcard is specified, you must set `'{$name}' => ['*']`." . ' But using wildcard is not recommended.');
        }
    }
    private function check_wildcard_and_credentials(string $name, string $header): void
    {
        if ($this->config[$name] === ['*'] && $this->config['supportsCredentials']) {
            throw new Config_Exception('When responding to a credentialed request, ' . 'the server must not specify the "*" wildcard for the ' . $header . ' response-header value.');
        }
    }
    private function set_allow_origin(Request_Interface $request, Response_Interface $response): void
    {
        $origin_count = count($this->config['allowedOrigins']);
        $origin_pattern_count = count($this->config['allowedOriginsPatterns']);
        $this->check_wildcard('allowedOrigins', $origin_count);
        $this->check_wildcard_and_credentials('allowedOrigins', 'Access-Control-Allow-Origin');
        // Single Origin.
        if ($origin_count === 1 && $origin_pattern_count === 0) {
            $response->set_header('Access-Control-Allow-Origin', $this->config['allowedOrigins'][0]);
            return;
        }
        // Multiple Origins.
        if (!$request->has_header('Origin')) {
            return;
        }
        $origin = $request->get_header_line('Origin');
        if ($origin_count > 1 && in_array($origin, $this->config['allowedOrigins'], true)) {
            $response->set_header('Access-Control-Allow-Origin', $origin);
            $response->append_header('Vary', 'Origin');
            return;
        }
        if ($origin_pattern_count > 0) {
            foreach ($this->config['allowedOriginsPatterns'] as $pattern) {
                $regex = '#\A' . $pattern . '\z#';
                if (preg_match($regex, $origin)) {
                    $response->set_header('Access-Control-Allow-Origin', $origin);
                    $response->append_header('Vary', 'Origin');
                    return;
                }
            }
        }
    }
    private function set_allow_headers(Response_Interface $response): void
    {
        $this->check_wildcard('allowedHeaders', count($this->config['allowedHeaders']));
        $this->check_wildcard_and_credentials('allowedHeaders', 'Access-Control-Allow-Headers');
        $response->set_header('Access-Control-Allow-Headers', implode(', ', $this->config['allowedHeaders']));
    }
    private function set_allow_methods(Response_Interface $response): void
    {
        $this->check_wildcard('allowedMethods', count($this->config['allowedMethods']));
        $this->check_wildcard_and_credentials('allowedMethods', 'Access-Control-Allow-Methods');
        $response->set_header('Access-Control-Allow-Methods', implode(', ', $this->config['allowedMethods']));
    }
    private function set_allow_max_age(Response_Interface $response): void
    {
        $response->set_header('Access-Control-Max-Age', (string) $this->config['maxAge']);
    }
    private function set_allow_credentials(Response_Interface $response): void
    {
        if ($this->config['supportsCredentials']) {
            $response->set_header('Access-Control-Allow-Credentials', 'true');
        }
    }
    /**
     * Adds CORS headers to the Response.
     */
    public function add_response_headers(Request_Interface $request, Response_Interface $response): Response_Interface
    {
        $this->set_allow_origin($request, $response);
        if ($response->has_header('Access-Control-Allow-Origin')) {
            $this->set_allow_credentials($response);
            $this->set_expose_headers($response);
        }
        return $response;
    }
    private function set_expose_headers(Response_Interface $response): void
    {
        if ($this->config['exposedHeaders'] !== []) {
            $response->set_header('Access-Control-Expose-Headers', implode(', ', $this->config['exposedHeaders']));
        }
    }
    /**
     * Check if response headers were set
     */
    public function has_response_headers(Request_Interface $request, Response_Interface $response): bool
    {
        if (!$response->has_header('Access-Control-Allow-Origin')) {
            return false;
        }
        if ($this->config['supportsCredentials'] && !$response->has_header('Access-Control-Allow-Credentials')) {
            return false;
        }
        return !($this->config['exposedHeaders'] !== [] && (!$response->has_header('Access-Control-Expose-Headers') || !str_contains($response->get_header_line('Access-Control-Expose-Headers'), implode(', ', $this->config['exposedHeaders']))));
    }
}