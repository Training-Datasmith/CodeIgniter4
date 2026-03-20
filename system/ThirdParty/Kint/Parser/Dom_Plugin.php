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

use Dom\Attr;
use Dom\Character_Data;
use Dom\Document;
use Dom\Document_Type;
use Dom\Element;
use Dom\Html_Element;
use Dom\Named_Node_Map;
use Dom\Node;
use Dom\Node_List;
use Dom_Attr;
use Dom_Character_Data;
use Dom_Document_Type;
use Dom_Element;
use Dom_Named_Node_Map;
use Dom_Node;
use Dom_Node_List;
use Kint\Value\Abstract_Value;
use Kint\Value\Context\Base_Context;
use Kint\Value\Context\Class_Declared_Context;
use Kint\Value\Context\Context_Interface;
use Kint\Value\Context\Property_Context;
use Kint\Value\Dom_Node_List_Value;
use Kint\Value\Dom_Node_Value;
use Kint\Value\Fixed_Width_Value;
use Kint\Value\Instance_Value;
use Kint\Value\Representation\Container_Representation;
use Kint\Value\String_Value;
use LogicException;
use ReflectionClass;
class Dom_Plugin extends Abstract_Plugin implements Plugin_Begin_Interface
{
    /**
     * Reflection doesn't show readonly status.
     *
     * In order to ensure this is stable enough we're only going to provide
     * properties for element and node. If subclasses like attr or document
     * have their own fields then tough shit we're not showing them.
     *
     * @psalm-var non-empty-array<string, bool> Property names to readable status
     */
    public const NODE_PROPS = ['nodeType' => true, 'nodeName' => true, 'baseURI' => true, 'isConnected' => true, 'ownerDocument' => true, 'parentNode' => true, 'parentElement' => true, 'childNodes' => true, 'firstChild' => true, 'lastChild' => true, 'previousSibling' => true, 'nextSibling' => true, 'nodeValue' => true, 'textContent' => false];
    /**
     * @psalm-var non-empty-array<string, bool> Property names to readable status
     */
    public const ELEMENT_PROPS = ['namespaceURI' => true, 'prefix' => true, 'localName' => true, 'tagName' => true, 'id' => false, 'className' => false, 'classList' => true, 'attributes' => true, 'firstElementChild' => true, 'lastElementChild' => true, 'childElementCount' => true, 'previousElementSibling' => true, 'nextElementSibling' => true, 'innerHTML' => false, 'outerHTML' => true, 'substitutedNodeValue' => false, 'children' => true];
    /**
     * @psalm-var non-empty-array<string, bool> Property names to readable status
     */
    public const DOMNODE_PROPS = ['nodeName' => true, 'nodeValue' => false, 'nodeType' => true, 'parentNode' => true, 'parentElement' => true, 'childNodes' => true, 'firstChild' => true, 'lastChild' => true, 'previousSibling' => true, 'nextSibling' => true, 'attributes' => true, 'isConnected' => true, 'ownerDocument' => true, 'namespaceURI' => true, 'prefix' => false, 'localName' => true, 'baseURI' => true, 'textContent' => false];
    /**
     * @psalm-var non-empty-array<string, bool> Property names to readable status
     */
    public const DOMELEMENT_PROPS = ['tagName' => true, 'className' => false, 'id' => false, 'schemaTypeInfo' => true, 'firstElementChild' => true, 'lastElementChild' => true, 'childElementCount' => true, 'previousElementSibling' => true, 'nextElementSibling' => true];
    public const DOM_VERSIONS = ['parentElement' => KINT_PHP83, 'isConnected' => KINT_PHP83, 'className' => KINT_PHP83, 'id' => KINT_PHP83, 'firstElementChild' => KINT_PHP80, 'lastElementChild' => KINT_PHP80, 'childElementCount' => KINT_PHP80, 'previousElementSibling' => KINT_PHP80, 'nextElementSibling' => KINT_PHP80];
    /**
     * List of properties to skip parsing.
     *
     * The properties of a Dom\Node can do a *lot* of damage to debuggers. The
     * Dom\Node contains not one, not two, but 13 different ways to recurse into itself:
     * * parentNode
     * * firstChild
     * * lastChild
     * * previousSibling
     * * nextSibling
     * * parentElement
     * * firstElementChild
     * * lastElementChild
     * * previousElementSibling
     * * nextElementSibling
     * * childNodes
     * * attributes
     * * ownerDocument
     *
     * All of this combined: the tiny SVGs used as the caret in Kint were already
     * enough to make parsing and rendering take over a second, and send memory
     * usage over 128 megs, back in the old DOM API. So we blacklist every field
     * we don't strictly need and hope that that's good enough.
     *
     * In retrospect -- this is probably why print_r does the same
     *
     * @psalm-var array<string, true>
     */
    public static array $blacklist = ['parentNode' => true, 'firstChild' => true, 'lastChild' => true, 'previousSibling' => true, 'nextSibling' => true, 'firstElementChild' => true, 'lastElementChild' => true, 'parentElement' => true, 'previousElementSibling' => true, 'nextElementSibling' => true, 'ownerDocument' => true];
    /**
     * Show all properties and methods.
     */
    public static bool $verbose = false;
    /** @psalm-var array<class-string, array<string, bool>> cache of properties for getKnownProperties */
    protected static array $property_cache = [];
    protected Class_Methods_Plugin $methods_plugin;
    protected Class_Statics_Plugin $statics_plugin;
    public function __construct(Parser $parser)
    {
        parent::__construct($parser);
        $this->methods_plugin = new Class_Methods_Plugin($parser);
        $this->statics_plugin = new Class_Statics_Plugin($parser);
    }
    public function set_parser(Parser $p): void
    {
        parent::set_parser($p);
        $this->methods_plugin->set_parser($p);
        $this->statics_plugin->set_parser($p);
    }
    public function get_types(): array
    {
        return ['object'];
    }
    public function get_triggers(): int
    {
        return Parser::TRIGGER_BEGIN;
    }
    public function parse_begin(&$var, Context_Interface $c): ?Abstract_Value
    {
        // Attributes and chardata (Which is parent of comments and text
        // nodes) don't need children or attributes of their own
        if ($var instanceof Attr || $var instanceof Character_Data || $var instanceof Dom_Attr || $var instanceof Dom_Character_Data) {
            return $this->parse_text($var, $c);
        }
        if ($var instanceof Named_Node_Map || $var instanceof Node_List || $var instanceof Dom_Named_Node_Map || $var instanceof Dom_Node_List) {
            return $this->parse_list($var, $c);
        }
        if ($var instanceof Node || $var instanceof Dom_Node) {
            return $this->parse_node($var, $c);
        }
        return null;
    }
    /** @psalm-param Node|DOMNode $var */
    private function parse_property(object $var, string $prop, Context_Interface $c): Abstract_Value
    {
        // Suppress deprecation message
        if (@!isset($var->{$prop})) {
            return new Fixed_Width_Value($c, null);
        }
        $parser = $this->get_parser();
        // Suppress deprecation message
        @$value = $var->{$prop};
        if (\is_scalar($value)) {
            return $parser->parse($value, $c);
        }
        if (isset(self::$blacklist[$prop])) {
            $b = new Instance_Value($c, \get_class($value), \spl_object_hash($value), \spl_object_id($value));
            $b->flags |= Abstract_Value::FLAG_GENERATED | Abstract_Value::FLAG_BLACKLIST;
            return $b;
        }
        // Everything we can handle in parseBegin
        if ($value instanceof Attr || $value instanceof Character_Data || $value instanceof Dom_Attr || $value instanceof Dom_Character_Data || $value instanceof Named_Node_Map || $value instanceof Node_List || $value instanceof Dom_Named_Node_Map || $value instanceof Dom_Node_List || $value instanceof Node || $value instanceof Dom_Node) {
            $out = $this->parse_begin($value, $c);
        }
        if (!isset($out)) {
            // Shouldn't ever happen
            $out = $parser->parse($value, $c);
            // @codeCoverageIgnore
        }
        $out->flags |= Abstract_Value::FLAG_GENERATED;
        return $out;
    }
    /** @psalm-param Attr|CharacterData|DOMAttr|DOMCharacterData $var */
    private function parse_text(object $var, Context_Interface $c): Abstract_Value
    {
        if ($c instanceof Base_Context && null !== $c->access_path) {
            $c->access_path .= '->nodeValue';
        }
        return $this->parse_property($var, 'nodeValue', $c);
    }
    /** @psalm-param NamedNodeMap|NodeList|DOMNamedNodeMap|DOMNodeList $var */
    private function parse_list(object $var, Context_Interface $c): Instance_Value
    {
        if ($var instanceof Node_List || $var instanceof Dom_Node_List) {
            $v = new Dom_Node_List_Value($c, $var);
        } else {
            $v = new Instance_Value($c, \get_class($var), \spl_object_hash($var), \spl_object_id($var));
        }
        $parser = $this->get_parser();
        $pdepth = $parser->get_depth_limit();
        // Depth limit
        // Use empty iterator representation since we need it to point out depth limits
        if (($var instanceof Node_List || $var instanceof Dom_Node_List) && $pdepth && $c->get_depth() >= $pdepth) {
            $v->flags |= Abstract_Value::FLAG_DEPTH_LIMIT;
            return $v;
        }
        if (self::$verbose) {
            $v = $this->methods_plugin->parse_complete($var, $v, Parser::TRIGGER_SUCCESS);
            $v = $this->statics_plugin->parse_complete($var, $v, Parser::TRIGGER_SUCCESS);
        }
        if (0 === $var->length) {
            $v->set_children([]);
            return $v;
        }
        $cdepth = $c->get_depth();
        $ap = $c->get_access_path();
        $contents = [];
        foreach ($var as $key => $item) {
            $base_obj = new Base_Context($item->node_name);
            $base_obj->depth = $cdepth + 1;
            if ($var instanceof Named_Node_Map || $var instanceof Dom_Named_Node_Map) {
                if (null !== $ap) {
                    $base_obj->access_path = $ap . '[' . \var_export($item->node_name, true) . ']';
                }
            } else if (null !== $ap) {
                $base_obj->access_path = $ap . '[' . \var_export($key, true) . ']';
            }
            if ($item instanceof Html_Element) {
                $base_obj->name = $item->local_name;
            }
            $item = $parser->parse($item, $base_obj);
            $item->flags |= Abstract_Value::FLAG_GENERATED;
            $contents[] = $item;
        }
        $v->set_children($contents);
        if ($contents) {
            $v->add_representation(new Container_Representation('Iterator', $contents), 0);
        }
        return $v;
    }
    /** @psalm-param Node|DOMNode $var */
    private function parse_node(object $var, Context_Interface $c): Dom_Node_Value
    {
        $class = \get_class($var);
        $pdepth = $this->get_parser()->get_depth_limit();
        if ($pdepth && $c->get_depth() >= $pdepth) {
            $v = new Dom_Node_Value($c, $var);
            $v->flags |= Abstract_Value::FLAG_DEPTH_LIMIT;
            return $v;
        }
        if (($var instanceof Document_Type || $var instanceof Dom_Document_Type) && $c instanceof Base_Context && $c->name === $var->node_name) {
            $c->name = '!DOCTYPE ' . $c->name;
        }
        $cdepth = $c->get_depth();
        $ap = $c->get_access_path();
        $properties = [];
        $children = [];
        $attributes = [];
        foreach (self::get_known_properties($var) as $prop => $readonly) {
            $prop_c = new Property_Context($prop, $class, Class_Declared_Context::ACCESS_PUBLIC);
            $prop_c->depth = $cdepth + 1;
            $prop_c->readonly = KINT_PHP81 && $readonly;
            if (null !== $ap) {
                $prop_c->access_path = $ap . '->' . $prop;
            }
            $properties[] = $prop_obj = $this->parse_property($var, $prop, $prop_c);
            if ('childNodes' === $prop) {
                if (!$prop_obj instanceof Dom_Node_List_Value) {
                    throw new LogicException('childNodes property parsed incorrectly');
                    // @codeCoverageIgnore
                }
                $children = self::get_children($prop_obj);
            } elseif ('attributes' === $prop) {
                $attributes = $prop_obj->get_representation('iterator');
                $attributes = $attributes instanceof Container_Representation ? $attributes->get_contents() : [];
            } elseif ('classList' === $prop) {
                if ($iter = $prop_obj->get_representation('iterator')) {
                    $prop_obj->remove_representation($iter);
                    $prop_obj->add_representation($iter, 0);
                }
            }
        }
        $v = new Dom_Node_Value($c, $var);
        // If we're in text mode, we can see children through the childNodes property
        $v->set_children($properties);
        if ($children) {
            $v->add_representation(new Container_Representation('Children', $children, null, true));
        }
        if ($attributes) {
            $v->add_representation(new Container_Representation('Attributes', $attributes));
        }
        if (self::$verbose) {
            $v->add_representation(new Container_Representation('Properties', $properties));
            $v = $this->methods_plugin->parse_complete($var, $v, Parser::TRIGGER_SUCCESS);
            $v = $this->statics_plugin->parse_complete($var, $v, Parser::TRIGGER_SUCCESS);
        }
        return $v;
    }
    /**
     * @psalm-param Node|DOMNode $var
     *
     * @psalm-return non-empty-array<string, bool>
     */
    public static function get_known_properties(object $var): array
    {
        if (KINT_PHP81) {
            $r = new ReflectionClass($var);
            $classname = $r->get_name();
            if (!isset(self::$property_cache[$classname])) {
                self::$property_cache[$classname] = [];
                foreach ($r->get_properties() as $prop) {
                    if ($prop->is_static()) {
                        continue;
                    }
                    $declaring = $prop->get_declaring_class()->get_name();
                    $name = $prop->name;
                    if (\in_array($declaring, [Node::class, Element::class], true)) {
                        $readonly = self::NODE_PROPS[$name] ?? self::ELEMENT_PROPS[$name];
                    } elseif (\in_array($declaring, [Dom_Node::class, Dom_Element::class], true)) {
                        $readonly = self::DOMNODE_PROPS[$name] ?? self::DOMELEMENT_PROPS[$name];
                    } else {
                        continue;
                    }
                    self::$property_cache[$classname][$prop->name] = $readonly;
                }
                if ($var instanceof Document) {
                    self::$property_cache[$classname]['textContent'] = true;
                }
                if ($var instanceof Attr || $var instanceof Character_Data) {
                    self::$property_cache[$classname]['nodeValue'] = false;
                }
            }
            $known_properties = self::$property_cache[$classname];
        } else {
            $known_properties = self::DOMNODE_PROPS;
            if ($var instanceof Dom_Element) {
                $known_properties += self::DOMELEMENT_PROPS;
            }
            foreach (self::DOM_VERSIONS as $key => $val) {
                if (false === $val) {
                    unset($known_properties[$key]);
                    // @codeCoverageIgnore
                }
            }
        }
        /** @psalm-var non-empty-array $known_properties */
        if (!self::$verbose) {
            $known_properties = \array_intersect_key($known_properties, ['nodeValue' => null, 'childNodes' => null, 'attributes' => null]);
        }
        return $known_properties;
    }
    /** @psalm-return list<AbstractValue> */
    private static function get_children(Dom_Node_List_Value $property): array
    {
        if (0 === $property->get_length()) {
            return [];
        }
        if ($property->flags & Abstract_Value::FLAG_DEPTH_LIMIT) {
            return [$property];
        }
        $list_items = $property->get_children();
        if (null === $list_items) {
            // This is here for psalm but all DomNodeListValue should
            // either be depth_limit or have array children
            return [];
            // @codeCoverageIgnore
        }
        $children = [];
        foreach ($list_items as $node) {
            // Remove text nodes if theyre empty
            if ($node instanceof String_Value && '#text' === $node->get_context()->get_name()) {
                /**
                 * @psalm-suppress InvalidArgument
                 * Psalm bug #11055
                 */
                if (\ctype_space($node->get_value()) || '' === $node->get_value()) {
                    continue;
                }
            }
            $children[] = $node;
        }
        return $children;
    }
}