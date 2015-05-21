<?php

namespace Sb\Browser;

use Sunra\PhpSimple\HtmlDomParser;
use Sb\UrlUtils as SbUrlUtils;

class Console
{
    private $cookiesJar = null;
    private $userAgent = null;
    private $cache = null;

    private $referer = null;

    private $lastUrl = null;
    private $lastRequest = null;
    private $lastResponse = null;
    private $lastResponseBody = null;
    private $lastResponseHeaders = null;

    private $redirects = 0;
    private $connectTimeout = self::DEFAULT_TIMEOUT;
    private $timeout = self::DEFAULT_TIMEOUT;
    private $proxy = null;

    private $headers = array();

    private $execTime = null;

    private $dom = null;

    const REQUEST_METHOD_POST = "POST";
    const REQUEST_METHOD_GET = "GET";

    const OPTION_CONNECT_TIMEOUT = 'option_connect_timeout';
    const OPTION_TIMEOUT = 'option_timeout';
    const OPTION_PROXY = 'option_proxy';
    const OPTION_USER_AGENT = 'option_user_agent';
    const OPTION_CACHE = 'option_cache';
    const OPTION_COOKIES_JAR = 'option_cookies_jar';
    const DEFAULT_TIMEOUT = 120;

    public function __construct($options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        } else {
            // legacy
            $this->cookiesJar = $options;
        }
    }

    public function addHeader($name, $value)
    {
        $this->headers[] = $name . ': ' . $value;
    }

    public function clearHeader()
    {
        $this->headers = array();
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    public function setCache(Cache\CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
    }

    public function getCookiesJar()
    {
        return $this->cookiesJar;
    }

    public function getReferer()
    {
        return $this->referer;
    }

    public function getLastResponseBody()
    {
        return $this->lastResponseBody;
    }

    public function getLastResponseHeaders()
    {
        return $this->lastResponseHeaders;
    }

    public function setOptions($options)
    {
        if (array_key_exists(self::OPTION_CONNECT_TIMEOUT, $options)) {
            $this->connectTimeout = $options[self::OPTION_CONNECT_TIMEOUT];
        }
        if (array_key_exists(self::OPTION_TIMEOUT, $options)) {
            $this->timeout = $options[self::OPTION_TIMEOUT];
        }
        if (array_key_exists(self::OPTION_USER_AGENT, $options)) {
            $this->userAgent = $options[self::OPTION_USER_AGENT];
        }
        if (array_key_exists(self::OPTION_PROXY, $options)) {
            $this->proxy = $options[self::OPTION_PROXY];
        }
        if (array_key_exists(self::OPTION_CACHE, $options)) {
            $this->cache = $options[self::OPTION_CACHE];
        }
    }

    public function mergeParams($url, $params)
    {
        $urlParts = parse_url($url);
        if (!empty($urlParts['query'])) {
            $urlParts['query'] .= '&' . http_build_query($params);
        } else {
            $urlParts['query'] = http_build_query($params);
        }
        $result = SbUrlUtils::httpBuildUrl($url, $urlParts);
        $result = rtrim($result, '?&');
        return $result;
    }

    public function downloadFile($url, $destinationFile)
    {
        $data = $this->get($url);
        $file = fopen($destinationFile, "w+");
        fputs($file, $data);
        fclose($file);
    }

    /**
     * @return Cache\CacheInterface
     */
    public function getCache()
    {
        return $this->cache;
    }

    public function isCacheEnable()
    {
        return $this->cache instanceof Cache\CacheInterface;
    }

    public function request($url, $method = self::REQUEST_METHOD_GET, $params = array(), $redirect = 0, $isSubRequest = false)
    {
        $isMethodGet = $method == self::REQUEST_METHOD_GET;

        if ($isMethodGet) {
            $requestUrl = $this->mergeParams($url, $params);

            if ($this->isCacheEnable()) {
                $cacheData = $this->getCache()->load(sha1($requestUrl));
                if ($cacheData) {
                    $this->lastResponse = $cacheData;
                    $this->lastResponseBody = $cacheData;
                    $this->lastResponseHeaders = '';
                    $this->lastUrl = $requestUrl;
                    $this->dom = null;
                    return $cacheData;
                }
            }

            $ch = curl_init($requestUrl);
        } else {
            $requestUrl = $url;
            $ch = curl_init($requestUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            if (is_array($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode($params));
            }
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $cookiesJar = $this->getCookiesJar();
        if ($cookiesJar) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesJar);
        }

        curl_setopt($ch, CURLOPT_PROXY, $this->proxy);

        $headers = $this->getHeaders();
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $referer = $this->getReferer();
        if ($referer) {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }

        $userAgent = $this->getUserAgent();
        if ($userAgent) {
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        }

        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $error = curl_errno($ch);
        $requestInfo = curl_getinfo($ch);
        curl_close($ch);

        $headerSize = $requestInfo['header_size'];
        $responseHeader = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        if (array_key_exists('redirect_url', $requestInfo)) {
            $redirectUrl = $requestInfo['redirect_url'];
        } else {
            $redirectUrl = null;
        }

        if (array_key_exists('request_header', $requestInfo)) {
            $this->lastRequest = $requestInfo['request_header'];
        } else {
            $this->lastRequest = null;
        }

        if ($method == self::REQUEST_METHOD_POST ) {
            if (is_array($params)) {
                $this->lastRequest .= http_build_query($params);
            } else {
                $this->lastRequest .= urlencode($params);
            }
        }
        $this->lastResponse = $response;
        $this->lastResponseBody = $responseBody;
        $this->lastResponseHeaders = $responseHeader;
        $this->lastUrl = $requestUrl;

        $this->dom = null;

        if ($error > 0) {
            throw new Exception\Exception("Curl error: ". $error);
        }

        if ($redirect > 10) {
            throw new Exception\Exception("Max redirects: ". 10);
        }

        $httpCode = $requestInfo['http_code'];

        if ($httpCode == 200) {
            if (!$isSubRequest) {
                $this->referer = $requestUrl;
            }

            if ($isMethodGet && $this->isCacheEnable()) {
                $this->getCache()->save(sha1($requestUrl), $responseBody);
            }

        } elseif ($httpCode >= 300 && $httpCode < 400) {
            $redirectUrl = $this->relativeToAbsolute($redirectUrl);
            return $this->request($redirectUrl, self::REQUEST_METHOD_GET, array(), $redirect + 1);
        } elseif ($httpCode > 400) {
            throw new Exception\Exception("http error ". $httpCode);
        }

        return $responseBody;
    }

    public function getCurrentScheme()
    {
        return parse_url($this->getLastUrl(), PHP_URL_SCHEME);
    }

    public function getCurrentHost()
    {
        return parse_url($this->getLastUrl(), PHP_URL_HOST);
    }

    public function relativeToAbsolute($url)
    {
        if (strpos($url, '//') === 0) {
            $url = $this->getCurrentScheme() . ':' . $url;
        } elseif (strpos($url, 'http') !== 0) {
            // относит url, to-do parse url

            if (strpos($url, '/') === 0) {
                $url = $this->getCurrentScheme() . '://' . $this->getCurrentHost() . $url;
            } else {
                $url = $this->getCurrentScheme() . '://' . $this->getCurrentHost() . '/' .$url;
            }
        }
        return $url;
    }
    
    public function get($url, $params = array())
    {
        return $this->request($url, self::REQUEST_METHOD_GET, $params);
    }
    
    public function post($url, $params = array())
    {
        return $this->request($url, self::REQUEST_METHOD_POST, $params);
    }

    /**
     * @param $html
     * @return \simple_html_dom
     */
    public function getHtmlDomParser($html)
    {
        return HtmlDomParser::str_get_html($html);
    }

    /**
     * @param null $execTime
     */
    public function setExecTime($execTime)
    {
        $this->execTime = $execTime;
    }

    /**
     * @return null
     */
    public function getExecTime()
    {
        return $this->execTime;
    }

    /**
     * @param null $lastRequest
     */
    public function setLastRequest($lastRequest)
    {
        $this->lastRequest = $lastRequest;
    }

    /**
     * @return null
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * @param null $lastResponse
     */
    public function setLastResponse($lastResponse)
    {
        $this->lastResponse = $lastResponse;
    }

    /**
     * @return null
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * @param null $lastUrl
     */
    public function setLastUrl($lastUrl)
    {
        $this->lastUrl = $lastUrl;
    }

    /**
     * @return null
     */
    public function getLastUrl()
    {
        return $this->lastUrl;
    }

    /**
     * @param int $redirects
     */
    public function setRedirects($redirects)
    {
        $this->redirects = $redirects;
    }

    /**
     * @return int
     */
    public function getRedirects()
    {
        return $this->redirects;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    public function getDom()
    {
        if (!$this->dom) {
            $this->dom = $this->getHtmlDomParser($this->getLastResponseBody());
        }
        return $this->dom;
    }

    public function getForm($selector)
    {
        if (!$this->getLastResponseBody()) {
            return false;
        }

        $form = $this->domFindFirst($selector);
        return $this->getFormFromDom($form);
    }

    public function submitFormData($form)
    {
        unset($form['_images']);
        return $this->request($form['action'], $form['method'], $form['fields']);
    }

    /**
     * @param \simple_html_dom $dom
     * @return array
     */
    public function getFormFromDom($dom)
    {
        if (!$dom instanceof \simple_html_dom) {
            return false;
        }

        $action = $dom->getAttribute('action');
        if (!$action) {
            $action = $this->getLastUrl();
        } else {
            $action = $this->relativeToAbsolute($action);
        }

        $method = $dom->getAttribute('method');
        if (!$method) {
            $method = self::REQUEST_METHOD_GET;
        }

        $enctype = $dom->getAttribute('enctype');
        if (!$enctype) {
            $enctype = 'application/x-www-form-urlencoded';
        }

        $result = array(
            'action' => $action,
            'method' => $method,
            'enctype' => $enctype
        );

        $formInputData = array();

        $inputs = $dom->find('input'); // input[type=text]
        /** @var \simple_html_dom[] $inputs  */
        foreach ($inputs as $input) {
            $inputType = $input->getAttribute('type');
            $inputName = $input->getAttribute('name');
            if ($inputName) {
                $formInputData[$inputName] = $input->getAttribute('value');
            }
        }

        $images = $dom->find('img');
        /** @var \simple_html_dom[] $images  */
        foreach ($images as $img) {
            $imageSrc = $img->getAttribute ( 'src' );
            $image = array(
                'src' => $imageSrc,
                'data' => base64_encode($this->request($imageSrc, self::REQUEST_METHOD_GET, array(), 0, true))
            );
            $formInputData['_images'][] = $image;
        }

        $result['fields'] = $formInputData;

        return $result;
    }

    /**
     * @param $selector
     * @param \simple_html_dom $dom
     * @return \simple_html_dom[]
     */
    public function domFind($selector, $dom = null)
    {
        if (is_null($dom)) {
            $dom = $this->getDom();
        }

        if (!$dom instanceof \simple_html_dom) {
            return null;
        }

        return $dom->find($selector);
    }

    /**
     * @param $selector
     * @param \simple_html_dom $dom
     * @return \simple_html_dom
     */
    public function domFindFirst($selector, $dom = null)
    {
        if (is_null($dom)) {
            $dom = $this->getDom();
        }

        if (!$dom instanceof \simple_html_dom) {
            return null;
        }

        return $dom->find($selector, 0);
    }

    /**
     * @param $selector
     * @param \simple_html_dom $dom
     * @param int $idx
     * @return \simple_html_dom
     */
    public function domFindNElement($selector, $idx, $dom = null)
    {
        if (is_null($dom)) {
            $dom = $this->getDom();
        }

        if (!$dom instanceof \simple_html_dom) {
            return null;
        }

        return $dom->find($selector, $idx);
    }

    public function getForms()
    {
        if (!$this->getLastResponseBody()) {
            return [];
        }

        $dom = $this->getHtmlDomParser($this->getLastResponseBody());
        if (!$dom instanceof \simple_html_dom) {
            return [];
        }

        $forms = $dom->find('form');
        $i = 1;
        $result = [];
        foreach ($forms as $form) {
            $result['form_' . $i] = $this->getFormFromDom($form);
            $i++;
        }
        return $result;
    }
}