<?php

$request = new Curl\Request();
$request->setUrl('http://google.com');
// or
$request = Curl\Request::newRequest('http://google.com');


// making POST request
$request = Curl\Request::newRequest('http://example.com', 'POST', array(
    'param1' => 1,
    'param2' => 2,
));

// add attachments
$request->attachFiles(array(
    'file' => 'filename.txt',
    'file2' => array('filename1.txt', 'filename2.txt'),
));

$response = $request->getResponse();
// or
$response = $request->send();

echo $response->getContent();
// or
echo $response;


