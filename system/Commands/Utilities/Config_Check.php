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

use Code_Igniter\Cache\Factories_Cache;
use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\Config\Base_Config;
use Config\Optimize;
use Kint\Kint;
/**
 * Check the Config values.
 *
 * @see \CodeIgniter\Commands\Utilities\ConfigCheckTest
 */
final class Config_Check extends Base_Command
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
    protected $name = 'config:check';
    /**
     * The Command's short description
     *
     * @var string
     */
    protected $description = 'Check your Config values.';
    /**
     * The Command's usage
     *
     * @var string
     */
    protected $usage = 'config:check <classname>';
    /**
     * The Command's arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['classname' => 'The config classname to check. Short classname or FQCN.'];
    /**
     * The Command's options
     *
     * @var array<string, string>
     */
    protected $options = [];
    /**
     * @return int
     */
    public function run(array $params)
    {
        if (!isset($params[0])) {
            CLI::error('You must specify a Config classname.');
            CLI::write('  Usage: ' . $this->usage);
            CLI::write('Example: config:check App');
            CLI::write('         config:check \'CodeIgniter\Shield\Config\Auth\'');
            return EXIT_ERROR;
        }
        /** @var class-string<BaseConfig> $class */
        $class = $params[0];
        // Load Config cache if it is enabled.
        $config_cache_enabled = class_exists(Optimize::class) && (new Optimize())->config_cache_enabled;
        if ($config_cache_enabled) {
            $factories_cache = new Factories_Cache();
            $factories_cache->load('config');
        }
        $config = config($class);
        if ($config === null) {
            CLI::error('No such Config class: ' . $class);
            return EXIT_ERROR;
        }
        if (defined('KINT_DIR') && Kint::$enabled_mode !== false) {
            CLI::write($this->get_kint_d($config));
        } else {
            CLI::write(CLI::color($this->get_var_dump($config), 'cyan'));
        }
        CLI::new_line();
        $state = CLI::color($config_cache_enabled ? 'Enabled' : 'Disabled', 'green');
        CLI::write('Config Caching: ' . $state);
        return EXIT_SUCCESS;
    }
    /**
     * Gets object dump by Kint d()
     */
    private function get_kint_d(object $config): string
    {
        ob_start();
        d($config);
        $output = ob_get_clean();
        $output = trim($output);
        $lines = explode("\n", $output);
        array_splice($lines, 0, 3);
        array_splice($lines, -3);
        return implode("\n", $lines);
    }
    /**
     * Gets object dump by var_dump()
     */
    private function get_var_dump(object $config): string
    {
        ob_start();
        var_dump($config);
        $output = ob_get_clean();
        return preg_replace('!.*system/Commands/Utilities/ConfigCheck.php.*\n!u', '', $output);
    }
}