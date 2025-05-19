<?php
class Bincang_Salary
{
    public $conn;
    private $table = 'bincang_salary';
    private $table_capital = 'bincang_capital';
    private $base_url = 'bincang_accounting';

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAll($page = 1, $perPage = 10, $search = null, $startDate = null, $endDate = null)
    {
        $start = ($page - 1) * $perPage;
    
        $baseSql = "FROM bincang_salary s
                     JOIN bincang_user u_input ON s.user_uuid = u_input.user_uuid
                     JOIN bincang_user u_payee ON s.payee_user_uuid = u_payee.user_uuid";
    
        $where = ["s.deleted_at IS NULL"];
        $params = [];
    
        // filter pencarian
        if (!empty($search)) {
            $where[] = "(s.month LIKE :search
                     OR s.year LIKE :search
                     OR s.basic_salary LIKE :search
                     OR s.allowance LIKE :search
                     OR s.bonus LIKE :search
                     OR s.deduction LIKE :search
                     OR s.total_salary LIKE :search
                     OR s.status LIKE :search
                     OR u_input.user_username LIKE :search
                     OR u_payee.user_username LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
    
        // filter tanggal
        if (!empty($startDate) && empty($endDate)) {
            $startTime = strtotime($startDate . " 00:00:00");
            $endTime = strtotime($startDate . " 23:59:59");
            $where[] = "s.created_at BETWEEN :startTime AND :endTime";
            $params[':startTime'] = $startTime;
            $params[':endTime'] = $endTime;
        } elseif (!empty($startDate) && !empty($endDate)) {
            $startTime = strtotime($startDate . " 00:00:00");
            $endTime = strtotime($endDate . " 23:59:59");
            $where[] = "s.created_at BETWEEN :startTime AND :endTime";
            $params[':startTime'] = $startTime;
            $params[':endTime'] = $endTime;
        }
    
        $whereSql = '';
        if (!empty($where)) {
            $whereSql = " WHERE " . implode(" AND ", $where);
        }
    
        // Hitung total data
        $sqlCount = "SELECT COUNT(*) as total " . $baseSql . $whereSql;
        $stmtCount = $this->conn->prepare($sqlCount);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $totalData = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPage = ceil($totalData / $perPage);
    
        // Ambil data sesuai pagination
        $sql = "SELECT 
                    s.salary_uuid,
                    s.user_uuid,
                    s.payee_user_uuid,
                    s.month,
                    s.year,
                    s.basic_salary,
                    s.allowance,
                    s.detail_allowance,
                    s.bonus,
                    s.detail_bonus,
                    s.deduction,
                    s.detail_deduction,
                    s.total_salary,
                    s.payment_date,
                    s.status,
                    s.proof_of_payment,
                    s.note,
                    s.created_at,
                    u_input.user_username AS salary_input_by,
                    u_payee.user_username AS username_payee
                " . $baseSql . $whereSql . "
                ORDER BY s.id DESC
                LIMIT :start, :perPage";
    
        $stmt = $this->conn->prepare($sql);
    
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->execute();
    
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($result as &$item) {
            date_default_timezone_set('Asia/Jakarta');
            $item['created_at'] = date('Y-m-d H:i:s', $item['created_at']);
            
            if(!empty($item['payment_date']))
            {
                $item['payment_date'] = date('Y-m-d', strtotime($item['payment_date']));
            }

            $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
            $host = $_SERVER['HTTP_HOST'];

            $item['proof_of_payment'] = $scheme . '://' . $host . '/' . $this->base_url . '/' . $item['proof_of_payment'];
        }
    
        return [
            "status" => "success",
            "code" => 200,
            "current_page" => $page,
            "total_page" => $totalPage,
            "total_data" => $totalData,
            "data" => $result
        ];
    }

    public function update($salary_uuid, $data, $update_by)
{

        $userIsNotAccountant =  isAccountant($update_by,$this->conn);
        if(!empty($userIsNotAccountant)){
            return $userIsNotAccountant;
        }

    // ambil data lama dari DB
    $sqlOld = "SELECT * FROM {$this->table} WHERE salary_uuid = :id";
    $stmtOld = $this->conn->prepare($sqlOld);
    $stmtOld->bindValue(':id', $salary_uuid);
    $stmtOld->execute();
    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

    if (!$salary_uuid) {
        return errorResponse("400", "id is required");
    }

    if (!$oldData) {
        return errorResponse("404", "Data tidak ditemukan");
    }

    //jangan sampai update status dan salah satu dari nominal salary bersamaan, agar tidak nge bug
if (isset($data['status']) && (
    isset($data['basic_salary']) ||
    isset($data['allowance']) ||
    isset($data['bonus']) ||
    isset($data['deduction'])
)) {
    // Tolak operasi
    return errorResponse("400","Operasi tidak diizinkan: 'status' tidak boleh diset bersamaan dengan komponen gaji lainnya yang berisi nominal.");
}

   


    // merge data baru dengan data lama
    $mergedData = [
        'user_uuid'        => $data['user_uuid']        ?? $oldData['user_uuid'],
        'payee_user_uuid'  => $data['payee_user_uuid']  ?? $oldData['payee_user_uuid'],
        'month'            => $data['month']            ?? $oldData['month'],
        'year'             => $data['year']             ?? $oldData['year'],
        'basic_salary'     => $data['basic_salary']     ?? $oldData['basic_salary'],
        'allowance'        => $data['allowance']        ?? $oldData['allowance'],
        'bonus'            => $data['bonus']            ?? $oldData['bonus'],
        'deduction'        => $data['deduction']        ?? $oldData['deduction'],
        'payment_date'     => $data['payment_date']     ?? $oldData['payment_date'],
        'status'           => $data['status']           ?? $oldData['status'],
        'proof_of_payment' => $data['proof_of_payment'] ?? $oldData['proof_of_payment'],

     'detail_allowance' => isset($data['detail_allowance']) ? json_encode($data['detail_allowance']) : $oldData['detail_allowance'],
      'detail_bonus' => isset($data['detail_bonus']) ? json_encode($data['detail_bonus']) : $oldData['detail_allowance'],
      'detail_deduction' => isset($data['detail_deduction']) ? json_encode($data['detail_deduction']) : $oldData['detail_allowance'],
        'note' => $data['note'] ?? $oldData['note'],
    ];

    // Hitung total_salary
    $total_salary = $mergedData['basic_salary'] + $mergedData['allowance'] + $mergedData['bonus'] - $mergedData['deduction'];

    // do update
    $sql = "UPDATE {$this->table} SET 
        user_uuid = :user_uuid,
        payee_user_uuid = :payee_user_uuid,
        month = :month,
        year = :year,
        basic_salary = :basic_salary,
        allowance = :allowance,
        bonus = :bonus,
        deduction = :deduction,
        total_salary = :total_salary,
        payment_date = :payment_date,
        status = :status,
        proof_of_payment = :proof_of_payment,
        detail_allowance = :detail_allowance,
        detail_bonus = :detail_bonus,
        detail_deduction = :detail_deduction,
        note = :note,



        updated_at = :updated_at
        WHERE salary_uuid = :id AND deleted_at IS NULL AND deleted_by IS NULL";

    $stmt = $this->conn->prepare($sql);


    $stmt->execute([
        ':user_uuid'        => $mergedData['user_uuid'],
        ':payee_user_uuid'  => $mergedData['payee_user_uuid'],
        ':month'            => $mergedData['month'],
        ':year'             => $mergedData['year'],
        ':basic_salary'     => $mergedData['basic_salary'],
        ':allowance'        => $mergedData['allowance'],
        ':bonus'            => $mergedData['bonus'],
        ':deduction'        => $mergedData['deduction'],
        ':total_salary'     => $total_salary,
        ':payment_date'     => $mergedData['payment_date'],
        ':status'           => $mergedData['status'],
        ':proof_of_payment' => $mergedData['proof_of_payment'],
        ':updated_at'       => time(),
        ':id'               => $salary_uuid,
        ':detail_allowance' => $mergedData['detail_allowance'],
        ':detail_bonus' => $mergedData['detail_bonus'],
        ':detail_deduction' => $mergedData['detail_deduction'],
        ':note' => $mergedData['note'],
    ]);


    



    

    $affectedRows = $stmt->rowCount();

    if ($affectedRows > 0) {
        $sqlSelect = "SELECT s.*, u.user_username AS salary_input_by, p.user_username AS username_payee, CONCAT(up.profile_first_name, ' ', up.profile_last_name) AS nama_karyawan
        FROM {$this->table} s
        LEFT JOIN bincang_user u ON s.user_uuid = u.user_uuid
        LEFT JOIN bincang_user p ON s.payee_user_uuid = p.user_uuid
          JOIN bincang_user_profile up ON  s.payee_user_uuid = up.profile_user_uuid
        WHERE salary_uuid = :id";
        $stmtSelect = $this->conn->prepare($sqlSelect);
        $stmtSelect->bindValue(':id', $salary_uuid);
        $stmtSelect->execute();
        $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if ($item) {

            $this->insertOrUpdateToCapital($item, $update_by, $oldData);


            date_default_timezone_set('Asia/Jakarta');
            if(!empty($item['payment_date'])){
              $item['payment_date'] = date('Y-m-d', strtotime($item['payment_date']));
            }
            return [
                "status" => "success",
                "code" => 200,
                "data" => $item
            ];
        }
    }

  

    return [
        "status" => "error",
        "code" => 500,
        "message" => "Gagal memperbarui data."
    ];
}


public function delete($salary_uuid, $deleted_by)
    {

        $userIsNotAccountant =  isAccountant($deleted_by,$this->conn);
        if(!empty($userIsNotAccountant)){
            return $userIsNotAccountant;
        }
        // ambil data sebelum update
    $sqlSelect = "SELECT 
                s.*, 
                u.user_username AS salary_input_by, 
                p.user_username AS username_payee,
                c.id AS id_bincang_capital,
                c.description AS description_bincang_capital
            FROM {$this->table} s
            LEFT JOIN bincang_user u ON s.user_uuid = u.user_uuid
            LEFT JOIN bincang_user p ON s.payee_user_uuid = p.user_uuid
            LEFT JOIN bincang_capital c ON c.salary_uuid = s.salary_uuid
            WHERE s.salary_uuid = :id";
        $stmtSelect = $this->conn->prepare($sqlSelect);
        $stmtSelect->bindValue(':id', $salary_uuid);
        $stmtSelect->execute();
        $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);


        if (!$item) {
            return [
                "status" => "error",
                "code" => 404,
                "message" => "Data tidak ditemukan."
            ];
        }


        $sql = "UPDATE {$this->table} 
            SET deleted_by = :deleted_by, deleted_at = :deleted_at 
            WHERE salary_uuid = :salary_uuid";

        $stmt = $this->conn->prepare($sql);
        $deletedAt = time();

        $stmt->execute([
            ':deleted_by'   => $deleted_by,
            ':deleted_at'   => $deletedAt,
            ':salary_uuid' => $salary_uuid
        ]);

        $affectedRows = $stmt->rowCount();

        if ($affectedRows > 0) {

            //jika status == paid, maka hapus record dari total saldo
            if($item['status'] == "paid"){
                $this->deleteCapitalBySalaryUUID($item['salary_uuid'], $deleted_by);
                acumulate_amount_total_capital("income", $item['total_salary'], $this->conn);
                logCapitalAction("delete", $deleted_by, $item['total_salary'], $item['id_bincang_capital'], $item['description_bincang_capital'], $this->conn);    
            }

            $item['deleted_by'] = $deleted_by;
            $item['deleted_at'] = $deletedAt;



            return [
                "status" => "success",
                "code"   => 200,
                "message" => "Data berhasil dihapus",
                "data" => $item
            ];
        }

        return [
            "status" => "error",
            "code"   => 500,
            "message" => "Gagal menghapus data."
        ];
    }

    public function deleteCapital($capital_uuid, $deleted_by)
    {  require_once 'Bincang_Capital.php';
        $capitalModel = new Bincang_Capital($this->conn);

       $capitalModel->delete($capital_uuid, $deleted_by);

    }

    public function syncLastCapitalAfterRecover($capital_uuid){
        require_once 'Bincang_Capital.php';
       $capitalModel = new Bincang_Capital($this->conn);

       $capitalModel->syncLastCapitalAfterRecover($capital_uuid);
    }



    

    public function insertOrUpdateToCapital($dataUpdateSalary, $user_uuid, $oldDataBeforeUpdate){
        
        //cek jika data capital dgn salary_uuid x belum ada, maka insert
        $oldDataCapital = get_capital_by_salary_uuid($dataUpdateSalary['salary_uuid'],$this->conn);
        if($oldDataCapital == null){
            //data belum ada, maka insert
            $this->insertToCapital($dataUpdateSalary, $user_uuid);
        }
        else{
            //data sudah ada, maka update
             $this->updateToCapital($oldDataCapital, $dataUpdateSalary, $user_uuid, $oldDataBeforeUpdate);
        }
        
    }

public function recoverCapitalBySalaryUUID($salaryUUID, $recover_by="")
{
    $sql = "UPDATE bincang_capital 
            SET deleted_by = NULL, 
                deleted_at = NULL 
            WHERE salary_uuid = :salary_uuid";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindValue(':salary_uuid', $salaryUUID);

    return $stmt->execute(); // true jika sukses, false jika gagal
}


    public function deleteCapitalBySalaryUUID($salaryUUID, $deleted_by){
    $sql = "UPDATE bincang_capital 
            SET deleted_by = :deleted_by, 
                deleted_at = :deleted_at 
            WHERE salary_uuid = :salary_uuid";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindValue(':deleted_by', $deleted_by);
    $stmt->bindValue(':deleted_at', time()); // gunakan time() untuk timestamp saat ini
    $stmt->bindValue(':salary_uuid', $salaryUUID);

    return $stmt->execute();
    }

   public function updateToCapital($oldDataCapital, $dataUpdateSalary, $user_uuid, $oldDataSalaryBeforeUpdate) {
    $deleted_by = null;
    $deleted_at = null;

    // jika status salary unpaid atau pending, tandai sebagai dihapus
    if ($dataUpdateSalary['status'] == "unpaid" || $dataUpdateSalary['status'] == "pending") {
        $deleted_by = $user_uuid;
        $deleted_at = time();
    }

    // update field ke tabel bincang_capital berdasarkan salary_uuid
    $sqlUpdateCapital = "UPDATE {$this->table_capital}
                         SET deleted_at = :deleted_at,
                             updated_at = :updated_at,
                             deleted_by = :deleted_by,
                             amount = :amount
                         WHERE salary_uuid = :salary_uuid";

    $stmt = $this->conn->prepare($sqlUpdateCapital);
    $success = $stmt->execute([
        ':deleted_at'   => $deleted_at,
        ':updated_at'   => time(),
        ':deleted_by'   => $deleted_by,
        ':amount'       => $dataUpdateSalary['total_salary'],
        ':salary_uuid'  => $dataUpdateSalary['salary_uuid'],
    ]);

    //jika ada perubahan si penerima gaji
    if($dataUpdateSalary['payee_user_uuid'] !=  $oldDataSalaryBeforeUpdate['payee_user_uuid']){
        updateUserNotificationSalary($dataUpdateSalary['salary_uuid'],$dataUpdateSalary['payee_user_uuid'],$this->conn);
    }

    //status berubah dari nonpaid menjadi paid
    if($dataUpdateSalary['status'] == "paid" && $oldDataSalaryBeforeUpdate['status'] != "paid")
    {    
    acumulate_amount_total_capital("expense", $dataUpdateSalary['total_salary'], $this->conn);
    addNotificationSalary($dataUpdateSalary['payee_user_uuid'],$dataUpdateSalary['salary_uuid'], $this->conn);
    logCapitalAction("recover", $user_uuid, $dataUpdateSalary['total_salary'], $oldDataCapital['id'], $oldDataCapital['description'], $this->conn);
    }

    //status berubah dari paid menjadi nonpaid
    else if($dataUpdateSalary['status'] != "paid" && $oldDataSalaryBeforeUpdate['status'] == "paid")
    {
    acumulate_amount_total_capital("income", $dataUpdateSalary['total_salary'], $this->conn);
    removeNotificationSalary($dataUpdateSalary['salary_uuid'], $this->conn);
    logCapitalAction("delete", $user_uuid, $dataUpdateSalary['total_salary'], $oldDataCapital['id'], $oldDataCapital['description'], $this->conn);        
    }

    else if (($oldDataCapital['amount'] != $dataUpdateSalary['total_salary']) && $dataUpdateSalary['status'] == "paid") {
        $difference = $dataUpdateSalary['total_salary'] - $oldDataCapital['amount'];       
        acumulate_amount_total_capital("expense", $difference, $this->conn);
        logCapitalAction("expense", $user_uuid, $difference, $oldDataCapital['id'], $oldDataCapital['description'], $this->conn);
    }
}



    public function insertToCapital($dataUpdateSalary, $user_uuid){
   

        if($dataUpdateSalary['status'] == "paid")
        {

$description = "Pembayaran gaji kepada " . $dataUpdateSalary['nama_karyawan'];
 $sql = "INSERT INTO {$this->table_capital} 
        (capital_uuid, type_transaction, purchase_uuid, amount, description, user_uuid, created_at, salary_uuid, deleted_at, deleted_by)
        VALUES
        (:capital_uuid, :type_transaction, :purchase_uuid, :amount, :description, :user_uuid, :created_at, :salary_uuid, :deleted_at, :deleted_by)";

        $stmt = $this->conn->prepare($sql);
        $success = $stmt->execute([
        'capital_uuid'     => generate_uuid(),
        'type_transaction' => 'expense', // karena gaji keluar dari kas
        'salary_uuid'    => $dataUpdateSalary['salary_uuid'], // kaitkan dengan salary
        'amount'           => $dataUpdateSalary['total_salary'],
        'description'      => $description,
        'purchase_uuid' => null,
        'user_uuid'        => $dataUpdateSalary['user_uuid'],
        'created_at'       => time(),
        'deleted_at'       => null,
        'deleted_by'       => null,
          ]);
            $lastId = $this->conn->lastInsertId();
            acumulate_amount_total_capital("expense",$dataUpdateSalary['total_salary'],$this->conn);
            logCapitalAction("insert",$user_uuid, $dataUpdateSalary['total_salary'],  $lastId, $description, $this->conn);
        }  

}


    public function insert($data)
    {

        $userIsNotAccountant =  isAccountant($data['user_uuid'],$this->conn);
        if(!empty($userIsNotAccountant)){
            return $userIsNotAccountant;
        }

        $sql = "INSERT INTO {$this->table} 
        (salary_uuid, user_uuid, payee_user_uuid, month, year, basic_salary, allowance, bonus, deduction, total_salary, payment_date, status, proof_of_payment, created_at, detail_allowance, detail_bonus, detail_deduction, note)
        VALUES
        (:salary_uuid, :user_uuid, :payee_user_uuid, :month, :year, :basic_salary, :allowance, :bonus, :deduction, :total_salary, :payment_date, :status, :proof_of_payment, :created_at,  :detail_allowance, :detail_bonus, :detail_deduction, :note)";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':salary_uuid'        => $data['salary_uuid'],
            ':user_uuid'          => $data['user_uuid'],
            ':payee_user_uuid'    => $data['payee_user_uuid'],
            ':month'              => $data['month'],
            ':year'               => $data['year'],
            ':basic_salary'       => $data['basic_salary'],
            ':allowance'          => $data['allowance'],
            ':bonus'              => $data['bonus'],
            ':deduction'          => $data['deduction'],
            ':total_salary'       => $data['total_salary'],
            ':payment_date'       => $data['payment_date'],
            ':status'             => $data['status'],
            ':proof_of_payment'   => $data['proof_of_payment'],
            ':created_at'         => $data['created_at'],
            ':detail_allowance' => json_encode($data['detail_allowance']),
            ':detail_bonus' => json_encode($data['detail_bonus']),
            ':detail_deduction' => json_encode($data['detail_deduction']),
            ':note'         => $data['note'],
        ]);


        if ($stmt->rowCount() > 0) {
            $lastId = $this->conn->lastInsertId();

            $sqlSelect = "SELECT s.*, u.user_username as salary_input_by,           CONCAT(up.profile_first_name, ' ', up.profile_last_name) AS nama_karyawan
                      FROM {$this->table} s
                      LEFT JOIN bincang_user u ON s.user_uuid = u.user_uuid
                      LEFT JOIN bincang_user p ON s.payee_user_uuid = p.user_uuid
                      JOIN bincang_user_profile up ON  s.payee_user_uuid = up.profile_user_uuid
                      WHERE s.id = :id";

            $stmtSelect = $this->conn->prepare($sqlSelect);
            $stmtSelect->bindValue(':id', $lastId, PDO::PARAM_INT);
            $stmtSelect->execute();
            $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if ($item) {

                //masukkan data ke penggajian ke capital jika sudah dibayarkan (status == paid)
                $dataForCapital = [
                    'salary_uuid' => $item['salary_uuid'],
                    'total_salary' => $item['total_salary'],
                    "user_uuid" => $item['user_uuid'],
                    "nama_karyawan" => getEmployeeName($item['payee_user_uuid'], $this->conn),
                    "status" => $item['status'],
                ];
                if($dataForCapital["status"] == "paid")
                {    
                $this->insertToCapital($dataForCapital,$data['user_uuid']);
                addNotificationSalary($item['payee_user_uuid'],$item['salary_uuid'],$this->conn);
                }
                
                return [
                    "status" => "success",
                    "code" => 200,
                    "data" => $item
                ];
            }
        }
    }



    public function recover($salary_uuid, $recover_by)
    {

        $userIsNotAccountant =  isAccountant($recover_by,$this->conn);
        if(!empty($userIsNotAccountant)){
            return $userIsNotAccountant;
        }

        // Ambil data sebelum update
        $sqlSelect = "SELECT s.*, u.user_username AS salary_input_by, p.user_username AS username_payee,
                        c.id AS id_bincang_capital,
                c.description AS description_bincang_capital
        FROM {$this->table} s
        LEFT JOIN bincang_user u ON s.user_uuid = u.user_uuid
        LEFT JOIN bincang_user p ON s.payee_user_uuid = p.user_uuid
        LEFT JOIN bincang_capital c ON c.salary_uuid = s.salary_uuid
        WHERE s.salary_uuid = :salary_uuid";

        $stmtSelect = $this->conn->prepare($sqlSelect);
        $stmtSelect->execute([':salary_uuid' => $salary_uuid]);
        $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return [
                "status" => "error",
                "code" => 404,
                "message" => "Data tidak ditemukanx."
            ];
        }


        $sql = "UPDATE {$this->table} 
            SET deleted_by = null, deleted_at = null 
            WHERE salary_uuid = :salary_uuid";

        $stmt = $this->conn->prepare($sql);
        $deletedAt = time();

        $stmt->execute([
            ':salary_uuid' => $salary_uuid
        ]);

        $affectedRows = $stmt->rowCount();

        if ($affectedRows > 0) {

            //jika status == paid, maka kembalikan record dari total saldo
            if($item['status'] == "paid"){
                $this->recoverCapitalBySalaryUUID($item['salary_uuid']);
                acumulate_amount_total_capital("expense", $item['total_salary'], $this->conn);
                logCapitalAction("recover", $recover_by, $item['total_salary'], $item['id_bincang_capital'], $item['description_bincang_capital'], $this->conn);    
            }  

            return [
                "status" => "success",
                "code"   => 200,
                "message" => "Data berhasil dikembalikan",
                "data" => $item
            ];
        }

        return [
            "status" => "error",
            "code"   => 500,
            "message" => "Gagal gagal mengembalikan data."
        ];
    }

    public function updateProofImage($id, $filePath)
{
    $sql = "UPDATE {$this->table} SET proof_of_payment = :filePath WHERE salary_uuid = :id";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindValue(':filePath', $filePath);
    $stmt->bindValue(':id', $id, PDO::PARAM_STR);
    return $stmt->execute();
}

public function findById($id)
{
   $sql = "SELECT 
            s.*,
            CONCAT(p.profile_first_name, ' ', p.profile_last_name) AS nama_karyawan,
            u.user_roles AS jabatan
        FROM bincang_salary s
        JOIN bincang_user u ON s.payee_user_uuid = u.user_uuid
        JOIN bincang_user_profile p ON u.user_uuid = p.profile_user_uuid
        WHERE salary_uuid = :id
        ORDER BY s.id ASC
        LIMIT 1";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


public function getReport($month = null, $year = null)
{
    $where = ["s.deleted_at IS NULL"];
    $params = [];

    if (!empty($month)) {
        $where[] = "s.month = :month";
        $params[':month'] = (int)$month;
    }

    if (!empty($year)) {
        $where[] = "s.year = :year";
        $params[':year'] = (int)$year;
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = " WHERE " . implode(" AND ", $where);
    }

    $sql = "SELECT 
            s.basic_salary,
            s.allowance,
            s.bonus,
            s.deduction,
            s.total_salary,
            CONCAT(p.profile_first_name, ' ', p.profile_last_name) AS nama_karyawan,
            u.user_roles AS jabatan
        FROM bincang_salary s
        JOIN bincang_user u ON s.payee_user_uuid = u.user_uuid
        JOIN bincang_user_profile p ON u.user_uuid = p.profile_user_uuid
        $whereSql
        ORDER BY s.id ASC";

    $stmt = $this->conn->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    $no = 1;
    $totalKeseluruhan = 0;

    foreach ($data as $item) {
        $result[] = [
            'no' => $no++,
            'nama_karyawan' => $item['nama_karyawan'],
            'jabatan' => $this->formatRoles($item['jabatan']),
            'gaji_pokok' => (float)$item['basic_salary'],
            'tunjangan' => (float)$item['allowance'],
            'bonus' => (float)$item['bonus'],
            'potongan' => (float)$item['deduction'],
            'total_gaji' => (float)$item['total_salary'],
        ];
        $totalKeseluruhan += $item['total_salary'];
    }

    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $periode = null;
    // if (!empty($month) && !empty($year)) {
    //     $bulanNama = isset($namaBulan[(int)$month]) ? $namaBulan[(int)$month] : '';
    //     $periode = $bulanNama . ' ' . $year;
    // }

        if (!empty($month)) {
        $bulanNama = isset($namaBulan[(int)$month]) ? $namaBulan[(int)$month] : '';
        $periode = $bulanNama;
    }

    if (!empty($year)) {
        if(strlen($periode) > 1)
       $periode .= " ".$year;
        else
        $periode .= $year;
    }

    return [
        'status' => 'success',
        'code' => 200,
        'periode' => $periode,
        'total_data' => count($result),
        'total_gaji_keseluruhan' => $totalKeseluruhan,
        'data' => $result
    ];
}




private function formatRoles($rolesJson)
{
    $roles = json_decode($rolesJson, true);
    if (!is_array($roles)) return '';

    $filteredRoles = array_filter($roles, function($role) {
        return $role !== 'user';
    });

    return implode(', ', $filteredRoles);
}

public function getSalarySlip($month = null, $year = null, $user_uuid = null)
{
    $where = ["s.deleted_at IS NULL"];
    $params = [];

    if (!empty($month)) {
        $where[] = "s.month = :month";
        $params[':month'] = (int)$month;
    }

    if (!empty($year)) {
        $where[] = "s.year = :year";
        $params[':year'] = (int)$year;
    }

    if (!empty($user_uuid)) {
        $where[] = "s.payee_user_uuid = :user_uuid";
        $params[':user_uuid'] = $user_uuid;
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = " WHERE " . implode(" AND ", $where);
    }

    $sql = "SELECT 
                s.basic_salary,
                s.allowance,
                s.bonus,
                s.deduction,
                s.total_salary,
                s.detail_allowance,
                s.detail_bonus,
                s.detail_deduction,
            CONCAT(p.profile_first_name, ' ', p.profile_last_name) AS nama_karyawan,
                u.user_roles AS jabatan
            FROM bincang_salary s
            JOIN bincang_user u ON s.payee_user_uuid = u.user_uuid
            JOIN bincang_user_profile p ON u.user_uuid = p.profile_user_uuid
            $whereSql
            ORDER BY s.id DESC
            LIMIT 1"; 

    $stmt = $this->conn->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        return [
            'status' => 'error',
            'code' => 404,
            'message' => 'Slip gaji tidak ditemukan.'
        ];
    }

    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $periode = '';
    if (!empty($month)) {
        $bulanNama = isset($namaBulan[(int)$month]) ? $namaBulan[(int)$month] : '';
        $periode = $bulanNama;
    }

    if (!empty($year)) {
        $periode .= $periode ? " $year" : $year;
    }

    return [
        'status' => 'success',
        'code' => 200,
        'periode' => $periode,
        'data' => [
            'nama_karyawan' => $item['nama_karyawan'],
            'jabatan' => $this->formatRoles($item['jabatan']),
            'gaji_pokok' => (float)$item['basic_salary'],
            'tunjangan' => (float)$item['allowance'],
            'bonus' => (float)$item['bonus'],
            'potongan' => (float)$item['deduction'],
            'total_gaji' => (float)$item['total_salary'],
            'rincian_tunjangan' => $item['detail_allowance'],
            'rincian_bonus' => $item['detail_bonus'],
            'rincian_potongan' => $item['detail_deduction'],
        ]
    ];
}






}
