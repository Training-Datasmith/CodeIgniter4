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
namespace Code_Igniter\CLI;

use Code_Igniter\Autoloader\File_Locator_Interface;
use Code_Igniter\Events\Events;
use Code_Igniter\Log\Logger;
use ReflectionClass;
use Reflection_Exception;
/**
 * Core functionality for running, listing, etc commands.
 *
 * @phpstan-type commands_list array<string, array{'class': class-string<BaseCommand>, 'file': string, 'group': string,'description': string}>
 */
class Commands
{
    /**
     * The found commands.
     *
     * @var commands_list
     */
    protected $commands = [];
    /**
     * Logger instance.
     *
     * @var Logger
     */
    protected $logger;
    /**
     * Constructor
     *
     * @param Logger|null $logger
     */
    public function __construct($logger = null)
    {
        $this->logger = $logger ?? service('logger');
        $this->discover_commands();
    }
    /**
     * Runs a command given
     *
     * @param array<int|string, string|null> $params
     *
     * @return int Exit code
     */
    public function run(string $command, array $params)
    {
        if (!$this->verify_command($command, $this->commands)) {
            return EXIT_ERROR;
        }
        // The file would have already been loaded during the
        // createCommandList function...
        $class_name = $this->commands[$command]['class'];
        $class = new $class_name($this->logger, $this);
        Events::trigger('pre_command');
        $exit = $class->run($params);
        Events::trigger('post_command');
        return $exit;
    }
    /**
     * Provide access to the list of commands.
     *
     * @return commands_list
     */
    public function get_commands()
    {
        return $this->commands;
    }
    /**
     * Discovers all commands in the framework and within user code,
     * and collects instances of them to work with.
     *
     * @return void
     */
    public function discover_commands()
    {
        if ($this->commands !== []) {
            return;
        }
        /** @var FileLocatorInterface */
        $locator = service('locator');
        $files = $locator->list_files('Commands/');
        // If no matching command files were found, bail
        // This should never happen in unit testing.
        if ($files === []) {
            return;
            // @codeCoverageIgnore
        }
        // Loop over each file checking to see if a command with that
        // alias exists in the class.
        foreach ($files as $file) {
            /** @var class-string<BaseCommand>|false */
            $class_name = $locator->find_qualified_name_from_path($file);
            if ($class_name === false || !class_exists($class_name)) {
                continue;
            }
            try {
                $class = new ReflectionClass($class_name);
                if (!$class->is_instantiable() || !$class->is_subclass_of(Base_Command::class)) {
                    continue;
                }
                $class = new $class_name($this->logger, $this);
                if ($class->group !== null && !isset($this->commands[$class->name])) {
                    $this->commands[$class->name] = ['class' => $class_name, 'file' => $file, 'group' => $class->group, 'description' => $class->description];
                }
                unset($class);
            } catch (Reflection_Exception $e) {
                $this->logger->error($e->get_message());
            }
        }
        asort($this->commands);
    }
    /**
     * Verifies if the command being sought is found
     * in the commands list.
     *
     * @param commands_list $commands
     */
    public function verify_command(string $command, array $commands): bool
    {
        if (isset($commands[$command])) {
            return true;
        }
        $message = lang('CLI.commandNotFound', [$command]);
        $alternatives = $this->get_command_alternatives($command, $commands);
        if ($alternatives !== []) {
            if (count($alternatives) === 1) {
                $message .= "\n\n" . lang('CLI.altCommandSingular') . "\n    ";
            } else {
                $message .= "\n\n" . lang('CLI.altCommandPlural') . "\n    ";
            }
            $message .= implode("\n    ", $alternatives);
        }
        CLI::error($message);
        CLI::new_line();
        return false;
    }
    /**
     * Finds alternative of `$name` among collection
     * of commands.
     *
     * @param commands_list $collection
     *
     * @return list<string>
     */
    protected function get_command_alternatives(string $name, array $collection): array
    {
        /** @var array<string, int> */
        $alternatives = [];
        /** @var string $commandName */
        foreach (array_keys($collection) as $command_name) {
            $lev = levenshtein($name, $command_name);
            if ($lev <= strlen($command_name) / 3 || str_contains($command_name, $name)) {
                $alternatives[$command_name] = $lev;
            }
        }
        ksort($alternatives, SORT_NATURAL | SORT_FLAG_CASE);
        return array_keys($alternatives);
    }
}