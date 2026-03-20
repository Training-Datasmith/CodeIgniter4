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
use Code_Igniter\Controller;
use Code_Igniter\Res_Tful\Resource_Controller;
use Code_Igniter\Res_Tful\Resource_Presenter;
/**
 * Generates a skeleton controller file.
 */
class Controller_Generator extends Base_Command
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
    protected $name = 'make:controller';
    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new controller file.';
    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:controller <name> [options]';
    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = ['name' => 'The controller class name.'];
    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = ['--bare' => 'Extends from CodeIgniter\Controller instead of BaseController.', '--restful' => 'Extends from a RESTful resource, Options: [controller, presenter]. Default: "controller".', '--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".', '--suffix' => 'Append the component title to the class name (e.g. User => UserController).', '--force' => 'Force overwrite existing file.'];
    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        $this->component = 'Controller';
        $this->directory = 'Controllers';
        $this->template = 'controller.tpl.php';
        $this->class_name_lang = 'CLI.generator.className.controller';
        $this->generate_class($params);
    }
    /**
     * Prepare options and do the necessary replacements.
     */
    protected function prepare(string $class): string
    {
        $bare = $this->get_option('bare');
        $rest = $this->get_option('restful');
        $use_statement = trim(APP_NAMESPACE, '\\') . '\Controllers\BaseController';
        $extends = 'BaseController';
        // Gets the appropriate parent class to extend.
        if ($bare || $rest) {
            if ($bare) {
                $use_statement = Controller::class;
                $extends = 'Controller';
            } elseif ($rest) {
                $rest = is_string($rest) ? $rest : 'controller';
                if (!in_array($rest, ['controller', 'presenter'], true)) {
                    // @codeCoverageIgnoreStart
                    $rest = CLI::prompt(lang('CLI.generator.parentClass'), ['controller', 'presenter'], 'required');
                    CLI::new_line();
                    // @codeCoverageIgnoreEnd
                }
                if ($rest === 'controller') {
                    $use_statement = Resource_Controller::class;
                    $extends = 'ResourceController';
                } elseif ($rest === 'presenter') {
                    $use_statement = Resource_Presenter::class;
                    $extends = 'ResourcePresenter';
                }
            }
        }
        return $this->parse_template($class, ['{useStatement}', '{extends}'], [$use_statement, $extends], ['type' => $rest]);
    }
}