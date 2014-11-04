<?php
namespace Curl;
use Curl\Request;

/**
 * Curl Multi Executor
 * @author Alexey "Lexeo" Grishatkin
 * @version 0.3.1
 */
class MultiExecutor
{
    private $_mh;
    private $_isRunning = false;
    protected $concurrentRequestsLimit = 5;
    protected $requestTimeout = 10;

    protected $requests = array();
    protected $requestMap = array();

    protected $commonRequestOptions = array();
    protected $lastExecutionTime;
    protected $lastExecutionSentRequests = 0;


    /**
     * Resets the request list and common options if needed
     * @param boolean $resetCommonOptions [optional] default false
     */
    public function reset($resetCommonOptions = false)
    {
        $this->_mh = null;
        $this->requests = array();
        $this->requestMap = array();
        $resetCommonOptions && $this->commonRequestOptions = array();
        return $this;
    }

    /**
     * Add request
     * @param Curl\Request $request
     */
    public function addRequest(Request $request)
    {
        $reflection = new \ReflectionClass($request);
        if(!empty($this->commonRequestOptions)) {
            $possibleCurlOpts = array();
            $eventTypes = Request::getAvailableEventTypes();
            foreach ($this->commonRequestOptions as $k => $p) {
                if(is_string($k)) {
                    try {
                        if($reflection->hasMethod(($m = 'set'. ucfirst($k)))) {
                            // try to set param
                            call_user_func_array(array($request, $m), (array) $p);
                        } else if(isset($eventTypes[strtolower($k)])) {
                            // try to attach event handler
                            $request->on(strtolower($k), $p);
                        }
                    } catch (\Exception $e) {
                        // TODO add debug mode
                        trigger_error('Invalid param in common options. Error: '. $e->getMessage(), E_USER_NOTICE);
                    }
                } else if(is_numeric($k)) {
                    $possibleCurlOpts[$k] = $p;
                }
            } // end foreach
            // try to set cURL option
            $request->setOptions($possibleCurlOpts);
        }
        $this->requests[] = $request;
        return $this;
    }

    /**
     * Defines common request options
     * @param array $options
     */
    public function setCommonRequestOptions(array $options)
    {
        $this->commonRequestOptions = $options;
        return $this;
    }

    /**
     * Sets up the request timeout in sec
     * @param integer $value
     */
    public function setRequestTimeout($value)
    {
        $this->requestTimeout = (int) $value;
        return $this;
    }

    /**
     * Sets up the number of cuncurrent requests
     * @param integer $value min: 1
     */
    public function setConcurrentRequestsLimit($value)
    {
        $this->concurrentRequestsLimit = max(1, (int) $value);
        return $this;
    }

    /**
     * Execute
     * @return integer number of sent requests
     */
    public function execute()
    {
        $requestCount = $this->requestCount();
        if(0 == $requestCount) {
            $this->lastExecutionTime = $this->lastExecutionSentRequests = 0;
            return 0;
        } else if(1 == $requestCount) {
            /* @var $request Request */
            $request = array_shift($this->requests);
            $request->send();
            $requestCount = $this->requestCount();
            if($requestCount > 1 && !$this->isRunning()) {
                $this->execute();
            } else if(1 == $requestCount) {
                trigger_error('', E_USER_WARNING, 'Only one request available. The multichannel execution requires more than one request');
            }
            return 1;
        }

        $this->_mh = curl_multi_init();

        $i = 0;
        foreach ($this->requests as $k => $request) {
            /* @var $request Request */
            $ch = $request->prepare()->getResource();
            curl_multi_add_handle($this->_mh, $ch);

            $stringKey = (string) $ch;
            $this->requestMap[$stringKey] = $k;

            $i++;
            if($i > $this->concurrentRequestsLimit) {
                break;
            }
        }

        $timeStart = microtime(1);
        // number of sent requests
        $sentCount = 0;
        $this->_isRunning = true;

        $stillRunning = null;
        do {
            while (CURLM_CALL_MULTI_PERFORM == ($execurn = curl_multi_exec($this->_mh, $stillRunning))) {
                if(CURLM_OK != $execurn) {
                    break;
                }
            }
            while (false !== ($done = curl_multi_info_read($this->_mh))) {
                $ch = $done['handle'];
                $stringKey = (string) $ch;
                /* @var $request Request */
                $request = $this->requests[$this->requestMap[$stringKey]];
                // trigger beforeSend
                $request->trigger(Request::EVENT_BEFORE_SEND);
                $result = curl_multi_getcontent($ch);
                // set response
                $request->setResponse($result);
                $sentCount++;

                // remove completed requests
                unset($this->requests[$this->requestMap[$stringKey]], $this->requestMap[$stringKey]);
                if(count($this->requests)) {
                    if(false !== ($current = each($this->requests))) {
                        /* @var $r Request */
                        list($k, $r)  = $current;
                        $ch = $r->prepare()->getResource();
                        curl_multi_add_handle($this->_mh, $ch);

                        $stringKey = (string) $ch;
                        $this->requestMap[$stringKey] = $k;
                    }
                }

                curl_multi_remove_handle($this->_mh, $done['handle']);
            }

            if($stillRunning) {
                curl_multi_select($this->_mh, $this->requestTimeout);
            }

        } while ($stillRunning);

        curl_multi_close($this->_mh);

        $execTime = microtime(1) - $timeStart;
        $this->lastExecutionTime = $execTime;
        $this->lastExecutionSentRequests = $sentCount;
        $this->_isRunning = false;

        return $sentCount;
    }


    /**
     * Returns boolean true if executor is still running
     * @return boolean
     */
    public function isRunning()
    {
        return $this->_isRunning;
    }

    /**
     * Returns the number of unsent requests
     * @return integer
     */
    public function requestCount()
    {
        return count($this->requests);
    }
}