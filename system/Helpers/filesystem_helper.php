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
use Code_Igniter\Exceptions\InvalidArgumentException;
// CodeIgniter File System Helpers
if (!function_exists('directory_map')) {
    /**
     * Create a Directory Map
     *
     * Reads the specified directory and builds an array
     * representation of it. Sub-folders contained with the
     * directory will be mapped as well.
     *
     * @param string $sourceDir      Path to source
     * @param int    $directoryDepth Depth of directories to traverse
     *                               (0 = fully recursive, 1 = current dir, etc)
     * @param bool   $hidden         Whether to show hidden files
     */
    function directory_map(string $source_dir, int $directory_depth = 0, bool $hidden = false): array
    {
        try {
            $fp = opendir($source_dir);
            $file_data = [];
            $new_depth = $directory_depth - 1;
            $source_dir = rtrim($source_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            while (false !== $file = readdir($fp)) {
                // Remove '.', '..', and hidden files [optional]
                if ($file === '.' || $file === '..' || $hidden === false && $file[0] === '.') {
                    continue;
                }
                if (is_dir($source_dir . $file)) {
                    $file .= DIRECTORY_SEPARATOR;
                }
                if (($directory_depth < 1 || $new_depth > 0) && is_dir($source_dir . $file)) {
                    $file_data[$file] = directory_map($source_dir . $file, $new_depth, $hidden);
                } else {
                    $file_data[] = $file;
                }
            }
            closedir($fp);
            return $file_data;
        } catch (Throwable) {
            return [];
        }
    }
}
if (!function_exists('directory_mirror')) {
    /**
     * Recursively copies the files and directories of the origin directory
     * into the target directory, i.e. "mirror" its contents.
     *
     * @param bool $overwrite Whether individual files overwrite on collision
     *
     * @throws InvalidArgumentException
     */
    function directory_mirror(string $origin_dir, string $target_dir, bool $overwrite = true): void
    {
        if (!is_dir($origin_dir = rtrim($origin_dir, '\/'))) {
            throw new InvalidArgumentException(sprintf('The origin directory "%s" was not found.', $origin_dir));
        }
        if (!is_dir($target_dir = rtrim($target_dir, '\/'))) {
            @mkdir($target_dir, 0755, true);
        }
        $dir_len = strlen($origin_dir);
        /**
         * @var SplFileInfo $file
         */
        foreach (new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($origin_dir, Filesystem_Iterator::SKIP_DOTS), Recursive_Iterator_Iterator::SELF_FIRST) as $file) {
            $origin = $file->get_pathname();
            $target = $target_dir . substr($origin, $dir_len);
            if ($file->is_dir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755);
                }
            } elseif ($overwrite || !is_file($target)) {
                copy($origin, $target);
            }
        }
    }
}
if (!function_exists('write_file')) {
    /**
     * Write File
     *
     * Writes data to the file specified in the path.
     * Creates a new file if non-existent.
     *
     * @param string $path File path
     * @param string $data Data to write
     * @param string $mode fopen() mode (default: 'wb')
     */
    function write_file(string $path, string $data, string $mode = 'wb'): bool
    {
        try {
            $fp = fopen($path, $mode);
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
        } catch (Throwable) {
            return false;
        }
    }
}
if (!function_exists('delete_files')) {
    /**
     * Delete Files
     *
     * Deletes all files contained in the supplied directory path.
     * Files must be writable or owned by the system in order to be deleted.
     * If the second parameter is set to true, any directories contained
     * within the supplied base directory will be nuked as well.
     *
     * @param string $path   File path
     * @param bool   $delDir Whether to delete any directories found in the path
     * @param bool   $htdocs Whether to skip deleting .htaccess and index page files
     * @param bool   $hidden Whether to include hidden files (files beginning with a period)
     */
    function delete_files(string $path, bool $del_dir = false, bool $htdocs = false, bool $hidden = false): bool
    {
        $path = realpath($path) ?: $path;
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        try {
            foreach (new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($path, Recursive_Directory_Iterator::SKIP_DOTS), Recursive_Iterator_Iterator::CHILD_FIRST) as $object) {
                $filename = $object->get_filename();
                if (!$hidden && $filename[0] === '.') {
                    continue;
                }
                if (!$htdocs || preg_match('/^(\.htaccess|index\.(html|htm|php)|web\.config)$/i', $filename) !== 1) {
                    $is_dir = $object->is_dir();
                    if ($is_dir && $del_dir) {
                        rmdir($object->get_pathname());
                        continue;
                    }
                    if (!$is_dir) {
                        unlink($object->get_pathname());
                    }
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
if (!function_exists('get_filenames')) {
    /**
     * Get Filenames
     *
     * Reads the specified directory and builds an array containing the filenames.
     * Any sub-folders contained within the specified path are read as well.
     *
     * @param string    $sourceDir   Path to source
     * @param bool|null $includePath Whether to include the path as part of the filename; false for no path, null for a relative path, true for full path
     * @param bool      $hidden      Whether to include hidden files (files beginning with a period)
     * @param bool      $includeDir  Whether to include directories
     */
    function get_filenames(string $source_dir, ?bool $include_path = false, bool $hidden = false, bool $include_dir = true): array
    {
        $files = [];
        $source_dir = realpath($source_dir) ?: $source_dir;
        $source_dir = rtrim($source_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        try {
            foreach (new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($source_dir, Recursive_Directory_Iterator::SKIP_DOTS | Filesystem_Iterator::FOLLOW_SYMLINKS), Recursive_Iterator_Iterator::SELF_FIRST) as $name => $object) {
                $basename = pathinfo($name, PATHINFO_BASENAME);
                if (!$hidden && $basename[0] === '.') {
                    continue;
                }
                if ($include_dir || !$object->is_dir()) {
                    if ($include_path === false) {
                        $files[] = $basename;
                    } elseif ($include_path === null) {
                        $files[] = str_replace($source_dir, '', $name);
                    } else {
                        $files[] = $name;
                    }
                }
            }
        } catch (Throwable) {
            return [];
        }
        sort($files);
        return $files;
    }
}
if (!function_exists('get_dir_file_info')) {
    /**
     * Get Directory File Information
     *
     * Reads the specified directory and builds an array containing the filenames,
     * filesize, dates, and permissions
     *
     * Any sub-folders contained within the specified path are read as well.
     *
     * @param string $sourceDir    Path to source
     * @param bool   $topLevelOnly Look only at the top level directory specified?
     * @param bool   $recursion    Internal variable to determine recursion status - do not use in calls
     *
     * @return array<string, array{
     *  name: string,
     *  server_path: string,
     *  size: int,
     *  date: int,
     *  relative_path: string,
     * }>
     */
    function get_dir_file_info(string $source_dir, bool $top_level_only = true, bool $recursion = false): array
    {
        static $file_data = [];
        $relative_path = $source_dir;
        try {
            $fp = opendir($source_dir);
            // reset the array and make sure $sourceDir has a trailing slash on the initial call
            if ($recursion === false) {
                $file_data = [];
                $source_dir = rtrim(realpath($source_dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
            // Used to be foreach (scandir($sourceDir, 1) as $file), but scandir() is simply not as fast
            while (false !== $file = readdir($fp)) {
                if (is_dir($source_dir . $file) && $file[0] !== '.' && $top_level_only === false) {
                    get_dir_file_info($source_dir . $file . DIRECTORY_SEPARATOR, $top_level_only, true);
                } elseif ($file[0] !== '.') {
                    $file_data[$file] = get_file_info($source_dir . $file);
                    $file_data[$file]['relative_path'] = $relative_path;
                }
            }
            closedir($fp);
            return $file_data;
        } catch (Throwable) {
            return [];
        }
    }
}
if (!function_exists('get_file_info')) {
    /**
     * Get File Info
     *
     * Given a file and path, returns the name, path, size, date modified
     * Second parameter allows you to explicitly declare what information you want returned
     * Options are: name, server_path, size, date, readable, writable, executable, fileperms
     * Returns false if the file cannot be found.
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
     * }|null
     */
    function get_file_info(string $file, $returned_values = ['name', 'server_path', 'size', 'date'])
    {
        if (!is_file($file)) {
            return null;
        }
        $file_info = [];
        if (is_string($returned_values)) {
            $returned_values = explode(',', $returned_values);
        }
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
                    $file_info['writable'] = is_really_writable($file);
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
if (!function_exists('symbolic_permissions')) {
    /**
     * Symbolic Permissions
     *
     * Takes a numeric value representing a file's permissions and returns
     * standard symbolic notation representing that value
     *
     * @param int $perms Permissions
     */
    function symbolic_permissions(int $perms): string
    {
        if (($perms & 0xc000) === 0xc000) {
            $symbolic = 's';
            // Socket
        } elseif (($perms & 0xa000) === 0xa000) {
            $symbolic = 'l';
            // Symbolic Link
        } elseif (($perms & 0x8000) === 0x8000) {
            $symbolic = '-';
            // Regular
        } elseif (($perms & 0x6000) === 0x6000) {
            $symbolic = 'b';
            // Block special
        } elseif (($perms & 0x4000) === 0x4000) {
            $symbolic = 'd';
            // Directory
        } elseif (($perms & 0x2000) === 0x2000) {
            $symbolic = 'c';
            // Character special
        } elseif (($perms & 0x1000) === 0x1000) {
            $symbolic = 'p';
            // FIFO pipe
        } else {
            $symbolic = 'u';
            // Unknown
        }
        // Owner
        $symbolic .= (($perms & 0x100) !== 0 ? 'r' : '-') . (($perms & 0x80) !== 0 ? 'w' : '-') . (($perms & 0x40) !== 0 ? ($perms & 0x800) !== 0 ? 's' : 'x' : (($perms & 0x800) !== 0 ? 'S' : '-'));
        // Group
        $symbolic .= (($perms & 0x20) !== 0 ? 'r' : '-') . (($perms & 0x10) !== 0 ? 'w' : '-') . (($perms & 0x8) !== 0 ? ($perms & 0x400) !== 0 ? 's' : 'x' : (($perms & 0x400) !== 0 ? 'S' : '-'));
        // World
        $symbolic .= (($perms & 0x4) !== 0 ? 'r' : '-') . (($perms & 0x2) !== 0 ? 'w' : '-') . (($perms & 0x1) !== 0 ? ($perms & 0x200) !== 0 ? 't' : 'x' : (($perms & 0x200) !== 0 ? 'T' : '-'));
        return $symbolic;
    }
}
if (!function_exists('octal_permissions')) {
    /**
     * Octal Permissions
     *
     * Takes a numeric value representing a file's permissions and returns
     * a three character string representing the file's octal permissions
     *
     * @param int $perms Permissions
     */
    function octal_permissions(int $perms): string
    {
        return substr(sprintf('%o', $perms), -3);
    }
}
if (!function_exists('same_file')) {
    /**
     * Checks if two files both exist and have identical hashes
     *
     * @return bool Same or not
     */
    function same_file(string $file1, string $file2): bool
    {
        return is_file($file1) && is_file($file2) && md5_file($file1) === md5_file($file2);
    }
}
if (!function_exists('set_realpath')) {
    /**
     * Set Realpath
     *
     * @param bool $checkExistence Checks to see if the path exists
     */
    function set_realpath(string $path, bool $check_existence = false): string
    {
        // Security check to make sure the path is NOT a URL. No remote file inclusion!
        if (preg_match('#^(http:\/\/|https:\/\/|www\.|ftp)#i', $path) || filter_var($path, FILTER_VALIDATE_IP) === $path) {
            throw new InvalidArgumentException('The path you submitted must be a local server path, not a URL');
        }
        // Resolve the path
        if (realpath($path) !== false) {
            $path = realpath($path);
        } elseif ($check_existence && !is_dir($path) && !is_file($path)) {
            throw new InvalidArgumentException('Not a valid path: ' . $path);
        }
        // Add a trailing slash, if this is a directory
        return is_dir($path) ? rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : $path;
    }
}