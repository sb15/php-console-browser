<?php

namespace Sb\Browser;

use Sunra\PhpSimple\HtmlDomParser;
use Sb\UrlUtils as SbUrlUtils;

class Console
{
    private $cookiesJar = null;
    private $userAgent = null;

    private $referer = null;

    private $lastUrl = null;
    private $lastRequest = null;
    private $lastResponse = null;
    private $lastResponseBody = null;
    private $lastResponseHeaders = null;

    private $redirects = 0;
    private $timeout = 120;

    private $headers = array();

    private $execTime = null;

    private $dom = null;

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

    public function getLastResponseBody()
    {
        return $this->lastResponseBody;
    }

    public function getLastResponseHeaders()
    {
        return $this->lastResponseHeaders;
    }

    public function mergeParams($url, $params)
    {
        $urlParts = parse_url($url);
        if (!empty($urlParts['query'])) {
            $urlParts['query'] .= '&' . http_build_query($params);
        } else {
            $urlParts['query'] = http_build_query($params);
        }
        return SbUrlUtils::httpBuildUrl($url, $urlParts);
    }

    public function request($url, $method = self::REQUEST_METHOD_GET, $params = array(), $redirect = 0, $isSubRequest = false)
    {
        if ($method == self::REQUEST_METHOD_GET) {
            $requestUrl = $this->mergeParams($url, $params);
            $ch = curl_init($requestUrl);
        } else {
            $requestUrl = $url;
            $ch = curl_init($requestUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
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
        $headerSize = $requestInfo['header_size'];
        $responseHeader = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        curl_close($ch);

        $redirectUrl = $requestInfo['redirect_url'];

        $this->lastRequest = $requestInfo['request_header'];
        $this->lastResponse = $response;
        $this->lastResponseBody = $responseBody;
        $this->lastResponseHeaders = $responseHeader;
        $this->lastUrl = $requestUrl;

        $this->dom = null;

        if ($error > 0) {
            die;
        }

        if ($redirect > 5) {
            // limit
            die;
        }

        $httpCode = $requestInfo['http_code'];

        if ($httpCode == 200) {
            if (!$isSubRequest) {
                $this->referer = $requestUrl;
            }
        } elseif ($httpCode >= 300 && $httpCode < 400) {
            $redirectUrl = $this->relativeToAbsolute($redirectUrl);
            return $this->request($redirectUrl, self::REQUEST_METHOD_GET, array(), $redirect + 1);
        } elseif ($httpCode > 400) {

        }

        return $responseBody;
    }

    public function relativeToAbsolute($url)
    {
        if (strpos($url, 'http') !== 0) {
            // относит url
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
     * @return \simple_html_dom_node
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
            $this->dom = $this->getHtmlDomParser($this->getLastResponse());
        }
        return $this->dom;
    }

    public function getForm($selector)
    {
        if (!$this->getLastResponse()) {
            return false;
        }

        $form = $this->getDom()->find($selector, 0);
        return $this->getFormFromDom($form);
    }

    public function submitFormData($form)
    {
        unset($form['_images']);
        return $this->request($form['action'], $form['method'], $form['fields']);
    }

    /**
     * @param \simple_html_dom_node $dom
     * @return array
     */
    public function getFormFromDom($dom)
    {
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
        /** @var \simple_html_dom_node[] $inputs  */
        foreach ($inputs as $input) {
            $inputType = $input->getAttribute('type');
            $inputName = $input->getAttribute('name');
            if ($inputName) {
                $formInputData[$inputName] = $input->getAttribute('value');
            }
        }

        $images = $dom->find('img');
        /** @var \simple_html_dom_node[] $images  */
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

    public function getForms()
    {
        if (!$this->getLastResponse()) {
            return false;
        }
        $dom = $this->getHtmlDomParser($this->getLastResponse());
        $forms = $dom->find('form');
        $i = 1;
        $result = array();
        foreach ($forms as $form) {
            $result['form_' . $i] = $this->getFormFromDom($form);
            $i++;
        }
        return $result;
    }
}