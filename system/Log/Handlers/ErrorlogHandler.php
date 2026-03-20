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
namespace Code_Igniter\Log\Handlers;

use Code_Igniter\Log\Exceptions\Log_Exception;
/**
 * Log handler that writes to PHP's `error_log()`
 *
 * @see \CodeIgniter\Log\Handlers\ErrorlogHandlerTest
 */
class Errorlog_Handler extends Base_Handler
{
    /**
     * Message is sent to PHP's system logger, using the Operating System's
     * system logging mechanism or a file, depending on what the error_log
     * configuration directive is set to.
     */
    public const TYPE_OS = 0;
    /**
     * Message is sent directly to the SAPI logging handler.
     */
    public const TYPE_SAPI = 4;
    /**
     * Says where the error should go. Currently supported are
     * 0 (`TYPE_OS`) and 4 (`TYPE_SAPI`).
     *
     * @var 0|4
     */
    protected $message_type = 0;
    /**
     * Constructor.
     *
     * @param array{handles?: list<string>, messageType?: int} $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $message_type = $config['messageType'] ?? self::TYPE_OS;
        if (!is_int($message_type) || !in_array($message_type, [self::TYPE_OS, self::TYPE_SAPI], true)) {
            throw Log_Exception::for_invalid_message_type(print_r($message_type, true));
        }
        $this->message_type = $message_type;
    }
    /**
     * Handles logging the message.
     * If the handler returns false, then execution of handlers
     * will stop. Any handlers that have not run, yet, will not
     * be run.
     *
     * @param string $level
     * @param string $message
     */
    public function handle($level, $message): bool
    {
        $message = strtoupper($level) . ' --> ' . $message . "\n";
        return $this->error_log($message, $this->message_type);
    }
    /**
     * Extracted call to `error_log()` in order to be tested.
     *
     * @param 0|4 $messageType
     *
     * @codeCoverageIgnore
     */
    protected function error_log(string $message, int $message_type): bool
    {
        return error_log($message, $message_type);
    }
}