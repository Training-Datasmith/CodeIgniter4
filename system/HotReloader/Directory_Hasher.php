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
namespace Code_Igniter\Hot_Reloader;

use Code_Igniter\Exceptions\Framework_Exception;
use Config\Toolbar;
use Filesystem_Iterator;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
/**
 * @internal
 * @see \CodeIgniter\HotReloader\DirectoryHasherTest
 */
final class Directory_Hasher
{
    /**
     * Generates an md5 value of all directories that are watched by the
     * Hot Reloader, as defined in the Config\Toolbar.
     *
     * This is the current app fingerprint.
     */
    public function hash(): string
    {
        return md5(implode('', $this->hash_app()));
    }
    /**
     * Generates an array of md5 hashes for all directories that are
     * watched by the Hot Reloader, as defined in the Config\Toolbar.
     */
    public function hash_app(): array
    {
        $hashes = [];
        $watched_directories = config(Toolbar::class)->watched_directories;
        foreach ($watched_directories as $directory) {
            if (is_dir(ROOTPATH . $directory)) {
                $hashes[$directory] = $this->hash_directory(ROOTPATH . $directory);
            }
        }
        return array_unique(array_filter($hashes));
    }
    /**
     * Generates an md5 hash of a given directory and all of its files
     * that match the watched extensions defined in Config\Toolbar.
     */
    public function hash_directory(string $path): string
    {
        if (!is_dir($path)) {
            throw Framework_Exception::for_invalid_directory($path);
        }
        $directory = new Recursive_Directory_Iterator($path, Filesystem_Iterator::SKIP_DOTS);
        $filter = new Iterator_Filter($directory);
        $iterator = new Recursive_Iterator_Iterator($filter);
        $hashes = [];
        foreach ($iterator as $file) {
            if ($file->is_file()) {
                $hashes[] = md5_file($file->get_real_path());
            }
        }
        return md5(implode('', $hashes));
    }
}