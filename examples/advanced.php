<?php

$request = new Curl\Request('http://google.com');

$request->setTimeout(30)
    ->setConnectionTimeout(5)
    // allow auto redirect but limit 5 times
    ->setAllowRedirect(true, 5)
    ->setRefererUrl('http://some.url');


$request->setHeaders(array(
    // set request headers
    'Accept: text/html',
    'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:26.0) Gecko/20100101 Firefox/26.0'
))->addHeaders(array(
    // append another headers
    'Cache-Control: max-age=0'
));

$request->setOptions(array(
    // set curl options array
    CURLOPT_VERBOSE => false,
    CURLOPT_FAILONERROR => true,
))->addOptions(array(
    // append another options to existing
    CURLINFO_HEADER_OUT => true,
));


$request->on('beforeSend', function(/* null */ $request, Curl\Request $request) {
    // before send the request
})->on('success', function(Curl\Response\PlainResponse $response, Curl\Request $request) {
    // if response has no error
    // $response->hasError() retuned false
})->on('error', function(Curl\Response\PlainResponse $response, Curl\Request $request) {
    // if response has an error
    // $response->hasError() retuned true
})->on('compete', function(Curl\Response\PlainResponse $response, Curl\Request $request) {
    // always on complete request
});

$response = $request->send();

// retrieve request info
$info = $response->getInfo();
var_dump($response->getInfo(true)->http_code, $info['content_type']);

/**
 * Custom Response class
 */
class CustomResponse extends Curl\Response\PlainResponse
{
    /**
     * (non-PHPdoc)
     * @see Curl.Response::hasError()
     */
    public function hasError()
    {
        return parent::hasError() && 200 != $this->getInfo(true)->http_code;
    }
}

// use custom response class
// it must implement the Curl\IResponse interface
$request->setResponseClass('CustomResponse');
var_dump($request->send()->hasError());

