<?php

/**
 * @author Alexey "Lexeo" Grishatkin
 * @version 0.3.2
 */
class CurlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array List of created temporary files
     */
    protected static $tmpFiles = array();
    /**
     * @var string host:port
     */
    protected static $webServerHost = '127.0.0.1:7777';
    /**
     * @var integer
     */
    protected static $webServerPid;

    protected static $cookieFileName = 'testcookies.txt';

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        $dr = realpath(dirname(__FILE__));
        $cmd = sprintf(
            'php -S %s -t %s >/dev/null 2>&1 & echo $!',
            escapeshellarg(self::$webServerHost),
            escapeshellarg($dr)
        );
        $output = array();
        exec($cmd, $output);
        if (!isset($output[0]) || !is_numeric($output[0])) {
            throw new \RuntimeException('Failed to run PHP built-in webserver.');
        }
        self::$webServerPid = (int) $output[0];
    }

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        $cmd = sprintf('kill %d', self::$webServerPid);
        exec(escapeshellcmd($cmd));
        self::$webServerPid = null;
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        foreach (self::$tmpFiles as $filename) {
            file_exists($filename) && unlink($filename);
        }
        self::$tmpFiles = array();

        $filename = dirname(__FILE__) . DIRECTORY_SEPARATOR . self::$cookieFileName;
        file_exists($filename) && unlink($filename);
    }

    /**
     * Returns the URL to serverside.php script
     * @return string
     */
    protected function getTestCallbackUrl()
    {
        return 'http://' . self::$webServerHost .'/serverside.php';
    }

    /**
     * @throws \RuntimeException
     * @return string filename
     */
    protected function createTmpFile()
    {
        $dir = sys_get_temp_dir();
        $filename = $dir . DIRECTORY_SEPARATOR . uniqid('phpunit_') . '.txt';
        if (!file_put_contents($filename, uniqid())) {
            throw new \RuntimeException('Failed to create temporary file.');
        }
        return $filename;
    }


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
        $request = Curl\Request::newRequest($this->getTestCallbackUrl());
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
        $request = Curl\Request::newRequest($this->getTestCallbackUrl());
        $params = array(
            'param1' => 1,
            'param2' => 2,
        );
        $files = array(
            'file' => $this->createTmpFile(),
            'file2' => $this->createTmpFile(),
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
        $request = Curl\Request::newRequest($this->getTestCallbackUrl());
        $params = array(
            'param1' => 1,
            'param2' => array(2, 'param3' => 3),
            'param4' => 4,
            'paramNULL' => null,
            'paramBool' => array(true, false),
        );
        $request->setMethod('POST')
            ->addPostParams($params);

        $data = json_decode($request->send(), true);
        $this->assertInternalType('array', $data, 'Invalid response');
        $this->assertArrayHasKey('params', $data, 'Invalid response');
        $this->assertEquals($params, $data['params'], 'One or more params missed or not equal');
    }

    /**
     * Test PUT request with params and files
     */
    public function testPutRequestParamsAndFiles()
    {
        $request = Curl\Request::newRequest($this->getTestCallbackUrl());
        $params = array(
            'param1' => 1,
            'param2' => 2,
        );
        $files = array(
            'file' => $this->createTmpFile(),
            'file2' => $this->createTmpFile(),
        );
        $request->setMethod('PUT')
            ->addPostParams($params)
            ->attachFiles($files)
            ->addOptions(array(CURLINFO_HEADER_OUT => 1));

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
     * Test POST request with files
     */
    public function testForceMultipartContent()
    {
        $request = Curl\Request::newRequest($this->getTestCallbackUrl());
        $params = array(
            'param1' => 1,
            'param2' => 2,
        );
        $files = array(
            'file' => $this->createTmpFile(),
            'file2' => $this->createTmpFile(),
        );
        $invalidHeaders = array(
            'Content-Type: application/json',
            'Content-Length: 100500',
        );
        $request->setMethod('POST')
            ->addPostParams($params)
            ->attachFiles($files)
            ->addHeaders($invalidHeaders)
            ->addOptions(array(
                CURLINFO_HEADER_OUT => 1,
            ));

        $data = json_decode($request->send(), true);
        $this->assertNotEmpty($request->getResponse()->getContent());
        $requestHeaders = $request->getResponse()->getInfo(true)->request_header;
        foreach ($invalidHeaders as $h) {
            $this->assertNotContains($h, $requestHeaders);
        }
        $this->assertContains('Content-Type: multipart/form-data', $requestHeaders);

        if(!is_array($data)) {
            $this->fail('Invalid response');
        } else if(!isset($data['params']) || array_diff_key($data['params'], $params)) {
            $this->fail('One or more params missed');
        } else if(!isset($data['files']) || array_diff_key($data['files'], $files)) {
            $this->fail('One or more files missed');
        }
    }

    /**
     * test that default Content-Type header is urlencoded
     */
    public function testDefaultContentTypeIsUrlEncoded()
    {
        $request = Curl\Request::newRequest($this->getTestCallbackUrl());
        $params = array(
            'param1' => 1,
            'param2' => 2,
            'paramBool' => true,
            'paramNull' => null,
        );
        $request->setMethod('POST')
            ->addPostParams($params)
            ->addOptions(array(
                CURLINFO_HEADER_OUT => 1,
            ));
        $data = json_decode($request->send(), true);
        $this->assertNotEmpty($request->getResponse()->getContent());
        $requestHeaders = $request->getResponse()->getInfo(true)->request_header;
        $this->assertContains('application/x-www-form-urlencoded', $requestHeaders);
        $this->assertInternalType('array', $data, 'Invalid response');
        $this->assertArrayHasKey('params', $data, 'Invalid response');
        $this->assertEquals($params, $data['params'], 'One or more params missed or not equal');
    }

    /**
     * test post request without any param
     */
    public function testEmptyPostRequest()
    {
        $request = Curl\Request::newRequest($this->getTestCallbackUrl());
        $request->setMethod('POST')
            ->addOptions(array(
                CURLINFO_HEADER_OUT => 1,
            ));
        $data = json_decode($request->send(), true);
        $this->assertNotEmpty($request->getResponse()->getContent());
        $this->assertInternalType('array', $data, 'Invalid response');
        $requestHeaders = $request->getResponse()->getInfo(true)->request_header;
        $this->assertContains('Content-Length: 0', $requestHeaders);
    }

    /**
     * Test curl multi
     */
    public function testMultiCurl()
    {
        $mh = new Curl\MultiExecutor();
        $callback = function(Curl\Response\ResponseInterface $response, Curl\Request $request) {
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
        $request = Curl\Request::newRequest($this->getTestCallbackUrl() .'?redirect=1');
        $response = $request->setAllowRedirect(true, $limit)->send();
        $this->assertLessThanOrEqual($limit, $response->getInfo(true)->redirect_count);
    }

    /**
     * Test cookie file
     */
    public function testCookieFile()
    {
        // Note: the directory "test" must be writable
        $filename = __DIR__ . DIRECTORY_SEPARATOR . self::$cookieFileName;
        $request = Curl\Request::newRequest('http://google.com');
        $request->setCookieFile($filename, true)->send();
        $this->assertFileExists($filename);
    }

    /**
     * test defining of response class by type
     */
    public function testSetResponseType()
    {
        $request = Curl\Request::newRequest('http://google.com');

        $request->setResponseType('json');
        $this->assertEquals('Curl\Response\JSONResponse', trim($request->getResponseClass(),'\\'));

        $request->setResponseType('xml');
        $this->assertEquals('Curl\Response\XMLResponse', trim($request->getResponseClass(),'\\'));

        $request->setResponseType('plain');
        $this->assertEquals('Curl\Response\PlainResponse', trim($request->getResponseClass(),'\\'));
    }

    /**
     * test set response options
     */
    public function testSetResponseOptions()
    {
        /* @var $response Curl\Response\JSONResponse */
        $autoDecode = false;
        $response = Curl\Request::newRequest('http://google.com')
            ->setResponseOptions(array('autoDecode' => $autoDecode))
            ->setResponseClass('Curl\Response\JSONResponse')
            ->send();
        $this->assertEquals($autoDecode, $response->autoDecode);
        $this->assertNull($response->content);
        $this->assertFalse($response->hasError());
        $response->decode();
        $this->assertTrue($response->hasError());
    }


    /**
     * test JSONResponse
     */
    public function testJSONResponse()
    {
        /* @var $response Curl\Response\JSONResponse */
        $response = Curl\Request::newRequest($this->getTestCallbackUrl())
            ->setResponseClass('Curl\Response\JSONResponse')
            ->send();
        $this->assertInstanceOf('Curl\Response\JSONResponse', $response);
        $this->assertNotEmpty($response->contentRaw);
        $this->assertFalse($response->hasError());
        $this->assertInternalType('object', $response->decode());
        $this->assertObjectHasAttribute('params', $response->content);
        $this->assertInternalType('array', $response->toArray());
    }

    /**
     * test JSONResponse with non-json response text
     */
    public function testInvalidJSONResponse()
    {
        /* @var $response Curl\Response\JSONResponse */
        $response = Curl\Request::newRequest('http://google.com')
            ->setResponseType('json')
            ->send();
        $this->assertInstanceOf('Curl\Response\JSONResponse', $response);
        $this->assertNotEmpty($response->contentRaw);
        $this->assertTrue($response->hasError());
        $error = $response->getError();
        $this->assertNotEquals(0, $error['code']);
        $this->assertNotEmpty($error['message']);
    }

    /**
     * test XMLResponse
     */
    public function testXMLResponse()
    {
        /* @var $response Curl\Response\XMLResponse */
        $response = Curl\Request::newRequest($this->getTestCallbackUrl() .'?xml=1')
            ->setResponseClass('Curl\Response\XMLResponse')
            ->send();
        $this->assertInstanceOf('Curl\Response\XMLResponse', $response);
        $this->assertNotEmpty($response->contentRaw);
        $this->assertFalse($response->hasError());
        $this->assertInstanceOf('SimpleXMLElement', $response->content);
    }

    /**
     * test XMLResponse with non-xml response text
     */
    public function testInvalidXMLResponse()
    {
        /* @var $response Curl\Response\JSONResponse */
        $response = Curl\Request::newRequest('http://google.com')
            ->setResponseType('xml')
            ->send();
        $this->assertInstanceOf('Curl\Response\XMLResponse', $response);
        $this->assertNotEmpty($response->contentRaw);
        $this->assertTrue($response->hasError());
        $error = $response->getError();
        $this->assertNotEquals(0, $error['code']);
        $this->assertNotEmpty($error['message']);
    }

    /**
     * test XML to array converting
     */
    public function testXMLResponseToArray()
    {
        $xml = '<?xml version="1.0" ?>
            <document id="100500">
                <title>Forty What?</title>
                <from email="email@example.com">Joe</from>
                <to>Jane</to>
                <body>I know that\'s the answer -- but what\'s the question?</body>
                <items>
                    <item><name>foo</name><value>bar</value></item>
                    <item><name>bar</name><value>baz</value></item>
                    <item><name>baz</name><value>foo</value></item>
                    <item id="1"/>
                    <item id="2"/>
                </items>
            </document>';
        $response = new Curl\Response\XMLResponse(null, $xml);
        $result = $response->toArray(true);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayNotHasKey('@attributes', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(4, $result['items']);

        $result = $response->toArray(false);
        $this->assertArrayHasKey('@attributes', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(5, $result['items']);
    }

    /**
     * Advanced test XML to array converting
     */
    public function textXMLResponseToArrayAdv()
    {
        $xml = '<?xml version="1.0" ?>
            <root>
                <item>
                    <title>Item1</title>
                </item>
                <item>
                    <title>Item2</title>
                </item>
            </root>';
        $response = new Curl\Response\XMLResponse(null, $xml);
        $result = $response->toArray();
        $this->assertInternalType('array', $result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('title', $result[0]);
        $this->assertArrayHasKey('title', $result[1]);

        $xml = '<?xml version="1.0" ?>
            <root>
                <item name="item1">
                    <value>Item1</value>
                </item>
            </root>';
        $response = new Curl\Response\XMLResponse(null, $xml);
        $result = $response->toArray();
        $this->assertInternalType('array', $result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('item', $result);
        $this->assertArrayHasKey('value', $result['item']);
    }

    /**
     * test send a copy of request
     */
    public function testCloneRequest()
    {
        $request = Curl\Request::newRequest($this->getTestCallbackUrl());
        $params = array(
            'param1' => 1,
            'param2' => 2,
        );
        $request->setMethod('POST')->addPostParams($params);
        $result = json_decode($request->send(), true);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('param2', $result['params']);

        $result = null;
        try {
            $result = $request->send();
        } catch (\BadMethodCallException $e) {
        }
        $this->assertNull($result);

        $request2 = clone $request;
        $result = $result = json_decode($request2->send(), true);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('param2', $result['params']);
    }

}
