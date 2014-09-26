<?php
namespace Curl\Response;

/**
 * Response class
 * @author Alexey "Lexeo" Grishatkin
 * @version 0.3b
 */
class PlainResponse implements ResponseInterface
{
    public $error;
    public $info;
    public $content;

    /**
     * Constructor
     * @param resource $resource [optional]
     * @param string $result [optional]
     */
    public function __construct($resource = null, $result = null)
    {
        null !== $result && $this->init($resource, $result);
    }

    /**
     * (non-PHPdoc)
     * @see Curl\ResponseInterface::init($resource, $result)
     */
    public function init($resource, $result)
    {
        $this->content = $result;
        if(is_resource($resource)) {
            $this->info = curl_getinfo($resource);
            $this->error = array(
                'code' => curl_errno($resource),
                'message' => curl_error($resource),
            );
        }
        return $this;
    }

    /**
     * (non-PHPdoc)
     * @see Curl\ResponseInterface::getInfo()
     */
    public function getInfo($asObject = false)
    {
        return $asObject ? (object) $this->info : $this->info;
    }

    /**
     * (non-PHPdoc)
     * @see Curl\ResponseInterface::hasError()
     */
    public function hasError()
    {
        $err = $this->getError();
        return !empty($err['message']) || (is_numeric($err['code']) && 0 !== $err['code']);
    }

    /**
     * (non-PHPdoc)
     * @see Curl\ResponseInterface::getError()
     */
    public function getError()
    {
        return $this->error;
    }

	/**
     * (non-PHPdoc)
     * @see Curl\ResponseInterface::getContent()
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
