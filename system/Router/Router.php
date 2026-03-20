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
use Code_Igniter\HTTP\Exceptions\Bad_Request_Exception;
use Code_Igniter\HTTP\Exceptions\Redirect_Exception;
use Code_Igniter\HTTP\Method;
use Code_Igniter\HTTP\Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\Router\Attributes\Filter;
use Code_Igniter\Router\Attributes\Route_Attribute_Interface;
use Code_Igniter\Router\Exceptions\Router_Exception;
use Config\App;
use Config\Feature;
use Config\Routing;
use ReflectionClass;
use Throwable;
/**
 * Request router.
 *
 * @see \CodeIgniter\Router\RouterTest
 */
class Router implements Router_Interface
{
    /**
     * List of allowed HTTP methods (and CLI for command line use).
     */
    public const HTTP_METHODS = [Method::GET, Method::HEAD, Method::POST, Method::PATCH, Method::PUT, Method::DELETE, Method::OPTIONS, Method::TRACE, Method::CONNECT, 'CLI'];
    /**
     * A RouteCollection instance.
     *
     * @var RouteCollectionInterface
     */
    protected $collection;
    /**
     * Sub-directory that contains the requested controller class.
     * Primarily used by 'autoRoute'.
     *
     * @var string|null
     */
    protected $directory;
    /**
     * The name of the controller class.
     *
     * @var (Closure(mixed...): (ResponseInterface|string|void))|string
     */
    protected $controller;
    /**
     * The name of the method to use.
     *
     * @var string
     */
    protected $method;
    /**
     * An array of binds that were collected
     * so they can be sent to closure routes.
     *
     * @var array
     */
    protected $params = [];
    /**
     * The name of the front controller.
     *
     * @var string
     */
    protected $index_page = 'index.php';
    /**
     * Whether dashes in URI's should be converted
     * to underscores when determining method names.
     *
     * @var bool
     */
    protected $translate_uri_dashes = false;
    /**
     * The route that was matched for this request.
     *
     * @var array|null
     */
    protected $matched_route;
    /**
     * The options set for the matched route.
     *
     * @var array|null
     */
    protected $matched_route_options;
    /**
     * The locale that was detected in a route.
     *
     * @var string
     */
    protected $detected_locale;
    /**
     * The filter info from Route Collection
     * if the matched route should be filtered.
     *
     * @var list<string>
     */
    protected $filters_info = [];
    protected ?Auto_Router_Interface $auto_router = null;
    /**
     * Route attributes collected during routing for the current route.
     *
     * @var array{class: list<RouteAttributeInterface>, method: list<RouteAttributeInterface>}
     */
    protected array $route_attributes = ['class' => [], 'method' => []];
    /**
     * Permitted URI chars
     *
     * The default value is `''` (do not check) for backward compatibility.
     */
    protected string $permitted_uri_chars = '';
    /**
     * Stores a reference to the RouteCollection object.
     */
    public function __construct(Route_Collection_Interface $routes, ?Request $request = null)
    {
        $config = config(App::class);
        if (isset($config->permitted_uri_chars)) {
            $this->permitted_uri_chars = $config->permitted_uri_chars;
        }
        $this->collection = $routes;
        // These are only for auto-routing
        $this->controller = $this->collection->get_default_controller();
        $this->method = $this->collection->get_default_method();
        $this->collection->set_http_verb($request->get_method() === '' ? service('superglobals')->server('REQUEST_METHOD') : $request->get_method());
        $this->translate_uri_dashes = $this->collection->should_translate_uri_dashes();
        if ($this->collection->should_auto_route()) {
            $auto_routes_improved = config(Feature::class)->auto_routes_improved ?? false;
            if ($auto_routes_improved) {
                assert($this->collection instanceof Route_Collection);
                $this->auto_router = new Auto_Router_Improved($this->collection->get_registered_controllers('*'), $this->collection->get_default_namespace(), $this->collection->get_default_controller(), $this->collection->get_default_method(), $this->translate_uri_dashes);
            } else {
                $this->auto_router = new Auto_Router($this->collection->get_routes('CLI', false), $this->collection->get_default_namespace(), $this->collection->get_default_controller(), $this->collection->get_default_method(), $this->translate_uri_dashes);
            }
        }
    }
    /**
     * Finds the controller corresponding to the URI.
     *
     * @param string|null $uri URI path relative to baseURL
     *
     * @return (Closure(mixed...): (ResponseInterface|string|void))|string Controller classname or Closure
     *
     * @throws BadRequestException
     * @throws PageNotFoundException
     * @throws RedirectException
     */
    public function handle(?string $uri = null)
    {
        // If we cannot find a URI to match against, then set it to root (`/`).
        if ($uri === null || $uri === '') {
            $uri = '/';
        }
        // Decode URL-encoded string
        $uri = urldecode($uri);
        $this->check_disallowed_chars($uri);
        // Restart filterInfo
        $this->filters_info = [];
        // Checks defined routes
        if ($this->check_routes($uri)) {
            if ($this->collection->is_filtered($this->matched_route[0])) {
                $this->filters_info = $this->collection->get_filters_for_route($this->matched_route[0]);
            }
            $this->process_route_attributes();
            return $this->controller;
        }
        // Still here? Then we can try to match the URI against
        // Controllers/directories, but the application may not
        // want this, like in the case of API's.
        if (!$this->collection->should_auto_route()) {
            throw new Page_Not_Found_Exception("Can't find a route for '{$this->collection->get_http_verb()}: {$uri}'.");
        }
        // Checks auto routes
        $this->auto_route($uri);
        $this->process_route_attributes();
        return $this->controller_name();
    }
    /**
     * Returns the filter info for the matched route, if any.
     *
     * @return list<string>
     */
    public function get_filters(): array
    {
        $filters = $this->filters_info;
        // Check for attribute-based filters
        foreach ($this->route_attributes as $attributes) {
            foreach ($attributes as $attribute) {
                if ($attribute instanceof Filter) {
                    $filters = array_merge($filters, $attribute->get_filters());
                }
            }
        }
        return $filters;
    }
    /**
     * Returns the name of the matched controller or closure.
     *
     * @return (Closure(mixed...): (ResponseInterface|string|void))|string Controller classname or Closure
     */
    public function controller_name()
    {
        return $this->translate_uri_dashes && !$this->controller instanceof Closure ? str_replace('-', '_', $this->controller) : $this->controller;
    }
    /**
     * Returns the name of the method to run in the
     * chosen controller.
     */
    public function method_name(): string
    {
        return $this->translate_uri_dashes ? str_replace('-', '_', $this->method) : $this->method;
    }
    /**
     * Returns the 404 Override settings from the Collection.
     * If the override is a string, will split to controller/index array.
     *
     * @return array{string, string}|(Closure(string): (ResponseInterface|string|void))|null
     */
    public function get404Override()
    {
        $route = $this->collection->get404Override();
        if (is_string($route)) {
            $route_array = explode('::', $route);
            return [
                $route_array[0],
                // Controller
                $route_array[1] ?? 'index',
            ];
        }
        if (is_callable($route)) {
            return $route;
        }
        return null;
    }
    /**
     * Returns the binds that have been matched and collected
     * during the parsing process as an array, ready to send to
     * instance->method(...$params).
     */
    public function params(): array
    {
        return $this->params;
    }
    /**
     * Returns the name of the sub-directory the controller is in,
     * if any. Relative to APPPATH.'Controllers'.
     *
     * Only used when auto-routing is turned on.
     */
    public function directory(): string
    {
        if ($this->auto_router instanceof Auto_Router) {
            return $this->auto_router->directory();
        }
        return '';
    }
    /**
     * Returns the routing information that was matched for this
     * request, if a route was defined.
     *
     * @return array|null
     */
    public function get_matched_route()
    {
        return $this->matched_route;
    }
    /**
     * Returns all options set for the matched route
     *
     * @return array|null
     */
    public function get_matched_route_options()
    {
        return $this->matched_route_options;
    }
    /**
     * Sets the value that should be used to match the index.php file. Defaults
     * to index.php but this allows you to modify it in case you are using
     * something like mod_rewrite to remove the page. This allows you to set
     * it a blank.
     *
     * @param string $page
     */
    public function set_index_page($page): self
    {
        $this->index_page = $page;
        return $this;
    }
    /**
     * Tells the system whether we should translate URI dashes or not
     * in the URI from a dash to an underscore.
     *
     * @deprecated This method should be removed.
     */
    public function set_translate_uri_dashes(bool $val = false): self
    {
        if ($this->auto_router instanceof Auto_Router) {
            $this->auto_router->set_translate_uri_dashes($val);
            return $this;
        }
        return $this;
    }
    /**
     * Returns true/false based on whether the current route contained
     * a {locale} placeholder.
     *
     * @return bool
     */
    public function has_locale()
    {
        return (bool) $this->detected_locale;
    }
    /**
     * Returns the detected locale, if any, or null.
     *
     * @return string
     */
    public function get_locale()
    {
        return $this->detected_locale;
    }
    /**
     * Checks Defined Routes.
     *
     * Compares the uri string against the routes that the
     * RouteCollection class defined for us, attempting to find a match.
     * This method will modify $this->controller, etal as needed.
     *
     * @param string $uri The URI path to compare against the routes
     *
     * @return bool Whether the route was matched or not.
     *
     * @throws RedirectException
     */
    protected function check_routes(string $uri): bool
    {
        $routes = $this->collection->get_routes($this->collection->get_http_verb());
        // Don't waste any time
        if ($routes === []) {
            return false;
        }
        $uri = $uri === '/' ? $uri : trim($uri, '/ ');
        // Loop through the route array looking for wildcards
        foreach ($routes as $route_key => $handler) {
            $route_key = $route_key === '/' ? $route_key : ltrim((string) $route_key, '/ ');
            $matched_key = $route_key;
            // Are we dealing with a locale?
            if (str_contains($route_key, '{locale}')) {
                $route_key = str_replace('{locale}', '[^/]+', $route_key);
            }
            // Does the RegEx match?
            if (preg_match('#^' . $route_key . '$#u', $uri, $matches)) {
                // Is this route supposed to redirect to another?
                if ($this->collection->is_redirect($route_key)) {
                    // replacing matched route groups with references: post/([0-9]+) -> post/$1
                    $redirect_to = preg_replace_callback('/(\([^\(]+\))/', static function (): string {
                        static $i = 1;
                        return '$' . $i++;
                    }, is_array($handler) ? key($handler) : $handler);
                    throw new Redirect_Exception(preg_replace('#\A' . $route_key . '\z#u', $redirect_to, $uri), $this->collection->get_redirect_code($route_key));
                }
                // Store our locale so CodeIgniter object can
                // assign it to the Request.
                if (str_contains($matched_key, '{locale}')) {
                    preg_match('#^' . str_replace('{locale}', '(?<locale>[^/]+)', $matched_key) . '$#u', $uri, $matched);
                    if ($this->collection->should_use_supported_locales_only() && !in_array($matched['locale'], config(App::class)->supported_locales, true)) {
                        // Throw exception to prevent the autorouter, if enabled,
                        // from trying to find a route
                        throw Page_Not_Found_Exception::for_locale_not_supported($matched['locale']);
                    }
                    $this->detected_locale = $matched['locale'];
                    unset($matched);
                }
                // Are we using Closures? If so, then we need
                // to collect the params into an array
                // so it can be passed to the controller method later.
                if (!is_string($handler) && is_callable($handler)) {
                    $this->controller = $handler;
                    // Remove the original string from the matches array
                    array_shift($matches);
                    $this->params = $matches;
                    $this->set_matched_route($matched_key, $handler);
                    return true;
                }
                if (str_contains($handler, '::')) {
                    [$controller, $method_and_params] = explode('::', $handler);
                } else {
                    $controller = $handler;
                    $method_and_params = '';
                }
                // Checks `/` in controller name
                if (str_contains($controller, '/')) {
                    throw Router_Exception::for_invalid_controller_name($handler);
                }
                if (str_contains($handler, '$') && str_contains($route_key, '(')) {
                    // Checks dynamic controller
                    if (str_contains($controller, '$')) {
                        throw Router_Exception::for_dynamic_controller($handler);
                    }
                    if (config(Routing::class)->multiple_segments_one_param === false) {
                        // Using back-references
                        $segments = explode('/', preg_replace('#\A' . $route_key . '\z#u', $handler, $uri));
                    } else {
                        if (str_contains($method_and_params, '/')) {
                            [$method, $handler_params] = explode('/', $method_and_params, 2);
                            $params = explode('/', $handler_params);
                            $handler_segments = array_merge([$controller . '::' . $method], $params);
                        } else {
                            $handler_segments = [$handler];
                        }
                        $segments = [];
                        foreach ($handler_segments as $segment) {
                            $segments[] = $this->replace_back_references($segment, $matches);
                        }
                    }
                } else {
                    $segments = explode('/', $handler);
                }
                $this->set_request($segments);
                $this->set_matched_route($matched_key, $handler);
                return true;
            }
        }
        return false;
    }
    /**
     * Replace string `$n` with `$matches[n]` value.
     */
    private function replace_back_references(string $input, array $matches): string
    {
        $pattern = '/\$([1-' . count($matches) . '])/u';
        return preg_replace_callback($pattern, static function ($match) use ($matches) {
            $index = (int) $match[1];
            return $matches[$index] ?? '';
        }, $input);
    }
    /**
     * Checks Auto Routes.
     *
     * Attempts to match a URI path against Controllers and directories
     * found in APPPATH/Controllers, to find a matching route.
     *
     * @return void
     */
    public function auto_route(string $uri)
    {
        [$this->directory, $this->controller, $this->method, $this->params] = $this->auto_router->get_route($uri, $this->collection->get_http_verb());
    }
    /**
     * Scans the controller directory, attempting to locate a controller matching the supplied uri $segments
     *
     * @param array $segments URI segments
     *
     * @return array returns an array of remaining uri segments that don't map onto a directory
     *
     * @deprecated this function name does not properly describe its behavior so it has been deprecated
     *
     * @codeCoverageIgnore
     */
    protected function validate_request(array $segments): array
    {
        return $this->scan_controllers($segments);
    }
    /**
     * Scans the controller directory, attempting to locate a controller matching the supplied uri $segments
     *
     * @param array $segments URI segments
     *
     * @return array returns an array of remaining uri segments that don't map onto a directory
     *
     * @deprecated Not used. Moved to AutoRouter class.
     */
    protected function scan_controllers(array $segments): array
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
            $segment_convert = ucfirst($this->translate_uri_dashes === true ? str_replace('-', '_', $segments[0]) : $segments[0]);
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
     * Sets the sub-directory that the controller is in.
     *
     * @param bool $validate if true, checks to make sure $dir consists of only PSR4 compliant segments
     *
     * @return void
     *
     * @deprecated This method should be removed.
     */
    public function set_directory(?string $dir = null, bool $append = false, bool $validate = true)
    {
        if ($dir === null || $dir === '') {
            $this->directory = null;
        }
        if ($this->auto_router instanceof Auto_Router) {
            $this->auto_router->set_directory($dir, $append, $validate);
        }
    }
    /**
     * Returns true if the supplied $segment string represents a valid PSR-4 compliant namespace/directory segment
     *
     * regex comes from https://www.php.net/manual/en/language.variables.basics.php
     *
     * @deprecated Moved to AutoRouter class.
     */
    private function is_valid_segment(string $segment): bool
    {
        return (bool) preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $segment);
    }
    /**
     * Set request route
     *
     * Takes an array of URI segments as input and sets the class/method
     * to be called.
     *
     * @param array $segments URI segments
     *
     * @return void
     */
    protected function set_request(array $segments = [])
    {
        // If we don't have any segments - use the default controller;
        if ($segments === []) {
            return;
        }
        [$controller, $method] = array_pad(explode('::', $segments[0]), 2, null);
        $this->controller = $controller;
        // $this->method already contains the default method name,
        // so don't overwrite it with emptiness.
        if (!empty($method)) {
            $this->method = $method;
        }
        array_shift($segments);
        $this->params = $segments;
    }
    /**
     * Sets the default controller based on the info set in the RouteCollection.
     *
     * @deprecated This was an unnecessary method, so it is no longer used.
     *
     * @return void
     */
    protected function set_default_controller()
    {
        if (empty($this->controller)) {
            throw Router_Exception::for_missing_default_route();
        }
        sscanf($this->controller, '%[^/]/%s', $class, $this->method);
        if (!is_file(APPPATH . 'Controllers/' . $this->directory . ucfirst($class) . '.php')) {
            return;
        }
        $this->controller = ucfirst($class);
        log_message('info', 'Used the default controller.');
    }
    /**
     * @param callable|string $handler
     */
    protected function set_matched_route(string $route, $handler): void
    {
        $this->matched_route = [$route, $handler];
        $this->matched_route_options = $this->collection->get_routes_options($route);
    }
    /**
     * Checks disallowed characters
     */
    private function check_disallowed_chars(string $uri): void
    {
        foreach (explode('/', $uri) as $segment) {
            if ($segment !== '' && $this->permitted_uri_chars !== '' && preg_match('/\A[' . $this->permitted_uri_chars . ']+\z/iu', $segment) !== 1) {
                throw new Bad_Request_Exception('The URI you submitted has disallowed characters: "' . $segment . '"');
            }
        }
    }
    /**
     * Extracts PHP attributes from the resolved controller and method.
     */
    private function process_route_attributes(): void
    {
        $this->route_attributes = ['class' => [], 'method' => []];
        // Skip if controller attributes are disabled in config
        if (config('routing')->use_controller_attributes === false) {
            return;
        }
        // Skip if controller is a Closure
        if ($this->controller instanceof Closure) {
            return;
        }
        if (!class_exists($this->controller)) {
            return;
        }
        $reflection_class = new ReflectionClass($this->controller);
        // Process class-level attributes
        foreach ($reflection_class->get_attributes() as $attribute) {
            try {
                $instance = $attribute->new_instance();
                if ($instance instanceof Route_Attribute_Interface) {
                    $this->route_attributes['class'][] = $instance;
                }
            } catch (Throwable) {
                log_message('error', 'Failed to instantiate attribute: ' . $attribute->get_name());
            }
        }
        if ($this->method === '' || $this->method === null) {
            return;
        }
        // Process method-level attributes
        if ($reflection_class->has_method($this->method)) {
            $reflection_method = $reflection_class->get_method($this->method);
            foreach ($reflection_method->get_attributes() as $attribute) {
                try {
                    $instance = $attribute->new_instance();
                    if ($instance instanceof Route_Attribute_Interface) {
                        $this->route_attributes['method'][] = $instance;
                    }
                } catch (Throwable) {
                    // Skip attributes that fail to instantiate
                    log_message('error', 'Failed to instantiate attribute: ' . $attribute->get_name());
                }
            }
        }
    }
    /**
     * Execute beforeController() on all route attributes.
     * Called by CodeIgniter before controller execution.
     */
    public function execute_before_attributes(Request_Interface $request): Request_Interface|Response_Interface|null
    {
        // Process class-level attributes first, then method-level
        foreach (['class', 'method'] as $level) {
            foreach ($this->route_attributes[$level] as $attribute) {
                if (!$attribute instanceof Route_Attribute_Interface) {
                    continue;
                }
                $result = $attribute->before($request);
                // If attribute returns a Response, short-circuit
                if ($result instanceof Response_Interface) {
                    return $result;
                }
                // If attribute returns a Request, use it
                if ($result instanceof Request_Interface) {
                    $request = $result;
                }
            }
        }
        return $request;
    }
    /**
     * Execute afterController() on all route attributes.
     * Called by CodeIgniter after controller execution.
     */
    public function execute_after_attributes(Request_Interface $request, Response_Interface $response): Response_Interface
    {
        // Process in reverse order: method-level first, then class-level
        foreach (array_reverse(['class', 'method']) as $level) {
            foreach ($this->route_attributes[$level] as $attribute) {
                if ($attribute instanceof Route_Attribute_Interface) {
                    $result = $attribute->after($request, $response);
                    if ($result instanceof Response_Interface) {
                        $response = $result;
                    }
                }
            }
        }
        return $response;
    }
    /**
     * Returns the route attributes collected during routing
     * for the current route.
     *
     * @return array{class: list<string>, method: list<string>}
     */
    public function get_route_attributes(): array
    {
        return $this->route_attributes;
    }
}