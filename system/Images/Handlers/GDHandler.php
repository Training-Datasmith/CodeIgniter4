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
namespace Code_Igniter\Images\Handlers;

use Code_Igniter\Images\Exceptions\Image_Exception;
use Config\Images;
/**
 * Image handler for GD package
 */
class Gd_Handler extends Base_Handler
{
    /**
     * Constructor.
     *
     * @param Images|null $config
     *
     * @throws ImageException
     */
    public function __construct($config = null)
    {
        parent::__construct($config);
        if (!extension_loaded('gd')) {
            throw Image_Exception::for_missing_extension('GD');
            // @codeCoverageIgnore
        }
    }
    /**
     * Handles the rotation of an image resource.
     * Doesn't save the image, but replaces the current resource.
     */
    protected function _rotate(int $angle): bool
    {
        // Create the image handle
        $src_img = $this->create_image();
        // Set the background color
        // This won't work with transparent PNG files so we are
        // going to have to figure out how to determine the color
        // of the alpha channel in a future release.
        $white = imagecolorallocate($src_img, 255, 255, 255);
        // Rotate it!
        $dest_img = imagerotate($src_img, $angle, $white);
        $this->resource = $dest_img;
        return true;
    }
    /**
     * Flattens transparencies
     *
     * @return $this
     */
    protected function _flatten(int $red = 255, int $green = 255, int $blue = 255)
    {
        $src_img = $this->create_image();
        if (function_exists('imagecreatetruecolor')) {
            $create = 'imagecreatetruecolor';
            $copy = 'imagecopyresampled';
        } else {
            $create = 'imagecreate';
            $copy = 'imagecopyresized';
        }
        $dest = $create($this->width, $this->height);
        $matte = imagecolorallocate($dest, $red, $green, $blue);
        imagefilledrectangle($dest, 0, 0, $this->width, $this->height, $matte);
        imagecopy($dest, $src_img, 0, 0, 0, 0, $this->width, $this->height);
        $this->resource = $dest;
        return $this;
    }
    /**
     * Flips an image along it's vertical or horizontal axis.
     *
     * @return $this
     */
    protected function _flip(string $direction)
    {
        $src_img = $this->create_image();
        $angle = $direction === 'horizontal' ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL;
        imageflip($src_img, $angle);
        $this->resource = $src_img;
        return $this;
    }
    /**
     * Get GD version
     *
     * @return mixed
     */
    public function get_version()
    {
        if (function_exists('gd_info')) {
            $gd_version = @gd_info();
            return preg_replace('/\D/', '', $gd_version['GD Version']);
        }
        return false;
    }
    /**
     * Resizes the image.
     *
     * @return GDHandler
     */
    public function _resize(bool $maintain_ratio = false)
    {
        return $this->process('resize');
    }
    /**
     * Crops the image.
     *
     * @return GDHandler
     */
    public function _crop()
    {
        return $this->process('crop');
    }
    /**
     * Handles all of the grunt work of resizing, etc.
     *
     * @return $this
     */
    protected function process(string $action)
    {
        $orig_width = $this->image()->orig_width;
        $orig_height = $this->image()->orig_height;
        if ($action === 'crop') {
            // Reassign the source width/height if cropping
            $orig_width = $this->width;
            $orig_height = $this->height;
            // Modify the "original" width/height to the new
            // values so that methods that come after have the
            // correct size to work with.
            $this->image()->orig_height = $this->height;
            $this->image()->orig_width = $this->width;
        }
        // Create the image handle
        $src = $this->create_image();
        if (function_exists('imagecreatetruecolor')) {
            $create = 'imagecreatetruecolor';
            $copy = 'imagecopyresampled';
        } else {
            $create = 'imagecreate';
            $copy = 'imagecopyresized';
        }
        $dest = $create($this->width, $this->height);
        // for png and webp we can actually preserve transparency
        if (in_array($this->image()->image_type, $this->support_transparency, true)) {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
        }
        $copy($dest, $src, 0, 0, (int) $this->x_axis, (int) $this->y_axis, $this->width, $this->height, $orig_width, $orig_height);
        $this->resource = $dest;
        return $this;
    }
    /**
     * Saves any changes that have been made to file. If no new filename is
     * provided, the existing image is overwritten, otherwise a copy of the
     * file is made at $target.
     *
     * Example:
     *    $image->resize(100, 200, true)
     *          ->save();
     *
     * @param non-empty-string|null $target
     */
    public function save(?string $target = null, int $quality = 90): bool
    {
        $original = $target;
        $target = $target === null || $target === '' ? $this->image()->get_pathname() : $target;
        // If no new resource has been created, then we're
        // simply copy the existing one.
        if (empty($this->resource) && $quality === 100) {
            if ($original === null) {
                return true;
            }
            $name = basename($target);
            $path = pathinfo($target, PATHINFO_DIRNAME);
            return $this->image()->copy($path, $name);
        }
        $this->ensure_resource();
        // for png and webp we can actually preserve transparency
        if (in_array($this->image()->image_type, $this->support_transparency, true)) {
            imagepalettetotruecolor($this->resource);
            imagealphablending($this->resource, false);
            imagesavealpha($this->resource, true);
        }
        switch ($this->image()->image_type) {
            case IMAGETYPE_GIF:
                if (!function_exists('imagegif')) {
                    throw Image_Exception::for_invalid_image_create(lang('Images.gifNotSupported'));
                }
                if (!@imagegif($this->resource, $target)) {
                    throw Image_Exception::for_save_failed();
                }
                break;
            case IMAGETYPE_JPEG:
                if (!function_exists('imagejpeg')) {
                    throw Image_Exception::for_invalid_image_create(lang('Images.jpgNotSupported'));
                }
                if (!@imagejpeg($this->resource, $target, $quality)) {
                    throw Image_Exception::for_save_failed();
                }
                break;
            case IMAGETYPE_PNG:
                if (!function_exists('imagepng')) {
                    throw Image_Exception::for_invalid_image_create(lang('Images.pngNotSupported'));
                }
                if (!@imagepng($this->resource, $target)) {
                    throw Image_Exception::for_save_failed();
                }
                break;
            case IMAGETYPE_WEBP:
                if (!function_exists('imagewebp')) {
                    throw Image_Exception::for_invalid_image_create(lang('Images.webpNotSupported'));
                }
                if (!@imagewebp($this->resource, $target, $quality)) {
                    throw Image_Exception::for_save_failed();
                }
                break;
            default:
                throw Image_Exception::for_invalid_image_create();
        }
        $this->resource = null;
        chmod($target, $this->file_permissions);
        return true;
    }
    /**
     * Create Image Resource
     *
     * This simply creates an image resource handle
     * based on the type of image being processed
     *
     * @return bool|resource
     */
    protected function create_image(string $path = '', string $image_type = '')
    {
        if ($this->resource !== null) {
            return $this->resource;
        }
        if ($path === '') {
            $path = $this->image()->get_pathname();
        }
        if ($image_type === '') {
            $image_type = $this->image()->image_type;
        }
        return $this->get_image_resource($path, $image_type);
    }
    /**
     * Make the image resource object if needed
     */
    protected function ensure_resource()
    {
        if ($this->resource === null) {
            // if valid image type, make corresponding image resource
            $this->resource = $this->get_image_resource($this->image()->get_pathname(), $this->image()->image_type);
        }
    }
    /**
     * Check if image type is supported and return image resource
     *
     * @param string $path      Image path
     * @param int    $imageType Image type
     *
     * @return bool|resource
     *
     * @throws ImageException
     */
    protected function get_image_resource(string $path, int $image_type)
    {
        switch ($image_type) {
            case IMAGETYPE_GIF:
                if (!function_exists('imagecreatefromgif')) {
                    throw Image_Exception::for_invalid_image_create(lang('Images.gifNotSupported'));
                }
                return imagecreatefromgif($path);
            case IMAGETYPE_JPEG:
                if (!function_exists('imagecreatefromjpeg')) {
                    throw Image_Exception::for_invalid_image_create(lang('Images.jpgNotSupported'));
                }
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                if (!function_exists('imagecreatefrompng')) {
                    throw Image_Exception::for_invalid_image_create(lang('Images.pngNotSupported'));
                }
                return @imagecreatefrompng($path);
            case IMAGETYPE_WEBP:
                if (!function_exists('imagecreatefromwebp')) {
                    throw Image_Exception::for_invalid_image_create(lang('Images.webpNotSupported'));
                }
                return imagecreatefromwebp($path);
            default:
                throw Image_Exception::for_invalid_image_create('Ima');
        }
    }
    /**
     * Add text overlay to an image.
     */
    protected function _text(string $text, array $options = [])
    {
        // Reverse the vertical offset
        // When the image is positioned at the bottom
        // we don't want the vertical offset to push it
        // further down. We want the reverse, so we'll
        // invert the offset. Note: The horizontal
        // offset flips itself automatically
        if ($options['vAlign'] === 'bottom') {
            $options['vOffset'] *= -1;
        }
        if ($options['hAlign'] === 'right') {
            $options['hOffset'] *= -1;
        }
        // Set font width and height
        // These are calculated differently depending on
        // whether we are using the true type font or not
        if (!empty($options['fontPath'])) {
            if (function_exists('imagettfbbox')) {
                $temp = imagettfbbox($options['fontSize'], 0, $options['fontPath'], $text);
                $temp = $temp[2] - $temp[0];
                $fontwidth = $temp / strlen($text);
            } else {
                $fontwidth = $options['fontSize'] - $options['fontSize'] / 4;
            }
            $fontheight = $options['fontSize'];
        } else {
            $fontwidth = imagefontwidth($options['fontSize']);
            $fontheight = imagefontheight($options['fontSize']);
        }
        $options['fontheight'] = $fontheight;
        $options['fontwidth'] = $fontwidth;
        // Set base X and Y axis values
        $x_axis = $options['hOffset'] + $options['padding'];
        $y_axis = $options['vOffset'] + $options['padding'];
        // Set vertical alignment
        if ($options['vAlign'] === 'middle') {
            // Don't apply padding when you're in the middle of the image.
            $y_axis += $this->image()->orig_height / 2 + $fontheight / 2 - $options['padding'] - $fontheight - $options['shadowOffset'];
        } elseif ($options['vAlign'] === 'bottom') {
            $y_axis = $this->image()->orig_height - $fontheight - $options['shadowOffset'] - $fontheight / 2 - $y_axis;
        }
        // Set horizontal alignment
        if ($options['hAlign'] === 'right') {
            $x_axis += $this->image()->orig_width - $fontwidth * strlen($text) - $options['shadowOffset'] - 2 * $options['padding'];
        } elseif ($options['hAlign'] === 'center') {
            $x_axis += floor(($this->image()->orig_width - $fontwidth * strlen($text)) / 2);
        }
        $options['xAxis'] = $x_axis;
        $options['yAxis'] = $y_axis;
        if ($options['withShadow']) {
            // Offset from text
            $options['xShadow'] = $x_axis + $options['shadowOffset'];
            $options['yShadow'] = $y_axis + $options['shadowOffset'];
            $this->text_overlay($text, $options, true);
        }
        $this->text_overlay($text, $options);
    }
    /**
     * Handler-specific method for overlaying text on an image.
     *
     * @param bool $isShadow Whether we are drawing the dropshadow or actual text
     */
    protected function text_overlay(string $text, array $options = [], bool $is_shadow = false)
    {
        $src = $this->create_image();
        /* Set RGB values for shadow
         *
         * Get the rest of the string and split it into 2-length
         * hex values:
         */
        $opacity = (int) ($options['opacity'] * 127);
        // Allow opacity to be applied to the text
        imagealphablending($src, true);
        $color = $is_shadow ? $options['shadowColor'] : $options['color'];
        // shorthand hex, #f00
        if (strlen($color) === 3) {
            $color = implode('', array_map(str_repeat(...), str_split($color), [2, 2, 2]));
        }
        $color = str_split(substr($color, 0, 6), 2);
        $color = imagecolorclosestalpha($src, hexdec($color[0]), hexdec($color[1]), hexdec($color[2]), $opacity);
        $x_axis = $is_shadow ? $options['xShadow'] : $options['xAxis'];
        $y_axis = $is_shadow ? $options['yShadow'] : $options['yAxis'];
        // Add the shadow to the source image
        if (!empty($options['fontPath'])) {
            // We have to add fontheight because imagettftext locates the bottom left corner, not top-left corner.
            imagettftext($src, $options['fontSize'], 0, (int) $x_axis, (int) ($y_axis + $options['fontheight']), $color, $options['fontPath'], $text);
        } else {
            imagestring($src, (int) $options['fontSize'], (int) $x_axis, (int) $y_axis, $text, $color);
        }
        $this->resource = $src;
    }
    /**
     * Return image width.
     *
     * @return int
     */
    public function _get_width()
    {
        return imagesx($this->resource);
    }
    /**
     * Return image height.
     *
     * @return int
     */
    public function _get_height()
    {
        return imagesy($this->resource);
    }
}