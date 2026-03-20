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
namespace Code_Igniter\Honeypot;

use Code_Igniter\Honeypot\Exceptions\Honeypot_Exception;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Config\Honeypot as HoneypotConfig;
/**
 * class Honeypot
 *
 * @see \CodeIgniter\Honeypot\HoneypotTest
 */
class Honeypot
{
    /**
     * Our configuration.
     *
     * @var HoneypotConfig
     */
    protected $config;
    /**
     * Constructor.
     *
     * @throws HoneypotException
     */
    public function __construct(Honeypot_Config $config)
    {
        $this->config = $config;
        if ($this->config->container === '' || !str_contains($this->config->container, '{template}')) {
            $this->config->container = '<div style="display:none">{template}</div>';
        }
        $this->config->container_id ??= 'hpc';
        if ($this->config->template === '') {
            throw Honeypot_Exception::for_no_template();
        }
        if ($this->config->name === '') {
            throw Honeypot_Exception::for_no_name_field();
        }
    }
    /**
     * Checks the request if honeypot field has data.
     *
     * @return bool
     */
    public function has_content(Request_Interface $request)
    {
        assert($request instanceof Incoming_Request);
        return !empty($request->get_post($this->config->name));
    }
    /**
     * Attaches Honeypot template to response.
     *
     * @return void
     */
    public function attach_honeypot(Response_Interface $response)
    {
        if ($response->get_body() === null) {
            return;
        }
        if ($response->get_csp()->enabled()) {
            // Add id attribute to the container tag.
            $this->config->container = str_ireplace('>{template}', ' id="' . $this->config->container_id . '">{template}', $this->config->container);
        }
        $prep_field = $this->prepare_template($this->config->template);
        $body_before = $response->get_body();
        $body_after = str_ireplace('</form>', $prep_field . '</form>', $body_before);
        if ($response->get_csp()->enabled() && $body_before !== $body_after) {
            // Add style tag for the container tag in the head tag.
            $style = '<style ' . csp_style_nonce() . '>#' . $this->config->container_id . ' { display:none }</style>';
            $body_after = str_ireplace('</head>', $style . '</head>', $body_after);
        }
        $response->set_body($body_after);
    }
    /**
     * Prepares the template by adding label
     * content and field name.
     */
    protected function prepare_template(string $template): string
    {
        $template = str_ireplace('{label}', $this->config->label, $template);
        $template = str_ireplace('{name}', $this->config->name, $template);
        if ($this->config->hidden) {
            $template = str_ireplace('{template}', $template, $this->config->container);
        }
        return $template;
    }
}