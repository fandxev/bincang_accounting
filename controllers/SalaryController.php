<?php
require_once './models/Bincang_Salary.php';
require_once 'Controller.php';


class SalaryController extends Controller
{
    private $model;

    public function __construct($db)
    {
        $this->model = new Bincang_Salary($db);
    }

    public function index() {}

    public function get()
    {
        $data = $this->model->getAll();

        echo "<h2>Data Salary</h2>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }

    public function show($id)
    {
        echo "get by id " . $id;
    }

    public function post() {}

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
        $this->generate_uuid();
    }
}
