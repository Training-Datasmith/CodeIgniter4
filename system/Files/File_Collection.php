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
namespace Code_Igniter\Files;

use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Files\Exceptions\File_Exception;
use Code_Igniter\Files\Exceptions\File_Not_Found_Exception;
use Countable;
use Generator;
use IteratorAggregate;
/**
 * File Collection Class
 *
 * Representation for a group of files, with utilities for locating,
 * filtering, and ordering them.
 *
 * @template-implements IteratorAggregate<int, File>
 * @see \CodeIgniter\Files\FileCollectionTest
 */
class File_Collection implements Countable, IteratorAggregate
{
    /**
     * The current list of file paths.
     *
     * @var list<string>
     */
    protected $files = [];
    // --------------------------------------------------------------------
    // Support Methods
    // --------------------------------------------------------------------
    /**
     * Resolves a full path and verifies it is an actual directory.
     *
     * @throws FileException
     */
    final protected static function resolve_directory(string $directory): string
    {
        if (!is_dir($directory = set_realpath($directory))) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
            throw File_Exception::for_expected_directory($caller['function']);
        }
        return $directory;
    }
    /**
     * Resolves a full path and verifies it is an actual file.
     *
     * @throws FileException
     */
    final protected static function resolve_file(string $file): string
    {
        if (!is_file($file = set_realpath($file))) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
            throw File_Exception::for_expected_file($caller['function']);
        }
        return $file;
    }
    /**
     * Removes files that are not part of the given directory (recursive).
     *
     * @param list<string> $files
     *
     * @return list<string>
     */
    final protected static function filter_files(array $files, string $directory): array
    {
        $directory = self::resolve_directory($directory);
        return array_filter($files, static fn(string $value): bool => str_starts_with($value, $directory));
    }
    /**
     * Returns any files whose `basename` matches the given pattern.
     *
     * @param list<string> $files
     * @param string       $pattern Regex or pseudo-regex string
     *
     * @return list<string>
     */
    final protected static function match_files(array $files, string $pattern): array
    {
        // Convert pseudo-regex into their true form
        if (@preg_match($pattern, '') === false) {
            $pattern = str_replace(['#', '.', '*', '?'], ['\#', '\.', '.*', '.'], $pattern);
            $pattern = "#\\A{$pattern}\\z#";
        }
        return array_filter($files, static fn($value): bool => (bool) preg_match($pattern, basename($value)));
    }
    // --------------------------------------------------------------------
    // Class Core
    // --------------------------------------------------------------------
    /**
     * Loads the Filesystem helper and adds any initial files.
     *
     * @param list<string> $files
     */
    public function __construct(array $files = [])
    {
        helper(['filesystem']);
        $this->add($files)->define();
    }
    /**
     * Applies any initial inputs after the constructor.
     * This method is a stub to be implemented by child classes.
     */
    protected function define(): void
    {
    }
    /**
     * Optimizes and returns the current file list.
     *
     * @return list<string>
     */
    public function get(): array
    {
        $this->files = array_unique($this->files);
        sort($this->files, SORT_STRING);
        return $this->files;
    }
    /**
     * Sets the file list directly, files are still subject to verification.
     * This works as a "reset" method with [].
     *
     * @param list<string> $files The new file list to use
     *
     * @return $this
     */
    public function set(array $files)
    {
        $this->files = [];
        return $this->add_files($files);
    }
    /**
     * Adds an array/single file or directory to the list.
     *
     * @param list<string>|string $paths
     *
     * @return $this
     */
    public function add($paths, bool $recursive = true)
    {
        $paths = (array) $paths;
        foreach ($paths as $path) {
            if (!is_string($path)) {
                throw new InvalidArgumentException('FileCollection paths must be strings.');
            }
            try {
                // Test for a directory
                self::resolve_directory($path);
            } catch (File_Exception) {
                $this->add_file($path);
                continue;
            }
            $this->add_directory($path, $recursive);
        }
        return $this;
    }
    // --------------------------------------------------------------------
    // File Handling
    // --------------------------------------------------------------------
    /**
     * Verifies and adds files to the list.
     *
     * @param list<string> $files
     *
     * @return $this
     */
    public function add_files(array $files)
    {
        foreach ($files as $file) {
            $this->add_file($file);
        }
        return $this;
    }
    /**
     * Verifies and adds a single file to the file list.
     *
     * @return $this
     */
    public function add_file(string $file)
    {
        $this->files[] = self::resolve_file($file);
        return $this;
    }
    /**
     * Removes files from the list.
     *
     * @param list<string> $files
     *
     * @return $this
     */
    public function remove_files(array $files)
    {
        $this->files = array_diff($this->files, $files);
        return $this;
    }
    /**
     * Removes a single file from the list.
     *
     * @return $this
     */
    public function remove_file(string $file)
    {
        return $this->remove_files([$file]);
    }
    // --------------------------------------------------------------------
    // Directory Handling
    // --------------------------------------------------------------------
    /**
     * Verifies and adds files from each
     * directory to the list.
     *
     * @param list<string> $directories
     *
     * @return $this
     */
    public function add_directories(array $directories, bool $recursive = false)
    {
        foreach ($directories as $directory) {
            $this->add_directory($directory, $recursive);
        }
        return $this;
    }
    /**
     * Verifies and adds all files from a directory.
     *
     * @return $this
     */
    public function add_directory(string $directory, bool $recursive = false)
    {
        $directory = self::resolve_directory($directory);
        // Map the directory to depth 2 to so directories become arrays
        foreach (directory_map($directory, 2, true) as $key => $path) {
            if (is_string($path)) {
                $this->add_file($directory . $path);
            } elseif ($recursive && is_array($path)) {
                $this->add_directory($directory . $key, true);
            }
        }
        return $this;
    }
    // --------------------------------------------------------------------
    // Filtering
    // --------------------------------------------------------------------
    /**
     * Removes any files from the list that match the supplied pattern
     * (within the optional scope).
     *
     * @param string      $pattern Regex or pseudo-regex string
     * @param string|null $scope   The directory to limit the scope
     *
     * @return $this
     */
    public function remove_pattern(string $pattern, ?string $scope = null)
    {
        if ($pattern === '') {
            return $this;
        }
        // Start with all files or those in scope
        $files = $scope === null ? $this->files : self::filter_files($this->files, $scope);
        // Remove any files that match the pattern
        return $this->remove_files(self::match_files($files, $pattern));
    }
    /**
     * Keeps only the files from the list that match
     * (within the optional scope).
     *
     * @param string      $pattern Regex or pseudo-regex string
     * @param string|null $scope   A directory to limit the scope
     *
     * @return $this
     */
    public function retain_pattern(string $pattern, ?string $scope = null)
    {
        if ($pattern === '') {
            return $this;
        }
        // Start with all files or those in scope
        $files = $scope === null ? $this->files : self::filter_files($this->files, $scope);
        // Matches the pattern within the scoped files and remove their inverse.
        return $this->remove_files(array_diff($files, self::match_files($files, $pattern)));
    }
    /**
     * Keeps only the files from the list that match multiple patterns
     * (within the optional scope).
     *
     * @param list<string> $patterns Array of regex or pseudo-regex strings
     * @param string|null  $scope    A directory to limit the scope
     *
     * @return $this
     */
    public function retain_multiple_patterns(array $patterns, ?string $scope = null)
    {
        if ($patterns === []) {
            return $this;
        }
        if (count($patterns) === 1 && $patterns[0] === '') {
            return $this;
        }
        // Start with all files or those in scope
        $files = $scope === null ? $this->files : self::filter_files($this->files, $scope);
        // Add files to retain to array
        $files_to_retain = [];
        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }
            // Matches the pattern within the scoped files
            $files_to_retain = array_merge($files_to_retain, self::match_files($files, $pattern));
        }
        // Remove the inverse of files to retain
        return $this->remove_files(array_diff($files, $files_to_retain));
    }
    // --------------------------------------------------------------------
    // Interface Methods
    // --------------------------------------------------------------------
    /**
     * Returns the current number of files in the collection.
     * Fulfills Countable.
     */
    public function count(): int
    {
        return count($this->files);
    }
    /**
     * Yields as an Iterator for the current files.
     * Fulfills IteratorAggregate.
     *
     * @return Generator<File>
     *
     * @throws FileNotFoundException
     */
    public function getIterator(): Generator
    {
        foreach ($this->get() as $file) {
            yield new File($file, true);
        }
    }
}