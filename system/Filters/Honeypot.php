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

use Code_Igniter\Honeypot\Exceptions\Honeypot_Exception;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
/**
 * Honeypot filter
 *
 * @see \CodeIgniter\Filters\HoneypotTest
 */
class Honeypot implements Filter_Interface
{
    /**
     * Checks if Honeypot field is empty, if not then the
     * requester is a bot
     *
     * @param list<string>|null $arguments
     *
     * @throws HoneypotException
     */
    public function before(Request_Interface $request, $arguments = null)
    {
        if (!$request instanceof Incoming_Request) {
            return null;
        }
        if (service('honeypot')->has_content($request)) {
            throw Honeypot_Exception::is_bot();
        }
        return null;
    }
    /**
     * Attach a honeypot to the current response.
     *
     * @param list<string>|null $arguments
     */
    public function after(Request_Interface $request, Response_Interface $response, $arguments = null)
    {
        service('honeypot')->attach_honeypot($response);
        return null;
    }
}