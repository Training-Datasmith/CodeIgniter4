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
namespace Code_Igniter\HTTP;

/**
 * Representation of an incoming, server-side HTTP request.
 *
 * Corresponds to Psr7\ServerRequestInterface.
 */
interface Request_Interface extends Outgoing_Request_Interface
{
    /**
     * Gets the user's IP address.
     * Supplied by RequestTrait.
     *
     * @return string IP address
     */
    public function get_ip_address(): string;
    /**
     * Fetch an item from the $_SERVER array.
     * Supplied by RequestTrait.
     *
     * @param array|string|null $index  Index for item to be fetched from $_SERVER
     * @param int|null          $filter A filter name to be applied
     *
     * @return mixed
     */
    public function get_server($index = null, $filter = null);
}