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
namespace Code_Igniter\Debug;

use Code_Igniter\Code_Igniter;
use Code_Igniter\Debug\Toolbar\Collectors\Base_Collector;
use Code_Igniter\Debug\Toolbar\Collectors\Config;
use Code_Igniter\Debug\Toolbar\Collectors\History;
use Code_Igniter\Format\Json_Formatter;
use Code_Igniter\Format\Xml_Formatter;
use Code_Igniter\HTTP\Download_Response;
use Code_Igniter\HTTP\Header;
use Code_Igniter\HTTP\Incoming_Request;
use Code_Igniter\HTTP\Request_Interface;
use Code_Igniter\HTTP\Response_Interface;
use Code_Igniter\I18n\Time;
use Config\Toolbar as ToolbarConfig;
use Kint\Kint;
/**
 * Displays a toolbar with bits of stats to aid a developer in debugging.
 *
 * Inspiration: http://prophiler.fabfuel.de
 */
class Toolbar
{
    /**
     * Toolbar configuration settings.
     *
     * @var ToolbarConfig
     */
    protected $config;
    /**
     * Collectors to be used and displayed.
     *
     * @var list<BaseCollector>
     */
    protected $collectors = [];
    public function __construct(Toolbar_Config $config)
    {
        $this->config = $config;
        foreach ($config->collectors as $collector) {
            if (!class_exists($collector)) {
                log_message('critical', 'Toolbar collector does not exist (' . $collector . ').' . ' Please check $collectors in the app/Config/Toolbar.php file.');
                continue;
            }
            $this->collectors[] = new $collector();
        }
    }
    /**
     * Returns all the data required by Debug Bar
     *
     * @param float           $startTime App start time
     * @param IncomingRequest $request
     *
     * @return string JSON encoded data
     */
    public function run(float $start_time, float $total_time, Request_Interface $request, Response_Interface $response): string
    {
        $data = [];
        // Data items used within the view.
        $data['url'] = current_url();
        $data['method'] = $request->get_method();
        $data['isAJAX'] = $request->is_ajax();
        $data['startTime'] = $start_time;
        $data['totalTime'] = $total_time * 1000;
        $data['totalMemory'] = number_format(memory_get_peak_usage() / 1024 / 1024, 3);
        $data['segmentDuration'] = $this->round_to($data['totalTime'] / 7);
        $data['segmentCount'] = (int) ceil($data['totalTime'] / $data['segmentDuration']);
        $data['CI_VERSION'] = Code_Igniter::CI_VERSION;
        $data['collectors'] = [];
        foreach ($this->collectors as $collector) {
            $data['collectors'][] = $collector->get_as_array();
        }
        foreach ($this->collect_var_data() as $heading => $items) {
            $var_data = [];
            if (is_array($items)) {
                foreach ($items as $key => $value) {
                    if (is_string($value)) {
                        $var_data[esc($key)] = esc($value);
                    } else {
                        $old_kint_mode = Kint::$mode_default;
                        $old_kint_called_from = Kint::$display_called_from;
                        Kint::$mode_default = Kint::MODE_RICH;
                        Kint::$display_called_from = false;
                        $kint = @Kint::dump($value);
                        $kint = substr($kint, strpos($kint, '</style>') + 8);
                        Kint::$mode_default = $old_kint_mode;
                        Kint::$display_called_from = $old_kint_called_from;
                        $var_data[esc($key)] = $kint;
                    }
                }
            }
            $data['vars']['varData'][esc($heading)] = $var_data;
        }
        if (isset($_SESSION)) {
            foreach ($_SESSION as $key => $value) {
                // Replace the binary data with string to avoid json_encode failure.
                if (is_string($value) && preg_match('~[^\x20-\x7E\t\r\n]~', $value)) {
                    $value = 'binary data';
                }
                $data['vars']['session'][esc($key)] = is_string($value) ? esc($value) : '<pre>' . esc(print_r($value, true)) . '</pre>';
            }
        }
        foreach ($request->get_get() as $name => $value) {
            $data['vars']['get'][esc($name)] = is_array($value) ? '<pre>' . esc(print_r($value, true)) . '</pre>' : esc($value);
        }
        foreach ($request->get_post() as $name => $value) {
            $data['vars']['post'][esc($name)] = is_array($value) ? '<pre>' . esc(print_r($value, true)) . '</pre>' : esc($value);
        }
        foreach ($request->headers() as $name => $value) {
            if ($value instanceof Header) {
                $data['vars']['headers'][esc($name)] = esc($value->get_value_line());
            } else {
                foreach ($value as $i => $header) {
                    $index = $i + 1;
                    $data['vars']['headers'][esc($name)] ??= '';
                    $data['vars']['headers'][esc($name)] .= ' (' . $index . ') ' . esc($header->get_value_line());
                }
            }
        }
        foreach ($request->get_cookie() as $name => $value) {
            $data['vars']['cookies'][esc($name)] = esc($value);
        }
        $data['vars']['request'] = ($request->is_secure() ? 'HTTPS' : 'HTTP') . '/' . $request->get_protocol_version();
        $data['vars']['response'] = ['statusCode' => $response->get_status_code(), 'reason' => esc($response->get_reason_phrase()), 'contentType' => esc($response->get_header_line('content-type')), 'headers' => []];
        foreach ($response->headers() as $name => $value) {
            if ($value instanceof Header) {
                $data['vars']['response']['headers'][esc($name)] = esc($value->get_value_line());
            } else {
                foreach ($value as $i => $header) {
                    $index = $i + 1;
                    $data['vars']['response']['headers'][esc($name)] ??= '';
                    $data['vars']['response']['headers'][esc($name)] .= ' (' . $index . ') ' . esc($header->get_value_line());
                }
            }
        }
        $data['config'] = Config::display();
        $response->get_csp()->add_image_src('data:');
        return json_encode($data);
    }
    /**
     * Called within the view to display the timeline itself.
     */
    protected function render_timeline(array $collectors, float $start_time, int $segment_count, int $segment_duration, array &$styles): string
    {
        $rows = $this->collect_timeline_data($collectors);
        $style_count = 0;
        // Use recursive render function
        return $this->render_timeline_recursive($rows, $start_time, $segment_count, $segment_duration, $styles, $style_count);
    }
    /**
     * Recursively renders timeline elements and their children.
     */
    protected function render_timeline_recursive(array $rows, float $start_time, int $segment_count, int $segment_duration, array &$styles, int &$style_count, int $level = 0, bool $is_child = false): string
    {
        $display_time = $segment_count * $segment_duration;
        $output = '';
        foreach ($rows as $row) {
            $has_children = isset($row['children']) && !empty($row['children']);
            $is_query = isset($row['query']) && !empty($row['query']);
            // Open controller timeline by default
            $open = $row['name'] === 'Controller';
            if ($has_children || $is_query) {
                $output .= '<tr class="timeline-parent' . ($open ? ' timeline-parent-open' : '') . '" id="timeline-' . $style_count . '_parent" data-toggle="childrows" data-child="timeline-' . $style_count . '">';
            } else {
                $output .= '<tr>';
            }
            $output .= '<td class="' . ($is_child ? 'debug-bar-width30' : '') . ' debug-bar-level-' . $level . '" >' . ($has_children || $is_query ? '<nav></nav>' : '') . $row['name'] . '</td>';
            $output .= '<td class="' . ($is_child ? 'debug-bar-width10' : '') . '">' . $row['component'] . '</td>';
            $output .= '<td class="' . ($is_child ? 'debug-bar-width10 ' : '') . 'debug-bar-alignRight">' . number_format($row['duration'] * 1000, 2) . ' ms</td>';
            $output .= "<td class='debug-bar-noverflow' colspan='{$segment_count}'>";
            $offset = ((float) $row['start'] - $start_time) * 1000 / $display_time * 100;
            $length = (float) $row['duration'] * 1000 / $display_time * 100;
            $styles['debug-bar-timeline-' . $style_count] = "left: {$offset}%; width: {$length}%;";
            $output .= "<span class='timer debug-bar-timeline-{$style_count}' title='" . number_format($length, 2) . "%'></span>";
            $output .= '</td>';
            $output .= '</tr>';
            $style_count++;
            // Add children if any
            if ($has_children || $is_query) {
                $output .= '<tr class="child-row ' . ($open ? '' : ' debug-bar-ndisplay') . '" id="timeline-' . ($style_count - 1) . '_children" >';
                $output .= '<td colspan="' . ($segment_count + 3) . '" class="child-container">';
                $output .= '<table class="timeline">';
                $output .= '<tbody>';
                if ($is_query) {
                    // Output query string if query
                    $output .= '<tr>';
                    $output .= '<td class="query-container debug-bar-level-' . ($level + 1) . '" >' . $row['query'] . '</td>';
                    $output .= '</tr>';
                } else {
                    // Recursively render children
                    $output .= $this->render_timeline_recursive($row['children'], $start_time, $segment_count, $segment_duration, $styles, $style_count, $level + 1, true);
                }
                $output .= '</tbody>';
                $output .= '</table>';
                $output .= '</td>';
                $output .= '</tr>';
            }
        }
        return $output;
    }
    /**
     * Returns a sorted array of timeline data arrays from the collectors.
     *
     * @param array $collectors
     */
    protected function collect_timeline_data($collectors): array
    {
        $data = [];
        // Collect it
        foreach ($collectors as $collector) {
            if (!$collector['hasTimelineData']) {
                continue;
            }
            $data = array_merge($data, $collector['timelineData']);
        }
        // Sort it
        $sort_array = [array_column($data, 'start'), SORT_NUMERIC, SORT_ASC, array_column($data, 'duration'), SORT_NUMERIC, SORT_DESC, &$data];
        array_multisort(...$sort_array);
        // Add end time to each element
        array_walk($data, static function (&$row): void {
            $row['end'] = $row['start'] + $row['duration'];
        });
        // Group it
        $data = $this->structure_timeline_data($data);
        return $data;
    }
    /**
     * Arranges the already sorted timeline data into a parent => child structure.
     */
    protected function structure_timeline_data(array $elements): array
    {
        // We define ourselves as the first element of the array
        $element = array_shift($elements);
        // If we have children behind us, collect and attach them to us
        while ($elements !== [] && $elements[array_key_first($elements)]['end'] <= $element['end']) {
            $element['children'][] = array_shift($elements);
        }
        // Make sure our children know whether they have children, too
        if (isset($element['children'])) {
            $element['children'] = $this->structure_timeline_data($element['children']);
        }
        // If we have no younger siblings, we can return
        if ($elements === []) {
            return [$element];
        }
        // Make sure our younger siblings know their relatives, too
        return array_merge([$element], $this->structure_timeline_data($elements));
    }
    /**
     * Returns an array of data from all of the modules
     * that should be displayed in the 'Vars' tab.
     */
    protected function collect_var_data(): array
    {
        if (!($this->config->collect_var_data ?? true)) {
            return [];
        }
        $data = [];
        foreach ($this->collectors as $collector) {
            if (!$collector->has_var_data()) {
                continue;
            }
            $data = array_merge($data, $collector->get_var_data());
        }
        return $data;
    }
    /**
     * Rounds a number to the nearest incremental value.
     */
    protected function round_to(float $number, int $increments = 5): float
    {
        $increments = 1 / $increments;
        return ceil($number * $increments) / $increments;
    }
    /**
     * Prepare for debugging.
     */
    public function prepare(?Request_Interface $request = null, ?Response_Interface $response = null): void
    {
        /**
         * @var IncomingRequest|null $request
         */
        if (CI_DEBUG && !is_cli()) {
            if ($this->has_native_header_conflict()) {
                return;
            }
            $app = service('codeigniter');
            $request ??= service('request');
            /** @var ResponseInterface $response */
            $response ??= service('response');
            // Disable the toolbar for downloads
            if ($response instanceof Download_Response) {
                return;
            }
            $toolbar = service('toolbar', $this->config);
            $stats = $app->get_performance_stats();
            $data = $toolbar->run($stats['startTime'], $stats['totalTime'], $request, $response);
            helper('filesystem');
            // Updated to microtime() so we can get history
            $time = sprintf('%.6F', Time::now()->format('U.u'));
            if (!is_dir(WRITEPATH . 'debugbar')) {
                mkdir(WRITEPATH . 'debugbar', 0777);
            }
            write_file(WRITEPATH . 'debugbar/debugbar_' . $time . '.json', $data, 'w+');
            $format = $response->get_header_line('content-type');
            // Non-HTML formats should not include the debugbar
            // then we send headers saying where to find the debug data
            // for this response
            if ($this->should_disable_toolbar($request) || !str_contains($format, 'html')) {
                $response->set_header('Debugbar-Time', "{$time}")->set_header('Debugbar-Link', site_url("?debugbar_time={$time}"));
                return;
            }
            $old_kint_mode = Kint::$mode_default;
            Kint::$mode_default = Kint::MODE_RICH;
            $kint_script = @Kint::dump('');
            Kint::$mode_default = $old_kint_mode;
            $kint_script = substr($kint_script, 0, strpos($kint_script, '</style>') + 8);
            $kint_script = $kint_script === '0' ? '' : $kint_script;
            $script = PHP_EOL . '<script ' . csp_script_nonce() . ' id="debugbar_loader" ' . 'data-time="' . $time . '" ' . 'src="' . site_url() . '?debugbar"></script>' . '<script ' . csp_script_nonce() . ' id="debugbar_dynamic_script"></script>' . '<style ' . csp_style_nonce() . ' id="debugbar_dynamic_style"></style>' . $kint_script . PHP_EOL;
            if (str_contains((string) $response->get_body(), '<head>')) {
                $response->set_body(preg_replace('/<head>/', '<head>' . $script, $response->get_body(), 1));
                return;
            }
            $response->append_body($script);
        }
    }
    /**
     * Inject debug toolbar into the response.
     *
     * @codeCoverageIgnore
     */
    public function respond(): void
    {
        if (ENVIRONMENT === 'testing') {
            return;
        }
        $request = service('request');
        // If the request contains '?debugbar then we're
        // simply returning the loading script
        if ($request->get_get('debugbar') !== null) {
            header('Content-Type: application/javascript');
            ob_start();
            include $this->config->views_path . 'toolbarloader.js';
            $output = ob_get_clean();
            $output = str_replace('{url}', rtrim(site_url(), '/'), $output);
            echo $output;
            exit;
        }
        // Otherwise, if it includes ?debugbar_time, then
        // we should return the entire debugbar.
        if ($request->get_get('debugbar_time')) {
            helper('security');
            // Negotiate the content-type to format the output
            $format = $request->negotiate('media', ['text/html', 'application/json', 'application/xml']);
            $format = explode('/', $format)[1];
            $filename = sanitize_filename('debugbar_' . $request->get_get('debugbar_time'));
            $filename = WRITEPATH . 'debugbar/' . $filename . '.json';
            if (is_file($filename)) {
                // Show the toolbar if it exists
                echo $this->format(file_get_contents($filename), $format);
                exit;
            }
            // Filename not found
            http_response_code(404);
            exit;
            // Exit here is needed to avoid loading the index page
        }
    }
    /**
     * Format output
     */
    protected function format(string $data, string $format = 'html'): string
    {
        $data = json_decode($data, true);
        if (preg_match('/\d+\.\d{6}/s', (string) service('request')->get_get('debugbar_time'), $debugbar_time)) {
            $history = new History();
            $history->set_files($debugbar_time[0], $this->config->max_history);
            $data['collectors'][] = $history->get_as_array();
        }
        $output = '';
        switch ($format) {
            case 'html':
                $data['styles'] = [];
                extract($data);
                $parser = service('parser', $this->config->views_path, null, false);
                ob_start();
                include $this->config->views_path . 'toolbar.tpl.php';
                $output = ob_get_clean();
                break;
            case 'json':
                $formatter = new Json_Formatter();
                $output = $formatter->format($data);
                break;
            case 'xml':
                $formatter = new Xml_Formatter();
                $output = $formatter->format($data);
                break;
        }
        return $output;
    }
    /**
     * Checks if the native PHP headers indicate a non-HTML response
     * or if headers are already sent.
     */
    protected function has_native_header_conflict(): bool
    {
        // If headers are sent, we can't inject HTML.
        if (headers_sent()) {
            return true;
        }
        // Native Header Inspection
        foreach (headers_list() as $header) {
            $lower_header = strtolower($header);
            $is_non_html_content = str_starts_with($lower_header, 'content-type:') && !str_contains($lower_header, 'text/html');
            $is_attachment = str_starts_with($lower_header, 'content-disposition:') && str_contains($lower_header, 'attachment');
            if ($is_non_html_content || $is_attachment) {
                return true;
            }
        }
        return false;
    }
    /**
     * Determine if the toolbar should be disabled based on the request headers.
     *
     * This method allows checking both the presence of headers and their expected values.
     * Useful for AJAX, HTMX, Unpoly, Turbo, etc., where partial HTML responses are expected.
     *
     * @return bool True if any header condition matches; false otherwise.
     */
    private function should_disable_toolbar(Incoming_Request $request): bool
    {
        // Fallback for older installations where the config option is missing (e.g. after upgrading from a previous version).
        $headers = $this->config->disable_on_headers ?? ['X-Requested-With' => 'xmlhttprequest'];
        foreach ($headers as $header_name => $expected_value) {
            if (!$request->has_header($header_name)) {
                continue;
                // header not present, skip
            }
            // If expectedValue is null, only presence is enough
            if ($expected_value === null) {
                return true;
            }
            $header_value = strtolower($request->get_header_line($header_name));
            if ($header_value === strtolower($expected_value)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Reset all collectors for worker mode.
     * Calls reset() on collectors that support it.
     */
    public function reset(): void
    {
        foreach ($this->collectors as $collector) {
            if (method_exists($collector, 'reset')) {
                $collector->reset();
            }
        }
    }
}