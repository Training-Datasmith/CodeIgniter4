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

use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Images\Exceptions\Image_Exception;
use Code_Igniter\Images\Image;
use Code_Igniter\Images\Image_Handler_Interface;
use Config\Images;
/**
 * Base image handling implementation
 */
abstract class Base_Handler implements Image_Handler_Interface
{
    /**
     * Configuration settings.
     *
     * @var Images
     */
    protected $config;
    /**
     * The image/file instance
     *
     * @var Image|null
     */
    protected $image;
    /**
     * Whether the image file has been confirmed.
     *
     * @var bool
     */
    protected $verified = false;
    /**
     * Image width.
     *
     * @var int
     */
    protected $width = 0;
    /**
     * Image height.
     *
     * @var int
     */
    protected $height = 0;
    /**
     * File permission mask.
     *
     * @var int
     */
    protected $file_permissions = 0644;
    /**
     * X-axis.
     *
     * @var int|null
     */
    protected $x_axis = 0;
    /**
     * Y-axis.
     *
     * @var int|null
     */
    protected $y_axis = 0;
    /**
     * Master dimensioning.
     *
     * @var string
     */
    protected $master_dim = 'auto';
    /**
     * Default options for text watermarking.
     *
     * @var array
     */
    protected $text_defaults = ['fontPath' => null, 'fontSize' => 16, 'color' => 'ffffff', 'opacity' => 1.0, 'vAlign' => 'bottom', 'hAlign' => 'center', 'vOffset' => 0, 'hOffset' => 0, 'padding' => 0, 'withShadow' => false, 'shadowColor' => '000000', 'shadowOffset' => 3];
    /**
     * Image types with support for transparency.
     *
     * @var array
     */
    protected $support_transparency = [IMAGETYPE_PNG, IMAGETYPE_WEBP];
    /**
     * Temporary image used by the different engines.
     *
     * @var resource|null
     */
    protected $resource;
    /**
     * Constructor.
     *
     * @param Images|null $config
     */
    public function __construct($config = null)
    {
        $this->config = $config ?? new Images();
    }
    /**
     * Sets another image for this handler to work on.
     * Keeps us from needing to continually instantiate the handler.
     *
     * @phpstan-assert Image $this->image
     *
     * @return $this
     */
    public function with_file(string $path)
    {
        // Clear out the old resource so that
        // it doesn't try to use a previous image
        $this->resource = null;
        $this->verified = false;
        $this->image = new Image($path, true);
        $this->image->get_properties(false);
        $this->width = $this->image->orig_width;
        $this->height = $this->image->orig_height;
        return $this;
    }
    /**
     * Make the image resource object if needed
     *
     * @return void
     */
    abstract protected function ensure_resource();
    /**
     * Returns the image instance.
     *
     * @return Image
     */
    public function get_file()
    {
        return $this->image;
    }
    /**
     * Verifies that a file has been supplied and it is an image.
     *
     * @phpstan-assert Image $this->image
     *
     * @throws ImageException
     */
    protected function image(): Image
    {
        if ($this->verified) {
            return $this->image;
        }
        // Verify withFile has been called
        if ($this->image === null) {
            throw Image_Exception::for_missing_image();
        }
        // Verify the loaded image is an Image instance
        if (!$this->image instanceof Image) {
            throw Image_Exception::for_invalid_path();
        }
        // File::__construct has verified the file exists - make sure it is an image
        if (!is_int($this->image->image_type)) {
            throw Image_Exception::for_file_not_supported();
        }
        // Note that the image has been verified
        $this->verified = true;
        return $this->image;
    }
    /**
     * Returns the temporary image used during the image processing.
     * Good for extending the system or doing things this library
     * is not intended to do.
     *
     * @return resource
     */
    public function get_resource()
    {
        $this->ensure_resource();
        return $this->resource;
    }
    /**
     * Load the temporary image used during the image processing.
     * Some functions e.g. save() will only copy and not compress
     * your image otherwise.
     *
     * @return $this
     */
    public function with_resource()
    {
        $this->ensure_resource();
        return $this;
    }
    /**
     * Resize the image
     *
     * @param bool $maintainRatio If true, will get the closest match possible while keeping aspect ratio true.
     *
     * @return BaseHandler
     */
    public function resize(int $width, int $height, bool $maintain_ratio = false, string $master_dim = 'auto')
    {
        // If the target width/height match the source, then we have nothing to do here.
        if ($this->image()->orig_width === $width && $this->image()->orig_height === $height) {
            return $this;
        }
        $this->width = $width;
        $this->height = $height;
        if ($maintain_ratio) {
            $this->master_dim = $master_dim;
            $this->reproportion();
        }
        return $this->_resize($maintain_ratio);
    }
    /**
     * Crops the image to the desired height and width. If one of the height/width values
     * is not provided, that value will be set the appropriate value based on offsets and
     * image dimensions.
     *
     * @param int|null $x X-axis coord to start cropping from the left of image
     * @param int|null $y Y-axis coord to start cropping from the top of image
     *
     * @return $this
     */
    public function crop(?int $width = null, ?int $height = null, ?int $x = null, ?int $y = null, bool $maintain_ratio = false, string $master_dim = 'auto')
    {
        $this->width = $width;
        $this->height = $height;
        $this->x_axis = $x;
        $this->y_axis = $y;
        if ($maintain_ratio) {
            $this->master_dim = $master_dim;
            $this->reproportion();
        }
        $result = $this->_crop();
        $this->x_axis = null;
        $this->y_axis = null;
        return $result;
    }
    /**
     * Changes the stored image type to indicate the new file format to use when saving.
     * Does not touch the actual resource.
     *
     * @param int $imageType A PHP imageType constant, e.g. https://www.php.net/manual/en/function.image-type-to-mime-type.php
     *
     * @return $this
     */
    public function convert(int $image_type)
    {
        $this->ensure_resource();
        $this->image()->image_type = $image_type;
        return $this;
    }
    /**
     * Rotates the image on the current canvas.
     *
     * @return $this
     */
    public function rotate(float $angle)
    {
        // Allowed rotation values
        $degs = [90.0, 180.0, 270.0];
        if (!in_array($angle, $degs, true)) {
            throw Image_Exception::for_missing_angle();
        }
        // cast angle as an int, for our use
        $angle = (int) $angle;
        // Reassign the width and height
        if ($angle === 90 || $angle === 270) {
            $temp = $this->height;
            $this->width = $this->height;
            $this->height = $temp;
        }
        // Call the Handler-specific version.
        $this->_rotate($angle);
        return $this;
    }
    /**
     * Flattens transparencies, default white background
     *
     * @return $this
     */
    public function flatten(int $red = 255, int $green = 255, int $blue = 255)
    {
        $this->width = $this->image()->orig_width;
        $this->height = $this->image()->orig_height;
        return $this->_flatten($red, $green, $blue);
    }
    /**
     * Handler-specific method to flattening an image's transparencies.
     *
     * @return $this
     *
     * @internal
     */
    abstract protected function _flatten(int $red = 255, int $green = 255, int $blue = 255);
    /**
     * Handler-specific method to handle rotating an image in 90 degree increments.
     *
     * @return mixed
     */
    abstract protected function _rotate(int $angle);
    /**
     * Flips an image either horizontally or vertically.
     *
     * @param string $dir Either 'vertical' or 'horizontal'
     *
     * @return $this
     */
    public function flip(string $dir = 'vertical')
    {
        $dir = strtolower($dir);
        if ($dir !== 'vertical' && $dir !== 'horizontal') {
            throw Image_Exception::for_invalid_direction($dir);
        }
        return $this->_flip($dir);
    }
    /**
     * Handler-specific method to handle flipping an image along its
     * horizontal or vertical axis.
     *
     * @return $this
     */
    abstract protected function _flip(string $direction);
    public function text(string $text, array $options = [])
    {
        $options = array_merge($this->text_defaults, $options);
        $options['color'] = trim($options['color'], '# ');
        $options['shadowColor'] = trim($options['shadowColor'], '# ');
        $this->_text($text, $options);
        return $this;
    }
    /**
     * Handler-specific method for overlaying text on an image.
     *
     * @param array{
     *     color?: string,
     *     shadowColor?: string,
     *     hAlign?: string,
     *     vAlign?: string,
     *     hOffset?: int,
     *     vOffset?: int,
     *     fontPath?: string,
     *     fontSize?: int,
     *     shadowOffset?: int,
     *     opacity?: float,
     *     padding?: int,
     *     withShadow?: bool|string
     * } $options
     *
     * @return void
     */
    abstract protected function _text(string $text, array $options = []);
    /**
     * Handles the actual resizing of the image.
     *
     * @return $this
     */
    abstract public function _resize(bool $maintain_ratio = false);
    /**
     * Crops the image.
     *
     * @return $this
     */
    abstract public function _crop();
    /**
     * Return image width.
     *
     * @return int
     */
    abstract public function _get_width();
    /**
     * Return the height of an image.
     *
     * @return int
     */
    abstract public function _get_height();
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
            5 => $this->rotate(270)->flip('horizontal'),
            6 => $this->rotate(270),
            7 => $this->rotate(90)->flip('horizontal'),
            8 => $this->rotate(90),
            default => $this,
        };
    }
    /**
     * Retrieve the EXIF information from the image, if possible. Returns
     * an array of the information, or null if nothing can be found.
     *
     * EXIF data is only supported fr JPEG & TIFF formats.
     *
     * @param string|null $key    If specified, will only return this piece of EXIF data.
     * @param bool        $silent If true, will not throw our own exceptions.
     *
     * @return mixed
     *
     * @throws ImageException
     */
    public function get_exif(?string $key = null, bool $silent = false)
    {
        if (!function_exists('exif_read_data')) {
            if ($silent) {
                return null;
            }
            throw Image_Exception::for_exif_unsupported();
            // @codeCoverageIgnore
        }
        $exif = null;
        // default
        switch ($this->image()->image_type) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_TIFF_II:
                $exif = @exif_read_data($this->image()->get_pathname());
                if ($key !== null && is_array($exif)) {
                    $exif = $exif[$key] ?? false;
                }
        }
        return $exif;
    }
    /**
     * Combine cropping and resizing into a single command.
     *
     * Supported positions:
     *  - top-left
     *  - top
     *  - top-right
     *  - left
     *  - center
     *  - right
     *  - bottom-left
     *  - bottom
     *  - bottom-right
     *
     * @return BaseHandler
     */
    public function fit(int $width, ?int $height = null, string $position = 'center')
    {
        $orig_width = $this->image()->orig_width;
        $orig_height = $this->image()->orig_height;
        [$crop_width, $crop_height] = $this->calc_aspect_ratio($width, $height, $orig_width, $orig_height);
        if ($height === null) {
            $height = (int) ceil($width / $crop_width * $crop_height);
        }
        [$x, $y] = $this->calc_crop_coords($crop_width, $crop_height, $orig_width, $orig_height, $position);
        return $this->crop($crop_width, $crop_height, (int) $x, (int) $y)->resize($width, $height);
    }
    /**
     * Calculate image aspect ratio.
     *
     * @param float|int      $width
     * @param float|int|null $height
     * @param float|int      $origWidth
     * @param float|int      $origHeight
     */
    protected function calc_aspect_ratio($width, $height = null, $orig_width = 0, $orig_height = 0): array
    {
        if (empty($orig_width) || empty($orig_height)) {
            throw new InvalidArgumentException('You must supply the parameters: origWidth, origHeight.');
        }
        // If $height is null, then we have it easy.
        // Calc based on full image size and be done.
        if ($height === null) {
            $height = $width / $orig_width * $orig_height;
            return [$width, (int) $height];
        }
        $x_ratio = $width / $orig_width;
        $y_ratio = $height / $orig_height;
        if ($x_ratio > $y_ratio) {
            return [$orig_width, (int) ($orig_width * $height / $width)];
        }
        return [(int) ($orig_height * $width / $height), $orig_height];
    }
    /**
     * Based on the position, will determine the correct x/y coords to
     * crop the desired portion from the image.
     *
     * @param float|int $width
     * @param float|int $height
     * @param float|int $origWidth
     * @param float|int $origHeight
     * @param string    $position
     */
    protected function calc_crop_coords($width, $height, $orig_width, $orig_height, $position): array
    {
        $position = strtolower($position);
        $x = $y = 0;
        switch ($position) {
            case 'top-left':
                $x = 0;
                $y = 0;
                break;
            case 'top':
                $x = floor(($orig_width - $width) / 2);
                $y = 0;
                break;
            case 'top-right':
                $x = $orig_width - $width;
                $y = 0;
                break;
            case 'left':
                $x = 0;
                $y = floor(($orig_height - $height) / 2);
                break;
            case 'center':
                $x = floor(($orig_width - $width) / 2);
                $y = floor(($orig_height - $height) / 2);
                break;
            case 'right':
                $x = $orig_width - $width;
                $y = floor(($orig_height - $height) / 2);
                break;
            case 'bottom-left':
                $x = 0;
                $y = $orig_height - $height;
                break;
            case 'bottom':
                $x = floor(($orig_width - $width) / 2);
                $y = $orig_height - $height;
                break;
            case 'bottom-right':
                $x = $orig_width - $width;
                $y = $orig_height - $height;
                break;
        }
        return [$x, $y];
    }
    /**
     * Get the version of the image library in use.
     *
     * @return string
     */
    abstract public function get_version();
    /**
     * Saves any changes that have been made to file.
     *
     * Example:
     *    $image->resize(100, 200, true)
     *          ->save($target);
     *
     * @param non-empty-string|null $target
     *
     * @return bool
     */
    abstract public function save(?string $target = null, int $quality = 90);
    /**
     * Does the driver-specific processing of the image.
     *
     * @return mixed
     */
    abstract protected function process(string $action);
    /**
     * Provide access to the Image class' methods if they don't exist
     * on the handler itself.
     *
     * @return mixed
     */
    public function __call(string $name, array $args = [])
    {
        if (method_exists($this->image(), $name)) {
            return $this->image()->{$name}(...$args);
        }
        return null;
    }
    /**
     * Re-proportion Image Width/Height
     *
     * When creating thumbs, the desired width/height
     * can end up warping the image due to an incorrect
     * ratio between the full-sized image and the thumb.
     *
     * This function lets us re-proportion the width/height
     * if users choose to maintain the aspect ratio when resizing.
     *
     * @return void
     */
    protected function reproportion()
    {
        if ($this->width === 0 && $this->height === 0 || $this->image()->orig_width === 0 || $this->image()->orig_height === 0 || !ctype_digit((string) $this->width) && !ctype_digit((string) $this->height) || !ctype_digit((string) $this->image()->orig_width) || !ctype_digit((string) $this->image()->orig_height)) {
            return;
        }
        // Sanitize
        $this->width = (int) $this->width;
        $this->height = (int) $this->height;
        if ($this->master_dim !== 'width' && $this->master_dim !== 'height') {
            if ($this->width > 0 && $this->height > 0) {
                $this->master_dim = $this->image()->orig_height / $this->image()->orig_width - $this->height / $this->width < 0 ? 'width' : 'height';
            } else {
                $this->master_dim = $this->height === 0 ? 'width' : 'height';
            }
        } elseif ($this->master_dim === 'width' && $this->width === 0 || $this->master_dim === 'height' && $this->height === 0) {
            return;
        }
        if ($this->master_dim === 'width') {
            $this->height = (int) ceil($this->width * $this->image()->orig_height / $this->image()->orig_width);
        } else {
            $this->width = (int) ceil($this->image()->orig_width * $this->height / $this->image()->orig_height);
        }
    }
    /**
     * Return image width.
     *
     * accessor for testing; not part of interface
     *
     * @return int
     */
    public function get_width()
    {
        return $this->resource !== null ? $this->_get_width() : $this->width;
    }
    /**
     * Return image height.
     *
     * accessor for testing; not part of interface
     *
     * @return int
     */
    public function get_height()
    {
        return $this->resource !== null ? $this->_get_height() : $this->height;
    }
    /**
     * Placeholder method for implementing metadata clearing logic.
     *
     * This method should be implemented to remove or reset metadata as needed.
     */
    public function clear_metadata(): static
    {
        return $this;
    }
}