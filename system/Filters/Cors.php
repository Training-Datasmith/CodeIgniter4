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
namespace Code_Igniter\Filters;

use Code_Igniter\HTTP\Cors as CorsService;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
/**
 * @see \CodeIgniter\Filters\CorsTest
 */
class Cors implements Filter_Interface
{
    private ?Cors_Service $cors = null;
    /**
     * @testTag $config is used for testing purposes only.
     *
     * @param array{
     *      allowedOrigins?: list<string>,
     *      allowedOriginsPatterns?: list<string>,
     *      supportsCredentials?: bool,
     *      allowedHeaders?: list<string>,
     *      exposedHeaders?: list<string>,
     *      allowedMethods?: list<string>,
     *      maxAge?: int,
     *  } $config
     */
    public function __construct(array $config = [])
    {
        if ($config !== []) {
            $this->cors = new Cors_Service($config);
        }
    }
    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(Request_Interface $request, $arguments = null)
    {
        if (!$request instanceof Incoming_Request) {
            return null;
        }
        $this->create_cors_service($arguments);
        /** @var ResponseInterface $response */
        $response = service('response');
        if ($this->cors->is_preflight_request($request)) {
            $response = $this->cors->handle_preflight_request($request, $response);
            // Always adds `Vary: Access-Control-Request-Method` header for cacheability.
            // If there is an intermediate cache server such as a CDN, if a plain
            // OPTIONS request is sent, it may be cached. But valid preflight requests
            // have this header, so it will be cached separately.
            $response->append_header('Vary', 'Access-Control-Request-Method');
            return $response;
        }
        if ($request->is('OPTIONS')) {
            // Always adds `Vary: Access-Control-Request-Method` header for cacheability.
            // If there is an intermediate cache server such as a CDN, if a plain
            // OPTIONS request is sent, it may be cached. But valid preflight requests
            // have this header, so it will be cached separately.
            $response->append_header('Vary', 'Access-Control-Request-Method');
        }
        $this->cors->add_response_headers($request, $response);
        return null;
    }
    /**
     * @param list<string>|null $arguments
     */
    private function create_cors_service(?array $arguments): void
    {
        $this->cors ??= $arguments === null ? Cors_Service::factory() : Cors_Service::factory($arguments[0]);
    }
    /**
     * @param list<string>|null $arguments
     */
    public function after(Request_Interface $request, Response_Interface $response, $arguments = null)
    {
        if (!$request instanceof Incoming_Request) {
            return null;
        }
        $this->create_cors_service($arguments);
        if ($this->cors->has_response_headers($request, $response)) {
            return null;
        }
        // Always adds `Vary: Access-Control-Request-Method` header for cacheability.
        // If there is an intermediate cache server such as a CDN, if a plain
        // OPTIONS request is sent, it may be cached. But valid preflight requests
        // have this header, so it will be cached separately.
        if ($request->is('OPTIONS')) {
            $response->append_header('Vary', 'Access-Control-Request-Method');
        }
        return $this->cors->add_response_headers($request, $response);
    }
}