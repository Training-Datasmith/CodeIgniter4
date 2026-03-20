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
namespace Code_Igniter\HTTP\Files;

use Code_Igniter\Files\File;
use Code_Igniter\HTTP\Exceptions\Http_Exception;
use Config\Mimes;
use Exception;
/**
 * Value object representing a single file uploaded through an
 * HTTP request. Used by the IncomingRequest class to
 * provide files.
 *
 * Typically, implementors will extend the SplFileInfo class.
 */
class Uploaded_File extends File implements Uploaded_File_Interface
{
    /**
     * The path to the temporary file.
     *
     * @var string
     */
    protected $path;
    /**
     * The webkit relative path of the file.
     *
     * @var string
     */
    protected $client_path;
    /**
     * The original filename as provided by the client.
     *
     * @var string
     */
    protected $original_name;
    /**
     * The filename given to a file during a move.
     *
     * @var string
     */
    protected $name;
    /**
     * The type of file as provided by PHP
     *
     * @var string
     */
    protected $original_mime_type;
    /**
     * The error constant of the upload
     * (one of PHP's UPLOADERRXXX constants)
     *
     * @var int
     */
    protected $error;
    /**
     * Whether the file has been moved already or not.
     *
     * @var bool
     */
    protected $has_moved = false;
    /**
     * Accepts the file information as would be filled in from the $_FILES array.
     *
     * @param string      $path         The temporary location of the uploaded file.
     * @param string      $originalName The client-provided filename.
     * @param string|null $mimeType     The type of file as provided by PHP
     * @param int|null    $size         The size of the file, in bytes
     * @param int|null    $error        The error constant of the upload (one of PHP's UPLOADERRXXX constants)
     * @param string|null $clientPath   The webkit relative path of the uploaded file.
     */
    public function __construct(string $path, string $original_name, ?string $mime_type = null, ?int $size = null, ?int $error = null, ?string $client_path = null)
    {
        $this->path = $path;
        $this->name = $original_name;
        $this->original_name = $original_name;
        $this->original_mime_type = $mime_type;
        $this->size = $size;
        $this->error = $error;
        $this->client_path = $client_path;
        parent::__construct($path, false);
    }
    /**
     * Move the uploaded file to a new location.
     *
     * $targetPath may be an absolute path, or a relative path. If it is a
     * relative path, resolution should be the same as used by PHP's rename()
     * function.
     *
     * The original file MUST be removed on completion.
     *
     * If this method is called more than once, any subsequent calls MUST raise
     * an exception.
     *
     * When used in an SAPI environment where $_FILES is populated, when writing
     * files via moveTo(), is_uploaded_file() and move_uploaded_file() SHOULD be
     * used to ensure permissions and upload status are verified correctly.
     *
     * If you wish to move to a stream, use getStream(), as SAPI operations
     * cannot guarantee writing to stream destinations.
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     *
     * @param string      $targetPath Path to which to move the uploaded file.
     * @param string|null $name       the name to rename the file to.
     * @param bool        $overwrite  State for indicating whether to overwrite the previously generated file with the same
     *                                name or not.
     *
     * @return bool
     */
    public function move(string $target_path, ?string $name = null, bool $overwrite = false)
    {
        $target_path = rtrim($target_path, '/') . '/';
        $target_path = $this->set_path($target_path);
        // set the target path
        if ($this->has_moved) {
            throw Http_Exception::for_already_moved();
        }
        if (!$this->is_valid()) {
            throw Http_Exception::for_invalid_file();
        }
        $name ??= $this->get_name();
        $destination = $overwrite ? $target_path . $name : $this->get_destination($target_path . $name);
        try {
            $this->has_moved = move_uploaded_file($this->path, $destination);
        } catch (Exception) {
            $error = error_get_last();
            $message = strip_tags($error['message'] ?? '');
            throw Http_Exception::for_move_failed(basename($this->path), $target_path, $message);
        }
        if ($this->has_moved === false) {
            $message = 'move_uploaded_file() returned false';
            throw Http_Exception::for_move_failed(basename($this->path), $target_path, $message);
        }
        @chmod($target_path, 0777 & ~umask());
        // Success, so store our new information
        $this->path = $target_path;
        $this->name = basename($destination);
        return true;
    }
    /**
     * create file target path if
     * the set path does not exist
     *
     * @return string The path set or created.
     */
    protected function set_path(string $path): string
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            // create the index.html file
            if (!is_file($path . 'index.html')) {
                $file = fopen($path . 'index.html', 'x+b');
                fclose($file);
            }
        }
        return $path;
    }
    /**
     * Returns whether the file has been moved or not. If it has,
     * the move() method will not work and certain properties, like
     * the tempName, will no longer be available.
     */
    public function has_moved(): bool
    {
        return $this->has_moved;
    }
    /**
     * Retrieve the error associated with the uploaded file.
     *
     * The return value MUST be one of PHP's UPLOAD_ERR_XXX constants.
     *
     * If the file was uploaded successfully, this method MUST return
     * UPLOAD_ERR_OK.
     *
     * Implementations SHOULD return the value stored in the "error" key of
     * the file in the $_FILES array.
     *
     * @see    http://php.net/manual/en/features.file-upload.errors.php
     *
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     */
    public function get_error(): int
    {
        return $this->error ?? UPLOAD_ERR_OK;
    }
    /**
     * Get error string
     */
    public function get_error_string(): string
    {
        $errors = [UPLOAD_ERR_OK => lang('HTTP.uploadErrOk'), UPLOAD_ERR_INI_SIZE => lang('HTTP.uploadErrIniSize'), UPLOAD_ERR_FORM_SIZE => lang('HTTP.uploadErrFormSize'), UPLOAD_ERR_PARTIAL => lang('HTTP.uploadErrPartial'), UPLOAD_ERR_NO_FILE => lang('HTTP.uploadErrNoFile'), UPLOAD_ERR_CANT_WRITE => lang('HTTP.uploadErrCantWrite'), UPLOAD_ERR_NO_TMP_DIR => lang('HTTP.uploadErrNoTmpDir'), UPLOAD_ERR_EXTENSION => lang('HTTP.uploadErrExtension')];
        $error = $this->error ?? UPLOAD_ERR_OK;
        return sprintf($errors[$error] ?? lang('HTTP.uploadErrUnknown'), $this->get_name());
    }
    /**
     * Returns the mime type as provided by the client.
     * This is NOT a trusted value.
     * For a trusted version, use getMimeType() instead.
     *
     * @return string The media type sent by the client or null if none was provided.
     */
    public function get_client_mime_type(): string
    {
        return $this->original_mime_type;
    }
    /**
     * Retrieve the filename. This will typically be the filename sent
     * by the client, and should not be trusted. If the file has been
     * moved, this will return the final name of the moved file.
     *
     * @return string The filename sent by the client or null if none was provided.
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Returns the name of the file as provided by the client during upload.
     */
    public function get_client_name(): string
    {
        return $this->original_name;
    }
    /**
     * (PHP 8.1+)
     * Returns the webkit relative path of the uploaded file on directory uploads.
     */
    public function get_client_path(): ?string
    {
        return $this->client_path;
    }
    /**
     * Gets the temporary filename where the file was uploaded to.
     */
    public function get_temp_name(): string
    {
        return $this->path;
    }
    /**
     * Overrides SPLFileInfo's to work with uploaded files, since
     * the temp file that's been uploaded doesn't have an extension.
     *
     * This method tries to guess the extension from the files mime
     * type but will return the clientExtension if it fails to do so.
     *
     * This method will always return a more or less helpfull extension
     * but might be insecure if the mime type is not matched. Consider
     * using guessExtension for a more safe version.
     */
    public function get_extension(): string
    {
        $guess_extension = $this->guess_extension();
        return $guess_extension !== '' ? $guess_extension : $this->get_client_extension();
    }
    /**
     * Attempts to determine the best file extension from the file's
     * mime type. In contrast to getExtension, this method will return
     * an empty string if it fails to determine an extension instead of
     * falling back to the unsecure clientExtension.
     */
    public function guess_extension(): string
    {
        return Mimes::guess_extension_from_type($this->get_mime_type(), $this->get_client_extension()) ?? '';
    }
    /**
     * Returns the original file extension, based on the file name that
     * was uploaded. This is NOT a trusted source.
     * For a trusted version, use guessExtension() instead.
     */
    public function get_client_extension(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }
    /**
     * Returns whether the file was uploaded successfully, based on whether
     * it was uploaded via HTTP and has no errors.
     */
    public function is_valid(): bool
    {
        return is_uploaded_file($this->path) && $this->error === UPLOAD_ERR_OK;
    }
    /**
     * Save the uploaded file to a new location.
     *
     * By default, upload files are saved in writable/uploads directory. The YYYYMMDD folder
     * and random file name will be created.
     *
     * @param string|null $folderName the folder name to writable/uploads directory.
     * @param string|null $fileName   the name to rename the file to.
     *
     * @return string file full path
     */
    public function store(?string $folder_name = null, ?string $file_name = null): string
    {
        $folder_name = rtrim($folder_name ?? date('Ymd'), '/') . '/';
        $file_name ??= $this->get_random_name();
        // Move the uploaded file to a new location.
        $this->move(WRITEPATH . 'uploads/' . $folder_name, $file_name);
        return $folder_name . $this->name;
    }
}