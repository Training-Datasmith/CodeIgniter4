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
namespace Code_Igniter\Commands\Generators;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
use Code_Igniter\CLI\Generator_Trait;
use Config\Database;
use Config\Migrations;
use Config\Session as SessionConfig;
/**
 * Generates a skeleton migration file.
 */
class Migration_Generator extends Base_Command
{
    use Generator_Trait;
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Generators';
    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'make:migration';
    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new migration file.';
    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:migration <name> [options]';
    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['name' => 'The migration class name.'];
    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['--session' => 'Generates the migration file for database sessions.', '--table' => 'Table name to use for database sessions. Default: "ci_sessions".', '--dbgroup' => 'Database group to use for database sessions. Default: "default".', '--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".', '--suffix' => 'Append the component title to the class name (e.g. User => UserMigration).'];
    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        $this->component = 'Migration';
        $this->directory = 'Database\Migrations';
        $this->template = 'migration.tpl.php';
        if (array_key_exists('session', $params) || CLI::get_option('session')) {
            $table = $params['table'] ?? CLI::get_option('table') ?? 'ci_sessions';
            $params[0] = "_create_{$table}_table";
        }
        $this->class_name_lang = 'CLI.generator.className.migration';
        $this->generate_class($params);
    }
    /**
     * Prepare options and do the necessary replacements.
     */
    protected function prepare(string $class): string
    {
        $data = [];
        $data['session'] = false;
        if ($this->get_option('session')) {
            $table = $this->get_option('table');
            $db_group = $this->get_option('dbgroup');
            $data['session'] = true;
            $data['table'] = is_string($table) ? $table : 'ci_sessions';
            $data['DBGroup'] = is_string($db_group) ? $db_group : 'default';
            $data['DBDriver'] = config(Database::class)->{$data['DBGroup']}['DBDriver'];
            $data['matchIP'] = config(Session_Config::class)->match_ip;
        }
        return $this->parse_template($class, [], [], $data);
    }
    /**
     * Change file basename before saving.
     */
    protected function basename(string $filename): string
    {
        return gmdate(config(Migrations::class)->timestamp_format) . basename($filename);
    }
}