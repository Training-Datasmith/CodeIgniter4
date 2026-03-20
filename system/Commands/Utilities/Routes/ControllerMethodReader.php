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

use ReflectionClass;
use ReflectionMethod;
/**
 * Reads a controller and returns a list of auto route listing.
 *
 * @see \CodeIgniter\Commands\Utilities\Routes\ControllerMethodReaderTest
 */
final readonly class Controller_Method_Reader
{
    /**
     * @param string $namespace the default namespace
     */
    public function __construct(private string $namespace)
    {
    }
    /**
     * @param class-string $class
     *
     * @return list<array{route: string, handler: string}>
     */
    public function read(string $class, string $default_controller = 'Home', string $default_method = 'index'): array
    {
        $reflection = new ReflectionClass($class);
        if ($reflection->is_abstract()) {
            return [];
        }
        $classname = $reflection->get_name();
        $class_shortname = $reflection->get_short_name();
        $output = [];
        $uri_by_class = $this->get_uri_by_class($classname);
        if ($this->has_remap($reflection)) {
            $method_name = '_remap';
            $route_without_controller = $this->get_route_without_controller($class_shortname, $default_controller, $uri_by_class, $classname, $method_name);
            $output = [...$output, ...$route_without_controller];
            $output[] = ['route' => $uri_by_class . '[/...]', 'handler' => '\\' . $classname . '::' . $method_name];
            return $output;
        }
        foreach ($reflection->get_methods(ReflectionMethod::IS_PUBLIC) as $method) {
            $method_name = $method->get_name();
            $route = $uri_by_class . '/' . $method_name;
            // Exclude BaseController and initController
            // See system/Config/Routes.php
            if (preg_match('#\AbaseController.*#', $route) === 1) {
                continue;
            }
            if (preg_match('#.*/initController\z#', $route) === 1) {
                continue;
            }
            if ($method_name === $default_method) {
                $route_without_controller = $this->get_route_without_controller($class_shortname, $default_controller, $uri_by_class, $classname, $method_name);
                $output = [...$output, ...$route_without_controller];
                $output[] = ['route' => $uri_by_class, 'handler' => '\\' . $classname . '::' . $method_name];
            }
            $output[] = ['route' => $route . '[/...]', 'handler' => '\\' . $classname . '::' . $method_name];
        }
        return $output;
    }
    /**
     * Whether the class has a _remap() method.
     */
    private function has_remap(ReflectionClass $class): bool
    {
        if ($class->has_method('_remap')) {
            $remap = $class->get_method('_remap');
            return $remap->is_public();
        }
        return false;
    }
    /**
     * @param class-string $classname
     *
     * @return string URI path part from the folder(s) and controller
     */
    private function get_uri_by_class(string $classname): string
    {
        // remove the namespace
        $pattern = '/' . preg_quote($this->namespace, '/') . '/';
        $class = ltrim(preg_replace($pattern, '', $classname), '\\');
        $class_parts = explode('\\', $class);
        $class_path = '';
        foreach ($class_parts as $part) {
            // make the first letter lowercase, because auto routing makes
            // the URI path's first letter uppercase and search the controller
            $class_path .= lcfirst($part) . '/';
        }
        return rtrim($class_path, '/');
    }
    /**
     * Gets a route without default controller.
     */
    private function get_route_without_controller(string $class_shortname, string $default_controller, string $uri_by_class, string $classname, string $method_name): array
    {
        if ($class_shortname !== $default_controller) {
            return [];
        }
        $pattern = '#' . preg_quote(lcfirst($default_controller), '#') . '\z#';
        $route_without_controller = rtrim(preg_replace($pattern, '', $uri_by_class), '/');
        $route_without_controller = $route_without_controller !== '' && $route_without_controller !== '0' ? $route_without_controller : '/';
        return [['route' => $route_without_controller, 'handler' => '\\' . $classname . '::' . $method_name]];
    }
}