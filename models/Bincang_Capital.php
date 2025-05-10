<?php
class Bincang_Capital
{
    private $conn;
    private $table = 'bincang_capital';
    private $tableTotalCapital = 'bincang_capital_total';

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAll($page = 1, $perPage = 10, $search = null, $startDate = null, $endDate = null)
    {
        $start = ($page - 1) * $perPage;

        $baseSql = "FROM {$this->table} c
                JOIN bincang_user u ON c.user_uuid = u.user_uuid";

        $where = ["c.deleted_at IS NULL"];
        $params = [];

        // Filter pencarian
        if (!empty($search)) {
            $where[] = "(c.type_transaction LIKE :search 
                 OR c.amount LIKE :search 
                 OR c.description LIKE :search 
                 OR c.last_capital LIKE :search 
                 OR u.user_username LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        // Filter tanggal
        if (!empty($startDate) && empty($endDate)) {
            $startTime = strtotime($startDate . " 00:00:00");
            $endTime = strtotime($startDate . " 23:59:59");
            $where[] = "c.created_at BETWEEN :startTime AND :endTime";
            $params[':startTime'] = $startTime;
            $params[':endTime'] = $endTime;
        } elseif (!empty($startDate) && !empty($endDate)) {
            $startTime = strtotime($startDate . " 00:00:00");
            $endTime = strtotime($endDate . " 23:59:59");
            $where[] = "c.created_at BETWEEN :startTime AND :endTime";
            $params[':startTime'] = $startTime;
            $params[':endTime'] = $endTime;
        }

        // Gabungkan WHERE jika ada
        $whereSql = '';
        if (!empty($where)) {
            $whereSql = " WHERE " . implode(" AND ", $where);
        }

        // Ambil total data
        $sqlCount = "SELECT COUNT(*) as total " . $baseSql . $whereSql;
        $stmtCount = $this->conn->prepare($sqlCount);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $totalData = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPage = ceil($totalData / $perPage);

        // Ambil data sesuai pagination
        $sql = "SELECT c.*, u.user_username " . $baseSql . $whereSql . " 
            ORDER BY c.id DESC 
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



    public function insert($data)
    {
       $userIsNotAccountant =  isAccountant($data['user_uuid'],$this->conn);
        if(!empty($userIsNotAccountant)){
            return $userIsNotAccountant;
        }


        $sql = "INSERT INTO {$this->table} 
        (capital_uuid, type_transaction, purchase_uuid, amount, description, last_capital, user_uuid, created_at)
        VALUES
        (:capital_uuid, :type_transaction, :purchase_uuid, :amount, :description, :last_capital, :user_uuid, :created_at)";

        $stmt = $this->conn->prepare($sql);
        $success = $stmt->execute([
            ':capital_uuid'     => $data['capital_uuid'],
            ':type_transaction' => $data['type_transaction'],
            ':purchase_uuid'    => $data['purchase_uuid'],
            ':amount'           => $data['amount'],
            ':description'      => $data['description'],
            ':last_capital'     => $data['last_capital'],
            ':user_uuid'        => $data['user_uuid'],
            ':created_at'       => $data['created_at'],
        ]);

        if ($success) {
            $lastId = $this->conn->lastInsertId();

            // Ambil data lengkap setelah insert
            $sqlSelect = "SELECT c.*, u.user_username 
                      FROM {$this->table} c
                      JOIN bincang_user u ON c.user_uuid = u.user_uuid
                      WHERE c.id = :id";

            $stmtSelect = $this->conn->prepare($sqlSelect);
            $stmtSelect->bindValue(':id', $lastId, PDO::PARAM_INT);
            $stmtSelect->execute();
            $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if ($item) {

                $this->insertOrUpdateTotalCapital($data['type_transaction'], $data['amount']);
                date_default_timezone_set('Asia/Jakarta');
                $item['created_at'] = date('Y-m-d H:i:s', $item['created_at']);

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
            "message" => "Gagal menyimpan data."
        ];
    }

    public function update($capital_uuid, $data,$update_by)
    {

       $userIsNotAccountant =  isAccountant($update_by,$this->conn);
        if(!empty($userIsNotAccountant)){
            return $userIsNotAccountant;
        }

        // Ambil data lama dari DB
        $sqlOld = "SELECT * FROM {$this->table} WHERE capital_uuid = :id";
        $stmtOld = $this->conn->prepare($sqlOld);
        $stmtOld->bindValue(':id', $capital_uuid);
        $stmtOld->execute();
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$capital_uuid) {
            return errorResponse("400", "id is required");
        }

        if (!$oldData) {
            return errorResponse("404", "Data tidak ditemukan");
        }

        // Gabungkan data lama dengan data baru (data baru akan menimpa yang lama)

        $mergedData['type_transaction'] = (isset($data['type_transaction'])) ? $data['type_transaction'] : $oldData['type_transaction'];
        $mergedData['purchase_uuid'] = (isset($data['purchase_uuid'])) ? $data['purchase_uuid'] : $oldData['purchase_uuid'];
        $mergedData['amount'] = (isset($data['amount'])) ? $data['amount'] : $oldData['amount'];
        $mergedData['description'] = (isset($data['description'])) ? $data['description'] : $oldData['description'];
        $mergedData['last_capital'] = (isset($data['last_capital'])) ? $data['last_capital'] : $oldData['last_capital'];
        $mergedData['user_uuid'] = (isset($data['user_uuid'])) ? $data['user_uuid'] : $oldData['user_uuid'];



        // Jalankan update
        $sql = "UPDATE {$this->table} SET 
        type_transaction = :type_transaction,
        purchase_uuid = :purchase_uuid,
        amount = :amount,
        description = :description,
        last_capital = :last_capital,
        user_uuid = :user_uuid,
        updated_at = :updated_at
        WHERE 
    capital_uuid = :id 
    AND deleted_at IS NULL 
    AND deleted_by IS NULL";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':type_transaction' => $mergedData['type_transaction'],
            ':purchase_uuid'    => $mergedData['purchase_uuid'],
            ':amount'           => $mergedData['amount'],
            ':description'      => $mergedData['description'],
            ':last_capital'     => $mergedData['last_capital'],
            ':user_uuid'        => $mergedData['user_uuid'],
            ':updated_at'       => time(),
            ':id'               => $capital_uuid,
        ]);

        $affectedRows = $stmt->rowCount();



        if ($affectedRows > 0) {

            //singkronisasi data capital setelah ada update
            $this->syncLastCapitalAfterUpdate($capital_uuid, $oldData['type_transaction'], $oldData['amount'], $data['type_transaction'], $data['amount']);


            // Ambil kembali data yang telah diperbarui
            $sqlSelect = "SELECT c.*, u.user_username 
                      FROM {$this->table} c
                      JOIN bincang_user u ON c.user_uuid = u.user_uuid
                      WHERE c.capital_uuid = :id";

            $stmtSelect = $this->conn->prepare($sqlSelect);
            $stmtSelect->bindValue(':id', $capital_uuid);
            $stmtSelect->execute();
            $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                date_default_timezone_set('Asia/Jakarta');
                $item['created_at'] = date('Y-m-d H:i:s', $item['created_at']);

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

    public function get_recent_last_capital()
    {
        try {
            $sql = "SELECT total_capital FROM " . $this->tableTotalCapital . " ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->conn->prepare($sql);
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


    public function deletePermanentlyInactive($capital_uuid)
    {
        // Ambil data sebelum dihapus
        $sqlSelect = "SELECT c.*, u.user_username 
                  FROM {$this->table} c
                  JOIN bincang_user u ON c.user_uuid = u.user_uuid
                  WHERE c.capital_uuid = :capital_uuid";

        //sesuaikan capital sebelum dihapus
        $this->syncLastCapitalAfterDelete($capital_uuid);

        $stmtSelect = $this->conn->prepare($sqlSelect);
        $stmtSelect->bindValue(':capital_uuid', $capital_uuid);
        $stmtSelect->execute();
        $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return [
                "status" => "error",
                "code" => 404,
                "message" => "Data tidak ditemukan"
            ];
        }

        // Hapus data
        $sqlDelete = "DELETE FROM {$this->table} WHERE capital_uuid = :capital_uuid";
        $stmtDelete = $this->conn->prepare($sqlDelete);
        $success = $stmtDelete->execute([':capital_uuid' => $capital_uuid]);

        if ($success) {
            date_default_timezone_set('Asia/Jakarta');
            $item['created_at'] = date('Y-m-d H:i:s', $item['created_at']);

            return [
                "status" => "success",
                "code" => 200,
                "data" => $item
            ];
        }

        return [
            "status" => "error",
            "code" => 500,
            "message" => "Gagal menghapus data."
        ];
    }

    public function delete($capital_uuid, $deleted_by)
    {

               $userIsNotAccountant =  isAccountant($deleted_by,$this->conn);
        if(!empty($userIsNotAccountant)){
            return $userIsNotAccountant;
        }
        // Ambil data sebelum update
        $sqlSelect = "SELECT c.*, u.user_username 
                  FROM {$this->table} c
                  JOIN bincang_user u ON c.user_uuid = u.user_uuid
                  WHERE c.capital_uuid = :capital_uuid";

        $stmtSelect = $this->conn->prepare($sqlSelect);
        $stmtSelect->execute([':capital_uuid' => $capital_uuid]);
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
            WHERE capital_uuid = :capital_uuid";

        $stmt = $this->conn->prepare($sql);
        $deletedAt = time();

        $stmt->execute([
            ':deleted_by'   => $deleted_by,
            ':deleted_at'   => $deletedAt,
            ':capital_uuid' => $capital_uuid
        ]);

        $affectedRows = $stmt->rowCount();

        if ($affectedRows > 0) {

            $this->syncLastCapitalAfterDelete($capital_uuid);
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

    public function insertOrUpdateTotalCapital($type_transaction, $amount)
    {
        // Ambil baris pertama (hanya ada satu row)
        $sqlCheck = "SELECT total_capital FROM {$this->tableTotalCapital} LIMIT 1";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute();
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $timestamp = time(); // timestamp UNIX

        if ($type_transaction === "income") {
            if ($existing) {
                // Sudah ada, maka tambahkan
                $newTotal = $existing['total_capital'] + $amount;

                $sqlUpdate = "UPDATE {$this->tableTotalCapital} 
                          SET total_capital = :total_capital, updated_at = :updated_at";
                $stmtUpdate = $this->conn->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':total_capital' => $newTotal,
                    ':updated_at'    => $timestamp
                ]);
            } else {
                // Belum ada, maka insert
                $sqlInsert = "INSERT INTO {$this->tableTotalCapital} 
                          (total_capital, created_at, updated_at) 
                          VALUES (:total_capital, :created_at, :updated_at)";
                $stmtInsert = $this->conn->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':total_capital' => $amount,
                    ':created_at'    => $timestamp,
                    ':updated_at'    => $timestamp
                ]);
            }
        } else if ($type_transaction === "expense") {
            if ($existing) {
                // Sudah ada, maka kurangi
                $newTotal = $existing['total_capital'] - $amount;

                $sqlUpdate = "UPDATE {$this->tableTotalCapital} 
                          SET total_capital = :total_capital, updated_at = :updated_at";
                $stmtUpdate = $this->conn->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':total_capital' => $newTotal,
                    ':updated_at'    => $timestamp
                ]);
            } else {
                // Belum ada, maka insert dengan nilai negatif
                $sqlInsert = "INSERT INTO {$this->tableTotalCapital} 
                          (total_capital, created_at, updated_at) 
                          VALUES (:total_capital, :created_at, :updated_at)";
                $stmtInsert = $this->conn->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':total_capital' => -$amount,
                    ':created_at'    => $timestamp,
                    ':updated_at'    => $timestamp
                ]);
            }
        }
    }


    public function recover($capital_uuid, $recover_by)
    {

        $userIsNotAccountant =  isAccountant($recover_by,$this->conn);
        if(!empty($userIsNotAccountant)){
            return $userIsNotAccountant;
        }


        // Ambil data sebelum update
        $sqlSelect = "SELECT c.*, u.user_username 
                  FROM {$this->table} c
                  JOIN bincang_user u ON c.user_uuid = u.user_uuid
                  WHERE c.capital_uuid = :capital_uuid";

        $stmtSelect = $this->conn->prepare($sqlSelect);
        $stmtSelect->execute([':capital_uuid' => $capital_uuid]);
        $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return [
                "status" => "error",
                "code" => 404,
                "message" => "Data tidak ditemukan."
            ];
        }


        $sql = "UPDATE {$this->table} 
            SET deleted_by = null, deleted_at = null 
            WHERE capital_uuid = :capital_uuid";

        $stmt = $this->conn->prepare($sql);
        $deletedAt = time();

        $stmt->execute([
            ':capital_uuid' => $capital_uuid
        ]);

        $affectedRows = $stmt->rowCount();

        if ($affectedRows > 0) {

            $this->syncLastCapitalAfterRecover($capital_uuid);

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
            "message" => "Gagal menghapus data."
        ];
    }


    public function syncLastCapitalAfterUpdate($uuid_capital, $previous_type_transaction, $previous_amount, $new_type_transaction, $new_amount)
    {

        $difference_amount = 0;
        if ($previous_type_transaction == "expense") {
            $previous_amount = -abs($previous_amount);
        }

        if ($new_type_transaction == "expense") {
            $new_amount = -abs($new_amount);
        }



        $difference_amount = $new_amount - $previous_amount;




        // Ambil ID dari baris yang diubah
        $sqlFind = "SELECT id FROM {$this->table} 
                WHERE capital_uuid = :capital_uuid 
                ORDER BY id DESC LIMIT 1";
        $stmtFind = $this->conn->prepare($sqlFind);
        $stmtFind->execute([':capital_uuid' => $uuid_capital]);
        $row = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($row && isset($row['id'])) {
            $currentId = $row['id'];

            // Update semua baris setelahnya
            $sqlUpdate = "UPDATE {$this->table}
                      SET last_capital = last_capital + :diff
                      WHERE capital_uuid = :capital_uuid OR id > :current_id";

            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':diff' => $difference_amount,
                ':capital_uuid' => $uuid_capital,
                ':current_id' => $currentId
            ]);
        }


        // Update total capital di tabel bincang_capital_total

        $sqlUpdateTotal = "UPDATE {$this->tableTotalCapital}
                       SET total_capital = total_capital + :diff,
                           updated_at = :updated_at
                        ";

        $stmtTotal = $this->conn->prepare($sqlUpdateTotal);
        $stmtTotal->execute([
            ':diff' => $difference_amount,
            ':updated_at' => time()
        ]);
    }

    public function syncLastCapitalAfterDelete($uuid_capital)
    {
        $delete_amount = 0;

        $sqlFind = "SELECT id, type_transaction, amount, last_capital FROM {$this->table} 
                WHERE capital_uuid = :capital_uuid 
                ORDER BY id DESC LIMIT 1";
        $stmtFind = $this->conn->prepare($sqlFind);
        $stmtFind->execute([':capital_uuid' => $uuid_capital]);
        $row = $stmtFind->fetch(PDO::FETCH_ASSOC);


        if ($row && isset($row['id'])) {
            $currentId = $row['id'];
            $type_transaction = $row['type_transaction'];
            if ($type_transaction == "expense") {
                $delete_amount = -abs($row['amount']);
            } else {
                $delete_amount = +abs($row['amount']);
            }

            // Update semua baris setelahnya
            $sqlUpdate = "UPDATE {$this->table}
                      SET last_capital = last_capital - :deletion
                      WHERE  id > :current_id";

            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':deletion' => $delete_amount,
                ':current_id' => $currentId
            ]);
        }


        // Update total capital di tabel bincang_capital_total

        $sqlUpdateTotal = "UPDATE {$this->tableTotalCapital}
                       SET total_capital = total_capital - :deletion,
                           updated_at = :updated_at
                        ";

        $stmtTotal = $this->conn->prepare($sqlUpdateTotal);
        $stmtTotal->execute([
            ':deletion' => $delete_amount,
            ':updated_at' => time()
        ]);
    }

    public function syncLastCapitalAfterRecover($uuid_capital)
    {
        $recover_amount = 0;

        $sqlFind = "SELECT id, type_transaction, amount, last_capital FROM {$this->table} 
                WHERE capital_uuid = :capital_uuid 
                ORDER BY id DESC LIMIT 1";
        $stmtFind = $this->conn->prepare($sqlFind);
        $stmtFind->execute([':capital_uuid' => $uuid_capital]);
        $row = $stmtFind->fetch(PDO::FETCH_ASSOC);


        if ($row && isset($row['id'])) {
            $currentId = $row['id'];
            $type_transaction = $row['type_transaction'];
            if ($type_transaction == "expense") {
                $recover_amount = -abs($row['amount']);
            } else {
                $recover_amount = abs($row['amount']);
            }

            // Update semua baris setelahnya
            $sqlUpdate = "UPDATE {$this->table}
                      SET last_capital = last_capital + :recover
                      WHERE  id > :current_id";

            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':recover' => $recover_amount,
                ':current_id' => $currentId
            ]);
        }


        // Update total capital di tabel bincang_capital_total

        $sqlUpdateTotal = "UPDATE {$this->tableTotalCapital}
                       SET total_capital = total_capital + :recover,
                           updated_at = :updated_at
                        ";

        $stmtTotal = $this->conn->prepare($sqlUpdateTotal);
        $stmtTotal->execute([
            ':recover' => $recover_amount,
            ':updated_at' => time()
        ]);
    }

    public function getCapitalReport($startDate = null, $endDate = null)
{
    $where = ["c.deleted_at IS NULL"];
    $params = [];

    if (!empty($startDate) && empty($endDate)) {
        $startTime = strtotime($startDate . " 00:00:00");
        $endTime = strtotime($startDate . " 23:59:59");
        $where[] = "c.created_at BETWEEN :startTime AND :endTime";
        $params[':startTime'] = $startTime;
        $params[':endTime'] = $endTime;
    } elseif (!empty($startDate) && !empty($endDate)) {
        $startTime = strtotime($startDate . " 00:00:00");
        $endTime = strtotime($endDate . " 23:59:59");
        $where[] = "c.created_at BETWEEN :startTime AND :endTime";
        $params[':startTime'] = $startTime;
        $params[':endTime'] = $endTime;
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = " WHERE " . implode(" AND ", $where);
    }

    $sql = "SELECT 
                c.capital_uuid,
                c.type_transaction,
                c.amount,
                c.description,
                c.created_at,
                u.user_username AS input_by
            FROM {$this->table} c
            JOIN bincang_user u ON c.user_uuid = u.user_uuid
            $whereSql
            ORDER BY c.created_at ASC";

    $stmt = $this->conn->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    $totalIncome = 0;
    $totalExpense = 0;

    foreach ($data as $item) {
        if ($item['type_transaction'] === 'income') {
            $totalIncome += $item['amount'];
        } elseif ($item['type_transaction'] === 'expense') {
            $totalExpense += $item['amount'];
        }

        date_default_timezone_set('Asia/Jakarta');
        $formattedDate = date('d F Y H:i:s', $item['created_at']);
        setlocale(LC_TIME, 'id_ID.UTF-8'); // opsional jika locale didukung server
        $tanggal = strftime('%d %B %Y %H:%M:%S', $item['created_at']);

        $result[] = [
            'capital_uuid' => $item['capital_uuid'],
            'type_transaction' => $item['type_transaction'],
            'amount' => (float)$item['amount'],
            'description' => $item['description'],
            'input_by' => $item['input_by'],
            'tanggal' => $tanggal
        ];
    }

    $lastCapital = $totalIncome - $totalExpense;

    return [
        'status' => 'success',
        'code' => 200,
        'total_income' => $totalIncome,
        'total_expense' => $totalExpense,
        'total_capital_terakhir' => $lastCapital,
        'data' => $result
    ];
}


public function getCapitalReportByYear($year = null)
{

    echo "oyiiisaaamm";
    $where = ["c.deleted_at IS NULL"];
    $params = [];

    if (!empty($year)) {
        $startTime = strtotime("{$year}-01-01 00:00:00");
        $endTime = strtotime("{$year}-12-31 23:59:59");
        $where[] = "c.created_at BETWEEN :startTime AND :endTime";
        $params[':startTime'] = $startTime;
        $params[':endTime'] = $endTime;
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = " WHERE " . implode(" AND ", $where);
    }

    $sql = "SELECT 
                c.capital_uuid,
                c.type_transaction,
                c.amount,
                c.description,
                c.created_at,
                u.user_username AS input_by
            FROM {$this->table} c
            JOIN bincang_user u ON c.user_uuid = u.user_uuid
            $whereSql
            ORDER BY c.created_at ASC";

    $stmt = $this->conn->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    $totalIncome = 0;
    $totalExpense = 0;

    foreach ($data as $item) {
        if ($item['type_transaction'] === 'income') {
            $totalIncome += $item['amount'];
        } elseif ($item['type_transaction'] === 'expense') {
            $totalExpense += $item['amount'];
        }

        date_default_timezone_set('Asia/Jakarta');
        $tanggal = strftime('%d %B %Y %H:%M:%S', $item['created_at']);

        $result[] = [
            'capital_uuid' => $item['capital_uuid'],
            'type_transaction' => $item['type_transaction'],
            'amount' => (float)$item['amount'],
            'description' => $item['description'],
            'input_by' => $item['input_by'],
            'tanggal' => $tanggal
        ];
    }

    $lastCapital = $totalIncome - $totalExpense;

    return [
        'status' => 'success',
        'code' => 200,
        'total_income' => $totalIncome,
        'total_expense' => $totalExpense,
        'total_capital_terakhir' => $lastCapital,
        'data' => $result
    ];
}


}
