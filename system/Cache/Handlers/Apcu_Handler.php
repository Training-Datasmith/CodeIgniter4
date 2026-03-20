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
namespace Code_Igniter\Cache\Handlers;

use Apcu_Iterator;
use Closure;
use Code_Igniter\I18n\Time;
use Config\Cache;
/**
 * APCu cache handler
 *
 * @see \CodeIgniter\Cache\Handlers\ApcuHandlerTest
 */
class Apcu_Handler extends Base_Handler
{
    /**
     * Note: Use `CacheFactory::getHandler()` to instantiate.
     */
    public function __construct(Cache $config)
    {
        $this->prefix = $config->prefix;
    }
    public function initialize(): void
    {
    }
    public function get(string $key): mixed
    {
        $key = static::validate_key($key, $this->prefix);
        $success = false;
        $data = apcu_fetch($key, $success);
        // Success returned by reference from apcu_fetch()
        return $success ? $data : null;
    }
    public function save(string $key, $value, int $ttl = 60): bool
    {
        $key = static::validate_key($key, $this->prefix);
        return apcu_store($key, $value, $ttl);
    }
    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        $key = static::validate_key($key, $this->prefix);
        return apcu_entry($key, $callback, $ttl);
    }
    public function delete(string $key): bool
    {
        $key = static::validate_key($key, $this->prefix);
        return apcu_delete($key);
    }
    public function delete_matching(string $pattern): int
    {
        $matched_keys = array_filter(array_keys(iterator_to_array(new Apcu_Iterator(null, APC_ITER_KEY))), static fn($key): bool => fnmatch($pattern, $key));
        if ($matched_keys !== []) {
            return count($matched_keys) - count(apcu_delete($matched_keys));
        }
        return 0;
    }
    public function increment(string $key, int $offset = 1): false|int
    {
        $key = static::validate_key($key, $this->prefix);
        return apcu_inc($key, $offset);
    }
    public function decrement(string $key, int $offset = 1): false|int
    {
        $key = static::validate_key($key, $this->prefix);
        return apcu_dec($key, $offset);
    }
    public function clean(): bool
    {
        return apcu_clear_cache();
    }
    public function get_cache_info(): array|false
    {
        return apcu_cache_info(true);
    }
    public function get_meta_data(string $key): ?array
    {
        $key = static::validate_key($key, $this->prefix);
        $metadata = apcu_key_info($key);
        if ($metadata !== null) {
            return ['expire' => $metadata['ttl'] > 0 ? Time::now()->get_timestamp() + $metadata['ttl'] : null, 'mtime' => $metadata['mtime'], 'data' => apcu_fetch($key)];
        }
        return null;
    }
    public function is_supported(): bool
    {
        return extension_loaded('apcu') && apcu_enabled();
    }
}