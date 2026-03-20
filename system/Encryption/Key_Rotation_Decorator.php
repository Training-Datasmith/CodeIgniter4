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
use Sensitive_Parameter;
/**
 * Key Rotation Decorator
 *
 * Wraps any EncrypterInterface implementation to provide automatic
 * fallback to previous encryption keys during decryption. This enables
 * seamless key rotation without requiring re-encryption of existing data.
 */
class Key_Rotation_Decorator implements Encrypter_Interface
{
    /**
     * @param EncrypterInterface $innerHandler The wrapped encryption handler
     * @param list<string>       $previousKeys Array of previous encryption keys
     */
    public function __construct(private readonly Encrypter_Interface $inner_handler, private readonly array $previous_keys)
    {
    }
    /**
     * {@inheritDoc}
     *
     * Encryption always uses the inner handler's current key.
     */
    public function encrypt(
        #[Sensitive_Parameter]
        $data,
        #[Sensitive_Parameter]
        $params = null
    )
    {
        return $this->inner_handler->encrypt($data, $params);
    }
    /**
     * {@inheritDoc}
     *
     * Attempts decryption with current key first. If that fails and no
     * explicit key was provided in $params, tries each previous key.
     *
     * @throws EncryptionException
     */
    public function decrypt(
        $data,
        #[Sensitive_Parameter]
        $params = null
    )
    {
        try {
            return $this->inner_handler->decrypt($data, $params);
        } catch (Encryption_Exception $e) {
            // Don't try previous keys if an explicit key was provided
            if (is_string($params) || is_array($params) && isset($params['key'])) {
                throw $e;
            }
            if ($this->previous_keys === []) {
                throw $e;
            }
            foreach ($this->previous_keys as $previous_key) {
                try {
                    $previous_params = is_array($params) ? array_merge($params, ['key' => $previous_key]) : $previous_key;
                    return $this->inner_handler->decrypt($data, $previous_params);
                } catch (Encryption_Exception) {
                    continue;
                }
            }
            throw $e;
        }
    }
    /**
     * Delegate property access to the inner handler.
     *
     * @return array|bool|int|string|null
     */
    public function __get(string $key)
    {
        if (method_exists($this->inner_handler, '__get')) {
            return $this->inner_handler->__get($key);
        }
        return null;
    }
    /**
     * Delegate property existence check to inner handler.
     */
    public function __isset(string $key): bool
    {
        if (method_exists($this->inner_handler, '__isset')) {
            return $this->inner_handler->__isset($key);
        }
        return false;
    }
}