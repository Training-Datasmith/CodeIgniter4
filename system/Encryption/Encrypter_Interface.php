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
 * CodeIgniter Encryption Handler
 *
 * Provides two-way keyed encryption
 */
interface Encrypter_Interface
{
    /**
     * Encrypt - convert plaintext into ciphertext
     *
     * @param string            $data   Input data
     * @param array|string|null $params Overridden parameters, specifically the key
     *
     * @return string
     *
     * @throws EncryptionException
     */
    public function encrypt(
        #[Sensitive_Parameter]
        $data,
        #[Sensitive_Parameter]
        $params = null
    );
    /**
     * Decrypt - convert ciphertext into plaintext
     *
     * @param string            $data   Encrypted data
     * @param array|string|null $params Overridden parameters, specifically the key
     *
     * @return string
     *
     * @throws EncryptionException
     */
    public function decrypt(
        $data,
        #[Sensitive_Parameter]
        $params = null
    );
}