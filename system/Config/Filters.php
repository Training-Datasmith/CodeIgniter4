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

use Code_Igniter\Filters\Cors;
use Code_Igniter\Filters\CSRF;
use Code_Igniter\Filters\Debug_Toolbar;
use Code_Igniter\Filters\Force_Https;
use Code_Igniter\Filters\Honeypot;
use Code_Igniter\Filters\Invalid_Chars;
use Code_Igniter\Filters\Page_Cache;
use Code_Igniter\Filters\Performance_Metrics;
use Code_Igniter\Filters\Secure_Headers;
/**
 * Filters configuration
 */
class Filters extends Base_Config
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = ['csrf' => CSRF::class, 'toolbar' => Debug_Toolbar::class, 'honeypot' => Honeypot::class, 'invalidchars' => Invalid_Chars::class, 'secureheaders' => Secure_Headers::class, 'cors' => Cors::class, 'forcehttps' => Force_Https::class, 'pagecache' => Page_Cache::class, 'performance' => Performance_Metrics::class];
    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = ['before' => [
        'forcehttps',
        // Force Global Secure Requests
        'pagecache',
    ], 'after' => [
        'pagecache',
        // Web Page Caching
        'performance',
        // Performance Metrics
        'toolbar',
    ]];
    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array{
     *    before: array<string, array{except: list<string>|string}>|list<string>,
     *    after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = ['before' => [], 'after' => []];
    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];
    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}