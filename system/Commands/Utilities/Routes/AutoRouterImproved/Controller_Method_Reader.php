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

use Config\Routing;
use ReflectionClass;
use ReflectionMethod;
/**
 * Reads a controller and returns a list of auto route listing.
 *
 * @see \CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\ControllerMethodReaderTest
 */
final readonly class Controller_Method_Reader
{
    private bool $translate_uri_dashes;
    private bool $translate_uri_to_camel_case;
    /**
     * @param string       $namespace   the default namespace
     * @param list<string> $httpMethods
     */
    public function __construct(private string $namespace, private array $http_methods)
    {
        $config = config(Routing::class);
        $this->translate_uri_dashes = $config->translate_uri_dashes;
        $this->translate_uri_to_camel_case = $config->translate_uri_to_camel_case;
    }
    /**
     * Returns found route info in the controller.
     *
     * @param class-string $class
     *
     * @return list<array<string, array|string>>
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
        $class_in_uri = $this->convert_class_name_to_uri($classname);
        foreach ($reflection->get_methods(ReflectionMethod::IS_PUBLIC) as $method) {
            $method_name = $method->get_name();
            foreach ($this->http_methods as $http_verb) {
                if (str_starts_with($method_name, strtolower($http_verb))) {
                    // Remove HTTP verb prefix.
                    $method_in_uri = $this->convert_method_name_to_uri($http_verb, $method_name);
                    // Check if it is the default method.
                    if ($method_in_uri === $default_method) {
                        $route_for_default_controller = $this->get_route_for_default_controller($class_shortname, $default_controller, $class_in_uri, $classname, $method_name, $http_verb, $method);
                        if ($route_for_default_controller !== []) {
                            // The controller is the default controller. It only
                            // has a route for the default method. Other methods
                            // will not be routed even if they exist.
                            $output = [...$output, ...$route_for_default_controller];
                            continue;
                        }
                        [$params, $route_params] = $this->get_parameters($method);
                        // Route for the default method.
                        $output[] = ['method' => $http_verb, 'route' => $class_in_uri, 'route_params' => $route_params, 'handler' => '\\' . $classname . '::' . $method_name, 'params' => $params];
                        continue;
                    }
                    $route = $class_in_uri . '/' . $method_in_uri;
                    [$params, $route_params] = $this->get_parameters($method);
                    // If it is the default controller, the method will not be
                    // routed.
                    if ($class_shortname === $default_controller) {
                        $route = 'x ' . $route;
                    }
                    $output[] = ['method' => $http_verb, 'route' => $route, 'route_params' => $route_params, 'handler' => '\\' . $classname . '::' . $method_name, 'params' => $params];
                }
            }
        }
        return $output;
    }
    private function get_parameters(ReflectionMethod $method): array
    {
        $params = [];
        $route_params = '';
        $ref_params = $method->get_parameters();
        foreach ($ref_params as $param) {
            $required = true;
            if ($param->is_optional()) {
                $required = false;
                $route_params .= '[/..]';
            } else {
                $route_params .= '/..';
            }
            // [variable_name => required?]
            $params[$param->get_name()] = $required;
        }
        return [$params, $route_params];
    }
    /**
     * @param class-string $classname
     *
     * @return string URI path part from the folder(s) and controller
     */
    private function convert_class_name_to_uri(string $classname): string
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
        $class_uri = rtrim($class_path, '/');
        return $this->translate_to_uri($class_uri);
    }
    /**
     * @return string URI path part from the method
     */
    private function convert_method_name_to_uri(string $http_verb, string $method_name): string
    {
        $method_uri = lcfirst(substr($method_name, strlen($http_verb)));
        return $this->translate_to_uri($method_uri);
    }
    /**
     * @param string $string classname or method name
     */
    private function translate_to_uri(string $string): string
    {
        if ($this->translate_uri_to_camel_case) {
            $string = strtolower(preg_replace('/([a-z\d])([A-Z])/', '$1-$2', $string));
        } elseif ($this->translate_uri_dashes) {
            $string = str_replace('_', '-', $string);
        }
        return $string;
    }
    /**
     * Gets a route for the default controller.
     *
     * @return list<array>
     */
    private function get_route_for_default_controller(string $class_shortname, string $default_controller, string $uri_by_class, string $classname, string $method_name, string $http_verb, ReflectionMethod $method): array
    {
        $output = [];
        if ($class_shortname === $default_controller) {
            $pattern = '#' . preg_quote(lcfirst($default_controller), '#') . '\z#';
            $route_without_controller = rtrim(preg_replace($pattern, '', $uri_by_class), '/');
            $route_without_controller = $route_without_controller !== '' && $route_without_controller !== '0' ? $route_without_controller : '/';
            [$params, $route_params] = $this->get_parameters($method);
            if ($route_without_controller === '/' && $route_params !== '') {
                $route_without_controller = '';
            }
            $output[] = ['method' => $http_verb, 'route' => $route_without_controller, 'route_params' => $route_params, 'handler' => '\\' . $classname . '::' . $method_name, 'params' => $params];
        }
        return $output;
    }
}