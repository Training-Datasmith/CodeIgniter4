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
namespace Code_Igniter;

use Closure;
use Code_Igniter\Cache\Response_Cache;
use Code_Igniter\Debug\Timer;
use Code_Igniter\Events\Events;
use Code_Igniter\Exceptions\LogicException;
use Code_Igniter\Exceptions\Page_Not_Found_Exception;
use Code_Igniter\Filters\Filters;
use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Download_Response;
use Code_Igniter\HTTP\Exceptions\Redirect_Exception;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Method;
use Code_Igniter\HTTP\Redirect_Response;
use Code_Igniter\HTTP\Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Responsable_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\HTTP\URI;
use Code_Igniter\Router\Route_Collection_Interface;
use Code_Igniter\Router\Router;
use Config\App;
use Config\Cache;
use Config\Feature;
use Config\Kint as KintConfig;
use Config\Services;
use Exception;
use Kint;
use Kint\Renderer\Cli_Renderer;
use Kint\Renderer\Rich_Renderer;
use Locale;
use Throwable;
/**
 * This class is the core of the framework, and will analyse the
 * request, route it to a controller, and send back the response.
 * Of course, there are variations to that flow, but this is the brains.
 *
 * @see \CodeIgniter\CodeIgniterTest
 */
class Code_Igniter
{
    /**
     * The current version of CodeIgniter Framework
     */
    public const CI_VERSION = '4.7.1-dev';
    /**
     * App startup time.
     *
     * @var float|null
     */
    protected $start_time;
    /**
     * Total app execution time
     *
     * @var float
     */
    protected $total_time;
    /**
     * Main application configuration
     *
     * @var App
     */
    protected $config;
    /**
     * Timer instance.
     *
     * @var Timer
     */
    protected $benchmark;
    /**
     * Current request.
     *
     * @var CLIRequest|IncomingRequest|null
     */
    protected $request;
    /**
     * Current response.
     *
     * @var ResponseInterface|null
     */
    protected $response;
    /**
     * Router to use.
     *
     * @var Router|null
     */
    protected $router;
    /**
     * Controller to use.
     *
     * @var (Closure(mixed...): ResponseInterface|string)|string|null
     */
    protected $controller;
    /**
     * Controller method to invoke.
     *
     * @var string|null
     */
    protected $method;
    /**
     * Output handler to use.
     *
     * @var string|null
     */
    protected $output;
    /**
     * Cache expiration time
     *
     * @var int seconds
     *
     * @deprecated 4.4.0 Moved to ResponseCache::$ttl. No longer used.
     */
    protected static $cache_ttl = 0;
    /**
     * Context
     *  web:     Invoked by HTTP request
     *  php-cli: Invoked by CLI via `php public/index.php`
     *
     * @var 'php-cli'|'web'|null
     */
    protected ?string $context = null;
    /**
     * Whether to enable Control Filters.
     */
    protected bool $enable_filters = true;
    /**
     * Whether to return Response object or send response.
     *
     * @deprecated 4.4.0 No longer used.
     */
    protected bool $return_response = false;
    /**
     * Application output buffering level
     */
    protected int $buffer_level;
    /**
     * Web Page Caching
     */
    protected Response_Cache $page_cache;
    /**
     * Constructor.
     */
    public function __construct(App $config)
    {
        $this->start_time = microtime(true);
        $this->config = $config;
        $this->page_cache = Services::responsecache();
    }
    /**
     * Handles some basic app and environment setup.
     *
     * @return void
     */
    public function initialize()
    {
        // Set default locale on the server
        Locale::set_default($this->config->default_locale ?? 'en');
        // Set default timezone on the server
        date_default_timezone_set($this->config->app_timezone ?? 'UTC');
    }
    /**
     * Reset request-specific state for worker mode.
     * Clears all request/response data to prepare for the next request.
     */
    public function reset_for_worker_mode(): void
    {
        $this->request = null;
        $this->response = null;
        $this->router = null;
        $this->controller = null;
        $this->method = null;
        $this->output = null;
        // Reset timing
        $this->start_time = null;
        $this->total_time = 0;
    }
    /**
     * Initializes Kint
     *
     * @return void
     *
     * @deprecated 4.5.0 Moved to Autoloader.
     */
    protected function initialize_kint()
    {
        if (CI_DEBUG) {
            $this->autoload_kint();
            $this->configure_kint();
        } elseif (class_exists(Kint::class)) {
            // In case that Kint is already loaded via Composer.
            Kint::$enabled_mode = false;
            // @codeCoverageIgnore
        }
        helper('kint');
    }
    /**
     * @deprecated 4.5.0 Moved to Autoloader.
     */
    private function autoload_kint(): void
    {
        // If we have KINT_DIR it means it's already loaded via composer
        if (!defined('KINT_DIR')) {
            spl_autoload_register(function ($class): void {
                $class = explode('\\', $class);
                if (array_shift($class) !== 'Kint') {
                    return;
                }
                $file = SYSTEMPATH . 'ThirdParty/Kint/' . implode('/', $class) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            });
            require_once SYSTEMPATH . 'ThirdParty/Kint/init.php';
        }
    }
    /**
     * @deprecated 4.5.0 Moved to Autoloader.
     */
    private function configure_kint(): void
    {
        $config = new Kint_Config();
        Kint::$depth_limit = $config->max_depth;
        Kint::$display_called_from = $config->display_called_from;
        Kint::$expanded = $config->expanded;
        if (isset($config->plugins) && is_array($config->plugins)) {
            Kint::$plugins = $config->plugins;
        }
        $csp = Services::csp();
        if ($csp->enabled()) {
            Rich_Renderer::$js_nonce = $csp->get_script_nonce();
            Rich_Renderer::$css_nonce = $csp->get_style_nonce();
        }
        Rich_Renderer::$theme = $config->rich_theme;
        Rich_Renderer::$folder = $config->rich_folder;
        if (isset($config->rich_object_plugins) && is_array($config->rich_object_plugins)) {
            Rich_Renderer::$value_plugins = $config->rich_object_plugins;
        }
        if (isset($config->rich_tab_plugins) && is_array($config->rich_tab_plugins)) {
            Rich_Renderer::$tab_plugins = $config->rich_tab_plugins;
        }
        Cli_Renderer::$cli_colors = $config->cli_colors;
        Cli_Renderer::$force_utf8 = $config->cli_force_utf8;
        Cli_Renderer::$detect_width = $config->cli_detect_width;
        Cli_Renderer::$min_terminal_width = $config->cli_min_width;
    }
    /**
     * Launch the application!
     *
     * This is "the loop" if you will. The main entry point into the script
     * that gets the required class instances, fires off the filters,
     * tries to route the response, loads the controller and generally
     * makes all the pieces work together.
     *
     * @param bool $returnResponse Used for testing purposes only.
     *
     * @return ResponseInterface|null
     */
    public function run(?Route_Collection_Interface $routes = null, bool $return_response = false)
    {
        if ($this->context === null) {
            throw new LogicException('Context must be set before run() is called. If you are upgrading from 4.1.x, ' . 'you need to merge `public/index.php` and `spark` file from `vendor/codeigniter4/framework`.');
        }
        $this->page_cache->set_ttl(0);
        $this->buffer_level = ob_get_level();
        $this->start_benchmark();
        $this->get_request_object();
        $this->get_response_object();
        Events::trigger('pre_system');
        $this->benchmark->stop('bootstrap');
        $this->benchmark->start('required_before_filters');
        // Start up the filters
        $filters = Services::filters();
        // Run required before filters
        $possible_response = $this->run_required_before_filters($filters);
        // If a ResponseInterface instance is returned then send it back to the client and stop
        if ($possible_response instanceof Response_Interface) {
            $this->response = $possible_response;
        } else {
            try {
                $this->response = $this->handle_request($routes, config(Cache::class), $return_response);
            } catch (Responsable_Interface $e) {
                $this->output_buffering_end();
                $this->response = $e->get_response();
            } catch (Page_Not_Found_Exception $e) {
                $this->response = $this->display404errors($e);
            } catch (Throwable $e) {
                $this->output_buffering_end();
                throw $e;
            }
        }
        $this->run_required_after_filters($filters);
        // Is there a post-system event?
        Events::trigger('post_system');
        if ($return_response) {
            return $this->response;
        }
        $this->send_response();
        return null;
    }
    /**
     * Run required before filters.
     */
    private function run_required_before_filters(Filters $filters): ?Response_Interface
    {
        $possible_response = $filters->run_required('before');
        $this->benchmark->stop('required_before_filters');
        // If a ResponseInterface instance is returned then send it back to the client and stop
        if ($possible_response instanceof Response_Interface) {
            return $possible_response;
        }
        return null;
    }
    /**
     * Run required after filters.
     */
    private function run_required_after_filters(Filters $filters): void
    {
        $filters->set_response($this->response);
        // Run required after filters
        $this->benchmark->start('required_after_filters');
        $response = $filters->run_required('after');
        $this->benchmark->stop('required_after_filters');
        if ($response instanceof Response_Interface) {
            $this->response = $response;
        }
    }
    /**
     * Invoked via php-cli command?
     */
    private function is_php_cli(): bool
    {
        return $this->context === 'php-cli';
    }
    /**
     * Web access?
     */
    private function is_web(): bool
    {
        return $this->context === 'web';
    }
    /**
     * Disables Controller Filters.
     */
    public function disable_filters(): void
    {
        $this->enable_filters = false;
    }
    /**
     * Handles the main request logic and fires the controller.
     *
     * @return ResponseInterface
     *
     * @throws PageNotFoundException
     * @throws RedirectException
     *
     * @deprecated $returnResponse is deprecated.
     */
    protected function handle_request(?Route_Collection_Interface $routes, Cache $cache_config, bool $return_response = false)
    {
        if ($this->request instanceof Incoming_Request && $this->request->get_method() === 'CLI') {
            return $this->response->set_status_code(405)->set_body('Method Not Allowed');
        }
        $route_filters = $this->try_to_route_it($routes);
        // $uri is URL-encoded.
        $uri = $this->request->get_path();
        if ($this->enable_filters) {
            /** @var Filters $filters */
            $filters = service('filters');
            // If any filters were specified within the routes file,
            // we need to ensure it's active for the current request
            if ($route_filters !== null) {
                $filters->enable_filters($route_filters, 'before');
                $old_filter_order = config(Feature::class)->old_filter_order ?? false;
                if (!$old_filter_order) {
                    $route_filters = array_reverse($route_filters);
                }
                $filters->enable_filters($route_filters, 'after');
            }
            // Run "before" filters
            $this->benchmark->start('before_filters');
            $possible_response = $filters->run($uri, 'before');
            $this->benchmark->stop('before_filters');
            // If a ResponseInterface instance is returned then send it back to the client and stop
            if ($possible_response instanceof Response_Interface) {
                $this->output_buffering_end();
                return $possible_response;
            }
            if ($possible_response instanceof Incoming_Request || $possible_response instanceof Cli_Request) {
                $this->request = $possible_response;
            }
        }
        $returned = $this->start_controller();
        // If startController returned a Response (from an attribute or Closure), use it
        if ($returned instanceof Response_Interface) {
            $this->gather_output($cache_config, $returned);
        } elseif (!is_callable($this->controller)) {
            $controller = $this->create_controller();
            if (!method_exists($controller, '_remap') && !is_callable([$controller, $this->method], false)) {
                throw Page_Not_Found_Exception::for_method_not_found($this->method);
            }
            // Is there a "post_controller_constructor" event?
            Events::trigger('post_controller_constructor');
            $returned = $this->run_controller($controller);
        } else {
            $this->benchmark->stop('controller_constructor');
            $this->benchmark->stop('controller');
        }
        // If $returned is a string, then the controller output something,
        // probably a view, instead of echoing it directly. Send it along
        // so it can be used with the output.
        $this->gather_output($cache_config, $returned);
        if ($this->enable_filters) {
            /** @var Filters $filters */
            $filters = service('filters');
            $filters->set_response($this->response);
            // Run "after" filters
            $this->benchmark->start('after_filters');
            $response = $filters->run($uri, 'after');
            $this->benchmark->stop('after_filters');
            if ($response instanceof Response_Interface) {
                $this->response = $response;
            }
        }
        // Execute controller attributes' after() methods AFTER framework filters
        if ((config('Routing')->use_controller_attributes ?? true) === true) {
            $this->benchmark->start('route_attributes_after');
            $this->response = $this->router->execute_after_attributes($this->request, $this->response);
            $this->benchmark->stop('route_attributes_after');
        }
        // Skip unnecessary processing for special Responses.
        if (!$this->response instanceof Download_Response && !$this->response instanceof Redirect_Response) {
            // Save our current URI as the previous URI in the session
            // for safer, more accurate use with `previous_url()` helper function.
            $this->store_previous_url(current_url(true));
        }
        unset($uri);
        return $this->response;
    }
    /**
     * You can load different configurations depending on your
     * current environment. Setting the environment also influences
     * things like logging and error reporting.
     *
     * This can be set to anything, but default usage is:
     *
     *     development
     *     testing
     *     production
     *
     * @codeCoverageIgnore
     *
     * @return void
     *
     * @deprecated 4.4.0 No longer used. Moved to index.php and spark.
     */
    protected function detect_environment()
    {
        // Make sure ENVIRONMENT isn't already set by other means.
        if (!defined('ENVIRONMENT')) {
            define('ENVIRONMENT', env('CI_ENVIRONMENT', 'production'));
        }
    }
    /**
     * Load any custom boot files based upon the current environment.
     *
     * If no boot file exists, we shouldn't continue because something
     * is wrong. At the very least, they should have error reporting setup.
     *
     * @return void
     *
     * @deprecated 4.5.0 Moved to system/bootstrap.php.
     */
    protected function bootstrap_environment()
    {
        if (is_file(APPPATH . 'Config/Boot/' . ENVIRONMENT . '.php')) {
            require_once APPPATH . 'Config/Boot/' . ENVIRONMENT . '.php';
        } else {
            // @codeCoverageIgnoreStart
            header('HTTP/1.1 503 Service Unavailable.', true, 503);
            echo 'The application environment is not set correctly.';
            exit(EXIT_ERROR);
            // EXIT_ERROR
            // @codeCoverageIgnoreEnd
        }
    }
    /**
     * Start the Benchmark
     *
     * The timer is used to display total script execution both in the
     * debug toolbar, and potentially on the displayed page.
     *
     * @return void
     */
    protected function start_benchmark()
    {
        if ($this->start_time === null) {
            $this->start_time = microtime(true);
        }
        $this->benchmark = Services::timer();
        $this->benchmark->start('total_execution', $this->start_time);
        $this->benchmark->start('bootstrap');
    }
    /**
     * Sets a Request object to be used for this request.
     * Used when running certain tests.
     *
     * @param CLIRequest|IncomingRequest $request
     *
     * @return $this
     *
     * @internal Used for testing purposes only.
     * @testTag
     */
    public function set_request($request)
    {
        $this->request = $request;
        return $this;
    }
    /**
     * Get our Request object, (either IncomingRequest or CLIRequest).
     *
     * @return void
     */
    protected function get_request_object()
    {
        if ($this->request instanceof Request) {
            $this->spoof_request_method();
            return;
        }
        if ($this->is_php_cli()) {
            Services::create_request($this->config, true);
        } else {
            Services::create_request($this->config);
        }
        $this->request = service('request');
        $this->spoof_request_method();
    }
    /**
     * Get our Response object, and set some default values, including
     * the HTTP protocol version and a default successful response.
     *
     * @return void
     */
    protected function get_response_object()
    {
        $this->response = Services::response($this->config);
        if ($this->is_web()) {
            $this->response->set_protocol_version($this->request->get_protocol_version());
        }
        // Assume success until proven otherwise.
        $this->response->set_status_code(200);
    }
    /**
     * Force Secure Site Access? If the config value 'forceGlobalSecureRequests'
     * is true, will enforce that all requests to this site are made through
     * HTTPS. Will redirect the user to the current page with HTTPS, as well
     * as set the HTTP Strict Transport Security header for those browsers
     * that support it.
     *
     * @param int $duration How long the Strict Transport Security
     *                      should be enforced for this URL.
     *
     * @return void
     *
     * @deprecated 4.5.0 No longer used. Moved to ForceHTTPS filter.
     */
    protected function force_secure_access($duration = 31536000)
    {
        if ($this->config->force_global_secure_requests !== true) {
            return;
        }
        force_https($duration, $this->request, $this->response);
    }
    /**
     * Determines if a response has been cached for the given URI.
     *
     * @return false|ResponseInterface
     *
     * @throws Exception
     *
     * @deprecated 4.5.0 PageCache required filter is used. No longer used.
     * @deprecated 4.4.2 The parameter $config is deprecated. No longer used.
     */
    public function display_cache(Cache $config)
    {
        $cached_response = $this->page_cache->get($this->request, $this->response);
        if ($cached_response instanceof Response_Interface) {
            $this->response = $cached_response;
            $this->total_time = $this->benchmark->get_elapsed_time('total_execution');
            $output = $this->display_performance_metrics($cached_response->get_body());
            $this->response->set_body($output);
            return $this->response;
        }
        return false;
    }
    /**
     * Tells the app that the final output should be cached.
     *
     * @deprecated 4.4.0 Moved to ResponseCache::setTtl(). No longer used.
     *
     * @return void
     */
    public static function cache(int $time)
    {
        static::$cache_ttl = $time;
    }
    /**
     * Caches the full response from the current request. Used for
     * full-page caching for very high performance.
     *
     * @return bool
     *
     * @deprecated 4.4.0 No longer used.
     */
    public function cache_page(Cache $config)
    {
        $headers = [];
        foreach ($this->response->headers() as $header) {
            $headers[$header->get_name()] = $header->get_value_line();
        }
        return cache()->save($this->generate_cache_name($config), serialize(['headers' => $headers, 'output' => $this->output]), static::$cache_ttl);
    }
    /**
     * Returns an array with our basic performance stats collected.
     */
    public function get_performance_stats(): array
    {
        // After filter debug toolbar requires 'total_execution'.
        $this->total_time = $this->benchmark->get_elapsed_time('total_execution');
        return ['startTime' => $this->start_time, 'totalTime' => $this->total_time];
    }
    /**
     * Generates the cache name to use for our full-page caching.
     *
     * @deprecated 4.4.0 No longer used.
     */
    protected function generate_cache_name(Cache $config): string
    {
        if ($this->request instanceof Cli_Request) {
            return md5($this->request->get_path());
        }
        $uri = clone $this->request->get_uri();
        $query = $config->cache_query_string ? $uri->get_query(is_array($config->cache_query_string) ? ['only' => $config->cache_query_string] : []) : '';
        return md5((string) $uri->set_fragment('')->set_query($query));
    }
    /**
     * Replaces the elapsed_time and memory_usage tag.
     *
     * @deprecated 4.5.0 PerformanceMetrics required filter is used. No longer used.
     */
    public function display_performance_metrics(string $output): string
    {
        return str_replace(['{elapsed_time}', '{memory_usage}'], [(string) $this->total_time, number_format(memory_get_peak_usage() / 1024 / 1024, 3)], $output);
    }
    /**
     * Try to Route It - As it sounds like, works with the router to
     * match a route against the current URI. If the route is a
     * "redirect route", will also handle the redirect.
     *
     * @param RouteCollectionInterface|null $routes A collection interface to use in place
     *                                              of the config file.
     *
     * @return list<string>|string|null Route filters, that is, the filters specified in the routes file
     *
     * @throws RedirectException
     */
    protected function try_to_route_it(?Route_Collection_Interface $routes = null)
    {
        $this->benchmark->start('routing');
        if (!$routes instanceof Route_Collection_Interface) {
            $routes = service('routes')->load_routes();
        }
        // $routes is defined in Config/Routes.php
        $this->router = Services::router($routes, $this->request);
        // $uri is URL-encoded.
        $uri = $this->request->get_path();
        $this->output_buffering_start();
        $this->controller = $this->router->handle($uri);
        $this->method = $this->router->method_name();
        // If a {locale} segment was matched in the final route,
        // then we need to set the correct locale on our Request.
        if ($this->router->has_locale()) {
            $this->request->set_locale($this->router->get_locale());
        }
        $this->benchmark->stop('routing');
        return $this->router->get_filters();
    }
    /**
     * Determines the path to use for us to try to route to, based
     * on the CLI/IncomingRequest path.
     *
     * @return string
     *
     * @deprecated 4.5.0 No longer used.
     */
    protected function determine_path()
    {
        return $this->request->get_path();
    }
    /**
     * Now that everything has been setup, this method attempts to run the
     * controller method and make the script go. If it's not able to, will
     * show the appropriate Page Not Found error.
     *
     * @return ResponseInterface|string|null
     */
    protected function start_controller()
    {
        $this->benchmark->start('controller');
        $this->benchmark->start('controller_constructor');
        // Is it routed to a Closure?
        if (is_object($this->controller) && $this->controller::class === 'Closure') {
            $controller = $this->controller;
            return $controller(...$this->router->params());
        }
        // No controller specified - we don't know what to do now.
        if (!isset($this->controller)) {
            throw Page_Not_Found_Exception::for_empty_controller();
        }
        // Try to autoload the class
        if (!class_exists($this->controller, true) || $this->method[0] === '_' && $this->method !== '__invoke') {
            throw Page_Not_Found_Exception::for_controller_not_found($this->controller, $this->method);
        }
        // Execute route attributes' before() methods
        // This runs after routing/validation but BEFORE expensive controller instantiation
        if ((config('Routing')->use_controller_attributes ?? true) === true) {
            $this->benchmark->start('route_attributes_before');
            $attribute_response = $this->router->execute_before_attributes($this->request);
            $this->benchmark->stop('route_attributes_before');
            // If attribute returns a Response, short-circuit
            if ($attribute_response instanceof Response_Interface) {
                $this->benchmark->stop('controller_constructor');
                $this->benchmark->stop('controller');
                return $attribute_response;
            }
            // If attribute returns a modified Request, use it
            if ($attribute_response instanceof Request_Interface) {
                $this->request = $attribute_response;
            }
        }
        return null;
    }
    /**
     * Instantiates the controller class.
     *
     * @return Controller
     */
    protected function create_controller()
    {
        assert(is_string($this->controller));
        $class = new $this->controller();
        $class->init_controller($this->request, $this->response, Services::logger());
        $this->benchmark->stop('controller_constructor');
        return $class;
    }
    /**
     * Runs the controller, allowing for _remap methods to function.
     *
     * CI4 supports three types of requests:
     *  1. Web: URI segments become parameters, sent to Controllers via Routes,
     *      output controlled by Headers to browser
     *  2. PHP CLI: accessed by CLI via php public/index.php, arguments become URI segments,
     *      sent to Controllers via Routes, output varies
     *
     * @param Controller $class
     *
     * @return false|ResponseInterface|string|void
     */
    protected function run_controller($class)
    {
        // This is a Web request or PHP CLI request
        $params = $this->router->params();
        // The controller method param types may not be string.
        // So cannot set `declare(strict_types=1)` in this file.
        $output = method_exists($class, '_remap') ? $class->_remap($this->method, ...$params) : $class->{$this->method}(...$params);
        $this->benchmark->stop('controller');
        return $output;
    }
    /**
     * Displays a 404 Page Not Found error. If set, will try to
     * call the 404Override controller/method that was set in routing config.
     *
     * @return ResponseInterface|void
     */
    protected function display404errors(Page_Not_Found_Exception $e)
    {
        $this->response->set_status_code($e->get_code());
        // Is there a 404 Override available?
        $override = $this->router->get404Override();
        if ($override !== null) {
            $returned = null;
            if ($override instanceof Closure) {
                echo $override($e->get_message());
            } elseif (is_array($override)) {
                $this->benchmark->start('controller');
                $this->benchmark->start('controller_constructor');
                $this->controller = $override[0];
                $this->method = $override[1];
                $controller = $this->create_controller();
                $returned = $controller->{$this->method}($e->get_message());
                $this->benchmark->stop('controller');
            }
            unset($override);
            $cache_config = config(Cache::class);
            $this->gather_output($cache_config, $returned);
            return $this->response;
        }
        $this->output_buffering_end();
        // Throws new PageNotFoundException and remove exception message on production.
        throw Page_Not_Found_Exception::for_page_not_found(ENVIRONMENT !== 'production' || !$this->is_web() ? $e->get_message() : null);
    }
    /**
     * Gathers the script output from the buffer, replaces some execution
     * time tag in the output and displays the debug toolbar, if required.
     *
     * @param Cache|null                    $cacheConfig Deprecated. No longer used.
     * @param ResponseInterface|string|null $returned
     *
     * @deprecated $cacheConfig is deprecated.
     *
     * @return void
     */
    protected function gather_output(?Cache $cache_config = null, $returned = null)
    {
        $this->output = $this->output_buffering_end();
        if ($returned instanceof Download_Response) {
            $this->response = $returned;
            return;
        }
        // If the controller returned a response object,
        // we need to grab the body from it so it can
        // be added to anything else that might have been
        // echoed already.
        // We also need to save the instance locally
        // so that any status code changes, etc, take place.
        if ($returned instanceof Response_Interface) {
            $this->response = $returned;
            $returned = $returned->get_body();
        }
        if (is_string($returned)) {
            $this->output .= $returned;
        }
        $this->response->set_body($this->output);
    }
    /**
     * If we have a session object to use, store the current URI
     * as the previous URI. This is called just prior to sending the
     * response to the client, and will make it available next request.
     *
     * This helps provider safer, more reliable previous_url() detection.
     *
     * @param string|URI $uri
     *
     * @return void
     */
    public function store_previous_url($uri)
    {
        // Ignore CLI requests
        if (!$this->is_web()) {
            return;
        }
        // Ignore AJAX requests
        if (method_exists($this->request, 'isAJAX') && $this->request->is_ajax()) {
            return;
        }
        // Ignore unroutable responses
        if ($this->response instanceof Download_Response || $this->response instanceof Redirect_Response) {
            return;
        }
        // Ignore non-HTML responses
        if (!str_contains($this->response->get_header_line('Content-Type'), 'text/html')) {
            return;
        }
        // This is mainly needed during testing...
        if (is_string($uri)) {
            $uri = new URI($uri);
        }
        if (isset($_SESSION)) {
            session()->set('_ci_previous_url', URI::create_uri_string($uri->get_scheme(), $uri->get_authority(), $uri->get_path(), $uri->get_query(), $uri->get_fragment()));
        }
    }
    /**
     * Modifies the Request Object to use a different method if a POST
     * variable called _method is found.
     *
     * @return void
     */
    public function spoof_request_method()
    {
        // Only works with POSTED forms
        if ($this->request->get_method() !== Method::POST) {
            return;
        }
        $method = $this->request->get_post('_method');
        if ($method === null) {
            return;
        }
        // Only allows PUT, PATCH, DELETE
        if (in_array($method, [Method::PUT, Method::PATCH, Method::DELETE], true)) {
            $this->request = $this->request->set_method($method);
        }
    }
    /**
     * Sends the output of this request back to the client.
     * This is what they've been waiting for!
     *
     * @return void
     */
    protected function send_response()
    {
        $this->response->send();
    }
    /**
     * Exits the application, setting the exit code for CLI-based applications
     * that might be watching.
     *
     * Made into a separate method so that it can be mocked during testing
     * without actually stopping script execution.
     *
     * @param int $code
     *
     * @deprecated 4.4.0 No longer Used. Moved to index.php.
     *
     * @return void
     */
    protected function call_exit($code)
    {
        exit($code);
        // @codeCoverageIgnore
    }
    /**
     * Sets the app context.
     *
     * @param 'php-cli'|'web' $context
     *
     * @return $this
     */
    public function set_context(string $context)
    {
        $this->context = $context;
        return $this;
    }
    protected function output_buffering_start(): void
    {
        $this->buffer_level = ob_get_level();
        ob_start();
    }
    protected function output_buffering_end(): string
    {
        $buffer = '';
        while (ob_get_level() > $this->buffer_level) {
            $buffer .= ob_get_contents();
            ob_end_clean();
        }
        return $buffer;
    }
}