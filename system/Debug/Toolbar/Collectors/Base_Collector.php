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
 * Base Toolbar collector
 */
class Base_Collector
{
    /**
     * Whether this collector has data that can
     * be displayed in the Timeline.
     *
     * @var bool
     */
    protected $has_timeline = false;
    /**
     * Whether this collector needs to display
     * content in a tab or not.
     *
     * @var bool
     */
    protected $has_tab_content = false;
    /**
     * Whether this collector needs to display
     * a label or not.
     *
     * @var bool
     */
    protected $has_label = false;
    /**
     * Whether this collector has data that
     * should be shown in the Vars tab.
     *
     * @var bool
     */
    protected $has_var_data = false;
    /**
     * The 'title' of this Collector.
     * Used to name things in the toolbar HTML.
     *
     * @var string
     */
    protected $title = '';
    /**
     * Gets the Collector's title.
     */
    public function get_title(bool $safe = false): string
    {
        if ($safe) {
            return str_replace(' ', '-', strtolower($this->title));
        }
        return $this->title;
    }
    /**
     * Returns any information that should be shown next to the title.
     */
    public function get_title_details(): string
    {
        return '';
    }
    /**
     * Does this collector need it's own tab?
     */
    public function has_tab_content(): bool
    {
        return (bool) $this->has_tab_content;
    }
    /**
     * Does this collector have a label?
     */
    public function has_label(): bool
    {
        return (bool) $this->has_label;
    }
    /**
     * Does this collector have information for the timeline?
     */
    public function has_timeline_data(): bool
    {
        return (bool) $this->has_timeline;
    }
    /**
     * Grabs the data for the timeline, properly formatted,
     * or returns an empty array.
     */
    public function timeline_data(): array
    {
        if (!$this->has_timeline) {
            return [];
        }
        return $this->format_timeline_data();
    }
    /**
     * Does this Collector have data that should be shown in the
     * 'Vars' tab?
     */
    public function has_var_data(): bool
    {
        return (bool) $this->has_var_data;
    }
    /**
     * Gets a collection of data that should be shown in the 'Vars' tab.
     * The format is an array of sections, each with their own array
     * of key/value pairs:
     *
     *  $data = [
     *      'section 1' => [
     *          'foo' => 'bar,
     *          'bar' => 'baz'
     *      ],
     *      'section 2' => [
     *          'foo' => 'bar,
     *          'bar' => 'baz'
     *      ],
     *  ];
     *
     * @return array|null
     */
    public function get_var_data()
    {
        return null;
    }
    /**
     * Child classes should implement this to return the timeline data
     * formatted for correct usage.
     *
     * Timeline data should be formatted into arrays that look like:
     *
     *  [
     *      'name'      => 'Database::Query',
     *      'component' => 'Database',
     *      'start'     => 10       // milliseconds
     *      'duration'  => 15       // milliseconds
     *  ]
     */
    protected function format_timeline_data(): array
    {
        return [];
    }
    /**
     * Returns the data of this collector to be formatted in the toolbar
     *
     * @return array|string
     */
    public function display()
    {
        return [];
    }
    /**
     * This makes nicer looking paths for the error output.
     *
     * @deprecated Use the dedicated `clean_path()` function.
     */
    public function clean_path(string $file): string
    {
        return clean_path($file);
    }
    /**
     * Gets the "badge" value for the button.
     *
     * @return int|null
     */
    public function get_badge_value()
    {
        return null;
    }
    /**
     * Does this collector have any data collected?
     *
     * If not, then the toolbar button won't get shown.
     */
    public function is_empty(): bool
    {
        return false;
    }
    /**
     * Returns the HTML to display the icon. Should either
     * be SVG, or a base-64 encoded.
     *
     * Recommended dimensions are 24px x 24px
     */
    public function icon(): string
    {
        return '';
    }
    /**
     * Return settings as an array.
     */
    public function get_as_array(): array
    {
        return ['title' => $this->get_title(), 'titleSafe' => $this->get_title(true), 'titleDetails' => $this->get_title_details(), 'display' => $this->display(), 'badgeValue' => $this->get_badge_value(), 'isEmpty' => $this->is_empty(), 'hasTabContent' => $this->has_tab_content(), 'hasLabel' => $this->has_label(), 'icon' => $this->icon(), 'hasTimelineData' => $this->has_timeline_data(), 'timelineData' => $this->timeline_data()];
    }
}