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
namespace Code_Igniter\Debug\Toolbar\Collectors;

use Code_Igniter\Code_Igniter;
use Config\App;
/**
 * Debug toolbar configuration
 */
class Config
{
    /**
     * Return toolbar config values as an array.
     */
    public static function display(): array
    {
        $config = config(App::class);
        return ['ciVersion' => Code_Igniter::CI_VERSION, 'phpVersion' => PHP_VERSION, 'phpSAPI' => PHP_SAPI, 'environment' => ENVIRONMENT, 'baseURL' => $config->base_url, 'timezone' => app_timezone(), 'locale' => service('request')->get_locale(), 'cspEnabled' => $config->csp_enabled];
    }
}