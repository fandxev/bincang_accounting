<?php

require_once 'config/Database.php';

$db = new Database();
$conn = $db->connect();

// Ambil URL, misal: salary/index
$url = $_GET['url'];

$urlParts = explode('/', $url);

$controllerName = ucfirst($urlParts[0]) . 'Controller';


$controllerFile = "controllers/{$controllerName}.php";


/*cek apakah ada function dengan nama sesuai path param, jika tidak ada maka ambil berdasarkan jenis request_method nya (restful) */





if (file_exists($controllerFile)) {
    require_once $controllerFile;
    $controller = new $controllerName($conn);


    //1.Jika nama method ada di controller
    if (isset($urlParts[1])) {
        if (method_exists($controller, $urlParts[1])) {
            $action = $urlParts[1];
            $pathParam = array_slice($urlParts, 2);

            call_user_func_array([$controller, $action], $pathParam);
        }
        //2.RESTFUL berdasarkan tipe request
        else {
            $action = strtolower($_SERVER['REQUEST_METHOD']);
            $pathParam = array_slice($urlParts, 1);

            call_user_func_array([$controller, $action], $pathParam);
        }
    } else {
        $action = strtolower($_SERVER['REQUEST_METHOD']);
        $pathParam = array_slice($urlParts, 1);

        call_user_func_array([$controller, $action], $pathParam);
    }
} else {
    echo "Controller '$controllerName' tidak ditemukan.";
}
