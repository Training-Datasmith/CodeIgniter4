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
namespace Code_Igniter\Commands\Housekeeping;

use Code_Igniter\CLI\Base_Command;
use Code_Igniter\CLI\CLI;
/**
 * ClearLogs command.
 */
class Clear_Logs extends Base_Command
{
    /**
     * The group the command is lumped under
     * when listing commands.
     *
     * @var string
     */
    protected $group = 'Housekeeping';
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'logs:clear';
    /**
     * The Command's short description
     *
     * @var string
     */
    protected $description = 'Clears all log files.';
    /**
     * The Command's usage
     *
     * @var string
     */
    protected $usage = 'logs:clear [option';
    /**
     * The Command's options
     *
     * @var array<string, string>
     */
    protected $options = ['--force' => 'Force delete of all logs files without prompting.'];
    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        $force = array_key_exists('force', $params) || CLI::get_option('force');
        if (!$force && CLI::prompt('Are you sure you want to delete the logs?', ['n', 'y']) === 'n') {
            // @codeCoverageIgnoreStart
            CLI::error('Deleting logs aborted.', 'light_gray', 'red');
            CLI::error('If you want, use the "-force" option to force delete all log files.', 'light_gray', 'red');
            CLI::new_line();
            return;
            // @codeCoverageIgnoreEnd
        }
        helper('filesystem');
        if (!delete_files(WRITEPATH . 'logs', false, true)) {
            // @codeCoverageIgnoreStart
            CLI::error('Error in deleting the logs files.', 'light_gray', 'red');
            CLI::new_line();
            return;
            // @codeCoverageIgnoreEnd
        }
        CLI::write('Logs cleared.', 'green');
        CLI::new_line();
    }
}