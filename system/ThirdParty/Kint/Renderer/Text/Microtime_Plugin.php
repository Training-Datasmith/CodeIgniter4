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
namespace Kint\Renderer\Text;

use Kint\Renderer\Plain_Renderer;
use Kint\Renderer\Text_Renderer;
use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Representation\Microtime_Representation;
class Microtime_Plugin extends Abstract_Plugin
{
    protected bool $use_js = false;
    public function __construct(Text_Renderer $r)
    {
        parent::__construct($r);
        if ($this->renderer instanceof Plain_Renderer) {
            $this->use_js = true;
        }
    }
    public function render(Abstract_Value $v): ?string
    {
        $r = $v->get_representation('microtime');
        if (!$r instanceof Microtime_Representation || !$dt = $r->get_date_time()) {
            return null;
        }
        $c = $v->get_context();
        $out = '';
        if (0 === $c->get_depth()) {
            $out .= $this->renderer->color_title($this->renderer->render_title($v)) . PHP_EOL;
        }
        $out .= $this->renderer->render_header($v);
        $out .= $this->renderer->render_children($v) . PHP_EOL;
        $indent = \str_repeat(' ', ($c->get_depth() + 1) * $this->renderer->indent_width);
        if ($this->use_js) {
            $out .= '<span data-kint-microtime-group="' . $r->get_group() . '">';
        }
        $out .= $indent . $this->renderer->color_type('TIME:') . ' ';
        $out .= $this->renderer->color_value($dt->format('Y-m-d H:i:s.u')) . PHP_EOL;
        if (null !== $lap = $r->get_lap_time()) {
            $out .= $indent . $this->renderer->color_type('SINCE LAST CALL:') . ' ';
            $lap = \round($lap, 4);
            if ($this->use_js) {
                $lap = '<span class="kint-microtime-lap">' . $lap . '</span>';
            }
            $out .= $this->renderer->color_value($lap . 's') . '.' . PHP_EOL;
        }
        if (null !== $total = $r->get_total_time()) {
            $out .= $indent . $this->renderer->color_type('SINCE START:') . ' ';
            $out .= $this->renderer->color_value(\round($total, 4) . 's') . '.' . PHP_EOL;
        }
        if (null !== $avg = $r->get_average_time()) {
            $out .= $indent . $this->renderer->color_type('AVERAGE DURATION:') . ' ';
            $avg = \round($avg, 4);
            if ($this->use_js) {
                $avg = '<span class="kint-microtime-avg">' . $avg . '</span>';
            }
            $out .= $this->renderer->color_value($avg . 's') . '.' . PHP_EOL;
        }
        $bytes = Utils::get_human_readable_bytes($r->get_memory_usage());
        $mem = $r->get_memory_usage() . ' bytes (' . \round($bytes['value'], 3) . ' ' . $bytes['unit'] . ')';
        $bytes = Utils::get_human_readable_bytes($r->get_memory_usage_real());
        $mem .= ' (real ' . \round($bytes['value'], 3) . ' ' . $bytes['unit'] . ')';
        $out .= $indent . $this->renderer->color_type('MEMORY USAGE:') . ' ';
        $out .= $this->renderer->color_value($mem) . '.' . PHP_EOL;
        $bytes = Utils::get_human_readable_bytes($r->get_memory_peak_usage());
        $mem = $r->get_memory_peak_usage() . ' bytes (' . \round($bytes['value'], 3) . ' ' . $bytes['unit'] . ')';
        $bytes = Utils::get_human_readable_bytes($r->get_memory_peak_usage_real());
        $mem .= ' (real ' . \round($bytes['value'], 3) . ' ' . $bytes['unit'] . ')';
        $out .= $indent . $this->renderer->color_type('PEAK MEMORY USAGE:') . ' ';
        $out .= $this->renderer->color_value($mem) . '.' . PHP_EOL;
        if ($this->use_js) {
            $out .= '</span>';
        }
        return $out;
    }
}