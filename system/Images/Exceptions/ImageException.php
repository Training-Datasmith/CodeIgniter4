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
namespace Code_Igniter\Images\Exceptions;

use Code_Igniter\Exceptions\Framework_Exception;
class Image_Exception extends Framework_Exception
{
    /**
     * Thrown when the image is not found.
     *
     * @return static
     */
    public static function for_missing_image()
    {
        return new static(lang('Images.sourceImageRequired'));
    }
    /**
     * Thrown when the file specific is not following the role.
     *
     * @return static
     */
    public static function for_file_not_supported()
    {
        return new static(lang('Images.fileNotSupported'));
    }
    /**
     * Thrown when the angle is undefined.
     *
     * @return static
     */
    public static function for_missing_angle()
    {
        return new static(lang('Images.rotationAngleRequired'));
    }
    /**
     * Thrown when the direction property is invalid.
     *
     * @return static
     */
    public static function for_invalid_direction(?string $dir = null)
    {
        return new static(lang('Images.invalidDirection', [$dir]));
    }
    /**
     * Thrown when the path property is invalid.
     *
     * @return static
     */
    public static function for_invalid_path()
    {
        return new static(lang('Images.invalidPath'));
    }
    /**
     * Thrown when the EXIF function is not supported.
     *
     * @return static
     */
    public static function for_exif_unsupported()
    {
        return new static(lang('Images.exifNotSupported'));
    }
    /**
     * Thrown when the image specific is invalid.
     *
     * @return static
     */
    public static function for_invalid_image_create(?string $extra = null)
    {
        return new static(lang('Images.unsupportedImageCreate') . ' ' . $extra);
    }
    /**
     * Thrown when the image save failed.
     *
     * @return static
     */
    public static function for_save_failed()
    {
        return new static(lang('Images.saveFailed'));
    }
    /**
     * Thrown when the image library path is invalid.
     *
     * @deprecated 4.7.0 No longer used.
     *
     * @return static
     */
    public static function for_invalid_image_library_path(?string $path = null)
    {
        return new static(lang('Images.libPathInvalid', [$path]));
    }
    /**
     * Thrown when the image process failed.
     *
     * @return static
     */
    public static function for_image_process_failed()
    {
        return new static(lang('Images.imageProcessFailed'));
    }
}