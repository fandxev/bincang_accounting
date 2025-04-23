<?php
class Bincang_Capital
{
    private $conn;
    private $table = 'bincang_capital';

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAll($page = 1, $perPage = 10,  $search = null, $startDate = null, $endDate = null)
    {
        $start = ($page - 1) * $perPage;

        $sql = "SELECT c.*, u.user_username 
            FROM " . $this->table . " c
            JOIN bincang_user u ON c.user_uuid = u.user_uuid";

        $where = [];
        $params = [];

        // Filter pencarian (LIKE)
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

        // Gabungkan kondisi WHERE jika ada
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY c.id DESC 
              LIMIT :start, :perPage";

        $stmt = $this->conn->prepare($sql);

        // Bind semua parameter
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
            "data" => $result
        ];
    }

    // public function insert($data)
    // {
    //     $sql = "INSERT INTO {$this->table} 
    //         (capital_uuid, type_transaction, purchase_uuid, amount, description, last_capital, user_uuid, created_at)
    //         VALUES
    //         (:capital_uuid, :type_transaction, :purchase_uuid, :amount, :description, :last_capital, :user_uuid, :created_at)";

    //     $stmt = $this->conn->prepare($sql);

    //     return $stmt->execute([
    //         ':capital_uuid'     => $data['capital_uuid'],
    //         ':type_transaction' => $data['type_transaction'],
    //         ':purchase_uuid'    => $data['purchase_uuid'],
    //         ':amount'           => $data['amount'],
    //         ':description'      => $data['description'],
    //         ':last_capital'     => $data['last_capital'],
    //         ':user_uuid'        => $data['user_uuid'],
    //         ':created_at'       => $data['created_at'],
    //     ]);
    // }

    public function insert($data)
    {
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



    public function get_recent_last_capital()
    {
        try {
            $sql = "SELECT last_capital FROM " . $this->table . " ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $lastCapital = $result['last_capital'];
                return $lastCapital;
            } else {
                return 0;
            }
        } catch (PDOException $e) {
            echo "Error get last capital: " . $e->getMessage();
        }
    }
}
