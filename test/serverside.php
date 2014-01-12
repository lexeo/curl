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
        $result['params'] = $_GET;
        break;
    case 'POST':
        $result['params'] = $_POST;
        $result['files'] = $_FILES;
        break;
    case 'PUT':
    case 'DELETE':
    default:
        $inputData = file_get_contents('php://input');
        $result = array_merge($result, parseInputData($inputData));
        break;
} 

header('Content-Type: application/json');
echo json_encode($result);

/**
 * Parses php input data
 * @param string $data
 * @return array
 */
function parseInputData($data) {
    $result = array();
    $pattern = '#Content-Disposition: form-data; name="([^\"]+)"(?:; filename="([^\"]+)")?[\w\W]*?\s{3,}([\w\W]+?)\s-+#mi';
    if(preg_match_all($pattern, $data, $m, PREG_SET_ORDER)) {
        foreach ($m as $v) {
            if(!empty($v[2])) {
                // append file
                $result['files'][$v[1]] = $v[2];
            } else if(isset($v[3])) {
                // append param => value
                $result['params'][$v[1]] = $v[3];
            }
        }
    }  
    return $result;
};