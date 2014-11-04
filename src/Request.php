<?php
namespace Curl;

/**
 * Request class
 * @author Alexey "Lexeo" Grishatkin
 * @version 0.3.1
 */
class Request
{
    // request methods
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_HEAD = 'HEAD';

    // request event types
    const EVENT_BEFORE_SEND = 'beforesend';
    const EVENT_SUCCESS = 'success';
    const EVENT_ERROR = 'error';
    const EVENT_COMPLETE = 'complete';

    protected $url;
    protected $method;
    protected $postParams = array();
    protected $options = array();
    protected $headers = array();
    protected $cookies = array();
    protected $refererUrl;
    protected $userAgent;

    protected $proxy;
    protected $proxyPort;
    protected $proxyType;
    protected $proxyUserPwd;

    protected $timeout;
    protected $connectionTimeout;

    protected $allowRedirect = true;
    protected $redirectLimit = 0;

    protected $cookieFile = null;
    protected $cookieFileReadOnly = true;

    protected $observers = array();

    protected $customData = array();

    private $_ch = null;

    /**
     * @var Curl\Response\ResponseInterface
     */
    protected $response = null;
    protected $responseClass = 'Curl\Response\PlainResponse';
    protected $responseOptions = array();

   /**
    * Constructor
    * @param string $url
    * @param string $method [optional], default GET
    * @param array $postParams [optional]
    * @param callback $callback [optional]
    * @return Curl\Request
    */
   public function __construct($url = null, $method = self::METHOD_GET, array $postParams = null, $callback = null)
   {
       null !== $url && $this->setUrl($url);
       null !== $method && $this->setMethod($method);
       null !== $postParams && $this->setPostParams($postParams);
       null !== $callback && $this->setCallback($callback);

       $this->init();
   }

   /**
    * Destructor
    */
   public function __destruct()
   {
        $this->close();
   }

   /**
    * Initializes the request
    */
   public function init()
   {
       $this->_ch = curl_init();
       $this->response = null;
       if(false === $this->getResource()) {
           throw new \RuntimeException('Function curl_init returned false. Failed to init the Request');
       }
       return $this;
   }

   /**
    * Makes new Request object
    * @param string $url
    * @param string $method [optional], default GET
    * @param array $postParams [optional]
    * @param callback $callback [optional]
    * @return Curl\Request
    */
   public static function newRequest($url = null, $method = self::METHOD_GET, array $postParams = null, $callback = null)
   {
       return new self($url, $method, $postParams, $callback);
   }

   /**
    * Returns a list of available event types [key => description]
    * @return array
    */
   public static function getAvailableEventTypes()
   {
       return array(
           self::EVENT_BEFORE_SEND => 'Before Send',
           self::EVENT_SUCCESS => 'Success. Response has no error',
           self::EVENT_ERROR => 'Error. Response has an error',
           self::EVENT_COMPLETE => 'Complete',
       );
   }

   /**
    * Attaches an event handler
    * @param string $eventType
    * @param callback $handler valid callback
    */
   public function on($eventType, $handler)
   {
       if(!is_callable($handler, true)) {
           throw new \InvalidArgumentException('Invalid event handler given. Expected a valid callback');
       }
       $this->observers[strtolower((string) $eventType)][] = $handler;

       return $this;
   }

   /**
    * Detaches an event handler
    * @param string $eventType
    * @param callback $handler
    */
   public function off($eventType, $handler)
   {
       $k = strtolower((string) $eventType);
       if(isset($this->observers[$k])) {
           if(false !== ($index = array_search($handler, $this->observers[$k]))) {
               unset($this->observers[$k][$index]);
           }
           if(!count($this->observers[$k])) {
               unset($this->observers[$k]);
           }
       }
       return $this;
   }

   /**
    * Fires event
    * @param string $eventType
    * @param array $customParams
    */
   public function trigger($eventType, array $customParams = null)
   {
       $k = strtolower((string) $eventType);
       $observers = isset($this->observers[$k]) ? $this->observers[$k] : array();
       $params = array($this->response, $this);
       if(null !== $customParams) {
           $params = array_merge($params, $customParams);
       }

       foreach ($observers as $callback) {
           call_user_func_array($callback, $params);
       }
       return $this;
   }



   /**
    * Prepares request (sets up the curl options)
    */
   public function prepare()
   {
       if(empty($this->url)) {
           throw new \BadMethodCallException('Check the Request URL. It should not be empty.');
       }

        $options = array(
            CURLOPT_URL => $this->url,
            CURLOPT_REFERER => $this->refererUrl,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ) + $this->options;

        // allow redirects
        if(ini_get('safe_mode') == 'Off' || !ini_get('safe_mode')) {
            $options[CURLOPT_FOLLOWLOCATION] = $this->allowRedirect;
            if($this->redirectLimit > 0) {
                 $options[CURLOPT_MAXREDIRS] = $this->redirectLimit;
            }
        }

        // set path to cookie file
        if(null != $this->cookieFile) {
            $options[CURLOPT_COOKIEFILE] = $this->cookieFile;
        }

        // allow to write cookies in file
        if(!$this->cookieFileReadOnly) {
            $options[CURLOPT_COOKIEJAR] = $this->cookieFile;
        }

        // set custom cookies
        if(!empty($this->cookies)) {
            $cookieStr = '';
            foreach ($this->cookies as $k => $v) {
                $cookieStr .= "{$k}={$v}; ";
            }
            $cookieStr = trim($cookieStr);
            $options[CURLOPT_COOKIE] = $cookieStr;
        }

        if(!empty($this->proxy)) {
            $options[CURLOPT_PROXY] = $this->proxy;
            $options[CURLOPT_PROXYPORT] = $this->proxyPort;
            $options[CURLOPT_PROXYMETHOD] = $this->proxyType;
            if(!empty($this->proxyUsrPwd)) {
                $options[CURLOPT_PROXYAUTH] = true;
                $options[CURLOPT_PROXYUSERPWD] = $this->proxyUserPwd;
            }
        }

        // remove request method options to avoid problems
        unset($options[CURLOPT_NOBODY], $options[CURLOPT_HTTPGET], $options[CURLOPT_CUSTOMREQUEST], $options[CURLOPT_POST]);
        // set request data
        if(!empty($this->postParams)) {
            if(!in_array($this->method, array(self::METHOD_POST, self::METHOD_PUT))) {
                $this->method = self::METHOD_POST;
            }
            $options[CURLOPT_POSTFIELDS] = $this->preparePostData($this->postParams);
        } else if(self::METHOD_POST == $this->method) {
            // FIXME check and remove this section if needed
            // fix POST request with empty data issue (Content-Length: -1)
            foreach ($this->headers as $k => $h) {
                if(false !== stripos($h, 'Content-Length')) {
                    unset($this->headers[$k]);
                } else if (false !== stripos($h, 'Content-type')) {
                    unset($this->headers[$k]);
                }
            }
            $this->headers[] = 'Content-Type: multipart/form-data';
            $this->headers[] = 'Content-Length: 0';
        }

        // set curl request method
        switch ($this->method) {
            case self::METHOD_HEAD:
               $options[CURLOPT_NOBODY] = true;
               break;
            case self::METHOD_GET:
               $options[CURLOPT_HTTPGET] = true;
               break;
            case self::METHOD_POST:
               $options[CURLOPT_POST] = true;
               break;
            default:
               $options[CURLOPT_CUSTOMREQUEST] = $this->method;
               break;
        }

        // append request headers
        $options[CURLOPT_HTTPHEADER] = $this->headers;
        // sort the array with options to avoid some problems with POST requests
        ksort($options);
        // ser curl options
        curl_setopt_array($this->getResource(), $options);

       return $this;
   }

   /**
    * Prepares data to be able to send multidimensional array
    * @param array|object $data
    * @param string $prefix [optional]
    * @return array
    */
   protected function preparePostData($data, $prefix = null)
   {
        $result = array();
        is_object($data) && null != ($props = get_object_vars($data)) && $data = $props;
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid argument $data given. Expected array or object.');
        }
        foreach ($data as $k => $v) {
            $key = $prefix ? "{$prefix}[{$k}]" : $k;
            if (is_scalar($v) || null === $v) {
                $result[$key] = $v;
            } else {
                $result = array_merge($result, $this->preparePostData($v, $key));
            }
        }
        return $result;
   }

   /**
    * Closes connection
    * @return Curl\Request
    */
   public function close()
   {
       if(is_resource($this->getResource())) {
           curl_close($this->getResource());
       }
       $this->_ch = null;
       return $this;
   }

   /**
    * @param string $url
    */
   public function setUrl($url)
   {
        $this->url = $url;
        return $this;
   }

   /**
    * @return string
    */
   public function getUrl()
   {
       return $this->url;
   }

   /**
    * @param string $method
    */
   public function setMethod($method)
   {
        $this->method = strtoupper($method);
        return $this;
   }

   /**
    * @return string
    */
   public function getMethod()
   {
       return $this->method;
   }

   /**
    * Defines POST params
    * @param array $params
    */
   public function setPostParams(array $params)
   {
        $this->postParams = $params;
        return $this;
   }

   /**
    * Appends POST params to request, overwrites duplicate keys
    * @param array $params
    */
   public function addPostParams(array $params)
   {
        $this->postParams = $params + $this->postParams;
        return $this;
   }

   /**
    * @return array
    */
   public function getPostParams()
   {
       return $this->postParams;
   }

   /**
    * Attaches files in POST data
    * @param array $files [key => file] or [key => [file1, file2, file3]]
    * @throws \InvalidArgumentException
    */
   public function attachFiles(array $files)
   {
        $attachments = array();
        foreach ($files as $fieldname => $file) {
            if(is_array($file)) {
                foreach($file as $k => $filename) {
                    $pathToFile = $filename;
                    if(is_file($filename) && false !== ($path = realpath($filename))) {
                        $attachments["{$fieldname}[{$k}]"] = '@'. $pathToFile;
                    } else {
                        trigger_error('Invalid filename: '. $filename, E_USER_NOTICE);
                    }
                }
            } else if(is_string($file)) {
                $pathToFile = $file;
                if(is_file($file) && false !== ($path = realpath($file))) {
                    $attachments[$fieldname] = '@'. $file;
                } else {
                    trigger_error('Invalid filename: '. $file, E_USER_NOTICE);
                }
            }
        }
        $this->addPostParams($attachments);
        return $this;
   }

   /**
    * Defines request headers
    * @param array $headers
    */
   public function setHeaders(array $headers)
   {
       $this->headers = $headers;
       return $this;
   }

   /**
    * Appends headers to request, overwrites duplicate keys
    * @param array $headers
    */
   public function addHeaders(array $headers)
   {
       $this->headers = $headers + $this->headers;
       return $this;
   }

   /**
    * @return array
    */
   public function getHeaders()
   {
       return $this->headers;
   }

   /**
    * Defines cURL options array
    * @param array $options
    */
   public function setOptions(array $options)
   {
       $this->options = $options;
       return $this;
   }

   /**
    * Appends certain cURL options, overwrite duplicate keys
    * @param array $options
    */
   public function addOptions(array $options)
   {
       $this->options = $options + $this->options;
       return $this;
   }

   /**
    * @return array
    */
   public function getOptions()
   {
       return $this->options;
   }

   /**
    * @param string $pathToFile
    * @param boolean $write [optional]
    */
   public function setCookieFile($pathToFile, $write = false)
   {
       if($write && !is_writable(($dir = dirname($pathToFile)))) {
           throw new \Exception('Check write permissions to directory: '. $dir);
       } else if(!$write && file_exists($pathToFile)) {
           throw new \InvalidArgumentException('Couldn\'t find the cookie file: '. $pathToFile);
       }
       $this->cookieFile = $pathToFile;
       $this->cookieFileReadOnly = !$write;
       return $this;
   }

   /**
    * Define request cookies
    * @param array $cookies
    */
   public function setCookies(array $cookies)
   {
       $this->cookies = $cookies;
       return $this;
   }

   /**
    * Appends certain request cookies, overwrite duplicate keys
    * @param array $cookies
    */
   public function addCookies(array $cookies)
   {
       $this->cookies = $cookies + $this->cookies;
       return $this;
   }

   /**
    * @return array
    */
   public function getCookies()
   {
       return $this->cookies;
   }

   /**
    * Attaches the callback
    * @param object $callback
    * @throws Exception
    */
   public function setCallback($callback)
   {
       $this->on(self::EVENT_COMPLETE, $callback);
       return $this;
   }

   /**
    * Sets up the request timeout in sec
    * @param integer $value
    */
   public function setTimeout($value)
   {
       $this->timeout = (int) $value;
       return $this;
   }

   /**
    * Sets up the request connection timeout in sec
    * @param integer $value
    */
   public function setConnectionTimeout($value)
   {
       $this->connectionTimeout = (int) $value;
       return $this;
   }

   /**
    * If true, allow automatically redirect id needed
    * but only certain number of times
    * Note: it works only if php safe_mode is Off
    * @param boolean $flag
    * @param integer $limit [optional] 0 = unlimited
    */
   public function setAllowRedirect($flag, $limit = null)
   {
       $this->allowRedirect = (boolean) $flag;
       if(null !== $limit) {
           $this->redirectLimit = max(0, (int) $limit);
       }
       return $this;
   }

   /**
    * Sets up request referer url
    * @param string $url
    */
   public function setRefererUrl($url)
   {
       $this->refererUrl = $url;
       return $this;
   }

   /**
    * Sets up request HTTP_USER_AGENT
    * @param string $ua default $_SERVER['HTTP_USER_AGENT']
    */
   public function setUserAgent($ua = null)
   {
       if(!empty($ua)) {
           $this->userAgent = $ua;
       } else if(isset($_SERVER['HTTP_USER_AGENT'])) {
           $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
       }
       return $this;
   }

   /**
    * Sets up proxy params
    * @param string $proxy
    * @param string $port [optional]
    * @param integer $type [optional] default CURLPROXY_HTTP
    */
   public function setProxy($proxy, $port = null, $type = CURLPROXY_HTTP)
   {
       $this->proxy = $proxy;
       $this->proxyPort = $port;
       $this->proxyType = $type;
       return $this;
   }

   /**
    * Sets up proxy owner credentials
    * @param string $user
    * @param string $password
    */
   public function setProxyUserPwd($user, $password)
   {
       $this->proxyUserPwd = $user .':'. $password;
       return $this;
   }



   /**
    * Sets up the custom user data to be able to get it later
    * @param array $data
    */
   public function setCustomData(array $data)
   {
       $this->customData = $data;
       return $this;
   }

   /**
    * Returns defined custom data
    * @return array
    */
   public function getCustomData()
   {
       return $this->customData;
   }

   /**
    * Returns the curl resource
    */
   public function getResource()
   {
       return $this->_ch;
   }

   /**
    * Sends request
    * @return Curl\Response\ResponseInterface
    */
   public function send()
   {
        $this->prepare();
        $this->trigger(self::EVENT_BEFORE_SEND);
        $result = curl_exec($this->getResource());
        $this->setResponse($result, true);
        return $this->getResponse();
   }

   /**
    * Sets up the response type. Generates a notice if invalid type given
    * @param string $type
    */
   public function setResponseType($type)
   {
        switch (strtolower(trim((string) $type))) {
            case 'json':
                $this->setResponseClass('Curl\Response\JSONResponse');
                break;
            case 'xml':
                $this->setResponseClass('Curl\Response\XMLResponse');
                break;
            case 'default':
            case 'text':
            case 'plain':
                $this->setResponseClass('Curl\Response\PlainResponse');
                break;
            default:
                trigger_error('Unsupported response type "'. $type .'". Default type set.', E_USER_NOTICE);
                $this->setResponseClass('Curl\Response\PlainResponse');
                break;
        }
        return $this;
   }

   /**
    * Defines response class
    * @param string $className
    */
    public function setResponseClass($className)
    {
        if(class_exists($className) && ($r = new \ReflectionClass($className))
            && $r->implementsInterface('Curl\Response\ResponseInterface')
        ) {
            $this->responseClass = $className;
            return $this;
        }
        throw new \InvalidArgumentException('Invalid class given. Response class must implement Curl\Response\ResponseInterface interface');
    }

    /**
     * Returns defined response class
     * @return string
     */
    public function getResponseClass()
    {
        return $this->responseClass;
    }

   /**
    * Sets up the response options which will be defined after construct
    * @param array $options
    */
   public function setResponseOptions(array $options)
   {
        $this->responseOptions = $options;
        return $this;
   }

   /**
    * Sets up the response
    * @param string $result
    * @param boolean $autoClose
    */
   public function setResponse($result, $autoClose = true)
   {
        if(false === $result) {
            $result = '';
        }
        $this->response = new $this->responseClass();
        foreach ($this->responseOptions as $k => $v) {
            if (isset($this->response->{$k})) {
                $this->response->{$k} = $v;
            } else {
                $setter = 'set'. ucfirst($k);
                method_exists($this->response, $setter) && $this->response->{$setter}($v);
            }
        }
        $this->response->init($this->getResource(), (string) $result);

        // handle events
        if($this->response->hasError()) {
           $this->trigger(self::EVENT_ERROR);
        } else {
           $this->trigger(self::EVENT_SUCCESS);
        }
        $this->trigger(self::EVENT_COMPLETE);

        if($autoClose) {
           $this->close();
        }
        return $this;
   }

   /**
    * @return Curl\Response\ResponseInterface
    */
   public function getResponse()
   {
       if(null === $this->response) {
           $this->send();
       }
       return $this->response;
   }

}