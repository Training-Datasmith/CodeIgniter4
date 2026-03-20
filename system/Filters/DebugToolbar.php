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
namespace Code_Igniter\Filters;

use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
/**
 * Debug toolbar filter
 *
 * @see \CodeIgniter\Filters\DebugToolbarTest
 */
class Debug_Toolbar implements Filter_Interface
{
    /**
     * We don't need to do anything here.
     *
     * @param list<string>|null $arguments
     */
    public function before(Request_Interface $request, $arguments = null)
    {
        return null;
    }
    /**
     * If the debug flag is set (CI_DEBUG) then collect performance
     * and debug information and display it in a toolbar.
     *
     * @param list<string>|null $arguments
     */
    public function after(Request_Interface $request, Response_Interface $response, $arguments = null)
    {
        service('toolbar')->prepare($request, $response);
        return null;
    }
}