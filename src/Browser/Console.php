<?php

namespace Sb\Browser;

class Console
{

    private $cookiesJar = null;
    private $userAgent = null;

    private $referer = null;

    private $lastUrl = null;
    private $lastRequest = null;
    private $lastResponse = null;

    private $redirects = 0;
    private $timeout = 120;

    private $headers = array();

    private $execTime = null;

    const REQUEST_METHOD_POST = "POST";
    const REQUEST_METHOD_GET = "GET";

    public function __construct($cookiesJar = null)
    {
        $this->cookiesJar = $cookiesJar;
    }

    public function addHeader($name, $value)
    {
        $this->headers[] = array($name => $value);
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

    public function getCookiesJar()
    {
        return $this->cookiesJar;
    }

    public function getReferer()
    {
        return $this->referer;
    }

    public function request($url, $method = self::REQUEST_METHOD_GET, $params = array(), $redirect = 0)
    {

        $paramsString = http_build_query($params);
        if ($method == self::REQUEST_METHOD_GET) {
            // detect
            $requestUrl = $url . '?' . $paramsString;
            $ch = curl_init($requestUrl);
        } else {
            $requestUrl = $url;
            $ch = curl_init($requestUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsString);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $cookiesJar = $this->getCookiesJar();
        if ($cookiesJar) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesJar);
        }

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

        curl_setopt($ch, CURLOPT_HEADER, 0);
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

        if ($error > 0) {
            die;
        }

        $httpCode = $requestInfo['http_code'];

        if ($httpCode == 200) {
            $this->lastResponse = $response;
            $this->lastUrl = $requestUrl;
            $this->referer = $requestUrl;
        }

        return $response;
    }
    
    public function get($url, $params = array())
    {
        return $this->request($url, self::REQUEST_METHOD_GET, $params);
    }
    
    public function post($url, $params = array())
    {
        return $this->request($url, self::REQUEST_METHOD_POST, $params);
    }
    
    public function getForm($selector)
    {
        // check response

        // find form
    }
}