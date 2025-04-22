<?php
require_once './models/Bincang_Capital.php';

class CapitalController
{
    private $model;

    public function __construct($db)
    {
        $this->model = new Bincang_Capital($db);
    }

    public function index()
    {
        $data = $this->model->getAll();

        echo "<h2>Data Salary</h2>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }

    public function get()
    {
        echo "getAll";
    }

    public function show($id)
    {
        echo "get by id " . $id;
    }

    public function post()
    {
        $data = [
            'capital_uuid'     => uniqid('cap_'),
            'type_transaction' => $_POST['type_transaction'],
            'purchase_uuid'    => $_POST['purchase_uuid'] ?? null,
            'amount'           => $_POST['amount'] ?? 0,
            'description'      => $_POST['description'] ?? '',
            'last_capital'     => $_POST['last_capital'] ?? 0,
            'user_uuid'        => $_POST['user_uuid'] ?? '',
            'created_at'       => time(),
        ];

        $result = $this->model->insert($data);

        if ($result) {
            echo "Data berhasil disimpan.";
        } else {
            echo "Gagal menyimpan data.";
        }
    }

    public function put($id)
    {
        echo "update by id " . $id;
    }

    public function delete($id)
    {
        echo "delete by id " . $id;
    }

    public function nonRestFul()
    {
        echo "voila ini non restful";
    }
}
