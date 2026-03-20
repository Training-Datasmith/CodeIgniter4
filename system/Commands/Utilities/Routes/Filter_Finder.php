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

use Code_Igniter\Exceptions\Page_Not_Found_Exception;
use Code_Igniter\Filters\Filters;
use Code_Igniter\HTTP\Exceptions\Bad_Request_Exception;
use Code_Igniter\HTTP\Exceptions\Redirect_Exception;
use Code_Igniter\Router\Router;
use Config\Feature;
/**
 * Finds filters.
 *
 * @see \CodeIgniter\Commands\Utilities\Routes\FilterFinderTest
 */
final readonly class Filter_Finder
{
    private Router $router;
    private Filters $filters;
    public function __construct(?Router $router = null, ?Filters $filters = null)
    {
        $this->router = $router ?? service('router');
        $this->filters = $filters ?? service('filters');
    }
    private function get_route_filters(string $uri): array
    {
        $this->router->handle($uri);
        return $this->router->get_filters();
    }
    /**
     * @param string $uri URI path to find filters for
     *
     * @return array{before: list<string>, after: list<string>} array of alias/classname:args
     */
    public function find(string $uri): array
    {
        $this->filters->reset();
        try {
            // Add route filters
            $route_filters = $this->get_route_filters($uri);
            $this->filters->enable_filters($route_filters, 'before');
            $old_filter_order = config(Feature::class)->old_filter_order ?? false;
            if (!$old_filter_order) {
                $route_filters = array_reverse($route_filters);
            }
            $this->filters->enable_filters($route_filters, 'after');
            $this->filters->initialize($uri);
            return $this->filters->get_filters();
        } catch (Redirect_Exception) {
            return ['before' => [], 'after' => []];
        } catch (Bad_Request_Exception|Page_Not_Found_Exception) {
            return ['before' => ['<unknown>'], 'after' => ['<unknown>']];
        }
    }
    /**
     * @param string $uri URI path to find filters for
     *
     * @return array{before: list<string>, after: list<string>} array of classname:args
     */
    public function find_classes(string $uri): array
    {
        $this->filters->reset();
        try {
            // Add route filters
            $route_filters = $this->get_route_filters($uri);
            $this->filters->enable_filters($route_filters, 'before');
            $old_filter_order = config(Feature::class)->old_filter_order ?? false;
            if (!$old_filter_order) {
                $route_filters = array_reverse($route_filters);
            }
            $this->filters->enable_filters($route_filters, 'after');
            $this->filters->initialize($uri);
            $filter_class_list = $this->filters->get_filters_class();
            $filter_classes = ['before' => [], 'after' => []];
            foreach ($filter_class_list['before'] as $class_info) {
                $class_with_arguments = $class_info[1] === [] ? $class_info[0] : $class_info[0] . ':' . implode(',', $class_info[1]);
                $filter_classes['before'][] = $class_with_arguments;
            }
            foreach ($filter_class_list['after'] as $class_info) {
                $class_with_arguments = $class_info[1] === [] ? $class_info[0] : $class_info[0] . ':' . implode(',', $class_info[1]);
                $filter_classes['after'][] = $class_with_arguments;
            }
            return $filter_classes;
        } catch (Redirect_Exception) {
            return ['before' => [], 'after' => []];
        } catch (Bad_Request_Exception|Page_Not_Found_Exception) {
            return ['before' => ['<unknown>'], 'after' => ['<unknown>']];
        }
    }
    /**
     * Returns Required Filters
     *
     * @return array{before: list<string>, after:list<string>} array of aliases
     */
    public function get_required_filters(): array
    {
        [$required_before] = $this->filters->get_required_filters('before');
        [$required_after] = $this->filters->get_required_filters('after');
        return ['before' => $required_before, 'after' => $required_after];
    }
    /**
     * Returns Required Filter classes
     *
     * @return array{before: list<string>, after:list<string>}
     */
    public function get_required_filter_classes(): array
    {
        $before = $this->filters->get_required_classes('before');
        $after = $this->filters->get_required_classes('after');
        $required_before = [];
        $required_after = [];
        foreach ($before as $class_info) {
            $required_before[] = $class_info[0];
        }
        foreach ($after as $class_info) {
            $required_after[] = $class_info[0];
        }
        return ['before' => $required_before, 'after' => $required_after];
    }
}