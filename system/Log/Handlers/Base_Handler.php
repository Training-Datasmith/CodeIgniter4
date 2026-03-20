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

/**
 * Base class for logging
 */
abstract class Base_Handler implements Handler_Interface
{
    /**
     * Handles
     *
     * @var list<string>
     */
    protected $handles;
    /**
     * Date format for logging
     *
     * @var string
     */
    protected $date_format = 'Y-m-d H:i:s';
    /**
     * @param array{handles?: list<string>} $config
     */
    public function __construct(array $config)
    {
        $this->handles = $config['handles'] ?? [];
    }
    /**
     * Checks whether the Handler will handle logging items of this
     * log Level.
     */
    public function can_handle(string $level): bool
    {
        return in_array($level, $this->handles, true);
    }
    /**
     * Stores the date format to use while logging messages.
     */
    public function set_date_format(string $format): Handler_Interface
    {
        $this->date_format = $format;
        return $this;
    }
}