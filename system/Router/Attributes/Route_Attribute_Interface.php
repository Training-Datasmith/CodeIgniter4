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
namespace Code_Igniter\Router\Attributes;

use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
interface Route_Attribute_Interface
{
    /**
     * Process the attribute before the controller is executed.
     *
     * @return RequestInterface|ResponseInterface|null
     *                                                 Return RequestInterface to replace the request
     *                                                 Return ResponseInterface to short-circuit and send response
     *                                                 Return null to continue normal execution
     */
    public function before(Request_Interface $request): Request_Interface|Response_Interface|null;
    /**
     * Process the attribute after the controller is executed.
     *
     * @return ResponseInterface|null
     *                                Return ResponseInterface to replace the response
     *                                Return null to use the existing response
     */
    public function after(Request_Interface $request, Response_Interface $response): ?Response_Interface;
}