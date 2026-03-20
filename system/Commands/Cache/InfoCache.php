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
namespace Code_Igniter\Commands\Cache;

use Code_Igniter\Cache\Cache_Factory;
use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\I18n\Time;
use Config\Cache;
/**
 * Shows information on the cache.
 */
class Info_Cache extends Base_Command
{
    /**
     * Command grouping.
     *
     * @var string
     */
    protected $group = 'Cache';
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'cache:info';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Shows file cache information in the current system.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'cache:info';
    /**
     * Clears the cache
     */
    public function run(array $params)
    {
        $config = config(Cache::class);
        helper('number');
        if ($config->handler !== 'file') {
            CLI::error('This command only supports the file cache handler.');
            return;
        }
        $cache = Cache_Factory::get_handler($config);
        $caches = $cache->get_cache_info();
        $tbody = [];
        foreach ($caches as $key => $field) {
            $tbody[] = [$key, clean_path($field['server_path']), number_to_size($field['size']), Time::create_from_timestamp($field['date'])];
        }
        $thead = [CLI::color('Name', 'green'), CLI::color('Server Path', 'green'), CLI::color('Size', 'green'), CLI::color('Date', 'green')];
        CLI::table($tbody, $thead);
    }
}