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

use Code_Igniter\Cache\Exceptions\Cache_Exception;
use Code_Igniter\I18n\Time;
use Config\Cache;
use Throwable;
/**
 * File system cache handler
 *
 * @see \CodeIgniter\Cache\Handlers\FileHandlerTest
 */
class File_Handler extends Base_Handler
{
    /**
     * Maximum key length.
     */
    public const MAX_KEY_LENGTH = 255;
    /**
     * Where to store cached files on the disk.
     *
     * @var string
     */
    protected $path;
    /**
     * Mode for the stored files.
     * Must be chmod-safe (octal).
     *
     * @var int
     *
     * @see https://www.php.net/manual/en/function.chmod.php
     */
    protected $mode;
    /**
     * Note: Use `CacheFactory::getHandler()` to instantiate.
     *
     * @throws CacheException
     */
    public function __construct(Cache $config)
    {
        $options = [...['storePath' => WRITEPATH . 'cache', 'mode' => 0640], ...$config->file];
        $this->path = $options['storePath'] !== '' ? $options['storePath'] : WRITEPATH . 'cache';
        $this->path = rtrim($this->path, '\/') . '/';
        if (!is_really_writable($this->path)) {
            throw Cache_Exception::for_unable_to_write($this->path);
        }
        $this->mode = $options['mode'];
        $this->prefix = $config->prefix;
        helper('filesystem');
    }
    public function initialize(): void
    {
    }
    public function get(string $key): mixed
    {
        $key = static::validate_key($key, $this->prefix);
        $data = $this->get_item($key);
        return is_array($data) ? $data['data'] : null;
    }
    public function save(string $key, mixed $value, int $ttl = 60): bool
    {
        $key = static::validate_key($key, $this->prefix);
        $contents = ['time' => Time::now()->get_timestamp(), 'ttl' => $ttl, 'data' => $value];
        if (write_file($this->path . $key, serialize($contents))) {
            try {
                chmod($this->path . $key, $this->mode);
                // @codeCoverageIgnoreStart
            } catch (Throwable $e) {
                log_message('debug', 'Failed to set mode on cache file: ' . $e);
                // @codeCoverageIgnoreEnd
            }
            return true;
        }
        return false;
    }
    public function delete(string $key): bool
    {
        $key = static::validate_key($key, $this->prefix);
        return is_file($this->path . $key) && unlink($this->path . $key);
    }
    public function delete_matching(string $pattern): int
    {
        $deleted = 0;
        foreach (glob($this->path . $pattern, GLOB_NOSORT) as $filename) {
            if (is_file($filename) && @unlink($filename)) {
                $deleted++;
            }
        }
        return $deleted;
    }
    public function increment(string $key, int $offset = 1): bool|int
    {
        $prefixed_key = static::validate_key($key, $this->prefix);
        $tmp = $this->get_item($prefixed_key);
        if ($tmp === false) {
            $tmp = ['data' => 0, 'ttl' => 60];
        }
        ['data' => $value, 'ttl' => $ttl] = $tmp;
        if (!is_int($value)) {
            return false;
        }
        $value += $offset;
        return $this->save($key, $value, $ttl) ? $value : false;
    }
    public function decrement(string $key, int $offset = 1): bool|int
    {
        return $this->increment($key, -$offset);
    }
    public function clean(): bool
    {
        return delete_files($this->path, false, true);
    }
    public function get_cache_info(): array
    {
        return get_dir_file_info($this->path);
    }
    public function get_meta_data(string $key): ?array
    {
        $key = static::validate_key($key, $this->prefix);
        if (false === $data = $this->get_item($key)) {
            return null;
        }
        return ['expire' => $data['ttl'] > 0 ? $data['time'] + $data['ttl'] : null, 'mtime' => filemtime($this->path . $key), 'data' => $data['data']];
    }
    public function is_supported(): bool
    {
        return is_writable($this->path);
    }
    /**
     * Does the heavy lifting of actually retrieving the file and
     * verifying its age.
     *
     * @return array{data: mixed, ttl: int, time: int}|false
     */
    protected function get_item(string $filename): array|false
    {
        if (!is_file($this->path . $filename)) {
            return false;
        }
        $content = @file_get_contents($this->path . $filename);
        if ($content === false) {
            return false;
        }
        try {
            $data = unserialize($content, ['allowed_classes' => false]);
        } catch (Throwable) {
            return false;
        }
        if (!is_array($data)) {
            return false;
        }
        if (!isset($data['ttl']) || !is_int($data['ttl'])) {
            return false;
        }
        if (!isset($data['time']) || !is_int($data['time'])) {
            return false;
        }
        if ($data['ttl'] > 0 && Time::now()->get_timestamp() > $data['time'] + $data['ttl']) {
            @unlink($this->path . $filename);
            return false;
        }
        return $data;
    }
    /**
     * Writes a file to disk, or returns false if not successful.
     *
     * @deprecated 4.6.1 Use `write_file()` instead.
     *
     * @param string $path
     * @param string $data
     * @param string $mode
     */
    protected function write_file($path, $data, $mode = 'wb'): bool
    {
        if (($fp = @fopen($path, $mode)) === false) {
            return false;
        }
        flock($fp, LOCK_EX);
        $result = 0;
        for ($written = 0, $length = strlen($data); $written < $length; $written += $result) {
            if (($result = fwrite($fp, substr($data, $written))) === false) {
                break;
            }
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        return is_int($result);
    }
    /**
     * Deletes all files contained in the supplied directory path.
     * Files must be writable or owned by the system in order to be deleted.
     * If the second parameter is set to TRUE, any directories contained
     * within the supplied base directory will be nuked as well.
     *
     * @deprecated 4.6.1 Use `delete_files()` instead.
     *
     * @param string $path   File path
     * @param bool   $delDir Whether to delete any directories found in the path
     * @param bool   $htdocs Whether to skip deleting .htaccess and index page files
     * @param int    $_level Current directory depth level (default: 0; internal use only)
     */
    protected function delete_files(string $path, bool $del_dir = false, bool $htdocs = false, int $_level = 0): bool
    {
        // Trim the trailing slash
        $path = rtrim($path, '/\\');
        if (!$current_dir = @opendir($path)) {
            return false;
        }
        while (false !== $filename = @readdir($current_dir)) {
            if ($filename !== '.' && $filename !== '..') {
                if (is_dir($path . DIRECTORY_SEPARATOR . $filename) && $filename[0] !== '.') {
                    $this->delete_files($path . DIRECTORY_SEPARATOR . $filename, $del_dir, $htdocs, $_level + 1);
                } elseif (!$htdocs || preg_match('/^(\.htaccess|index\.(html|htm|php)|web\.config)$/i', $filename) !== 1) {
                    @unlink($path . DIRECTORY_SEPARATOR . $filename);
                }
            }
        }
        closedir($current_dir);
        return $del_dir && $_level > 0 ? @rmdir($path) : true;
    }
    /**
     * Reads the specified directory and builds an array containing the filenames,
     * filesize, dates, and permissions
     *
     * Any sub-folders contained within the specified path are read as well.
     *
     * @deprecated 4.6.1 Use `get_dir_file_info()` instead.
     *
     * @param string $sourceDir    Path to source
     * @param bool   $topLevelOnly Look only at the top level directory specified?
     * @param bool   $_recursion   Internal variable to determine recursion status - do not use in calls
     *
     * @return array<string, array{
     *  name: string,
     *  server_path: string,
     *  size: int,
     *  date: int,
     *  relative_path: string,
     * }>|false
     */
    protected function get_dir_file_info(string $source_dir, bool $top_level_only = true, bool $_recursion = false): array|false
    {
        static $filedata = [];
        $relative_path = $source_dir;
        $file_pointer = @opendir($source_dir);
        if (!is_bool($file_pointer)) {
            // reset the array and make sure $sourceDir has a trailing slash on the initial call
            if ($_recursion === false) {
                $filedata = [];
                $resolved_src = realpath($source_dir);
                $resolved_src = $resolved_src === false ? $source_dir : $resolved_src;
                $source_dir = rtrim($resolved_src, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
            // Used to be foreach (scandir($sourceDir, 1) as $file), but scandir() is simply not as fast
            while (false !== $file = readdir($file_pointer)) {
                if (is_dir($source_dir . $file) && $file[0] !== '.' && $top_level_only === false) {
                    $this->get_dir_file_info($source_dir . $file . DIRECTORY_SEPARATOR, $top_level_only, true);
                } elseif (!is_dir($source_dir . $file) && $file[0] !== '.') {
                    $filedata[$file] = $this->get_file_info($source_dir . $file);
                    $filedata[$file]['relative_path'] = $relative_path;
                }
            }
            closedir($file_pointer);
            return $filedata;
        }
        return false;
    }
    /**
     * Given a file and path, returns the name, path, size, date modified
     * Second parameter allows you to explicitly declare what information you want returned
     * Options are: name, server_path, size, date, readable, writable, executable, fileperms
     * Returns FALSE if the file cannot be found.
     *
     * @deprecated 4.6.1 Use `get_file_info()` instead.
     *
     * @param string              $file           Path to file
     * @param list<string>|string $returnedValues Array or comma separated string of information returned
     *
     * @return array{
     *  name?: string,
     *  server_path?: string,
     *  size?: int,
     *  date?: int,
     *  readable?: bool,
     *  writable?: bool,
     *  executable?: bool,
     *  fileperms?: int
     * }|false
     */
    protected function get_file_info(string $file, $returned_values = ['name', 'server_path', 'size', 'date']): array|false
    {
        if (!is_file($file)) {
            return false;
        }
        if (is_string($returned_values)) {
            $returned_values = explode(',', $returned_values);
        }
        $file_info = [];
        foreach ($returned_values as $key) {
            switch ($key) {
                case 'name':
                    $file_info['name'] = basename($file);
                    break;
                case 'server_path':
                    $file_info['server_path'] = $file;
                    break;
                case 'size':
                    $file_info['size'] = filesize($file);
                    break;
                case 'date':
                    $file_info['date'] = filemtime($file);
                    break;
                case 'readable':
                    $file_info['readable'] = is_readable($file);
                    break;
                case 'writable':
                    $file_info['writable'] = is_writable($file);
                    break;
                case 'executable':
                    $file_info['executable'] = is_executable($file);
                    break;
                case 'fileperms':
                    $file_info['fileperms'] = fileperms($file);
                    break;
            }
        }
        return $file_info;
    }
}