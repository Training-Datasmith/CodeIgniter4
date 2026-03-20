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
use Code_Igniter\Commands\Utilities\Routes\Filter_Collector;
/**
 * Check filters for a route.
 */
class Filter_Check extends Base_Command
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
    protected $name = 'filter:check';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Check filters for a route.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'filter:check <HTTP method> <route>';
    /**
     * the Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['method' => 'The HTTP method. GET, POST, PUT, etc.', 'route' => 'The route (URI path) to check filters.'];
    /**
     * the Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [];
    /**
     * @return int exit code
     */
    public function run(array $params)
    {
        if (!$this->check_params($params)) {
            return EXIT_ERROR;
        }
        $method = $params[0];
        $route = $params[1];
        // Load Routes
        service('routes')->load_routes();
        $filter_collector = new Filter_Collector();
        $filters = $filter_collector->get($method, $route);
        // PageNotFoundException
        if ($filters['before'] === ['<unknown>']) {
            CLI::error("Can't find a route: " . CLI::color('"' . strtoupper($method) . ' ' . $route . '"', 'black', 'light_gray'));
            return EXIT_ERROR;
        }
        $this->show_table($filter_collector, $filters, $method, $route);
        $this->show_filter_classes($filter_collector, $method, $route);
        return EXIT_SUCCESS;
    }
    /**
     * @param array<int|string, string|null> $params
     */
    private function check_params(array $params): bool
    {
        if (!isset($params[0], $params[1])) {
            CLI::error('You must specify a HTTP verb and a route.');
            CLI::write('  Usage: ' . $this->usage);
            CLI::write('Example: filter:check GET /');
            CLI::write('         filter:check PUT products/1');
            return false;
        }
        return true;
    }
    /**
     * @param array{before: list<string>, after: list<string>} $filters
     */
    private function show_table(Filter_Collector $filter_collector, array $filters, string $method, string $route): void
    {
        $thead = ['Method', 'Route', 'Before Filters', 'After Filters'];
        $required = $filter_collector->get_required_filters();
        $colored_required = $this->color_items($required);
        $before = array_merge($colored_required['before'], $filters['before']);
        $after = array_merge($filters['after'], $colored_required['after']);
        $tbody = [];
        $tbody[] = [strtoupper($method), $route, implode(' ', $before), implode(' ', $after)];
        CLI::table($tbody, $thead);
    }
    /**
     * Color all elements of the array.
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    private function color_items(array $array): array
    {
        return array_map(function ($item): array|string {
            if (is_array($item)) {
                return $this->color_items($item);
            }
            return CLI::color($item, 'yellow');
        }, $array);
    }
    private function show_filter_classes(Filter_Collector $filter_collector, string $method, string $route): void
    {
        $required_filter_classes = $filter_collector->get_required_filter_classes();
        $filter_classes = $filter_collector->get_classes($method, $route);
        $colored_required_filter_classes = $this->color_items($required_filter_classes);
        $class_list = ['before' => array_merge($colored_required_filter_classes['before'], $filter_classes['before']), 'after' => array_merge($filter_classes['after'], $colored_required_filter_classes['after'])];
        foreach ($class_list as $position => $classes) {
            CLI::write(ucfirst($position) . ' Filter Classes:', 'cyan');
            CLI::write(implode(' → ', $classes));
        }
    }
}