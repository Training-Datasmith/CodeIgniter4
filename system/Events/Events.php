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
namespace Code_Igniter\Events;

use Config\Modules;
/**
 * Events
 *
 * @see \CodeIgniter\Events\EventsTest
 */
class Events
{
    public const PRIORITY_LOW = 200;
    public const PRIORITY_NORMAL = 100;
    public const PRIORITY_HIGH = 10;
    /**
     * The list of listeners.
     *
     * @var array<string, array{0: bool, 1: list<int>, 2: list<callable(mixed): mixed>}>
     */
    protected static $listeners = [];
    /**
     * Flag to let us know if we've read from the Config file(s)
     * and have all of the defined events.
     *
     * @var bool
     */
    protected static $initialized = false;
    /**
     * If true, events will not actually be fired.
     * Useful during testing.
     *
     * @var bool
     */
    protected static $simulate = false;
    /**
     * Stores information about the events
     * for display in the debug toolbar.
     *
     * @var list<array{start: float, end: float, event: string}>
     */
    protected static $performance_log = [];
    /**
     * A list of found files.
     *
     * @var list<string>
     */
    protected static $files = [];
    /**
     * Ensures that we have a events file ready.
     *
     * @return void
     */
    public static function initialize()
    {
        // Don't overwrite anything....
        if (static::$initialized) {
            return;
        }
        $config = new Modules();
        $events = APPPATH . 'Config' . DIRECTORY_SEPARATOR . 'Events.php';
        $files = [];
        if ($config->should_discover('events')) {
            $files = service('locator')->search('Config/Events.php');
        }
        $files = array_filter(array_map(realpath(...), $files));
        static::$files = array_values(array_unique(array_merge($files, [$events])));
        foreach (static::$files as $file) {
            include $file;
        }
        static::$initialized = true;
    }
    /**
     * Registers an action to happen on an event. The action can be any sort
     * of callable:
     *
     *  Events::on('create', 'myFunction');               // procedural function
     *  Events::on('create', ['myClass', 'myMethod']);    // Class::method
     *  Events::on('create', [$myInstance, 'myMethod']);  // Method on an existing instance
     *  Events::on('create', function() {});              // Closure
     *
     * @param string                 $eventName
     * @param callable(mixed): mixed $callback
     * @param int                    $priority
     *
     * @return void
     */
    public static function on($event_name, $callback, $priority = self::PRIORITY_NORMAL)
    {
        if (!isset(static::$listeners[$event_name])) {
            static::$listeners[$event_name] = [
                true,
                // If there's only 1 item, it's sorted.
                [$priority],
                [$callback],
            ];
        } else {
            static::$listeners[$event_name][0] = false;
            // Not sorted
            static::$listeners[$event_name][1][] = $priority;
            static::$listeners[$event_name][2][] = $callback;
        }
    }
    /**
     * Runs through all subscribed methods running them one at a time,
     * until either:
     *  a) All subscribers have finished or
     *  b) a method returns false, at which point execution of subscribers stops.
     *
     * @param string $eventName
     * @param mixed  ...$arguments
     */
    public static function trigger($event_name, ...$arguments): bool
    {
        // Read in our Config/Events file so that we have them all!
        if (!static::$initialized) {
            static::initialize();
        }
        $listeners = static::listeners($event_name);
        foreach ($listeners as $listener) {
            $start = microtime(true);
            $result = static::$simulate === false ? $listener(...$arguments) : true;
            if (CI_DEBUG) {
                static::$performance_log[] = ['start' => $start, 'end' => microtime(true), 'event' => $event_name];
            }
            if ($result === false) {
                return false;
            }
        }
        return true;
    }
    /**
     * Returns an array of listeners for a single event. They are
     * sorted by priority.
     *
     * @param string $eventName
     *
     * @return list<callable(mixed): mixed>
     */
    public static function listeners($event_name): array
    {
        if (!isset(static::$listeners[$event_name])) {
            return [];
        }
        // The list is not sorted
        if (!static::$listeners[$event_name][0]) {
            // Sort it!
            array_multisort(static::$listeners[$event_name][1], SORT_NUMERIC, static::$listeners[$event_name][2]);
            // Mark it as sorted already!
            static::$listeners[$event_name][0] = true;
        }
        return static::$listeners[$event_name][2];
    }
    /**
     * Removes a single listener from an event.
     *
     * If the listener couldn't be found, returns FALSE, else TRUE if
     * it was removed.
     *
     * @param string                 $eventName
     * @param callable(mixed): mixed $listener
     */
    public static function remove_listener($event_name, callable $listener): bool
    {
        if (!isset(static::$listeners[$event_name])) {
            return false;
        }
        foreach (static::$listeners[$event_name][2] as $index => $check) {
            if ($check === $listener) {
                unset(static::$listeners[$event_name][1][$index], static::$listeners[$event_name][2][$index]);
                return true;
            }
        }
        return false;
    }
    /**
     * Removes all listeners.
     *
     * If the event_name is specified, only listeners for that event will be
     * removed, otherwise all listeners for all events are removed.
     *
     * @param string|null $eventName
     *
     * @return void
     */
    public static function remove_all_listeners($event_name = null)
    {
        if ($event_name !== null) {
            unset(static::$listeners[$event_name]);
        } else {
            static::$listeners = [];
        }
    }
    /**
     * Sets the path to the file that routes are read from.
     *
     * @param list<string> $files
     *
     * @return void
     */
    public static function set_files(array $files)
    {
        static::$files = $files;
    }
    /**
     * Returns the files that were found/loaded during this request.
     *
     * @return list<string>
     */
    public static function get_files()
    {
        return static::$files;
    }
    /**
     * Turns simulation on or off. When on, events will not be triggered,
     * simply logged. Useful during testing when you don't actually want
     * the tests to run.
     *
     * @return void
     */
    public static function simulate(bool $choice = true)
    {
        static::$simulate = $choice;
    }
    /**
     * Getter for the performance log records.
     *
     * @return list<array{start: float, end: float, event: string}>
     */
    public static function get_performance_logs()
    {
        return static::$performance_log;
    }
    /**
     * Cleanup performance log and request-specific listeners for worker mode.
     *
     * Called at the END of each request to clean up state.
     *
     * @param list<string> $resetEventListeners Additional event names to reset.
     */
    public static function cleanup_for_worker_mode(array $reset_event_listeners = []): void
    {
        if (CI_DEBUG) {
            static::$performance_log = [];
            static::remove_all_listeners('DBQuery');
        }
        foreach ($reset_event_listeners as $event) {
            static::remove_all_listeners($event);
        }
    }
}