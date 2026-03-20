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

use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Redirect_Response;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\Security\Exceptions\Security_Exception;
use Code_Igniter\Security\Security;
/**
 * CSRF filter.
 *
 * This filter is not intended to be used from the command line.
 *
 * @codeCoverageIgnore
 * @see \CodeIgniter\Filters\CSRFTest
 */
class CSRF implements Filter_Interface
{
    /**
     * CSRF verification.
     *
     * @param list<string>|null $arguments
     *
     * @return RedirectResponse|null
     *
     * @throws SecurityException
     */
    public function before(Request_Interface $request, $arguments = null)
    {
        if (!$request instanceof Incoming_Request) {
            return null;
        }
        /** @var Security $security */
        $security = service('security');
        try {
            $security->verify($request);
        } catch (Security_Exception $e) {
            if ($security->should_redirect() && !$request->is_ajax()) {
                return redirect()->back()->with('error', $e->get_message());
            }
            throw $e;
        }
        return null;
    }
    /**
     * We don't have anything to do here.
     *
     * @param list<string>|null $arguments
     */
    public function after(Request_Interface $request, Response_Interface $response, $arguments = null)
    {
        return null;
    }
}