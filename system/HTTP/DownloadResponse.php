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
namespace Code_Igniter\HTTP;

use Code_Igniter\Exceptions\Download_Exception;
use Code_Igniter\Files\File;
use Config\App;
use Config\Mimes;
/**
 * HTTP response when a download is requested.
 *
 * @see \CodeIgniter\HTTP\DownloadResponseTest
 */
class Download_Response extends Response
{
    /**
     * Download file name
     */
    private string $filename;
    /**
     * Download for file
     */
    private ?File $file = null;
    /**
     * mime set flag
     */
    private readonly bool $set_mime;
    /**
     * Download for binary
     */
    private ?string $binary = null;
    /**
     * Download charset
     */
    private string $charset = 'UTF-8';
    /**
     * Download reason
     *
     * @var string
     */
    protected $reason = 'OK';
    /**
     * The current status code for this response.
     *
     * @var int
     */
    protected $status_code = 200;
    /**
     * Constructor.
     */
    public function __construct(string $filename, bool $set_mime)
    {
        parent::__construct(config(App::class));
        $this->filename = $filename;
        $this->set_mime = $set_mime;
        // Make sure the content type is either specified or detected
        $this->remove_header('Content-Type');
    }
    /**
     * set download for binary string.
     *
     * @return void
     */
    public function set_binary(string $binary)
    {
        if ($this->file instanceof File) {
            throw Download_Exception::for_cannot_set_binary();
        }
        $this->binary = $binary;
    }
    /**
     * set download for file.
     *
     * @return void
     */
    public function set_file_path(string $filepath)
    {
        if ($this->binary !== null) {
            throw Download_Exception::for_cannot_set_file_path($filepath);
        }
        $this->file = new File($filepath, true);
    }
    /**
     * set name for the download.
     *
     * @return $this
     */
    public function set_file_name(string $filename)
    {
        $this->filename = $filename;
        return $this;
    }
    /**
     * get content length.
     */
    public function get_content_length(): int
    {
        if (is_string($this->binary)) {
            return strlen($this->binary);
        }
        if ($this->file instanceof File) {
            return $this->file->get_size();
        }
        return 0;
    }
    /**
     * Set content type by guessing mime type from file extension
     */
    private function set_content_type_by_mime_type(): void
    {
        $mime = null;
        $charset = '';
        if ($this->set_mime && ($last_dot_position = strrpos($this->filename, '.')) !== false) {
            $mime = Mimes::guess_type_from_extension(substr($this->filename, $last_dot_position + 1));
            $charset = $this->charset;
        }
        if (!is_string($mime)) {
            // Set the default MIME type to send
            $mime = 'application/octet-stream';
            $charset = '';
        }
        $this->set_content_type($mime, $charset);
    }
    /**
     * get download filename.
     */
    private function get_download_file_name(): string
    {
        $filename = $this->filename;
        $x = explode('.', $this->filename);
        $extension = end($x);
        /* It was reported that browsers on Android 2.1 (and possibly older as well)
         * need to have the filename extension upper-cased in order to be able to
         * download it.
         *
         * Reference: http://digiblog.de/2011/04/19/android-and-the-download-file-headers/
         */
        $user_agent = service('superglobals')->server('HTTP_USER_AGENT');
        if (count($x) !== 1 && $user_agent !== null && preg_match('/Android\s(1|2\.[01])/', $user_agent)) {
            $x[count($x) - 1] = strtoupper($extension);
            $filename = implode('.', $x);
        }
        return $filename;
    }
    /**
     * Get Content-Disposition Header string.
     */
    private function get_content_disposition(bool $inline = false): string
    {
        $download_filename = $utf8Filename = $this->get_download_file_name();
        $disposition = $inline ? 'inline' : 'attachment';
        if (strtoupper($this->charset) !== 'UTF-8') {
            $utf8Filename = mb_convert_encoding($download_filename, 'UTF-8', $this->charset);
        }
        $result = sprintf('%s; filename="%s"', $disposition, addslashes($download_filename));
        if ($utf8Filename !== '') {
            $result .= sprintf('; filename*=UTF-8\'\'%s', rawurlencode($utf8Filename));
        }
        return $result;
    }
    /**
     * Disallows status changing.
     *
     * @throws DownloadException
     */
    public function set_status_code(int $code, string $reason = '')
    {
        throw Download_Exception::for_cannot_set_status_code($code, $reason);
    }
    /**
     * Sets the Content Type header for this response with the mime type
     * and, optionally, the charset.
     *
     * @return ResponseInterface
     */
    public function set_content_type(string $mime, string $charset = 'UTF-8')
    {
        parent::set_content_type($mime, $charset);
        if ($charset !== '') {
            $this->charset = $charset;
        }
        return $this;
    }
    /**
     * Sets the appropriate headers to ensure this response
     * is not cached by the browsers.
     */
    public function no_cache(): self
    {
        $this->remove_header('Cache-Control');
        $this->set_header('Cache-Control', ['private', 'no-transform', 'no-store', 'must-revalidate']);
        return $this;
    }
    /**
     * {@inheritDoc}
     *
     * @return $this
     *
     * @todo Do downloads need CSP or Cookies? Compare with ResponseTrait::send()
     */
    public function send()
    {
        // Turn off output buffering completely, even if php.ini output_buffering is not off
        if (ENVIRONMENT !== 'testing') {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
        $this->build_headers();
        $this->send_headers();
        $this->send_body();
        return $this;
    }
    /**
     * set header for file download.
     *
     * @return void
     */
    public function build_headers()
    {
        if (!$this->has_header('Content-Type')) {
            $this->set_content_type_by_mime_type();
        }
        if (!$this->has_header('Content-Disposition')) {
            $this->set_header('Content-Disposition', $this->get_content_disposition());
        }
        $this->set_header('Content-Transfer-Encoding', 'binary');
        $this->set_header('Content-Length', (string) $this->get_content_length());
    }
    /**
     * output download file text.
     *
     * @return DownloadResponse
     *
     * @throws DownloadException
     */
    public function send_body()
    {
        if ($this->binary !== null) {
            return $this->send_body_by_binary();
        }
        if ($this->file instanceof File) {
            return $this->send_body_by_file_path();
        }
        throw Download_Exception::for_not_found_download_source();
    }
    /**
     * output download text by file.
     *
     * @return DownloadResponse
     */
    private function send_body_by_file_path()
    {
        $spl_file_object = $this->file->open_file('rb');
        // Flush 1MB chunks of data
        while (!$spl_file_object->eof() && ($data = $spl_file_object->fread(1048576)) !== false) {
            echo $data;
            unset($data);
        }
        return $this;
    }
    /**
     * output download text by binary
     *
     * @return DownloadResponse
     */
    private function send_body_by_binary()
    {
        echo $this->binary;
        return $this;
    }
    /**
     * Sets the response header to display the file in the browser.
     *
     * @return DownloadResponse
     */
    public function inline()
    {
        $this->set_header('Content-Disposition', $this->get_content_disposition(true));
        return $this;
    }
}