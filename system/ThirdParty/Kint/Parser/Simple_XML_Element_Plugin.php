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

use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Context\Array_Context;
use Kint\Value\Context\Base_Context;
use Kint\Value\Context\Class_Owned_Context;
use Kint\Value\Context\Context_Interface;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\Representation\Value_Representation;
use Kint\Value\Simple_Xml_Element_Value;
use Simple_Xml_Element;
class Simple_Xml_Element_Plugin extends Abstract_Plugin implements Plugin_Begin_Interface
{
    /**
     * Show all properties and methods.
     */
    public static bool $verbose = false;
    protected Class_Methods_Plugin $methods_plugin;
    public function __construct(Parser $parser)
    {
        parent::__construct($parser);
        $this->methods_plugin = new Class_Methods_Plugin($parser);
    }
    public function set_parser(Parser $p): void
    {
        parent::set_parser($p);
        $this->methods_plugin->set_parser($p);
    }
    public function get_types(): array
    {
        return ['object'];
    }
    public function get_triggers(): int
    {
        // SimpleXMLElement is a weirdo. No recursion (Or rather everything is
        // recursion) and depth limit will have to be handled manually anyway.
        return Parser::TRIGGER_BEGIN;
    }
    public function parse_begin(&$var, Context_Interface $c): ?Abstract_Value
    {
        if (!$var instanceof Simple_Xml_Element) {
            return null;
        }
        return $this->parse_element($var, $c);
    }
    protected function parse_element(Simple_Xml_Element &$var, Context_Interface $c): Simple_Xml_Element_Value
    {
        $parser = $this->get_parser();
        $pdepth = $parser->get_depth_limit();
        $cdepth = $c->get_depth();
        $depthlimit = $pdepth && $cdepth >= $pdepth;
        $has_children = self::has_child_elements($var);
        if ($depthlimit && $has_children) {
            $x = new Simple_Xml_Element_Value($c, $var, [], null);
            $x->flags |= Abstract_Value::FLAG_DEPTH_LIMIT;
            return $x;
        }
        $children = $this->get_children($c, $var);
        $attributes = $this->get_attributes($c, $var);
        $to_string = (string) $var;
        $string_body = !$has_children && \strlen($to_string);
        $x = new Simple_Xml_Element_Value($c, $var, $children, \strlen($to_string) ? $to_string : null);
        if (self::$verbose) {
            $x = $this->methods_plugin->parse_complete($var, $x, Parser::TRIGGER_SUCCESS);
        }
        if ($attributes) {
            $x->add_representation(new Container_Representation('Attributes', $attributes), 0);
        }
        if ($string_body) {
            $base = new Base_Context('(string) ' . $c->get_name());
            $base->depth = $cdepth + 1;
            if (null !== $ap = $c->get_access_path()) {
                $base->access_path = '(string) ' . $ap;
            }
            $to_string = $parser->parse($to_string, $base);
            $x->add_representation(new Value_Representation('toString', $to_string, null, true), 0);
        }
        if ($children) {
            $x->add_representation(new Container_Representation('Children', $children), 0);
        }
        return $x;
    }
    /** @psalm-return list<AbstractValue> */
    protected function get_attributes(Context_Interface $c, Simple_Xml_Element $var): array
    {
        $parser = $this->get_parser();
        $namespaces = \array_merge(['' => null], $var->get_doc_namespaces());
        $cdepth = $c->get_depth();
        $ap = $c->get_access_path();
        $contents = [];
        foreach ($namespaces as $ns_alias => $_) {
            if ((bool) $ns_attribs = $var->attributes($ns_alias, true)) {
                foreach ($ns_attribs as $name => $attrib) {
                    $obj = new Array_Context($name);
                    $obj->depth = $cdepth + 1;
                    if (null !== $ap) {
                        $obj->access_path = '(string) ' . $ap;
                        if ('' !== $ns_alias) {
                            $obj->access_path .= '->attributes(' . \var_export($ns_alias, true) . ', true)';
                        }
                        $obj->access_path .= '[' . \var_export($name, true) . ']';
                    }
                    if ('' !== $ns_alias) {
                        $obj->name = $ns_alias . ':' . $obj->name;
                    }
                    $string = (string) $attrib;
                    $attribute = $parser->parse($string, $obj);
                    $contents[] = $attribute;
                }
            }
        }
        return $contents;
    }
    /**
     * Alright kids, let's learn about SimpleXMLElement::children!
     * children can take a namespace url or alias and provide a list of
     * child nodes. This is great since just accessing the members through
     * properties doesn't work on SimpleXMLElement when they have a
     * namespace at all!
     *
     * Unfortunately SimpleXML decided to go the retarded route of
     * categorizing elements by their tag name rather than by their local
     * name (to put it in Dom terms) so if you have something like this:
     *
     * <root xmlns:localhost="http://localhost/">
     *   <tag />
     *   <tag xmlns="http://localhost/" />
     *   <localhost:tag />
     * </root>
     *
     * * children(null) will get the first 2 results
     * * children('', true) will get the first 2 results
     * * children('http://localhost/') will get the last 2 results
     * * children('localhost', true) will get the last result
     *
     * So let's just give up and stick to aliases because fuck that mess!
     *
     * @psalm-return list<SimpleXMLElementValue>
     */
    protected function get_children(Context_Interface $c, Simple_Xml_Element $var): array
    {
        $namespaces = \array_merge(['' => null], $var->get_doc_namespaces());
        $cdepth = $c->get_depth();
        $ap = $c->get_access_path();
        $contents = [];
        foreach ($namespaces as $ns_alias => $_) {
            $ns_children = $var->children($ns_alias, true);
            if (!(bool) $ns_children) {
                continue;
            }
            $nsap = [];
            foreach ($ns_children as $name => $child) {
                $base = new Class_Owned_Context((string) $name, Simple_Xml_Element::class);
                $base->depth = $cdepth + 1;
                if ('' !== $ns_alias) {
                    $base->name = $ns_alias . ':' . $name;
                }
                if (null !== $ap) {
                    if ('' === $ns_alias) {
                        $base->access_path = $ap . '->';
                    } else {
                        $base->access_path = $ap . '->children(' . \var_export($ns_alias, true) . ', true)->';
                    }
                    if (Utils::is_valid_php_name((string) $name)) {
                        $base->access_path .= (string) $name;
                    } else {
                        $base->access_path .= '{' . \var_export((string) $name, true) . '}';
                    }
                    if (isset($nsap[$base->access_path])) {
                        ++$nsap[$base->access_path];
                        $base->access_path .= '[' . $nsap[$base->access_path] . ']';
                    } else {
                        $nsap[$base->access_path] = 0;
                    }
                }
                $v = $this->parse_element($child, $base);
                $v->flags |= Abstract_Value::FLAG_GENERATED;
                $contents[] = $v;
            }
        }
        return $contents;
    }
    /**
     * More SimpleXMLElement bullshit.
     *
     * If we want to know if the element contains text we can cast to string.
     * Except if it contains text mixed with elements simplexml for some stupid
     * reason decides to concatenate the text from between those elements
     * rather than all the text in the hierarchy...
     *
     * So we have NO way of getting text nodes between elements, but we can
     * still tell if we have elements right? If we have elements we assume it's
     * not a string and call it a day!
     *
     * Well if you cast the element to an array attributes will be on it so
     * you'd have to remove that key, and if it's a string it'll also have the
     * 0 index used for the string contents too...
     *
     * Wait, can we use the 0 index to tell if it's a string? Nope! CDATA
     * doesn't show up AT ALL when casting to anything but string, and we'll
     * still get those concatenated strings of mostly whitespace if we just do
     * (string) and check the length.
     *
     * Luckily, I found the only way to do this reliably is through children().
     * We still have to loop through all the namespaces and see if there's a
     * match but then we have the problem of the attributes showing up again...
     *
     * Or at least that's what var_dump says. And when we cast the result to
     * bool it's true too... But if we cast it to array then it's suddenly empty!
     *
     * Long story short the function below is the only way to reliably check if
     * a SimpleXMLElement has children
     */
    protected static function has_child_elements(Simple_Xml_Element $var): bool
    {
        $namespaces = \array_merge(['' => null], $var->get_doc_namespaces());
        foreach ($namespaces as $ns_alias => $_) {
            if ((array) $var->children($ns_alias, true)) {
                return true;
            }
        }
        return false;
    }
}