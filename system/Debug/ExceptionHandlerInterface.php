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
namespace Code_Igniter\Debug;

use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Throwable;
interface Exception_Handler_Interface
{
    /**
     * Determines the correct way to display the error.
     *
     * @param CLIRequest|IncomingRequest $request
     */
    public function handle(Throwable $exception, Request_Interface $request, Response_Interface $response, int $status_code, int $exit_code): void;
}