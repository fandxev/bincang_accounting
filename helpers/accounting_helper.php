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


function getEmployeeName($user_uuid, $conn)
{
    $sql = "SELECT profile_first_name, profile_last_name FROM bincang_user_profile WHERE profile_user_uuid = :user_uuid";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':user_uuid', $user_uuid, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);


    $fullName = trim($user['profile_first_name'] . ' ' . $user['profile_last_name']);

    return $fullName;
}



function get_recent_last_capital($conn)
    {
        $tableTotalCapital = 'bincang_capital_total';
        try {
            $sql = "SELECT total_capital FROM " .$tableTotalCapital . " ORDER BY created_at DESC LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $lastCapital = $result['total_capital'];
                return $lastCapital;
            } else {
                return 0;
            }
        } catch (PDOException $e) {
            echo "Error get last capital: " . $e->getMessage();
        }
    }

function check_if_salaryuuid_exist_in_capital($salary_uuid, $conn)
{
    $tableCapital = 'bincang_capital';
    try {
        $sql = "SELECT COUNT(*) FROM " . $tableCapital . " WHERE salary_uuid = :salary_uuid";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':salary_uuid', $salary_uuid, PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetchColumn();
        return $count > 0;
    } catch (PDOException $e) {
        echo "Error checking salary_uuid in capital: " . $e->getMessage();
    }
}


function get_capital_by_salary_uuid($salary_uuid, $conn)
{
    $tableCapital = 'bincang_capital';
    try {
        $sql = "SELECT * FROM " . $tableCapital . " WHERE salary_uuid = :salary_uuid";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':salary_uuid', $salary_uuid, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC); // ambil satu baris data
        return $result ? $result : null;
    } catch (PDOException $e) {
        echo "Error retrieving capital by salary_uuid: " . $e->getMessage();
        return null;
    }
}

//check apakah capital dengan salaryuuid di soft del atau tidak
function check_capital_active_by_salaryuuid($salary_uuid, $conn)
{
    $tableCapital = 'bincang_capital';
    try {
        $sql = "SELECT deleted_at FROM " . $tableCapital . " WHERE salary_uuid = :salary_uuid ORDER BY created_at DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':salary_uuid', $salary_uuid, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return is_null($result['deleted_at']);
        } else {
            return false;
        }
    } catch (PDOException $e) {
        echo "Error checking capital active status: " . $e->getMessage();
    }
}



function recover_capital_by_salary_uuid($salary_uuid, $conn)
{
    $tableCapital = 'bincang_capital';
    try {
        $sql = "UPDATE " . $tableCapital . " 
                SET deleted_by = NULL, deleted_at = NULL 
                WHERE salary_uuid = :salary_uuid";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':salary_uuid', $salary_uuid, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0; // true jika ada baris yang diubah
    } catch (PDOException $e) {
        echo "Error recovering capital by salary_uuid: " . $e->getMessage();
        return false;
    }
}



function acumulate_amount_total_capital($type_transaction, $amount, $conn)
{
    $tableTotalCapital = 'bincang_capital_total';
    // pastikan koneksi tersedia

    // Cek apakah data sudah ada di table
    $stmt = $conn->prepare("SELECT * FROM {$tableTotalCapital} LIMIT 1");
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $now = time();

    if ($data === false) {
        // Data belum ada, insert nilai awal (positif atau negatif tergantung tipe transaksi)
        $initialCapital = ($type_transaction === 'expense') ? -$amount : $amount;

        $insert = $conn->prepare("INSERT INTO {$tableTotalCapital} (total_capital, created_at, updated_at)
                                  VALUES (:total_capital, :created_at, :updated_at)");
        $insert->execute([
            'total_capital' => $initialCapital,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    } else {
        // Data sudah ada, update nilai total_capital
        $currentCapital = $data['total_capital'];
        $newCapital = $currentCapital;

        if ($type_transaction === 'expense') {
            $newCapital -= $amount;
        } elseif ($type_transaction === 'income') {
            $newCapital += $amount;
        }

        $update = $conn->prepare("UPDATE {$tableTotalCapital} SET total_capital = :total_capital, updated_at = :updated_at WHERE id = :id");
        $update->execute([
            'total_capital' => $newCapital,
            'updated_at'    => $now,
            'id'            => $data['id'],
        ]);
    }
}



function get_type_transaction($id, $conn)
{
    $tableCapital = 'bincang_capital';
    try {
        $sql = "SELECT type_transaction 
                FROM " . $tableCapital . " 
                WHERE id = :id 
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['type_transaction'];
        } else {
            return null;
        }
    } catch (PDOException $e) {
        echo "Error getting type_transaction by ID: " . $e->getMessage();
        return null;
    }
}



   function logCapitalAction($type, $actor, $amount_of_action, $id_bincang_capital, $description, $conn, $previousValue = 0, $newValue = 0){

       try{
        $tableLog = 'bincang_capital_log';
        $total_capital_after_action = get_recent_last_capital($conn);

        $type_transaction = get_type_transaction($id_bincang_capital, $conn);
        if( $type_transaction == "expense")
        {
           $amount_of_action = -abs($amount_of_action); 
        }


        $sqlInsert = "INSERT INTO " . $tableLog . " 
                      (type, actor, amount_of_action, total_capital_after_action, id_bincang_capital, description, timestamp)
                      VALUES 
                      (:type, :actor, :amount_of_action, :total_capital_after_action, :id_bincang_capital, :description, :timeNow)";

        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->bindValue(':type', $type, PDO::PARAM_STR);
        $stmtInsert->bindValue(':actor', $actor, PDO::PARAM_STR);
        $stmtInsert->bindValue(':amount_of_action', $amount_of_action, PDO::PARAM_STR);
        $stmtInsert->bindValue(':total_capital_after_action', $total_capital_after_action, PDO::PARAM_INT);
        $stmtInsert->bindValue(':id_bincang_capital', $id_bincang_capital, PDO::PARAM_INT);
        $stmtInsert->bindValue(':description', $description, PDO::PARAM_STR);
         $stmtInsert->bindValue(':timeNow', time(), PDO::PARAM_STR);

        $stmtInsert->execute();

    }
    catch(PDOException $e){
        // echo "terjadi kesalahan saat logging";
    }
    }