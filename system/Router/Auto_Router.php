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
use Code_Igniter\Exceptions\Page_Not_Found_Exception;
use Code_Igniter\HTTP\Response_Interface;
/**
 * Router for Auto-Routing
 */
final class Auto_Router implements Auto_Router_Interface
{
    /**
     * Sub-directory that contains the requested controller class.
     * Primarily used by 'autoRoute'.
     */
    private ?string $directory = null;
    public function __construct(
        /**
         * List of CLI routes that do not contain '*' routes.
         *
         * @var array<string, (Closure(mixed...): (ResponseInterface|string|void))|string> [routeKey => handler]
         */
        private readonly array $cli_routes,
        /**
         * Default namespace for controllers.
         */
        private readonly string $default_namespace,
        /**
         * The name of the controller class.
         */
        private string $controller,
        /**
         * The name of the method to use.
         */
        private string $method,
        /**
         * Whether dashes in URI's should be converted
         * to underscores when determining method names.
         */
        private bool $translate_uri_dashes
    )
    {
    }
    /**
     * Attempts to match a URI path against Controllers and directories
     * found in APPPATH/Controllers, to find a matching route.
     *
     * @param string $httpVerb HTTP verb like `GET`,`POST`
     *
     * @return array [directory_name, controller_name, controller_method, params]
     */
    public function get_route(string $uri, string $http_verb): array
    {
        $segments = explode('/', $uri);
        // WARNING: Directories get shifted out of the segments array.
        $segments = $this->scan_controllers($segments);
        // If we don't have any segments left - use the default controller;
        // If not empty, then the first segment should be the controller
        if ($segments !== []) {
            $this->controller = ucfirst(array_shift($segments));
        }
        $controller_name = $this->controller_name();
        if (!$this->is_valid_segment($controller_name)) {
            throw new Page_Not_Found_Exception($this->controller . ' is not a valid controller name');
        }
        // Use the method name if it exists.
        // If it doesn't, no biggie - the default method name
        // has already been set.
        if ($segments !== []) {
            $this->method = array_shift($segments) ?: $this->method;
        }
        // Prevent access to initController method
        if (strtolower($this->method) === 'initcontroller') {
            throw Page_Not_Found_Exception::for_page_not_found();
        }
        /** @var array $params An array of params to the controller method. */
        $params = [];
        if ($segments !== []) {
            $params = $segments;
        }
        // Ensure routes registered via $routes->cli() are not accessible via web.
        if ($http_verb !== 'CLI') {
            $controller = '\\' . $this->default_namespace;
            $controller .= $this->directory !== null ? str_replace('/', '\\', $this->directory) : '';
            $controller .= $controller_name;
            $controller = strtolower($controller);
            $method_name = strtolower($this->method_name());
            foreach ($this->cli_routes as $handler) {
                if (is_string($handler)) {
                    $handler = strtolower($handler);
                    // Like $routes->cli('hello/(:segment)', 'Home::$1')
                    if (str_contains($handler, '::$')) {
                        throw new Page_Not_Found_Exception('Cannot access CLI Route: ' . $uri);
                    }
                    if (str_starts_with($handler, $controller . '::' . $method_name)) {
                        throw new Page_Not_Found_Exception('Cannot access CLI Route: ' . $uri);
                    }
                    if ($handler === $controller) {
                        throw new Page_Not_Found_Exception('Cannot access CLI Route: ' . $uri);
                    }
                }
            }
        }
        // Load the file so that it's available for CodeIgniter.
        $file = APPPATH . 'Controllers/' . $this->directory . $controller_name . '.php';
        if (!is_file($file)) {
            throw Page_Not_Found_Exception::for_controller_not_found($this->controller, $this->method);
        }
        include_once $file;
        // Ensure the controller stores the fully-qualified class name
        // We have to check for a length over 1, since by default it will be '\'
        if (!str_contains($this->controller, '\\') && strlen($this->default_namespace) > 1) {
            $this->controller = '\\' . ltrim(str_replace('/', '\\', $this->default_namespace . $this->directory . $controller_name), '\\');
        }
        return [$this->directory, $this->controller_name(), $this->method_name(), $params];
    }
    /**
     * Tells the system whether we should translate URI dashes or not
     * in the URI from a dash to an underscore.
     *
     * @deprecated This method should be removed.
     */
    public function set_translate_uri_dashes(bool $val = false): self
    {
        $this->translate_uri_dashes = $val;
        return $this;
    }
    /**
     * Scans the controller directory, attempting to locate a controller matching the supplied uri $segments
     *
     * @param array $segments URI segments
     *
     * @return array returns an array of remaining uri segments that don't map onto a directory
     */
    private function scan_controllers(array $segments): array
    {
        $segments = array_filter($segments, static fn($segment): bool => $segment !== '');
        // numerically reindex the array, removing gaps
        $segments = array_values($segments);
        // if a prior directory value has been set, just return segments and get out of here
        if (isset($this->directory)) {
            return $segments;
        }
        // Loop through our segments and return as soon as a controller
        // is found or when such a directory doesn't exist
        $c = count($segments);
        while ($c-- > 0) {
            $segment_convert = ucfirst($this->translate_uri_dashes ? str_replace('-', '_', $segments[0]) : $segments[0]);
            // as soon as we encounter any segment that is not PSR-4 compliant, stop searching
            if (!$this->is_valid_segment($segment_convert)) {
                return $segments;
            }
            $test = APPPATH . 'Controllers/' . $this->directory . $segment_convert;
            // as long as each segment is *not* a controller file but does match a directory, add it to $this->directory
            if (!is_file($test . '.php') && is_dir($test)) {
                $this->set_directory($segment_convert, true, false);
                array_shift($segments);
                continue;
            }
            return $segments;
        }
        // This means that all segments were actually directories
        return $segments;
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
     * Sets the sub-directory that the controller is in.
     *
     * @param bool $validate if true, checks to make sure $dir consists of only PSR4 compliant segments
     *
     * @deprecated This method should be removed.
     *
     * @return void
     */
    public function set_directory(?string $dir = null, bool $append = false, bool $validate = true)
    {
        if ((string) $dir === '') {
            $this->directory = null;
            return;
        }
        if ($validate) {
            $segments = explode('/', trim($dir, '/'));
            foreach ($segments as $segment) {
                if (!$this->is_valid_segment($segment)) {
                    return;
                }
            }
        }
        if (!$append || (string) $this->directory === '') {
            $this->directory = trim($dir, '/') . '/';
        } else {
            $this->directory .= trim($dir, '/') . '/';
        }
    }
    /**
     * Returns the name of the sub-directory the controller is in,
     * if any. Relative to APPPATH.'Controllers'.
     *
     * @deprecated This method should be removed.
     */
    public function directory(): string
    {
        return (string) $this->directory !== '' ? $this->directory : '';
    }
    private function controller_name(): string
    {
        return $this->translate_uri_dashes ? str_replace('-', '_', $this->controller) : $this->controller;
    }
    private function method_name(): string
    {
        return $this->translate_uri_dashes ? str_replace('-', '_', $this->method) : $this->method;
    }
}