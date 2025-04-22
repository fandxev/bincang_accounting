<?php
class Database
{
    private $host = '202.10.40.238';
    private $username = 'serv_bincang';
    private $password = 'II1#OEjjApvOxwm6';
    private $dbname = 'serv_bincang';
    private $conn;

    public function connect()
    {
        try {
            $dsn = "mysql:host=$this->host;dbname=$this->dbname;charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->conn;
        } catch (PDOException $e) {
            die("Koneksi gagal: " . $e->getMessage());
        }
    }
}
