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
namespace Code_Igniter\Debug\Toolbar\Collectors;

/**
 * Timers collector
 */
class Timers extends Base_Collector
{
    /**
     * Whether this collector has data that can
     * be displayed in the Timeline.
     *
     * @var bool
     */
    protected $has_timeline = true;
    /**
     * Whether this collector needs to display
     * content in a tab or not.
     *
     * @var bool
     */
    protected $has_tab_content = false;
    /**
     * The 'title' of this Collector.
     * Used to name things in the toolbar HTML.
     *
     * @var string
     */
    protected $title = 'Timers';
    /**
     * Child classes should implement this to return the timeline data
     * formatted for correct usage.
     */
    protected function format_timeline_data(): array
    {
        $data = [];
        $benchmark = service('timer', true);
        $rows = $benchmark->get_timers(6);
        foreach ($rows as $name => $info) {
            if ($name === 'total_execution') {
                continue;
            }
            $data[] = ['name' => ucwords(str_replace('_', ' ', $name)), 'component' => 'Timer', 'start' => $info['start'], 'duration' => $info['end'] - $info['start']];
        }
        return $data;
    }
}