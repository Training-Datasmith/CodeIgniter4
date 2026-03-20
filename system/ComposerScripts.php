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
namespace Code_Igniter;

use Filesystem_Iterator;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
use Spl_File_Info;
/**
 * This class is used by Composer during installs and updates
 * to move files to locations within the system folder so that end-users
 * do not need to use Composer to install a package, but can simply
 * download.
 *
 * @codeCoverageIgnore
 *
 * @internal
 */
final class Composer_Scripts
{
    /**
     * Path to the ThirdParty directory.
     */
    private static string $path = __DIR__ . '/ThirdParty/';
    /**
     * Direct dependencies of CodeIgniter to copy
     * contents to `system/ThirdParty/`.
     *
     * @var array<string, array<string, string>>
     */
    private static array $dependencies = ['kint-src' => ['license' => __DIR__ . '/../vendor/kint-php/kint/LICENSE', 'from' => __DIR__ . '/../vendor/kint-php/kint/src/', 'to' => __DIR__ . '/ThirdParty/Kint/'], 'kint-resources' => ['from' => __DIR__ . '/../vendor/kint-php/kint/resources/', 'to' => __DIR__ . '/ThirdParty/Kint/resources/'], 'escaper' => ['license' => __DIR__ . '/../vendor/laminas/laminas-escaper/LICENSE.md', 'from' => __DIR__ . '/../vendor/laminas/laminas-escaper/src/', 'to' => __DIR__ . '/ThirdParty/Escaper/'], 'psr-log' => ['license' => __DIR__ . '/../vendor/psr/log/LICENSE', 'from' => __DIR__ . '/../vendor/psr/log/src/', 'to' => __DIR__ . '/ThirdParty/PSR/Log/']];
    /**
     * This static method is called by Composer after every update event,
     * i.e., `composer install`, `composer update`, `composer remove`.
     */
    public static function post_update(): void
    {
        self::recursive_delete(self::$path);
        foreach (self::$dependencies as $key => $dependency) {
            // Kint may be removed.
            if (!is_dir($dependency['from']) && str_starts_with($key, 'kint')) {
                continue;
            }
            self::recursive_mirror($dependency['from'], $dependency['to']);
            if (isset($dependency['license'])) {
                $license = basename($dependency['license']);
                copy($dependency['license'], $dependency['to'] . '/' . $license);
            }
        }
        self::copy_kint_init_files();
    }
    /**
     * Recursively remove the contents of the previous `system/ThirdParty`.
     */
    private static function recursive_delete(string $directory): void
    {
        if (!is_dir($directory)) {
            echo sprintf('Cannot recursively delete "%s" as it does not exist.', $directory) . PHP_EOL;
            return;
        }
        /** @var SplFileInfo $file */
        foreach (new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator(rtrim($directory, '\/'), Filesystem_Iterator::SKIP_DOTS), Recursive_Iterator_Iterator::CHILD_FIRST) as $file) {
            $path = $file->get_pathname();
            if ($file->is_dir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
    /**
     * Recursively copy the files and directories of the origin directory
     * into the target directory, i.e. "mirror" its contents.
     */
    private static function recursive_mirror(string $origin_dir, string $target_dir): void
    {
        $origin_dir = rtrim($origin_dir, '\/');
        $target_dir = rtrim($target_dir, '\/');
        if (!is_dir($origin_dir)) {
            echo sprintf('The origin directory "%s" was not found.', $origin_dir);
            exit(1);
        }
        if (is_dir($target_dir)) {
            echo sprintf('The target directory "%s" is existing. Run %s::recursiveDelete(\'%s\') first.', $target_dir, self::class, $target_dir);
            exit(1);
        }
        if (!@mkdir($target_dir, 0755, true)) {
            echo sprintf('Cannot create the target directory: "%s"', $target_dir) . PHP_EOL;
            exit(1);
        }
        $dir_len = strlen($origin_dir);
        /** @var SplFileInfo $file */
        foreach (new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($origin_dir, Filesystem_Iterator::SKIP_DOTS), Recursive_Iterator_Iterator::SELF_FIRST) as $file) {
            $origin = $file->get_pathname();
            $target = $target_dir . substr($origin, $dir_len);
            if ($file->is_dir()) {
                @mkdir($target, 0755);
            } else {
                @copy($origin, $target);
            }
        }
    }
    /**
     * Copy Kint's init files into `system/ThirdParty/Kint/`
     */
    private static function copy_kint_init_files(): void
    {
        $origin_dir = self::$dependencies['kint-src']['from'] . '../';
        $target_dir = self::$dependencies['kint-src']['to'];
        foreach (['init.php', 'init_helpers.php'] as $kint_init) {
            @copy($origin_dir . $kint_init, $target_dir . $kint_init);
        }
    }
}