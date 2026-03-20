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
 * Add Common Security Headers
 *
 * @see \CodeIgniter\Filters\SecureHeadersTest
 */
class Secure_Headers implements Filter_Interface
{
    /**
     * @var array<string, string>
     */
    protected $headers = [
        // https://owasp.org/www-project-secure-headers/#x-frame-options
        'X-Frame-Options' => 'SAMEORIGIN',
        // https://owasp.org/www-project-secure-headers/#x-content-type-options
        'X-Content-Type-Options' => 'nosniff',
        // https://docs.microsoft.com/en-us/previous-versions/windows/internet-explorer/ie-developer/compatibility/jj542450(v=vs.85)#the-noopen-directive
        'X-Download-Options' => 'noopen',
        // https://owasp.org/www-project-secure-headers/#x-permitted-cross-domain-policies
        'X-Permitted-Cross-Domain-Policies' => 'none',
        // https://owasp.org/www-project-secure-headers/#referrer-policy
        'Referrer-Policy' => 'same-origin',
    ];
    /**
     * We don't have anything to do here.
     *
     * @param list<string>|null $arguments
     */
    public function before(Request_Interface $request, $arguments = null)
    {
        return null;
    }
    /**
     * Add security headers.
     *
     * @param list<string>|null $arguments
     */
    public function after(Request_Interface $request, Response_Interface $response, $arguments = null)
    {
        foreach ($this->headers as $header => $value) {
            $response->set_header($header, $value);
        }
        return $response;
    }
}