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
namespace Code_Igniter\Session\Exceptions;

use Code_Igniter\Exceptions\Framework_Exception;
class Session_Exception extends Framework_Exception
{
    /**
     * @return static
     */
    public static function for_missing_database_table()
    {
        return new static(lang('Session.missingDatabaseTable'));
    }
    /**
     * @return static
     */
    public static function for_invalid_save_path(?string $path = null)
    {
        return new static(lang('Session.invalidSavePath', [$path]));
    }
    /**
     * @return static
     */
    public static function for_write_protected_save_path(?string $path = null)
    {
        return new static(lang('Session.writeProtectedSavePath', [$path]));
    }
    /**
     * @return static
     */
    public static function for_empty_savepath()
    {
        return new static(lang('Session.emptySavePath'));
    }
    /**
     * @return static
     */
    public static function for_invalid_save_path_format(string $path)
    {
        return new static(lang('Session.invalidSavePathFormat', [$path]));
    }
    /**
     * @deprecated
     *
     * @return static
     *
     * @codeCoverageIgnore
     */
    public static function for_invalid_same_site_setting(string $samesite)
    {
        return new static(lang('Session.invalidSameSiteSetting', [$samesite]));
    }
}