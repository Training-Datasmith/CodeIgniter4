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
use Config\Format as FormatConfig;
/**
 * The Format class is a convenient place to create Formatters.
 *
 * @see \CodeIgniter\Format\FormatTest
 */
class Format
{
    public function __construct(protected Format_Config $config)
    {
    }
    /**
     * Returns the current configuration instance.
     *
     * @return FormatConfig
     */
    public function get_config()
    {
        return $this->config;
    }
    /**
     * A Factory method to return the appropriate formatter for the given mime type.
     *
     * @throws FormatException
     */
    public function get_formatter(string $mime): Formatter_Interface
    {
        if (!array_key_exists($mime, $this->config->formatters)) {
            throw Format_Exception::for_invalid_mime($mime);
        }
        $class_name = $this->config->formatters[$mime];
        if (!class_exists($class_name)) {
            throw Format_Exception::for_invalid_formatter($class_name);
        }
        $class = new $class_name();
        if (!$class instanceof Formatter_Interface) {
            throw Format_Exception::for_invalid_formatter($class_name);
        }
        return $class;
    }
}