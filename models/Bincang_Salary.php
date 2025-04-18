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
        $result = $this->conn->query($sql);

        $data = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        return $data;
    }
}
