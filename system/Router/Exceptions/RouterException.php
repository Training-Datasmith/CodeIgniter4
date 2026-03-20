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
namespace Code_Igniter\Router\Exceptions;

use Code_Igniter\Exceptions\Framework_Exception;
/**
 * RouterException
 */
class Router_Exception extends Framework_Exception implements Exception_Interface
{
    /**
     * Thrown when the actual parameter type does not match
     * the expected types.
     *
     * @return RouterException
     */
    public static function for_invalid_parameter_type()
    {
        return new static(lang('Router.invalidParameter'));
    }
    /**
     * Thrown when a default route is not set.
     *
     * @return RouterException
     */
    public static function for_missing_default_route()
    {
        return new static(lang('Router.missingDefaultRoute'));
    }
    /**
     * Throw when controller or its method is not found.
     *
     * @return RouterException
     */
    public static function for_controller_not_found(string $controller, string $method)
    {
        return new static(lang('HTTP.controllerNotFound', [$controller, $method]));
    }
    /**
     * Throw when route is not valid.
     *
     * @return RouterException
     */
    public static function for_invalid_route(string $route)
    {
        return new static(lang('HTTP.invalidRoute', [$route]));
    }
    /**
     * Throw when dynamic controller.
     *
     * @return RouterException
     */
    public static function for_dynamic_controller(string $handler)
    {
        return new static(lang('Router.invalidDynamicController', [$handler]));
    }
    /**
     * Throw when controller name has `/`.
     *
     * @return RouterException
     */
    public static function for_invalid_controller_name(string $handler)
    {
        return new static(lang('Router.invalidControllerName', [$handler]));
    }
}