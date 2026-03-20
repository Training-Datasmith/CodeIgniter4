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
use Code_Igniter\Database\Seeder;
use Config\Database;
use Throwable;
/**
 * Runs the specified Seeder file to populate the database
 * with some data.
 */
class Seed extends Base_Command
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
    protected $name = 'db:seed';
    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Runs the specified seeder to populate known data into the database.';
    /**
     * the Command's usage
     *
     * @var string
     */
    protected $usage = 'db:seed <seeder_name>';
    /**
     * the Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['seeder_name' => 'The seeder name to run'];
    /**
     * Passes to Seeder to populate the database.
     */
    public function run(array $params)
    {
        $seeder = new Seeder(new Database());
        $seed_name = array_shift($params);
        if (empty($seed_name)) {
            $seed_name = CLI::prompt(lang('Migrations.migSeeder'), null, 'required');
            // @codeCoverageIgnore
        }
        try {
            $seeder->call($seed_name);
        } catch (Throwable $e) {
            $this->show_error($e);
        }
    }
}