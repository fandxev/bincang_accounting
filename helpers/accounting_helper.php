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


function isAccountant($user_uuid,$conn)
{

    $sql = "SELECT COUNT(*) FROM bincang_user_accountant WHERE user_uuid = :user_uuid";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':user_uuid', $user_uuid, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count <= 0){
                          return [
            "status" => "error",
            "code" => 403,
            "message" => "You are not allowed to do this operation"
        ];
    } 
}