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
namespace Code_Igniter\Commands\Utilities\Routes;

use Code_Igniter\Filters\Filters;
use Code_Igniter\HTTP\Method;
use Code_Igniter\HTTP\Request;
use Code_Igniter\Router\Router;
use Config\Filters as FiltersConfig;
/**
 * Collects filters for a route.
 *
 * @see \CodeIgniter\Commands\Utilities\Routes\FilterCollectorTest
 */
final readonly class Filter_Collector
{
    public function __construct(
        /**
         * Whether to reset Defined Routes.
         *
         * If set to true, route filters are not found.
         */
        private bool $reset_routes = false
    )
    {
    }
    /**
     * Returns filters for the URI
     *
     * @param string $method HTTP verb like `GET`,`POST` or `CLI`.
     * @param string $uri    URI path to find filters for
     *
     * @return array{before: list<string>, after: list<string>} array of alias/classname:args
     */
    public function get(string $method, string $uri): array
    {
        if ($method === strtolower($method)) {
            @trigger_error('Passing lowercase HTTP method "' . $method . '" is deprecated.' . ' Use uppercase HTTP method like "' . strtoupper($method) . '".', E_USER_DEPRECATED);
        }
        /**
         * @deprecated 4.5.0
         * @TODO Remove this in the future.
         */
        $method = strtoupper($method);
        if ($method === 'CLI') {
            return ['before' => [], 'after' => []];
        }
        $request = service('incomingrequest', null, false);
        $request->set_method($method);
        $router = $this->create_router($request);
        $filters = $this->create_filters($request);
        $finder = new Filter_Finder($router, $filters);
        return $finder->find($uri);
    }
    /**
     * Returns filter classes for the URI
     *
     * @param string $method HTTP verb like `GET`,`POST` or `CLI`.
     * @param string $uri    URI path to find filters for
     *
     * @return array{before: list<string>, after: list<string>} array of classname:args
     */
    public function get_classes(string $method, string $uri): array
    {
        if ($method === strtolower($method)) {
            @trigger_error('Passing lowercase HTTP method "' . $method . '" is deprecated.' . ' Use uppercase HTTP method like "' . strtoupper($method) . '".', E_USER_DEPRECATED);
        }
        /**
         * @deprecated 4.5.0
         * @TODO Remove this in the future.
         */
        $method = strtoupper($method);
        if ($method === 'CLI') {
            return ['before' => [], 'after' => []];
        }
        $request = service('incomingrequest', null, false);
        $request->set_method($method);
        $router = $this->create_router($request);
        $filters = $this->create_filters($request);
        $finder = new Filter_Finder($router, $filters);
        return $finder->find_classes($uri);
    }
    /**
     * Returns Required Filters
     *
     * @return array{before: list<string>, after: list<string>} array of aliases
     */
    public function get_required_filters(): array
    {
        $request = service('incomingrequest', null, false);
        $request->set_method(Method::GET);
        $router = $this->create_router($request);
        $filters = $this->create_filters($request);
        $finder = new Filter_Finder($router, $filters);
        return $finder->get_required_filters();
    }
    /**
     * Returns Required Filter class list
     *
     * @return array{before: list<string>, after: list<string>} array of classnames
     */
    public function get_required_filter_classes(): array
    {
        $request = service('incomingrequest', null, false);
        $request->set_method(Method::GET);
        $router = $this->create_router($request);
        $filters = $this->create_filters($request);
        $finder = new Filter_Finder($router, $filters);
        return $finder->get_required_filter_classes();
    }
    private function create_router(Request $request): Router
    {
        $routes = service('routes');
        if ($this->reset_routes) {
            $routes->reset_routes();
        }
        return new Router($routes, $request);
    }
    private function create_filters(Request $request): Filters
    {
        $config = config(Filters_Config::class);
        return new Filters($config, $request, service('response'));
    }
}