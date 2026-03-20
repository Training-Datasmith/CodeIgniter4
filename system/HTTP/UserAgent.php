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

use Config\User_Agents;
use Stringable;
/**
 * Abstraction for an HTTP user agent
 *
 * @see \CodeIgniter\HTTP\UserAgentTest
 */
class User_Agent implements Stringable
{
    /**
     * Current user-agent
     *
     * @var string
     */
    protected $agent = '';
    /**
     * Flag for if the user-agent belongs to a browser
     *
     * @var bool
     */
    protected $is_browser = false;
    /**
     * Flag for if the user-agent is a robot
     *
     * @var bool
     */
    protected $is_robot = false;
    /**
     * Flag for if the user-agent is a mobile browser
     *
     * @var bool
     */
    protected $is_mobile = false;
    /**
     * Holds the config file contents.
     *
     * @var UserAgents
     */
    protected $config;
    /**
     * Current user-agent platform
     *
     * @var string
     */
    protected $platform = '';
    /**
     * Current user-agent browser
     *
     * @var string
     */
    protected $browser = '';
    /**
     * Current user-agent version
     *
     * @var string
     */
    protected $version = '';
    /**
     * Current user-agent mobile name
     *
     * @var string
     */
    protected $mobile = '';
    /**
     * Current user-agent robot name
     *
     * @var string
     */
    protected $robot = '';
    /**
     * HTTP Referer
     *
     * @var bool|string|null
     */
    protected $referrer;
    /**
     * Constructor
     *
     * Sets the User Agent and runs the compilation routine
     */
    public function __construct(?User_Agents $config = null)
    {
        $this->config = $config ?? config(User_Agents::class);
        $user_agent = service('superglobals')->server('HTTP_USER_AGENT');
        if ($user_agent !== null) {
            $this->agent = trim($user_agent);
            $this->compile_data();
        }
    }
    /**
     * Is Browser
     */
    public function is_browser(?string $key = null): bool
    {
        if (!$this->is_browser) {
            return false;
        }
        // No need to be specific, it's a browser
        if ((string) $key === '') {
            return true;
        }
        // Check for a specific browser
        return isset($this->config->browsers[$key]) && $this->browser === $this->config->browsers[$key];
    }
    /**
     * Is Robot
     */
    public function is_robot(?string $key = null): bool
    {
        if (!$this->is_robot) {
            return false;
        }
        // No need to be specific, it's a robot
        if ((string) $key === '') {
            return true;
        }
        // Check for a specific robot
        return isset($this->config->robots[$key]) && $this->robot === $this->config->robots[$key];
    }
    /**
     * Is Mobile
     */
    public function is_mobile(?string $key = null): bool
    {
        if (!$this->is_mobile) {
            return false;
        }
        // No need to be specific, it's a mobile
        if ((string) $key === '') {
            return true;
        }
        // Check for a specific robot
        return isset($this->config->mobiles[$key]) && $this->mobile === $this->config->mobiles[$key];
    }
    /**
     * Is this a referral from another site?
     */
    public function is_referral(): bool
    {
        if (!isset($this->referrer)) {
            $referer = service('superglobals')->server('HTTP_REFERER');
            if ($referer === null || $referer === '') {
                $this->referrer = false;
            } else {
                $referer_host = @parse_url($referer, PHP_URL_HOST);
                $own_host = parse_url(\base_url(), PHP_URL_HOST);
                $this->referrer = $referer_host && $referer_host !== $own_host;
            }
        }
        return $this->referrer;
    }
    /**
     * Agent String
     */
    public function get_agent_string(): string
    {
        return $this->agent;
    }
    /**
     * Get Platform
     */
    public function get_platform(): string
    {
        return $this->platform;
    }
    /**
     * Get Browser Name
     */
    public function get_browser(): string
    {
        return $this->browser;
    }
    /**
     * Get the Browser Version
     */
    public function get_version(): string
    {
        return $this->version;
    }
    /**
     * Get The Robot Name
     */
    public function get_robot(): string
    {
        return $this->robot;
    }
    /**
     * Get the Mobile Device
     */
    public function get_mobile(): string
    {
        return $this->mobile;
    }
    /**
     * Get the referrer
     */
    public function get_referrer(): string
    {
        $referrer = service('superglobals')->server('HTTP_REFERER');
        return $referrer === null ? '' : trim($referrer);
    }
    /**
     * Parse a custom user-agent string
     *
     * @return void
     */
    public function parse(string $string)
    {
        // Reset values
        $this->is_browser = false;
        $this->is_robot = false;
        $this->is_mobile = false;
        $this->browser = '';
        $this->version = '';
        $this->mobile = '';
        $this->robot = '';
        // Set the new user-agent string and parse it, unless empty
        $this->agent = $string;
        if ($string !== '') {
            $this->compile_data();
        }
    }
    /**
     * Compile the User Agent Data
     *
     * @return void
     */
    protected function compile_data()
    {
        $this->set_platform();
        foreach (['setRobot', 'setBrowser', 'setMobile'] as $function) {
            if ($this->{$function}()) {
                break;
            }
        }
    }
    /**
     * Set the Platform
     */
    protected function set_platform(): bool
    {
        if (is_array($this->config->platforms) && $this->config->platforms !== []) {
            foreach ($this->config->platforms as $key => $val) {
                if (preg_match('|' . preg_quote($key, '|') . '|i', $this->agent)) {
                    $this->platform = $val;
                    return true;
                }
            }
        }
        $this->platform = 'Unknown Platform';
        return false;
    }
    /**
     * Set the Browser
     */
    protected function set_browser(): bool
    {
        if (is_array($this->config->browsers) && $this->config->browsers !== []) {
            foreach ($this->config->browsers as $key => $val) {
                if (preg_match('|' . $key . '.*?([0-9\.]+)|i', $this->agent, $match)) {
                    $this->is_browser = true;
                    $this->version = $match[1];
                    $this->browser = $val;
                    $this->set_mobile();
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Set the Robot
     */
    protected function set_robot(): bool
    {
        if (is_array($this->config->robots) && $this->config->robots !== []) {
            foreach ($this->config->robots as $key => $val) {
                if (preg_match('|' . preg_quote($key, '|') . '|i', $this->agent)) {
                    $this->is_robot = true;
                    $this->robot = $val;
                    $this->set_mobile();
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Set the Mobile Device
     */
    protected function set_mobile(): bool
    {
        if (is_array($this->config->mobiles) && $this->config->mobiles !== []) {
            foreach ($this->config->mobiles as $key => $val) {
                if (false !== stripos($this->agent, $key)) {
                    $this->is_mobile = true;
                    $this->mobile = $val;
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Outputs the original Agent String when cast as a string.
     */
    public function __toString(): string
    {
        return $this->get_agent_string();
    }
}