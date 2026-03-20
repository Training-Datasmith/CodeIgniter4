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
/**
 * Displays a list of all migrations and whether they've been run or not.
 *
 * @see \CodeIgniter\Commands\Database\MigrateStatusTest
 */
class Migrate_Status extends Base_Command
{
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
    protected $name = 'migrate:status';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Displays a list of all migrations and whether they\'ve been run or not.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'migrate:status [options]';
    /**
     * the Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['-g' => 'Set database group'];
    /**
     * Namespaces to ignore when looking for migrations.
     *
     * @var list<string>
     */
    protected $ignored_namespaces = ['CodeIgniter', 'Config', 'Kint', 'Laminas\ZendFrameworkBridge', 'Laminas\Escaper', 'Psr\Log'];
    /**
     * Displays a list of all migrations and whether they've been run or not.
     *
     * @param array<string, mixed> $params
     */
    public function run(array $params)
    {
        $runner = service('migrations');
        $param_group = $params['g'] ?? CLI::get_option('g');
        // Get all namespaces
        $namespaces = service('autoloader')->get_namespace();
        // Collection of migration status
        $status = [];
        foreach (array_keys($namespaces) as $namespace) {
            if (ENVIRONMENT !== 'testing') {
                // Make Tests\\Support discoverable for testing
                $this->ignored_namespaces[] = 'Tests\Support';
                // @codeCoverageIgnore
            }
            if (in_array($namespace, $this->ignored_namespaces, true)) {
                continue;
            }
            if (APP_NAMESPACE !== 'App' && $namespace === 'App') {
                continue;
                // @codeCoverageIgnore
            }
            $migrations = $runner->find_namespace_migrations($namespace);
            if (empty($migrations)) {
                continue;
            }
            $runner->set_namespace($namespace);
            $history = $runner->get_history((string) $param_group);
            ksort($migrations);
            foreach ($migrations as $uid => $migration) {
                $migrations[$uid]->name = mb_substr($migration->name, (int) mb_strpos($migration->name, $uid . '_'));
                $date = '---';
                $group = '---';
                $batch = '---';
                foreach ($history as $row) {
                    // @codeCoverageIgnoreStart
                    if ($runner->get_object_uid($row) !== $migration->uid) {
                        continue;
                    }
                    $date = date('Y-m-d H:i:s', (int) $row->time);
                    $group = $row->group;
                    $batch = $row->batch;
                    // @codeCoverageIgnoreEnd
                }
                $status[] = [$namespace, $migration->version, $migration->name, $group, $date, $batch];
            }
        }
        if ($status === []) {
            // @codeCoverageIgnoreStart
            CLI::error(lang('Migrations.noneFound'), 'light_gray', 'red');
            CLI::new_line();
            return;
            // @codeCoverageIgnoreEnd
        }
        $headers = [CLI::color(lang('Migrations.namespace'), 'yellow'), CLI::color(lang('Migrations.version'), 'yellow'), CLI::color(lang('Migrations.filename'), 'yellow'), CLI::color(lang('Migrations.group'), 'yellow'), CLI::color(str_replace(': ', '', lang('Migrations.on')), 'yellow'), CLI::color(lang('Migrations.batch'), 'yellow')];
        CLI::table($status, $headers);
    }
}