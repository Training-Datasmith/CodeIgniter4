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
namespace Code_Igniter\Res_Tful;

use Code_Igniter\Controller;
use Code_Igniter\HTTP\Cli_Request;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Psr\Log\Logger_Interface;
abstract class Base_Resource extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;
    /**
     * @var string|null The model that holding this resource's data
     */
    protected $model_name;
    /**
     * @var object|null The model that holding this resource's data
     */
    protected $model;
    /**
     * Constructor.
     *
     * @return void
     */
    public function init_controller(Request_Interface $request, Response_Interface $response, Logger_Interface $logger)
    {
        parent::init_controller($request, $response, $logger);
        $this->set_model($this->model_name);
    }
    /**
     * Set or change the model this controller is bound to.
     * Given either the name or the object, determine the other.
     *
     * @param object|string|null $which
     *
     * @return void
     */
    public function set_model($which = null)
    {
        if ($which !== null) {
            $this->model = is_object($which) ? $which : null;
            $this->model_name = is_object($which) ? null : $which;
        }
        if (empty($this->model) && !empty($this->model_name) && class_exists($this->model_name)) {
            $this->model = model($this->model_name);
        }
        if (!empty($this->model) && empty($this->model_name)) {
            $this->model_name = $this->model::class;
        }
    }
}