<?php
namespace Curl\Response;

/**
 * Response interface
 * @author Alexey "Lexeo" Grishatkin
 */
interface ResponseInterface
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
