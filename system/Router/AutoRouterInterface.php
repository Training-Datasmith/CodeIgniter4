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
namespace Code_Igniter\Router;

/**
 * Expected behavior of a AutoRouter.
 */
interface Auto_Router_Interface
{
    /**
     * Returns controller, method and params from the URI.
     *
     * @return array [directory_name, controller_name, controller_method, params]
     */
    public function get_route(string $uri, string $http_verb): array;
}