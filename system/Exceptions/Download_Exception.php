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
namespace Code_Igniter\Exceptions;

/**
 * Class DownloadException
 */
class Download_Exception extends RuntimeException
{
    use Debug_Traceable_Trait;
    /**
     * @return static
     */
    public static function for_cannot_set_file_path(string $path)
    {
        return new static(lang('HTTP.cannotSetFilepath', [$path]));
    }
    /**
     * @return static
     */
    public static function for_cannot_set_binary()
    {
        return new static(lang('HTTP.cannotSetBinary'));
    }
    /**
     * @return static
     */
    public static function for_not_found_download_source()
    {
        return new static(lang('HTTP.notFoundDownloadSource'));
    }
    /**
     * @deprecated Since v4.5.6
     *
     * @return static
     */
    public static function for_cannot_set_cache()
    {
        return new static(lang('HTTP.cannotSetCache'));
    }
    /**
     * @return static
     */
    public static function for_cannot_set_status_code(int $code, string $reason)
    {
        return new static(lang('HTTP.cannotSetStatusCode', [$code, $reason]));
    }
}