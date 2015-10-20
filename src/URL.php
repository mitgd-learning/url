<?php
namespace esperecyan\url;

use esperecyan\webidl\TypeHinter;
use esperecyan\webidl\TypeError;

/**
 * The URL class represent an object providing static methods used for creating object URLs.
 * @link https://url.spec.whatwg.org/#URL URL Standard
 * @link https://developer.mozilla.org/docs/Web/API/URL URL - Web API Interfaces | MDN
 * @property string $href Is a USVString containing the whole URL.
 * @property-read string $origin
 *      Returns a USVString containing the canonical form of the origin of the specific location.
 * @property string $protocol Is a USVString containing the protocol scheme of the URL, including the final ':'.
 * @property string $username Is a USVString containing the username specified before the domain name.
 * @property string $password Is a USVString containing the password specified before the domain name.
 * @property string $host Is a USVString containing the host, that is the hostname,
 *      and then, if the port of the URL is not empty (which can happen because it was not specified
 *          or because it was specified to be the default port of the URL's scheme), a ':', and the port of the URL.
 * @property string $hostname Is a USVString containing the domain of the URL.
 * @property string $port Is a USVString containing the port number of the URL.
 * @property string $pathname Is a USVString containing an initial '/' followed by the path of the URL.
 * @property string $search Is a USVString containing a '?' followed by the parameters of the URL.
 * @property-read URLSearchParams $searchParams
 *      Returns a URLSearchParams object allowing to access the GET query arguments contained in the URL.
 * @property string $hash Is a USVString containing a '#' followed by the fragment identifier of the URL.
 */
class URL
{
    /**
     * @var URL An associated URL.
     * @link https://url.spec.whatwg.org/#concept-url-url URL Standard
     */
    private $url;
    
    /**
     * @var URLSearchParams An associated query object.
     * @link https://url.spec.whatwg.org/#concept-url-query-object URL Standard
     */
    private $queryObject;
    
    /**
     * The constructor returns a newly created URL object representing the URL defined by the parameters.
     * @link https://url.spec.whatwg.org/#dom-URL-URL URL Standard
     * @link https://developer.mozilla.org/docs/Web/API/URL/URL URL() - Web API Interfaces | MDN
     * @param string $url Is a DOMString representing an absolute or relative URL.
     *      If $url is a relative URL, $base which is present, will be used as the base URL.
     *      If $url is an absolute URL, $base are ignored.
     * @param string $base Is a DOMString representing the base URL to use in case $url is a relative URL.
     *      If not specified, and no $base is passed in parameters, it default to 'about:blank'.
     * @throws TypeError If the given base URL or the resulting URL are not valid URLs, a TypeError is thrown.
     */
    public function __construct($url, $base = null)
    {
        $parsedBase = null;
        if (!is_null($base)) {
            $parsedBase = lib\URL::parseBasicURL(TypeHinter::to('USVString', $base, 1));
            if (!$parsedBase) {
                throw new TypeError(sprintf('<%s> is not a valid URL', $base));
            }
        }
        $this->url = lib\URL::parseBasicURL(TypeHinter::to('USVString', $url, 0), $parsedBase);
        if ($this->url === false) {
            throw new TypeError(sprintf('<%s> is not a valid URL', $url));
        }
        $this->queryObject = new URLSearchParams(is_null($this->url->query) ? '' : $this->url->query);
        \Closure::bind(function ($queryObject) {
            $queryObject->urlObject = $this;
        }, $this, $this->queryObject)->__invoke($this->queryObject);
    }
    
    /**
     * Convert domain name to IDNA ASCII form.
     * @link https://url.spec.whatwg.org/#dom-URL-domainToASCII URL Standard
     * @param string $domain
     * @return string Returns an empty string if $domain is an IPv6 address or an invalid domain.
     */
    public static function domainToASCII($domain)
    {
        $asciiDomain = lib\HostProcessing::parseHost(TypeHinter::to('USVString', $domain));
        return is_string($asciiDomain) ? $asciiDomain : '';
    }
    
    /**
     * Convert domain name from IDNA ASCII to Unicode.
     * @link https://url.spec.whatwg.org/#dom-URL-domainToASCII URL Standard
     * @param string $domain
     * @return string Returns an empty string if $domain is an IPv6 address or an invalid domain.
     */
    public static function domainToUnicode($domain)
    {
        $unicodeDomain = lib\HostProcessing::parseHost(TypeHinter::to('USVString', $domain), true);
        return is_string($unicodeDomain) ? $unicodeDomain : '';
    }
    
    /**
     * @param string $name
     * @param string $value
     * @throws TypeError
     */
    public function __set($name, $value)
    {
        if (in_array(
            $name,
            ['href', 'protocol', 'username', 'password', 'host', 'hostname', 'port', 'pathname', 'search', 'hash']
        )) {
            $input = TypeHinter::to('USVString', $value);
        }
        
        switch ($name) {
            case 'href':
                $parsedURL = lib\URL::parseBasicURL($input);
                if (!$parsedURL) {
                    throw new TypeError(sprintf('<%s> is not a valid URL', $input));
                }
                $this->url = $parsedURL;
                break;
            
            case 'protocol':
                lib\URL::parseBasicURL($input . ':', null, null, [
                    'url' => $this->url,
                    'state override' => 'scheme start state'
                ]);
                break;
            
            case 'username':
                if (!is_null($this->url->host) && !$this->url->nonRelativeFlag) {
                    $this->url->setUsername($input);
                }
                break;
            
            case 'password':
                if (!is_null($this->url->host) && !$this->url->nonRelativeFlag) {
                    $this->url->setPassword($input);
                }
                break;
            
            case 'host':
                if (!$this->url->nonRelativeFlag) {
                    lib\URL::parseBasicURL($input, null, null, [
                        'url' => $this->url,
                        'state override' => 'host state'
                    ]);
                }
                break;
            
            case 'hostname':
                if ($this->url && !$this->url->nonRelativeFlag) {
                    lib\URL::parseBasicURL($input, null, null, [
                        'url' => $this->url,
                        'state override' => 'hostname state'
                    ]);
                }
                break;
            
            case 'port':
                if (!is_null($this->url->host) && !$this->url->nonRelativeFlag && $this->url->scheme !== 'file') {
                    lib\URL::parseBasicURL($input, null, null, [
                        'url' => $this->url,
                        'state override' => 'port state'
                    ]);
                }
                break;
            
            case 'pathname':
                if (!$this->url->nonRelativeFlag) {
                    $this->url->path = [];
                    lib\URL::parseBasicURL($input, null, null, [
                        'url' => $this->url,
                        'state override' => 'path start state'
                    ]);
                }
                break;
            
            case 'search':
                if ($input === '') {
                    $this->url->query = null;
                    $list = [];
                } else {
                    $query = $input[0] === '?' ? substr($input, 1) : $input;
                    $this->url->query = '';
                    lib\URL::parseBasicURL($query, null, null, [
                        'url' => $this->url,
                        'state override' => 'query state'
                    ]);
                    $list = lib\URLencoding::parseURLencodedString($query);
                }
                \Closure::bind(function ($list, $queryObject) {
                    array_splice($queryObject->list, 0, count($queryObject->list), $list);
                }, null, $this->queryObject)->__invoke($list, $this->queryObject);
                break;
            
            case 'hash':
                if ($this->url->scheme !== 'javascript') {
                    if ($input === '') {
                        $this->url->fragment = null;
                    } else {
                        $fragment = $input[0] === '#' ? substr($input, 1) : $input;
                        $this->url->fragment = '';
                        lib\URL::parseBasicURL($fragment, null, null, [
                            'url' => $this->url,
                            'state override' => 'fragment state'
                        ]);
                    }
                }
                break;
            
            case 'origin':
            case 'searchParams':
                TypeHinter::throwReadonlyException();
                break;
            
            default:
                TypeHinter::triggerVisibilityErrorOrDefineProperty();
        }
    }
    
    /**
     * @param string $name
     * @return string|URLSearchParams
     */
    public function __get($name)
    {
        switch ($name) {
            case 'href':
                $value = $this->url->serializeURL();
                break;
            
            case 'origin':
                $value = self::unicodeSerialiseOrigin($this->url->getOrigin());
                break;
            
            case 'protocol':
                $value = $this->url->scheme . ':';
                break;
            
            case 'username':
                $value = $this->url->username;
                break;
            
            case 'password':
                $value = is_null($this->url->password) ? '' : $this->url->password;
                break;
            
            case 'host':
                $value = is_null($this->url->host)
                    ? ''
                    : lib\HostProcessing::serializeHost($this->url->host)
                        . (is_null($this->url->port) ? '' : ':' . $this->url->port);
                break;
            
            case 'hostname':
                $value = is_null($this->url->host) ? '' : lib\HostProcessing::serializeHost($this->url->host);
                break;
            
            case 'port':
                $value = is_null($this->url->port) ? '' : (string)$this->url->port;
                break;
            
            case 'pathname':
                $value = $this->url->nonRelativeFlag ? $this->url->path[0] : '/' . implode('/', $this->url->path);
                break;
            
            case 'search':
                $value = is_null($this->url->query) || $this->url->query === '' ? '' : '?' . $this->url->query;
                break;
            
            case 'searchParams':
                $value = $this->queryObject;
                break;
            
            case 'hash':
                $value = is_null($this->url->fragment) || $this->url->fragment === '' ? '' : '#' . $this->url->fragment;
                break;
            
            default:
                TypeHinter::triggerVisibilityErrorOrUndefinedNotice();
                $value = null;
        }
        
        return $value;
    }
    
    /**
     * The Unicode serialisation of an origin.
     * @link https://html.spec.whatwg.org/multipage/browsers.html#unicode-serialisation-of-an-origin HTML Standard
     * @param string[]|string $origin
     * @return string
     */
    private static function unicodeSerialiseOrigin($origin)
    {
        if (!is_array($origin)) {
            $result = 'null';
        } else {
            $result = $origin[0] . '://' . lib\HostProcessing::domainToUnicode($origin[1]);
            if (isset(lib\URL::$specialSchemes[$origin[0]]) && $origin[2] !== lib\URL::$specialSchemes[$origin[0]]) {
                $result .= ':' . $origin[2];
            }
        }
        return $result;
    }
    
    /**
     * The URLUtils::__toString() stringifier method returns a USVString containing the whole URL.
     * It is a read-only version of URLUtils::href.
     * @link https://url.spec.whatwg.org/#URLUtils-stringification-behavior URL Standard
     * @link https://developer.mozilla.org/docs/Web/API/URLUtils/toString
     *      URLUtils.toString() - Web API Interfaces | MDN
     * @return string USVString.
     */
    public function __toString()
    {
        return $this->__get('href');
    }
    
    /**
     * @param string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return in_array($name, ['href', 'origin', 'protocol', 'username', 'password', 'host', 'hostname', 'port', 'pathname', 'search', 'searchParams', 'hash']);
    }
}