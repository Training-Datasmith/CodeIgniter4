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
namespace Code_Igniter\Format;

use Code_Igniter\Format\Exceptions\Format_Exception;
use Config\Format;
/**
 * JSON data formatter
 *
 * @see \CodeIgniter\Format\JSONFormatterTest
 */
class Json_Formatter implements Formatter_Interface
{
    /**
     * Takes the given data and formats it.
     *
     * @param array<array-key, mixed>|object|string $data
     *
     * @return false|non-empty-string
     */
    public function format($data)
    {
        $config = new Format();
        $options = $config->formatter_options['application/json'] ?? JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $options |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        if (ENVIRONMENT !== 'production') {
            $options |= JSON_PRETTY_PRINT;
        }
        $result = json_encode($data, $options, $config->json_encode_depth ?? 512);
        if (!in_array(json_last_error(), [JSON_ERROR_NONE, JSON_ERROR_RECURSION], true)) {
            throw Format_Exception::for_invalid_json(json_last_error_msg());
        }
        return $result;
    }
}