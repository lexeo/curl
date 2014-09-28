<?php
namespace Curl\Response;

/**
 * @author Alexey "Lexeo" Grishatkin
 * @version 0.3
 * @since 0.3
 */
class XMLResponse extends PlainResponse
{
    /**
     * Raw response content
     * @var string
     */
    public $contentRaw;

    /**
     * @var \SimpleXMLElement
     */
    public $content;

    /**
     * @var boolean
     */
    public $autoParse = true;

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
        $this->autoParse && $this->parse();
        return $this;
    }

    /**
     * Returns raw XML response string
     * @return string
     */
    public function __toString()
    {
        return $this->contentRaw;
    }

    /**
     * Convets response XML to array
     * @param boolean $ignoreAttributes [optional, default=false]
     * @return array
     */
    public function toArray($ignoreAttributes = false)
    {
        null === $this->content && $this->parse(LIBXML_NOCDATA);
        return $this->xml2array($this->content, $ignoreAttributes);
    }

    /**
     * Convets SimpleXMLElement object to array
     * @param \SimpleXMLElement $element
     * @param boolean $ignoreAttributes [optional, default=false]
     * @return array
     */
    public static function xml2array(\SimpleXMLElement $element, $ignoreAttributes = false)
    {
        $result = array();
        if (!$ignoreAttributes) {
            foreach ($element->attributes() as $attr => $value) {
                $result['@attributes'][$attr] = (string) $value;
            }
        }
        if ($element->count()) {
            foreach ($element->children() as $name => $child) {
                /* @var $child SimpleXMLElement */
                $childArray = self::xml2array($child);
                if (count($childArray) == 1 && array_key_exists($name, $childArray)) {
                    $result[$name] = array_shift($childArray);
                } else {
                    $result[$name] = $childArray;
                }
            }
        } else if (isset($result['@attributes'])) {
            $result['@value'] = (string) $element;
        } else {
            return array($element->getName() => (string) $element);
        }
        return $result;
    }

    /**
     * Parses XML response and returns SimpleXMLElement object
     * @param integer $options [optional]
     * @param string $ns [optional]
     * @return SimpleXMLElement
     */
    public function parse($options = null, $ns = null)
    {
        libxml_use_internal_errors(true);
        $this->content = simplexml_load_string($this->contentRaw, null, $options, $ns);
        if (!$this->content instanceof \SimpleXMLElement && null != ($err = libxml_get_last_error())) {
            /* @var $err LibXMLError */
            $this->error = array(
            	'code' => $err->code,
                'message' => $err->message,
            );
        }
        return $this->content;
    }
}