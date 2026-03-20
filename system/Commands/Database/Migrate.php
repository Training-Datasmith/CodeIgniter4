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
namespace Code_Igniter\Commands\Database;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\CLI\Signal_Trait;
use Throwable;
/**
 * Runs all new migrations.
 */
class Migrate extends Base_Command
{
    use Signal_Trait;
    /**
     * The group the command is lumped under
     * when listing commands.
     *
     * @var string
     */
    protected $group = 'Database';
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'migrate';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Locates and runs all new migrations against the database.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'migrate [options]';
    /**
     * the Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['-n' => 'Set migration namespace', '-g' => 'Set database group', '--all' => 'Set for all namespaces, will ignore (-n) option'];
    /**
     * Ensures that all migrations have been run.
     */
    public function run(array $params)
    {
        $runner = service('migrations');
        $runner->clear_cli_messages();
        CLI::write(lang('Migrations.latest'), 'yellow');
        $namespace = $params['n'] ?? CLI::get_option('n');
        $group = $params['g'] ?? CLI::get_option('g');
        try {
            if (array_key_exists('all', $params) || CLI::get_option('all')) {
                $runner->set_namespace(null);
            } elseif ($namespace) {
                $runner->set_namespace($namespace);
            }
            $this->with_signals_blocked(static function () use ($runner, $group): void {
                if (!$runner->latest($group)) {
                    CLI::error(lang('Migrations.generalFault'), 'light_gray', 'red');
                    // @codeCoverageIgnore
                }
            });
            $messages = $runner->get_cli_messages();
            foreach ($messages as $message) {
                CLI::write($message);
            }
            CLI::write(lang('Migrations.migrated'), 'green');
            // @codeCoverageIgnoreStart
        } catch (Throwable $e) {
            $this->show_error($e);
            // @codeCoverageIgnoreEnd
        }
    }
}