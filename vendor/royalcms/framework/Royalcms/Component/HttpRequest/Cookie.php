<?php namespace Royalcms\Component\HttpRequest;

use Royalcms\Component\Support\Facades\Hook;

/**
 * Internal representation of a single cookie.
 *
 * Returned cookies are represented using this class, and when cookies are set, if they are not
 * already a Component_Http_Cookie() object, then they are turned into one.
 *
 * @todo The Royalcms convention is to use underscores instead of camelCase for function and method
 * names. Need to switch to use underscores instead for the methods.
 *
 * @package Component
 * @subpackage HTTP
 * @since 2.8.0
 */
class Cookie
{

    /**
     * Cookie name.
     *
     * @since 3.2.0
     * @var string
     */
    public $name;

    /**
     * Cookie value.
     *
     * @since 3.2.0
     * @var string
     */
    public $value;

    /**
     * When the cookie expires.
     *
     * @since 3.2.0
     * @var string
     */
    public $expires;

    /**
     * Cookie URL path.
     *
     * @since 3.2.0
     * @var string
     */
    public $path;

    /**
     * Cookie Domain.
     *
     * @since 3.2.0
     * @var string
     */
    public $domain;

    /**
     * Sets up this cookie object.
     *
     * The parameter $data should be either an associative array containing the indices names below
     * or a header string detailing it.
     *
     * If it's an array, it should include the following elements:
     * <ol>
     * <li>Name</li>
     * <li>Value - should NOT be urlencoded already.</li>
     * <li>Expires - (optional) String or int (UNIX timestamp).</li>
     * <li>Path (optional)</li>
     * <li>Domain (optional)</li>
     * <li>Port (optional)</li>
     * </ol>
     *
     * @access public
     * @since 3.2.0
     *       
     * @param string|array $data
     *            Raw cookie data.
     * @param string $requested_url
     *            The URL which the cookie was set on, used for default 'domain' and 'port' values
     */
    public function __construct($data, $requested_url = '')
    {
        if ($requested_url)
            $arrURL = @parse_url($requested_url);
        if (isset($arrURL['host']))
            $this->domain = $arrURL['host'];
        $this->path = isset($arrURL['path']) ? $arrURL['path'] : '/';
        if ('/' != substr($this->path, - 1))
            $this->path = dirname($this->path) . '/';
        
        if (is_string($data)) {
            // Assume it's a header string direct from a previous request
            $pairs = explode(';', $data);
            
            // Special handling for first pair; name=value. Also be careful of "=" in value
            $name = trim(substr($pairs[0], 0, strpos($pairs[0], '=')));
            $value = substr($pairs[0], strpos($pairs[0], '=') + 1);
            $this->name = $name;
            $this->value = urldecode($value);
            array_shift($pairs); // Removes name=value from items.
                                    
            // Set everything else as a property
            foreach ($pairs as $pair) {
                $pair = rtrim($pair);
                if (empty($pair)) // Handles the cookie ending in ; which results in a empty final pair
                    continue;
                
                list ($key, $val) = strpos($pair, '=') ? explode('=', $pair) : array(
                    $pair,
                    ''
                );
                $key = strtolower(trim($key));
                if ('expires' == $key)
                    $val = strtotime($val);
                $this->$key = $val;
            }
        } else {
            if (! isset($data['name']))
                return false;
                
                // Set properties based directly on parameters
            foreach (array(
                'name',
                'value',
                'path',
                'domain',
                'port'
            ) as $field) {
                if (isset($data[$field]))
                    $this->$field = $data[$field];
            }
            
            if (isset($data['expires']))
                $this->expires = is_int($data['expires']) ? $data['expires'] : strtotime($data['expires']);
            else
                $this->expires = null;
        }
    }

    /**
     * Confirms that it's OK to send this cookie to the URL checked against.
     *
     * Decision is based on RFC 2109/2965, so look there for details on validity.
     *
     * @access public
     * @since 3.2.0
     *       
     * @param string $url
     *            URL you intend to send this cookie to
     * @return boolean true if allowed, false otherwise.
     */
    public function test($url)
    {
        if (is_null($this->name))
            return false;
            
            // Expires - if expired then nothing else matters
        if (isset($this->expires) && time() > $this->expires)
            return false;
            
            // Get details on the URL we're thinking about sending to
        $url = parse_url($url);
        $url['port'] = isset($url['port']) ? $url['port'] : ('https' == $url['scheme'] ? 443 : 80);
        $url['path'] = isset($url['path']) ? $url['path'] : '/';
        
        // Values to use for comparison against the URL
        $path = isset($this->path) ? $this->path : '/';
        $port = isset($this->port) ? $this->port : null;
        $domain = isset($this->domain) ? strtolower($this->domain) : strtolower($url['host']);
        if (false === stripos($domain, '.'))
            $domain .= '.local';
            
            // Host - very basic check that the request URL ends with the domain restriction (minus leading dot)
        $domain = substr($domain, 0, 1) == '.' ? substr($domain, 1) : $domain;
        if (substr($url['host'], - strlen($domain)) != $domain)
            return false;
            
            // Port - supports "port-lists" in the format: "80,8000,8080"
        if (! empty($port) && ! in_array($url['port'], explode(',', $port)))
            return false;
            
            // Path - request path must start with path restriction
        if (substr($url['path'], 0, strlen($path)) != $path)
            return false;
        
        return true;
    }

    /**
     * Convert cookie name and value back to header string.
     *
     * @access public
     * @since 3.2.0
     *       
     * @return string Header encoded cookie name and value.
     */
    public function getHeaderValue()
    {
        if (! isset($this->name) || ! isset($this->value))
            return '';
        
        /**
         * Filter the header-encoded cookie value.
         *
         * @since 3.4.0
         *       
         * @param string $value
         *            The cookie value.
         * @param string $name
         *            The cookie name.
         */
        return $this->name . '=' . Hook::apply_filters('http_cookie_value', $this->value, $this->name);
    }

    /**
     * Retrieve cookie header for usage in the rest of the WordPress HTTP API.
     *
     * @access public
     * @since 3.2.0
     *       
     * @return string
     */
    public function getFullHeader()
    {
        return 'Cookie: ' . $this->getHeaderValue();
    }
}

// end