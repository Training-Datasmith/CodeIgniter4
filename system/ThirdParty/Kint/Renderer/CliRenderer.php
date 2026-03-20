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

use Kint\Value\Abstract_Value;
use Throwable;
class Cli_Renderer extends Text_Renderer
{
    /**
     * @var bool enable colors
     */
    public static bool $cli_colors = true;
    /**
     * Detects the terminal width on startup.
     */
    public static bool $detect_width = true;
    /**
     * The minimum width to detect terminal size as.
     *
     * Less than this is ignored and falls back to default width.
     */
    public static int $min_terminal_width = 40;
    /**
     * Forces utf8 output on windows.
     */
    public static bool $force_utf8 = false;
    /**
     * Which stream to check for VT100 support on windows.
     *
     * uses STDOUT by default if it's defined
     *
     * @psalm-var ?resource
     */
    public static $windows_stream = null;
    protected static ?int $terminal_width = null;
    protected bool $windows_output = false;
    protected bool $colors = false;
    public function __construct()
    {
        parent::__construct();
        if (!self::$force_utf8 && KINT_WIN) {
            if (!\function_exists('sapi_windows_vt100_support')) {
                $this->windows_output = true;
            } else {
                $stream = self::$windows_stream;
                if (!$stream && \defined('STDOUT')) {
                    $stream = STDOUT;
                }
                if (!$stream) {
                    $this->windows_output = true;
                } else {
                    $this->windows_output = !\sapi_windows_vt100_support($stream);
                }
            }
        }
        if (null === self::$terminal_width) {
            if (self::$detect_width) {
                try {
                    $tput = KINT_WIN ? \exec('tput cols 2>nul') : \exec('tput cols 2>/dev/null');
                    if ((bool) $tput) {
                        /**
                         * @psalm-suppress InvalidCast
                         * Psalm bug #11080
                         */
                        self::$terminal_width = (int) $tput;
                    }
                } catch (Throwable $t) {
                    self::$terminal_width = self::$default_width;
                }
            }
            if (!isset(self::$terminal_width) || self::$terminal_width < self::$min_terminal_width) {
                self::$terminal_width = self::$default_width;
            }
        }
        $this->colors = $this->windows_output ? false : self::$cli_colors;
        $this->header_width = self::$terminal_width;
    }
    public function color_value(string $string): string
    {
        if (!$this->colors) {
            return $string;
        }
        return "\x1b[32m" . \str_replace("\n", "\x1b[0m\n\x1b[32m", $string) . "\x1b[0m";
    }
    public function color_type(string $string): string
    {
        if (!$this->colors) {
            return $string;
        }
        return "\x1b[35;1m" . \str_replace("\n", "\x1b[0m\n\x1b[35;1m", $string) . "\x1b[0m";
    }
    public function color_title(string $string): string
    {
        if (!$this->colors) {
            return $string;
        }
        return "\x1b[36m" . \str_replace("\n", "\x1b[0m\n\x1b[36m", $string) . "\x1b[0m";
    }
    public function render_title(Abstract_Value $v): string
    {
        if ($this->windows_output) {
            return $this->utf8to_windows(parent::render_title($v));
        }
        return parent::render_title($v);
    }
    public function pre_render(): string
    {
        return PHP_EOL;
    }
    public function post_render(): string
    {
        if ($this->windows_output) {
            return $this->utf8to_windows(parent::post_render());
        }
        return parent::post_render();
    }
    public function escape(string $string, $encoding = false): string
    {
        return \str_replace("\x1b", '\x1b', $string);
    }
    protected function utf8to_windows(string $string): string
    {
        return \str_replace(['┌', '═', '┐', '│', '└', '─', '┘'], [' ', '=', ' ', '|', ' ', '-', ' '], $string);
    }
}