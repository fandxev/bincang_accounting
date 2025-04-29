<?php
class Bincang_Salary
{
    private $conn;
    private $table = 'bincang_salary';

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAll()
    {
        $sql = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
