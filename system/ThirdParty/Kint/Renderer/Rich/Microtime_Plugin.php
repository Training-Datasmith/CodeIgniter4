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
namespace Kint\Renderer\Rich;

use Kint\Utils;
use Kint\Value\Abstract_Value;
use Kint\Value\Representation\Microtime_Representation;
use Kint\Value\Representation\Representation_Interface;
class Microtime_Plugin extends Abstract_Plugin implements Tab_Plugin_Interface
{
    public function render_tab(Representation_Interface $r, Abstract_Value $v): ?string
    {
        if (!$r instanceof Microtime_Representation || !$dt = $r->get_date_time()) {
            return null;
        }
        $out = $dt->format('Y-m-d H:i:s.u');
        if (null !== $lap = $r->get_lap_time()) {
            $out .= '<br><b>SINCE LAST CALL:</b> <span class="kint-microtime-lap">' . \round($lap, 4) . '</span>s.';
        }
        if (null !== $total = $r->get_total_time()) {
            $out .= '<br><b>SINCE START:</b> ' . \round($total, 4) . 's.';
        }
        if (null !== $avg = $r->get_average_time()) {
            $out .= '<br><b>AVERAGE DURATION:</b> <span class="kint-microtime-avg">' . \round($avg, 4) . '</span>s.';
        }
        $bytes = Utils::get_human_readable_bytes($r->get_memory_usage());
        $out .= '<br><b>MEMORY USAGE:</b> ' . $r->get_memory_usage() . ' bytes (' . \round($bytes['value'], 3) . ' ' . $bytes['unit'] . ')';
        $bytes = Utils::get_human_readable_bytes($r->get_memory_usage_real());
        $out .= ' (real ' . \round($bytes['value'], 3) . ' ' . $bytes['unit'] . ')';
        $bytes = Utils::get_human_readable_bytes($r->get_memory_peak_usage());
        $out .= '<br><b>PEAK MEMORY USAGE:</b> ' . $r->get_memory_peak_usage() . ' bytes (' . \round($bytes['value'], 3) . ' ' . $bytes['unit'] . ')';
        $bytes = Utils::get_human_readable_bytes($r->get_memory_peak_usage_real());
        $out .= ' (real ' . \round($bytes['value'], 3) . ' ' . $bytes['unit'] . ')';
        return '<pre data-kint-microtime-group="' . $r->get_group() . '">' . $out . '</pre>';
    }
}