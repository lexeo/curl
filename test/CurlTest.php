<?php

require_once '../src/Request.php';
require_once '../src/Multi.php';

class CurlTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string URL to serverside.php
     */
    protected $testCallbackUrl = 'http://localhost/curl/test/serverside.php';

    /**
     * test Init
     */
    public function testInit()
    {
        $request = Curl\Request::newRequest('http://www.google.com');
        $this->assertTrue(is_resource($request->getResource()));
    }

    /**
     * Test close on complete
     */
    public function testCloseOnComplete()
    {
        $request = Curl\Request::newRequest('http://www.google.com');
        $request->send();
        $this->assertFalse(is_readable($request->getResource()));
    }

    /**
     * Test attach event handler
     */
    public function testAttachEventHandler()
    {
        $request = Curl\Request::newRequest('http://www.google.com');
        $eventTypes = array('beforeSend', 'success', 'complete');
        $handledEvents = 0;
        $callback = function() use (&$handledEvents) {
            $handledEvents++;
        };
        foreach ($eventTypes as $k) {
            $request->on($k, $callback);
        }
        $request->send();
        $this->assertEquals(count($eventTypes), $handledEvents);
    }

    /**
     * Test detach event handler
     */
    public function testDetachEventHandler()
    {
        $request = Curl\Request::newRequest('http://www.google.com');
        $callback = function() {
            $this->fail('An error occurred. This callback must not be executed');
        };
        $request->on('beforeSend', $callback)->off('beforeSend', $callback);
        $request->send();
    }

    /**
     * @return array
     */
    public function providerRequestMethod()
    {
        return array(
            array(Curl\Request::METHOD_GET),
            array(Curl\Request::METHOD_POST),
            array(Curl\Request::METHOD_PUT),
            array(Curl\Request::METHOD_DELETE),
            array(Curl\Request::METHOD_HEAD),

        );
    }

    /**
     * Test available request methods
     * @dataProvider providerRequestMethod
     * @param string $method
     */
    public function testRequestMethod($method)
    {
        $request = Curl\Request::newRequest($this->testCallbackUrl);
        $response = $request->setMethod($method)->send();
        if(Curl\Request::METHOD_HEAD == $method) {
            $this->assertEquals(200, $response->getInfo(true)->http_code);
        } else {
            $data = json_decode($response, true);
            if(!is_array($data) || !isset($data['method'])) {
                $this->fail('Invalid response');
            } else {
                $this->assertEquals($method, $data['method']);
            }
        }

    }

    /**
     * Test POST request with params and files
     */
    public function testPostRequestParamsAndFiles()
    {
        $request = Curl\Request::newRequest($this->testCallbackUrl);
        $params = array(
            'param1' => 1,
            'param2' => 2,
        );
        $files = array(
            'file' => __FILE__,
            'file2' => __FILE__,
        );
        $request->setMethod('POST')
            ->addPostParams($params)
            ->attachFiles($files);

        $data = json_decode($request->send(), true);
        if(!is_array($data)) {
            $this->fail('Invalid response');
        } else if(!isset($data['params']) || array_diff_key($data['params'], $params)) {
            $this->fail('One or more params missed');
        } else if(!isset($data['files']) || array_diff_key($data['files'], $files)) {
            $this->fail('One or more files missed');
        }
    }

    /**
     * Test POST request with multidimensional array of data
     */
    public function testPostRequestMultidimensionalArray()
    {
        $request = Curl\Request::newRequest($this->testCallbackUrl);
        $params = array(
            'param1' => 1,
            'param2' => array(2, 'param3' => 3),
            'param4' => 4,
        );
        $request->setMethod('POST')
            ->addPostParams($params);

        $data = json_decode($request->send(), true);
        $this->assertInternalType('array', $data, 'Invalid response');
        $this->assertArrayHasKey('params', $data, 'Invalid response');
        $this->assertEquals($params, $data['params'], 'One or more params missed');
    }

    /**
     * Test PUT request with params and files
     */
    public function testPutRequestParamsAndFiles()
    {
        $request = Curl\Request::newRequest($this->testCallbackUrl);
        $params = array(
            'param1' => 1,
            'param2' => 2,
        );
        $files = array(
            'file' => __FILE__,
            'file2' => __FILE__,
        );
        $request->setMethod('PUT')
            ->addPostParams($params)
            ->attachFiles($files);

            $data = json_decode($request->send(), true);
        if(!is_array($data)) {
            $this->fail('Invalid response');
        } else if(!isset($data['params']) || array_diff_key($data['params'], $params)) {
            $this->fail('One or more params missed');
        } else if(!isset($data['files']) || array_diff_key($data['files'], $files)) {
            $this->fail('One or more files missed');
        }
    }

    /**
     * Test curl multi
     */
    public function testMultiCurl()
    {
        $mh = new Curl\MultuExecutor();
        $callback = function(Curl\Response $response, Curl\Request $request) {
            // do something
        };
        $beforeSendCallback = function($response, Curl\Request $request) {
            // do something
        };
        $mh->setCommonRequestOptions(array(
            'allowRedirect' => true,
            'timeout' => 5,
            'connectionTimeout' => 2,
            'callback' => $callback,
            'beforeSend' => $beforeSendCallback,
        ))->setConcurrentRequestsLimit(2)
            ->addRequest(new Curl\Request('http://google.com'))
            ->addRequest(new Curl\Request('http://yahoo.com'))
            ->addRequest(new Curl\Request('http://yandex.ru'))
            ->addRequest(new Curl\Request('http://mail.ru'));

        $mh->execute();
    }

    /**
     * Test redirect limit
     */
    public function testRedirectLimit()
    {
        $limit = 2;
        $request = Curl\Request::newRequest($this->testCallbackUrl .'?redirect=1');
        $response = $request->setAllowRedirect(true, $limit)->send();
        $this->assertLessThanOrEqual($limit, $response->getInfo(true)->redirect_count);
    }

    /**
     * Test cookie file
     */
    public function testCookieFile()
    {
        // Note: the directory "test" must be writable
        $filename = __DIR__ . DIRECTORY_SEPARATOR .'testcookies.txt';
        if(file_exists($filename)) {
            unlink($filename);
        }
        $request = Curl\Request::newRequest('http://google.com');
        $request->setCookieFile($filename, true)->send();
        $this->assertFileExists($filename);
    }


}