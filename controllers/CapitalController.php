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
        $data = $this->model->getAll(1, 2);

        echo "<h2>Data Salary</h2>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }

    public function get()
    {

        $page = (isset($_GET['page'])) ? $_GET['page'] : 1;
        $perPage = (isset($_GET['perPage'])) ? $_GET['perPage'] : 10;
        $search = (isset($_GET['search'])) ? $_GET['search'] : null;
        $startDate = (isset($_GET['startDate'])) ? $_GET['startDate'] : null;
        $endDate = (isset($_GET['endDate'])) ? $_GET['endDate'] : null;


        $result = $this->model->getAll($page, $perPage, $search, $startDate, $endDate);
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    public function show($id)
    {
        $perPage = 5;
    }

    public function post()
    {
        $new_capital = 0;
        if ($_POST['type_transaction'] == "income") {
            $new_capital = $this->get_recent_last_capital() + $_POST['amount'];
        } else if ($_POST['type_transaction'] == "expense") {
            $new_capital = $this->get_recent_last_capital() - $_POST['amount'];
        } else {
            echo "invalid transaction";
            return 0;
        }

        $data = [
            'capital_uuid'     =>  generate_uuid(),
            'type_transaction' => $_POST['type_transaction'],
            'purchase_uuid'    => $_POST['purchase_uuid'] ?? null,
            'amount'           => $_POST['amount'] ?? 0,
            'description'      => $_POST['description'] ?? '',
            'last_capital'     => $new_capital,
            'user_uuid'        => $_POST['user_uuid'] ?? '',
            'created_at'       => time(),
        ];

        $result = $this->model->insert($data);

        if ($result) {
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            echo "Gagal menyimpan data.";
        }
    }

    public function patch($id)
    {
        echo "amoount: " . $_PATCH['amount'];
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

    public function get_recent_last_capital()
    {
        return $this->model->get_recent_last_capital();
    }
}
