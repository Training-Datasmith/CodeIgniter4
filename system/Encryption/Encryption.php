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
namespace Code_Igniter\Encryption;

use Code_Igniter\Encryption\Exceptions\Encryption_Exception;
use Config\Encryption as EncryptionConfig;
/**
 * CodeIgniter Encryption Manager
 *
 * Provides two-way keyed encryption via PHP's Sodium and/or OpenSSL extensions.
 * This class determines the driver, cipher, and mode to use, and then
 * initializes the appropriate encryption handler.
 *
 * @property-read string       $digest
 * @property-read string       $driver
 * @property-read list<string> $drivers
 * @property-read string       $key
 *
 * @see \CodeIgniter\Encryption\EncryptionTest
 */
class Encryption
{
    /**
     * The encrypter we create
     *
     * @var EncrypterInterface
     */
    protected $encrypter;
    /**
     * The driver being used
     *
     * @var string
     */
    protected $driver;
    /**
     * The key/seed being used
     *
     * @var string
     */
    protected $key;
    /**
     * The derived HMAC key
     *
     * @var string
     */
    protected $hmac_key;
    /**
     * HMAC digest to use
     *
     * @var string
     */
    protected $digest = 'SHA512';
    /**
     * Map of drivers to handler classes, in preference order
     *
     * @var array
     */
    protected $drivers = ['OpenSSL', 'Sodium'];
    /**
     * Handlers that are to be installed
     *
     * @var array<string, bool>
     */
    protected $handlers = [];
    /**
     * @throws EncryptionException
     */
    public function __construct(?Encryption_Config $config = null)
    {
        $config ??= new Encryption_Config();
        $this->key = $config->key;
        $this->driver = $config->driver;
        $this->digest = $config->digest ?? 'SHA512';
        $this->handlers = [
            'OpenSSL' => extension_loaded('openssl'),
            // the SodiumHandler uses some API (like sodium_pad) that is available only on v1.0.14+
            'Sodium' => extension_loaded('sodium') && version_compare(SODIUM_LIBRARY_VERSION, '1.0.14', '>='),
        ];
        if (!in_array($this->driver, $this->drivers, true) || array_key_exists($this->driver, $this->handlers) && !$this->handlers[$this->driver]) {
            throw Encryption_Exception::for_no_handler_available($this->driver);
        }
    }
    /**
     * Initialize or re-initialize an encrypter
     *
     * @return EncrypterInterface
     *
     * @throws EncryptionException
     */
    public function initialize(?Encryption_Config $config = null)
    {
        if ($config instanceof Encryption_Config) {
            $this->key = $config->key;
            $this->driver = $config->driver;
            $this->digest = $config->digest ?? 'SHA512';
        }
        if (empty($this->driver)) {
            throw Encryption_Exception::for_no_driver_requested();
        }
        if (!in_array($this->driver, $this->drivers, true)) {
            throw Encryption_Exception::for_un_known_handler($this->driver);
        }
        if (empty($this->key)) {
            throw Encryption_Exception::for_needs_starter_key();
        }
        $this->hmac_key = bin2hex(\hash_hkdf($this->digest, $this->key));
        $handler_name = 'CodeIgniter\Encryption\Handlers\\' . $this->driver . 'Handler';
        $this->encrypter = new $handler_name($config);
        if (($config->previous_keys ?? []) !== []) {
            $this->encrypter = new Key_Rotation_Decorator($this->encrypter, $config->previous_keys);
        }
        return $this->encrypter;
    }
    /**
     * Create a random key
     *
     * @param int $length Output length
     *
     * @return string
     */
    public static function create_key($length = 32)
    {
        return random_bytes($length);
    }
    /**
     * __get() magic, providing readonly access to some of our protected properties
     *
     * @param string $key Property name
     *
     * @return array|string|null
     */
    public function __get($key)
    {
        if ($this->__isset($key)) {
            return $this->{$key};
        }
        return null;
    }
    /**
     * __isset() magic, providing checking for some of our protected properties
     *
     * @param string $key Property name
     */
    public function __isset($key): bool
    {
        return in_array($key, ['key', 'digest', 'driver', 'drivers'], true);
    }
}