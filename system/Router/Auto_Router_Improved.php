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

use Code_Igniter\Exceptions\Page_Not_Found_Exception;
use Code_Igniter\Router\Exceptions\Method_Not_Found_Exception;
use Config\Routing;
use ReflectionClass;
use Reflection_Exception;
/**
 * New Secure Router for Auto-Routing
 *
 * @see \CodeIgniter\Router\AutoRouterImprovedTest
 */
final class Auto_Router_Improved implements Auto_Router_Interface
{
    /**
     * Sub-directory that contains the requested controller class.
     */
    private ?string $directory = null;
    /**
     * The name of the controller class.
     */
    private string $controller;
    /**
     * The name of the method to use.
     */
    private string $method;
    /**
     * An array of params to the controller method.
     *
     * @var list<string>
     */
    private array $params = [];
    /**
     *  Whether to translate dashes in URIs for controller/method to CamelCase.
     *  E.g., blog-controller -> BlogController
     */
    private readonly bool $translate_uri_to_camel_case;
    /**
     * The namespace for controllers.
     */
    private string $namespace;
    /**
     * Map of URI segments and namespaces.
     *
     * The key is the first URI segment. The value is the controller namespace.
     * E.g.,
     *   [
     *       'blog' => 'Acme\Blog\Controllers',
     *   ]
     *
     * @var array [ uri_segment => namespace ]
     */
    private array $module_routes;
    /**
     * The URI segments.
     *
     * @var list<string>
     */
    private array $segments = [];
    /**
     * The position of the Controller in the URI segments.
     * Null for the default controller.
     */
    private ?int $controller_pos = null;
    /**
     * The position of the Method in the URI segments.
     * Null for the default method.
     */
    private ?int $method_pos = null;
    /**
     * The position of the first Parameter in the URI segments.
     * Null for the no parameters.
     */
    private ?int $param_pos = null;
    /**
     * The current URI
     */
    private ?string $uri = null;
    /**
     * @param list<class-string> $protectedControllers
     * @param string             $defaultController    Short classname
     */
    public function __construct(
        /**
         * List of controllers in Defined Routes that should not be accessed via this Auto-Routing.
         */
        private readonly array $protected_controllers,
        string $namespace,
        private readonly string $default_controller,
        /**
         * The name of the default method without HTTP verb prefix.
         */
        private readonly string $default_method,
        /**
         * Whether dashes in URI's should be converted
         * to underscores when determining method names.
         */
        private readonly bool $translate_uri_dashes
    )
    {
        $this->namespace = rtrim($namespace, '\\');
        $routing_config = config(Routing::class);
        $this->module_routes = $routing_config->module_routes;
        $this->translate_uri_to_camel_case = $routing_config->translate_uri_to_camel_case;
        // Set the default values
        $this->controller = $this->default_controller;
    }
    private function create_segments(string $uri): array
    {
        $segments = explode('/', $uri);
        $segments = array_filter($segments, static fn($segment): bool => $segment !== '');
        // numerically reindex the array, removing gaps
        return array_values($segments);
    }
    /**
     * Search for the first controller corresponding to the URI segment.
     *
     * If there is a controller corresponding to the first segment, the search
     * ends there. The remaining segments are parameters to the controller.
     *
     * @return bool true if a controller class is found.
     */
    private function search_first_controller(): bool
    {
        $segments = $this->segments;
        $controller = '\\' . $this->namespace;
        $controller_pos = -1;
        while ($segments !== []) {
            $segment = array_shift($segments);
            $controller_pos++;
            $class = $this->translate_uri($segment);
            // as soon as we encounter any segment that is not PSR-4 compliant, stop searching
            if (!$this->is_valid_segment($class)) {
                return false;
            }
            $controller .= '\\' . $class;
            if (class_exists($controller)) {
                $this->controller = $controller;
                $this->controller_pos = $controller_pos;
                $this->check_uri_for_controller($controller);
                // The first item may be a method name.
                $this->params = $segments;
                if ($segments !== []) {
                    $this->param_pos = $this->controller_pos + 1;
                }
                return true;
            }
        }
        return false;
    }
    /**
     * Search for the last default controller corresponding to the URI segments.
     *
     * @return bool true if a controller class is found.
     */
    private function search_last_default_controller(): bool
    {
        $segments = $this->segments;
        $segment_count = count($this->segments);
        $param_pos = null;
        $params = [];
        while ($segments !== []) {
            if ($segment_count > count($segments)) {
                $param_pos = count($segments);
            }
            $namespaces = array_map($this->translate_uri(...), $segments);
            $controller = '\\' . $this->namespace . '\\' . implode('\\', $namespaces) . '\\' . $this->default_controller;
            if (class_exists($controller)) {
                $this->controller = $controller;
                $this->params = $params;
                if ($params !== []) {
                    $this->param_pos = $param_pos;
                }
                return true;
            }
            // Prepend the last element in $segments to the beginning of $params.
            array_unshift($params, array_pop($segments));
        }
        // Check for the default controller in Controllers directory.
        $controller = '\\' . $this->namespace . '\\' . $this->default_controller;
        if (class_exists($controller)) {
            $this->controller = $controller;
            $this->params = $params;
            if ($params !== []) {
                $this->param_pos = 0;
            }
            return true;
        }
        return false;
    }
    /**
     * Finds controller, method and params from the URI.
     *
     * @param string $httpVerb HTTP verb like `GET`,`POST`
     *
     * @return array [directory_name, controller_name, controller_method, params]
     */
    public function get_route(string $uri, string $http_verb): array
    {
        $this->uri = $uri;
        $http_verb = strtolower($http_verb);
        // Reset Controller method params.
        $this->params = [];
        $default_method = $http_verb . '_' . $this->default_method;
        $this->method = $default_method;
        $this->segments = $this->create_segments($uri);
        // Check for Module Routes.
        if ($this->segments !== [] && array_key_exists($this->segments[0], $this->module_routes)) {
            $uri_segment = array_shift($this->segments);
            $this->namespace = rtrim($this->module_routes[$uri_segment], '\\');
        }
        if ($this->search_first_controller()) {
            // Controller is found.
            $base_controller_name = class_basename($this->controller);
            // Prevent access to default controller path
            if (strtolower($base_controller_name) === strtolower($this->default_controller)) {
                throw new Page_Not_Found_Exception('Cannot access the default controller "' . $this->controller . '" with the controller name URI path.');
            }
        } elseif ($this->search_last_default_controller()) {
            // The default Controller is found.
            $base_controller_name = class_basename($this->controller);
        } else {
            // No Controller is found.
            throw new Page_Not_Found_Exception('No controller is found for: ' . $uri);
        }
        // The first item may be a method name.
        /** @var list<string> $params */
        $params = $this->params;
        $method_param = array_shift($params);
        $method = '';
        if ($method_param !== null) {
            $method = $http_verb . '_' . $this->translate_uri($method_param);
            $this->check_uri_for_method($method);
        }
        if ($method_param !== null && method_exists($this->controller, $method)) {
            // Method is found.
            $this->method = $method;
            $this->params = $params;
            // Update the positions.
            $this->method_pos = $this->param_pos;
            if ($params === []) {
                $this->param_pos = null;
            }
            if ($this->param_pos !== null) {
                $this->param_pos++;
            }
            // Prevent access to default controller's method
            if (strtolower($base_controller_name) === strtolower($this->default_controller)) {
                throw new Page_Not_Found_Exception('Cannot access the default controller "' . $this->controller . '::' . $this->method . '"');
            }
            // Prevent access to default method path
            if (strtolower($this->method) === strtolower($default_method)) {
                throw new Page_Not_Found_Exception('Cannot access the default method "' . $this->method . '" with the method name URI path.');
            }
        } elseif (method_exists($this->controller, $default_method)) {
            // The default method is found.
            $this->method = $default_method;
        } else {
            // No method is found.
            throw Page_Not_Found_Exception::for_controller_not_found($this->controller, $method);
        }
        // Ensure the controller is not defined in routes.
        $this->protect_defined_routes();
        // Ensure the controller does not have _remap() method.
        $this->check_remap();
        // Ensure the URI segments for the controller and method do not contain
        // underscores when $translateURIDashes is true.
        $this->check_underscore();
        // Check parameter count
        try {
            $this->check_parameters();
        } catch (Method_Not_Found_Exception) {
            throw Page_Not_Found_Exception::for_controller_not_found($this->controller, $this->method);
        }
        $this->set_directory();
        return [$this->directory, $this->controller, $this->method, $this->params];
    }
    /**
     * @internal For test purpose only.
     *
     * @return array<string, int|null>
     */
    public function get_pos(): array
    {
        return ['controller' => $this->controller_pos, 'method' => $this->method_pos, 'params' => $this->param_pos];
    }
    /**
     * Get the directory path from the controller and set it to the property.
     *
     * @return void
     */
    private function set_directory()
    {
        $segments = explode('\\', trim($this->controller, '\\'));
        // Remove short classname.
        array_pop($segments);
        $namespaces = implode('\\', $segments);
        $dir = str_replace('\\', '/', ltrim(substr($namespaces, strlen($this->namespace)), '\\'));
        if ($dir !== '') {
            $this->directory = $dir . '/';
        }
    }
    private function protect_defined_routes(): void
    {
        $controller = strtolower($this->controller);
        foreach ($this->protected_controllers as $controller_in_routes) {
            $route_lower_case = strtolower($controller_in_routes);
            if ($route_lower_case === $controller) {
                throw new Page_Not_Found_Exception('Cannot access the controller in Defined Routes. Controller: ' . $controller_in_routes);
            }
        }
    }
    private function check_parameters(): void
    {
        try {
            $ref_class = new ReflectionClass($this->controller);
        } catch (Reflection_Exception) {
            throw Page_Not_Found_Exception::for_controller_not_found($this->controller, $this->method);
        }
        try {
            $ref_method = $ref_class->get_method($this->method);
            $ref_params = $ref_method->get_parameters();
        } catch (Reflection_Exception) {
            throw new Method_Not_Found_Exception();
        }
        if (!$ref_method->is_public()) {
            throw new Method_Not_Found_Exception();
        }
        if (count($ref_params) < count($this->params)) {
            throw new Page_Not_Found_Exception('The param count in the URI are greater than the controller method params.' . ' Handler:' . $this->controller . '::' . $this->method . ', URI:' . $this->uri);
        }
    }
    private function check_remap(): void
    {
        try {
            $ref_class = new ReflectionClass($this->controller);
            $ref_class->get_method('_remap');
            throw new Page_Not_Found_Exception('AutoRouterImproved does not support `_remap()` method.' . ' Controller:' . $this->controller);
        } catch (Reflection_Exception) {
            // Do nothing.
        }
    }
    private function check_underscore(): void
    {
        if ($this->translate_uri_dashes === false) {
            return;
        }
        $param_pos = $this->param_pos ?? count($this->segments);
        for ($i = 0; $i < $param_pos; $i++) {
            if (str_contains($this->segments[$i], '_')) {
                throw new Page_Not_Found_Exception('AutoRouterImproved prohibits access to the URI' . ' containing underscores ("' . $this->segments[$i] . '")' . ' when $translateURIDashes is enabled.' . ' Please use the dash.' . ' Handler:' . $this->controller . '::' . $this->method . ', URI:' . $this->uri);
            }
        }
    }
    /**
     * Check URI for controller for $translateUriToCamelCase
     *
     * @param string $classname Controller classname that is generated from URI.
     *                          The case may be a bit incorrect.
     */
    private function check_uri_for_controller(string $classname): void
    {
        if ($this->translate_uri_to_camel_case === false) {
            return;
        }
        if (!in_array(ltrim($classname, '\\'), get_declared_classes(), true)) {
            throw new Page_Not_Found_Exception('"' . $classname . '" is not found.');
        }
    }
    /**
     * Check URI for method for $translateUriToCamelCase
     *
     * @param string $method Controller method name that is generated from URI.
     *                       The case may be a bit incorrect.
     */
    private function check_uri_for_method(string $method): void
    {
        if ($this->translate_uri_to_camel_case === false) {
            return;
        }
        if (method_exists($this->controller, $method) && !in_array($method, get_class_methods($this->controller), true)) {
            throw new Page_Not_Found_Exception('"' . $this->controller . '::' . $method . '()" is not found.');
        }
    }
    /**
     * Returns true if the supplied $segment string represents a valid PSR-4 compliant namespace/directory segment
     *
     * regex comes from https://www.php.net/manual/en/language.variables.basics.php
     */
    private function is_valid_segment(string $segment): bool
    {
        return (bool) preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $segment);
    }
    /**
     * Translates URI segment to CamelCase or replaces `-` with `_`.
     */
    private function translate_uri(string $segment): string
    {
        if ($this->translate_uri_to_camel_case) {
            if (strtolower($segment) !== $segment) {
                throw new Page_Not_Found_Exception('AutoRouterImproved prohibits access to the URI' . ' containing uppercase letters ("' . $segment . '")' . ' when $translateUriToCamelCase is enabled.' . ' Please use the dash.' . ' URI:' . $this->uri);
            }
            if (str_contains($segment, '--')) {
                throw new Page_Not_Found_Exception('AutoRouterImproved prohibits access to the URI' . ' containing double dash ("' . $segment . '")' . ' when $translateUriToCamelCase is enabled.' . ' Please use the single dash.' . ' URI:' . $this->uri);
            }
            return str_replace(' ', '', ucwords(preg_replace('/[\-]+/', ' ', $segment)));
        }
        $segment = ucfirst($segment);
        if ($this->translate_uri_dashes) {
            return str_replace('-', '_', $segment);
        }
        return $segment;
    }
}