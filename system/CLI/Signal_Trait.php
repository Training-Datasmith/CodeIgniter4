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
namespace Code_Igniter\CLI;

use Closure;
/**
 * Signal Trait
 *
 * Provides PCNTL signal handling capabilities for CLI commands.
 * Requires the PCNTL extension (Unix only).
 */
trait Signal_Trait
{
    /**
     * Whether the process should continue running (false = termination requested).
     */
    private bool $running = true;
    /**
     * Whether signals are currently blocked.
     */
    private bool $signals_blocked = false;
    /**
     * Array of registered signals.
     *
     * @var list<int>
     */
    private array $registered_signals = [];
    /**
     * Signal-to-method mapping.
     *
     * @var array<int, string>
     */
    private array $signal_method_map = [];
    /**
     * Cached result of PCNTL extension availability.
     */
    private static ?bool $is_pcntl_available = null;
    /**
     * Cached result of POSIX extension availability.
     */
    private static ?bool $is_posix_available = null;
    /**
     * Check if PCNTL extension is available (cached).
     */
    protected function is_pcntl_available(): bool
    {
        if (self::$is_pcntl_available === null) {
            if (is_windows()) {
                self::$is_pcntl_available = false;
            } else {
                self::$is_pcntl_available = extension_loaded('pcntl');
                if (!self::$is_pcntl_available) {
                    CLI::write(lang('CLI.signals.noPcntlExtension'), 'yellow');
                }
            }
        }
        return self::$is_pcntl_available;
    }
    /**
     * Check if POSIX extension is available (cached).
     */
    protected function is_posix_available(): bool
    {
        if (self::$is_posix_available === null) {
            self::$is_posix_available = is_windows() ? false : extension_loaded('posix');
        }
        return self::$is_posix_available;
    }
    /**
     * Register signal handlers.
     *
     * @param list<int>          $signals   List of signals to handle
     * @param array<int, string> $methodMap Optional signal-to-method mapping
     */
    protected function register_signals(array $signals = [], array $method_map = []): void
    {
        if (!$this->is_pcntl_available()) {
            return;
        }
        if ($signals === []) {
            $signals = [SIGTERM, SIGINT, SIGHUP, SIGQUIT];
        }
        if (!$this->is_posix_available() && (in_array(SIGTSTP, $signals, true) || in_array(SIGCONT, $signals, true))) {
            CLI::write(lang('CLI.signals.noPosixExtension'), 'yellow');
            $signals = array_diff($signals, [SIGTSTP, SIGCONT]);
            // Remove from method map as well
            unset($method_map[SIGTSTP], $method_map[SIGCONT]);
            if ($signals === []) {
                return;
            }
        }
        // Enable async signals for immediate response
        pcntl_async_signals(true);
        $this->signal_method_map = $method_map;
        foreach ($signals as $signal) {
            if (pcntl_signal($signal, [$this, 'handleSignal'])) {
                $this->registered_signals[] = $signal;
            } else {
                $signal = $this->get_signal_name($signal);
                CLI::write(lang('CLI.signals.failedSignal', [$signal]), 'red');
            }
        }
    }
    /**
     * Handle incoming signals.
     */
    protected function handle_signal(int $signal): void
    {
        $this->call_custom_handler($signal);
        // Apply standard Unix signal behavior for registered signals
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
            case SIGQUIT:
            case SIGHUP:
                $this->running = false;
                break;
            case SIGTSTP:
                // Restore default handler and re-send signal to actually suspend
                pcntl_signal(SIGTSTP, SIG_DFL);
                posix_kill(posix_getpid(), SIGTSTP);
                break;
            case SIGCONT:
                // Re-register SIGTSTP handler after resume
                pcntl_signal(SIGTSTP, [$this, 'handleSignal']);
                break;
        }
    }
    /**
     * Call custom signal handler if one is mapped for this signal.
     * Falls back to generic onInterruption() method if no explicit mapping exists.
     */
    private function call_custom_handler(int $signal): void
    {
        // Check for explicit mapping first
        $method = $this->signal_method_map[$signal] ?? null;
        if ($method !== null && method_exists($this, $method)) {
            $this->{$method}($signal);
            return;
        }
        // If no explicit mapping, try generic catch-all method
        if (method_exists($this, 'onInterruption')) {
            $this->on_interruption($signal);
        }
    }
    /**
     * Check if command should terminate.
     */
    protected function should_terminate(): bool
    {
        return !$this->running;
    }
    /**
     * Check if the process is currently running (not terminated).
     */
    protected function is_running(): bool
    {
        return $this->running;
    }
    /**
     * Request immediate termination.
     */
    protected function request_termination(): void
    {
        $this->running = false;
    }
    /**
     * Reset all states (for testing or restart scenarios).
     */
    protected function reset_state(): void
    {
        $this->running = true;
        // Unblock signals if they were blocked
        if ($this->signals_blocked) {
            $this->unblock_signals();
        }
    }
    /**
     * Execute a callable with ALL signals blocked to prevent ANY interruption during critical operations.
     *
     * This blocks ALL interruptible signals including:
     * - Termination signals (SIGTERM, SIGINT, etc.)
     * - Pause/resume signals (SIGTSTP, SIGCONT)
     * - Custom signals (SIGUSR1, SIGUSR2)
     *
     * Only SIGKILL (unblockable) can still terminate the process.
     * Use this for database transactions, file operations, or any critical atomic operations.
     *
     * @template TReturn
     *
     * @param Closure():TReturn $operation
     *
     * @return TReturn
     */
    protected function with_signals_blocked(Closure $operation)
    {
        $this->block_signals();
        try {
            return $operation();
        } finally {
            $this->unblock_signals();
        }
    }
    /**
     * Block ALL interruptible signals during critical sections.
     * Only SIGKILL (unblockable) can terminate the process.
     */
    protected function block_signals(): void
    {
        if (!$this->signals_blocked && $this->is_pcntl_available()) {
            // Block ALL signals that could interrupt critical operations
            pcntl_sigprocmask(SIG_BLOCK, [
                SIGTERM,
                SIGINT,
                SIGHUP,
                SIGQUIT,
                // Termination signals
                SIGTSTP,
                SIGCONT,
                // Pause/resume signals
                SIGUSR1,
                SIGUSR2,
                // Custom signals
                SIGPIPE,
                SIGALRM,
            ]);
            $this->signals_blocked = true;
        }
    }
    /**
     * Unblock previously blocked signals.
     */
    protected function unblock_signals(): void
    {
        if ($this->signals_blocked && $this->is_pcntl_available()) {
            // Unblock the same signals we blocked
            pcntl_sigprocmask(SIG_UNBLOCK, [
                SIGTERM,
                SIGINT,
                SIGHUP,
                SIGQUIT,
                // Termination signals
                SIGTSTP,
                SIGCONT,
                // Pause/resume signals
                SIGUSR1,
                SIGUSR2,
                // Custom signals
                SIGPIPE,
                SIGALRM,
            ]);
            $this->signals_blocked = false;
        }
    }
    /**
     * Check if signals are currently blocked.
     */
    protected function signals_blocked(): bool
    {
        return $this->signals_blocked;
    }
    /**
     * Add or update signal-to-method mapping at runtime.
     */
    protected function map_signal(int $signal, string $method): void
    {
        $this->signal_method_map[$signal] = $method;
    }
    /**
     * Get human-readable signal name.
     */
    protected function get_signal_name(int $signal): string
    {
        return match ($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            SIGHUP => 'SIGHUP',
            SIGQUIT => 'SIGQUIT',
            SIGUSR1 => 'SIGUSR1',
            SIGUSR2 => 'SIGUSR2',
            SIGPIPE => 'SIGPIPE',
            SIGALRM => 'SIGALRM',
            SIGTSTP => 'SIGTSTP',
            SIGCONT => 'SIGCONT',
            default => "Signal {$signal}",
        };
    }
    /**
     * Unregister all signals (cleanup).
     */
    protected function unregister_signals(): void
    {
        if (!$this->is_pcntl_available()) {
            return;
        }
        foreach ($this->registered_signals as $signal) {
            pcntl_signal($signal, SIG_DFL);
        }
        $this->registered_signals = [];
        $this->signal_method_map = [];
    }
    /**
     * Check if signals are registered.
     */
    protected function has_signals(): bool
    {
        return $this->registered_signals !== [];
    }
    /**
     * Get list of registered signals.
     *
     * @return list<int>
     */
    protected function get_signals(): array
    {
        return $this->registered_signals;
    }
    /**
     * Get comprehensive process state information.
     *
     * @return array{
     *      pid: int,
     *      running: bool,
     *      pcntl_available: bool,
     *      registered_signals: int,
     *      registered_signals_names: array<int, string>,
     *      signals_blocked: bool,
     *      explicit_mappings: int,
     *      memory_usage_mb: float,
     *      memory_peak_mb: float,
     *      session_id?: false|int,
     *      process_group?: false|int,
     *      has_controlling_terminal?: bool
     *  }
     */
    protected function get_process_state(): array
    {
        $pid = getmypid();
        $state = [
            // Process identification
            'pid' => $pid,
            'running' => $this->running,
            // Signal handling status
            'pcntl_available' => $this->is_pcntl_available(),
            'registered_signals' => count($this->registered_signals),
            'registered_signals_names' => array_map([$this, 'getSignalName'], $this->registered_signals),
            'signals_blocked' => $this->signals_blocked,
            'explicit_mappings' => count($this->signal_method_map),
            // System resources
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
        // Add terminal control info if POSIX extension is available
        if ($this->is_posix_available()) {
            $state['session_id'] = posix_getsid($pid);
            $state['process_group'] = posix_getpgid($pid);
            $state['has_controlling_terminal'] = posix_isatty(STDIN);
        }
        return $state;
    }
}