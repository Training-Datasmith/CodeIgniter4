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

use Code_Igniter\Cache\Cache_Interface;
use Code_Igniter\Cache\Factories_Cache\File_Var_Export_Handler;
/**
 * FileLocator with Cache
 *
 * @see \CodeIgniter\Autoloader\FileLocatorCachedTest
 */
final class File_Locator_Cached implements File_Locator_Interface
{
    /**
     * @var CacheInterface|FileVarExportHandler
     */
    private $cache_handler;
    /**
     * Cache data
     *
     * [method => data]
     * E.g.,
     * [
     *     'search' => [$path => $foundPaths],
     * ]
     *
     * @var array<string, array<string, mixed>>
     */
    private array $cache = [];
    /**
     * Is the cache updated?
     */
    private bool $cache_updated = false;
    private string $cache_key = 'FileLocatorCache';
    /**
     * @param CacheInterface|FileVarExportHandler|null $cache
     */
    public function __construct(private readonly File_Locator $locator, $cache = null)
    {
        $this->cache_handler = $cache ?? new File_Var_Export_Handler();
        $this->load_cache();
    }
    private function load_cache(): void
    {
        $data = $this->cache_handler->get($this->cache_key);
        if (is_array($data)) {
            $this->cache = $data;
        }
    }
    public function __destruct()
    {
        $this->save_cache();
    }
    private function save_cache(): void
    {
        if ($this->cache_updated) {
            $this->cache_handler->save($this->cache_key, $this->cache, 3600 * 24);
        }
    }
    /**
     * Delete cache data
     */
    public function delete_cache(): void
    {
        $this->cache_updated = false;
        $this->cache_handler->delete($this->cache_key);
    }
    public function find_qualified_name_from_path(string $path): false|string
    {
        if (isset($this->cache['findQualifiedNameFromPath'][$path])) {
            return $this->cache['findQualifiedNameFromPath'][$path];
        }
        $classname = $this->locator->find_qualified_name_from_path($path);
        $this->cache['findQualifiedNameFromPath'][$path] = $classname;
        $this->cache_updated = true;
        return $classname;
    }
    public function get_classname(string $file): string
    {
        if (isset($this->cache['getClassname'][$file])) {
            return $this->cache['getClassname'][$file];
        }
        $classname = $this->locator->get_classname($file);
        $this->cache['getClassname'][$file] = $classname;
        $this->cache_updated = true;
        return $classname;
    }
    /**
     * @return list<non-empty-string>
     */
    public function search(string $path, string $ext = 'php', bool $prioritize_app = true): array
    {
        if (isset($this->cache['search'][$path][$ext][$prioritize_app])) {
            return $this->cache['search'][$path][$ext][$prioritize_app];
        }
        $found_paths = $this->locator->search($path, $ext, $prioritize_app);
        $this->cache['search'][$path][$ext][$prioritize_app] = $found_paths;
        $this->cache_updated = true;
        return $found_paths;
    }
    public function list_files(string $path): array
    {
        if (isset($this->cache['listFiles'][$path])) {
            return $this->cache['listFiles'][$path];
        }
        $files = $this->locator->list_files($path);
        $this->cache['listFiles'][$path] = $files;
        $this->cache_updated = true;
        return $files;
    }
    public function list_namespace_files(string $prefix, string $path): array
    {
        if (isset($this->cache['listNamespaceFiles'][$prefix][$path])) {
            return $this->cache['listNamespaceFiles'][$prefix][$path];
        }
        $files = $this->locator->list_namespace_files($prefix, $path);
        $this->cache['listNamespaceFiles'][$prefix][$path] = $files;
        $this->cache_updated = true;
        return $files;
    }
    public function locate_file(string $file, ?string $folder = null, string $ext = 'php'): false|string
    {
        $folder_key = $folder ?? '';
        if (isset($this->cache['locateFile'][$file][$folder_key][$ext])) {
            return $this->cache['locateFile'][$file][$folder_key][$ext];
        }
        $files = $this->locator->locate_file($file, $folder, $ext);
        $this->cache['locateFile'][$file][$folder_key][$ext] = $files;
        $this->cache_updated = true;
        return $files;
    }
}