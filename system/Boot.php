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

use Code_Igniter\Cache\Factories_Cache;
use Code_Igniter\CLI\Console;
use Code_Igniter\Config\Dot_Env;
use Config\App;
use Config\Autoload;
use Config\Modules;
use Config\Optimize;
use Config\Paths;
use Config\Services;
/**
 * Bootstrap for the application
 *
 * @codeCoverageIgnore
 */
class Boot
{
    /**
     * Used by `public/index.php`
     *
     * Context
     *   web:     Invoked by HTTP request
     *   php-cli: Invoked by CLI via `php public/index.php`
     *
     * @return int Exit code.
     */
    public static function boot_web(Paths $paths): int
    {
        static::define_path_constants($paths);
        if (!defined('APP_NAMESPACE')) {
            static::load_constants();
        }
        static::check_missing_extensions();
        static::load_dot_env($paths);
        static::define_environment();
        static::load_environment_bootstrap($paths);
        static::load_common_functions();
        static::load_autoloader();
        static::set_exception_handler();
        static::initialize_kint();
        $config_cache_enabled = class_exists(Optimize::class) && (new Optimize())->config_cache_enabled;
        if ($config_cache_enabled) {
            $factories_cache = static::load_config_cache();
        }
        static::autoload_helpers();
        $app = static::initialize_code_igniter();
        static::run_code_igniter($app);
        if ($config_cache_enabled) {
            static::save_config_cache($factories_cache);
        }
        // Exits the application, setting the exit code for CLI-based
        // applications that might be watching.
        return EXIT_SUCCESS;
    }
    /**
     * Bootstrap for FrankenPHP worker mode.
     *
     * This method performs one-time initialization for worker mode,
     * loading everything except the CodeIgniter instance, which should
     * be created fresh for each request.
     *
     * @used-by `public/frankenphp-worker.php`
     */
    public static function boot_worker(Paths $paths): Code_Igniter
    {
        static::define_path_constants($paths);
        if (!defined('APP_NAMESPACE')) {
            static::load_constants();
        }
        static::check_missing_extensions();
        static::load_dot_env($paths);
        static::define_environment();
        static::load_environment_bootstrap($paths);
        static::load_common_functions();
        static::load_autoloader();
        static::set_exception_handler();
        static::initialize_kint();
        static::check_optimizations_for_worker();
        static::autoload_helpers();
        return Boot::initialize_code_igniter();
    }
    /**
     * Used by command line scripts other than
     * * `spark`
     * * `php-cli`
     * * `phpunit`
     *
     * @used-by `system/util_bootstrap.php`
     */
    public static function boot_console(Paths $paths): void
    {
        static::define_path_constants($paths);
        static::load_constants();
        static::check_missing_extensions();
        static::load_dot_env($paths);
        static::load_environment_bootstrap($paths);
        static::load_common_functions();
        static::load_autoloader();
        static::set_exception_handler();
        static::initialize_kint();
        static::autoload_helpers();
        // We need to force the request to be a CLIRequest since we're in console
        Services::create_request(new App(), true);
        service('routes')->load_routes();
    }
    /**
     * Used by `spark`
     *
     * @return int Exit code.
     */
    public static function boot_spark(Paths $paths): int
    {
        static::define_path_constants($paths);
        if (!defined('APP_NAMESPACE')) {
            static::load_constants();
        }
        static::check_missing_extensions();
        static::load_dot_env($paths);
        static::define_environment();
        static::load_environment_bootstrap($paths);
        static::load_common_functions();
        static::load_autoloader();
        static::set_exception_handler();
        static::initialize_kint();
        static::autoload_helpers();
        static::initialize_code_igniter();
        $console = static::initialize_console();
        return static::run_command($console);
    }
    /**
     * Used by `system/Test/bootstrap.php`
     */
    public static function boot_test(Paths $paths): void
    {
        static::load_constants();
        static::check_missing_extensions();
        static::load_dot_env($paths);
        static::load_environment_bootstrap($paths, false);
        static::load_common_functions_mock();
        static::load_common_functions();
        static::load_autoloader();
        static::set_exception_handler();
        static::initialize_kint();
        static::autoload_helpers();
    }
    /**
     * Used by `preload.php`
     */
    public static function preload(Paths $paths): void
    {
        static::define_path_constants($paths);
        static::load_constants();
        static::define_environment();
        static::load_environment_bootstrap($paths, false);
        static::load_autoloader();
    }
    /**
     * Load environment settings from .env files into $_SERVER and $_ENV
     */
    protected static function load_dot_env(Paths $paths): void
    {
        require_once $paths->system_directory . '/Config/DotEnv.php';
        $env_directory = $paths->env_directory ?? $paths->app_directory . '/../';
        (new Dot_Env($env_directory))->load();
    }
    protected static function define_environment(): void
    {
        if (!defined('ENVIRONMENT')) {
            // @phpstan-ignore-next-line
            $env = $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'production';
            define('ENVIRONMENT', $env);
        }
    }
    protected static function load_environment_bootstrap(Paths $paths, bool $exit = true): void
    {
        if (is_file($paths->app_directory . '/Config/Boot/' . ENVIRONMENT . '.php')) {
            require_once $paths->app_directory . '/Config/Boot/' . ENVIRONMENT . '.php';
            return;
        }
        if ($exit) {
            header('HTTP/1.1 503 Service Unavailable.', true, 503);
            echo 'The application environment is not set correctly.';
            exit(EXIT_ERROR);
        }
    }
    /**
     * The path constants provide convenient access to the folders throughout
     * the application. We have to set them up here, so they are available in
     * the config files that are loaded.
     */
    protected static function define_path_constants(Paths $paths): void
    {
        // The path to the application directory.
        if (!defined('APPPATH')) {
            define('APPPATH', realpath(rtrim($paths->app_directory, '\/ ')) . DIRECTORY_SEPARATOR);
        }
        // The path to the project root directory. Just above APPPATH.
        if (!defined('ROOTPATH')) {
            define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
        }
        // The path to the system directory.
        if (!defined('SYSTEMPATH')) {
            define('SYSTEMPATH', realpath(rtrim($paths->system_directory, '\/ ')) . DIRECTORY_SEPARATOR);
        }
        // The path to the writable directory.
        if (!defined('WRITEPATH')) {
            $write_path = realpath(rtrim($paths->writable_directory, '\/ '));
            if ($write_path === false) {
                header('HTTP/1.1 503 Service Unavailable.', true, 503);
                echo 'The WRITEPATH is not set correctly.';
                // EXIT_ERROR is not yet defined
                exit(1);
            }
            define('WRITEPATH', $write_path . DIRECTORY_SEPARATOR);
        }
        // The path to the tests directory
        if (!defined('TESTPATH')) {
            define('TESTPATH', realpath(rtrim($paths->tests_directory, '\/ ')) . DIRECTORY_SEPARATOR);
        }
    }
    protected static function load_constants(): void
    {
        require_once APPPATH . 'Config/Constants.php';
    }
    protected static function load_common_functions(): void
    {
        // Require app/Common.php file if exists.
        if (is_file(APPPATH . 'Common.php')) {
            require_once APPPATH . 'Common.php';
        }
        // Require system/Common.php
        require_once SYSTEMPATH . 'Common.php';
    }
    protected static function load_common_functions_mock(): void
    {
        require_once SYSTEMPATH . 'Test/Mock/MockCommon.php';
    }
    /**
     * The autoloader allows all the pieces to work together in the framework.
     * We have to load it here, though, so that the config files can use the
     * path constants.
     */
    protected static function load_autoloader(): void
    {
        if (!class_exists(Autoload::class, false)) {
            require_once SYSTEMPATH . 'Config/AutoloadConfig.php';
            require_once APPPATH . 'Config/Autoload.php';
            require_once SYSTEMPATH . 'Modules/Modules.php';
            require_once APPPATH . 'Config/Modules.php';
        }
        require_once SYSTEMPATH . 'Autoloader/Autoloader.php';
        require_once SYSTEMPATH . 'Config/BaseService.php';
        require_once SYSTEMPATH . 'Config/Services.php';
        require_once APPPATH . 'Config/Services.php';
        // Initialize and register the loader with the SPL autoloader stack.
        Services::autoloader()->initialize(new Autoload(), new Modules())->register();
    }
    protected static function autoload_helpers(): void
    {
        service('autoloader')->load_helpers();
    }
    protected static function set_exception_handler(): void
    {
        service('exceptions')->initialize();
    }
    protected static function check_missing_extensions(): void
    {
        if (is_file(COMPOSER_PATH)) {
            return;
        }
        // Run this check for manual installations
        $missing_extensions = [];
        foreach (['intl', 'mbstring'] as $extension) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $extension;
            }
        }
        if ($missing_extensions === []) {
            return;
        }
        $message = sprintf('The framework needs the following extension(s) installed and loaded: %s.', implode(', ', $missing_extensions));
        header('HTTP/1.1 503 Service Unavailable.', true, 503);
        echo $message;
        exit(EXIT_ERROR);
    }
    protected static function check_optimizations_for_worker(): void
    {
        if (class_exists(Optimize::class)) {
            $optimize = new Optimize();
            if ($optimize->config_cache_enabled || $optimize->locator_cache_enabled) {
                echo 'Optimization settings (configCacheEnabled, locatorCacheEnabled) ' . 'must be disabled in Config\Optimize when running in Worker Mode.';
                exit(EXIT_ERROR);
            }
        }
    }
    protected static function initialize_kint(): void
    {
        service('autoloader')->initialize_kint(CI_DEBUG);
    }
    protected static function load_config_cache(): Factories_Cache
    {
        $factories_cache = new Factories_Cache();
        $factories_cache->load('config');
        return $factories_cache;
    }
    /**
     * The CodeIgniter class contains the core functionality to make
     * the application run, and does all the dirty work to get
     * the pieces all working together.
     */
    protected static function initialize_code_igniter(): Code_Igniter
    {
        $app = service('codeigniter');
        $app->initialize();
        $context = is_cli() ? 'php-cli' : 'web';
        $app->set_context($context);
        return $app;
    }
    /**
     * Now that everything is set up, it's time to actually fire
     * up the engines and make this app do its thang.
     */
    protected static function run_code_igniter(Code_Igniter $app): void
    {
        $app->run();
    }
    protected static function save_config_cache(Factories_Cache $factories_cache): void
    {
        $factories_cache->save('config');
    }
    protected static function initialize_console(): Console
    {
        $console = new Console();
        // Show basic information before we do anything else.
        if (is_int($suppress = array_search('--no-header', $_SERVER['argv'], true))) {
            unset($_SERVER['argv'][$suppress]);
            $suppress = true;
        }
        $console->show_header($suppress);
        return $console;
    }
    protected static function run_command(Console $console): int
    {
        $exit = $console->run();
        return is_int($exit) ? $exit : EXIT_SUCCESS;
    }
}