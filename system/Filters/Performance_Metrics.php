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
 * Performance Metrics filter
 */
class Performance_Metrics implements Filter_Interface
{
    /**
     * We don't need to do anything here.
     *
     * @param array|null $arguments
     */
    public function before(Request_Interface $request, $arguments = null)
    {
        return null;
    }
    /**
     * Replaces the performance metrics.
     *
     * @param array|null $arguments
     */
    public function after(Request_Interface $request, Response_Interface $response, $arguments = null)
    {
        $body = $response->get_body();
        if ($body !== null) {
            $benchmark = service('timer');
            $output = str_replace(['{elapsed_time}', '{memory_usage}'], [(string) $benchmark->get_elapsed_time('total_execution'), number_format(memory_get_peak_usage() / 1024 / 1024, 3)], $body);
            $response->set_body($output);
            return $response;
        }
        return null;
    }
}