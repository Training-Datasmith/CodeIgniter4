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
namespace Code_Igniter\Commands\Utilities;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\Commands\Utilities\Routes\Auto_Route_Collector;
use Code_Igniter\Commands\Utilities\Routes\Auto_Router_Improved\Auto_Route_Collector as AutoRouteCollectorImproved;
use Code_Igniter\Commands\Utilities\Routes\Filter_Collector;
use Code_Igniter\Commands\Utilities\Routes\Sample_Uri_Generator;
use Code_Igniter\Router\Defined_Route_Collector;
use Code_Igniter\Router\Router;
use Config\Feature;
use Config\Routing;
/**
 * Lists all the routes. This will include any Routes files
 * that can be discovered, and will include routes that are not defined
 * in routes files, but are instead discovered through auto-routing.
 */
class Routes extends Base_Command
{
    /**
     * The group the command is lumped under
     * when listing commands.
     *
     * @var string
     */
    protected $group = 'CodeIgniter';
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'routes';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Displays all routes.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'routes';
    /**
     * the Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [];
    /**
     * the Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['-h' => 'Sort by Handler.', '--host' => 'Specify hostname in request URI.'];
    /**
     * Displays the help for the spark cli script itself.
     */
    public function run(array $params)
    {
        $sort_by_handler = array_key_exists('h', $params);
        $host = $params['host'] ?? null;
        // Set HTTP_HOST
        if ($host !== null) {
            service('superglobals')->set_server('HTTP_HOST', $host);
        }
        $collection = service('routes')->load_routes();
        // Reset HTTP_HOST
        if ($host !== null) {
            service('superglobals')->unset_server('HTTP_HOST');
        }
        $methods = Router::HTTP_METHODS;
        $tbody = [];
        $uri_generator = new Sample_Uri_Generator();
        $filter_collector = new Filter_Collector();
        $defined_route_collector = new Defined_Route_Collector($collection);
        foreach ($defined_route_collector->collect() as $route) {
            $sample_uri = $uri_generator->get($route['route']);
            $filters = $filter_collector->get($route['method'], $sample_uri);
            $route_name = $route['route'] === $route['name'] ? '»' : $route['name'];
            $tbody[] = [strtoupper($route['method']), $route['route'], $route_name, $route['handler'], implode(' ', array_map(class_basename(...), $filters['before'])), implode(' ', array_map(class_basename(...), $filters['after']))];
        }
        if ($collection->should_auto_route()) {
            $auto_routes_improved = config(Feature::class)->auto_routes_improved ?? false;
            if ($auto_routes_improved) {
                $auto_route_collector = new Auto_Route_Collector_Improved($collection->get_default_namespace(), $collection->get_default_controller(), $collection->get_default_method(), $methods, $collection->get_registered_controllers('*'));
                $auto_routes = $auto_route_collector->get();
                // Check for Module Routes.
                $routing_config = config(Routing::class);
                if ($routing_config instanceof Routing) {
                    foreach ($routing_config->module_routes as $uri => $namespace) {
                        $auto_route_collector = new Auto_Route_Collector_Improved($namespace, $collection->get_default_controller(), $collection->get_default_method(), $methods, $collection->get_registered_controllers('*'), $uri);
                        $auto_routes = [...$auto_routes, ...$auto_route_collector->get()];
                    }
                }
            } else {
                $auto_route_collector = new Auto_Route_Collector($collection->get_default_namespace(), $collection->get_default_controller(), $collection->get_default_method());
                $auto_routes = $auto_route_collector->get();
                foreach ($auto_routes as &$routes) {
                    // There is no `AUTO` method, but it is intentional not to get route filters.
                    $filters = $filter_collector->get('AUTO', $uri_generator->get($routes[1]));
                    $routes[] = implode(' ', array_map(class_basename(...), $filters['before']));
                    $routes[] = implode(' ', array_map(class_basename(...), $filters['after']));
                }
            }
            $tbody = [...$tbody, ...$auto_routes];
        }
        $thead = ['Method', 'Route', 'Name', $sort_by_handler ? 'Handler ↓' : 'Handler', 'Before Filters', 'After Filters'];
        // Sort by Handler.
        if ($sort_by_handler) {
            usort($tbody, static fn($handler1, $handler2): int => strcmp($handler1[3], $handler2[3]));
        }
        if ($host !== null) {
            CLI::write('Host: ' . $host);
        }
        CLI::table($tbody, $thead);
        $this->show_required_filters();
    }
    private function show_required_filters(): void
    {
        $filter_collector = new Filter_Collector();
        $required = $filter_collector->get_required_filters();
        $filters = [];
        foreach ($required['before'] as $filter) {
            $filters[] = CLI::color($filter, 'yellow');
        }
        CLI::write('Required Before Filters: ' . implode(', ', $filters));
        $filters = [];
        foreach ($required['after'] as $filter) {
            $filters[] = CLI::color($filter, 'yellow');
        }
        CLI::write(' Required After Filters: ' . implode(', ', $filters));
    }
}