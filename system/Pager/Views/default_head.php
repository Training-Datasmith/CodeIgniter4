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
use Code_Igniter\Pager\Pager_Renderer;
/**
 * @var PagerRenderer $pager
 */
$pager->set_surround_count(0);
if ($pager->has_previous()) {
    echo '<link rel="prev" href="' . $pager->get_previous() . '">' . PHP_EOL;
}
echo '<link rel="canonical" href="' . $pager->get_current() . '">' . PHP_EOL;
if ($pager->has_next()) {
    echo '<link rel="next" href="' . $pager->get_next() . '">' . PHP_EOL;
}