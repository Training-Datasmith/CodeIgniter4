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
namespace Code_Igniter\Commands\Utilities;

use Code_Igniter\Autoloader\File_Locator;
use Code_Igniter\Autoloader\File_Locator_Cached;
use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\Exceptions\RuntimeException;
use Code_Igniter\Publisher\Publisher;
/**
 * Optimize for production.
 */
final class Optimize extends Base_Command
{
    /**
     * The group the command is lumped under
     * when listing commands.
     *
     * @var string
     */
    protected $group = 'CodeIgniter';
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'optimize';
    /**
     * The Command's short description
     *
     * @var string
     */
    protected $description = 'Optimize for production.';
    /**
     * The Command's usage
     *
     * @var string
     */
    protected $usage = 'optimize';
    /**
     * @return int
     */
    public function run(array $params)
    {
        try {
            $this->enable_caching();
            $this->clear_cache();
            $this->remove_dev_packages();
            return EXIT_SUCCESS;
        } catch (RuntimeException) {
            CLI::error('The "spark optimize" failed.');
            return EXIT_ERROR;
        }
    }
    private function clear_cache(): void
    {
        $locator = new File_Locator_Cached(new File_Locator(service('autoloader')));
        $locator->delete_cache();
        CLI::write('Removed FileLocatorCache.', 'green');
        $cache = WRITEPATH . 'cache/FactoriesCache_config';
        $this->remove_file($cache);
    }
    private function remove_file(string $cache): void
    {
        if (is_file($cache)) {
            $result = unlink($cache);
            if ($result) {
                CLI::write('Removed "' . clean_path($cache) . '".', 'green');
                return;
            }
            CLI::error('Error in removing file: ' . clean_path($cache));
            throw new RuntimeException(__METHOD__);
        }
    }
    private function enable_caching(): void
    {
        $publisher = new Publisher(APPPATH, APPPATH);
        $config = APPPATH . 'Config/Optimize.php';
        $result = $publisher->replace($config, ['public bool $configCacheEnabled = false;' => 'public bool $configCacheEnabled = true;', 'public bool $locatorCacheEnabled = false;' => 'public bool $locatorCacheEnabled = true;']);
        if ($result) {
            CLI::write('Config Caching and FileLocator Caching are enabled in "app/Config/Optimize.php".', 'green');
            return;
        }
        CLI::error('Error in updating file: ' . clean_path($config));
        throw new RuntimeException(__METHOD__);
    }
    private function remove_dev_packages(): void
    {
        if (!defined('VENDORPATH')) {
            return;
        }
        chdir(ROOTPATH);
        passthru('composer install --no-dev', $status);
        if ($status === 0) {
            CLI::write('Removed Composer dev packages.', 'green');
            return;
        }
        CLI::error('Error in removing Composer dev packages.');
        throw new RuntimeException(__METHOD__);
    }
}