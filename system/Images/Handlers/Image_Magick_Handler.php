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
use Imagick;
use Imagick_Draw;
use Imagick_Draw_Exception;
use Imagick_Exception;
use Imagick_Pixel;
use Imagick_Pixel_Exception;
/**
 * Image handler for Imagick extension.
 */
class Image_Magick_Handler extends Base_Handler
{
    /**
     * Stores Imagick instance.
     *
     * @var Imagick|null
     */
    protected $resource;
    /**
     * Constructor.
     *
     * @param Images $config
     *
     * @throws ImageException
     */
    public function __construct($config = null)
    {
        parent::__construct($config);
        if (!extension_loaded('imagick')) {
            throw Image_Exception::for_missing_extension('IMAGICK');
            // @codeCoverageIgnore
        }
    }
    /**
     * Loads the image for manipulation.
     *
     * @return void
     *
     * @throws ImageException
     */
    protected function ensure_resource()
    {
        if (!$this->resource instanceof Imagick) {
            // Verify that we have a valid image
            $this->image();
            try {
                $this->resource = new Imagick();
                $this->resource->read_image($this->image()->get_pathname());
                // Check for valid image
                if ($this->resource->get_image_width() === 0 || $this->resource->get_image_height() === 0) {
                    throw Image_Exception::for_invalid_image_create($this->image()->get_pathname());
                }
                $this->supported_format_check();
            } catch (Imagick_Exception $e) {
                throw Image_Exception::for_invalid_image_create($e->get_message());
            }
        }
    }
    /**
     * Handles all the grunt work of resizing, etc.
     *
     * @param string $action  Type of action to perform
     * @param int    $quality Quality setting for Imagick operations
     *
     * @return $this
     *
     * @throws ImageException
     */
    protected function process(string $action, int $quality = 100)
    {
        $this->image();
        $this->ensure_resource();
        try {
            switch ($action) {
                case 'resize':
                    $this->resource->resize_image($this->width, $this->height, Imagick::FILTER_LANCZOS, 0);
                    break;
                case 'crop':
                    $width = $this->width;
                    $height = $this->height;
                    $x_axis = $this->x_axis ?? 0;
                    $y_axis = $this->y_axis ?? 0;
                    $this->resource->crop_image($width, $height, $x_axis, $y_axis);
                    // Reset canvas to cropped size
                    $this->resource->set_image_page(0, 0, 0, 0);
                    break;
            }
            // Handle transparency for supported image types
            if (in_array($this->image()->image_type, $this->support_transparency, true) && $this->resource->get_image_alpha_channel() === Imagick::ALPHACHANNEL_UNDEFINED) {
                $this->resource->set_image_alpha_channel(Imagick::ALPHACHANNEL_OPAQUE);
            }
        } catch (Imagick_Exception) {
            throw Image_Exception::for_image_process_failed();
        }
        return $this;
    }
    /**
     * Handles the actual resizing of the image.
     *
     * @return ImageMagickHandler
     *
     * @throws ImagickException
     */
    public function _resize(bool $maintain_ratio = false)
    {
        if ($maintain_ratio) {
            // If maintaining a ratio, we need a custom approach
            $this->ensure_resource();
            // Use thumbnailImage which preserves an aspect ratio
            $this->resource->thumbnail_image($this->width, $this->height, true);
            return $this;
        }
        // Use the common process() method for normal resizing
        return $this->process('resize');
    }
    /**
     * Crops the image.
     *
     * @return $this
     *
     * @throws ImagickException
     */
    public function _crop()
    {
        // Use the common process() method for cropping
        $result = $this->process('crop');
        // Handle a case where crop dimensions exceed the original image size
        if ($this->resource instanceof Imagick) {
            $img_width = $this->resource->get_image_width();
            $img_height = $this->resource->get_image_height();
            if ($this->x_axis >= $img_width || $this->y_axis >= $img_height) {
                // Create transparent background
                $background = new Imagick();
                $background->new_image($this->width, $this->height, new Imagick_Pixel('transparent'));
                $background->set_image_format($this->resource->get_image_format());
                // Composite our image on the background
                $background->composite_image($this->resource, Imagick::COMPOSITE_OVER, 0, 0);
                // Replace our resource
                $this->resource = $background;
            }
        }
        return $result;
    }
    /**
     * Handles the rotation of an image resource.
     * Doesn't save the image, but replaces the current resource.
     *
     * @return $this
     *
     * @throws ImagickException
     */
    protected function _rotate(int $angle)
    {
        $this->ensure_resource();
        // Create transparent background
        $this->resource->set_image_background_color(new Imagick_Pixel('transparent'));
        $this->resource->rotate_image(new Imagick_Pixel('transparent'), $angle);
        // Reset canvas dimensions
        $this->resource->set_image_page($this->resource->get_image_width(), $this->resource->get_image_height(), 0, 0);
        return $this;
    }
    /**
     * Flattens transparencies, default white background
     *
     * @return $this
     *
     * @throws ImagickException|ImagickPixelException
     */
    protected function _flatten(int $red = 255, int $green = 255, int $blue = 255)
    {
        $this->ensure_resource();
        // Create background
        $bg = new Imagick_Pixel("rgb({$red},{$green},{$blue})");
        // Create a new canvas with the background color
        $canvas = new Imagick();
        $canvas->new_image($this->resource->get_image_width(), $this->resource->get_image_height(), $bg, $this->resource->get_image_format());
        // Composite our image on the background
        $canvas->composite_image($this->resource, Imagick::COMPOSITE_OVER, 0, 0);
        // Replace our resource with the flattened version
        $this->resource->clear();
        $this->resource = $canvas;
        return $this;
    }
    /**
     * Flips an image along its vertical or horizontal axis.
     *
     * @return $this
     *
     * @throws ImagickException
     */
    protected function _flip(string $direction)
    {
        $this->ensure_resource();
        if ($direction === 'horizontal') {
            $this->resource->flop_image();
        } else {
            $this->resource->flip_image();
        }
        return $this;
    }
    /**
     * Get a driver version
     *
     * @return string
     */
    public function get_version()
    {
        $version = Imagick::get_version();
        if (preg_match('/ImageMagick\s+(\d+\.\d+\.\d+)/', $version['versionString'], $matches)) {
            return $matches[1];
        }
        return '';
    }
    /**
     * Check if a given image format is supported
     *
     * @return void
     *
     * @throws ImageException
     */
    protected function supported_format_check()
    {
        if (!$this->resource instanceof Imagick) {
            return;
        }
        if ($this->image()->image_type === IMAGETYPE_WEBP && !in_array('WEBP', Imagick::query_formats(), true)) {
            throw Image_Exception::for_invalid_image_create(lang('images.webpNotSupported'));
        }
    }
    /**
     * Saves any changes that have been made to the file. If no new filename is
     * provided, the existing image is overwritten; otherwise a copy of the
     * file is made at $target.
     *
     * Example:
     *    $image->resize(100, 200, true)
     *          ->save();
     *
     * @param non-empty-string|null $target
     *
     * @throws ImagickException
     */
    public function save(?string $target = null, int $quality = 90): bool
    {
        $original = $target;
        $target = $target === null || $target === '' ? $this->image()->get_pathname() : $target;
        // If no new resource has been created, then we're
        // simply copy the existing one.
        if (!$this->resource instanceof Imagick && $quality === 100) {
            if ($original === null) {
                return true;
            }
            $name = basename($target);
            $path = pathinfo($target, PATHINFO_DIRNAME);
            return $this->image()->copy($path, $name);
        }
        $this->ensure_resource();
        $this->resource->set_image_compression_quality($quality);
        if ($target !== null) {
            $extension = pathinfo($target, PATHINFO_EXTENSION);
            $this->resource->set_image_format($extension);
        }
        try {
            $result = $this->resource->write_image($target);
            chmod($target, $this->file_permissions);
            $this->resource->clear();
            $this->resource = null;
            return $result;
        } catch (Imagick_Exception) {
            throw Image_Exception::for_save_failed();
        }
    }
    /**
     * Handler-specific method for overlaying text on an image.
     *
     * @throws ImagickDrawException|ImagickException|ImagickPixelException
     */
    protected function _text(string $text, array $options = [])
    {
        $this->ensure_resource();
        $draw = new Imagick_Draw();
        if (isset($options['fontPath'])) {
            $draw->set_font($options['fontPath']);
        }
        if (isset($options['fontSize'])) {
            $draw->set_font_size($options['fontSize']);
        }
        if (isset($options['color'])) {
            $color = $options['color'];
            // Shorthand hex, #f00
            if (strlen($color) === 3) {
                $color = implode('', array_map(str_repeat(...), str_split($color), [2, 2, 2]));
            }
            [$r, $g, $b] = sscanf("#{$color}", '#%02x%02x%02x');
            $opacity = $options['opacity'] ?? 1.0;
            $draw->set_fill_color(new Imagick_Pixel("rgba({$r},{$g},{$b},{$opacity})"));
        }
        // Calculate text positioning
        $img_width = $this->resource->get_image_width();
        $img_height = $this->resource->get_image_height();
        $x_axis = 0;
        $y_axis = 0;
        // Default padding
        $padding = $options['padding'] ?? 0;
        if (isset($options['hAlign'])) {
            $h_offset = $options['hOffset'] ?? 0;
            switch ($options['hAlign']) {
                case 'left':
                    $x_axis = $h_offset + $padding;
                    $draw->set_text_alignment(Imagick::ALIGN_LEFT);
                    break;
                case 'center':
                    $x_axis = $img_width / 2 + $h_offset;
                    $draw->set_text_alignment(Imagick::ALIGN_CENTER);
                    break;
                case 'right':
                    $x_axis = $img_width - $h_offset - $padding;
                    $draw->set_text_alignment(Imagick::ALIGN_RIGHT);
                    break;
            }
        }
        if (isset($options['vAlign'])) {
            $v_offset = $options['vOffset'] ?? 0;
            switch ($options['vAlign']) {
                case 'top':
                    $y_axis = $v_offset + $padding + ($options['fontSize'] ?? 16);
                    break;
                case 'middle':
                    $y_axis = $img_height / 2 + $v_offset;
                    break;
                case 'bottom':
                    // Note: Vertical offset is inverted for bottom alignment as per original implementation
                    $y_axis = $v_offset < 0 ? $img_height + $v_offset - $padding : $img_height - $v_offset - $padding;
                    break;
            }
        }
        if (isset($options['withShadow'])) {
            $shadow = clone $draw;
            if (isset($options['shadowColor'])) {
                $shadow_color = $options['shadowColor'];
                // Shorthand hex, #f00
                if (strlen($shadow_color) === 3) {
                    $shadow_color = implode('', array_map(str_repeat(...), str_split($shadow_color), [2, 2, 2]));
                }
                [$sr, $sg, $sb] = sscanf("#{$shadow_color}", '#%02x%02x%02x');
                $shadow->set_fill_color(new Imagick_Pixel("rgb({$sr},{$sg},{$sb})"));
            } else {
                $shadow->set_fill_color(new Imagick_Pixel('rgba(0,0,0,0.5)'));
            }
            $offset = $options['shadowOffset'] ?? 3;
            $this->resource->annotate_image($shadow, $x_axis + $offset, $y_axis + $offset, 0, $text);
        }
        // Draw the main text
        $this->resource->annotate_image($draw, $x_axis, $y_axis, 0, $text);
    }
    /**
     * Return the width of an image.
     *
     * @return int
     *
     * @throws ImagickException
     */
    public function _get_width()
    {
        $this->ensure_resource();
        return $this->resource->get_image_width();
    }
    /**
     * Return the height of an image.
     *
     * @return int
     *
     * @throws ImagickException
     */
    public function _get_height()
    {
        $this->ensure_resource();
        return $this->resource->get_image_height();
    }
    /**
     * Reads the EXIF information from the image and modifies the orientation
     * so that displays correctly in the browser. This is especially an issue
     * with images taken by smartphones who always store the image up-right,
     * but set the orientation flag to display it correctly.
     *
     * @param bool $silent If true, will ignore exceptions when PHP doesn't support EXIF.
     *
     * @return $this
     */
    public function reorient(bool $silent = false)
    {
        $orientation = $this->get_exif('Orientation', $silent);
        return match ($orientation) {
            2 => $this->flip('horizontal'),
            3 => $this->rotate(180),
            4 => $this->rotate(180)->flip('horizontal'),
            5 => $this->rotate(90)->flip('horizontal'),
            6 => $this->rotate(90),
            7 => $this->rotate(270)->flip('horizontal'),
            8 => $this->rotate(270),
            default => $this,
        };
    }
    /**
     * Clears metadata from the image.
     *
     * @return $this
     *
     * @throws ImagickException
     */
    public function clear_metadata(): static
    {
        $this->ensure_resource();
        $this->resource->strip_image();
        return $this;
    }
}