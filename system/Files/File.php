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
namespace Code_Igniter\Files;

use Code_Igniter\Files\Exceptions\File_Exception;
use Code_Igniter\Files\Exceptions\File_Not_Found_Exception;
use Code_Igniter\I18n\Time;
use Config\Mimes;
use RuntimeException;
use Spl_File_Info;
/**
 * Wrapper for PHP's built-in SplFileInfo, with goodies.
 *
 * @see \CodeIgniter\Files\FileTest
 */
class File extends Spl_File_Info
{
    /**
     * The files size in bytes
     *
     * @var int
     */
    protected $size;
    /**
     * @var string|null
     */
    protected $original_mime_type;
    /**
     * Run our SplFileInfo constructor with an optional verification
     * that the path is really a file.
     *
     * @throws FileNotFoundException
     */
    public function __construct(string $path, bool $check_file = false)
    {
        if ($check_file && !is_file($path)) {
            throw File_Not_Found_Exception::for_file_not_found($path);
        }
        parent::__construct($path);
    }
    /**
     * Retrieve the file size.
     *
     * Implementations SHOULD return the value stored in the "size" key of
     * the file in the $_FILES array if available, as PHP calculates this based
     * on the actual size transmitted.
     *
     * @throws RuntimeException if the file does not exist or an error occurs
     */
    public function get_size(): false|int
    {
        return $this->size ?? $this->size = parent::get_size();
    }
    /**
     * Retrieve the file size by unit, calculated in IEC standards with 1024 as base value.
     *
     * @param positive-int $precision
     */
    public function get_size_by_binary_unit(File_Size_Unit $unit = File_Size_Unit::B, int $precision = 3): int|string
    {
        return $this->get_size_by_unit_internal(1024, $unit, $precision);
    }
    /**
     * Retrieve the file size by unit, calculated in metric standards with 1000 as base value.
     *
     * @param positive-int $precision
     */
    public function get_size_by_metric_unit(File_Size_Unit $unit = File_Size_Unit::B, int $precision = 3): int|string
    {
        return $this->get_size_by_unit_internal(1000, $unit, $precision);
    }
    /**
     * Retrieve the file size by unit.
     *
     * @deprecated 4.6.0 Use getSizeByBinaryUnit() or getSizeByMetricUnit() instead
     *
     * @return false|int|string
     */
    public function get_size_by_unit(string $unit = 'b')
    {
        return match (strtolower($unit)) {
            'kb' => $this->get_size_by_binary_unit(File_Size_Unit::KB),
            'mb' => $this->get_size_by_binary_unit(File_Size_Unit::MB),
            default => $this->get_size(),
        };
    }
    /**
     * Attempts to determine the file extension based on the trusted
     * getType() method. If the mime type is unknown, will return null.
     */
    public function guess_extension(): ?string
    {
        // naively get the path extension using pathinfo
        $pathinfo = pathinfo($this->get_real_path() ?: $this->__toString()) + ['extension' => ''];
        $proposed_extension = $pathinfo['extension'];
        return Mimes::guess_extension_from_type($this->get_mime_type(), $proposed_extension);
    }
    /**
     * Retrieve the media type of the file. SHOULD not use information from
     * the $_FILES array, but should use other methods to more accurately
     * determine the type of file, like finfo, or mime_content_type().
     *
     * @return string The media type we determined it to be.
     */
    public function get_mime_type(): string
    {
        if (!function_exists('finfo_open')) {
            return $this->original_mime_type ?? 'application/octet-stream';
            // @codeCoverageIgnore
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($finfo, $this->get_real_path() ?: $this->__toString());
    }
    /**
     * Generates a random names based on a simple hash and the time, with
     * the correct file extension attached.
     */
    public function get_random_name(): string
    {
        $extension = $this->get_extension();
        $extension = empty($extension) ? '' : '.' . $extension;
        return Time::now()->get_timestamp() . '_' . bin2hex(random_bytes(10)) . $extension;
    }
    /**
     * Moves a file to a new location.
     *
     * @return File
     */
    public function move(string $target_path, ?string $name = null, bool $overwrite = false)
    {
        $target_path = rtrim($target_path, '/') . '/';
        $name ??= $this->get_basename();
        $destination = $overwrite ? $target_path . $name : $this->get_destination($target_path . $name);
        $old_name = $this->get_real_path() ?: $this->__toString();
        if (!@rename($old_name, $destination)) {
            $error = error_get_last();
            throw File_Exception::for_unable_to_move($this->get_basename(), $target_path, strip_tags($error['message']));
        }
        @chmod($destination, 0777 & ~umask());
        return new self($destination);
    }
    /**
     * Returns the destination path for the move operation where overwriting is not expected.
     *
     * First, it checks whether the delimiter is present in the filename, if it is, then it checks whether the
     * last element is an integer as there may be cases that the delimiter may be present in the filename.
     * For the all other cases, it appends an integer starting from zero before the file's extension.
     */
    public function get_destination(string $destination, string $delimiter = '_', int $i = 0): string
    {
        if ($delimiter === '') {
            $delimiter = '_';
        }
        while (is_file($destination)) {
            $info = pathinfo($destination);
            $extension = isset($info['extension']) ? '.' . $info['extension'] : '';
            if (str_contains($info['filename'], $delimiter)) {
                $parts = explode($delimiter, $info['filename']);
                if (is_numeric(end($parts))) {
                    $i = end($parts);
                    array_pop($parts);
                    $parts[] = ++$i;
                    $destination = $info['dirname'] . DIRECTORY_SEPARATOR . implode($delimiter, $parts) . $extension;
                } else {
                    $destination = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . $delimiter . ++$i . $extension;
                }
            } else {
                $destination = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . $delimiter . ++$i . $extension;
            }
        }
        return $destination;
    }
    private function get_size_by_unit_internal(int $file_size_base, File_Size_Unit $unit, int $precision): int|string
    {
        $exponent = $unit->value;
        $divider = $file_size_base ** $exponent;
        $size = $this->get_size() / $divider;
        if ($unit !== File_Size_Unit::B) {
            $size = number_format($size, $precision);
        }
        return $size;
    }
}