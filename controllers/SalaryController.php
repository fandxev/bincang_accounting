<?php
require_once './models/Bincang_Salary.php';

class SalaryController
{
    private $model;

    public function __construct($db)
    {
        $this->model = new Bincang_Salary($db);
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
        echo "post";
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
