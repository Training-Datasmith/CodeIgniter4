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
 * ClearDebugbar Command
 */
class Clear_Debugbar extends Base_Command
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
    protected $name = 'debugbar:clear';
    /**
     * The Command's usage
     *
     * @var string
     */
    protected $usage = 'debugbar:clear';
    /**
     * The Command's short description.
     *
     * @var string
     */
    protected $description = 'Clears all debugbar JSON files.';
    /**
     * Actually runs the command.
     */
    public function run(array $params)
    {
        helper('filesystem');
        if (!delete_files(WRITEPATH . 'debugbar', false, true)) {
            // @codeCoverageIgnoreStart
            CLI::error('Error deleting the debugbar JSON files.');
            CLI::new_line();
            return;
            // @codeCoverageIgnoreEnd
        }
        CLI::write('Debugbar cleared.', 'green');
        CLI::new_line();
    }
}