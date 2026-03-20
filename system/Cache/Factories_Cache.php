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
namespace Code_Igniter\Cache;

use Code_Igniter\Cache\Factories_Cache\File_Var_Export_Handler;
use Code_Igniter\Config\Factories;
final readonly class Factories_Cache
{
    private Cache_Interface|File_Var_Export_Handler $cache;
    public function __construct(Cache_Interface|File_Var_Export_Handler|null $cache = null)
    {
        $this->cache = $cache ?? new File_Var_Export_Handler();
    }
    public function save(string $component): void
    {
        if (!Factories::is_updated($component)) {
            return;
        }
        $data = Factories::get_component_instances($component);
        $this->cache->save($this->get_cache_key($component), $data, 3600 * 24);
    }
    private function get_cache_key(string $component): string
    {
        return 'FactoriesCache_' . $component;
    }
    public function load(string $component): bool
    {
        $key = $this->get_cache_key($component);
        $data = $this->cache->get($key);
        if (!is_array($data) || $data === []) {
            return false;
        }
        Factories::set_component_instances($component, $data);
        return true;
    }
    public function delete(string $component): void
    {
        $this->cache->delete($this->get_cache_key($component));
    }
}