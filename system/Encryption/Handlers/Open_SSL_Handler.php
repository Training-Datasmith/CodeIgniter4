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
 * Encryption handling for OpenSSL library
 *
 * @see \CodeIgniter\Encryption\Handlers\OpenSSLHandlerTest
 */
class Open_Ssl_Handler extends Base_Handler
{
    /**
     * HMAC digest to use
     *
     * @var string
     */
    protected $digest = 'SHA512';
    /**
     * List of supported HMAC algorithms
     *
     * @var array [name => digest size]
     */
    protected array $digest_size = ['SHA224' => 28, 'SHA256' => 32, 'SHA384' => 48, 'SHA512' => 64];
    /**
     * Cipher to use
     *
     * @var string
     */
    protected $cipher = 'AES-256-CTR';
    /**
     * Starter key
     *
     * @var string
     */
    protected $key = '';
    /**
     * Whether the cipher-text should be raw. If set to false, then it will be base64 encoded.
     */
    protected bool $raw_data = true;
    /**
     * Encryption key info.
     * This setting is only used by OpenSSLHandler.
     *
     * Set to 'encryption' for CI3 Encryption compatibility.
     */
    public string $encrypt_key_info = '';
    /**
     * Authentication key info.
     * This setting is only used by OpenSSLHandler.
     *
     * Set to 'authentication' for CI3 Encryption compatibility.
     */
    public string $auth_key_info = '';
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
        if (empty($key)) {
            throw Encryption_Exception::for_needs_starter_key();
        }
        // derive a secret key
        $encrypt_key = \hash_hkdf($this->digest, $key, 0, $this->encrypt_key_info);
        // basic encryption
        $iv = ($iv_size = \openssl_cipher_iv_length($this->cipher)) ? random_bytes($iv_size) : null;
        $data = \openssl_encrypt($data, $this->cipher, $encrypt_key, OPENSSL_RAW_DATA, $iv);
        if ($data === false) {
            throw Encryption_Exception::for_encryption_failed();
        }
        $result = $this->raw_data ? $iv . $data : base64_encode($iv . $data);
        // derive a secret key
        $auth_key = \hash_hkdf($this->digest, $key, 0, $this->auth_key_info);
        $hmac_key = \hash_hmac($this->digest, $result, $auth_key, $this->raw_data);
        return $hmac_key . $result;
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
        if (empty($key)) {
            throw Encryption_Exception::for_needs_starter_key();
        }
        // derive a secret key
        $auth_key = \hash_hkdf($this->digest, $key, 0, $this->auth_key_info);
        $hmac_length = $this->raw_data ? $this->digest_size[$this->digest] : $this->digest_size[$this->digest] * 2;
        $hmac_key = self::substr($data, 0, $hmac_length);
        $data = self::substr($data, $hmac_length);
        $hmac_calc = \hash_hmac($this->digest, $data, $auth_key, $this->raw_data);
        if (!hash_equals($hmac_key, $hmac_calc)) {
            throw Encryption_Exception::for_authentication_failed();
        }
        $data = $this->raw_data ? $data : base64_decode($data, true);
        if ($iv_size = \openssl_cipher_iv_length($this->cipher)) {
            $iv = self::substr($data, 0, $iv_size);
            $data = self::substr($data, $iv_size);
        } else {
            $iv = null;
        }
        // derive a secret key
        $encrypt_key = \hash_hkdf($this->digest, $key, 0, $this->encrypt_key_info);
        $result = \openssl_decrypt($data, $this->cipher, $encrypt_key, OPENSSL_RAW_DATA, $iv);
        if ($result === false) {
            throw Encryption_Exception::for_authentication_failed();
        }
        return $result;
    }
}