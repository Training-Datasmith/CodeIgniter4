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
namespace Code_Igniter\Commands\Utilities\Routes\Auto_Router_Improved;

use Code_Igniter\Commands\Utilities\Routes\Controller_Finder;
use Code_Igniter\Commands\Utilities\Routes\Filter_Collector;
/**
 * Collects data for Auto Routing Improved.
 *
 * @see \CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\AutoRouteCollectorTest
 */
final readonly class Auto_Route_Collector
{
    /**
     * @param string             $namespace            namespace to search
     * @param list<class-string> $protectedControllers List of controllers in Defined
     *                                                 Routes that should not be accessed via Auto-Routing.
     * @param list<string>       $httpMethods
     * @param string             $prefix               URI prefix for Module Routing
     */
    public function __construct(private string $namespace, private string $default_controller, private string $default_method, private array $http_methods, private array $protected_controllers, private string $prefix = '')
    {
    }
    /**
     * @return list<list<string>>
     */
    public function get(): array
    {
        $finder = new Controller_Finder($this->namespace);
        $reader = new Controller_Method_Reader($this->namespace, $this->http_methods);
        $tbody = [];
        foreach ($finder->find() as $class) {
            // Exclude controllers in Defined Routes.
            if (in_array('\\' . $class, $this->protected_controllers, true)) {
                continue;
            }
            $routes = $reader->read($class, $this->default_controller, $this->default_method);
            if ($routes === []) {
                continue;
            }
            $routes = $this->add_filters($routes);
            foreach ($routes as $item) {
                $route = $item['route'] . $item['route_params'];
                // For module routing
                if ($this->prefix !== '' && $route === '/') {
                    $route = $this->prefix;
                } elseif ($this->prefix !== '') {
                    $route = $this->prefix . '/' . $route;
                }
                $tbody[] = [strtoupper($item['method']) . '(auto)', $route, '', $item['handler'], $item['before'], $item['after']];
            }
        }
        return $tbody;
    }
    /**
     * Adding Filters
     *
     * @param list<array<string, array|string>> $routes
     *
     * @return list<array<string, array|string>>
     */
    private function add_filters(array $routes): array
    {
        $filter_collector = new Filter_Collector(true);
        foreach ($routes as &$route) {
            $route_path = $route['route'];
            // For module routing
            if ($this->prefix !== '' && $route === '/') {
                $route_path = $this->prefix;
            } elseif ($this->prefix !== '') {
                $route_path = $this->prefix . '/' . $route_path;
            }
            // Search filters for the URI with all params
            $sample_uri = $this->generate_sample_uri($route);
            $filters_longest = $filter_collector->get($route['method'], $route_path . $sample_uri);
            // Search filters for the URI without optional params
            $sample_uri = $this->generate_sample_uri($route, false);
            $filters_shortest = $filter_collector->get($route['method'], $route_path . $sample_uri);
            // Get common array elements
            $filters = ['before' => array_intersect($filters_longest['before'], $filters_shortest['before']), 'after' => array_intersect($filters_longest['after'], $filters_shortest['after'])];
            $route['before'] = implode(' ', array_map(class_basename(...), $filters['before']));
            $route['after'] = implode(' ', array_map(class_basename(...), $filters['after']));
        }
        return $routes;
    }
    private function generate_sample_uri(array $route, bool $longest = true): string
    {
        $sample_uri = '';
        if (isset($route['params'])) {
            $i = 1;
            foreach ($route['params'] as $required) {
                if ($longest && !$required) {
                    $sample_uri .= '/' . $i++;
                }
            }
        }
        return $sample_uri;
    }
}