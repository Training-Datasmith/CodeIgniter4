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
use Code_Igniter\Autoloader\File_Locator_Interface;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\HTTP\Method;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\Router\Exceptions\Router_Exception;
use Config\App;
use Config\Modules;
use Config\Routing;
/**
 * @todo Implement nested resource routing (See CakePHP)
 * @see \CodeIgniter\Router\RouteCollectionTest
 */
class Route_Collection implements Route_Collection_Interface
{
    /**
     * The namespace to be added to any Controllers.
     * Defaults to the global namespaces (\).
     *
     * This must have a trailing backslash (\).
     *
     * @var string
     */
    protected $default_namespace = '\\';
    /**
     * The name of the default controller to use
     * when no other controller is specified.
     *
     * Not used here. Pass-thru value for Router class.
     *
     * @var string
     */
    protected $default_controller = 'Home';
    /**
     * The name of the default method to use
     * when no other method has been specified.
     *
     * Not used here. Pass-thru value for Router class.
     *
     * @var string
     */
    protected $default_method = 'index';
    /**
     * The placeholder used when routing 'resources'
     * when no other placeholder has been specified.
     *
     * @var string
     */
    protected $default_placeholder = 'any';
    /**
     * Whether to convert dashes to underscores in URI.
     *
     * Not used here. Pass-thru value for Router class.
     *
     * @var bool
     */
    protected $translate_uri_dashes = false;
    /**
     * Whether to match URI against Controllers
     * when it doesn't match defined routes.
     *
     * Not used here. Pass-thru value for Router class.
     *
     * @var bool
     */
    protected $auto_route = false;
    /**
     * A callable that will be shown
     * when the route cannot be matched.
     *
     * @var (Closure(string): (ResponseInterface|string|void))|string
     */
    protected $override404;
    /**
     * An array of files that would contain route definitions.
     */
    protected array $route_files = [];
    /**
     * Defined placeholders that can be used
     * within the
     *
     * @var array<string, string>
     */
    protected $placeholders = ['any' => '.*', 'segment' => '[^/]+', 'alphanum' => '[a-zA-Z0-9]+', 'num' => '[0-9]+', 'alpha' => '[a-zA-Z]+', 'hash' => '[^/]+'];
    /**
     * An array of all routes and their mappings.
     *
     * @var array
     *
     * [
     *     verb => [
     *         routeKey(regex) => [
     *             'name'    => routeName
     *             'handler' => handler,
     *             'from'    => from,
     *         ],
     *     ],
     *     // redirect route
     *     '*' => [
     *          routeKey(regex)(from) => [
     *             'name'     => routeName
     *             'handler'  => [routeKey(regex)(to) => handler],
     *             'from'     => from,
     *             'redirect' => statusCode,
     *         ],
     *     ],
     * ]
     */
    protected $routes = ['*' => [], Method::OPTIONS => [], Method::GET => [], Method::HEAD => [], Method::POST => [], Method::PATCH => [], Method::PUT => [], Method::DELETE => [], Method::TRACE => [], Method::CONNECT => [], 'CLI' => []];
    /**
     * Array of routes names
     *
     * @var array
     *
     * [
     *     verb => [
     *         routeName => routeKey(regex)
     *     ],
     * ]
     */
    protected $routes_names = ['*' => [], Method::OPTIONS => [], Method::GET => [], Method::HEAD => [], Method::POST => [], Method::PATCH => [], Method::PUT => [], Method::DELETE => [], Method::TRACE => [], Method::CONNECT => [], 'CLI' => []];
    /**
     * Array of routes options
     *
     * @var array
     *
     * [
     *     verb => [
     *         routeKey(regex) => [
     *             key => value,
     *         ]
     *     ],
     * ]
     */
    protected $routes_options = [];
    /**
     * The current method that the script is being called by.
     *
     * @var string HTTP verb like `GET`,`POST` or `*` or `CLI`
     */
    protected $http_verb = '*';
    /**
     * The default list of HTTP methods (and CLI for command line usage)
     * that is allowed if no other method is provided.
     *
     * @var list<string>
     */
    public $default_http_methods = Router::HTTP_METHODS;
    /**
     * The name of the current group, if any.
     *
     * @var string|null
     */
    protected $group;
    /**
     * The current subdomain.
     *
     * @var string|null
     */
    protected $current_subdomain;
    /**
     * Stores copy of current options being
     * applied during creation.
     *
     * @var array|null
     */
    protected $current_options;
    /**
     * A little performance booster.
     *
     * @var bool
     */
    protected $did_discover = false;
    /**
     * Handle to the file locator to use.
     *
     * @var FileLocatorInterface
     */
    protected $file_locator;
    /**
     * Handle to the modules config.
     *
     * @var Modules
     */
    protected $module_config;
    /**
     * Flag for sorting routes by priority.
     *
     * @var bool
     */
    protected $prioritize = false;
    /**
     * Route priority detection flag.
     *
     * @var bool
     */
    protected $prioritize_detected = false;
    /**
     * The current hostname from $_SERVER['HTTP_HOST']
     */
    private ?string $http_host = null;
    /**
     * Flag to limit or not the routes with {locale} placeholder to App::$supportedLocales
     */
    protected bool $use_supported_locales_only = false;
    /**
     * Constructor
     */
    public function __construct(File_Locator_Interface $locator, Modules $module_config, Routing $routing)
    {
        $this->file_locator = $locator;
        $this->module_config = $module_config;
        $this->http_host = service('request')->get_server('HTTP_HOST');
        // Setup based on config file. Let routes file override.
        $this->default_namespace = rtrim($routing->default_namespace, '\\') . '\\';
        $this->default_controller = $routing->default_controller;
        $this->default_method = $routing->default_method;
        $this->translate_uri_dashes = $routing->translate_uri_dashes;
        $this->override404 = $routing->override404;
        $this->auto_route = $routing->auto_route;
        $this->route_files = $routing->route_files;
        $this->prioritize = $routing->prioritize;
        // Normalize the path string in routeFiles array.
        foreach ($this->route_files as $route_key => $routes_file) {
            $realpath = realpath($routes_file);
            $this->route_files[$route_key] = $realpath === false ? $routes_file : $realpath;
        }
    }
    /**
     * Loads main routes file and discover routes.
     *
     * Loads only once unless reset.
     *
     * @return $this
     */
    public function load_routes(string $routes_file = APPPATH . 'Config/Routes.php')
    {
        if ($this->did_discover) {
            return $this;
        }
        // Normalize the path string in routesFile
        $realpath = realpath($routes_file);
        $routes_file = $realpath === false ? $routes_file : $realpath;
        // Include the passed in routesFile if it doesn't exist.
        // Only keeping that around for BC purposes for now.
        $route_files = $this->route_files;
        if (!in_array($routes_file, $route_files, true)) {
            $route_files[] = $routes_file;
        }
        // We need this var in local scope
        // so route files can access it.
        $routes = $this;
        foreach ($route_files as $routes_file) {
            if (!is_file($routes_file)) {
                log_message('warning', sprintf('Routes file not found: "%s"', $routes_file));
                continue;
            }
            require $routes_file;
        }
        $this->discover_routes();
        return $this;
    }
    /**
     * Will attempt to discover any additional routes, either through
     * the local PSR4 namespaces, or through selected Composer packages.
     *
     * @return void
     */
    protected function discover_routes()
    {
        if ($this->did_discover) {
            return;
        }
        // We need this var in local scope
        // so route files can access it.
        $routes = $this;
        if ($this->module_config->should_discover('routes')) {
            $files = $this->file_locator->search('Config/Routes.php');
            foreach ($files as $file) {
                // Don't include our main file again...
                if (in_array($file, $this->route_files, true)) {
                    continue;
                }
                include $file;
            }
        }
        $this->did_discover = true;
    }
    /**
     * Registers a new constraint with the system. Constraints are used
     * by the routes as placeholders for regular expressions to make defining
     * the routes more human-friendly.
     *
     * You can pass an associative array as $placeholder, and have
     * multiple placeholders added at once.
     *
     * @param array|string $placeholder
     */
    public function add_placeholder($placeholder, ?string $pattern = null): Route_Collection_Interface
    {
        if (!is_array($placeholder)) {
            $placeholder = [$placeholder => $pattern];
        }
        $this->placeholders = array_merge($this->placeholders, $placeholder);
        return $this;
    }
    /**
     * For `spark routes`
     *
     * @return array<string, string>
     *
     * @internal
     */
    public function get_placeholders(): array
    {
        return $this->placeholders;
    }
    /**
     * Sets the default namespace to use for Controllers when no other
     * namespace has been specified.
     */
    public function set_default_namespace(string $value): Route_Collection_Interface
    {
        $this->default_namespace = esc(strip_tags($value));
        $this->default_namespace = rtrim($this->default_namespace, '\\') . '\\';
        return $this;
    }
    /**
     * Sets the default controller to use when no other controller has been
     * specified.
     */
    public function set_default_controller(string $value): Route_Collection_Interface
    {
        $this->default_controller = esc(strip_tags($value));
        return $this;
    }
    /**
     * Sets the default method to call on the controller when no other
     * method has been set in the route.
     */
    public function set_default_method(string $value): Route_Collection_Interface
    {
        $this->default_method = esc(strip_tags($value));
        return $this;
    }
    /**
     * Tells the system whether to convert dashes in URI strings into
     * underscores. In some search engines, including Google, dashes
     * create more meaning and make it easier for the search engine to
     * find words and meaning in the URI for better SEO. But it
     * doesn't work well with PHP method names....
     */
    public function set_translate_uri_dashes(bool $value): Route_Collection_Interface
    {
        $this->translate_uri_dashes = $value;
        return $this;
    }
    /**
     * If TRUE, the system will attempt to match the URI against
     * Controllers by matching each segment against folders/files
     * in APPPATH/Controllers, when a match wasn't found against
     * defined routes.
     *
     * If FALSE, will stop searching and do NO automatic routing.
     */
    public function set_auto_route(bool $value): Route_Collection_Interface
    {
        $this->auto_route = $value;
        return $this;
    }
    /**
     * Sets the class/method that should be called if routing doesn't
     * find a match. It can be either a closure or the controller/method
     * name exactly like a route is defined: Users::index
     *
     * This setting is passed to the Router class and handled there.
     *
     * @param callable|string|null $callable
     */
    public function set404Override($callable = null): Route_Collection_Interface
    {
        $this->override404 = $callable;
        return $this;
    }
    /**
     * Returns the 404 Override setting, which can be null, a closure
     * or the controller/string.
     *
     * @return (Closure(string): (ResponseInterface|string|void))|string|null
     */
    public function get404Override()
    {
        return $this->override404;
    }
    /**
     * Sets the default constraint to be used in the system. Typically
     * for use with the 'resource' method.
     */
    public function set_default_constraint(string $placeholder): Route_Collection_Interface
    {
        if (array_key_exists($placeholder, $this->placeholders)) {
            $this->default_placeholder = $placeholder;
        }
        return $this;
    }
    /**
     * Returns the name of the default controller. With Namespace.
     */
    public function get_default_controller(): string
    {
        return $this->default_controller;
    }
    /**
     * Returns the name of the default method to use within the controller.
     */
    public function get_default_method(): string
    {
        return $this->default_method;
    }
    /**
     * Returns the default namespace as set in the Routes config file.
     */
    public function get_default_namespace(): string
    {
        return $this->default_namespace;
    }
    /**
     * Returns the current value of the translateURIDashes setting.
     */
    public function should_translate_uri_dashes(): bool
    {
        return $this->translate_uri_dashes;
    }
    /**
     * Returns the flag that tells whether to autoRoute URI against Controllers.
     */
    public function should_auto_route(): bool
    {
        return $this->auto_route;
    }
    /**
     * Returns the raw array of available routes.
     *
     * @param non-empty-string|null $verb            HTTP verb like `GET`,`POST` or `*` or `CLI`.
     * @param bool                  $includeWildcard Whether to include '*' routes.
     */
    public function get_routes(?string $verb = null, bool $include_wildcard = true): array
    {
        if ((string) $verb === '') {
            $verb = $this->get_http_verb();
        }
        // Since this is the entry point for the Router,
        // take a moment to do any route discovery
        // we might need to do.
        $this->discover_routes();
        $routes = [];
        if (isset($this->routes[$verb])) {
            // Keep current verb's routes at the beginning, so they're matched
            // before any of the generic, "add" routes.
            $collection = $include_wildcard ? $this->routes[$verb] + ($this->routes['*'] ?? []) : $this->routes[$verb];
            foreach ($collection as $route_key => $r) {
                $routes[$route_key] = $r['handler'];
            }
        }
        // sorting routes by priority
        if ($this->prioritize_detected && $this->prioritize && $routes !== []) {
            $order = [];
            foreach ($routes as $key => $value) {
                $key = $key === '/' ? $key : ltrim($key, '/ ');
                $priority = $this->get_routes_options($key, $verb)['priority'] ?? 0;
                $order[$priority][$key] = $value;
            }
            ksort($order);
            $routes = array_merge(...$order);
        }
        return $routes;
    }
    /**
     * Returns one or all routes options
     *
     * @param string|null $verb HTTP verb like `GET`,`POST` or `*` or `CLI`.
     *
     * @return array<string, int|string> [key => value]
     */
    public function get_routes_options(?string $from = null, ?string $verb = null): array
    {
        $options = $this->load_routes_options($verb);
        return (string) $from !== '' ? $options[$from] ?? [] : $options;
    }
    /**
     * Returns the current HTTP Verb being used.
     */
    public function get_http_verb(): string
    {
        return $this->http_verb;
    }
    /**
     * Sets the current HTTP verb.
     * Used primarily for testing.
     *
     * @param string $verb HTTP verb
     *
     * @return $this
     */
    public function set_http_verb(string $verb)
    {
        if ($verb !== '*' && $verb === strtolower($verb)) {
            @trigger_error('Passing lowercase HTTP method "' . $verb . '" is deprecated.' . ' Use uppercase HTTP method like "' . strtoupper($verb) . '".', E_USER_DEPRECATED);
        }
        /**
         * @deprecated 4.5.0
         * @TODO Remove strtoupper() in the future.
         */
        $this->http_verb = strtoupper($verb);
        return $this;
    }
    /**
     * A shortcut method to add a number of routes at a single time.
     * It does not allow any options to be set on the route, or to
     * define the method used.
     */
    public function map(array $routes = [], ?array $options = null): Route_Collection_Interface
    {
        foreach ($routes as $from => $to) {
            $this->add($from, $to, $options);
        }
        return $this;
    }
    /**
     * Adds a single route to the collection.
     *
     * Example:
     *      $routes->add('news', 'Posts::index');
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function add(string $from, $to, ?array $options = null): Route_Collection_Interface
    {
        $this->create('*', $from, $to, $options);
        return $this;
    }
    /**
     * Adds a temporary redirect from one route to another. Used for
     * redirecting traffic from old, non-existing routes to the new
     * moved routes.
     *
     * @param string $from   The pattern to match against
     * @param string $to     Either a route name or a URI to redirect to
     * @param int    $status The HTTP status code that should be returned with this redirect
     *
     * @return RouteCollection
     */
    public function add_redirect(string $from, string $to, int $status = 302)
    {
        // Use the named route's pattern if this is a named route.
        if (array_key_exists($to, $this->routes_names['*'])) {
            $route_name = $to;
            $route_key = $this->routes_names['*'][$route_name];
            $redirect_to = [$route_key => $this->routes['*'][$route_key]['handler']];
        } elseif (array_key_exists($to, $this->routes_names[Method::GET])) {
            $route_name = $to;
            $route_key = $this->routes_names[Method::GET][$route_name];
            $redirect_to = [$route_key => $this->routes[Method::GET][$route_key]['handler']];
        } else {
            // The named route is not found.
            $redirect_to = $to;
        }
        $this->create('*', $from, $redirect_to, ['redirect' => $status]);
        return $this;
    }
    /**
     * Determines if the route is a redirecting route.
     *
     * @param string $routeKey routeKey or route name
     */
    public function is_redirect(string $route_key): bool
    {
        if (isset($this->routes['*'][$route_key]['redirect'])) {
            return true;
        }
        // This logic is not used. Should be deprecated?
        $route_name = $this->routes['*'][$route_key]['name'] ?? null;
        if ($route_name === $route_key) {
            $route_key = $this->routes_names['*'][$route_name];
            return isset($this->routes['*'][$route_key]['redirect']);
        }
        return false;
    }
    /**
     * Grabs the HTTP status code from a redirecting Route.
     *
     * @param string $routeKey routeKey or route name
     */
    public function get_redirect_code(string $route_key): int
    {
        if (isset($this->routes['*'][$route_key]['redirect'])) {
            return $this->routes['*'][$route_key]['redirect'];
        }
        // This logic is not used. Should be deprecated?
        $route_name = $this->routes['*'][$route_key]['name'] ?? null;
        if ($route_name === $route_key) {
            $route_key = $this->routes_names['*'][$route_name];
            return $this->routes['*'][$route_key]['redirect'];
        }
        return 0;
    }
    /**
     * Group a series of routes under a single URL segment. This is handy
     * for grouping items into an admin area, like:
     *
     * Example:
     *     // Creates route: admin/users
     *     $route->group('admin', function() {
     *            $route->resource('users');
     *     });
     *
     * @param string         $name      The name to group/prefix the routes with.
     * @param array|callable ...$params
     *
     * @return void
     */
    public function group(string $name, ...$params)
    {
        $old_group = $this->group;
        $old_options = $this->current_options;
        // To register a route, we'll set a flag so that our router
        // will see the group name.
        // If the group name is empty, we go on using the previously built group name.
        $this->group = $name !== '' ? trim($old_group . '/' . $name, '/') : $old_group;
        $callback = array_pop($params);
        if ($params !== [] && is_array($params[0])) {
            $options = array_shift($params);
            if (isset($options['filter'])) {
                // Merge filters.
                $current_filter = (array) ($this->current_options['filter'] ?? []);
                $options['filter'] = array_merge($current_filter, (array) $options['filter']);
            }
            // Merge options other than filters.
            $this->current_options = array_merge($this->current_options ?? [], $options);
        }
        if (is_callable($callback)) {
            $callback($this);
        }
        $this->group = $old_group;
        $this->current_options = $old_options;
    }
    /*
     * --------------------------------------------------------------------
     *  HTTP Verb-based routing
     * --------------------------------------------------------------------
     * Routing works here because, as the routes Config file is read in,
     * the various HTTP verb-based routes will only be added to the in-memory
     * routes if it is a call that should respond to that verb.
     *
     * The options array is typically used to pass in an 'as' or var, but may
     * be expanded in the future. See the docblock for 'add' method above for
     * current list of globally available options.
     */
    /**
     * Creates a collections of HTTP-verb based routes for a controller.
     *
     * Possible Options:
     *      'controller'    - Customize the name of the controller used in the 'to' route
     *      'placeholder'   - The regex used by the Router. Defaults to '(:any)'
     *      'websafe'   -	- '1' if only GET and POST HTTP verbs are supported
     *
     * Example:
     *
     *      $route->resource('photos');
     *
     *      // Generates the following routes:
     *      HTTP Verb | Path        | Action        | Used for...
     *      ----------+-------------+---------------+-----------------
     *      GET         /photos             index           an array of photo objects
     *      GET         /photos/new         new             an empty photo object, with default properties
     *      GET         /photos/{id}/edit   edit            a specific photo object, editable properties
     *      GET         /photos/{id}        show            a specific photo object, all properties
     *      POST        /photos             create          a new photo object, to add to the resource
     *      DELETE      /photos/{id}        delete          deletes the specified photo object
     *      PUT/PATCH   /photos/{id}        update          replacement properties for existing photo
     *
     *  If 'websafe' option is present, the following paths are also available:
     *
     *      POST		/photos/{id}/delete delete
     *      POST        /photos/{id}        update
     *
     * @param string     $name    The name of the resource/controller to route to.
     * @param array|null $options A list of possible ways to customize the routing.
     */
    public function resource(string $name, ?array $options = null): Route_Collection_Interface
    {
        // In order to allow customization of the route the
        // resources are sent to, we need to have a new name
        // to store the values in.
        $new_name = implode('\\', array_map(ucfirst(...), explode('/', $name)));
        // If a new controller is specified, then we replace the
        // $name value with the name of the new controller.
        if (isset($options['controller'])) {
            $new_name = ucfirst(esc(strip_tags($options['controller'])));
        }
        // In order to allow customization of allowed id values
        // we need someplace to store them.
        $id = $options['placeholder'] ?? $this->placeholders[$this->default_placeholder] ?? '(:segment)';
        // Make sure we capture back-references
        $id = '(' . trim($id, '()') . ')';
        $methods = isset($options['only']) ? is_string($options['only']) ? explode(',', $options['only']) : $options['only'] : ['index', 'show', 'create', 'update', 'delete', 'new', 'edit'];
        if (isset($options['except'])) {
            $options['except'] = is_array($options['except']) ? $options['except'] : explode(',', $options['except']);
            foreach ($methods as $i => $method) {
                if (in_array($method, $options['except'], true)) {
                    unset($methods[$i]);
                }
            }
        }
        if (in_array('index', $methods, true)) {
            $this->get($name, $new_name . '::index', $options);
        }
        if (in_array('new', $methods, true)) {
            $this->get($name . '/new', $new_name . '::new', $options);
        }
        if (in_array('edit', $methods, true)) {
            $this->get($name . '/' . $id . '/edit', $new_name . '::edit/$1', $options);
        }
        if (in_array('show', $methods, true)) {
            $this->get($name . '/' . $id, $new_name . '::show/$1', $options);
        }
        if (in_array('create', $methods, true)) {
            $this->post($name, $new_name . '::create', $options);
        }
        if (in_array('update', $methods, true)) {
            $this->put($name . '/' . $id, $new_name . '::update/$1', $options);
            $this->patch($name . '/' . $id, $new_name . '::update/$1', $options);
        }
        if (in_array('delete', $methods, true)) {
            $this->delete($name . '/' . $id, $new_name . '::delete/$1', $options);
        }
        // Web Safe? delete needs checking before update because of method name
        if (isset($options['websafe'])) {
            if (in_array('delete', $methods, true)) {
                $this->post($name . '/' . $id . '/delete', $new_name . '::delete/$1', $options);
            }
            if (in_array('update', $methods, true)) {
                $this->post($name . '/' . $id, $new_name . '::update/$1', $options);
            }
        }
        return $this;
    }
    /**
     * Creates a collections of HTTP-verb based routes for a presenter controller.
     *
     * Possible Options:
     *      'controller'    - Customize the name of the controller used in the 'to' route
     *      'placeholder'   - The regex used by the Router. Defaults to '(:any)'
     *
     * Example:
     *
     *      $route->presenter('photos');
     *
     *      // Generates the following routes:
     *      HTTP Verb | Path        | Action        | Used for...
     *      ----------+-------------+---------------+-----------------
     *      GET         /photos             index           showing all array of photo objects
     *      GET         /photos/show/{id}   show            showing a specific photo object, all properties
     *      GET         /photos/new         new             showing a form for an empty photo object, with default properties
     *      POST        /photos/create      create          processing the form for a new photo
     *      GET         /photos/edit/{id}   edit            show an editing form for a specific photo object, editable properties
     *      POST        /photos/update/{id} update          process the editing form data
     *      GET         /photos/remove/{id} remove          show a form to confirm deletion of a specific photo object
     *      POST        /photos/delete/{id} delete          deleting the specified photo object
     *
     * @param string     $name    The name of the controller to route to.
     * @param array|null $options A list of possible ways to customize the routing.
     */
    public function presenter(string $name, ?array $options = null): Route_Collection_Interface
    {
        // In order to allow customization of the route the
        // resources are sent to, we need to have a new name
        // to store the values in.
        $new_name = implode('\\', array_map(ucfirst(...), explode('/', $name)));
        // If a new controller is specified, then we replace the
        // $name value with the name of the new controller.
        if (isset($options['controller'])) {
            $new_name = ucfirst(esc(strip_tags($options['controller'])));
        }
        // In order to allow customization of allowed id values
        // we need someplace to store them.
        $id = $options['placeholder'] ?? $this->placeholders[$this->default_placeholder] ?? '(:segment)';
        // Make sure we capture back-references
        $id = '(' . trim($id, '()') . ')';
        $methods = isset($options['only']) ? is_string($options['only']) ? explode(',', $options['only']) : $options['only'] : ['index', 'show', 'new', 'create', 'edit', 'update', 'remove', 'delete'];
        if (isset($options['except'])) {
            $options['except'] = is_array($options['except']) ? $options['except'] : explode(',', $options['except']);
            foreach ($methods as $i => $method) {
                if (in_array($method, $options['except'], true)) {
                    unset($methods[$i]);
                }
            }
        }
        if (in_array('index', $methods, true)) {
            $this->get($name, $new_name . '::index', $options);
        }
        if (in_array('show', $methods, true)) {
            $this->get($name . '/show/' . $id, $new_name . '::show/$1', $options);
        }
        if (in_array('new', $methods, true)) {
            $this->get($name . '/new', $new_name . '::new', $options);
        }
        if (in_array('create', $methods, true)) {
            $this->post($name . '/create', $new_name . '::create', $options);
        }
        if (in_array('edit', $methods, true)) {
            $this->get($name . '/edit/' . $id, $new_name . '::edit/$1', $options);
        }
        if (in_array('update', $methods, true)) {
            $this->post($name . '/update/' . $id, $new_name . '::update/$1', $options);
        }
        if (in_array('remove', $methods, true)) {
            $this->get($name . '/remove/' . $id, $new_name . '::remove/$1', $options);
        }
        if (in_array('delete', $methods, true)) {
            $this->post($name . '/delete/' . $id, $new_name . '::delete/$1', $options);
        }
        if (in_array('show', $methods, true)) {
            $this->get($name . '/' . $id, $new_name . '::show/$1', $options);
        }
        if (in_array('create', $methods, true)) {
            $this->post($name, $new_name . '::create', $options);
        }
        return $this;
    }
    /**
     * Specifies a single route to match for multiple HTTP Verbs.
     *
     * Example:
     *  $route->match( ['GET', 'POST'], 'users/(:num)', 'users/$1);
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function match(array $verbs = [], string $from = '', $to = '', ?array $options = null): Route_Collection_Interface
    {
        if ($from === '' || empty($to)) {
            throw new InvalidArgumentException('You must supply the parameters: from, to.');
        }
        foreach ($verbs as $verb) {
            if ($verb === strtolower($verb)) {
                @trigger_error('Passing lowercase HTTP method "' . $verb . '" is deprecated.' . ' Use uppercase HTTP method like "' . strtoupper($verb) . '".', E_USER_DEPRECATED);
            }
            /**
             * @TODO We should use correct uppercase verb.
             * @deprecated 4.5.0
             */
            $verb = strtolower($verb);
            $this->{$verb}($from, $to, $options);
        }
        return $this;
    }
    /**
     * Specifies a route that is only available to GET requests.
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function get(string $from, $to, ?array $options = null): Route_Collection_Interface
    {
        $this->create(Method::GET, $from, $to, $options);
        return $this;
    }
    /**
     * Specifies a route that is only available to POST requests.
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function post(string $from, $to, ?array $options = null): Route_Collection_Interface
    {
        $this->create(Method::POST, $from, $to, $options);
        return $this;
    }
    /**
     * Specifies a route that is only available to PUT requests.
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function put(string $from, $to, ?array $options = null): Route_Collection_Interface
    {
        $this->create(Method::PUT, $from, $to, $options);
        return $this;
    }
    /**
     * Specifies a route that is only available to DELETE requests.
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function delete(string $from, $to, ?array $options = null): Route_Collection_Interface
    {
        $this->create(Method::DELETE, $from, $to, $options);
        return $this;
    }
    /**
     * Specifies a route that is only available to HEAD requests.
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function head(string $from, $to, ?array $options = null): Route_Collection_Interface
    {
        $this->create(Method::HEAD, $from, $to, $options);
        return $this;
    }
    /**
     * Specifies a route that is only available to PATCH requests.
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function patch(string $from, $to, ?array $options = null): Route_Collection_Interface
    {
        $this->create(Method::PATCH, $from, $to, $options);
        return $this;
    }
    /**
     * Specifies a route that is only available to OPTIONS requests.
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function options(string $from, $to, ?array $options = null): Route_Collection_Interface
    {
        $this->create(Method::OPTIONS, $from, $to, $options);
        return $this;
    }
    /**
     * Specifies a route that is only available to command-line requests.
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     */
    public function cli(string $from, $to, ?array $options = null): Route_Collection_Interface
    {
        $this->create('CLI', $from, $to, $options);
        return $this;
    }
    /**
     * Specifies a route that will only display a view.
     * Only works for GET requests.
     */
    public function view(string $from, string $view, ?array $options = null): Route_Collection_Interface
    {
        $to = static fn(...$data) => service('renderer')->set_data(['segments' => $data], 'raw')->render($view, $options);
        $route_options = $options ?? [];
        $route_options = array_merge($route_options, ['view' => $view]);
        $this->create(Method::GET, $from, $to, $route_options);
        return $this;
    }
    /**
     * Limits the routes to a specified ENVIRONMENT or they won't run.
     *
     * @param Closure(RouteCollection): void $callback
     */
    public function environment(string $env, Closure $callback): Route_Collection_Interface
    {
        if ($env === ENVIRONMENT) {
            $callback($this);
        }
        return $this;
    }
    /**
     * Attempts to look up a route based on its destination.
     *
     * If a route exists:
     *
     *      'path/(:any)/(:any)' => 'Controller::method/$1/$2'
     *
     * This method allows you to know the Controller and method
     * and get the route that leads to it.
     *
     *      // Equals 'path/$param1/$param2'
     *      reverseRoute('Controller::method', $param1, $param2);
     *
     * @param string     $search    Route name or Controller::method
     * @param int|string ...$params One or more parameters to be passed to the route.
     *                              The last parameter allows you to set the locale.
     *
     * @return false|string The route (URI path relative to baseURL) or false if not found.
     */
    public function reverse_route(string $search, ...$params)
    {
        if ($search === '') {
            return false;
        }
        // Named routes get higher priority.
        foreach ($this->routes_names as $verb => $collection) {
            if (array_key_exists($search, $collection)) {
                $route_key = $collection[$search];
                $from = $this->routes[$verb][$route_key]['from'];
                return $this->build_reverse_route($from, $params);
            }
        }
        // Add the default namespace if needed.
        $namespace = trim($this->default_namespace, '\\') . '\\';
        if (!str_starts_with($search, '\\') && !str_starts_with($search, $namespace)) {
            $search = $namespace . $search;
        }
        // If it's not a named route, then loop over
        // all routes to find a match.
        foreach ($this->routes as $collection) {
            foreach ($collection as $route) {
                $to = $route['handler'];
                $from = $route['from'];
                // ignore closures
                if (!is_string($to)) {
                    continue;
                }
                // Lose any namespace slash at beginning of strings
                // to ensure more consistent match.
                $to = ltrim($to, '\\');
                $search = ltrim($search, '\\');
                // If there's any chance of a match, then it will
                // be with $search at the beginning of the $to string.
                if (!str_starts_with($to, $search)) {
                    continue;
                }
                // Ensure that the number of $params given here
                // matches the number of back-references in the route
                if (substr_count($to, '$') !== count($params)) {
                    continue;
                }
                return $this->build_reverse_route($from, $params);
            }
        }
        // If we're still here, then we did not find a match.
        return false;
    }
    /**
     * Replaces the {locale} tag with the current application locale
     *
     * @deprecated Unused.
     */
    protected function localize_route(string $route): string
    {
        return strtr($route, ['{locale}' => service('request')->get_locale()]);
    }
    /**
     * Checks a route (using the "from") to see if it's filtered or not.
     *
     * @param string|null $verb HTTP verb like `GET`,`POST` or `*` or `CLI`.
     */
    public function is_filtered(string $search, ?string $verb = null): bool
    {
        $options = $this->load_routes_options($verb);
        return isset($options[$search]['filter']);
    }
    /**
     * Returns the filters that should be applied for a single route, along
     * with any parameters it might have. Parameters are found by splitting
     * the parameter name on a colon to separate the filter name from the parameter list,
     * and the splitting the result on commas. So:
     *
     *    'role:admin,manager'
     *
     * has a filter of "role", with parameters of ['admin', 'manager'].
     *
     * @param string      $search routeKey
     * @param string|null $verb   HTTP verb like `GET`,`POST` or `*` or `CLI`.
     *
     * @return list<string> filter_name or filter_name:arguments like 'role:admin,manager'
     */
    public function get_filters_for_route(string $search, ?string $verb = null): array
    {
        $options = $this->load_routes_options($verb);
        if (!array_key_exists($search, $options) || !array_key_exists('filter', $options[$search])) {
            return [];
        }
        if (is_string($options[$search]['filter'])) {
            return [$options[$search]['filter']];
        }
        return $options[$search]['filter'];
    }
    /**
     * Given a
     *
     * @throws RouterException
     *
     * @deprecated Unused. Now uses buildReverseRoute().
     */
    protected function fill_route_params(string $from, ?array $params = null): string
    {
        // Find all of our back-references in the original route
        preg_match_all('/\(([^)]+)\)/', $from, $matches);
        if (empty($matches[0])) {
            return '/' . ltrim($from, '/');
        }
        /**
         * Build our resulting string, inserting the $params in
         * the appropriate places.
         *
         * @var list<string> $patterns
         */
        $patterns = $matches[0];
        foreach ($patterns as $index => $pattern) {
            if (preg_match('#^' . $pattern . '$#u', $params[$index]) !== 1) {
                throw Router_Exception::for_invalid_parameter_type();
            }
            // Ensure that the param we're inserting matches
            // the expected param type.
            $pos = strpos($from, $pattern);
            $from = substr_replace($from, $params[$index], $pos, strlen($pattern));
        }
        return '/' . ltrim($from, '/');
    }
    /**
     * Builds reverse route
     *
     * @param array $params One or more parameters to be passed to the route.
     *                      The last parameter allows you to set the locale.
     */
    protected function build_reverse_route(string $from, array $params): string
    {
        $locale = null;
        // Find all of our back-references in the original route
        preg_match_all('/\(([^)]+)\)/', $from, $matches);
        if (empty($matches[0])) {
            if (str_contains($from, '{locale}')) {
                $locale = $params[0] ?? null;
            }
            $from = $this->replace_locale($from, $locale);
            return '/' . ltrim($from, '/');
        }
        // Locale is passed?
        $placeholder_count = count($matches[0]);
        if (count($params) > $placeholder_count) {
            $locale = $params[$placeholder_count];
        }
        /**
         * Build our resulting string, inserting the $params in
         * the appropriate places.
         *
         * @var list<string> $placeholders
         */
        $placeholders = $matches[0];
        foreach ($placeholders as $index => $placeholder) {
            if (!isset($params[$index])) {
                throw new InvalidArgumentException('Missing argument for "' . $placeholder . '" in route "' . $from . '".');
            }
            // Remove `(:` and `)` when $placeholder is a placeholder.
            $placeholder_name = substr($placeholder, 2, -1);
            // or maybe $placeholder is not a placeholder, but a regex.
            $pattern = $this->placeholders[$placeholder_name] ?? $placeholder;
            if (preg_match('#^' . $pattern . '$#u', (string) $params[$index]) !== 1) {
                throw Router_Exception::for_invalid_parameter_type();
            }
            // Ensure that the param we're inserting matches
            // the expected param type.
            $pos = strpos($from, $placeholder);
            $from = substr_replace($from, (string) $params[$index], $pos, strlen($placeholder));
        }
        $from = $this->replace_locale($from, $locale);
        return '/' . ltrim($from, '/');
    }
    /**
     * Replaces the {locale} tag with the locale
     */
    private function replace_locale(string $route, ?string $locale = null): string
    {
        if (!str_contains($route, '{locale}')) {
            return $route;
        }
        // Check invalid locale
        if ((string) $locale !== '') {
            $config = config(App::class);
            if (!in_array($locale, $config->supported_locales, true)) {
                $locale = null;
            }
        }
        if ((string) $locale === '') {
            $locale = service('request')->get_locale();
        }
        return strtr($route, ['{locale}' => $locale]);
    }
    /**
     * Does the heavy lifting of creating an actual route. You must specify
     * the request method(s) that this route will work for. They can be separated
     * by a pipe character "|" if there is more than one.
     *
     * @param array|(Closure(mixed...): (ResponseInterface|string|void))|string $to
     *
     * @return void
     */
    protected function create(string $verb, string $from, $to, ?array $options = null)
    {
        $overwrite = false;
        $prefix = $this->group === null ? '' : $this->group . '/';
        $from = esc(strip_tags($prefix . $from));
        // While we want to add a route within a group of '/',
        // it doesn't work with matching, so remove them...
        if ($from !== '/') {
            $from = trim($from, '/');
        }
        // When redirecting to named route, $to is an array like `['zombies' => '\Zombies::index']`.
        if (is_array($to) && isset($to[0])) {
            $to = $this->process_array_callable_syntax($from, $to);
        }
        // Merge group filters.
        if (isset($options['filter'])) {
            $current_filter = (array) ($this->current_options['filter'] ?? []);
            $options['filter'] = array_merge($current_filter, (array) $options['filter']);
        }
        $options = array_merge($this->current_options ?? [], $options ?? []);
        // Route priority detect
        if (isset($options['priority'])) {
            $options['priority'] = abs((int) $options['priority']);
            if ($options['priority'] > 0) {
                $this->prioritize_detected = true;
            }
        }
        // Hostname limiting?
        if (!empty($options['hostname'])) {
            // @todo determine if there's a way to whitelist hosts?
            if (!$this->check_hostname($options['hostname'])) {
                return;
            }
            $overwrite = true;
        } elseif (!empty($options['subdomain'])) {
            // If we don't match the current subdomain, then
            // we don't need to add the route.
            if (!$this->check_subdomains($options['subdomain'])) {
                return;
            }
            $overwrite = true;
        }
        // Are we offsetting the binds?
        // If so, take care of them here in one
        // fell swoop.
        if (isset($options['offset']) && is_string($to)) {
            // Get a constant string to work with.
            $to = preg_replace('/(\$\d+)/', '$X', $to);
            for ($i = (int) $options['offset'] + 1; $i < (int) $options['offset'] + 7; $i++) {
                $to = preg_replace_callback('/\$X/', static fn($m): string => '$' . $i, $to, 1);
            }
        }
        $route_key = $from;
        // Replace our regex pattern placeholders with the actual thing
        // so that the Router doesn't need to know about any of this.
        foreach ($this->placeholders as $tag => $pattern) {
            $route_key = str_ireplace(':' . $tag, $pattern, $route_key);
        }
        // If is redirect, No processing
        if (!isset($options['redirect']) && is_string($to)) {
            // If no namespace found, add the default namespace
            if (!str_contains($to, '\\') || strpos($to, '\\') > 0) {
                $namespace = $options['namespace'] ?? $this->default_namespace;
                $to = trim($namespace, '\\') . '\\' . $to;
            }
            // Always ensure that we escape our namespace so we're not pointing to
            // \CodeIgniter\Routes\Controller::method.
            $to = '\\' . ltrim($to, '\\');
        }
        $name = $options['as'] ?? $route_key;
        helper('array');
        // Don't overwrite any existing 'froms' so that auto-discovered routes
        // do not overwrite any app/Config/Routes settings. The app
        // routes should always be the "source of truth".
        // this works only because discovered routes are added just prior
        // to attempting to route the request.
        $route_key_exists = isset($this->routes[$verb][$route_key]);
        if ((isset($this->routes_names[$verb][$name]) || $route_key_exists) && !$overwrite) {
            return;
        }
        $this->routes[$verb][$route_key] = ['name' => $name, 'handler' => $to, 'from' => $from];
        $this->routes_options[$verb][$route_key] = $options;
        $this->routes_names[$verb][$name] = $route_key;
        // Is this a redirect?
        if (isset($options['redirect']) && is_numeric($options['redirect'])) {
            $this->routes['*'][$route_key]['redirect'] = $options['redirect'];
        }
    }
    /**
     * Compares the hostname passed in against the current hostname
     * on this page request.
     *
     * @param list<string>|string $hostname Hostname in route options
     */
    private function check_hostname($hostname): bool
    {
        // CLI calls can't be on hostname.
        if (!isset($this->http_host)) {
            return false;
        }
        // Has multiple hostnames
        if (is_array($hostname)) {
            $hostname_lower = array_map(strtolower(...), $hostname);
            return in_array(strtolower($this->http_host), $hostname_lower, true);
        }
        return strtolower($this->http_host) === strtolower($hostname);
    }
    private function process_array_callable_syntax(string $from, array $to): string
    {
        // [classname, method]
        // eg, [Home::class, 'index']
        if (is_callable($to, true, $callable_name)) {
            // If the route has placeholders, add params automatically.
            $params = $this->get_method_params($from);
            return '\\' . $callable_name . $params;
        }
        // [[classname, method], params]
        // eg, [[Home::class, 'index'], '$1/$2']
        if (isset($to[0], $to[1]) && is_callable($to[0], true, $callable_name) && is_string($to[1])) {
            $to = '\\' . $callable_name . '/' . $to[1];
        }
        return $to;
    }
    /**
     * Returns the method param string like `/$1/$2` for placeholders
     */
    private function get_method_params(string $from): string
    {
        preg_match_all('/\(.+?\)/', $from, $matches);
        $count = count($matches[0]);
        $params = '';
        for ($i = 1; $i <= $count; $i++) {
            $params .= '/$' . $i;
        }
        return $params;
    }
    /**
     * Compares the subdomain(s) passed in against the current subdomain
     * on this page request.
     *
     * @param list<string>|string $subdomains
     */
    private function check_subdomains($subdomains): bool
    {
        // CLI calls can't be on subdomain.
        if (!isset($this->http_host)) {
            return false;
        }
        if ($this->current_subdomain === null) {
            $this->current_subdomain = parse_subdomain($this->http_host);
        }
        if (!is_array($subdomains)) {
            $subdomains = [$subdomains];
        }
        // Routes can be limited to any sub-domain. In that case, though,
        // it does require a sub-domain to be present.
        if (!empty($this->current_subdomain) && in_array('*', $subdomains, true)) {
            return true;
        }
        return in_array($this->current_subdomain, $subdomains, true);
    }
    /**
     * Reset the routes, so that a test case can provide the
     * explicit ones needed for it.
     *
     * @return void
     */
    public function reset_routes()
    {
        $this->routes = $this->routes_names = ['*' => []];
        foreach ($this->default_http_methods as $verb) {
            $this->routes[$verb] = [];
            $this->routes_names[$verb] = [];
        }
        $this->routes_options = [];
        $this->prioritize_detected = false;
        $this->did_discover = false;
    }
    /**
     * Load routes options based on verb
     *
     * @return array<
     *     string,
     *     array{
     *         filter?: list<string>|string, namespace?: string, hostname?: string,
     *         subdomain?: string, offset?: int, priority?: int, as?: string,
     *         redirect?: int
     *     }
     * >
     */
    protected function load_routes_options(?string $verb = null): array
    {
        $verb ??= $this->get_http_verb();
        $options = $this->routes_options[$verb] ?? [];
        if (isset($this->routes_options['*'])) {
            foreach ($this->routes_options['*'] as $key => $val) {
                if (isset($options[$key])) {
                    $extra_options = array_diff_key($val, $options[$key]);
                    $options[$key] = array_merge($options[$key], $extra_options);
                } else {
                    $options[$key] = $val;
                }
            }
        }
        return $options;
    }
    /**
     * Enable or Disable sorting routes by priority
     *
     * @param bool $enabled The value status
     *
     * @return $this
     */
    public function set_prioritize(bool $enabled = true)
    {
        $this->prioritize = $enabled;
        return $this;
    }
    /**
     * Get all controllers in Route Handlers
     *
     * @param string|null $verb HTTP verb like `GET`,`POST` or `*` or `CLI`.
     *                          `'*'` returns all controllers in any verb.
     *
     * @return list<string> controller name list
     *
     * @interal
     */
    public function get_registered_controllers(?string $verb = '*'): array
    {
        if ($verb !== '*' && $verb === strtolower($verb)) {
            @trigger_error('Passing lowercase HTTP method "' . $verb . '" is deprecated.' . ' Use uppercase HTTP method like "' . strtoupper($verb) . '".', E_USER_DEPRECATED);
        }
        /**
         * @deprecated 4.5.0
         * @TODO Remove this in the future.
         */
        $verb = strtoupper($verb);
        $controllers = [];
        if ($verb === '*') {
            foreach ($this->default_http_methods as $tmp_verb) {
                foreach ($this->routes[$tmp_verb] as $route) {
                    $controller = $this->get_controller_name($route['handler']);
                    if ($controller !== null) {
                        $controllers[] = $controller;
                    }
                }
            }
        } else {
            $routes = $this->get_routes($verb);
            foreach ($routes as $handler) {
                $controller = $this->get_controller_name($handler);
                if ($controller !== null) {
                    $controllers[] = $controller;
                }
            }
        }
        return array_unique($controllers);
    }
    /**
     * @param (Closure(mixed...): (ResponseInterface|string|void))|string $handler Handler
     *
     * @return string|null Controller classname
     */
    private function get_controller_name($handler)
    {
        if (!is_string($handler)) {
            return null;
        }
        [$controller] = explode('::', $handler, 2);
        return $controller;
    }
    /**
     * Set The flag that limit or not the routes with {locale} placeholder to App::$supportedLocales
     */
    public function use_supported_locales_only(bool $use_only): self
    {
        $this->use_supported_locales_only = $use_only;
        return $this;
    }
    /**
     * Get the flag that limit or not the routes with {locale} placeholder to App::$supportedLocales
     */
    public function should_use_supported_locales_only(): bool
    {
        return $this->use_supported_locales_only;
    }
}