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
use Code_Igniter\Exceptions\Page_Not_Found_Exception;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
/**
 * Restrict Attribute
 *
 * Restricts access to controller methods or entire controllers based on environment,
 * hostname, or subdomain conditions. Throws PageNotFoundException when restrictions
 * are not met.
 *
 * Limitations:
 * - Throws PageNotFoundException (404) for all restriction failures
 * - Cannot provide custom error messages or HTTP status codes
 * - Subdomain detection may not work correctly behind proxies without proper configuration
 * - Does not support wildcard or regex patterns for hostnames
 * - Cannot restrict based on request headers, IP addresses, or user authentication
 *
 * Security Considerations:
 * - Environment checks rely on the ENVIRONMENT constant being correctly set
 * - Hostname restrictions can be bypassed if Host header is not validated at web server level
 * - Should not be used as the sole security mechanism for sensitive operations
 * - Consider additional authorization checks for critical endpoints
 * - Does not prevent direct access if routes are exposed through other means
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Restrict implements Route_Attribute_Interface
{
    public function __construct(public array|string|null $environment = null, public array|string|null $hostname = null, public array|string|null $subdomain = null)
    {
    }
    public function before(Request_Interface $request): Request_Interface|Response_Interface|null
    {
        $this->check_environment();
        $this->check_hostname($request);
        $this->check_subdomain($request);
        return null;
        // Continue normal execution
    }
    public function after(Request_Interface $request, Response_Interface $response): ?Response_Interface
    {
        return null;
        // No post-processing needed
    }
    protected function check_environment(): void
    {
        if ($this->environment === null || $this->environment === []) {
            return;
        }
        $current_env = ENVIRONMENT;
        $allowed = [];
        $denied = [];
        foreach ((array) $this->environment as $env) {
            if (str_starts_with($env, '!')) {
                $denied[] = substr($env, 1);
            } else {
                $allowed[] = $env;
            }
        }
        // Check denied environments first (explicit deny takes precedence)
        if ($denied !== [] && in_array($current_env, $denied, true)) {
            throw new Page_Not_Found_Exception('Access denied: Current environment is blocked.');
        }
        // If allowed list exists, current env must be in it
        // If no allowed list (only denials), then all non-denied envs are allowed
        if ($allowed !== [] && !in_array($current_env, $allowed, true)) {
            throw new Page_Not_Found_Exception('Access denied: Current environment is not allowed.');
        }
    }
    private function check_hostname(Request_Interface $request): void
    {
        if ($this->hostname === null || $this->hostname === []) {
            return;
        }
        $current_host = strtolower($request->get_uri()->get_host());
        $allowed_hosts = array_map(strtolower(...), (array) $this->hostname);
        if (!in_array($current_host, $allowed_hosts, true)) {
            throw new Page_Not_Found_Exception('Access denied: Host is not allowed.');
        }
    }
    private function check_subdomain(Request_Interface $request): void
    {
        if ($this->subdomain === null || $this->subdomain === []) {
            return;
        }
        $current_subdomain = parse_subdomain($request->get_uri()->get_host());
        $allowed_subdomains = array_map(strtolower(...), (array) $this->subdomain);
        // If no subdomain exists but one is required
        if ($current_subdomain === '') {
            throw new Page_Not_Found_Exception('Access denied: Subdomain required');
        }
        // Check if the current subdomain is allowed
        if (!in_array($current_subdomain, $allowed_subdomains, true)) {
            throw new Page_Not_Found_Exception('Access denied: subdomain is blocked.');
        }
    }
}