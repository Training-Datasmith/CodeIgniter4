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
namespace Code_Igniter\Router;

use Closure;
use Generator;
/**
 * Collect all defined routes for display.
 *
 * @see \CodeIgniter\Router\DefinedRouteCollectorTest
 */
final readonly class Defined_Route_Collector
{
    public function __construct(private Route_Collection_Interface $route_collection)
    {
    }
    /**
     * @return Generator<array{method: string, route: string, name: string, handler: string}>
     */
    public function collect(): Generator
    {
        $methods = Router::HTTP_METHODS;
        foreach ($methods as $method) {
            $routes = $this->route_collection->get_routes($method);
            foreach ($routes as $route => $handler) {
                // The route key should be a string, but it is stored as an array key,
                // it might be an integer.
                $route = (string) $route;
                if (is_string($handler) || $handler instanceof Closure) {
                    if ($handler instanceof Closure) {
                        $view = $this->route_collection->get_routes_options($route, $method)['view'] ?? false;
                        $handler = $view ? '(View) ' . $view : '(Closure)';
                    }
                    $route_name = $this->route_collection->get_routes_options($route, $method)['as'] ?? $route;
                    yield ['method' => $method, 'route' => $route, 'name' => $route_name, 'handler' => $handler];
                }
            }
        }
    }
}