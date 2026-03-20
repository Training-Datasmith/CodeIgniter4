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
namespace Kint\Renderer;

abstract class Abstract_Renderer implements Constructable_Renderer_Interface
{
    public static ?string $js_nonce = null;
    public static ?string $css_nonce = null;
    /** @psalm-var ?non-empty-string */
    public static ?string $file_link_format = null;
    protected bool $show_trace = true;
    protected ?array $callee = null;
    protected array $trace = [];
    protected bool $render_spl_ids = true;
    public function __construct()
    {
    }
    public function should_render_object_ids(): bool
    {
        return $this->render_spl_ids;
    }
    public function set_call_info(array $info): void
    {
        $this->callee = $info['callee'] ?? null;
        $this->trace = $info['trace'] ?? [];
    }
    public function set_statics(array $statics): void
    {
        $this->show_trace = !empty($statics['display_called_from']);
    }
    public function filter_parser_plugins(array $plugins): array
    {
        return $plugins;
    }
    public function pre_render(): string
    {
        return '';
    }
    public function post_render(): string
    {
        return '';
    }
    public static function get_file_link(string $file, int $line): ?string
    {
        if (null === self::$file_link_format) {
            return null;
        }
        return \str_replace(['%f', '%l'], [$file, $line], self::$file_link_format);
    }
}