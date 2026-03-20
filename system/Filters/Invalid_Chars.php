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

use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\Security\Exceptions\Security_Exception;
/**
 * InvalidChars filter.
 *
 * Check if user input data ($_GET, $_POST, $_COOKIE, php://input) do not contain
 * invalid characters:
 *   - invalid UTF-8 characters
 *   - control characters except line break and tab code
 *
 * @see \CodeIgniter\Filters\InvalidCharsTest
 */
class Invalid_Chars implements Filter_Interface
{
    /**
     * Data source
     *
     * @var string
     */
    protected $source;
    /**
     * Regular expressions for valid control codes
     *
     * @var string
     */
    protected $control_code_regex = '/\A[\r\n\t[:^cntrl:]]*\z/u';
    /**
     * Check invalid characters.
     *
     * @param list<string>|null $arguments
     */
    public function before(Request_Interface $request, $arguments = null)
    {
        if (!$request instanceof Incoming_Request) {
            return null;
        }
        $data = ['get' => $request->get_get(), 'post' => $request->get_post(), 'cookie' => $request->get_cookie(), 'rawInput' => $request->get_raw_input()];
        foreach ($data as $source => $values) {
            $this->source = $source;
            $this->check_encoding($values);
            $this->check_control($values);
        }
        return null;
    }
    /**
     * We don't have anything to do here.
     *
     * @param list<string>|null $arguments
     */
    public function after(Request_Interface $request, Response_Interface $response, $arguments = null)
    {
        return null;
    }
    /**
     * Check the character encoding is valid UTF-8.
     *
     * @param array|string $value
     *
     * @return array|string
     */
    protected function check_encoding($value)
    {
        if (is_array($value)) {
            array_map($this->check_encoding(...), $value);
            return $value;
        }
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        throw Security_Exception::for_invalid_utf8chars($this->source, $value);
    }
    /**
     * Check for the presence of control characters except line breaks and tabs.
     *
     * @param array|string $value
     *
     * @return array|string
     */
    protected function check_control($value)
    {
        if (is_array($value)) {
            array_map($this->check_control(...), $value);
            return $value;
        }
        if (preg_match($this->control_code_regex, $value) === 1) {
            return $value;
        }
        throw Security_Exception::for_invalid_control_chars($this->source, $value);
    }
}