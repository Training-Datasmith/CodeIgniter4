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

use Code_Igniter\Exceptions\InvalidArgumentException;
use Config\App;
use Config\Content_Security_Policy as ContentSecurityPolicyConfig;
/**
 * Provides tools for working with the Content-Security-Policy header
 * to help defeat XSS attacks.
 *
 * @see http://www.w3.org/TR/CSP/
 * @see http://www.html5rocks.com/en/tutorials/security/content-security-policy/
 * @see http://content-security-policy.com/
 * @see https://www.owasp.org/index.php/Content_Security_Policy
 * @see \CodeIgniter\HTTP\ContentSecurityPolicyTest
 */
class Content_Security_Policy
{
    private const DIRECTIVES_ALLOWING_SOURCE_LISTS = ['base-uri' => 'baseURI', 'child-src' => 'childSrc', 'connect-src' => 'connectSrc', 'default-src' => 'defaultSrc', 'font-src' => 'fontSrc', 'form-action' => 'formAction', 'frame-ancestors' => 'frameAncestors', 'frame-src' => 'frameSrc', 'img-src' => 'imageSrc', 'media-src' => 'mediaSrc', 'object-src' => 'objectSrc', 'plugin-types' => 'pluginTypes', 'script-src' => 'scriptSrc', 'style-src' => 'styleSrc', 'sandbox' => 'sandbox', 'manifest-src' => 'manifestSrc', 'script-src-elem' => 'scriptSrcElem', 'script-src-attr' => 'scriptSrcAttr', 'style-src-elem' => 'styleSrcElem', 'style-src-attr' => 'styleSrcAttr', 'worker-src' => 'workerSrc'];
    /**
     * Map of CSP directives to this class's properties.
     *
     * @var array<string, string>
     */
    protected array $directives = [...self::DIRECTIVES_ALLOWING_SOURCE_LISTS, 'report-uri' => 'reportURI', 'report-to' => 'reportTo'];
    /**
     * The `base-uri` directive restricts the URLs that can be used to specify the document base URL.
     *
     * @var array<string, bool>|string|null
     */
    protected $base_uri = [];
    /**
     * The `child-src` directive governs the creation of nested browsing contexts as well
     * as Worker execution contexts.
     *
     * @var array<string, bool>|string
     */
    protected $child_src = [];
    /**
     * The `connect-src` directive restricts which URLs the protected resource can load using script interfaces.
     *
     * @var array<string, bool>|string
     */
    protected $connect_src = [];
    /**
     * The `default-src` directive sets a default source list for a number of directives.
     *
     * @var array<string, bool>|string|null
     */
    protected $default_src = [];
    /**
     * The `font-src` directive restricts from where the protected resource can load fonts.
     *
     * @var array<string, bool>|string
     */
    protected $font_src = [];
    /**
     * The `form-action` directive restricts which URLs can be used as the action of HTML form elements.
     *
     * @var array<string, bool>|string
     */
    protected $form_action = [];
    /**
     * The `frame-ancestors` directive indicates whether the user agent should allow embedding
     * the resource using a `frame`, `iframe`, `object`, `embed` or `applet` element,
     * or equivalent functionality in non-HTML resources.
     *
     * @var array<string, bool>|string
     */
    protected $frame_ancestors = [];
    /**
     * The `frame-src` directive restricts the URLs which may be loaded into child navigables.
     *
     * @var array<string, bool>|string
     */
    protected $frame_src = [];
    /**
     * The `img-src` directive restricts from where the protected resource can load images.
     *
     * @var array<string, bool>|string
     */
    protected $image_src = [];
    /**
     * The `media-src` directive restricts from where the protected resource can load video,
     * audio, and associated text tracks.
     *
     * @var array<string, bool>|string
     */
    protected $media_src = [];
    /**
     * The `object-src` directive restricts from where the protected resource can load plugins.
     *
     * @var array<string, bool>|string
     */
    protected $object_src = [];
    /**
     * The `plugin-types` directive restricts the set of plugins that can be invoked by the
     * protected resource by limiting the types of resources that can be embedded.
     *
     * @var array<string, bool>|string
     */
    protected $plugin_types = [];
    /**
     * The `script-src` directive restricts which scripts the protected resource can execute.
     *
     * @var array<string, bool>|string
     */
    protected $script_src = [];
    /**
     * The `style-src` directive restricts which styles the user may applies to the protected resource.
     *
     * @var array<string, bool>|string
     */
    protected $style_src = [];
    /**
     * The `sandbox` directive specifies an HTML sandbox policy that the user agent applies to the protected resource.
     *
     * @var array<string, bool>|string
     */
    protected $sandbox = [];
    /**
     * The `report-uri` directive specifies a URL to which the user agent sends reports about policy violation.
     *
     * @var string|null
     */
    protected $report_uri;
    /**
     * The `report-to` directive specifies a named group in a Reporting API
     * endpoint to which the user agent sends reports about policy violation.
     */
    protected ?string $report_to = null;
    // --------------------------------------------------------------
    // CSP Level 3 Directives
    // --------------------------------------------------------------
    /**
     * The `manifest-src` directive restricts the URLs from which application manifests may be loaded.
     *
     * @var array<string, bool>|string
     */
    protected $manifest_src = [];
    /**
     * The `script-src-elem` directive applies to all script requests and script blocks.
     *
     * @var array<string, bool>|string
     */
    protected array|string $script_src_elem = [];
    /**
     * The `script-src-attr` directive applies to event handlers and, if present,
     * it will override the `script-src` directive for relevant checks.
     *
     * @var array<string, bool>|string
     */
    protected array|string $script_src_attr = [];
    /**
     * The `style-src-elem` directive governs the behaviour of styles except
     * for styles defined in inline attributes.
     *
     * @var array<string, bool>|string
     */
    protected array|string $style_src_elem = [];
    /**
     * The `style-src-attr` directive governs the behaviour of style attributes.
     *
     * @var array<string, bool>|string
     */
    protected array|string $style_src_attr = [];
    /**
     * The `worker-src` directive restricts the URLs which may be loaded as a `Worker`,
     * `SharedWorker`, or `ServiceWorker`.
     *
     * @var array<string, bool>|string
     */
    protected array|string $worker_src = [];
    /**
     * Instructs user agents to rewrite URL schemes by changing HTTP to HTTPS.
     *
     * @var bool
     */
    protected $upgrade_insecure_requests = false;
    /**
     * Set to `true` to make all directives report-only instead of enforced.
     *
     * @var bool
     */
    protected $report_only = false;
    /**
     * Set of valid keyword-sources.
     *
     * @see https://www.w3.org/TR/CSP3/#source-expression
     *
     * @var list<string>
     */
    protected $valid_sources = [
        // CSP2 keywords
        'self',
        'none',
        'unsafe-inline',
        'unsafe-eval',
        // CSP3 keywords
        'strict-dynamic',
        'unsafe-hashes',
        'report-sample',
        'unsafe-allow-redirects',
        'wasm-unsafe-eval',
        'trusted-types-eval',
        'report-sha256',
        'report-sha384',
        'report-sha512',
    ];
    /**
     * Set of nonces generated.
     *
     * @var list<string>
     *
     * @deprecated 4.7.0 Never used.
     */
    protected $nonces = [];
    /**
     * Nonce for style tags.
     *
     * @var string|null
     */
    protected $style_nonce;
    /**
     * Nonce for script tags.
     *
     * @var string|null
     */
    protected $script_nonce;
    /**
     * Nonce placeholder for style tags.
     *
     * @var string
     */
    protected $style_nonce_tag = '{csp-style-nonce}';
    /**
     * Nonce placeholder for script tags.
     *
     * @var string
     */
    protected $script_nonce_tag = '{csp-script-nonce}';
    /**
     * Replace nonce tags automatically?
     *
     * @var bool
     */
    protected $auto_nonce = true;
    /**
     * An array of header info since we have to build
     * ourselves before passing to a Response object.
     *
     * @var array<string, string>
     */
    protected $temp_headers = [];
    /**
     * An array of header info to build that should only be reported.
     *
     * @var array<string, string>
     */
    protected $report_only_headers = [];
    /**
     * Whether Content Security Policy is being enforced.
     *
     * @var bool
     */
    protected $csp_enabled = false;
    /**
     * Map of reporting endpoints to their URLs.
     *
     * @var array<string, string>
     */
    private array $reporting_endpoints = [];
    /**
     * Stores our default values from the Config file.
     */
    public function __construct(Content_Security_Policy_Config $config)
    {
        $this->csp_enabled = config(App::class)->csp_enabled;
        foreach (get_object_vars($config) as $setting => $value) {
            if (!property_exists($this, $setting)) {
                continue;
            }
            if (in_array($setting, self::DIRECTIVES_ALLOWING_SOURCE_LISTS, true) && is_array($value) && array_is_list($value)) {
                // Config sets these directives as `list<string>|string`
                // but we need them as `array<string, bool>` internally.
                $this->{$setting} = array_combine($value, array_fill(0, count($value), $this->report_only));
                continue;
            }
            $this->{$setting} = $value;
        }
        if (!is_array($this->style_src)) {
            $this->style_src = [$this->style_src => $this->report_only];
        }
        if (!is_array($this->script_src)) {
            $this->script_src = [$this->script_src => $this->report_only];
        }
    }
    /**
     * Whether Content Security Policy is being enforced.
     */
    public function enabled(): bool
    {
        return $this->csp_enabled;
    }
    /**
     * Get the nonce for the style tag.
     */
    public function get_style_nonce(): string
    {
        if ($this->style_nonce === null) {
            $this->style_nonce = base64_encode(random_bytes(12));
            $this->add_style_src('nonce-' . $this->style_nonce);
            if ($this->style_src_elem !== []) {
                $this->add_style_src_elem('nonce-' . $this->style_nonce);
            }
        }
        return $this->style_nonce;
    }
    /**
     * Get the nonce for the script tag.
     */
    public function get_script_nonce(): string
    {
        if ($this->script_nonce === null) {
            $this->script_nonce = base64_encode(random_bytes(12));
            $this->add_script_src('nonce-' . $this->script_nonce);
            if ($this->script_src_elem !== []) {
                $this->add_script_src_elem('nonce-' . $this->script_nonce);
            }
        }
        return $this->script_nonce;
    }
    /**
     * Compiles and sets the appropriate headers in the request.
     *
     * Should be called just prior to sending the response to the user agent.
     *
     * @return void
     */
    public function finalize(Response_Interface $response)
    {
        $this->generate_nonces($response);
        $this->build_headers($response);
    }
    /**
     * If TRUE, nothing will be restricted. Instead all violations will
     * be reported to the reportURI for monitoring. This is useful when
     * you are just starting to implement the policy, and will help
     * determine what errors need to be addressed before you turn on
     * all filtering.
     *
     * @return $this
     */
    public function report_only(bool $value = true)
    {
        $this->report_only = $value;
        return $this;
    }
    /**
     * Adds a new value to the `base-uri` directive.
     *
     * `base-uri` restricts the URLs that can appear in a page's <base> element.
     *
     * @see http://www.w3.org/TR/CSP/#directive-base-uri
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_base_uri($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'baseURI', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `child-src` directive.
     *
     * `child-src` lists the URLs for workers and embedded frame contents.
     * For example: child-src https://youtube.com would enable embedding
     * videos from YouTube but not from other origins.
     *
     * @see http://www.w3.org/TR/CSP/#directive-child-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_child_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'childSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `connect-src` directive.
     *
     * `connect-src` limits the origins to which you can connect
     * (via XHR, WebSockets, and EventSource).
     *
     * @see http://www.w3.org/TR/CSP/#directive-connect-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_connect_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'connectSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `default-src` directive.
     *
     * `default-src` is the URI that is used for many of the settings when
     * no other source has been set.
     *
     * @see http://www.w3.org/TR/CSP/#directive-default-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function set_default_src($uri, ?bool $explicit_reporting = null)
    {
        $this->default_src = [(string) $uri => $explicit_reporting ?? $this->report_only];
        return $this;
    }
    /**
     * Adds a new value to the `font-src` directive.
     *
     * `font-src` specifies the origins that can serve web fonts.
     *
     * @see http://www.w3.org/TR/CSP/#directive-font-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_font_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'fontSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `form-action` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-form-action
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_form_action($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'formAction', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `frame-ancestors` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-frame-ancestors
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_frame_ancestor($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'frameAncestors', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `frame-src` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-frame-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_frame_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'frameSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `img-src` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-img-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_image_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'imageSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `media-src` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-media-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_media_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'mediaSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `manifest-src` directive.
     *
     * @see https://www.w3.org/TR/CSP/#directive-manifest-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_manifest_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'manifestSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `object-src` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-object-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_object_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'objectSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `plugin-types` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-plugin-types
     *
     * @param list<string>|string $mime
     *
     * @return $this
     */
    public function add_plugin_type($mime, ?bool $explicit_reporting = null)
    {
        $this->add_option($mime, 'pluginTypes', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `sandbox` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-sandbox
     *
     * @param list<string>|string $flags
     *
     * @return $this
     */
    public function add_sandbox($flags, ?bool $explicit_reporting = null)
    {
        $this->add_option($flags, 'sandbox', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `script-src` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-script-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_script_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'scriptSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `script-src-elem` directive.
     *
     * @see https://www.w3.org/TR/CSP/#directive-script-src-elem
     *
     * @param list<string>|string $uri
     */
    public function add_script_src_elem(array|string $uri, ?bool $explicit_reporting = null): static
    {
        $this->add_option($uri, 'scriptSrcElem', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `script-src-attr` directive.
     *
     * @see https://www.w3.org/TR/CSP/#directive-script-src-attr
     *
     * @param list<string>|string $uri
     */
    public function add_script_src_attr(array|string $uri, ?bool $explicit_reporting = null): static
    {
        $this->add_option($uri, 'scriptSrcAttr', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `style-src` directive.
     *
     * @see http://www.w3.org/TR/CSP/#directive-style-src
     *
     * @param list<string>|string $uri
     *
     * @return $this
     */
    public function add_style_src($uri, ?bool $explicit_reporting = null)
    {
        $this->add_option($uri, 'styleSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `style-src-elem` directive.
     *
     * @see https://www.w3.org/TR/CSP/#directive-style-src-elem
     *
     * @param list<string>|string $uri
     */
    public function add_style_src_elem(array|string $uri, ?bool $explicit_reporting = null): static
    {
        $this->add_option($uri, 'styleSrcElem', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `style-src-attr` directive.
     *
     * @see https://www.w3.org/TR/CSP/#directive-style-src-attr
     *
     * @param list<string>|string $uri
     */
    public function add_style_src_attr(array|string $uri, ?bool $explicit_reporting = null): static
    {
        $this->add_option($uri, 'styleSrcAttr', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Adds a new value to the `worker-src` directive.
     *
     * @see https://www.w3.org/TR/CSP/#directive-worker-src
     *
     * @param list<string>|string $uri
     */
    public function add_worker_src($uri, ?bool $explicit_reporting = null): static
    {
        $this->add_option($uri, 'workerSrc', $explicit_reporting ?? $this->report_only);
        return $this;
    }
    /**
     * Sets whether the user agents should rewrite URL schemes, changing HTTP to HTTPS.
     *
     * @return $this
     */
    public function upgrade_insecure_requests(bool $value = true)
    {
        $this->upgrade_insecure_requests = $value;
        return $this;
    }
    /**
     * Specifies a URL where a browser will send reports when a content
     * security policy is violated.
     *
     * @see http://www.w3.org/TR/CSP/#directive-report-uri
     *
     * @param string $uri URL to send reports. Set `''` if you want to remove
     *                    this directive at runtime.
     *
     * @return $this
     */
    public function set_report_uri(string $uri)
    {
        $this->report_uri = $uri;
        return $this;
    }
    /**
     * Specifies a named group in a Reporting API endpoint to which the user
     * agent sends reports about policy violation.
     *
     * @see https://www.w3.org/TR/CSP/#directive-report-to
     *
     * @param string $endpoint The name of the reporting endpoint. Set `''` if you
     *                         want to remove this directive at runtime.
     */
    public function set_report_to_endpoint(string $endpoint): static
    {
        if ($endpoint === '') {
            $this->report_uri = null;
            $this->report_to = null;
            return $this;
        }
        if (!array_key_exists($endpoint, $this->reporting_endpoints)) {
            throw new InvalidArgumentException(sprintf('The reporting endpoint "%s" has not been defined.', $endpoint));
        }
        $this->report_uri = $this->reporting_endpoints[$endpoint];
        // for BC with browsers that do not support `report-to`
        $this->report_to = $endpoint;
        return $this;
    }
    /**
     * Adds reporting endpoints to the `Reporting-Endpoints` header.
     *
     * @param array<string, string> $endpoint
     */
    public function add_reporting_endpoints(array $endpoint): static
    {
        foreach ($endpoint as $name => $url) {
            $this->reporting_endpoints[$name] = $url;
        }
        return $this;
    }
    /**
     * DRY method to add an string or array to a class property.
     *
     * @param list<string>|string $options
     *
     * @return void
     */
    protected function add_option($options, string $target, ?bool $explicit_reporting = null)
    {
        // Ensure we have an array to work with...
        if (is_string($this->{$target})) {
            $this->{$target} = [$this->{$target} => $this->report_only];
        }
        $options = is_array($options) ? $options : [$options];
        foreach ($options as $option) {
            $this->{$target}[$option] = $explicit_reporting ?? $this->report_only;
        }
    }
    /**
     * Scans the body of the request message and replaces any nonce
     * placeholders with actual nonces, that we'll then add to our
     * headers.
     *
     * @return void
     */
    protected function generate_nonces(Response_Interface $response)
    {
        if ($this->enabled() && !$this->auto_nonce) {
            return;
        }
        $body = (string) $response->get_body();
        if ($body === '') {
            return;
        }
        // Escape quotes for JSON responses to prevent corrupting the JSON body
        $json_escape = str_contains($response->get_header_line('Content-Type'), 'json');
        // Replace style and script placeholders with nonces
        $pattern = sprintf('/(%s|%s)/', preg_quote($this->style_nonce_tag, '/'), preg_quote($this->script_nonce_tag, '/'));
        $body = preg_replace_callback($pattern, function ($match) use ($json_escape): string {
            if (!$this->enabled()) {
                return '';
            }
            $nonce = $match[0] === $this->style_nonce_tag ? $this->get_style_nonce() : $this->get_script_nonce();
            $attr = 'nonce="' . $nonce . '"';
            return $json_escape ? str_replace('"', '\"', $attr) : $attr;
        }, $body);
        $response->set_body($body);
    }
    /**
     * Based on the current state of the elements, will add the appropriate
     * Content-Security-Policy and Content-Security-Policy-Report-Only headers
     * with their values to the response object.
     *
     * @return void
     */
    protected function build_headers(Response_Interface $response)
    {
        if (!$this->enabled()) {
            return;
        }
        $response->set_header('Content-Security-Policy', []);
        $response->set_header('Content-Security-Policy-Report-Only', []);
        $response->set_header('Reporting-Endpoints', []);
        if (in_array($this->base_uri, ['', null, []], true)) {
            $this->base_uri = 'self';
        }
        if (in_array($this->default_src, ['', null, []], true)) {
            $this->default_src = 'self';
        }
        foreach ($this->directives as $name => $property) {
            if ($name === 'report-uri' && (string) $this->report_uri === '') {
                continue;
            }
            if ($name === 'report-to' && (string) $this->report_to === '') {
                continue;
            }
            if ($this->{$property} !== null) {
                $this->add_to_header($name, $this->{$property});
            }
        }
        // Compile our own header strings here since if we just
        // append it to the response, it will be joined with
        // commas, not semi-colons as we need.
        if ($this->reporting_endpoints !== []) {
            $endpoints = [];
            foreach ($this->reporting_endpoints as $name => $url) {
                $endpoints[] = trim("{$name}=\"{$url}\"");
            }
            $response->append_header('Reporting-Endpoints', implode(', ', $endpoints));
            $this->reporting_endpoints = [];
        }
        if ($this->temp_headers !== []) {
            $header = [];
            foreach ($this->temp_headers as $name => $value) {
                $header[] = trim("{$name} {$value}");
            }
            if ($this->upgrade_insecure_requests) {
                $header[] = 'upgrade-insecure-requests';
            }
            $response->append_header('Content-Security-Policy', implode('; ', $header));
            $this->temp_headers = [];
        }
        if ($this->report_only_headers !== []) {
            $header = [];
            foreach ($this->report_only_headers as $name => $value) {
                $header[] = trim("{$name} {$value}");
            }
            $response->append_header('Content-Security-Policy-Report-Only', implode('; ', $header));
            $this->report_only_headers = [];
        }
    }
    /**
     * Adds a directive and its options to the appropriate header. The $values
     * array might have options that are geared toward either the regular or the
     * reportOnly header, since it's viable to have both simultaneously.
     *
     * @param array<string, bool>|string $values
     *
     * @return void
     */
    protected function add_to_header(string $name, $values = null)
    {
        if (is_string($values)) {
            $values = [$values => $this->report_only];
        }
        $sources = [];
        $report_sources = [];
        foreach ($values as $value => $report_only) {
            if (in_array($value, $this->valid_sources, true) || str_starts_with($value, 'nonce-') || str_starts_with($value, 'sha256-') || str_starts_with($value, 'sha384-') || str_starts_with($value, 'sha512-')) {
                $value = "'{$value}'";
            }
            if ($report_only) {
                $report_sources[] = $value;
            } else {
                $sources[] = $value;
            }
        }
        if ($sources !== []) {
            $this->temp_headers[$name] = implode(' ', $sources);
        }
        if ($report_sources !== []) {
            $this->report_only_headers[$name] = implode(' ', $report_sources);
        }
    }
    public function clear_directive(string $directive): void
    {
        if (!array_key_exists($directive, $this->directives)) {
            return;
        }
        if ($directive === 'report-uri') {
            $this->report_uri = null;
            return;
        }
        if ($directive === 'report-to') {
            $this->report_uri = null;
            $this->report_to = null;
            return;
        }
        $this->{$this->directives[$directive]} = [];
    }
}