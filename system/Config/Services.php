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
namespace Code_Igniter\Config;

use Code_Igniter\Cache\Cache_Factory;
use Code_Igniter\Cache\Cache_Interface;
use Code_Igniter\Cache\Response_Cache;
use Code_Igniter\CLI\Commands;
use Code_Igniter\Code_Igniter;
use Code_Igniter\Database\Connection_Interface;
use Code_Igniter\Database\Migration_Runner;
use Code_Igniter\Debug\Exceptions;
use Code_Igniter\Debug\Iterator;
use Code_Igniter\Debug\Timer;
use Code_Igniter\Debug\Toolbar;
use Code_Igniter\Email\Email;
use Code_Igniter\Encryption\Encrypter_Interface;
use Code_Igniter\Encryption\Encryption;
use Code_Igniter\Filters\Filters;
use Code_Igniter\Format\Format;
use Code_Igniter\Honeypot\Honeypot;
use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Content_Security_Policy;
use Code_Igniter\HTTP\Curl_Request;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Negotiate;
use Code_Igniter\HTTP\Redirect_Response;
use Code_Igniter\HTTP\Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\HTTP\Site_Uri_Factory;
use Code_Igniter\HTTP\URI;
use Code_Igniter\HTTP\User_Agent;
use Code_Igniter\Images\Handlers\Base_Handler;
use Code_Igniter\Language\Language;
use Code_Igniter\Log\Logger;
use Code_Igniter\Pager\Pager;
use Code_Igniter\Router\Route_Collection;
use Code_Igniter\Router\Route_Collection_Interface;
use Code_Igniter\Router\Router;
use Code_Igniter\Security\Security;
use Code_Igniter\Session\Handlers\Base_Handler as SessionBaseHandler;
use Code_Igniter\Session\Handlers\Database\My_Sq_Li_Handler;
use Code_Igniter\Session\Handlers\Database\Postgre_Handler;
use Code_Igniter\Session\Handlers\Database_Handler;
use Code_Igniter\Session\Session;
use Code_Igniter\Superglobals;
use Code_Igniter\Throttle\Throttler;
use Code_Igniter\Typography\Typography;
use Code_Igniter\Validation\Validation;
use Code_Igniter\Validation\Validation_Interface;
use Code_Igniter\View\Cell;
use Code_Igniter\View\Parser;
use Code_Igniter\View\Renderer_Interface;
use Code_Igniter\View\View;
use Config\App;
use Config\Cache;
use Config\Content_Security_Policy as ContentSecurityPolicyConfig;
use Config\Content_Security_Policy as CSPConfig;
use Config\Database;
use Config\Email as EmailConfig;
use Config\Encryption as EncryptionConfig;
use Config\Exceptions as ExceptionsConfig;
use Config\Filters as FiltersConfig;
use Config\Format as FormatConfig;
use Config\Honeypot as HoneypotConfig;
use Config\Images;
use Config\Logger as LoggerConfig;
use Config\Migrations;
use Config\Modules;
use Config\Pager as PagerConfig;
use Config\Paths;
use Config\Routing;
use Config\Security as SecurityConfig;
use Config\Services as AppServices;
use Config\Session as SessionConfig;
use Config\Toolbar as ToolbarConfig;
use Config\Validation as ValidationConfig;
use Config\View as ViewConfig;
use InvalidArgumentException;
use Locale;
/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This is used in place of a Dependency Injection container primarily
 * due to its simplicity, which allows a better long-term maintenance
 * of the applications built on top of CodeIgniter. A bonus side-effect
 * is that IDEs are able to determine what class you are calling
 * whereas with DI Containers there usually isn't a way for them to do this.
 *
 * @see http://blog.ircmaxell.com/2015/11/simple-easy-risk-and-change.html
 * @see http://www.infoq.com/presentations/Simple-Made-Easy
 * @see \CodeIgniter\Config\ServicesTest
 */
class Services extends Base_Service
{
    /**
     * The cache class provides a simple way to store and retrieve
     * complex data for later.
     *
     * @return CacheInterface
     */
    public static function cache(?Cache $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('cache', $config);
        }
        $config ??= config(Cache::class);
        return Cache_Factory::get_handler($config);
    }
    /**
     * The CLI Request class provides for ways to interact with
     * a command line request.
     *
     * @return CLIRequest
     *
     * @internal
     */
    public static function clirequest(?App $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('clirequest', $config);
        }
        $config ??= config(App::class);
        return new Cli_Request($config);
    }
    /**
     * CodeIgniter, the core of the framework.
     *
     * @return CodeIgniter
     */
    public static function codeigniter(?App $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('codeigniter', $config);
        }
        $config ??= config(App::class);
        return new Code_Igniter($config);
    }
    /**
     * The commands utility for running and working with CLI commands.
     *
     * @return Commands
     */
    public static function commands(bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('commands');
        }
        return new Commands();
    }
    /**
     * Content Security Policy
     *
     * @return ContentSecurityPolicy
     */
    public static function csp(?Csp_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('csp', $config);
        }
        $config ??= config(Content_Security_Policy_Config::class);
        return new Content_Security_Policy($config);
    }
    /**
     * The CURL Request class acts as a simple HTTP client for interacting
     * with other servers, typically through APIs.
     *
     * @return CURLRequest
     */
    public static function curlrequest(array $options = [], ?Response_Interface $response = null, ?App $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('curlrequest', $options, $response, $config);
        }
        $config ??= config(App::class);
        $response ??= new Response($config);
        return new Curl_Request($config, new URI($options['baseURI'] ?? null), $response, $options);
    }
    /**
     * The Email class allows you to send email via mail, sendmail, SMTP.
     *
     * @param array|EmailConfig|null $config
     *
     * @return Email
     */
    public static function email($config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('email', $config);
        }
        if (empty($config) || !is_array($config) && !$config instanceof Email_Config) {
            $config = config(Email_Config::class);
        }
        return new Email($config);
    }
    /**
     * The Encryption class provides two-way encryption.
     *
     * @param bool $getShared
     *
     * @return EncrypterInterface Encryption handler
     */
    public static function encrypter(?Encryption_Config $config = null, $get_shared = false)
    {
        if ($get_shared === true) {
            return static::get_shared_instance('encrypter', $config);
        }
        $config ??= config(Encryption_Config::class);
        $encryption = new Encryption($config);
        return $encryption->initialize($config);
    }
    /**
     * The Exceptions class holds the methods that handle:
     *
     *  - set_exception_handler
     *  - set_error_handler
     *  - register_shutdown_function
     *
     * @return Exceptions
     */
    public static function exceptions(?Exceptions_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('exceptions', $config);
        }
        $config ??= config(Exceptions_Config::class);
        return new Exceptions($config);
    }
    /**
     * Filters allow you to run tasks before and/or after a controller
     * is executed. During before filters, the request can be modified,
     * and actions taken based on the request, while after filters can
     * act on or modify the response itself before it is sent to the client.
     *
     * @return Filters
     */
    public static function filters(?Filters_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('filters', $config);
        }
        $config ??= config(Filters_Config::class);
        return new Filters($config, App_Services::get('request'), App_Services::get('response'));
    }
    /**
     * The Format class is a convenient place to create Formatters.
     *
     * @return Format
     */
    public static function format(?Format_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('format', $config);
        }
        $config ??= config(Format_Config::class);
        return new Format($config);
    }
    /**
     * The Honeypot provides a secret input on forms that bots should NOT
     * fill in, providing an additional safeguard when accepting user input.
     *
     * @return Honeypot
     */
    public static function honeypot(?Honeypot_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('honeypot', $config);
        }
        $config ??= config(Honeypot_Config::class);
        return new Honeypot($config);
    }
    /**
     * Acts as a factory for ImageHandler classes and returns an instance
     * of the handler. Used like service('image')->withFile($path)->rotate(90)->save();
     *
     * @return BaseHandler
     */
    public static function image(?string $handler = null, ?Images $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('image', $handler, $config);
        }
        $config ??= config(Images::class);
        assert($config instanceof Images);
        $handler = in_array($handler, [null, '', '0'], true) ? $config->default_handler : $handler;
        $class = $config->handlers[$handler];
        return new $class($config);
    }
    /**
     * The Iterator class provides a simple way of looping over a function
     * and timing the results and memory usage. Used when debugging and
     * optimizing applications.
     *
     * @return Iterator
     */
    public static function iterator(bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('iterator');
        }
        return new Iterator();
    }
    /**
     * Responsible for loading the language string translations.
     *
     * @return Language
     */
    public static function language(?string $locale = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('language', $locale)->set_locale($locale);
        }
        if (App_Services::get('request') instanceof Incoming_Request) {
            $request_locale = App_Services::get('request')->get_locale();
        } else {
            $request_locale = Locale::get_default();
        }
        // Use '?:' for empty string check
        $locale = in_array($locale, [null, '', '0'], true) ? $request_locale : $locale;
        return new Language($locale);
    }
    /**
     * The Logger class is a PSR-3 compatible Logging class that supports
     * multiple handlers that process the actual logging.
     *
     * @return Logger
     */
    public static function logger(bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('logger');
        }
        return new Logger(config(Logger_Config::class));
    }
    /**
     * Return the appropriate Migration runner.
     *
     * @return MigrationRunner
     */
    public static function migrations(?Migrations $config = null, ?Connection_Interface $db = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('migrations', $config, $db);
        }
        $config ??= config(Migrations::class);
        return new Migration_Runner($config, $db);
    }
    /**
     * The Negotiate class provides the content negotiation features for
     * working the request to determine correct language, encoding, charset,
     * and more.
     *
     * @return Negotiate
     */
    public static function negotiator(?Request_Interface $request = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('negotiator', $request);
        }
        $request ??= App_Services::get('request');
        return new Negotiate($request);
    }
    /**
     * Return the ResponseCache.
     *
     * @return ResponseCache
     */
    public static function responsecache(?Cache $config = null, ?Cache_Interface $cache = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('responsecache', $config, $cache);
        }
        $config ??= config(Cache::class);
        $cache ??= App_Services::get('cache');
        return new Response_Cache($config, $cache);
    }
    /**
     * Return the appropriate pagination handler.
     *
     * @return Pager
     */
    public static function pager(?Pager_Config $config = null, ?Renderer_Interface $view = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('pager', $config, $view);
        }
        $config ??= config(Pager_Config::class);
        $view ??= App_Services::renderer(null, null, false);
        return new Pager($config, $view);
    }
    /**
     * The Parser is a simple template parser.
     *
     * @return Parser
     */
    public static function parser(?string $view_path = null, ?View_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('parser', $view_path, $config);
        }
        $view_path = in_array($view_path, [null, '', '0'], true) ? (new Paths())->view_directory : $view_path;
        $config ??= config(View_Config::class);
        return new Parser($config, $view_path, App_Services::get('locator'), CI_DEBUG, App_Services::get('logger'));
    }
    /**
     * The Renderer class is the class that actually displays a file to the user.
     * The default View class within CodeIgniter is intentionally simple, but this
     * service could easily be replaced by a template engine if the user needed to.
     *
     * @return View
     */
    public static function renderer(?string $view_path = null, ?View_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('renderer', $view_path, $config);
        }
        $view_path = in_array($view_path, [null, '', '0'], true) ? (new Paths())->view_directory : $view_path;
        $config ??= config(View_Config::class);
        return new View($config, $view_path, App_Services::get('locator'), CI_DEBUG, App_Services::get('logger'));
    }
    /**
     * Returns the current Request object.
     *
     * createRequest() injects IncomingRequest or CLIRequest.
     *
     * @return CLIRequest|IncomingRequest
     *
     * @deprecated The parameter $config and $getShared are deprecated.
     */
    public static function request(?App $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('request', $config);
        }
        // @TODO remove the following code for backward compatibility
        return App_Services::incomingrequest($config, $get_shared);
    }
    /**
     * Create the current Request object, either IncomingRequest or CLIRequest.
     *
     * This method is called from CodeIgniter::getRequestObject().
     *
     * @internal
     */
    public static function create_request(App $config, bool $is_cli = false): void
    {
        if ($is_cli) {
            $request = App_Services::clirequest($config);
        } else {
            $request = App_Services::incomingrequest($config);
            // guess at protocol if needed
            $request->set_protocol_version(static::superglobals()->server('SERVER_PROTOCOL', 'HTTP/1.1'));
        }
        // Inject the request object into Services.
        static::$instances['request'] = $request;
    }
    /**
     * The IncomingRequest class models an HTTP request.
     *
     * @return IncomingRequest
     *
     * @internal
     */
    public static function incomingrequest(?App $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('request', $config);
        }
        $config ??= config(App::class);
        return new Incoming_Request($config, App_Services::get('uri'), 'php://input', new User_Agent());
    }
    /**
     * The Response class models an HTTP response.
     *
     * @return ResponseInterface
     */
    public static function response(?App $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('response', $config);
        }
        $config ??= config(App::class);
        return new Response($config);
    }
    /**
     * The Redirect class provides nice way of working with redirects.
     *
     * @return RedirectResponse
     */
    public static function redirectresponse(?App $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('redirectresponse', $config);
        }
        $config ??= config(App::class);
        $response = new Redirect_Response($config);
        $response->set_protocol_version(App_Services::get('request')->get_protocol_version());
        return $response;
    }
    /**
     * The Routes service is a class that allows for easily building
     * a collection of routes.
     *
     * @return RouteCollection
     */
    public static function routes(bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('routes');
        }
        return new Route_Collection(App_Services::get('locator'), new Modules(), config(Routing::class));
    }
    /**
     * The Router class uses a RouteCollection's array of routes, and determines
     * the correct Controller and Method to execute.
     *
     * @return Router
     */
    public static function router(?Route_Collection_Interface $routes = null, ?Request $request = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('router', $routes, $request);
        }
        $routes ??= App_Services::get('routes');
        $request ??= App_Services::get('request');
        return new Router($routes, $request);
    }
    /**
     * The Security class provides a few handy tools for keeping the site
     * secure, most notably the CSRF protection tools.
     *
     * @return Security
     */
    public static function security(?Security_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('security', $config);
        }
        $config ??= config(Security_Config::class);
        return new Security($config);
    }
    /**
     * Return the session manager.
     *
     * @return Session
     */
    public static function session(?Session_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('session', $config);
        }
        $config ??= config(Session_Config::class);
        $logger = App_Services::get('logger');
        $driver_name = $config->driver;
        if ($driver_name === Database_Handler::class) {
            $db_group = $config->db_group ?? config(Database::class)->default_group;
            $driver_platform = Database::connect($db_group)->get_platform();
            if ($driver_platform === 'MySQLi') {
                $driver_name = My_Sq_Li_Handler::class;
            } elseif ($driver_platform === 'Postgre') {
                $driver_name = Postgre_Handler::class;
            } else {
                throw new InvalidArgumentException(sprintf('Invalid session database handler "%s" provided. Only "MySQLi" and "Postgre" are supported.', $driver_platform));
            }
        }
        if (!class_exists($driver_name) || !is_a($driver_name, Session_Base_Handler::class, true)) {
            throw new InvalidArgumentException(sprintf('Invalid session handler "%s" provided.', $driver_name));
        }
        /** @var SessionBaseHandler $driver */
        $driver = new $driver_name($config, App_Services::get('request')->get_ip_address());
        $driver->set_logger($logger);
        $session = new Session($driver, $config);
        $session->set_logger($logger);
        if (session_status() === PHP_SESSION_NONE) {
            // PHP Session emits the headers according to `session.cache_limiter`.
            // See https://www.php.net/manual/en/function.session-cache-limiter.php.
            // The headers are not managed by CI's Response class.
            // So, we remove CI's default Cache-Control header.
            App_Services::get('response')->remove_header('Cache-Control');
            $session->start();
        }
        return $session;
    }
    /**
     * The Factory for SiteURI.
     *
     * @return SiteURIFactory
     */
    public static function siteurifactory(?App $config = null, ?Superglobals $superglobals = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('siteurifactory', $config, $superglobals);
        }
        $config ??= config('App');
        $superglobals ??= App_Services::get('superglobals');
        return new Site_Uri_Factory($config, $superglobals);
    }
    /**
     * Superglobals.
     *
     * @return Superglobals
     */
    public static function superglobals(?array $server = null, ?array $get = null, ?array $post = null, ?array $cookie = null, ?array $files = null, ?array $request = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('superglobals', $server, $get, $post, $cookie, $files, $request);
        }
        return new Superglobals($server, $get, $post, $cookie, $files, $request);
    }
    /**
     * The Throttler class provides a simple method for implementing
     * rate limiting in your applications.
     *
     * @return Throttler
     */
    public static function throttler(bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('throttler');
        }
        return new Throttler(App_Services::get('cache'));
    }
    /**
     * The Timer class provides a simple way to Benchmark portions of your
     * application.
     *
     * @return Timer
     */
    public static function timer(bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('timer');
        }
        return new Timer();
    }
    /**
     * Return the debug toolbar.
     *
     * @return Toolbar
     */
    public static function toolbar(?Toolbar_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('toolbar', $config);
        }
        $config ??= config(Toolbar_Config::class);
        return new Toolbar($config);
    }
    /**
     * The URI class provides a way to model and manipulate URIs.
     *
     * @param string|null $uri The URI string
     *
     * @return URI The current URI if $uri is null.
     */
    public static function uri(?string $uri = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('uri', $uri);
        }
        if ($uri === null) {
            $app_config = config(App::class);
            $factory = App_Services::siteurifactory($app_config, App_Services::get('superglobals'));
            return $factory->create_from_globals();
        }
        return new URI($uri);
    }
    /**
     * The Validation class provides tools for validating input data.
     *
     * @return ValidationInterface
     */
    public static function validation(?Validation_Config $config = null, bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('validation', $config);
        }
        $config ??= config(Validation_Config::class);
        return new Validation($config, App_Services::get('renderer'));
    }
    /**
     * View cells are intended to let you insert HTML into view
     * that has been generated by any callable in the system.
     *
     * @return Cell
     */
    public static function viewcell(bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('viewcell');
        }
        return new Cell(App_Services::get('cache'));
    }
    /**
     * The Typography class provides a way to format text in semantically relevant ways.
     *
     * @return Typography
     */
    public static function typography(bool $get_shared = true)
    {
        if ($get_shared) {
            return static::get_shared_instance('typography');
        }
        return new Typography();
    }
}