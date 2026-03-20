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
namespace Code_Igniter\Autoloader;

use Code_Igniter\Exceptions\Config_Exception;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Exceptions\RuntimeException;
use Composer\Autoload\Class_Loader;
use Composer\Installed_Versions;
use Config\Autoload;
use Config\Kint as KintConfig;
use Config\Modules;
use Kint\Kint;
use Kint\Renderer\Cli_Renderer;
use Kint\Renderer\Rich_Renderer;
/**
 * An autoloader that uses both PSR4 autoloading, and traditional classmaps.
 *
 * Given a foo-bar package of classes in the file system at the following paths:
 * ```
 *      /path/to/packages/foo-bar/
 *          /src
 *              Baz.php         # Foo\Bar\Baz
 *              Qux/
 *                  Quux.php    # Foo\Bar\Qux\Quux
 * ```
 * you can add the path to the configuration array that is passed in the constructor.
 * The Config array consists of 2 primary keys, both of which are associative arrays:
 * 'psr4', and 'classmap'.
 * ```
 *      $Config = [
 *          'psr4' => [
 *              'Foo\Bar'   => '/path/to/packages/foo-bar'
 *          ],
 *          'classmap' => [
 *              'MyClass'   => '/path/to/class/file.php'
 *          ]
 *      ];
 * ```
 * Example:
 * ```
 *      <?php
 *      // our configuration array
 *      $Config = [ ... ];
 *      $loader = new \CodeIgniter\Autoloader\Autoloader($Config);
 *
 *      // register the autoloader
 *      $loader->register();
 * ```
 *
 * @see \CodeIgniter\Autoloader\AutoloaderTest
 */
class Autoloader
{
    /**
     * Stores namespaces as key, and path as values.
     *
     * @var array<non-empty-string, list<non-empty-string>>
     */
    protected $prefixes = [];
    /**
     * Stores class name as key, and path as values.
     *
     * @var array<class-string, non-empty-string>
     */
    protected $classmap = [];
    /**
     * Stores files as a list.
     *
     * @var list<non-empty-string>
     */
    protected $files = [];
    /**
     * Stores helper list.
     * Always load the URL helper, it should be used in most apps.
     *
     * @var list<non-empty-string>
     */
    protected $helpers = ['url'];
    /**
     * Reads in the configuration array (described above) and stores
     * the valid parts that we'll need.
     *
     * @return $this
     */
    public function initialize(Autoload $config, Modules $modules)
    {
        $this->prefixes = [];
        $this->classmap = [];
        $this->files = [];
        // We have to have one or the other, though we don't enforce the need
        // to have both present in order to work.
        if ($config->psr4 === [] && $config->classmap === []) {
            throw new InvalidArgumentException('Config array must contain either the \'psr4\' key or the \'classmap\' key.');
        }
        if ($config->psr4 !== []) {
            $this->add_namespace($config->psr4);
        }
        if ($config->classmap !== []) {
            $this->classmap = $config->classmap;
        }
        if ($config->files !== []) {
            $this->files = $config->files;
        }
        if ($config->helpers !== []) {
            $this->helpers = [...$this->helpers, ...$config->helpers];
        }
        if (is_file(COMPOSER_PATH)) {
            $this->load_composer_autoloader($modules);
        }
        return $this;
    }
    private function load_composer_autoloader(Modules $modules): void
    {
        // The path to the vendor directory.
        // We do not want to enforce this, so set the constant if Composer was used.
        if (!defined('VENDORPATH')) {
            define('VENDORPATH', dirname(COMPOSER_PATH) . DIRECTORY_SEPARATOR);
        }
        /** @var ClassLoader $composer */
        $composer = include COMPOSER_PATH;
        // Should we load through Composer's namespaces, also?
        if ($modules->discover_in_composer) {
            $composer_packages = $modules->composer_packages;
            $this->load_composer_namespaces($composer, $composer_packages ?? []);
        }
        unset($composer);
    }
    /**
     * Register the loader with the SPL autoloader stack
     * in the following order:
     *
     * 1. Classmap loader
     * 2. PSR-4 autoloader
     * 3. Non-class files
     *
     * @return void
     */
    public function register()
    {
        spl_autoload_register($this->load_classmap(...), true);
        spl_autoload_register($this->load_class(...), true);
        foreach ($this->files as $file) {
            $this->include_file($file);
        }
    }
    /**
     * Unregisters the autoloader from the SPL autoload stack.
     */
    public function unregister(): void
    {
        spl_autoload_unregister($this->load_class(...));
        spl_autoload_unregister($this->load_classmap(...));
    }
    /**
     * Registers namespaces with the autoloader.
     *
     * @param array<non-empty-string, list<non-empty-string>|non-empty-string>|non-empty-string $namespace
     *
     * @return $this
     */
    public function add_namespace($namespace, ?string $path = null)
    {
        if (is_array($namespace)) {
            foreach ($namespace as $prefix => $namespaced_path) {
                $prefix = trim($prefix, '\\');
                if (is_array($namespaced_path)) {
                    foreach ($namespaced_path as $dir) {
                        $this->prefixes[$prefix][] = rtrim($dir, '\/') . DIRECTORY_SEPARATOR;
                    }
                    continue;
                }
                $this->prefixes[$prefix][] = rtrim($namespaced_path, '\/') . DIRECTORY_SEPARATOR;
            }
        } else {
            $this->prefixes[trim($namespace, '\\')][] = rtrim($path, '\/') . DIRECTORY_SEPARATOR;
        }
        return $this;
    }
    /**
     * Get namespaces with prefixes as keys and paths as values.
     *
     * If a prefix param is set, returns only paths to the given prefix.
     *
     * @return ($prefix is null ? array<non-empty-string, list<non-empty-string>> : list<non-empty-string>)
     */
    public function get_namespace(?string $prefix = null)
    {
        if ($prefix === null) {
            return $this->prefixes;
        }
        return $this->prefixes[trim($prefix, '\\')] ?? [];
    }
    /**
     * Removes a single namespace from the psr4 settings.
     *
     * @return $this
     */
    public function remove_namespace(string $namespace)
    {
        if (isset($this->prefixes[trim($namespace, '\\')])) {
            unset($this->prefixes[trim($namespace, '\\')]);
        }
        return $this;
    }
    /**
     * Load a class using available class mapping.
     *
     * @param class-string $class The fully qualified class name.
     *
     * @internal For `spl_autoload_register` use.
     */
    public function load_classmap(string $class): void
    {
        $file = $this->classmap[$class] ?? '';
        if (is_string($file) && $file !== '') {
            $this->include_file($file);
        }
    }
    /**
     * Loads the class file for a given class name.
     *
     * @param class-string $class The fully qualified class name.
     *
     * @internal For `spl_autoload_register` use.
     */
    public function load_class(string $class): void
    {
        $this->load_in_namespace($class);
    }
    /**
     * Loads the class file for a given class name.
     *
     * @param class-string $class The fully qualified class name.
     *
     * @return false|non-empty-string The mapped file name on success, or boolean false on fail
     */
    protected function load_in_namespace(string $class)
    {
        if (!str_contains($class, '\\')) {
            return false;
        }
        foreach ($this->prefixes as $namespace => $directories) {
            if (str_starts_with($class, $namespace)) {
                $relative_class_path = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($namespace)));
                foreach ($directories as $directory) {
                    $directory = rtrim($directory, '\/');
                    $file_path = $directory . $relative_class_path . '.php';
                    $filename = $this->include_file($file_path);
                    if ($filename !== false) {
                        return $filename;
                    }
                }
            }
        }
        return false;
    }
    /**
     * A central way to include a file. Split out primarily for testing purposes.
     *
     * @return false|non-empty-string The filename on success, false if the file is not loaded
     */
    protected function include_file(string $file)
    {
        if (is_file($file)) {
            include_once $file;
            return $file;
        }
        return false;
    }
    /**
     * Check file path.
     *
     * Checks special characters that are illegal in filenames on certain
     * operating systems and special characters requiring special escaping
     * to manipulate at the command line. Replaces spaces and consecutive
     * dashes with a single dash. Trim period, dash and underscore from beginning
     * and end of filename.
     *
     * @return string The sanitized filename
     *
     * @deprecated No longer used. See https://github.com/codeigniter4/CodeIgniter4/issues/7055
     */
    public function sanitize_filename(string $filename): string
    {
        // Only allow characters deemed safe for POSIX portable filenames.
        // Plus the forward slash for directory separators since this might be a path.
        // http://pubs.opengroup.org/onlinepubs/9699919799/basedefs/V1_chap03.html#tag_03_278
        // Modified to allow backslash and colons for on Windows machines.
        $result = preg_match_all('/[^0-9\p{L}\s\/\-_.:\\\\]/u', $filename, $matches);
        if ($result > 0) {
            $chars = implode('', $matches[0]);
            throw new InvalidArgumentException('The file path contains special characters "' . $chars . '" that are not allowed: "' . $filename . '"');
        }
        if ($result === false) {
            $message = preg_last_error_msg();
            throw new RuntimeException($message . '. filename: "' . $filename . '"');
        }
        // Clean up our filename edges.
        $clean_filename = trim($filename, '.-_');
        if ($filename !== $clean_filename) {
            throw new InvalidArgumentException('The characters ".-_" are not allowed in filename edges: "' . $filename . '"');
        }
        return $clean_filename;
    }
    /**
     * @param array{only?: list<string>, exclude?: list<string>} $composerPackages
     */
    private function load_composer_namespaces(Class_Loader $composer, array $composer_packages): void
    {
        $namespace_paths = $composer->get_prefixes_psr4();
        // Get rid of duplicated namespaces.
        $duplicated_namespaces = ['CodeIgniter', APP_NAMESPACE, 'Config'];
        foreach ($duplicated_namespaces as $ns) {
            if (isset($namespace_paths[$ns . '\\'])) {
                unset($namespace_paths[$ns . '\\']);
            }
        }
        if (!method_exists(Installed_Versions::class, 'getAllRawData')) {
            // @phpstan-ignore function.alreadyNarrowedType
            throw new RuntimeException('Your Composer version is too old.' . ' Please update Composer (run `composer self-update`) to v2.0.14 or later' . ' and remove your vendor/ directory, and run `composer update`.');
        }
        // This method requires Composer 2.0.14 or later.
        $all_data = Installed_Versions::get_all_raw_data();
        $package_list = [];
        foreach ($all_data as $list) {
            $package_list = array_merge($package_list, $list['versions']);
        }
        // Check config for $composerPackages.
        $only = $composer_packages['only'] ?? [];
        $exclude = $composer_packages['exclude'] ?? [];
        if ($only !== [] && $exclude !== []) {
            throw new Config_Exception('Cannot use "only" and "exclude" at the same time in "Config\Modules::$composerPackages".');
        }
        // Get install paths of packages to add namespace for auto-discovery.
        $install_paths = [];
        if ($only !== []) {
            foreach ($package_list as $package_name => $data) {
                if (in_array($package_name, $only, true) && isset($data['install_path'])) {
                    $install_paths[] = $data['install_path'];
                }
            }
        } else {
            foreach ($package_list as $package_name => $data) {
                if (!in_array($package_name, $exclude, true) && isset($data['install_path'])) {
                    $install_paths[] = $data['install_path'];
                }
            }
        }
        $new_paths = [];
        foreach ($namespace_paths as $namespace => $src_paths) {
            $add = false;
            foreach ($src_paths as $path) {
                foreach ($install_paths as $install_path) {
                    if (str_starts_with($path, $install_path)) {
                        $add = true;
                        break 2;
                    }
                }
            }
            if ($add) {
                // Composer stores namespaces with trailing slash. We don't.
                $new_paths[rtrim($namespace, '\ ')] = $src_paths;
            }
        }
        $this->add_namespace($new_paths);
    }
    /**
     * Locates autoload information from Composer, if available.
     *
     * @deprecated No longer used.
     *
     * @return void
     */
    protected function discover_composer_namespaces()
    {
        if (!is_file(COMPOSER_PATH)) {
            return;
        }
        /**
         * @var ClassLoader $composer
         */
        $composer = include COMPOSER_PATH;
        $paths = $composer->get_prefixes_psr4();
        $classes = $composer->get_class_map();
        unset($composer);
        // Get rid of CodeIgniter so we don't have duplicates
        if (isset($paths['CodeIgniter\\'])) {
            unset($paths['CodeIgniter\\']);
        }
        $new_paths = [];
        foreach ($paths as $key => $value) {
            // Composer stores namespaces with trailing slash. We don't.
            $new_paths[rtrim($key, '\ ')] = $value;
        }
        $this->prefixes = array_merge($this->prefixes, $new_paths);
        $this->classmap = array_merge($this->classmap, $classes);
    }
    /**
     * Loads helpers
     */
    public function load_helpers(): void
    {
        helper($this->helpers);
    }
    /**
     * Initializes Kint
     */
    public function initialize_kint(bool $debug = false): void
    {
        if ($debug) {
            $this->autoload_kint();
            $this->configure_kint();
        } elseif (class_exists(Kint::class)) {
            // In case that Kint is already loaded via Composer.
            Kint::$enabled_mode = false;
        }
        helper('kint');
    }
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
    private function configure_kint(): void
    {
        $config = new Kint_Config();
        Kint::$depth_limit = $config->max_depth;
        Kint::$display_called_from = $config->display_called_from;
        Kint::$expanded = $config->expanded;
        if (isset($config->plugins) && is_array($config->plugins)) {
            Kint::$plugins = $config->plugins;
        }
        $csp = service('csp');
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
}