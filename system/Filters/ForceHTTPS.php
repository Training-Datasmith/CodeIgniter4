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

use Code_Igniter\HTTP\Exceptions\Redirect_Exception;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Config\App;
/**
 * Force HTTPS filter
 */
class Force_Https implements Filter_Interface
{
    /**
     * Force Secure Site Access? If the config value 'forceGlobalSecureRequests'
     * is true, will enforce that all requests to this site are made through
     * HTTPS. Will redirect the user to the current page with HTTPS, as well
     * as set the HTTP Strict Transport Security (HSTS) header for those browsers
     * that support it.
     *
     * @param array|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(Request_Interface $request, $arguments = null)
    {
        $config = config(App::class);
        if ($config->force_global_secure_requests !== true) {
            return null;
        }
        $response = service('response');
        try {
            force_https(YEAR, $request, $response);
        } catch (Redirect_Exception $e) {
            return $e->get_response();
        }
        return null;
    }
    /**
     * We don't have anything to do here.
     *
     * @param array|null $arguments
     */
    public function after(Request_Interface $request, Response_Interface $response, $arguments = null)
    {
        return null;
    }
}