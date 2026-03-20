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
/**
 * Generates a complete set of scaffold files.
 */
class Scaffold_Generator extends Base_Command
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
    protected $name = 'make:scaffold';
    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a complete set of scaffold files.';
    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:scaffold <name> [options]';
    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['name' => 'The class name'];
    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['--bare' => 'Add the "--bare" option to controller component.', '--restful' => 'Add the "--restful" option to controller component.', '--table' => 'Add the "--table" option to the model component.', '--dbgroup' => 'Add the "--dbgroup" option to model component.', '--return' => 'Add the "--return" option to the model component.', '--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".', '--suffix' => 'Append the component title to the class name.', '--force' => 'Force overwrite existing file.'];
    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        $this->params = $params;
        $options = [];
        if ($this->get_option('namespace')) {
            $options['namespace'] = $this->get_option('namespace');
        }
        if ($this->get_option('suffix')) {
            $options['suffix'] = null;
        }
        if ($this->get_option('force')) {
            $options['force'] = null;
        }
        $controller_opts = [];
        if ($this->get_option('bare')) {
            $controller_opts['bare'] = null;
        } elseif ($this->get_option('restful')) {
            $controller_opts['restful'] = $this->get_option('restful');
        }
        $model_opts = ['table' => $this->get_option('table'), 'dbgroup' => $this->get_option('dbgroup'), 'return' => $this->get_option('return')];
        $class = $params[0] ?? CLI::get_segment(2);
        // Call those commands!
        $this->call('make:controller', array_merge([$class], $controller_opts, $options));
        $this->call('make:model', array_merge([$class], $model_opts, $options));
        $this->call('make:migration', array_merge([$class], $options));
        $this->call('make:seeder', array_merge([$class], $options));
    }
}