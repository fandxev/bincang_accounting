<?php

class Bincang_Reason_Allowance
{
    private $conn;
    private $table = "bincang_reason_allowance";

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
            $where[] = "(c.reason LIKE :search 
                 OR c.reason_deduction_uuid LIKE :search 
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
        $sql = "SELECT c.*, u.user_username AS reason_by_username " . $baseSql . $whereSql . " 
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
            $item['updated_at'] = date('Y-m-d H:i:s', $item['updated_at']);
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
    

    public function getByUUID($uuid)
    {
        $stmt = $this->conn->prepare("SELECT * FROM bincang_reason_allowance WHERE reason_deduction_uuid = ? AND deleted_at IS NULL");
        $stmt->execute([$uuid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function create($data)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO bincang_reason_allowance (reason_allowance_uuid, salary_uuid, user_uuid, reason, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
         $stmt->execute([
            $data['reason_allowance_uuid'],
            $data['salary_uuid'],
            $data['user_uuid'],
            $data['reason'],
            $data['created_at'],
            $data['updated_at']
        ]);
        $affectedRows = $stmt->rowCount();
        if($affectedRows > 0){
            return [
                "status" => "success",
                "code" => 200,
                "data" => $this->getSingleRecordForResponse($data['reason_allowance_uuid'])
            ];
        }
    }

    public function update($reason_allowance_uuid, $data)
{
    // Cek ID
    if (!$reason_allowance_uuid) {
        return errorResponse("400", "reason_allowance_uuid is required");
    }

    // Ambil data lama
    $sqlOld = "SELECT * FROM bincang_reason_allowance WHERE reason_allowance_uuid = :id";
    $stmtOld = $this->conn->prepare($sqlOld);
    $stmtOld->bindValue(':id', $reason_allowance_uuid);
    $stmtOld->execute();
    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

    if (!$oldData) {
        return errorResponse("404", "Data tidak ditemukan");
    }

    // Gabungkan data lama dan baru
    $mergedData['reason']     = isset($data['reason']) ? $data['reason'] : $oldData['reason'];
    $mergedData['user_uuid']       = isset($data['user_uuid']) ? $data['user_uuid'] : $oldData['user_uuid'];
    $mergedData['reason_allowance_uuid']  = isset($data['reason_allowance_uuid']) ? $data['reason_allowance_uuid'] : $oldData['reason_allowance_uuid'];

    // Eksekusi update
    $sql = "UPDATE bincang_reason_allowance SET 
                reason = :reason,
                user_uuid = :user_uuid,
                reason_allowance_uuid = :reason_allowance_uuid,
                updated_at = :updated_at
            WHERE reason_allowance_uuid = :id 
            AND deleted_at IS NULL 
            AND deleted_by IS NULL";

    $stmt = $this->conn->prepare($sql);

    $stmt->execute([
        ':reason'    => $mergedData['reason'],
        ':user_uuid'      => $mergedData['user_uuid'],
        ':reason_allowance_uuid' => $mergedData['reason_allowance_uuid'],
        ':updated_at'     => time(),
        ':id'             => $reason_allowance_uuid,
    ]);

    $affectedRows = $stmt->rowCount();

    if ($affectedRows > 0) {
        // Ambil data yang telah diperbarui
        $sqlSelect = "SELECT r.*, u.user_username AS reason_by_username 
                      FROM bincang_reason_allowance r
                      JOIN bincang_user u ON r.user_uuid = u.user_uuid
                      WHERE r.reason_allowance_uuid = :id";

        $stmtSelect = $this->conn->prepare($sqlSelect);
        $stmtSelect->bindValue(':id', $reason_allowance_uuid);
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


    public function softDelete($uuid, $deleted_by, $deleted_at)
    {
        $stmt = $this->conn->prepare("
            UPDATE bincang_reason_allowance 
            SET deleted_at = ?, deleted_by = ? 
            WHERE reason_deduction_uuid = ?
        ");
        return $stmt->execute([$deleted_at, $deleted_by, $uuid]);
    }

    public function getSingleRecordForResponse($reason_allowance_uuid){
        $sql = "SELECT c.*, u.user_username AS reason_by_username  
        FROM {$this->table} c
        JOIN bincang_user u ON c.user_uuid = u.user_uuid
        WHERE reason_allowance_uuid = :reason_allowance_uuid";

$stmt = $this->conn->prepare($sql);
$stmt->bindValue(':reason_allowance_uuid', $reason_allowance_uuid, PDO::PARAM_STR);

$stmt->execute();

return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete($reason_allowance_uuid, $deleted_by)
{
    // Ambil data sebelum dihapus
    $sqlSelect = "SELECT r.*, u.user_username AS reason_by_username 
                  FROM bincang_reason_allowance r
                  JOIN bincang_user u ON r.user_uuid = u.user_uuid
                  WHERE r.reason_allowance_uuid = :reason_allowance_uuid";

    $stmtSelect = $this->conn->prepare($sqlSelect);
    $stmtSelect->execute([':reason_allowance_uuid' => $reason_allowance_uuid]);
    $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        return [
            "status" => "error",
            "code" => 404,
            "message" => "Data tidak ditemukan."
        ];
    }

    // Soft delete
    $sql = "UPDATE bincang_reason_allowance 
            SET deleted_by = :deleted_by, deleted_at = :deleted_at 
            WHERE reason_allowance_uuid = :reason_allowance_uuid";

    $stmt = $this->conn->prepare($sql);
    $deletedAt = time();

    $stmt->execute([
        ':deleted_by'   => $deleted_by,
        ':deleted_at'   => $deletedAt,
        ':reason_allowance_uuid'  => $reason_allowance_uuid
    ]);

    $affectedRows = $stmt->rowCount();

    if ($affectedRows > 0) {
        $item['deleted_by'] = $deleted_by;
        $item['deleted_at'] = $deletedAt;

        return [
            "status"  => "success",
            "code"    => 200,
            "message" => "Data berhasil dihapus",
            "data"    => $item
        ];
    }

    return [
        "status" => "error",
        "code"   => 500,
        "message" => "Gagal menghapus data."
    ];
}

public function recover($reason_allowance_uuid)
{
    // Ambil data sebelum di-recover
    $sqlSelect = "SELECT r.*, u.user_username AS reason_by_username
                  FROM bincang_reason_allowance r
                  JOIN bincang_user u ON r.user_uuid = u.user_uuid
                  WHERE r.reason_allowance_uuid = :reason_allowance_uuid";

    $stmtSelect = $this->conn->prepare($sqlSelect);
    $stmtSelect->execute([':reason_allowance_uuid' => $reason_allowance_uuid]);
    $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        return [
            "status" => "error",
            "code" => 404,
            "message" => "Data tidak ditemukan."
        ];
    }

    // Recover (null-kan deleted_by dan deleted_at)
    $sql = "UPDATE bincang_reason_allowance 
            SET deleted_by = NULL, deleted_at = NULL 
            WHERE reason_allowance_uuid = :reason_allowance_uuid";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute([':reason_allowance_uuid' => $reason_allowance_uuid]);

    $affectedRows = $stmt->rowCount();

    if ($affectedRows > 0) {
        return [
            "status"  => "success",
            "code"    => 200,
            "message" => "Data berhasil dikembalikan",
            "data"    => $item
        ];
    }

    return [
        "status" => "error",
        "code"   => 500,
        "message" => "Gagal mengembalikan data."
    ];
}


}
