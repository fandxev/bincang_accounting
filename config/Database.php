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
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->dbname);

        if ($this->conn->connect_error) {
            die("Koneksi gagal: " . $this->conn->connect_error);
        }

        return $this->conn;
    }
}
