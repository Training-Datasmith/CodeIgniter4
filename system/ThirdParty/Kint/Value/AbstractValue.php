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
namespace Kint\Value;

use Kint\Value\Context\Context_Interface;
use Kint\Value\Representation\Representation_Interface;
use OutOfRangeException;
/**
 * @psalm-import-type ValueName from ContextInterface
 *
 * @psalm-type ValueFlags int-mask-of<AbstractValue::FLAG_*>
 */
abstract class Abstract_Value
{
    public const FLAG_NONE = 0;
    public const FLAG_GENERATED = 1 << 0;
    public const FLAG_BLACKLIST = 1 << 1;
    public const FLAG_RECURSION = 1 << 2;
    public const FLAG_DEPTH_LIMIT = 1 << 3;
    public const FLAG_ARRAY_LIMIT = 1 << 4;
    /** @psalm-var ValueFlags */
    public int $flags = self::FLAG_NONE;
    /** @psalm-readonly */
    protected Context_Interface $context;
    /** @psalm-readonly string */
    protected string $type;
    /** @psalm-var RepresentationInterface[] */
    protected array $representations = [];
    public function __construct(Context_Interface $context, string $type)
    {
        $this->context = $context;
        $this->type = $type;
    }
    public function __clone()
    {
        $this->context = clone $this->context;
    }
    public function get_context(): Context_Interface
    {
        return $this->context;
    }
    public function get_hint(): ?string
    {
        if (self::FLAG_NONE === $this->flags) {
            return null;
        }
        if ($this->flags & self::FLAG_BLACKLIST) {
            return 'blacklist';
        }
        if ($this->flags & self::FLAG_RECURSION) {
            return 'recursion';
        }
        if ($this->flags & self::FLAG_DEPTH_LIMIT) {
            return 'depth_limit';
        }
        if ($this->flags & self::FLAG_ARRAY_LIMIT) {
            return 'array_limit';
        }
        return null;
    }
    public function get_type(): string
    {
        return $this->type;
    }
    public function add_representation(Representation_Interface $rep, ?int $pos = null): void
    {
        if (isset($this->representations[$rep->get_name()])) {
            throw new OutOfRangeException('Representation already exists');
        }
        if (null === $pos) {
            $this->representations[$rep->get_name()] = $rep;
        } else {
            $this->representations = \array_merge(\array_slice($this->representations, 0, $pos), [$rep->get_name() => $rep], \array_slice($this->representations, $pos));
        }
    }
    public function replace_representation(Representation_Interface $rep, ?int $pos = null): void
    {
        if (null === $pos) {
            $this->representations[$rep->get_name()] = $rep;
        } else {
            $this->remove_representation($rep);
            $this->add_representation($rep, $pos);
        }
    }
    /**
     * @param RepresentationInterface|string $rep
     */
    public function remove_representation($rep): void
    {
        if ($rep instanceof Representation_Interface) {
            unset($this->representations[$rep->get_name()]);
        } else {
            // String
            unset($this->representations[$rep]);
        }
    }
    public function get_representation(string $name): ?Representation_Interface
    {
        return $this->representations[$name] ?? null;
    }
    /** @psalm-return RepresentationInterface[] */
    public function get_representations(): array
    {
        return $this->representations;
    }
    /** @psalm-param RepresentationInterface[] $reps */
    public function append_representations(array $reps): void
    {
        foreach ($reps as $rep) {
            $this->add_representation($rep);
        }
    }
    /** @psalm-api */
    public function clear_representations(): void
    {
        $this->representations = [];
    }
    public function get_display_type(): string
    {
        return $this->type;
    }
    public function get_display_name(): string
    {
        return (string) $this->context->get_name();
    }
    public function get_display_size(): ?string
    {
        return null;
    }
    public function get_display_value(): ?string
    {
        return null;
    }
    /** @psalm-return AbstractValue[] */
    public function get_display_children(): array
    {
        return [];
    }
}