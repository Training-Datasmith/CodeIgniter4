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
namespace Code_Igniter\HTTP\Exceptions;

use Code_Igniter\Exceptions\Http_Exception_Interface;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Exceptions\LogicException;
use Code_Igniter\Exceptions\RuntimeException;
use Code_Igniter\HTTP\Responsable_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Throwable;
/**
 * RedirectException
 */
class Redirect_Exception extends RuntimeException implements Exception_Interface, Responsable_Interface, Http_Exception_Interface
{
    /**
     * HTTP status code for redirects
     *
     * @var int
     */
    protected $code = 302;
    protected ?Response_Interface $response = null;
    /**
     * @param ResponseInterface|string $message Response object or a string containing a relative URI.
     * @param int                      $code    HTTP status code to redirect if $message is a string.
     */
    public function __construct($message = '', int $code = 0, ?Throwable $previous = null)
    {
        if (!is_string($message) && !$message instanceof Response_Interface) {
            throw new InvalidArgumentException('RedirectException::__construct() first argument must be a string or ResponseInterface', 0, $this);
        }
        if ($message instanceof Response_Interface) {
            $this->response = $message;
            $message = '';
            if ($this->response->get_header_line('Location') === '' && $this->response->get_header_line('Refresh') === '') {
                throw new LogicException('The Response object passed to RedirectException does not contain a redirect address.');
            }
            if ($this->response->get_status_code() < 301 || $this->response->get_status_code() > 308) {
                $this->response->set_status_code($this->code);
            }
        }
        parent::__construct($message, $code, $previous);
    }
    public function get_response(): Response_Interface
    {
        if (!$this->response instanceof Response_Interface) {
            $this->response = service('response')->redirect(base_url($this->get_message()), 'auto', $this->get_code());
        }
        $location = $this->response->get_header_line('Location');
        service('logger')->info(sprintf('REDIRECTED ROUTE at %s', $location !== '' ? $location : substr($this->response->get_header_line('Refresh'), 6)));
        return $this->response;
    }
}