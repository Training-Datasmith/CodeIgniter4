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
namespace Code_Igniter;

use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Code_Igniter\HTTP\Exceptions\Redirect_Exception;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\Validation\Exceptions\Validation_Exception;
use Code_Igniter\Validation\Validation_Interface;
use Config\Validation;
use Psr\Log\Logger_Interface;
/**
 * @see \CodeIgniter\ControllerTest
 */
class Controller
{
    /**
     * Helpers that will be automatically loaded on class instantiation.
     *
     * @var list<string>
     */
    protected $helpers = [];
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;
    /**
     * Instance of the main response object.
     *
     * @var ResponseInterface
     */
    protected $response;
    /**
     * Instance of logger to use.
     *
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * Should enforce HTTPS access for all methods in this controller.
     *
     * @var int Number of seconds to set HSTS header
     */
    protected $force_https = 0;
    /**
     * Once validation has been run, will hold the Validation instance.
     *
     * @var ValidationInterface|null
     */
    protected $validator;
    /**
     * Constructor.
     *
     * @return void
     *
     * @throws HTTPException|RedirectException
     */
    public function init_controller(Request_Interface $request, Response_Interface $response, Logger_Interface $logger)
    {
        $this->request = $request;
        $this->response = $response;
        $this->logger = $logger;
        if ($this->force_https > 0) {
            $this->force_https($this->force_https);
        }
        // Autoload helper files.
        helper($this->helpers);
    }
    /**
     * A convenience method to use when you need to ensure that a single
     * method is reached only via HTTPS. If it isn't, then a redirect
     * will happen back to this method and HSTS header will be sent
     * to have modern browsers transform requests automatically.
     *
     * @param int $duration The number of seconds this link should be
     *                      considered secure for. Only with HSTS header.
     *                      Default value is 1 year.
     *
     * @return void
     *
     * @throws HTTPException|RedirectException
     */
    protected function force_https(int $duration = 31536000)
    {
        force_https($duration, $this->request, $this->response);
    }
    /**
     * How long to cache the current page for.
     *
     * @params int $time time to live in seconds.
     *
     * @return void
     */
    protected function cache_page(int $time)
    {
        service('responsecache')->set_ttl($time);
    }
    /**
     * A shortcut to performing validation on Request data.
     *
     * @param array|string $rules
     * @param array        $messages An array of custom error messages
     */
    protected function validate($rules, array $messages = []): bool
    {
        $this->set_validator($rules, $messages);
        return $this->validator->with_request($this->request)->run();
    }
    /**
     * A shortcut to performing validation on any input data.
     *
     * @param array        $data     The data to validate
     * @param array|string $rules
     * @param array        $messages An array of custom error messages
     * @param string|null  $dbGroup  The database group to use
     */
    protected function validate_data(array $data, $rules, array $messages = [], ?string $db_group = null): bool
    {
        $this->set_validator($rules, $messages);
        return $this->validator->run($data, null, $db_group);
    }
    /**
     * @param array|string $rules
     */
    private function set_validator($rules, array $messages): void
    {
        $this->validator = service('validation');
        // If you replace the $rules array with the name of the group
        if (is_string($rules)) {
            $validation = config(Validation::class);
            // If the rule wasn't found in the \Config\Validation, we
            // should throw an exception so the developer can find it.
            if (!isset($validation->{$rules})) {
                throw Validation_Exception::for_rule_not_found($rules);
            }
            // If no error message is defined, use the error message in the Config\Validation file
            if ($messages === []) {
                $error_name = $rules . '_errors';
                $messages = $validation->{$error_name} ?? [];
            }
            $rules = $validation->{$rules};
        }
        $this->validator->set_rules($rules, $messages);
    }
}