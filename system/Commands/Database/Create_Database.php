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
use Code_Igniter\Config\Factories;
use Code_Igniter\Database\Sq_Lite3\Connection;
use Config\Database;
use Throwable;
/**
 * Creates a new database.
 */
class Create_Database extends Base_Command
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
    protected $name = 'db:create';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Create a new database schema.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'db:create <db_name> [options]';
    /**
     * The Command's arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['db_name' => 'The database name to use'];
    /**
     * The Command's options
     *
     * @var array<string, string>
     */
    protected $options = ['--ext' => 'File extension of the database file for SQLite3. Can be `db` or `sqlite`. Defaults to `db`.'];
    /**
     * Creates a new database.
     */
    public function run(array $params)
    {
        $name = array_shift($params);
        if (empty($name)) {
            $name = CLI::prompt('Database name', null, 'required');
            // @codeCoverageIgnore
        }
        try {
            $config = config(Database::class);
            // Set to an empty database to prevent connection errors.
            $group = ENVIRONMENT === 'testing' ? 'tests' : $config->default_group;
            $config->{$group}['database'] = '';
            $db = Database::connect();
            // Special SQLite3 handling
            if ($db instanceof Connection) {
                $ext = $params['ext'] ?? CLI::get_option('ext') ?? 'db';
                if (!in_array($ext, ['db', 'sqlite'], true)) {
                    $ext = CLI::prompt('Please choose a valid file extension', ['db', 'sqlite']);
                    // @codeCoverageIgnore
                }
                if ($name !== ':memory:') {
                    $name = str_replace(['.db', '.sqlite'], '', $name) . ".{$ext}";
                }
                $config->{$group}['DBDriver'] = 'SQLite3';
                $config->{$group}['database'] = $name;
                if ($name !== ':memory:') {
                    $db_name = str_contains($name, DIRECTORY_SEPARATOR) ? $name : WRITEPATH . $name;
                    if (is_file($db_name)) {
                        CLI::error("Database \"{$db_name}\" already exists.", 'light_gray', 'red');
                        CLI::new_line();
                        return;
                    }
                    unset($db_name);
                }
                // Connect to new SQLite3 to create new database
                $db = Database::connect(null, false);
                $db->connect();
                if (!is_file($db->get_database()) && $name !== ':memory:') {
                    // @codeCoverageIgnoreStart
                    CLI::error('Database creation failed.', 'light_gray', 'red');
                    CLI::new_line();
                    return;
                    // @codeCoverageIgnoreEnd
                }
            } elseif (!Database::forge()->create_database($name)) {
                // @codeCoverageIgnoreStart
                CLI::error('Database creation failed.', 'light_gray', 'red');
                CLI::new_line();
                return;
                // @codeCoverageIgnoreEnd
            }
            CLI::write("Database \"{$name}\" successfully created.", 'green');
            CLI::new_line();
        } catch (Throwable $e) {
            $this->show_error($e);
        } finally {
            Factories::reset('config');
            Database::connect(null, false);
        }
    }
}