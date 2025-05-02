<?php
class Bincang_Salary
{
    private $conn;
    private $table = 'bincang_salary';
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
    
        // Filter pencarian
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
    
        // Filter tanggal
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
                    s.bonus,
                    s.deduction,
                    s.total_salary,
                    s.payment_date,
                    s.status,
                    s.proof_of_payment,
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
            $item['payment_date'] = date('Y-m-d', strtotime($item['payment_date']));

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
    

    public function insert($data)
    {
        $sql = "INSERT INTO {$this->table} 
        (salary_uuid, user_uuid, payee_user_uuid, month, year, basic_salary, allowance, bonus, deduction, total_salary, payment_date, status, proof_of_payment, created_at)
        VALUES
        (:salary_uuid, :user_uuid, :payee_user_uuid, :month, :year, :basic_salary, :allowance, :bonus, :deduction, :total_salary, :payment_date, :status, :proof_of_payment, :created_at)";

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
        ]);

        if ($stmt->rowCount() > 0) {
            $lastId = $this->conn->lastInsertId();

            $sqlSelect = "SELECT s.*, u.user_username as payer_username, p.user_username as payee_username
                      FROM {$this->table} s
                      LEFT JOIN bincang_user u ON s.user_uuid = u.user_uuid
                      LEFT JOIN bincang_user p ON s.payee_user_uuid = p.user_uuid
                      WHERE s.id = :id";

            $stmtSelect = $this->conn->prepare($sqlSelect);
            $stmtSelect->bindValue(':id', $lastId, PDO::PARAM_INT);
            $stmtSelect->execute();
            $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if ($item) {

                return [
                    "status" => "success",
                    "code" => 200,
                    "data" => $item
                ];
            }
        }
    }
}
