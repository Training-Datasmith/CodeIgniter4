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
use Code_Igniter\Cache\Cache_Interface;
use Code_Igniter\Config\Base_Config;
use Code_Igniter\Config\Factories;
use Code_Igniter\Cookie\Cookie;
use Code_Igniter\Cookie\Cookie_Store;
use Code_Igniter\Cookie\Exceptions\Cookie_Exception;
use Code_Igniter\Database\Base_Connection;
use Code_Igniter\Database\Connection_Interface;
use Code_Igniter\Debug\Timer;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Exceptions\RuntimeException;
use Code_Igniter\Files\Exceptions\File_Not_Found_Exception;
use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Code_Igniter\HTTP\Exceptions\Redirect_Exception;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Redirect_Response;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\Language\Language;
use Code_Igniter\Model;
use Code_Igniter\Session\Session;
use Code_Igniter\Test\Test_Logger;
use Config\App;
use Config\Database;
use Config\Doc_Types;
use Config\Logger;
use Config\Services;
use Config\View;
use Laminas\Escaper\Escaper;
// Services Convenience Functions
if (!function_exists('app_timezone')) {
    /**
     * Returns the timezone the application has been set to display
     * dates in. This might be different than the timezone set
     * at the server level, as you often want to stores dates in UTC
     * and convert them on the fly for the user.
     */
    function app_timezone(): string
    {
        $config = config(App::class);
        return $config->app_timezone;
    }
}
if (!function_exists('cache')) {
    /**
     * A convenience method that provides access to the Cache
     * object. If no parameter is provided, will return the object,
     * otherwise, will attempt to return the cached value.
     *
     * Examples:
     *    cache()->save('foo', 'bar');
     *    $foo = cache('bar');
     *
     * @return ($key is null ? CacheInterface : mixed)
     */
    function cache(?string $key = null)
    {
        $cache = service('cache');
        // No params - return cache object
        if ($key === null) {
            return $cache;
        }
        // Still here? Retrieve the value.
        return $cache->get($key);
    }
}
if (!function_exists('clean_path')) {
    /**
     * A convenience method to clean paths for
     * a nicer looking output. Useful for exception
     * handling, error logging, etc.
     */
    function clean_path(string $path): string
    {
        // Resolve relative paths
        try {
            $path = realpath($path) ?: $path;
        } catch (ErrorException|Value_Error) {
            $path = 'error file path: ' . urlencode($path);
        }
        return match (true) {
            str_starts_with($path, APPPATH) => 'APPPATH' . DIRECTORY_SEPARATOR . substr($path, strlen(APPPATH)),
            str_starts_with($path, SYSTEMPATH) => 'SYSTEMPATH' . DIRECTORY_SEPARATOR . substr($path, strlen(SYSTEMPATH)),
            str_starts_with($path, FCPATH) => 'FCPATH' . DIRECTORY_SEPARATOR . substr($path, strlen(FCPATH)),
            defined('VENDORPATH') && str_starts_with($path, VENDORPATH) => 'VENDORPATH' . DIRECTORY_SEPARATOR . substr($path, strlen(VENDORPATH)),
            str_starts_with($path, ROOTPATH) => 'ROOTPATH' . DIRECTORY_SEPARATOR . substr($path, strlen(ROOTPATH)),
            default => $path,
        };
    }
}
if (!function_exists('command')) {
    /**
     * Runs a single command.
     * Input expected in a single string as would
     * be used on the command line itself:
     *
     *  > command('migrate:create SomeMigration');
     *
     * @return false|string
     */
    function command(string $command)
    {
        $regex_string = '([^\s]+?)(?:\s|(?<!\\\\)"|(?<!\\\\)\'|$)';
        $regex_quoted = '(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\')';
        $args = [];
        $length = strlen($command);
        $cursor = 0;
        /**
         * Adopted from Symfony's `StringInput::tokenize()` with few changes.
         *
         * @see https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Console/Input/StringInput.php
         */
        while ($cursor < $length) {
            if (preg_match('/\s+/A', $command, $match, 0, $cursor)) {
                // nothing to do
            } elseif (preg_match('/' . $regex_quoted . '/A', $command, $match, 0, $cursor)) {
                $args[] = stripcslashes(substr($match[0], 1, strlen($match[0]) - 2));
            } elseif (preg_match('/' . $regex_string . '/A', $command, $match, 0, $cursor)) {
                $args[] = stripcslashes($match[1]);
            } else {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Unable to parse input near "... %s ...".', substr($command, $cursor, 10)));
                // @codeCoverageIgnoreEnd
            }
            $cursor += strlen($match[0]);
        }
        /** @var array<int|string, string|null> */
        $params = [];
        $command = array_shift($args);
        $option_value = false;
        foreach ($args as $i => $arg) {
            if (mb_strpos($arg, '-') !== 0) {
                if ($option_value) {
                    // if this was an option value, it was already
                    // included in the previous iteration
                    $option_value = false;
                } else {
                    // add to segments if not starting with '-'
                    // and not an option value
                    $params[] = $arg;
                }
                continue;
            }
            $arg = ltrim($arg, '-');
            $value = null;
            if (isset($args[$i + 1]) && mb_strpos($args[$i + 1], '-') !== 0) {
                $value = $args[$i + 1];
                $option_value = true;
            }
            $params[$arg] = $value;
        }
        ob_start();
        service('commands')->run($command, $params);
        return ob_get_clean();
    }
}
if (!function_exists('config')) {
    /**
     * More simple way of getting config instances from Factories
     *
     * @template ConfigTemplate of BaseConfig
     *
     * @param class-string<ConfigTemplate>|string $name
     *
     * @return ($name is class-string<ConfigTemplate> ? ConfigTemplate : object|null)
     */
    function config(string $name, bool $get_shared = true)
    {
        if ($get_shared) {
            return Factories::get('config', $name);
        }
        return Factories::config($name, ['getShared' => $get_shared]);
    }
}
if (!function_exists('cookie')) {
    /**
     * Simpler way to create a new Cookie instance.
     *
     * @param string $name  Name of the cookie
     * @param string $value Value of the cookie
     * @param array{
     *     prefix?: string,
     *     max-age?: int|numeric-string,
     *     expires?: DateTimeInterface|int|string,
     *     path?: string,
     *     domain?: string,
     *     secure?: bool,
     *     httponly?: bool,
     *     samesite?: string,
     *     raw?: bool
     * } $options Cookie configuration options
     *
     * @throws CookieException
     */
    function cookie(string $name, string $value = '', array $options = []): Cookie
    {
        return new Cookie($name, $value, $options);
    }
}
if (!function_exists('cookies')) {
    /**
     * Fetches the global `CookieStore` instance held by `Response`.
     *
     * @param list<Cookie> $cookies   If `getGlobal` is false, this is passed to CookieStore's constructor
     * @param bool         $getGlobal If false, creates a new instance of CookieStore
     */
    function cookies(array $cookies = [], bool $get_global = true): Cookie_Store
    {
        if ($get_global) {
            return service('response')->get_cookie_store();
        }
        return new Cookie_Store($cookies);
    }
}
if (!function_exists('csrf_token')) {
    /**
     * Returns the CSRF token name.
     * Can be used in Views when building hidden inputs manually,
     * or used in javascript vars when using APIs.
     */
    function csrf_token(): string
    {
        return service('security')->get_token_name();
    }
}
if (!function_exists('csrf_header')) {
    /**
     * Returns the CSRF header name.
     * Can be used in Views by adding it to the meta tag
     * or used in javascript to define a header name when using APIs.
     */
    function csrf_header(): string
    {
        return service('security')->get_header_name();
    }
}
if (!function_exists('csrf_hash')) {
    /**
     * Returns the current hash value for the CSRF protection.
     * Can be used in Views when building hidden inputs manually,
     * or used in javascript vars for API usage.
     */
    function csrf_hash(): string
    {
        return service('security')->get_hash();
    }
}
if (!function_exists('csrf_field')) {
    /**
     * Generates a hidden input field for use within manually generated forms.
     *
     * @param non-empty-string|null $id
     */
    function csrf_field(?string $id = null): string
    {
        return '<input type="hidden"' . ($id !== null ? ' id="' . esc($id, 'attr') . '"' : '') . ' name="' . csrf_token() . '" value="' . csrf_hash() . '"' . _solidus() . '>';
    }
}
if (!function_exists('csrf_meta')) {
    /**
     * Generates a meta tag for use within javascript calls.
     *
     * @param non-empty-string|null $id
     */
    function csrf_meta(?string $id = null): string
    {
        return '<meta' . ($id !== null ? ' id="' . esc($id, 'attr') . '"' : '') . ' name="' . csrf_header() . '" content="' . csrf_hash() . '"' . _solidus() . '>';
    }
}
if (!function_exists('csp_style_nonce')) {
    /**
     * Generates a nonce attribute for style tag.
     */
    function csp_style_nonce(): string
    {
        $csp = service('csp');
        if (!$csp->enabled()) {
            return '';
        }
        return 'nonce="' . $csp->get_style_nonce() . '"';
    }
}
if (!function_exists('csp_script_nonce')) {
    /**
     * Generates a nonce attribute for script tag.
     */
    function csp_script_nonce(): string
    {
        $csp = service('csp');
        if (!$csp->enabled()) {
            return '';
        }
        return 'nonce="' . $csp->get_script_nonce() . '"';
    }
}
if (!function_exists('db_connect')) {
    /**
     * Grabs a database connection and returns it to the user.
     *
     * This is a convenience wrapper for \Config\Database::connect()
     * and supports the same parameters. Namely:
     *
     * When passing in $db, you may pass any of the following to connect:
     * - group name
     * - existing connection instance
     * - array of database configuration values
     *
     * If $getShared === false then a new connection instance will be provided,
     * otherwise it will all calls will return the same instance.
     *
     * @param array{
     *     DSN?: string,
     *     hostname?: string,
     *     username?: string,
     *     password?: string,
     *     database?: string,
     *     DBDriver?: 'MySQLi'|'OCI8'|'Postgre'|'SQLite3'|'SQLSRV',
     *     DBPrefix?: string,
     *     pConnect?: bool,
     *     DBDebug?: bool,
     *     charset?: string,
     *     DBCollat?: string,
     *     swapPre?: string,
     *     encrypt?: bool,
     *     compress?: bool,
     *     strictOn?: bool,
     *     failover?: array<string, mixed>,
     *     port?: int,
     *     dateFormat?: array<string, string>,
     *     foreignKeys?: bool
     * }|ConnectionInterface|string|null $db
     *
     * @return BaseConnection
     */
    function db_connect($db = null, bool $get_shared = true)
    {
        return Database::connect($db, $get_shared);
    }
}
if (!function_exists('env')) {
    /**
     * Allows user to retrieve values from the environment
     * variables that have been set. Especially useful for
     * retrieving values set from the .env file for
     * use in config files.
     *
     * @param array<int|string, mixed>|bool|float|int|object|string|null $default
     *
     * @return array<int|string, mixed>|bool|float|int|object|string|null
     */
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        // Not found? Return the default value
        if ($value === false) {
            return $default;
        }
        // Handle any boolean values
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'empty' => '',
            'null' => null,
            default => $value,
        };
    }
}
if (!function_exists('esc')) {
    /**
     * Performs simple auto-escaping of data for security reasons.
     * Might consider making this more complex at a later date.
     *
     * If $data is a string, then it simply escapes and returns it.
     * If $data is an array, then it loops over it, escaping each
     * 'value' of the key/value pairs.
     *
     * @param array<int|string, array<int|string, mixed>|string>|string $data
     * @param 'attr'|'css'|'html'|'js'|'raw'|'url'                      $context
     * @param string|null                                               $encoding Current encoding for escaping.
     *                                                                            If not UTF-8, we convert strings from this encoding
     *                                                                            pre-escaping and back to this encoding post-escaping.
     *
     * @return ($data is string ? string : array<int|string, array<int|string, mixed>|string>)
     *
     * @throws InvalidArgumentException
     */
    function esc($data, string $context = 'html', ?string $encoding = null)
    {
        $context = strtolower($context);
        // Provide a way to NOT escape data since
        // this could be called automatically by
        // the View library.
        if ($context === 'raw') {
            return $data;
        }
        if (is_array($data)) {
            foreach ($data as &$value) {
                $value = esc($value, $context);
            }
        }
        if (is_string($data)) {
            if (!in_array($context, ['html', 'js', 'css', 'url', 'attr'], true)) {
                throw new InvalidArgumentException('Invalid escape context provided.');
            }
            $method = $context === 'attr' ? 'escapeHtmlAttr' : 'escape' . ucfirst($context);
            static $escaper;
            if (!$escaper) {
                $escaper = new Escaper($encoding);
            }
            if ($encoding !== null && $escaper->get_encoding() !== $encoding) {
                $escaper = new Escaper($encoding);
            }
            $data = $escaper->{$method}($data);
        }
        return $data;
    }
}
if (!function_exists('force_https')) {
    /**
     * Used to force a page to be accessed in via HTTPS.
     * Uses a standard redirect, plus will set the HSTS header
     * for modern browsers that support, which gives best
     * protection against man-in-the-middle attacks.
     *
     * @see https://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security
     *
     * @param int $duration How long should the SSL header be set for? (in seconds)
     *                      Defaults to 1 year.
     *
     * @throws HTTPException
     * @throws RedirectException
     */
    function force_https(int $duration = 31536000, ?Request_Interface $request = null, ?Response_Interface $response = null): void
    {
        $request ??= service('request');
        if (!$request instanceof Incoming_Request) {
            return;
        }
        $response ??= service('response');
        if (ENVIRONMENT !== 'testing' && (is_cli() || $request->is_secure()) || $request->get_server('HTTPS') === 'test') {
            return;
            // @codeCoverageIgnore
        }
        // If the session status is active, we should regenerate
        // the session ID for safety sake.
        if (ENVIRONMENT !== 'testing' && session_status() === PHP_SESSION_ACTIVE) {
            service('session')->regenerate();
            // @codeCoverageIgnore
        }
        $uri = $request->get_uri()->with_scheme('https');
        // Set an HSTS header
        $response->set_header('Strict-Transport-Security', 'max-age=' . $duration)->redirect((string) $uri)->set_status_code(307)->set_body('')->get_cookie_store()->clear();
        throw new Redirect_Exception($response);
    }
}
if (!function_exists('function_usable')) {
    /**
     * Function usable
     *
     * Executes a function_exists() check, and if the Suhosin PHP
     * extension is loaded - checks whether the function that is
     * checked might be disabled in there as well.
     *
     * This is useful as function_exists() will return FALSE for
     * functions disabled via the *disable_functions* php.ini
     * setting, but not for *suhosin.executor.func.blacklist* and
     * *suhosin.executor.disable_eval*. These settings will just
     * terminate script execution if a disabled function is executed.
     *
     * The above described behavior turned out to be a bug in Suhosin,
     * but even though a fix was committed for 0.9.34 on 2012-02-12,
     * that version is yet to be released. This function will therefore
     * be just temporary, but would probably be kept for a few years.
     *
     * @see   http://www.hardened-php.net/suhosin/
     *
     * @param string $functionName Function to check for
     *
     * @return bool TRUE if the function exists and is safe to call,
     *              FALSE otherwise.
     *
     * @codeCoverageIgnore This is too exotic
     */
    function function_usable(string $function_name): bool
    {
        static $_suhosin_func_blacklist;
        if (function_exists($function_name)) {
            if (!isset($_suhosin_func_blacklist)) {
                $_suhosin_func_blacklist = extension_loaded('suhosin') ? explode(',', trim(ini_get('suhosin.executor.func.blacklist'))) : [];
            }
            return !in_array($function_name, $_suhosin_func_blacklist, true);
        }
        return false;
    }
}
if (!function_exists('helper')) {
    /**
     * Loads a helper file into memory. Supports namespaced helpers,
     * both in and out of the 'Helpers' directory of a namespaced directory.
     *
     * Will load ALL helpers of the matching name, in the following order:
     *   1. app/Helpers
     *   2. {namespace}/Helpers
     *   3. system/Helpers
     *
     * @param list<string>|string $filenames
     *
     * @throws FileNotFoundException
     */
    function helper($filenames): void
    {
        static $loaded = [];
        $loader = service('locator');
        if (!is_array($filenames)) {
            $filenames = [$filenames];
        }
        // Store a list of all files to include...
        $includes = [];
        foreach ($filenames as $filename) {
            // Store our system and application helper
            // versions so that we can control the load ordering.
            $system_helper = '';
            $app_helper = '';
            $local_includes = [];
            if (!str_contains($filename, '_helper')) {
                $filename .= '_helper';
            }
            // Check if this helper has already been loaded
            if (in_array($filename, $loaded, true)) {
                continue;
            }
            // If the file is namespaced, we'll just grab that
            // file and not search for any others
            if (str_contains($filename, '\\')) {
                $path = $loader->locate_file($filename, 'Helpers');
                if ($path === false) {
                    throw File_Not_Found_Exception::for_file_not_found($filename);
                }
                $includes[] = $path;
                $loaded[] = $filename;
            } else {
                // No namespaces, so search in all available locations
                $paths = $loader->search('Helpers/' . $filename);
                foreach ($paths as $path) {
                    if (str_starts_with($path, APPPATH . 'Helpers' . DIRECTORY_SEPARATOR)) {
                        $app_helper = $path;
                    } elseif (str_starts_with($path, SYSTEMPATH . 'Helpers' . DIRECTORY_SEPARATOR)) {
                        $system_helper = $path;
                    } else {
                        $local_includes[] = $path;
                        $loaded[] = $filename;
                    }
                }
                // App-level helpers should override all others
                if ($app_helper !== '') {
                    $includes[] = $app_helper;
                    $loaded[] = $filename;
                }
                // All namespaced files get added in next
                $includes = [...$includes, ...$local_includes];
                // And the system default one should be added in last.
                if ($system_helper !== '') {
                    $includes[] = $system_helper;
                    $loaded[] = $filename;
                }
            }
        }
        // Now actually include all of the files
        foreach ($includes as $path) {
            include_once $path;
        }
    }
}
if (!function_exists('is_cli')) {
    /**
     * Check if PHP was invoked from the command line.
     *
     * @codeCoverageIgnore Cannot be tested fully as PHPUnit always run in php-cli
     */
    function is_cli(): bool
    {
        if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            return true;
        }
        // PHP_SAPI could be 'cgi-fcgi', 'fpm-fcgi'.
        // See https://github.com/codeigniter4/CodeIgniter4/pull/5393
        return !isset($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['REQUEST_METHOD']);
    }
}
if (!function_exists('is_really_writable')) {
    /**
     * Tests for file writability
     *
     * is_writable() returns TRUE on Windows servers when you really can't write to
     * the file, based on the read-only attribute. is_writable() is also unreliable
     * on Unix servers if safe_mode is on.
     *
     * @see https://bugs.php.net/bug.php?id=54709
     *
     * @throws Exception
     *
     * @codeCoverageIgnore Not practical to test, as travis runs on linux
     */
    function is_really_writable(string $file): bool
    {
        // If we're on a Unix server we call is_writable
        if (!is_windows()) {
            return is_writable($file);
        }
        /* For Windows servers and safe_mode "on" installations we'll actually
         * write a file then read it. Bah...
         */
        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/' . bin2hex(random_bytes(16));
            if (($fp = @fopen($file, 'ab')) === false) {
                return false;
            }
            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);
            return true;
        }
        if (!is_file($file) || ($fp = @fopen($file, 'ab')) === false) {
            return false;
        }
        fclose($fp);
        return true;
    }
}
if (!function_exists('is_windows')) {
    /**
     * Detect if platform is running in Windows.
     */
    function is_windows(?bool $mock = null): bool
    {
        static $mocked;
        if (func_num_args() === 1) {
            $mocked = $mock;
        }
        return $mocked ?? DIRECTORY_SEPARATOR === '\\';
    }
}
if (!function_exists('lang')) {
    /**
     * A convenience method to translate a string or array of them and format
     * the result with the intl extension's MessageFormatter.
     *
     * @param array<array-key, float|int|string> $args
     *
     * @return list<string>|string
     */
    function lang(string $line, array $args = [], ?string $locale = null)
    {
        /** @var Language $language */
        $language = service('language');
        // Get active locale
        $active_locale = $language->get_locale();
        if ((string) $locale !== '' && $locale !== $active_locale) {
            $language->set_locale($locale);
        }
        $lines = $language->get_line($line, $args);
        if ((string) $locale !== '' && $locale !== $active_locale) {
            // Reset to active locale
            $language->set_locale($active_locale);
        }
        return $lines;
    }
}
if (!function_exists('log_message')) {
    /**
     * A convenience/compatibility method for logging events through
     * the Log system.
     *
     * Allowed log levels are:
     *  - emergency
     *  - alert
     *  - critical
     *  - error
     *  - warning
     *  - notice
     *  - info
     *  - debug
     */
    function log_message(string $level, string $message, array $context = []): void
    {
        // When running tests, we want to always ensure that the
        // TestLogger is running, which provides utilities for
        // for asserting that logs were called in the test code.
        if (ENVIRONMENT === 'testing') {
            $logger = new Test_Logger(new Logger());
            $logger->log($level, $message, $context);
            return;
        }
        service('logger')->log($level, $message, $context);
        // @codeCoverageIgnore
    }
}
if (!function_exists('model')) {
    /**
     * More simple way of getting model instances from Factories
     *
     * @template ModelTemplate of Model
     *
     * @param class-string<ModelTemplate>|string $name
     *
     * @return ($name is class-string<ModelTemplate> ? ModelTemplate : object|null)
     */
    function model(string $name, bool $get_shared = true, ?Connection_Interface &$conn = null)
    {
        return Factories::models($name, ['getShared' => $get_shared], $conn);
    }
}
if (!function_exists('old')) {
    /**
     * Provides access to "old input" that was set in the session
     * during a redirect()->withInput().
     *
     * @param string|null                                $default
     * @param 'attr'|'css'|'html'|'js'|'raw'|'url'|false $escape
     *
     * @return array|string|null
     */
    function old(string $key, $default = null, $escape = 'html')
    {
        // Ensure the session is loaded
        if (session_status() === PHP_SESSION_NONE && ENVIRONMENT !== 'testing') {
            session();
            // @codeCoverageIgnore
        }
        $request = service('request');
        $value = $request->get_old_input($key);
        // Return the default value if nothing
        // found in the old input.
        if ($value === null) {
            return $default;
        }
        return $escape === false ? $value : esc($value, $escape);
    }
}
if (!function_exists('redirect')) {
    /**
     * Convenience method that works with the current global $request and
     * $router instances to redirect using named/reverse-routed routes
     * to determine the URL to go to.
     *
     * If more control is needed, you must use $response->redirect explicitly.
     *
     * @param non-empty-string|null $route Route name or Controller::method
     */
    function redirect(?string $route = null): Redirect_Response
    {
        $response = service('redirectresponse');
        if ((string) $route !== '') {
            return $response->route($route);
        }
        return $response;
    }
}
if (!function_exists('_solidus')) {
    /**
     * Generates the solidus character (`/`) depending on the HTML5 compatibility flag in `Config\DocTypes`
     *
     * @param DocTypes|null $docTypesConfig New config. For testing purpose only.
     *
     * @internal
     */
    function _solidus(?Doc_Types $doc_types_config = null): string
    {
        static $doc_types = null;
        if ($doc_types_config instanceof Doc_Types) {
            $doc_types = $doc_types_config;
        }
        $doc_types ??= new Doc_Types();
        if ($doc_types->html5 ?? false) {
            return '';
        }
        return ' /';
    }
}
if (!function_exists('remove_invisible_characters')) {
    /**
     * Remove Invisible Characters
     *
     * This prevents sandwiching null characters
     * between ascii characters, like Java\0script.
     */
    function remove_invisible_characters(string $str, bool $url_encoded = true): string
    {
        $non_displayables = [];
        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/';
            // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/';
            // url encoded 16-31
        }
        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';
        // 00-08, 11, 12, 14-31, 127
        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);
        return $str;
    }
}
if (!function_exists('render_backtrace')) {
    /**
     * Renders a backtrace in a nice string format.
     *
     * @param list<array{
     *  file?: string,
     *  line?: int,
     *  class?: string,
     *  type?: string,
     *  function: string,
     *  args?: list<mixed>
     * }> $backtrace
     */
    function render_backtrace(array $backtrace): string
    {
        $backtraces = [];
        foreach ($backtrace as $index => $trace) {
            $frame = $trace + ['file' => '[internal function]', 'line' => 0, 'class' => '', 'type' => '', 'args' => []];
            if ($frame['file'] !== '[internal function]') {
                $frame['file'] = sprintf('%s(%s)', $frame['file'], $frame['line']);
            }
            unset($frame['line']);
            $idx = $index;
            $idx = str_pad((string) ++$idx, 2, ' ', STR_PAD_LEFT);
            $args = implode(', ', array_map(static fn($value): string => match (true) {
                is_object($value) => sprintf('Object(%s)', $value::class),
                is_array($value) => $value !== [] ? '[...]' : '[]',
                $value === null => 'null',
                is_resource($value) => sprintf('resource (%s)', get_resource_type($value)),
                default => var_export($value, true),
            }, $frame['args']));
            $backtraces[] = sprintf('%s %s: %s%s%s(%s)', $idx, clean_path($frame['file']), $frame['class'], $frame['type'], $frame['function'], $args);
        }
        return implode("\n", $backtraces);
    }
}
if (!function_exists('request')) {
    /**
     * Returns the shared Request.
     *
     * @return CLIRequest|IncomingRequest
     */
    function request()
    {
        return service('request');
    }
}
if (!function_exists('response')) {
    /**
     * Returns the shared Response.
     */
    function response(): Response_Interface
    {
        return service('response');
    }
}
if (!function_exists('route_to')) {
    /**
     * Given a route name or controller/method string and any params,
     * will attempt to build the relative URL to the
     * matching route.
     *
     * NOTE: This requires the controller/method to
     * have a route defined in the routes Config file.
     *
     * @param string     $method    Route name or Controller::method
     * @param int|string ...$params One or more parameters to be passed to the route.
     *                              The last parameter allows you to set the locale.
     *
     * @return false|string The route (URI path relative to baseURL) or false if not found.
     */
    function route_to(string $method, ...$params)
    {
        return service('routes')->reverse_route($method, ...$params);
    }
}
if (!function_exists('session')) {
    /**
     * A convenience method for accessing the session instance,
     * or an item that has been set in the session.
     *
     * Examples:
     *    session()->set('foo', 'bar');
     *    $foo = session('bar');
     *
     * @return ($val is null ? Session : mixed)
     */
    function session(?string $val = null)
    {
        $session = service('session');
        // Returning a single item?
        if (is_string($val)) {
            return $session->get($val);
        }
        return $session;
    }
}
if (!function_exists('service')) {
    /**
     * Allows cleaner access to the Services Config file.
     * Always returns a SHARED instance of the class, so
     * calling the function multiple times should always
     * return the same instance.
     *
     * These are equal:
     *  - $timer = service('timer')
     *  - $timer = \CodeIgniter\Config\Services::timer();
     *
     * @param array|bool|float|int|object|string|null ...$params
     */
    function service(string $name, ...$params): ?object
    {
        if ($params === []) {
            return Services::get($name);
        }
        return Services::$name(...$params);
    }
}
if (!function_exists('single_service')) {
    /**
     * Always returns a new instance of the class.
     *
     * @param array|bool|float|int|object|string|null ...$params
     */
    function single_service(string $name, ...$params): ?object
    {
        $service = Services::service_exists($name);
        if ($service === null) {
            // The service is not defined anywhere so just return.
            return null;
        }
        $method = new ReflectionMethod($service, $name);
        $count = $method->get_number_of_parameters();
        $m_param = $method->get_parameters();
        if ($count === 1) {
            // This service needs only one argument, which is the shared
            // instance flag, so let's wrap up and pass false here.
            return $service::$name(false);
        }
        // Fill in the params with the defaults, but stop before the last
        for ($start_index = count($params); $start_index <= $count - 2; $start_index++) {
            $params[$start_index] = $m_param[$start_index]->get_default_value();
        }
        // Ensure the last argument will not create a shared instance
        $params[$count - 1] = false;
        return $service::$name(...$params);
    }
}
if (!function_exists('slash_item')) {
    // Unlike CI3, this function is placed here because
    // it's not a config, or part of a config.
    /**
     * Fetch a config file item with slash appended (if not empty)
     *
     * @param string $item Config item name
     *
     * @return string|null The configuration item or NULL if
     *                     the item doesn't exist
     */
    function slash_item(string $item): ?string
    {
        $config = config(App::class);
        if (!property_exists($config, $item)) {
            return null;
        }
        $config_item = $config->{$item};
        if (!is_scalar($config_item)) {
            throw new RuntimeException(sprintf('Cannot convert "%s::$%s" of type "%s" to type "string".', App::class, $item, gettype($config_item)));
        }
        $config_item = trim((string) $config_item);
        if ($config_item === '') {
            return $config_item;
        }
        return rtrim($config_item, '/') . '/';
    }
}
if (!function_exists('stringify_attributes')) {
    /**
     * Stringify attributes for use in HTML tags.
     *
     * Helper function used to convert a string, array, or object
     * of attributes to a string.
     *
     * @param array|object|string $attributes string, array, object that can be cast to array
     */
    function stringify_attributes($attributes, bool $js = false): string
    {
        $atts = '';
        if (in_array($attributes, ['', [], null], true)) {
            return $atts;
        }
        if (is_string($attributes)) {
            return ' ' . $attributes;
        }
        $attributes = (array) $attributes;
        foreach ($attributes as $key => $val) {
            $atts .= $js ? $key . '=' . esc($val, 'js') . ',' : ' ' . $key . '="' . esc($val) . '"';
        }
        return rtrim($atts, ',');
    }
}
if (!function_exists('timer')) {
    /**
     * A convenience method for working with the timer.
     * If no parameter is passed, it will return the timer instance.
     * If callable is passed, it measures time of callable and
     * returns its return value if any.
     * Otherwise will start or stop the timer intelligently.
     *
     * @param non-empty-string|null    $name
     * @param (callable(): mixed)|null $callable
     *
     * @return ($name is null ? Timer : ($callable is (callable(): mixed) ? mixed : Timer))
     */
    function timer(?string $name = null, ?callable $callable = null)
    {
        $timer = service('timer');
        if ($name === null) {
            return $timer;
        }
        if ($callable !== null) {
            return $timer->record($name, $callable);
        }
        if ($timer->has($name)) {
            return $timer->stop($name);
        }
        return $timer->start($name);
    }
}
if (!function_exists('view')) {
    /**
     * Grabs the current RendererInterface-compatible class
     * and tells it to render the specified view. Simply provides
     * a convenience method that can be used in Controllers,
     * libraries, and routed closures.
     *
     * NOTE: Does not provide any escaping of the data, so that must
     * all be handled manually by the developer.
     *
     * @param array $options Options for saveData or third-party extensions.
     */
    function view(string $name, array $data = [], array $options = []): string
    {
        $renderer = service('renderer');
        $config = config(View::class);
        $save_data = $config->save_data;
        if (array_key_exists('saveData', $options)) {
            $save_data = (bool) $options['saveData'];
            unset($options['saveData']);
        }
        return $renderer->set_data($data, 'raw')->render($name, $options, $save_data);
    }
}
if (!function_exists('view_cell')) {
    /**
     * View cells are used within views to insert HTML chunks that are managed
     * by other classes.
     *
     * @param array|string|null $params
     *
     * @throws ReflectionException
     */
    function view_cell(string $library, $params = null, int $ttl = 0, ?string $cache_name = null): string
    {
        return service('viewcell')->render($library, $params, $ttl, $cache_name);
    }
}
/**
 * These helpers come from Laravel so will not be
 * re-tested and can be ignored safely.
 *
 * @see https://github.com/laravel/framework/blob/8.x/src/Illuminate/Support/helpers.php
 */
if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param class-string|object $class
     *
     * @return string
     *
     * @codeCoverageIgnore
     */
    function class_basename($class)
    {
        $class = is_object($class) ? $class::class : $class;
        return basename(str_replace('\\', '/', $class));
    }
}
if (!function_exists('class_uses_recursive')) {
    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     *
     * @param class-string|object $class
     *
     * @return array<class-string, class-string>
     *
     * @codeCoverageIgnore
     */
    function class_uses_recursive($class)
    {
        if (is_object($class)) {
            $class = $class::class;
        }
        $results = [];
        foreach (array_reverse(class_parents($class)) + [$class => $class] as $class) {
            $results += trait_uses_recursive($class);
        }
        return array_unique($results);
    }
}
if (!function_exists('trait_uses_recursive')) {
    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param class-string $trait
     *
     * @return array<class-string, class-string>
     *
     * @codeCoverageIgnore
     */
    function trait_uses_recursive($trait)
    {
        $traits = class_uses($trait) ?: [];
        foreach ($traits as $trait) {
            $traits += trait_uses_recursive($trait);
        }
        return $traits;
    }
}