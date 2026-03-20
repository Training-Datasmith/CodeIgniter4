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
namespace Code_Igniter\Email;

use Code_Igniter\Events\Events;
use Code_Igniter\I18n\Time;
use Config\Mimes;
use ErrorException;
/**
 * CodeIgniter Email Class
 *
 * Permits email to be sent using Mail, Sendmail, or SMTP.
 *
 * @see \CodeIgniter\Email\EmailTest
 */
class Email
{
    /**
     * Properties from the last successful send.
     *
     * @var array|null
     */
    public $archive;
    /**
     * Properties to be added to the next archive.
     *
     * @var array
     */
    protected $tmp_archive = [];
    /**
     * @var string
     */
    public $from_email;
    /**
     * @var string
     */
    public $from_name;
    /**
     * Used as the User-Agent and X-Mailer headers' value.
     *
     * @var string
     */
    public $user_agent = 'CodeIgniter';
    /**
     * Path to the Sendmail binary.
     *
     * @var string
     */
    public $mail_path = '/usr/sbin/sendmail';
    /**
     * Which method to use for sending e-mails.
     *
     * @var 'mail'|'sendmail'|'smtp'
     */
    public $protocol = 'mail';
    /**
     * STMP Server Hostname
     *
     * @var string
     */
    public $smtp_host = '';
    /**
     * SMTP Username
     *
     * @var string
     */
    public $smtp_user = '';
    /**
     * SMTP Password
     *
     * @var string
     */
    public $smtp_pass = '';
    /**
     * SMTP Server port
     *
     * @var int
     */
    public $smtp_port = 25;
    /**
     * SMTP connection timeout in seconds
     *
     * @var int
     */
    public $smtp_timeout = 5;
    /**
     * SMTP persistent connection
     *
     * @var bool
     */
    public $smtp_keep_alive = false;
    /**
     * SMTP Encryption
     *
     * * `tls` - will issue a STARTTLS command to the server
     * * `ssl` - means implicit SSL
     * * `''` - for connection on port 465
     *
     * @var ''|'ssl'|'tls'
     */
    public $smtp_crypto = '';
    /**
     * Whether to apply word-wrapping to the message body.
     *
     * @var bool
     */
    public $word_wrap = true;
    /**
     * Number of characters to wrap at.
     *
     * @see Email::$wordWrap
     *
     * @var int
     */
    public $wrap_chars = 76;
    /**
     * Message format.
     *
     * @var 'html'|'text'
     */
    public $mail_type = 'text';
    /**
     * Character set (default: utf-8)
     *
     * @var string
     */
    public $charset = 'UTF-8';
    /**
     * Alternative message (for HTML messages only)
     *
     * @var string
     */
    public $alt_message = '';
    /**
     * Whether to validate e-mail addresses.
     *
     * @var bool
     */
    public $validate = true;
    /**
     * X-Priority header value.
     *
     * @var int<1, 5>
     */
    public $priority = 3;
    /**
     * Newline character sequence.
     * Use "\r\n" to comply with RFC 822.
     *
     * @see http://www.ietf.org/rfc/rfc822.txt
     *
     * @var "\r\n"|"n"
     */
    public $newline = "\r\n";
    /**
     * CRLF character sequence
     *
     * RFC 2045 specifies that for 'quoted-printable' encoding,
     * "\r\n" must be used. However, it appears that some servers
     * (even on the receiving end) don't handle it properly and
     * switching to "\n", while improper, is the only solution
     * that seems to work for all environments.
     *
     * @see http://www.ietf.org/rfc/rfc822.txt
     *
     * @var "\r\n"|"n"
     */
    public $CRLF = "\r\n";
    /**
     * Whether to use Delivery Status Notification.
     *
     * @var bool
     */
    public $DSN = false;
    /**
     * Whether to send multipart alternatives.
     * Yahoo! doesn't seem to like these.
     *
     * @var bool
     */
    public $send_multipart = true;
    /**
     * Whether to send messages to BCC recipients in batches.
     *
     * @var bool
     */
    public $bcc_batch_mode = false;
    /**
     * BCC Batch max number size.
     *
     * @see Email::$BCCBatchMode
     *
     * @var int|string
     */
    public $bcc_batch_size = 200;
    /**
     * Subject header
     *
     * @var string
     */
    protected $subject = '';
    /**
     * Message body
     *
     * @var string
     */
    protected $body = '';
    /**
     * Final message body to be sent.
     *
     * @var string
     */
    protected $final_body = '';
    /**
     * Final headers to send
     *
     * @var string
     */
    protected $header_str = '';
    /**
     * SMTP Connection socket placeholder
     *
     * @var false|resource|null
     */
    protected $smtp_connect;
    /**
     * Mail encoding
     *
     * @var '7bit'|'8bit'
     */
    protected $encoding = '8bit';
    /**
     * Whether to perform SMTP authentication
     *
     * @var bool
     */
    protected $smtp_auth = false;
    /**
     * Which SMTP authentication method to use: login, plain
     */
    protected string $smtp_auth_method = 'login';
    /**
     * Whether to send a Reply-To header
     *
     * @var bool
     */
    protected $reply_to_flag = false;
    /**
     * Debug messages
     *
     * @see Email::printDebugger()
     *
     * @var array
     */
    protected $debug_message = [];
    /**
     * Raw debug messages
     *
     * @var list<string>
     */
    private array $debug_message_raw = [];
    /**
     * Recipients
     *
     * @var array|string
     */
    protected $recipients = [];
    /**
     * CC Recipients
     *
     * @var array
     */
    protected $cc_array = [];
    /**
     * BCC Recipients
     *
     * @var array
     */
    protected $bcc_array = [];
    /**
     * Message headers
     *
     * @var array
     */
    protected $headers = [];
    /**
     * Attachment data
     *
     * @var array
     */
    protected $attachments = [];
    /**
     * Valid $protocol values
     *
     * @see Email::$protocol
     *
     * @var list<string>
     */
    protected $protocols = ['mail', 'sendmail', 'smtp'];
    /**
     * Character sets valid for 7-bit encoding,
     * excluding language suffix.
     *
     * @var list<string>
     */
    protected $base_charsets = ['us-ascii', 'iso-2022-'];
    /**
     * Bit depths
     *
     * Valid mail encodings
     *
     * @see Email::$encoding
     *
     * @var list<string>
     */
    protected $bit_depths = ['7bit', '8bit'];
    /**
     * $priority translations
     *
     * Actual values to send with the X-Priority header
     *
     * @var array<int, string>
     */
    protected $priorities = [1 => '1 (Highest)', 2 => '2 (High)', 3 => '3 (Normal)', 4 => '4 (Low)', 5 => '5 (Lowest)'];
    /**
     * mbstring.func_overload flag
     *
     * @var bool|null
     */
    protected static $func_overload;
    /**
     * @param array|\Config\Email|null $config
     */
    public function __construct($config = null)
    {
        $this->initialize($config);
        if (!isset(static::$func_overload)) {
            static::$func_overload = extension_loaded('mbstring') && ini_get('mbstring.func_overload');
        }
    }
    /**
     * Initialize preferences
     *
     * @param array|\Config\Email|null $config
     *
     * @return $this
     */
    public function initialize($config)
    {
        $this->clear();
        if ($config instanceof \Config\Email) {
            $config = get_object_vars($config);
        }
        foreach (array_keys(get_class_vars(static::class)) as $key) {
            if (property_exists($this, $key) && isset($config[$key])) {
                $method = 'set' . ucfirst($key);
                if (method_exists($this, $method)) {
                    $this->{$method}($config[$key]);
                } else {
                    $this->{$key} = $config[$key];
                }
            }
        }
        $this->charset = strtoupper($this->charset);
        $this->smtp_auth = isset($this->smtp_user[0], $this->smtp_pass[0]);
        return $this;
    }
    /**
     * @param bool $clearAttachments
     *
     * @return $this
     */
    public function clear($clear_attachments = false)
    {
        $this->subject = '';
        $this->body = '';
        $this->final_body = '';
        $this->header_str = '';
        $this->reply_to_flag = false;
        $this->recipients = [];
        $this->cc_array = [];
        $this->bcc_array = [];
        $this->headers = [];
        $this->debug_message = [];
        $this->debug_message_raw = [];
        $this->set_header('Date', $this->set_date());
        if ($clear_attachments) {
            $this->attachments = [];
        }
        return $this;
    }
    /**
     * @param string      $from
     * @param string      $name
     * @param string|null $returnPath
     *
     * @return $this
     */
    public function set_from($from, $name = '', $return_path = null)
    {
        if (preg_match('/\<(.*)\>/', $from, $match) === 1) {
            $from = $match[1];
        }
        if ($this->validate) {
            $this->validate_email($this->string_to_array($from));
            if ($return_path !== null) {
                $this->validate_email($this->string_to_array($return_path));
            }
        }
        $this->tmp_archive['fromEmail'] = $from;
        $this->tmp_archive['fromName'] = $name;
        if ($name !== '') {
            // only use Q encoding if there are characters that would require it
            if (preg_match('/[\200-\377]/', $name) !== 1) {
                $name = '"' . addcslashes($name, "\x00..\x1f'\"\\") . '"';
            } else {
                $name = $this->prep_q_encoding($name);
            }
        }
        $this->set_header('From', $name . ' <' . $from . '>');
        $return_path ??= $from;
        $this->set_header('Return-Path', '<' . $return_path . '>');
        $this->tmp_archive['returnPath'] = $return_path;
        return $this;
    }
    /**
     * @param string $replyto
     * @param string $name
     *
     * @return $this
     */
    public function set_reply_to($replyto, $name = '')
    {
        if (preg_match('/\<(.*)\>/', $replyto, $match) === 1) {
            $replyto = $match[1];
        }
        if ($this->validate) {
            $this->validate_email($this->string_to_array($replyto));
        }
        if ($name !== '') {
            $this->tmp_archive['replyName'] = $name;
            // only use Q encoding if there are characters that would require it
            if (preg_match('/[\200-\377]/', $name) !== 1) {
                $name = '"' . addcslashes($name, "\x00..\x1f'\"\\") . '"';
            } else {
                $name = $this->prep_q_encoding($name);
            }
        }
        $this->set_header('Reply-To', $name . ' <' . $replyto . '>');
        $this->reply_to_flag = true;
        $this->tmp_archive['replyTo'] = $replyto;
        return $this;
    }
    /**
     * @param array|string $to
     *
     * @return $this
     */
    public function set_to($to)
    {
        $to = $this->string_to_array($to);
        $to = $this->clean_email($to);
        if ($this->validate) {
            $this->validate_email($to);
        }
        if ($this->get_protocol() !== 'mail') {
            $this->set_header('To', implode(', ', $to));
        }
        $this->recipients = $to;
        return $this;
    }
    /**
     * @param string $cc
     *
     * @return $this
     */
    public function set_cc($cc)
    {
        $cc = $this->clean_email($this->string_to_array($cc));
        if ($this->validate) {
            $this->validate_email($cc);
        }
        $this->set_header('Cc', implode(', ', $cc));
        if ($this->get_protocol() === 'smtp') {
            $this->cc_array = $cc;
        }
        $this->tmp_archive['CCArray'] = $cc;
        return $this;
    }
    /**
     * @param string $bcc
     * @param string $limit
     *
     * @return $this
     */
    public function set_bcc($bcc, $limit = '')
    {
        if ($limit !== '' && is_numeric($limit)) {
            $this->bcc_batch_mode = true;
            $this->bcc_batch_size = $limit;
        }
        $bcc = $this->clean_email($this->string_to_array($bcc));
        if ($this->validate) {
            $this->validate_email($bcc);
        }
        if ($this->get_protocol() === 'smtp' || $this->bcc_batch_mode && count($bcc) > $this->bcc_batch_size) {
            $this->bcc_array = $bcc;
        } else {
            $this->set_header('Bcc', implode(', ', $bcc));
            $this->tmp_archive['BCCArray'] = $bcc;
        }
        return $this;
    }
    /**
     * @param string $subject
     *
     * @return $this
     */
    public function set_subject($subject)
    {
        $this->tmp_archive['subject'] = $subject;
        $subject = $this->prep_q_encoding($subject);
        $this->set_header('Subject', $subject);
        return $this;
    }
    /**
     * @param string $body
     *
     * @return $this
     */
    public function set_message($body)
    {
        $this->body = rtrim(str_replace("\r", '', $body));
        return $this;
    }
    /**
     * @param string      $file        Can be local path, URL or buffered content
     * @param string      $disposition 'attachment'
     * @param string|null $newname
     * @param string      $mime
     *
     * @return bool|Email
     */
    public function attach($file, $disposition = '', $newname = null, $mime = '')
    {
        if ($mime === '') {
            if (!str_contains($file, '://') && !is_file($file)) {
                $this->set_error_message(lang('Email.attachmentMissing', [$file]));
                return false;
            }
            if (!$fp = @fopen($file, 'rb')) {
                $this->set_error_message(lang('Email.attachmentUnreadable', [$file]));
                return false;
            }
            $file_content = stream_get_contents($fp);
            $mime = $this->mime_types(pathinfo($file, PATHINFO_EXTENSION));
            fclose($fp);
        } else {
            $file_content =& $file;
            // buffered file
        }
        // declare names on their own, to make phpcbf happy
        $names_attached = [$file, $newname];
        $this->attachments[] = [
            'name' => $names_attached,
            'disposition' => empty($disposition) ? 'attachment' : $disposition,
            // Can also be 'inline'  Not sure if it matters
            'type' => $mime,
            'content' => chunk_split(base64_encode($file_content)),
            'multipart' => 'mixed',
        ];
        return $this;
    }
    /**
     * Set and return attachment Content-ID
     * Useful for attached inline pictures
     *
     * @param string $filename
     *
     * @return bool|string
     */
    public function set_attachment_cid($filename)
    {
        foreach ($this->attachments as $i => $attachment) {
            // For file path.
            if ($attachment['name'][0] === $filename) {
                $this->attachments[$i]['multipart'] = 'related';
                $this->attachments[$i]['cid'] = uniqid(basename($attachment['name'][0]) . '@', true);
                return $this->attachments[$i]['cid'];
            }
            // For buffer string.
            if ($attachment['name'][1] === $filename) {
                $this->attachments[$i]['multipart'] = 'related';
                $this->attachments[$i]['cid'] = uniqid(basename($attachment['name'][1]) . '@', true);
                return $this->attachments[$i]['cid'];
            }
        }
        return false;
    }
    /**
     * @param string $header
     * @param string $value
     *
     * @return $this
     */
    public function set_header($header, $value)
    {
        $this->headers[$header] = str_replace(["\n", "\r"], '', $value);
        return $this;
    }
    /**
     * @param list<string>|string $email
     *
     * @return list<string>
     */
    protected function string_to_array($email)
    {
        if (!is_array($email)) {
            return str_contains($email, ',') ? preg_split('/[\s,]/', $email, -1, PREG_SPLIT_NO_EMPTY) : (array) trim($email);
        }
        return $email;
    }
    /**
     * @param string $str
     *
     * @return $this
     */
    public function set_alt_message($str)
    {
        $this->alt_message = (string) $str;
        return $this;
    }
    /**
     * @param string $type
     *
     * @return $this
     */
    public function set_mail_type($type = 'text')
    {
        $this->mail_type = $type === 'html' ? 'html' : 'text';
        return $this;
    }
    /**
     * @param bool $wordWrap
     *
     * @return $this
     */
    public function set_word_wrap($word_wrap = true)
    {
        $this->word_wrap = (bool) $word_wrap;
        return $this;
    }
    /**
     * @param string $protocol
     *
     * @return $this
     */
    public function set_protocol($protocol = 'mail')
    {
        $this->protocol = in_array($protocol, $this->protocols, true) ? strtolower($protocol) : 'mail';
        return $this;
    }
    /**
     * @param int $n
     *
     * @return $this
     */
    public function set_priority($n = 3)
    {
        $this->priority = preg_match('/^[1-5]$/', (string) $n) ? (int) $n : 3;
        return $this;
    }
    /**
     * @param string $newline
     *
     * @return $this
     */
    public function set_newline($newline = "\n")
    {
        $this->newline = in_array($newline, ["\n", "\r\n", "\r"], true) ? $newline : "\n";
        return $this;
    }
    /**
     * @param string $CRLF
     *
     * @return $this
     */
    public function set_crlf($CRLF = "\n")
    {
        $this->CRLF = in_array($CRLF, ["\n", "\r\n", "\r"], true) ? $CRLF : "\n";
        return $this;
    }
    /**
     * @return string
     */
    protected function get_message_id()
    {
        $from = str_replace(['>', '<'], '', $this->headers['Return-Path']);
        return '<' . uniqid('', true) . strstr($from, '@') . '>';
    }
    /**
     * @return string
     */
    protected function get_protocol()
    {
        $this->protocol = strtolower($this->protocol);
        if (!in_array($this->protocol, $this->protocols, true)) {
            $this->protocol = 'mail';
        }
        return $this->protocol;
    }
    /**
     * @return string
     */
    protected function get_encoding()
    {
        if (!in_array($this->encoding, $this->bit_depths, true)) {
            $this->encoding = '8bit';
        }
        foreach ($this->base_charsets as $charset) {
            if (str_starts_with($this->charset, $charset)) {
                $this->encoding = '7bit';
                break;
            }
        }
        return $this->encoding;
    }
    /**
     * @return string
     */
    protected function get_content_type()
    {
        if ($this->mail_type === 'html') {
            return $this->attachments === [] ? 'html' : 'html-attach';
        }
        if ($this->mail_type === 'text' && $this->attachments !== []) {
            return 'plain-attach';
        }
        return 'plain';
    }
    /**
     * Set RFC 822 Date
     *
     * @return string
     */
    protected function set_date()
    {
        $timezone = date('Z');
        $operator = $timezone[0] === '-' ? '-' : '+';
        $timezone = abs((int) $timezone);
        $timezone = floor($timezone / 3600) * 100 + $timezone % 3600 / 60;
        return sprintf('%s %s%04d', date('D, j M Y H:i:s'), $operator, $timezone);
    }
    /**
     * @return string
     */
    protected function get_mime_message()
    {
        return 'This is a multi-part message in MIME format.' . $this->newline . 'Your email application may not support this format.';
    }
    /**
     * @param array|string $email
     *
     * @return bool
     */
    public function validate_email($email)
    {
        if (!is_array($email)) {
            $this->set_error_message(lang('Email.mustBeArray'));
            return false;
        }
        foreach ($email as $val) {
            if (!$this->is_valid_email($val)) {
                $this->set_error_message(lang('Email.invalidAddress', [$val]));
                return false;
            }
        }
        return true;
    }
    /**
     * @param string $email
     *
     * @return bool
     */
    public function is_valid_email($email)
    {
        if (function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46') && $atpos = strpos($email, '@')) {
            $email = static::substr($email, 0, ++$atpos) . idn_to_ascii(static::substr($email, $atpos), 0, INTL_IDNA_VARIANT_UTS46);
        }
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    /**
     * @param array|string $email
     *
     * @return array|string
     */
    public function clean_email($email)
    {
        if (!is_array($email)) {
            return preg_match('/\<(.*)\>/', $email, $match) ? $match[1] : $email;
        }
        $clean_email = [];
        foreach ($email as $addy) {
            $clean_email[] = preg_match('/\<(.*)\>/', $addy, $match) ? $match[1] : $addy;
        }
        return $clean_email;
    }
    /**
     * Build alternative plain text message
     *
     * Provides the raw message for use in plain-text headers of
     * HTML-formatted emails.
     *
     * If the user hasn't specified his own alternative message
     * it creates one by stripping the HTML
     *
     * @return string
     */
    protected function get_alt_message()
    {
        if ($this->alt_message !== '') {
            return $this->word_wrap ? $this->word_wrap($this->alt_message, 76) : $this->alt_message;
        }
        $body = preg_match('/\<body.*?\>(.*)\<\/body\>/si', $this->body, $match) ? $match[1] : $this->body;
        $body = str_replace("\t", '', preg_replace('#<!--(.*)--\>#', '', trim(strip_tags($body))));
        for ($i = 20; $i >= 3; $i--) {
            $body = str_replace(str_repeat("\n", $i), "\n\n", $body);
        }
        $body = preg_replace('| +|', ' ', $body);
        return $this->word_wrap ? $this->word_wrap($body, 76) : $body;
    }
    /**
     * @param string   $str
     * @param int|null $charlim Line-length limit
     *
     * @return string
     */
    public function word_wrap($str, $charlim = null)
    {
        $charlim ??= 0;
        if ($charlim === 0) {
            $charlim = $this->wrap_chars === 0 ? 76 : $this->wrap_chars;
        }
        if (str_contains($str, "\r")) {
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }
        $str = preg_replace('| +\n|', "\n", $str);
        $unwrap = [];
        if (preg_match_all('|\{unwrap\}(.+?)\{/unwrap\}|s', $str, $matches) >= 1) {
            for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
                $unwrap[] = $matches[1][$i];
                $str = str_replace($matches[0][$i], '{{unwrapped' . $i . '}}', $str);
            }
        }
        // Use PHP's native function to do the initial wordwrap.
        // We set the cut flag to FALSE so that any individual words that are
        // too long get left alone. In the next step we'll deal with them.
        $str = wordwrap($str, $charlim, "\n", false);
        // Split the string into individual lines of text and cycle through them
        $output = '';
        foreach (explode("\n", $str) as $line) {
            if (static::strlen($line) <= $charlim) {
                $output .= $line . $this->newline;
                continue;
            }
            $temp = '';
            do {
                if (preg_match('!\[url.+\]|://|www\.!', $line)) {
                    break;
                }
                $temp .= static::substr($line, 0, $charlim - 1);
                $line = static::substr($line, $charlim - 1);
            } while (static::strlen($line) > $charlim);
            if ($temp !== '') {
                $output .= $temp . $this->newline;
            }
            $output .= $line . $this->newline;
        }
        foreach ($unwrap as $key => $val) {
            $output = str_replace('{{unwrapped' . $key . '}}', $val, $output);
        }
        return $output;
    }
    /**
     * Build final headers
     *
     * @return void
     */
    protected function build_headers()
    {
        $this->set_header('User-Agent', $this->user_agent);
        $this->set_header('X-Sender', $this->clean_email($this->headers['From']));
        $this->set_header('X-Mailer', $this->user_agent);
        $this->set_header('X-Priority', $this->priorities[$this->priority]);
        $this->set_header('Message-ID', $this->get_message_id());
        $this->set_header('Mime-Version', '1.0');
    }
    /**
     * Write Headers as a string
     *
     * @return void
     */
    protected function write_headers()
    {
        if ($this->protocol === 'mail' && isset($this->headers['Subject'])) {
            $this->subject = $this->headers['Subject'];
            unset($this->headers['Subject']);
        }
        reset($this->headers);
        $this->header_str = '';
        foreach ($this->headers as $key => $val) {
            $val = trim($val);
            if ($val !== '') {
                $this->header_str .= $key . ': ' . $val . $this->newline;
            }
        }
        if ($this->get_protocol() === 'mail') {
            $this->header_str = rtrim($this->header_str);
        }
    }
    /**
     * Build Final Body and attachments
     *
     * @return void
     */
    protected function build_message()
    {
        if ($this->word_wrap === true && $this->mail_type !== 'html') {
            $this->body = $this->word_wrap($this->body);
        }
        $this->write_headers();
        $hdr = $this->get_protocol() === 'mail' ? $this->newline : '';
        $body = '';
        switch ($this->get_content_type()) {
            case 'plain':
                $hdr .= 'Content-Type: text/plain; charset=' . $this->charset . $this->newline . 'Content-Transfer-Encoding: ' . $this->get_encoding();
                if ($this->get_protocol() === 'mail') {
                    $this->header_str .= $hdr;
                    $this->final_body = $this->body;
                } else {
                    $this->final_body = $hdr . $this->newline . $this->newline . $this->body;
                }
                return;
            case 'html':
                $boundary = uniqid('B_ALT_', true);
                if ($this->send_multipart === false) {
                    $hdr .= 'Content-Type: text/html; charset=' . $this->charset . $this->newline . 'Content-Transfer-Encoding: quoted-printable';
                } else {
                    $hdr .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
                    $body .= $this->get_mime_message() . $this->newline . $this->newline . '--' . $boundary . $this->newline . 'Content-Type: text/plain; charset=' . $this->charset . $this->newline . 'Content-Transfer-Encoding: ' . $this->get_encoding() . $this->newline . $this->newline . $this->get_alt_message() . $this->newline . $this->newline . '--' . $boundary . $this->newline . 'Content-Type: text/html; charset=' . $this->charset . $this->newline . 'Content-Transfer-Encoding: quoted-printable' . $this->newline . $this->newline;
                }
                $this->final_body = $body . $this->prep_quoted_printable($this->body) . $this->newline . $this->newline;
                if ($this->get_protocol() === 'mail') {
                    $this->header_str .= $hdr;
                } else {
                    $this->final_body = $hdr . $this->newline . $this->newline . $this->final_body;
                }
                if ($this->send_multipart !== false) {
                    $this->final_body .= '--' . $boundary . '--';
                }
                return;
            case 'plain-attach':
                $boundary = uniqid('B_ATC_', true);
                $hdr .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
                if ($this->get_protocol() === 'mail') {
                    $this->header_str .= $hdr;
                }
                $body .= $this->get_mime_message() . $this->newline . $this->newline . '--' . $boundary . $this->newline . 'Content-Type: text/plain; charset=' . $this->charset . $this->newline . 'Content-Transfer-Encoding: ' . $this->get_encoding() . $this->newline . $this->newline . $this->body . $this->newline . $this->newline;
                $this->append_attachments($body, $boundary);
                break;
            case 'html-attach':
                $alt_boundary = uniqid('B_ALT_', true);
                $last_boundary = null;
                if ($this->attachments_have_multipart('mixed')) {
                    $atc_boundary = uniqid('B_ATC_', true);
                    $hdr .= 'Content-Type: multipart/mixed; boundary="' . $atc_boundary . '"';
                    $last_boundary = $atc_boundary;
                }
                if ($this->attachments_have_multipart('related')) {
                    $rel_boundary = uniqid('B_REL_', true);
                    $rel_boundary_header = 'Content-Type: multipart/related; boundary="' . $rel_boundary . '"';
                    if (isset($last_boundary)) {
                        $body .= '--' . $last_boundary . $this->newline . $rel_boundary_header;
                    } else {
                        $hdr .= $rel_boundary_header;
                    }
                    $last_boundary = $rel_boundary;
                }
                if ($this->get_protocol() === 'mail') {
                    $this->header_str .= $hdr;
                }
                if (static::strlen($body) > 0) {
                    $body .= $this->newline . $this->newline;
                }
                $body .= $this->get_mime_message() . $this->newline . $this->newline . '--' . $last_boundary . $this->newline . 'Content-Type: multipart/alternative; boundary="' . $alt_boundary . '"' . $this->newline . $this->newline . '--' . $alt_boundary . $this->newline . 'Content-Type: text/plain; charset=' . $this->charset . $this->newline . 'Content-Transfer-Encoding: ' . $this->get_encoding() . $this->newline . $this->newline . $this->get_alt_message() . $this->newline . $this->newline . '--' . $alt_boundary . $this->newline . 'Content-Type: text/html; charset=' . $this->charset . $this->newline . 'Content-Transfer-Encoding: quoted-printable' . $this->newline . $this->newline . $this->prep_quoted_printable($this->body) . $this->newline . $this->newline . '--' . $alt_boundary . '--' . $this->newline . $this->newline;
                if (isset($rel_boundary)) {
                    $body .= $this->newline . $this->newline;
                    $this->append_attachments($body, $rel_boundary, 'related');
                }
                // multipart/mixed attachments
                if (isset($atc_boundary)) {
                    $body .= $this->newline . $this->newline;
                    $this->append_attachments($body, $atc_boundary, 'mixed');
                }
                break;
        }
        $this->final_body = $this->get_protocol() === 'mail' ? $body : $hdr . $this->newline . $this->newline . $body;
    }
    /**
     * @param string $type
     *
     * @return bool
     */
    protected function attachments_have_multipart($type)
    {
        foreach ($this->attachments as $attachment) {
            if ($attachment['multipart'] === $type) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param string      $body      Message body to append to
     * @param string      $boundary  Multipart boundary
     * @param string|null $multipart When provided, only attachments of this type will be processed
     *
     * @return void
     */
    protected function append_attachments(&$body, $boundary, $multipart = null)
    {
        foreach ($this->attachments as $attachment) {
            if (isset($multipart) && $attachment['multipart'] !== $multipart) {
                continue;
            }
            $name = $attachment['name'][1] ?? basename($attachment['name'][0]);
            $body .= '--' . $boundary . $this->newline . 'Content-Type: ' . $attachment['type'] . '; name="' . $name . '"' . $this->newline . 'Content-Disposition: ' . $attachment['disposition'] . ';' . $this->newline . 'Content-Transfer-Encoding: base64' . $this->newline . (isset($attachment['cid']) && $attachment['cid'] !== '' ? 'Content-ID: <' . $attachment['cid'] . '>' . $this->newline : '') . $this->newline . $attachment['content'] . $this->newline;
        }
        // $name won't be set if no attachments were appended,
        // and therefore a boundary wouldn't be necessary
        if (isset($name)) {
            $body .= '--' . $boundary . '--';
        }
    }
    /**
     * Prepares string for Quoted-Printable Content-Transfer-Encoding
     * Refer to RFC 2045 http://www.ietf.org/rfc/rfc2045.txt
     *
     * @param string $str
     *
     * @return string
     */
    protected function prep_quoted_printable($str)
    {
        // ASCII code numbers for "safe" characters that can always be
        // used literally, without encoding, as described in RFC 2049.
        // http://www.ietf.org/rfc/rfc2049.txt
        static $ascii_safe_chars = [
            // ' (  )   +   ,   -   .   /   :   =   ?
            39,
            40,
            41,
            43,
            44,
            45,
            46,
            47,
            58,
            61,
            63,
            // numbers
            48,
            49,
            50,
            51,
            52,
            53,
            54,
            55,
            56,
            57,
            // upper-case letters
            65,
            66,
            67,
            68,
            69,
            70,
            71,
            72,
            73,
            74,
            75,
            76,
            77,
            78,
            79,
            80,
            81,
            82,
            83,
            84,
            85,
            86,
            87,
            88,
            89,
            90,
            // lower-case letters
            97,
            98,
            99,
            100,
            101,
            102,
            103,
            104,
            105,
            106,
            107,
            108,
            109,
            110,
            111,
            112,
            113,
            114,
            115,
            116,
            117,
            118,
            119,
            120,
            121,
            122,
        ];
        // We are intentionally wrapping so mail servers will encode characters
        // properly and MUAs will behave, so {unwrap} must go!
        $str = str_replace(['{unwrap}', '{/unwrap}'], '', $str);
        // RFC 2045 specifies CRLF as "\r\n".
        // However, many developers choose to override that and violate
        // the RFC rules due to (apparently) a bug in MS Exchange,
        // which only works with "\n".
        if ($this->CRLF === "\r\n") {
            return quoted_printable_encode($str);
        }
        // Reduce multiple spaces & remove nulls
        $str = preg_replace(['| +|', '/\x00+/'], [' ', ''], $str);
        // Standardize newlines
        if (str_contains($str, "\r")) {
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }
        $escape = '=';
        $output = '';
        foreach (explode("\n", $str) as $line) {
            $length = static::strlen($line);
            $temp = '';
            // Loop through each character in the line to add soft-wrap
            // characters at the end of a line " =\r\n" and add the newly
            // processed line(s) to the output (see comment on $crlf class property)
            for ($i = 0; $i < $length; $i++) {
                // Grab the next character
                $char = $line[$i];
                $ascii = ord($char);
                // Convert spaces and tabs but only if it's the end of the line
                if ($ascii === 32 || $ascii === 9) {
                    if ($i === $length - 1) {
                        $char = $escape . sprintf('%02s', dechex($ascii));
                    }
                } elseif ($ascii === 61) {
                    $char = $escape . strtoupper(sprintf('%02s', dechex($ascii)));
                    // =3D
                } elseif (!in_array($ascii, $ascii_safe_chars, true)) {
                    $char = $escape . strtoupper(sprintf('%02s', dechex($ascii)));
                }
                // If we're at the character limit, add the line to the output,
                // reset our temp variable, and keep on chuggin'
                if (static::strlen($temp) + static::strlen($char) >= 76) {
                    $output .= $temp . $escape . $this->CRLF;
                    $temp = '';
                }
                // Add the character to our temporary line
                $temp .= $char;
            }
            // Add our completed line to the output
            $output .= $temp . $this->CRLF;
        }
        // get rid of extra CRLF tacked onto the end
        return static::substr($output, 0, static::strlen($this->CRLF) * -1);
    }
    /**
     * Performs "Q Encoding" on a string for use in email headers.
     * It's related but not identical to quoted-printable, so it has its
     * own method.
     *
     * @param string $str
     *
     * @return string
     */
    protected function prep_q_encoding($str)
    {
        $str = str_replace(["\r", "\n"], '', $str);
        if ($this->charset === 'UTF-8') {
            // Note: We used to have mb_encode_mimeheader() as the first choice
            // here, but it turned out to be buggy and unreliable. DO NOT
            // re-add it! -- Narf
            if (extension_loaded('iconv')) {
                $output = @iconv_mime_encode('', $str, ['scheme' => 'Q', 'line-length' => 76, 'input-charset' => $this->charset, 'output-charset' => $this->charset, 'line-break-chars' => $this->CRLF]);
                // There are reports that iconv_mime_encode() might fail and return FALSE
                if ($output !== false) {
                    // iconv_mime_encode() will always put a header field name.
                    // We've passed it an empty one, but it still prepends our
                    // encoded string with ': ', so we need to strip it.
                    return static::substr($output, 2);
                }
                $chars = iconv_strlen($str, 'UTF-8');
            } elseif (extension_loaded('mbstring')) {
                $chars = mb_strlen($str, 'UTF-8');
            }
        }
        // We might already have this set for UTF-8
        if (!isset($chars)) {
            $chars = static::strlen($str);
        }
        $output = '=?' . $this->charset . '?Q?';
        for ($i = 0, $length = static::strlen($output); $i < $chars; $i++) {
            $chr = $this->charset === 'UTF-8' && extension_loaded('iconv') ? '=' . implode('=', str_split(strtoupper(bin2hex(iconv_substr($str, $i, 1, $this->charset))), 2)) : '=' . strtoupper(bin2hex($str[$i]));
            // RFC 2045 sets a limit of 76 characters per line.
            // We'll append ?= to the end of each line though.
            if ($length + ($l = static::strlen($chr)) > 74) {
                $output .= '?=' . $this->CRLF . ' =?' . $this->charset . '?Q?' . $chr;
                // New line
                $length = 6 + static::strlen($this->charset) + $l;
                // Reset the length for the new line
            } else {
                $output .= $chr;
                $length += $l;
            }
        }
        // End the header
        return $output . '?=';
    }
    /**
     * @param bool $autoClear
     *
     * @return bool
     */
    public function send($auto_clear = true)
    {
        if (!isset($this->headers['From']) && !empty($this->from_email)) {
            $this->set_from($this->from_email, $this->from_name);
        }
        if (!isset($this->headers['From'])) {
            $this->set_error_message(lang('Email.noFrom'));
            return false;
        }
        if ($this->reply_to_flag === false) {
            $this->set_reply_to($this->headers['From']);
        }
        if (empty($this->recipients) && !isset($this->headers['To']) && empty($this->bcc_array) && !isset($this->headers['Bcc']) && !isset($this->headers['Cc'])) {
            $this->set_error_message(lang('Email.noRecipients'));
            return false;
        }
        $this->build_headers();
        if ($this->bcc_batch_mode && count($this->bcc_array) > $this->bcc_batch_size) {
            $this->batch_bcc_send();
            if ($auto_clear) {
                $this->clear();
            }
            return true;
        }
        $this->build_message();
        $result = $this->spool_email();
        if ($result) {
            $this->set_archive_values();
            if ($auto_clear) {
                $this->clear();
            }
            Events::trigger('email', $this->archive);
        }
        return $result;
    }
    /**
     * Batch Bcc Send. Sends groups of BCCs in batches
     *
     * @return void
     */
    public function batch_bcc_send()
    {
        $float = $this->bcc_batch_size - 1;
        $set = '';
        $chunk = [];
        for ($i = 0, $c = count($this->bcc_array); $i < $c; $i++) {
            if (isset($this->bcc_array[$i])) {
                $set .= ', ' . $this->bcc_array[$i];
            }
            if ($i === $float) {
                $chunk[] = static::substr($set, 1);
                $float += $this->bcc_batch_size;
                $set = '';
            }
            if ($i === $c - 1) {
                $chunk[] = static::substr($set, 1);
            }
        }
        for ($i = 0, $c = count($chunk); $i < $c; $i++) {
            unset($this->headers['Bcc']);
            $bcc = $this->clean_email($this->string_to_array($chunk[$i]));
            if ($this->protocol !== 'smtp') {
                $this->set_header('Bcc', implode(', ', $bcc));
            } else {
                $this->bcc_array = $bcc;
            }
            $this->build_message();
            $this->spool_email();
        }
        // Update the archive
        $this->set_archive_values();
        Events::trigger('email', $this->archive);
    }
    /**
     * Unwrap special elements
     *
     * @return void
     */
    protected function unwrap_specials()
    {
        $this->final_body = preg_replace_callback('/\{unwrap\}(.*?)\{\/unwrap\}/si', $this->remove_nl_callback(...), $this->final_body);
    }
    /**
     * Strip line-breaks via callback
     *
     * @used-by unwrapSpecials()
     *
     * @param list<string> $matches
     *
     * @return string
     */
    protected function remove_nl_callback($matches)
    {
        if (str_contains($matches[1], "\r") || str_contains($matches[1], "\n")) {
            $matches[1] = str_replace(["\r\n", "\r", "\n"], '', $matches[1]);
        }
        return $matches[1];
    }
    /**
     * Spool mail to the mail server
     *
     * @return bool
     */
    protected function spool_email()
    {
        $this->unwrap_specials();
        $protocol = $this->get_protocol();
        $upper_first_protocol = ucfirst($protocol);
        $method = 'sendWith' . $upper_first_protocol;
        try {
            $success = $this->{$method}();
        } catch (ErrorException $e) {
            $success = false;
            log_message('error', 'Email: ' . $method . ' throwed ' . $e);
        }
        if (!$success) {
            $message = lang('Email.sendFailure' . ($protocol === 'mail' ? 'PHPMail' : $upper_first_protocol));
            log_message('error', 'Email: ' . $message);
            log_message('error', $this->print_debugger_raw());
            $this->set_error_message($message);
            return false;
        }
        $this->set_error_message(lang('Email.sent', [$protocol]));
        return true;
    }
    /**
     * Validate email for shell
     *
     * Applies stricter, shell-safe validation to email addresses.
     * Introduced to prevent RCE via sendmail's -f option.
     *
     * @see     https://github.com/codeigniter4/CodeIgniter/issues/4963
     * @see     https://gist.github.com/Zenexer/40d02da5e07f151adeaeeaa11af9ab36
     *
     * @license https://creativecommons.org/publicdomain/zero/1.0/    CC0 1.0, Public Domain
     *
     * Credits for the base concept go to Paul Buonopane <paul@namepros.com>
     *
     * @param string $email
     *
     * @return bool
     */
    protected function validate_email_for_shell(&$email)
    {
        if (function_exists('idn_to_ascii') && $atpos = strpos($email, '@')) {
            $email = static::substr($email, 0, ++$atpos) . idn_to_ascii(static::substr($email, $atpos), 0, INTL_IDNA_VARIANT_UTS46);
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) === $email && preg_match('#\A[a-z0-9._+-]+@[a-z0-9.-]{1,253}\z#i', $email);
    }
    /**
     * Send using mail()
     *
     * @return bool
     */
    protected function send_with_mail()
    {
        $recipients = is_array($this->recipients) ? implode(', ', $this->recipients) : $this->recipients;
        // _validate_email_for_shell() below accepts by reference,
        // so this needs to be assigned to a variable
        $from = $this->clean_email($this->headers['Return-Path']);
        if (!$this->validate_email_for_shell($from)) {
            return mail($recipients, $this->subject, $this->final_body, $this->header_str);
        }
        // most documentation of sendmail using the "-f" flag lacks a space after it, however
        // we've encountered servers that seem to require it to be in place.
        return mail($recipients, $this->subject, $this->final_body, $this->header_str, '-f ' . $from);
    }
    /**
     * Send using Sendmail
     *
     * @return bool
     */
    protected function send_with_sendmail()
    {
        // _validate_email_for_shell() below accepts by reference,
        // so this needs to be assigned to a variable
        $from = $this->clean_email($this->headers['From']);
        $from = $this->validate_email_for_shell($from) ? '-f ' . $from : '';
        if (!function_usable('popen') || false === $fp = @popen($this->mail_path . ' -oi ' . $from . ' -t', 'w')) {
            return false;
        }
        fwrite($fp, $this->header_str);
        fwrite($fp, $this->final_body);
        $status = pclose($fp);
        if ($status !== 0) {
            $this->set_error_message(lang('Email.exitStatus', [$status]));
            $this->set_error_message(lang('Email.noSocket'));
            return false;
        }
        return true;
    }
    /**
     * Send using SMTP
     *
     * @return bool
     */
    protected function send_with_smtp()
    {
        if ($this->smtp_host === '') {
            $this->set_error_message(lang('Email.noHostname'));
            return false;
        }
        if (!$this->smtp_connect() || !$this->smtp_authenticate()) {
            return false;
        }
        if (!$this->send_command('from', $this->clean_email($this->headers['From']))) {
            $this->smtp_end();
            return false;
        }
        foreach ($this->recipients as $val) {
            if (!$this->send_command('to', $val)) {
                $this->smtp_end();
                return false;
            }
        }
        foreach ($this->cc_array as $val) {
            if ($val !== '' && !$this->send_command('to', $val)) {
                $this->smtp_end();
                return false;
            }
        }
        foreach ($this->bcc_array as $val) {
            if ($val !== '' && !$this->send_command('to', $val)) {
                $this->smtp_end();
                return false;
            }
        }
        if (!$this->send_command('data')) {
            $this->smtp_end();
            return false;
        }
        // perform dot transformation on any lines that begin with a dot
        $this->send_data($this->header_str . preg_replace('/^\./m', '..$1', $this->final_body));
        $this->send_data($this->newline . '.');
        $reply = $this->get_smtp_data();
        $this->set_error_message($reply);
        $this->smtp_end();
        if (!str_starts_with($reply, '250')) {
            $this->set_error_message(lang('Email.SMTPError', [$reply]));
            return false;
        }
        return true;
    }
    /**
     * Shortcut to send RSET or QUIT depending on keep-alive
     *
     * @return void
     */
    protected function smtp_end()
    {
        $this->send_command($this->smtp_keep_alive ? 'reset' : 'quit');
    }
    /**
     * @return bool|string
     */
    protected function smtp_connect()
    {
        if ($this->is_smtp_connected()) {
            return true;
        }
        $ssl = '';
        // Connection to port 465 should use implicit TLS (without STARTTLS)
        // as per RFC 8314.
        if ($this->smtp_port === 465) {
            $ssl = 'tls://';
        }
        // But if $SMTPCrypto is set to `ssl`, SSL can be used.
        if ($this->smtp_crypto === 'ssl') {
            $ssl = 'ssl://';
        }
        $this->smtp_connect = fsockopen($ssl . $this->smtp_host, $this->smtp_port, $errno, $errstr, $this->smtp_timeout);
        if (!$this->is_smtp_connected()) {
            $this->set_error_message(lang('Email.SMTPError', [$errno . ' ' . $errstr]));
            return false;
        }
        stream_set_timeout($this->smtp_connect, $this->smtp_timeout);
        $this->set_error_message($this->get_smtp_data());
        if ($this->smtp_crypto === 'tls') {
            $this->send_command('hello');
            $this->send_command('starttls');
            $crypto = stream_socket_enable_crypto($this->smtp_connect, true, Stream_crypto_method_tl_Sv1_0_client | Stream_crypto_method_tl_Sv1_1_client | Stream_crypto_method_tl_Sv1_2_client | Stream_crypto_method_tl_Sv1_3_client);
            if ($crypto !== true) {
                $this->set_error_message(lang('Email.SMTPError', [$this->get_smtp_data()]));
                return false;
            }
        }
        return $this->send_command('hello');
    }
    /**
     * @param string $cmd
     * @param string $data
     *
     * @return bool
     */
    protected function send_command($cmd, $data = '')
    {
        switch ($cmd) {
            case 'hello':
                if ($this->smtp_auth || $this->get_encoding() === '8bit') {
                    $this->send_data('EHLO ' . $this->get_hostname());
                } else {
                    $this->send_data('HELO ' . $this->get_hostname());
                }
                $resp = 250;
                break;
            case 'starttls':
                $this->send_data('STARTTLS');
                $resp = 220;
                break;
            case 'from':
                $this->send_data('MAIL FROM:<' . $data . '>');
                $resp = 250;
                break;
            case 'to':
                if ($this->DSN) {
                    $this->send_data('RCPT TO:<' . $data . '> NOTIFY=SUCCESS,DELAY,FAILURE ORCPT=rfc822;' . $data);
                } else {
                    $this->send_data('RCPT TO:<' . $data . '>');
                }
                $resp = 250;
                break;
            case 'data':
                $this->send_data('DATA');
                $resp = 354;
                break;
            case 'reset':
                $this->send_data('RSET');
                $resp = 250;
                break;
            case 'quit':
                $this->send_data('QUIT');
                $resp = 221;
                break;
            default:
                $resp = null;
        }
        $reply = $this->get_smtp_data();
        $this->debug_message[] = '<pre>' . $cmd . ': ' . $reply . '</pre>';
        $this->debug_message_raw[] = $cmd . ': ' . $reply;
        if ($resp === null || (int) static::substr($reply, 0, 3) !== $resp) {
            $this->set_error_message(lang('Email.SMTPError', [$reply]));
            return false;
        }
        if ($cmd === 'quit') {
            fclose($this->smtp_connect);
        }
        return true;
    }
    /**
     * @return bool
     */
    protected function smtp_authenticate()
    {
        if (!$this->smtp_auth) {
            return true;
        }
        // If no username or password is set
        if ($this->smtp_user === '' || $this->smtp_pass === '') {
            $this->set_error_message(lang('Email.noSMTPAuth'));
            return false;
        }
        // normalize in case user entered capital words LOGIN/PLAIN
        $this->smtp_auth_method = strtolower($this->smtp_auth_method);
        // Validate supported authentication methods
        if (!in_array($this->smtp_auth_method, ['login', 'plain'], true)) {
            $this->set_error_message(lang('Email.invalidSMTPAuthMethod', [$this->smtp_auth_method]));
            return false;
        }
        $upper_auth_method = strtoupper($this->smtp_auth_method);
        // send initial 'AUTH' command
        $this->send_data('AUTH ' . $upper_auth_method);
        $reply = $this->get_smtp_data();
        if (str_starts_with($reply, '503')) {
            // Already authenticated
            return true;
        }
        // if 'AUTH' command is unsuported by the server
        if (!str_starts_with($reply, '334')) {
            $this->set_error_message(lang('Email.failureSMTPAuthMethod', [$upper_auth_method]));
            return false;
        }
        switch ($this->smtp_auth_method) {
            case 'login':
                $this->send_data(base64_encode($this->smtp_user));
                $reply = $this->get_smtp_data();
                if (!str_starts_with($reply, '334')) {
                    $this->set_error_message(lang('Email.SMTPAuthUsername', [$reply]));
                    return false;
                }
                $this->send_data(base64_encode($this->smtp_pass));
                break;
            case 'plain':
                // send credentials as the single second command
                $auth_string = "\x00" . $this->smtp_user . "\x00" . $this->smtp_pass;
                $this->send_data(base64_encode($auth_string));
                break;
        }
        $reply = $this->get_smtp_data();
        if (!str_starts_with($reply, '235')) {
            // Authentication failed
            $error_message = $this->smtp_auth_method === 'plain' ? 'Email.SMTPAuthCredentials' : 'Email.SMTPAuthPassword';
            $this->set_error_message(lang($error_message, [$reply]));
            return false;
        }
        if ($this->smtp_keep_alive) {
            $this->smtp_auth = false;
            // Prevent re-authentication for keep-alive sessions
        }
        return true;
    }
    /**
     * @param string $data
     *
     * @return bool
     */
    protected function send_data($data)
    {
        $data .= $this->newline;
        $result = null;
        for ($written = $timestamp = 0, $length = static::strlen($data); $written < $length; $written += $result) {
            if (($result = fwrite($this->smtp_connect, static::substr($data, $written))) === false) {
                break;
            }
            // See https://bugs.php.net/bug.php?id=39598 and http://php.net/manual/en/function.fwrite.php#96951
            if ($result === 0) {
                if ($timestamp === 0) {
                    $timestamp = Time::now()->get_timestamp();
                } elseif ($timestamp < Time::now()->get_timestamp() - $this->smtp_timeout) {
                    $result = false;
                    break;
                }
                usleep(250000);
                continue;
            }
            $timestamp = 0;
        }
        if (!is_int($result)) {
            $this->set_error_message(lang('Email.SMTPDataFailure', [$data]));
            return false;
        }
        return true;
    }
    /**
     * @return string
     */
    protected function get_smtp_data()
    {
        $data = '';
        while ($str = fgets($this->smtp_connect, 512)) {
            $data .= $str;
            if ($str[3] === ' ') {
                break;
            }
        }
        return $data;
    }
    /**
     * There are only two legal types of hostname - either a fully
     * qualified domain name (eg: "mail.example.com") or an IP literal
     * (eg: "[1.2.3.4]").
     *
     * @see https://tools.ietf.org/html/rfc5321#section-2.3.5
     * @see http://cbl.abuseat.org/namingproblems.html
     *
     * @return string
     */
    protected function get_hostname()
    {
        $superglobals = service('superglobals');
        $server_name = $superglobals->server('SERVER_NAME');
        if (!in_array($server_name, [null, ''], true)) {
            return $server_name;
        }
        $server_addr = $superglobals->server('SERVER_ADDR');
        if (!in_array($server_addr, [null, ''], true)) {
            return '[' . $server_addr . ']';
        }
        $hostname = gethostname();
        if ($hostname !== false) {
            return $hostname;
        }
        return '[127.0.0.1]';
    }
    /**
     * @param array|string $include List of raw data chunks to include in the output
     *                              Valid options are: 'headers', 'subject', 'body'
     *
     * @return string
     */
    public function print_debugger($include = ['headers', 'subject', 'body'])
    {
        $msg = implode('', $this->debug_message);
        // Determine which parts of our raw data needs to be printed
        $raw_data = '';
        if (!is_array($include)) {
            $include = [$include];
        }
        if (in_array('headers', $include, true)) {
            $raw_data = htmlspecialchars($this->header_str) . "\n";
        }
        if (in_array('subject', $include, true)) {
            $raw_data .= htmlspecialchars($this->subject) . "\n";
        }
        if (in_array('body', $include, true)) {
            $raw_data .= htmlspecialchars($this->final_body);
        }
        return $msg . ($raw_data === '' ? '' : '<pre>' . $raw_data . '</pre>');
    }
    /**
     * Returns raw debug messages
     */
    private function print_debugger_raw(): string
    {
        return implode("\n", $this->debug_message_raw);
    }
    /**
     * @param string $msg
     *
     * @return void
     */
    protected function set_error_message($msg)
    {
        $this->debug_message[] = $msg . '<br>';
        $this->debug_message_raw[] = $msg;
    }
    /**
     * Mime Types
     *
     * @param string $ext
     *
     * @return string
     */
    protected function mime_types($ext = '')
    {
        $mime = Mimes::guess_type_from_extension(strtolower($ext));
        return empty($mime) ? 'application/x-unknown-content-type' : $mime;
    }
    public function __destruct()
    {
        if ($this->is_smtp_connected()) {
            try {
                $this->send_command('quit');
            } catch (ErrorException $e) {
                $protocol = $this->get_protocol();
                $method = 'sendWith' . ucfirst($protocol);
                log_message('error', 'Email: ' . $method . ' throwed ' . $e);
            }
        }
    }
    /**
     * Byte-safe strlen()
     *
     * @param string $str
     *
     * @return int
     */
    protected static function strlen($str)
    {
        return static::$func_overload ? mb_strlen($str, '8bit') : strlen($str);
    }
    /**
     * Byte-safe substr()
     *
     * @param string   $str
     * @param int      $start
     * @param int|null $length
     *
     * @return string
     */
    protected static function substr($str, $start, $length = null)
    {
        if (static::$func_overload) {
            return mb_substr($str, $start, $length, '8bit');
        }
        return isset($length) ? substr($str, $start, $length) : substr($str, $start);
    }
    /**
     * Determines the values that should be stored in $archive.
     *
     * @return array The updated archive values
     */
    protected function set_archive_values(): array
    {
        // Get property values and add anything prepped in tmpArchive
        $this->archive = array_merge(get_object_vars($this), $this->tmp_archive);
        unset($this->archive['archive']);
        // Clear tmpArchive for next run
        $this->tmp_archive = [];
        return $this->archive;
    }
    /**
     * Checks if there is an active SMTP connection.
     *
     * @return bool True if SMTP connection is established and open, false otherwise
     */
    protected function is_smtp_connected(): bool
    {
        return $this->smtp_connect !== null && $this->smtp_connect !== false && get_debug_type($this->smtp_connect) !== 'resource (closed)';
    }
}