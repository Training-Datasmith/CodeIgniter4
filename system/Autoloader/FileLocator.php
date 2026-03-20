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

/**
 * Allows loading non-class files in a namespaced manner.
 * Works with Helpers, Views, etc.
 *
 * @see \CodeIgniter\Autoloader\FileLocatorTest
 */
class File_Locator implements File_Locator_Interface
{
    /**
     * The Autoloader to use.
     *
     * @var Autoloader
     */
    protected $autoloader;
    /**
     * List of classnames that did not exist.
     *
     * @var list<class-string>
     */
    private array $invalid_classnames = [];
    public function __construct(Autoloader $autoloader)
    {
        $this->autoloader = $autoloader;
    }
    /**
     * Attempts to locate a file by examining the name for a namespace
     * and looking through the PSR-4 namespaced files that we know about.
     *
     * @param non-empty-string      $file   The relative file path or namespaced file to
     *                                      locate. If not namespaced, search in the app
     *                                      folder.
     * @param non-empty-string|null $folder The folder within the namespace that we should
     *                                      look for the file. If $file does not contain
     *                                      this value, it will be appended to the namespace
     *                                      folder.
     * @param string                $ext    The file extension the file should have.
     *
     * @return false|non-empty-string The path to the file, or false if not found.
     */
    public function locate_file(string $file, ?string $folder = null, string $ext = 'php')
    {
        $file = $this->ensure_ext($file, $ext);
        // Clears the folder name if it is at the beginning of the filename
        if ($folder !== null && str_starts_with($file, $folder)) {
            $file = substr($file, strlen($folder . '/'));
        }
        // Is not namespaced? Try the application folder.
        if (!str_contains($file, '\\')) {
            return $this->legacy_locate($file, $folder);
        }
        // Standardize slashes to handle nested directories.
        $file = strtr($file, '/', '\\');
        $file = ltrim($file, '\\');
        $segments = explode('\\', $file);
        // The first segment will be empty if a slash started the filename.
        if ($segments[0] === '') {
            unset($segments[0]);
        }
        $paths = [];
        $filename = '';
        // Namespaces always comes with arrays of paths
        $namespaces = $this->autoloader->get_namespace();
        foreach (array_keys($namespaces) as $namespace) {
            if (substr($file, 0, strlen($namespace) + 1) === $namespace . '\\') {
                $file_without_namespace = substr($file, strlen($namespace));
                // There may be sub-namespaces of the same vendor,
                // so overwrite them with namespaces found later.
                $paths = $namespaces[$namespace];
                $filename = ltrim(str_replace('\\', '/', $file_without_namespace), '/');
            }
        }
        // if no namespaces matched then quit
        if ($paths === []) {
            return false;
        }
        // Check each path in the namespace
        foreach ($paths as $path) {
            // Ensure trailing slash
            $path = rtrim($path, '/') . '/';
            // If we have a folder name, then the calling function
            // expects this file to be within that folder, like 'Views',
            // or 'libraries'.
            if ($folder !== null && !str_contains($path . $filename, '/' . $folder . '/')) {
                $path .= trim($folder, '/') . '/';
            }
            $path .= $filename;
            if (is_file($path)) {
                return $path;
            }
        }
        return false;
    }
    /**
     * Examines a file and returns the fully qualified class name.
     */
    public function get_classname(string $file): string
    {
        if (is_dir($file)) {
            return '';
        }
        $php = file_get_contents($file);
        $tokens = token_get_all($php);
        $dlm = false;
        $namespace = '';
        $class_name = '';
        foreach ($tokens as $i => $token) {
            if ($i < 2) {
                continue;
            }
            if (isset($tokens[$i - 2][1]) && ($tokens[$i - 2][1] === 'phpnamespace' || $tokens[$i - 2][1] === 'namespace') || $dlm && $tokens[$i - 1][0] === T_NS_SEPARATOR && $token[0] === T_STRING) {
                if (!$dlm) {
                    $namespace = '';
                }
                if (isset($token[1])) {
                    $namespace = $namespace !== '' ? $namespace . '\\' . $token[1] : $token[1];
                    $dlm = true;
                }
            } elseif ($dlm && $token[0] !== T_NS_SEPARATOR && $token[0] !== T_STRING) {
                $dlm = false;
            }
            if (($tokens[$i - 2][0] === T_CLASS || isset($tokens[$i - 2][1]) && $tokens[$i - 2][1] === 'phpclass') && $tokens[$i - 1][0] === T_WHITESPACE && $token[0] === T_STRING) {
                $class_name = $token[1];
                break;
            }
        }
        if ($class_name === '') {
            return '';
        }
        return $namespace . '\\' . $class_name;
    }
    /**
     * Searches through all of the defined namespaces looking for a file.
     * Returns an array of all found locations for the defined file.
     *
     * Example:
     *
     *  $locator->search('Config/Routes.php');
     *  // Assuming PSR4 namespaces include foo and bar, might return:
     *  [
     *      'app/Modules/foo/Config/Routes.php',
     *      'app/Modules/bar/Config/Routes.php',
     *  ]
     *
     * @return list<non-empty-string>
     */
    public function search(string $path, string $ext = 'php', bool $prioritize_app = true): array
    {
        $path = $this->ensure_ext($path, $ext);
        $found_paths = [];
        $app_paths = [];
        foreach ($this->get_namespaces() as $namespace) {
            if (isset($namespace['path']) && is_file($namespace['path'] . $path)) {
                $full_path = $namespace['path'] . $path;
                $resolved_path = realpath($full_path);
                $full_path = $resolved_path !== false ? $resolved_path : $full_path;
                if ($prioritize_app) {
                    $found_paths[] = $full_path;
                } elseif (str_starts_with($full_path, APPPATH)) {
                    $app_paths[] = $full_path;
                } else {
                    $found_paths[] = $full_path;
                }
            }
        }
        if (!$prioritize_app && $app_paths !== []) {
            $found_paths = [...$found_paths, ...$app_paths];
        }
        return array_values(array_unique($found_paths));
    }
    /**
     * Ensures a extension is at the end of a filename
     */
    protected function ensure_ext(string $path, string $ext): string
    {
        if ($ext !== '') {
            $ext = '.' . $ext;
            if (!str_ends_with($path, $ext)) {
                $path .= $ext;
            }
        }
        return $path;
    }
    /**
     * Return the namespace mappings we know about.
     *
     * @return list<array{prefix: non-empty-string, path: non-empty-string}>
     */
    protected function get_namespaces()
    {
        $namespaces = [];
        $system = [];
        foreach ($this->autoloader->get_namespace() as $prefix => $paths) {
            foreach ($paths as $path) {
                if ($prefix === 'CodeIgniter') {
                    $system[] = ['prefix' => $prefix, 'path' => rtrim($path, '\/') . DIRECTORY_SEPARATOR];
                    continue;
                }
                $namespaces[] = ['prefix' => $prefix, 'path' => rtrim($path, '\/') . DIRECTORY_SEPARATOR];
            }
        }
        // Save system for last
        return array_merge($namespaces, $system);
    }
    public function find_qualified_name_from_path(string $path)
    {
        $resolved_path = realpath($path);
        $path = $resolved_path !== false ? $resolved_path : $path;
        if (!is_file($path)) {
            return false;
        }
        foreach ($this->get_namespaces() as $namespace) {
            $resolved_namespace_path = realpath($namespace['path']);
            $namespace['path'] = $resolved_namespace_path !== false ? $resolved_namespace_path : $namespace['path'];
            if ($namespace['path'] === '') {
                continue;
            }
            if (mb_strpos($path, $namespace['path']) === 0) {
                $class_name = $namespace['prefix'] . '\\' . ltrim(str_replace('/', '\\', mb_substr($path, mb_strlen($namespace['path']))), '\\');
                // Remove the file extension (.php)
                /** @var class-string $className */
                $class_name = mb_substr($class_name, 0, -4);
                if (in_array($class_name, $this->invalid_classnames, true)) {
                    continue;
                }
                if (class_exists($class_name)) {
                    return $class_name;
                }
                $this->invalid_classnames[] = $class_name;
            }
        }
        return false;
    }
    /**
     * Scans the defined namespaces, returning a list of all files
     * that are contained within the subpath specified by $path.
     *
     * @return list<string> List of file paths
     */
    public function list_files(string $path): array
    {
        if ($path === '') {
            return [];
        }
        $files = [];
        helper('filesystem');
        foreach ($this->get_namespaces() as $namespace) {
            $full_path = $namespace['path'] . $path;
            $resolved_path = realpath($full_path);
            $full_path = $resolved_path !== false ? $resolved_path : $full_path;
            if (!is_dir($full_path)) {
                continue;
            }
            $temp_files = get_filenames($full_path, true, false, false);
            if ($temp_files !== []) {
                $files = array_merge($files, $temp_files);
            }
        }
        return $files;
    }
    /**
     * Scans the provided namespace, returning a list of all files
     * that are contained within the sub path specified by $path.
     *
     * @return list<non-empty-string> List of file paths
     */
    public function list_namespace_files(string $prefix, string $path): array
    {
        if ($path === '' || $prefix === '') {
            return [];
        }
        $files = [];
        helper('filesystem');
        // autoloader->getNamespace($prefix) returns an array of paths for that namespace
        foreach ($this->autoloader->get_namespace($prefix) as $namespace_path) {
            $full_path = rtrim($namespace_path, '/') . '/' . $path;
            $resolved_path = realpath($full_path);
            $full_path = $resolved_path !== false ? $resolved_path : $full_path;
            if (!is_dir($full_path)) {
                continue;
            }
            $temp_files = get_filenames($full_path, true, false, false);
            if ($temp_files !== []) {
                $files = array_merge($files, $temp_files);
            }
        }
        return $files;
    }
    /**
     * Checks the app folder to see if the file can be found.
     * Only for use with filenames that DO NOT include namespacing.
     *
     * @param non-empty-string|null $folder
     *
     * @return false|string The path to the file, or false if not found.
     */
    protected function legacy_locate(string $file, ?string $folder = null)
    {
        $path = APPPATH . ($folder === null ? $file : $folder . '/' . $file);
        $resolved_path = realpath($path);
        $path = $resolved_path !== false ? $resolved_path : $path;
        if (is_file($path)) {
            return $path;
        }
        return false;
    }
}