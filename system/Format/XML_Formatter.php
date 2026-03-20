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
use Simple_Xml_Element;
/**
 * XML data formatter
 *
 * @see \CodeIgniter\Format\XMLFormatterTest
 */
class Xml_Formatter implements Formatter_Interface
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
        // SimpleXML is installed but default
        // but best to check, and then provide a fallback.
        if (!extension_loaded('simplexml')) {
            throw Format_Exception::for_missing_extension();
            // @codeCoverageIgnore
        }
        $options = $config->formatter_options['application/xml'] ?? 0;
        $output = new Simple_Xml_Element('<?xml version="1.0"?><response></response>', $options);
        $this->array_to_xml((array) $data, $output);
        return $output->as_xml();
    }
    /**
     * A recursive method to convert an array into a valid XML string.
     *
     * Written by CodexWorld. Received permission by email on Nov 24, 2016 to use this code.
     *
     * @see http://www.codexworld.com/convert-array-to-xml-in-php/
     *
     * @param array<array-key, mixed> $data
     * @param SimpleXMLElement        $output
     *
     * @return void
     */
    protected function array_to_xml(array $data, &$output)
    {
        foreach ($data as $key => $value) {
            $key = $this->normalize_xml_tag($key);
            if (is_array($value)) {
                $subnode = $output->add_child("{$key}");
                $this->array_to_xml($value, $subnode);
            } else {
                $output->add_child("{$key}", htmlspecialchars("{$value}"));
            }
        }
    }
    /**
     * Normalizes tags into the allowed by W3C.
     * Regex adopted from this StackOverflow answer.
     *
     * @param int|string $key
     *
     * @return string
     *
     * @see https://stackoverflow.com/questions/60001029/invalid-characters-in-xml-tag-name
     */
    protected function normalize_xml_tag($key)
    {
        $start_char = 'A-Z_a-z' . '\x{C0}-\x{D6}\x{D8}-\x{F6}\x{F8}-\x{2FF}\x{370}-\x{37D}' . '\x{37F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}' . '\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}' . '\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}';
        $valid_name = $start_char . '\.\d\x{B7}\x{300}-\x{36F}\x{203F}-\x{2040}';
        $key = (string) $key;
        $key = trim($key);
        $key = preg_replace("/[^{$valid_name}-]+/u", '', $key);
        $key = preg_replace("/^[^{$start_char}]+/u", 'item$0', $key);
        return preg_replace('/^(xml).*/iu', 'item$0', $key);
        // XML is a reserved starting word
    }
}