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

use Code_Igniter\Cookie\Cookie_Store;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
/**
 * Handle a redirect response
 *
 * @see \CodeIgniter\HTTP\RedirectResponseTest
 */
class Redirect_Response extends Response
{
    /**
     * Sets the URI to redirect to and, optionally, the HTTP status code to use.
     * If no code is provided it will be automatically determined.
     *
     * @param string   $uri  The URI path (relative to baseURL) to redirect to
     * @param int|null $code HTTP status code
     *
     * @return $this
     */
    public function to(string $uri, ?int $code = null, string $method = 'auto')
    {
        // If it appears to be a relative URL, then convert to full URL
        // for better security.
        if (!str_starts_with($uri, 'http')) {
            $uri = site_url($uri);
        }
        return $this->redirect($uri, $method, $code);
    }
    /**
     * Sets the URI to redirect to but as a reverse-routed or named route
     * instead of a raw URI.
     *
     * @param string $route Route name or Controller::method
     *
     * @return $this
     *
     * @throws HTTPException
     */
    public function route(string $route, array $params = [], ?int $code = null, string $method = 'auto')
    {
        $named_route = $route;
        $route = service('routes')->reverse_route($route, ...$params);
        if (!$route) {
            throw Http_Exception::for_invalid_redirect_route($named_route);
        }
        return $this->redirect(site_url($route), $method, $code);
    }
    /**
     * Helper function to return to previous page.
     *
     * Example:
     *  return redirect()->back();
     *
     * @return $this
     */
    public function back(?int $code = null, string $method = 'auto')
    {
        service('session');
        return $this->redirect(previous_url(), $method, $code);
    }
    /**
     * Sets the current $_GET and $_POST arrays in the session.
     * This also saves the validation errors.
     *
     * It will then be available via the 'old()' helper function.
     *
     * @return $this
     */
    public function with_input()
    {
        $session = service('session');
        $session->set_flashdata('_ci_old_input', ['get' => service('superglobals')->get_get_array(), 'post' => service('superglobals')->get_post_array()]);
        $this->with_errors();
        return $this;
    }
    /**
     * Sets validation errors in the session.
     *
     * If the validation has any errors, transmit those back
     * so they can be displayed when the validation is handled
     * within a method different than displaying the form.
     *
     * @return $this
     */
    private function with_errors(): self
    {
        $validation = service('validation');
        if ($validation->get_errors() !== []) {
            service('session')->set_flashdata('_ci_validation_errors', $validation->get_errors());
        }
        return $this;
    }
    /**
     * Adds a key and message to the session as Flashdata.
     *
     * @param array|string $message
     *
     * @return $this
     */
    public function with(string $key, $message)
    {
        service('session')->set_flashdata($key, $message);
        return $this;
    }
    /**
     * Copies any cookies from the global Response instance
     * into this RedirectResponse. Useful when you've just
     * set a cookie but need ensure that's actually sent
     * with the response instead of lost.
     *
     * @return $this|RedirectResponse
     */
    public function with_cookies()
    {
        $this->cookie_store = new Cookie_Store(service('response')->get_cookies());
        return $this;
    }
    /**
     * Copies any headers from the global Response instance
     * into this RedirectResponse. Useful when you've just
     * set a header be need to ensure its actually sent
     * with the redirect response.
     *
     * @return $this|RedirectResponse
     */
    public function with_headers()
    {
        foreach (service('response')->headers() as $name => $value) {
            if ($value instanceof Header) {
                $this->set_header($name, $value->get_value());
            } else {
                foreach ($value as $header) {
                    $this->add_header($name, $header->get_value());
                }
            }
        }
        return $this;
    }
}