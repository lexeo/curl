<?php
namespace Curl\Response;

/**
 * @author Alexey "Lexeo" Grishatkin
 * @version 0.3
 * @since 0.3
 */
class JSONResponse extends PlainResponse
{
    /**
     * Raw response content
     * @var string
     */
    public $contentRaw;

    /**
     * @var boolean
     */
    public $autoDecode = true;

   /**
    * (non-PHPdoc)
    * @see Curl\ResponseInterface::init($resource, $result)
    */
    public function init($resource, $result)
    {
        if(is_resource($resource)) {
            $this->info = curl_getinfo($resource);
            $this->error = array(
                'code' => curl_errno($resource),
                'message' => curl_error($resource),
            );
        }
        $this->contentRaw = $result;
        $this->autoDecode && $this->decode();
        return $this;
    }

    /**
     * Returns raw JSON response string
     * @return string
     */
    public function __toString()
    {
        return $this->contentRaw;
    }

    /**
     * Returns json decoded data as array
     * @return array
     */
    public function toArray()
    {
        return is_array($this->content) ? $this->content : $this->decode(true);
    }

    /**
     * Decodes JSON response content
     * @param boolean $assoc [optional, default=false]
     * @param integer $depth [optional, default=512]
     * @return \stdClass|array
     */
    public function decode($assoc = false, $depth = 512)
    {
        $this->content = json_decode($this->contentRaw, $assoc, $depth);
        if (null === $this->content && JSON_ERROR_NONE != ($errno = json_last_error())) {
            if (function_exists('json_last_error_msg')) {
                $this->error = array('code' => $errno, 'message' => json_last_error_msg());
            } else {
                $e = self::jsonErrorMessages();
                $this->error = array('code' => $errno, 'message' => (isset($e[$errno]) ? $e[$errno] : 'Unknown error'));
            }
        }
        return $this->content;
    }

    /**
     * Returns the list of available error messages
     * @return array [code, message]
     */
    public static function jsonErrorMessages()
    {
        return array(
            JSON_ERROR_NONE => '',
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
        );
    }
}