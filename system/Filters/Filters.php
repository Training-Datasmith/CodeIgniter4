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
namespace Code_Igniter\Filters;

use Code_Igniter\Config\Filters as BaseFiltersConfig;
use Code_Igniter\Filters\Exceptions\Filter_Exception;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Config\Feature;
use Config\Filters as FiltersConfig;
use Config\Modules;
/**
 * Filters
 *
 * @see \CodeIgniter\Filters\FiltersTest
 */
class Filters
{
    /**
     * The Config\Filters instance
     *
     * @var FiltersConfig
     */
    protected $config;
    /**
     * The active IncomingRequest or CLIRequest
     *
     * @var RequestInterface
     */
    protected $request;
    /**
     * The active Response instance
     *
     * @var ResponseInterface
     */
    protected $response;
    /**
     * The Config\Modules instance
     *
     * @var Modules
     */
    protected $modules;
    /**
     * Whether we've done initial processing on the filter lists.
     *
     * @var bool
     */
    protected $initialized = false;
    /**
     * The filter list to execute for the current request (URI path).
     *
     * This property is for display. Use $filtersClass to execute filters.
     * This does not include "Required Filters".
     *
     * [
     *     'before' => [
     *         'alias',
     *         'alias:arg1',
     *         'alias:arg1,arg2',
     *     ],
     *     'after'  => [
     *         'alias',
     *         'alias:arg1',
     *         'alias:arg1,arg2',
     *     ],
     * ]
     *
     * @var array{
     *     before: list<string>,
     *     after: list<string>
     * }
     */
    protected $filters = ['before' => [], 'after' => []];
    /**
     * The collection of filter classnames and their arguments to execute for
     * the current request (URI path).
     *
     * This does not include "Required Filters".
     *
     * [
     *     'before' => [
     *         [classname, arguments],
     *     ],
     *     'after'  => [
     *         [classname, arguments],
     *     ],
     * ]
     *
     * @var array{
     *     before: list<array{0: class-string, 1: list<string>}>,
     *     after: list<array{0: class-string, 1: list<string>}>
     * }
     */
    protected $filters_class = ['before' => [], 'after' => []];
    /**
     * List of filter class instances.
     *
     * @var array<class-string, FilterInterface> [classname => instance]
     */
    protected array $filter_class_instances = [];
    /**
     * Any arguments to be passed to filters.
     *
     * @var array<string, list<string>|null> [name => params]
     *
     * @deprecated 4.6.0 No longer used.
     */
    protected $arguments = [];
    /**
     * Any arguments to be passed to filtersClass.
     *
     * @var array<class-string, list<string>|null> [classname => arguments]
     *
     * @deprecated 4.6.0 No longer used.
     */
    protected $arguments_class = [];
    /**
     * Constructor.
     *
     * @param FiltersConfig $config
     */
    public function __construct($config, Request_Interface $request, Response_Interface $response, ?Modules $modules = null)
    {
        $this->config = $config;
        $this->request =& $request;
        $this->set_response($response);
        $this->modules = $modules instanceof Modules ? $modules : new Modules();
        if ($this->modules->should_discover('filters')) {
            $this->discover_filters();
        }
    }
    /**
     * If discoverFilters is enabled in Config then system will try to
     * auto-discover custom filters files in namespaces and allow access to
     * the config object via the variable $filters as with the routes file.
     *
     * Sample:
     * $filters->aliases['custom-auth'] = \Acme\Blob\Filters\BlobAuth::class;
     *
     * @deprecated 4.4.2 Use Registrar instead.
     */
    private function discover_filters(): void
    {
        $locator = service('locator');
        // for access by custom filters
        $filters = $this->config;
        $files = $locator->search('Config/Filters.php');
        foreach ($files as $file) {
            // The $file may not be a class file.
            $class_name = $locator->get_classname($file);
            // Don't include our main Filter config again...
            if ($class_name === Filters_Config::class || $class_name === Base_Filters_Config::class) {
                continue;
            }
            include $file;
        }
    }
    /**
     * Set the response explicitly.
     *
     * @return void
     */
    public function set_response(Response_Interface $response)
    {
        $this->response = $response;
    }
    /**
     * Runs through all the filters (except "Required Filters") for the specified
     * URI and position.
     *
     * @param string           $uri      URI path relative to baseURL
     * @param 'after'|'before' $position
     *
     * @return RequestInterface|ResponseInterface|string|null
     *
     * @throws FilterException
     */
    public function run(string $uri, string $position = 'before')
    {
        $this->initialize(strtolower($uri));
        if ($position === 'before') {
            return $this->run_before($this->filters_class[$position]);
        }
        // After
        return $this->run_after($this->filters_class[$position]);
    }
    /**
     * @param list<array{0: class-string, 1: list<string>}> $filterClassList [[classname, arguments], ...]
     *
     * @return RequestInterface|ResponseInterface|string
     */
    private function run_before(array $filter_class_list)
    {
        foreach ($filter_class_list as $filter_class_info) {
            $class_name = $filter_class_info[0];
            $arguments = $filter_class_info[1] === [] ? null : $filter_class_info[1];
            $instance = $this->create_filter($class_name);
            $result = $instance->before($this->request, $arguments);
            if ($result instanceof Request_Interface) {
                $this->request = $result;
                continue;
            }
            // If the response object was sent back,
            // then send it and quit.
            if ($result instanceof Response_Interface) {
                // short circuit - bypass any other filters
                return $result;
            }
            // Ignore an empty result
            if (empty($result)) {
                continue;
            }
            return $result;
        }
        return $this->request;
    }
    /**
     * @param list<array{0: class-string, 1: list<string>}> $filterClassList [[classname, arguments], ...]
     */
    private function run_after(array $filter_class_list): Response_Interface
    {
        foreach ($filter_class_list as $filter_class_info) {
            $class_name = $filter_class_info[0];
            $arguments = $filter_class_info[1] === [] ? null : $filter_class_info[1];
            $instance = $this->create_filter($class_name);
            $result = $instance->after($this->request, $this->response, $arguments);
            if ($result instanceof Response_Interface) {
                $this->response = $result;
                continue;
            }
        }
        return $this->response;
    }
    /**
     * @param class-string $className
     */
    private function create_filter(string $class_name): Filter_Interface
    {
        if (isset($this->filter_class_instances[$class_name])) {
            return $this->filter_class_instances[$class_name];
        }
        $instance = new $class_name();
        if (!$instance instanceof Filter_Interface) {
            throw Filter_Exception::for_incorrect_interface($instance::class);
        }
        $this->filter_class_instances[$class_name] = $instance;
        return $instance;
    }
    /**
     * Returns the "Required Filters" class list.
     *
     * @param 'after'|'before' $position
     *
     * @return list<array{0: class-string, 1: list<string>}> [[classname, arguments], ...]
     */
    public function get_required_classes(string $position): array
    {
        [$filters, $aliases] = $this->get_required_filters($position);
        if ($filters === []) {
            return [];
        }
        $filter_class_list = [];
        foreach ($filters as $alias) {
            if (is_array($aliases[$alias])) {
                foreach ($this->config->aliases[$alias] as $class) {
                    $filter_class_list[] = [$class, []];
                }
            } else {
                $filter_class_list[] = [$aliases[$alias], []];
            }
        }
        return $filter_class_list;
    }
    /**
     * Runs "Required Filters" for the specified position.
     *
     * @param 'after'|'before' $position
     *
     * @return RequestInterface|ResponseInterface|string|null
     *
     * @throws FilterException
     *
     * @internal
     */
    public function run_required(string $position = 'before')
    {
        $filter_class_list = $this->get_required_classes($position);
        if ($filter_class_list === []) {
            return $position === 'before' ? $this->request : $this->response;
        }
        if ($position === 'before') {
            return $this->run_before($filter_class_list);
        }
        // After
        return $this->run_after($filter_class_list);
    }
    /**
     * Returns "Required Filters" for the specified position.
     *
     * @param 'after'|'before' $position
     *
     * @internal
     */
    public function get_required_filters(string $position = 'before'): array
    {
        // For backward compatibility. For users who do not update Config\Filters.
        if (!isset($this->config->required[$position])) {
            $base_config = config(Base_Filters_Config::class);
            // @phpstan-ignore-line
            $filters = $base_config->required[$position];
            $aliases = $base_config->aliases;
        } else {
            $filters = $this->config->required[$position];
            $aliases = $this->config->aliases;
        }
        if ($filters === []) {
            return [[], $aliases];
        }
        if ($position === 'after') {
            if (in_array('toolbar', $this->filters['after'], true)) {
                // It was already run in globals filters. So remove it.
                $filters = $this->set_toolbar_to_last($filters, true);
            } else {
                // Set the toolbar filter to the last position to be executed
                $filters = $this->set_toolbar_to_last($filters);
            }
        }
        foreach ($filters as $alias) {
            if (!array_key_exists($alias, $aliases)) {
                throw Filter_Exception::for_no_alias($alias);
            }
        }
        return [$filters, $aliases];
    }
    /**
     * Set the toolbar filter to the last position to be executed.
     *
     * @param list<string> $filters `after` filter array
     * @param bool         $remove  if true, remove `toolbar` filter
     */
    private function set_toolbar_to_last(array $filters, bool $remove = false): array
    {
        $afters = [];
        $found = false;
        foreach ($filters as $alias) {
            if ($alias === 'toolbar') {
                $found = true;
                continue;
            }
            $afters[] = $alias;
        }
        if ($found && !$remove) {
            $afters[] = 'toolbar';
        }
        return $afters;
    }
    /**
     * Runs through our list of filters provided by the configuration
     * object to get them ready for use, including getting uri masks
     * to proper regex, removing those we can from the possibilities
     * based on HTTP method, etc.
     *
     * The resulting $this->filters is an array of only filters
     * that should be applied to this request.
     *
     * We go ahead and process the entire tree because we'll need to
     * run through both a before and after and don't want to double
     * process the rows.
     *
     * @param string|null $uri URI path relative to baseURL (all lowercase)
     *
     * @TODO We don't need to accept null as $uri.
     *
     * @return Filters
     *
     * @testTag Only for test code. The run() calls this, so you don't need to
     *          call this in your app.
     */
    public function initialize(?string $uri = null)
    {
        if ($this->initialized === true) {
            return $this;
        }
        // Decode URL-encoded string
        $uri = urldecode($uri ?? '');
        $old_filter_order = config(Feature::class)->old_filter_order ?? false;
        if ($old_filter_order) {
            $this->process_globals($uri);
            $this->process_methods();
            $this->process_filters($uri);
        } else {
            $this->process_filters($uri);
            $this->process_methods();
            $this->process_globals($uri);
        }
        // Set the toolbar filter to the last position to be executed
        $this->filters['after'] = $this->set_toolbar_to_last($this->filters['after']);
        // Since some filters like rate limiters rely on being executed once a request,
        // we filter em here.
        $this->filters['before'] = array_unique($this->filters['before']);
        $this->filters['after'] = array_unique($this->filters['after']);
        $this->process_aliases_to_class('before');
        $this->process_aliases_to_class('after');
        $this->initialized = true;
        return $this;
    }
    /**
     * Restores instance to its pre-initialized state.
     * Most useful for testing so the service can be
     * re-initialized to a different path.
     */
    public function reset(): self
    {
        $this->initialized = false;
        $this->arguments = $this->arguments_class = [];
        $this->filters = $this->filters_class = ['before' => [], 'after' => []];
        return $this;
    }
    /**
     * Returns the processed filters array.
     * This does not include "Required Filters".
     *
     * @return array{
     *      before: list<string>,
     *      after: list<string>
     *  }
     */
    public function get_filters(): array
    {
        return $this->filters;
    }
    /**
     * Returns the filtersClass array.
     * This does not include "Required Filters".
     *
     * @return array{
     *      before: list<array{0: class-string, 1: list<string>}>,
     *      after: list<array{0: class-string, 1: list<string>}>
     *  }
     */
    public function get_filters_class(): array
    {
        return $this->filters_class;
    }
    /**
     * Adds a new alias to the config file.
     * MUST be called prior to initialize();
     * Intended for use within routes files.
     *
     * @param 'after'|'before' $position
     *
     * @return $this
     */
    public function add_filter(string $class, ?string $alias = null, string $position = 'before', string $section = 'globals')
    {
        $alias ??= md5($class);
        if (!isset($this->config->{$section})) {
            $this->config->{$section} = [];
        }
        if (!isset($this->config->{$section}[$position])) {
            $this->config->{$section}[$position] = [];
        }
        $this->config->aliases[$alias] = $class;
        $this->config->{$section}[$position][] = $alias;
        return $this;
    }
    /**
     * Ensures that a specific filter is on and enabled for the current request.
     *
     * Filters can have "arguments". This is done by placing a colon immediately
     * after the filter name, followed by a comma-separated list of arguments that
     * are passed to the filter when executed.
     *
     * @param string           $filter   filter_name or filter_name:arguments like 'role:admin,manager'
     *                                   or filter classname.
     * @param 'after'|'before' $position
     */
    private function enable_filter(string $filter, string $position = 'before'): void
    {
        // Normalize the arguments.
        [$alias, $arguments] = $this->get_clean_name($filter);
        $filter = $arguments === [] ? $alias : $alias . ':' . implode(',', $arguments);
        if (class_exists($alias)) {
            $this->config->aliases[$alias] = $alias;
        } elseif (!array_key_exists($alias, $this->config->aliases)) {
            throw Filter_Exception::for_no_alias($alias);
        }
        if (!isset($this->filters[$position][$filter])) {
            $this->filters[$position][] = $filter;
        }
        // Since some filters like rate limiters rely on being executed once a request,
        // we filter em here.
        $this->filters[$position] = array_unique($this->filters[$position]);
    }
    /**
     * Get clean name and arguments
     *
     * @param string $filter filter_name or filter_name:arguments like 'role:admin,manager'
     *
     * @return array{0: string, 1: list<string>} [name, arguments]
     */
    private function get_clean_name(string $filter): array
    {
        $arguments = [];
        if (!str_contains($filter, ':')) {
            return [$filter, $arguments];
        }
        [$alias, $arguments] = explode(':', $filter);
        $arguments = explode(',', $arguments);
        array_walk($arguments, static function (&$item): void {
            $item = trim($item);
        });
        return [$alias, $arguments];
    }
    /**
     * Ensures that specific filters are on and enabled for the current request.
     *
     * Filters can have "arguments". This is done by placing a colon immediately
     * after the filter name, followed by a comma-separated list of arguments that
     * are passed to the filter when executed.
     *
     * @param list<string> $filters filter_name or filter_name:arguments like 'role:admin,manager'
     *
     * @return Filters
     */
    public function enable_filters(array $filters, string $when = 'before')
    {
        foreach ($filters as $filter) {
            $this->enable_filter($filter, $when);
        }
        return $this;
    }
    /**
     * Returns the arguments for a specified key, or all.
     *
     * @return array<string, string>|string
     *
     * @deprecated 4.6.0 Already does not work.
     */
    public function get_arguments(?string $key = null)
    {
        return (string) $key === '' ? $this->arguments : $this->arguments[$key];
    }
    // --------------------------------------------------------------------
    // Processors
    // --------------------------------------------------------------------
    /**
     * Add any applicable (not excluded) global filter settings to the mix.
     *
     * @param string|null $uri URI path relative to baseURL (all lowercase)
     *
     * @return void
     */
    protected function process_globals(?string $uri = null)
    {
        if (!isset($this->config->globals) || !is_array($this->config->globals)) {
            return;
        }
        $uri = strtolower(trim($uri ?? '', '/ '));
        // Add any global filters, unless they are excluded for this URI
        $sets = ['before', 'after'];
        $filters = [];
        foreach ($sets as $set) {
            if (isset($this->config->globals[$set])) {
                // look at each alias in the group
                foreach ($this->config->globals[$set] as $alias => $rules) {
                    $keep = true;
                    if (is_array($rules)) {
                        // see if it should be excluded
                        if (isset($rules['except'])) {
                            // grab the exclusion rules
                            $check = $rules['except'];
                            if ($this->check_except($uri, $check)) {
                                $keep = false;
                            }
                        }
                    } else {
                        $alias = $rules;
                        // simple name of filter to apply
                    }
                    if ($keep) {
                        $filters[$set][] = $alias;
                    }
                }
            }
        }
        if (isset($filters['before'])) {
            $old_filter_order = config(Feature::class)->old_filter_order ?? false;
            if ($old_filter_order) {
                $this->filters['before'] = array_merge($this->filters['before'], $filters['before']);
            } else {
                $this->filters['before'] = array_merge($filters['before'], $this->filters['before']);
            }
        }
        if (isset($filters['after'])) {
            $this->filters['after'] = array_merge($this->filters['after'], $filters['after']);
        }
    }
    /**
     * Add any method-specific filters to the mix.
     *
     * @return void
     */
    protected function process_methods()
    {
        if (!isset($this->config->methods) || !is_array($this->config->methods)) {
            return;
        }
        $method = $this->request->get_method();
        $found = false;
        if (array_key_exists($method, $this->config->methods)) {
            $found = true;
        } elseif (array_key_exists(strtolower($method), $this->config->methods)) {
            @trigger_error('Setting lowercase HTTP method key "' . strtolower($method) . '" is deprecated.' . ' Use uppercase HTTP method like "' . strtoupper($method) . '".', E_USER_DEPRECATED);
            $found = true;
            $method = strtolower($method);
        }
        if ($found) {
            $old_filter_order = config(Feature::class)->old_filter_order ?? false;
            if ($old_filter_order) {
                $this->filters['before'] = array_merge($this->filters['before'], $this->config->methods[$method]);
            } else {
                $this->filters['before'] = array_merge($this->config->methods[$method], $this->filters['before']);
            }
        }
    }
    /**
     * Add any applicable configured filters to the mix.
     *
     * @param string|null $uri URI path relative to baseURL (all lowercase)
     *
     * @return void
     */
    protected function process_filters(?string $uri = null)
    {
        if (!isset($this->config->filters) || $this->config->filters === []) {
            return;
        }
        $uri = strtolower(trim($uri, '/ '));
        // Add any filters that apply to this URI
        $filters = [];
        foreach ($this->config->filters as $filter => $settings) {
            // Normalize the arguments.
            [$alias, $arguments] = $this->get_clean_name($filter);
            $filter = $arguments === [] ? $alias : $alias . ':' . implode(',', $arguments);
            // Look for inclusion rules
            if (isset($settings['before'])) {
                $path = $settings['before'];
                if ($this->path_applies($uri, $path)) {
                    $filters['before'][] = $filter;
                }
            }
            if (isset($settings['after'])) {
                $path = $settings['after'];
                if ($this->path_applies($uri, $path)) {
                    $filters['after'][] = $filter;
                }
            }
        }
        $old_filter_order = config(Feature::class)->old_filter_order ?? false;
        if (isset($filters['before'])) {
            if ($old_filter_order) {
                $this->filters['before'] = array_merge($this->filters['before'], $filters['before']);
            } else {
                $this->filters['before'] = array_merge($filters['before'], $this->filters['before']);
            }
        }
        if (isset($filters['after'])) {
            if (!$old_filter_order) {
                $filters['after'] = array_reverse($filters['after']);
            }
            $this->filters['after'] = array_merge($this->filters['after'], $filters['after']);
        }
    }
    /**
     * Maps filter aliases to the equivalent filter classes
     *
     * @param 'after'|'before' $position
     *
     * @return void
     *
     * @throws FilterException
     */
    protected function process_aliases_to_class(string $position)
    {
        $filter_class_list = [];
        foreach ($this->filters[$position] as $filter) {
            // Get arguments and clean alias
            [$alias, $arguments] = $this->get_clean_name($filter);
            if (!array_key_exists($alias, $this->config->aliases)) {
                throw Filter_Exception::for_no_alias($alias);
            }
            if (is_array($this->config->aliases[$alias])) {
                foreach ($this->config->aliases[$alias] as $class) {
                    $filter_class_list[] = [$class, $arguments];
                }
            } else {
                $filter_class_list[] = [$this->config->aliases[$alias], $arguments];
            }
        }
        if ($position === 'before') {
            $this->filters_class[$position] = array_merge($filter_class_list, $this->filters_class[$position]);
        } else {
            $this->filters_class[$position] = array_merge($this->filters_class[$position], $filter_class_list);
        }
    }
    /**
     * Check paths for match for URI
     *
     * @param string       $uri   URI to test against
     * @param array|string $paths The path patterns to test
     *
     * @return bool True if any of the paths apply to the URI
     */
    private function path_applies(string $uri, $paths)
    {
        // empty path matches all
        if ($paths === '' || $paths === []) {
            return true;
        }
        // make sure the paths are iterable
        if (is_string($paths)) {
            $paths = [$paths];
        }
        return $this->check_pseudo_regex($uri, $paths);
    }
    /**
     * Check except paths
     *
     * @param string       $uri   URI path relative to baseURL (all lowercase)
     * @param array|string $paths The except path patterns
     *
     * @return bool True if the URI matches except paths.
     */
    private function check_except(string $uri, $paths): bool
    {
        // empty array does not match anything
        if ($paths === []) {
            return false;
        }
        // make sure the paths are iterable
        if (is_string($paths)) {
            $paths = [$paths];
        }
        return $this->check_pseudo_regex($uri, $paths);
    }
    /**
     * Check the URI path as pseudo-regex
     *
     * @param string $uri   URI path relative to baseURL (all lowercase, URL-decoded)
     * @param array  $paths The except path patterns
     */
    private function check_pseudo_regex(string $uri, array $paths): bool
    {
        // treat each path as pseudo-regex
        foreach ($paths as $path) {
            // need to escape path separators
            $path = str_replace('/', '\/', trim($path, '/ '));
            // need to make pseudo wildcard real
            $path = strtolower(str_replace('*', '.*', $path));
            // Does this rule apply here?
            if (preg_match('#\A' . $path . '\z#u', $uri, $match) === 1) {
                return true;
            }
        }
        return false;
    }
}