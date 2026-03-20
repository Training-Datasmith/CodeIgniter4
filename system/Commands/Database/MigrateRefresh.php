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
/**
 * Does a rollback followed by a latest to refresh the current state
 * of the database.
 */
class Migrate_Refresh extends Base_Command
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
    protected $name = 'migrate:refresh';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Does a rollback followed by a latest to refresh the current state of the database.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'migrate:refresh [options]';
    /**
     * the Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['-n' => 'Set migration namespace', '-g' => 'Set database group', '--all' => 'Set latest for all namespace, will ignore (-n) option', '-f' => 'Force command - this option allows you to bypass the confirmation question when running this command in a production environment'];
    /**
     * Does a rollback followed by a latest to refresh the current state
     * of the database.
     */
    public function run(array $params)
    {
        $params['b'] = 0;
        if (ENVIRONMENT === 'production') {
            // @codeCoverageIgnoreStart
            $force = array_key_exists('f', $params) || CLI::get_option('f');
            if (!$force && CLI::prompt(lang('Migrations.refreshConfirm'), ['y', 'n']) === 'n') {
                return;
            }
            $params['f'] = null;
            // @codeCoverageIgnoreEnd
        }
        $this->with_signals_blocked(function () use ($params): void {
            $this->call('migrate:rollback', $params);
            $this->call('migrate', $params);
        });
    }
}