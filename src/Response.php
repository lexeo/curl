<?php
namespace Curl;

/**
 * Response interface
 * @author lexeo
 * @version 0.1b
 */
interface IResponse
{
    /**
     * Initializes the response
     * @param resource $resource
     * @param string $result
     */
    public function init($resource, $result);
    
    /**
     * Returns the curl_getinfo function result
     * @param boolean $asObject if true returns an StdClass object instead the array
     * @return array|stdClass
     */
    public function getInfo($asObject = false);
    
    /**
     * Returns true if response has error
     * @return boolean
     */
    public function hasError();
    
    /**
     * Returns an array of error info
     * If there is no error occured returns null
     * @return array [code, message]
     */
    public function getError();
    
    /**
     * Returns received data
     * @return string
     */
    public function getContent();
}


/**
 * 
 * Response class
 * @author lexeo
 */
class Response implements IResponse
{
    public $error;
    public $info;
    public $content;
    
    /**
     * Constructor
     * @param resource $resource
     * @param string $result
     */
    public function __construct($resource, $result)
    {
        $this->init($resource, $result);
    }
    
    /**
     * (non-PHPdoc)
     * @see Curl.IResponse::init($resource, $result)
     */
    public function init($resource, $result)
    {
        $this->content = $result;
        if(is_resource($resource)) {
            $this->info = curl_getinfo($resource);
            $this->eror = array(
                'code' => curl_errno($resource),
                'message' => curl_error($resource),
            );
        }
        return $this;
    }
    
    /**
     * (non-PHPdoc)
     * @see Curl.IResponse::getInfo()
     */
    public function getInfo($asObject = false)
    {
        return $asObject ? (object) $this->info : $this->info;
    }
    
    /**
     * (non-PHPdoc)
     * @see Curl.IResponse::hasError()
     */
    public function hasError()
    {
        $err = $this->getError();
        return !empty($err['message']) || (is_numeric($err['code']) && 0 !== $err['code']);
    }
    
    /**
     * (non-PHPdoc)
     * @see Curl.IResponse::getError()
     */
    public function getError()
    {
        return $this->error;
    }
    
	/**
     * (non-PHPdoc)
     * @see Curl.IResponse::getContent()
     */
    public function getContent()
    {
        return $this->content;
    }
    
    
    /**
     * @return string
     */
    public function __toString()
    {
        return $this->content;
    }
}
