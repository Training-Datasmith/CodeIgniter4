<?php

declare (strict_types=1);
/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2013 Jonathan Vollebregt (jnvsor@gmail.com), Rokas Šleinius (raveren@gmail.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace Kint\Parser;

use Dom\Node;
use Dom\Xml_Document;
use Dom_Document;
use Dom_Exception;
use Dom_Node;
use InvalidArgumentException;
use Kint\Value\Abstract_Value;
use Kint\Value\Context\Base_Context;
use Kint\Value\Context\Context_Interface;
use Kint\Value\Representation\Value_Representation;
use Throwable;
class Xml_Plugin extends Abstract_Plugin implements Plugin_Complete_Interface
{
    /**
     * Which method to parse the variable with.
     *
     * DOMDocument provides more information including the text between nodes,
     * however it's memory usage is very high and it takes longer to parse and
     * render. Plus it's a pain to work with. So SimpleXML is the default.
     *
     * @psalm-var 'SimpleXML'|'DOMDocument'|'XMLDocument'
     */
    public static string $parse_method = 'SimpleXML';
    public function get_types(): array
    {
        return ['string'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_SUCCESS;
    }
    public function parse_complete(&$var, Abstract_Value $v, int $trigger): Abstract_Value
    {
        if ('<?xml' !== \substr($var, 0, 5)) {
            return $v;
        }
        if (!\method_exists($this, 'xmlTo' . self::$parse_method)) {
            return $v;
        }
        $c = $v->get_context();
        $out = \call_user_func([$this, 'xmlTo' . self::$parse_method], $var, $c);
        if (null === $out) {
            return $v;
        }
        $out->flags |= Abstract_Value::FLAG_GENERATED;
        $v->add_representation(new Value_Representation('XML', $out), 0);
        return $v;
    }
    /** @psalm-suppress PossiblyUnusedMethod */
    protected function xml_to_simple_xml(string $var, Context_Interface $c): ?Abstract_Value
    {
        $errors = \libxml_use_internal_errors(true);
        try {
            $xml = \simplexml_load_string($var);
            if (!(bool) $xml) {
                throw new InvalidArgumentException('Bad XML parse in XmlPlugin::xmlToSimpleXML');
            }
        } catch (Throwable $t) {
            return null;
        } finally {
            \libxml_use_internal_errors($errors);
            \libxml_clear_errors();
        }
        $base = new Base_Context($xml->get_name());
        $base->depth = $c->get_depth() + 1;
        if (null !== $ap = $c->get_access_path()) {
            $base->access_path = 'simplexml_load_string(' . $ap . ')';
        }
        return $this->get_parser()->parse($xml, $base);
    }
    /**
     * Get the DOMDocument info.
     *
     * If it errors loading then we wouldn't have gotten this far in the first place.
     *
     * @psalm-suppress PossiblyUnusedMethod
     *
     * @psalm-param non-empty-string $var
     */
    protected function xml_to_dom_document(string $var, Context_Interface $c): ?Abstract_Value
    {
        try {
            $xml = new Dom_Document();
            $check = $xml->load_xml($var, LIBXML_NOWARNING | LIBXML_NOERROR);
            if (false === $check) {
                throw new InvalidArgumentException('Bad XML parse in XmlPlugin::xmlToDOMDocument');
            }
        } catch (Throwable $t) {
            return null;
        }
        $xml = $xml->first_child;
        /**
         * @psalm-var DOMNode $xml
         * Psalm bug #11120
         */
        $base = new Base_Context($xml->node_name);
        $base->depth = $c->get_depth() + 1;
        if (null !== $ap = $c->get_access_path()) {
            $base->access_path = '(function($s){$x = new \DomDocument(); $x->loadXML($s); return $x;})(' . $ap . ')->firstChild';
        }
        return $this->get_parser()->parse($xml, $base);
    }
    /** @psalm-suppress PossiblyUnusedMethod */
    protected function xml_to_xml_document(string $var, Context_Interface $c): ?Abstract_Value
    {
        if (!KINT_PHP84) {
            return null;
            // @codeCoverageIgnore
        }
        try {
            $xml = Xml_Document::create_from_string($var, LIBXML_NOWARNING | LIBXML_NOERROR);
        } catch (Dom_Exception $e) {
            return null;
        }
        $xml = $xml->first_child;
        /**
         * @psalm-var Node $xml
         * Psalm bug #11120
         */
        $base = new Base_Context($xml->node_name);
        $base->depth = $c->get_depth() + 1;
        if (null !== $ap = $c->get_access_path()) {
            $base->access_path = '\Dom\XMLDocument::createFromString(' . $ap . ')->firstChild';
        }
        return $this->get_parser()->parse($xml, $base);
    }
}