<?php

namespace Sb\Browser;

use Sunra\PhpSimple\HtmlDomParser;

if (!function_exists('http_build_url'))
{
    define('HTTP_URL_REPLACE', 1);              // Replace every part of the first URL when there's one of the second URL
    define('HTTP_URL_JOIN_PATH', 2);            // Join relative paths
    define('HTTP_URL_JOIN_QUERY', 4);           // Join query strings
    define('HTTP_URL_STRIP_USER', 8);           // Strip any user authentication information
    define('HTTP_URL_STRIP_PASS', 16);          // Strip any password authentication information
    define('HTTP_URL_STRIP_AUTH', 32);          // Strip any authentication information
    define('HTTP_URL_STRIP_PORT', 64);          // Strip explicit port numbers
    define('HTTP_URL_STRIP_PATH', 128);         // Strip complete path
    define('HTTP_URL_STRIP_QUERY', 256);        // Strip query string
    define('HTTP_URL_STRIP_FRAGMENT', 512);     // Strip any fragments (#identifier)
    define('HTTP_URL_STRIP_ALL', 1024);         // Strip anything but scheme and host

    // Build an URL
    // The parts of the second URL will be merged into the first according to the flags argument.
    //
    // @param   mixed           (Part(s) of) an URL in form of a string or associative array like parse_url() returns
    // @param   mixed           Same as the first argument
    // @param   int             A bitmask of binary or'ed HTTP_URL constants (Optional)HTTP_URL_REPLACE is the default
    // @param   array           If set, it will be filled with the parts of the composed url like parse_url() would return
    function http_build_url($url, $parts=array(), $flags=HTTP_URL_REPLACE, &$new_url=false)
    {
        $keys = array('user','pass','port','path','query','fragment');

        // HTTP_URL_STRIP_ALL becomes all the HTTP_URL_STRIP_Xs
        if ($flags & HTTP_URL_STRIP_ALL)
        {
            $flags |= HTTP_URL_STRIP_USER;
            $flags |= HTTP_URL_STRIP_PASS;
            $flags |= HTTP_URL_STRIP_PORT;
            $flags |= HTTP_URL_STRIP_PATH;
            $flags |= HTTP_URL_STRIP_QUERY;
            $flags |= HTTP_URL_STRIP_FRAGMENT;
        }
        // HTTP_URL_STRIP_AUTH becomes HTTP_URL_STRIP_USER and HTTP_URL_STRIP_PASS
        else if ($flags & HTTP_URL_STRIP_AUTH)
        {
            $flags |= HTTP_URL_STRIP_USER;
            $flags |= HTTP_URL_STRIP_PASS;
        }

        // Parse the original URL
        $parse_url = parse_url($url);

        // Scheme and Host are always replaced
        if (isset($parts['scheme']))
            $parse_url['scheme'] = $parts['scheme'];
        if (isset($parts['host']))
            $parse_url['host'] = $parts['host'];

        // (If applicable) Replace the original URL with it's new parts
        if ($flags & HTTP_URL_REPLACE)
        {
            foreach ($keys as $key)
            {
                if (isset($parts[$key]))
                    $parse_url[$key] = $parts[$key];
            }
        }
        else
        {
            // Join the original URL path with the new path
            if (isset($parts['path']) && ($flags & HTTP_URL_JOIN_PATH))
            {
                if (isset($parse_url['path']))
                    $parse_url['path'] = rtrim(str_replace(basename($parse_url['path']), '', $parse_url['path']), '/') . '/' . ltrim($parts['path'], '/');
                else
                    $parse_url['path'] = $parts['path'];
            }

            // Join the original query string with the new query string
            if (isset($parts['query']) && ($flags & HTTP_URL_JOIN_QUERY))
            {
                if (isset($parse_url['query']))
                    $parse_url['query'] .= '&' . $parts['query'];
                else
                    $parse_url['query'] = $parts['query'];
            }
        }

        // Strips all the applicable sections of the URL
        // Note: Scheme and Host are never stripped
        foreach ($keys as $key)
        {
            if ($flags & (int)constant('HTTP_URL_STRIP_' . strtoupper($key)))
                unset($parse_url[$key]);
        }


        $new_url = $parse_url;

        return
            ((isset($parse_url['scheme'])) ? $parse_url['scheme'] . '://' : '')
            .((isset($parse_url['user'])) ? $parse_url['user'] . ((isset($parse_url['pass'])) ? ':' . $parse_url['pass'] : '') .'@' : '')
            .((isset($parse_url['host'])) ? $parse_url['host'] : '')
            .((isset($parse_url['port'])) ? ':' . $parse_url['port'] : '')
            .((isset($parse_url['path'])) ? $parse_url['path'] : '')
            .((isset($parse_url['query'])) ? '?' . $parse_url['query'] : '')
            .((isset($parse_url['fragment'])) ? '#' . $parse_url['fragment'] : '')
            ;
    }
}

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

    public function mergeParams($url, $params)
    {
        $urlParts = parse_url($url);
        if (!empty($urlParts['query'])) {
            $urlParts['query'] .= '&' . http_build_query($params);
        }
        return http_build_url($url, $urlParts);
    }

    public function request($url, $method = self::REQUEST_METHOD_GET, $params = array(), $redirect = 0, $isSubRequest = false)
    {

        $paramsString = http_build_query($params);
        if ($method == self::REQUEST_METHOD_GET) {
            $requestUrl = $this->mergeParams($url, $params);
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

        //var_dump($response);
        //var_dump($requestInfo);

        $redirectUrl = $requestInfo['redirect_url'];

        $this->lastRequest = $requestInfo['request_header'];
        $this->lastResponse = $response;
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

        return $response;
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

        $inputs = $dom->find('input');
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

        /*
        $action = $form->getAttribute('action');

		if ($action) {
			$action = $this->getAbsoluteUrl($action);
		} else {
			$action = $this->getClient()->getUri()->__toString();

		}
		// to-do
		// относительный адрес определяется неправильно

		$formData = array ('action' => $action,
						   'method' => $form->getAttribute('method'),
                           'enctype' => $form->getAttribute('enctype') ? $form->getAttribute('enctype') : Zend_Http_Client::ENC_URLENCODED);

		$formInputData = array();

		$inputs = $form->getElementsByTagName ( 'input' );
		foreach ( $inputs as $input ) {
			$inputName = $input->getAttribute ( 'name' );
			if (! empty ( $inputName )) {
				$formInputData [$inputName] = $input->getAttribute ( 'value' );
			}
		}

		$selects = $form->getElementsByTagName ( 'select' );
		foreach ( $selects as $select ) {

			$options = $select->getElementsByTagName ( 'option' );
			$optionsData = array ();
			foreach ( $options as $option ) {
				$optionsData [] = $option->getAttribute ( 'value' );
			}

			$formInputData [$select->getAttribute ( 'name' )] = $optionsData;
		}

		if ($withImages) {
			$imgs = $form->getElementsByTagName ( 'img' );
			foreach ( $imgs as $img ) {
				$imageSrc = $img->getAttribute ( 'src' );
				$image = array('src' => $imageSrc);
				$image['data'] = base64_encode($this->doRequest($imageSrc, 'GET', true));
				$formData['_images'][] = $image;
			}
		}

		$formData ['fields'] = $formInputData;
		return $formData;
         */
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