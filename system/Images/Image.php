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
namespace Code_Igniter\Images;

use Code_Igniter\Files\File;
use Code_Igniter\Images\Exceptions\Image_Exception;
/**
 * Encapsulation of an Image file
 *
 * @see \CodeIgniter\Images\ImageTest
 */
class Image extends File
{
    /**
     * The original image width in pixels.
     *
     * @var float|int
     */
    public $orig_width;
    /**
     * The original image height in pixels.
     *
     * @var float|int
     */
    public $orig_height;
    /**
     * The image type constant.
     *
     * @see http://php.net/manual/en/image.constants.php
     *
     * @var int
     */
    public $image_type;
    /**
     * attributes string with size info:
     * 'height="100" width="200"'
     *
     * @var string
     */
    public $size_str;
    /**
     * The image's mime type, i.e. image/jpeg
     *
     * @var string
     */
    public $mime;
    /**
     * Makes a copy of itself to the new location. If no filename is provided
     * it will use the existing filename.
     *
     * @param string      $targetPath The directory to store the file in
     * @param string|null $targetName The new name of the copied file.
     * @param int         $perms      File permissions to be applied after copy.
     */
    public function copy(string $target_path, ?string $target_name = null, int $perms = 0644): bool
    {
        $target_path = rtrim($target_path, '/ ') . '/';
        $target_name ??= $this->get_filename();
        if (empty($target_name)) {
            throw Image_Exception::for_invalid_file($target_name);
        }
        if (!is_dir($target_path)) {
            mkdir($target_path, 0755, true);
        }
        if (!copy($this->get_pathname(), "{$target_path}{$target_name}")) {
            throw Image_Exception::for_copy_error($target_path);
        }
        chmod("{$target_path}/{$target_name}", $perms);
        return true;
    }
    /**
     * Get image properties
     *
     * A helper function that gets info about the file
     *
     * @return array|bool
     */
    public function get_properties(bool $return = false)
    {
        $path = $this->get_pathname();
        $vals = getimagesize($path);
        if ($vals === false) {
            throw Image_Exception::for_file_not_supported();
        }
        $types = [IMAGETYPE_GIF => 'gif', IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        $mime = 'image/' . ($types[$vals[2]] ?? 'jpg');
        if ($return) {
            return ['width' => $vals[0], 'height' => $vals[1], 'image_type' => $vals[2], 'size_str' => $vals[3], 'mime_type' => $mime];
        }
        $this->orig_width = $vals[0];
        $this->orig_height = $vals[1];
        $this->image_type = $vals[2];
        $this->size_str = $vals[3];
        $this->mime = $mime;
        return true;
    }
}