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
namespace Code_Igniter\Encryption\Handlers;

use Code_Igniter\Encryption\Exceptions\Encryption_Exception;
use Sensitive_Parameter;
/**
 * SodiumHandler uses libsodium in encryption.
 *
 * @see https://github.com/jedisct1/libsodium/issues/392
 * @see \CodeIgniter\Encryption\Handlers\SodiumHandlerTest
 */
class Sodium_Handler extends Base_Handler
{
    /**
     * Starter key
     *
     * @var string|null Null is used for buffer cleanup.
     */
    protected $key = '';
    /**
     * Block size for padding message.
     *
     * @var int
     */
    protected $block_size = 16;
    /**
     * {@inheritDoc}
     */
    public function encrypt(
        #[Sensitive_Parameter]
        $data,
        #[Sensitive_Parameter]
        $params = null
    )
    {
        // Allow key override
        $key = $params !== null ? is_array($params) && isset($params['key']) ? $params['key'] : $params : $this->key;
        // Allow blockSize override
        $block_size = is_array($params) && isset($params['blockSize']) ? $params['blockSize'] : $this->block_size;
        if (empty($key)) {
            throw Encryption_Exception::for_needs_starter_key();
        }
        // create a nonce for this operation
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        // 24 bytes
        // add padding before we encrypt the data
        if ($block_size <= 0) {
            throw Encryption_Exception::for_encryption_failed();
        }
        $data = sodium_pad($data, $block_size);
        // encrypt message and combine with nonce
        $ciphertext = $nonce . sodium_crypto_secretbox($data, $nonce, $key);
        // cleanup buffers
        sodium_memzero($data);
        sodium_memzero($key);
        return $ciphertext;
    }
    /**
     * {@inheritDoc}
     */
    public function decrypt(
        $data,
        #[Sensitive_Parameter]
        $params = null
    )
    {
        // Allow key override
        $key = $params !== null ? is_array($params) && isset($params['key']) ? $params['key'] : $params : $this->key;
        // Allow blockSize override
        $block_size = is_array($params) && isset($params['blockSize']) ? $params['blockSize'] : $this->block_size;
        if (empty($key)) {
            throw Encryption_Exception::for_needs_starter_key();
        }
        if (mb_strlen($data, '8bit') < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            // message was truncated
            throw Encryption_Exception::for_authentication_failed();
        }
        // Extract info from encrypted data
        $nonce = self::substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = self::substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        // decrypt data
        $data = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($data === false) {
            // message was tampered in transit
            throw Encryption_Exception::for_authentication_failed();
            // @codeCoverageIgnore
        }
        // remove extra padding during encryption
        if ($block_size <= 0) {
            throw Encryption_Exception::for_authentication_failed();
        }
        $data = sodium_unpad($data, $block_size);
        // cleanup buffers
        sodium_memzero($ciphertext);
        sodium_memzero($key);
        return $data;
    }
    /**
     * Parse the $params before doing assignment.
     *
     * @param array|string|null $params
     *
     * @return void
     *
     * @throws EncryptionException If key is empty
     *
     * @deprecated 4.7.0 No longer used.
     */
    protected function parse_params($params)
    {
        if ($params === null) {
            return;
        }
        if (is_array($params)) {
            if (isset($params['key'])) {
                $this->key = $params['key'];
            }
            if (isset($params['blockSize'])) {
                $this->block_size = $params['blockSize'];
            }
            return;
        }
        $this->key = (string) $params;
    }
}