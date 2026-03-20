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
use Code_Igniter\Database\Migration_Runner;
use Throwable;
/**
 * Runs all of the migrations in reverse order, until they have
 * all been unapplied.
 */
class Migrate_Rollback extends Base_Command
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
    protected $name = 'migrate:rollback';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Runs the "down" method for all migrations in the last batch.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'migrate:rollback [options]';
    /**
     * the Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['-b' => 'Specify a batch to roll back to; e.g. "3" to return to batch #3', '-f' => 'Force command - this option allows you to bypass the confirmation question when running this command in a production environment'];
    /**
     * Runs all of the migrations in reverse order, until they have
     * all been unapplied.
     */
    public function run(array $params)
    {
        if (ENVIRONMENT === 'production') {
            // @codeCoverageIgnoreStart
            $force = array_key_exists('f', $params) || CLI::get_option('f');
            if (!$force && CLI::prompt(lang('Migrations.rollBackConfirm'), ['y', 'n']) === 'n') {
                return null;
            }
            // @codeCoverageIgnoreEnd
        }
        /** @var MigrationRunner $runner */
        $runner = service('migrations');
        try {
            $batch = $params['b'] ?? CLI::get_option('b') ?? $runner->get_last_batch() - 1;
            if (is_string($batch)) {
                if (!ctype_digit($batch)) {
                    CLI::error('Invalid batch number: ' . $batch, 'light_gray', 'red');
                    CLI::new_line();
                    return EXIT_ERROR;
                }
                $batch = (int) $batch;
            }
            CLI::write(lang('Migrations.rollingBack') . ' ' . $batch, 'yellow');
            $this->with_signals_blocked(static function () use ($runner, $batch): void {
                if (!$runner->regress($batch)) {
                    CLI::error(lang('Migrations.generalFault'), 'light_gray', 'red');
                    // @codeCoverageIgnore
                }
            });
            $messages = $runner->get_cli_messages();
            foreach ($messages as $message) {
                CLI::write($message);
            }
            CLI::write('Done rolling back migrations.', 'green');
            // @codeCoverageIgnoreStart
        } catch (Throwable $e) {
            $this->show_error($e);
            // @codeCoverageIgnoreEnd
        }
        return null;
    }
}