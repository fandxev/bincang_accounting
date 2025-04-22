<?php
class Bincang_Capital
{
    private $conn;
    private $table = 'bincang_capital';

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAll()
    {
        $sql = "SELECT * FROM " . $this->table;
        $result = $this->conn->query($sql);

        $data = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        return $data;
    }

    public function insert($data)
    {
        $sql = "INSERT INTO {$this->table} 
            (capital_uuid, type_transaction, purchase_uuid, amount, description, last_capital, user_uuid, created_at)
            VALUES
            (:capital_uuid, :type_transaction, :purchase_uuid, :amount, :description, :last_capital, :user_uuid, :created_at)";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':capital_uuid'     => $data['capital_uuid'],
            ':type_transaction' => $data['type_transaction'],
            ':purchase_uuid'    => $data['purchase_uuid'],
            ':amount'           => $data['amount'],
            ':description'      => $data['description'],
            ':last_capital'     => $data['last_capital'],
            ':user_uuid'        => $data['user_uuid'],
            ':created_at'       => $data['created_at'],
        ]);
    }
}
