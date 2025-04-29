<?php
require __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

function generate_uuid()
{
    return Uuid::uuid4()->toString();
}

function errorResponse($code, $message)
{
    $responError = [
        "status" => "error",
        "code" => $code,
        "message" => $message
    ];
    header('Content-Type: application/json');
    echo json_encode($responError);
}
