<?php

// test redirect
if(isset($_GET['redirect'])) {
    $i = isset($_GET['i']) ? (int) $_GET['i'] : 0;
    $l = isset($_GET['l']) ? (int) $_GET['l'] : 5;

    if($i < $l) {
        $uri = $_SERVER['SCRIPT_NAME'];
        header("Location: {$uri}?redirect=1&i={$i}&l={$l}");
        exit;
    }
    unset($_GET['i'], $_GET['l']);
}

$requestMethod = $_SERVER['REQUEST_METHOD'];
$result = array(
    'method' => $requestMethod,
    'params' => array(),
    'files' => array(),
);
$inputData = '';

switch ($requestMethod) {
    case 'GET':
        $result['params'] = decodeData($_GET);
        break;
    case 'POST':
        $result['params'] = decodeData($_POST);
        $result['files'] = $_FILES;
        break;
    case 'PUT':
    case 'DELETE':
    default:
        $inputData = file_get_contents('php://input');
        $result = array_merge($result, parseInputData($inputData));
        break;
}

if (!isset($_GET['xml'])) {
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    $xml = new SimpleXMLElement('<root/>');
    $xml = array2xml($result, $xml);

    header('Content-type: text/xml');
    echo $xml->asXML();
}

/**
 * @param array $data
 * @param SimpleXMLElement $xml
 * @return SimpleXMLElement
 */
function array2xml(array $data, SimpleXMLElement $xml) {
    foreach ($data as $k => $v) {
        is_array($v) ? array2xml($v, $xml->addChild($k)) : $xml->addChild($k, $v);
    }
    return $xml;
}

/**
 * Parses php input data
 * @param string $data
 * @return array
 */
function parseInputData($data) {
    $result = array();
    $pattern = '#Content-Disposition: form-data; name="([^\"]+)"(?:; filename="([^\"]+)")?[\w\W]*?\s{3,}([\w\W]+?)\s*-+#mi';
    if(preg_match_all($pattern, $data, $m, PREG_SET_ORDER)) {
        foreach ($m as $v) {
            if(!empty($v[2])) {
                // append file
                $result['files'][$v[1]] = $v[2];
            } else if(isset($v[3])) {
                // append param => value
                $result['params'][$v[1]] = decodeData($v[3]);
            }
        }
    }
    return $result;
}

/**
 * @param mixed $data
 * @return mixed
 */
function decodeData($data) {
    if (is_numeric($data)) {
        return (ctype_digit($data) ? (int) $data : (float) $data);
    } else if (in_array($data, array('true', 'false'), true)) {
        return filter_var($data, FILTER_VALIDATE_BOOLEAN);
    } else if ('null' === $data) {
        return null;
    } else if (is_array($data)) {
        $result = array();
        foreach ($data as $k => $v) {
            $result[$k] = decodeData($v);
        }
        return $result;
    }
    return $data;
}