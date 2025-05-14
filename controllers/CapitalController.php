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

        if ($_POST['type_transaction'] != "income" && $_POST['type_transaction'] != "expense") {
            errorResponse(400, "Transaksi Invalid");
            return;
        }

        $data = [
            'capital_uuid'     =>  generate_uuid(),
            'type_transaction' => $_POST['type_transaction'],
            'purchase_uuid'    => $_POST['purchase_uuid'] ?? null,
            'amount'           => $_POST['amount'] ?? 0,
            'description'      => $_POST['description'] ?? '',
            'user_uuid'        => $_POST['user_uuid'] ?? '',
            'created_at'       => time(),
        ];

        $result = $this->model->insert($data);

        if ($result) {
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            errorResponse(500, "Terjadi Kesalahan");
        }
    }

    public function patch($id)
    {

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $new_capital_uuid = (isset($data['capital_uuid'])) ? $data['capital_uuid'] : null;
        $new_type_transaction = (isset($data['type_transaction'])) ? $data['type_transaction'] : null;
        $new_purchase_uuid = (isset($data['purchase_uuid'])) ? $data['purchase_uuid'] : null;
        $new_amount = (isset($data['amount'])) ? $data['amount'] : null;
        $new_description = (isset($data['description'])) ? $data['description'] : null;
        $new_user_uuid = (isset($data['user_uuid'])) ? $data['user_uuid'] : null;



        $updateData = [
            'type_transaction' => $new_type_transaction,
            'purchase_uuid'    => $new_purchase_uuid,
            'amount'           => $new_amount,
            'description'      => $new_description,
            'user_uuid'        => $new_user_uuid,
        ];

        $result = $this->model->update($id, $updateData, $data['update_by']);
        if ($result) {
            header('Content-Type: application/json');
            echo json_encode($result);
        }
    }

    public function put($id)
    {
        echo "update by id " . $id;
    }

    public function deletePermanently($id)
    {
        if (!isset($id)) {
            echo json_encode([
                "status" => "error",
                "code" => 400,
                "message" => "capital_uuid tidak ditemukan"
            ]);
            return;
        }

        $capital_uuid = $id;

        $result = $this->model->deletePermanently($capital_uuid);

        header('Content-Type: application/json');
        echo json_encode($result);
    }


    /*soft del */
    public function delete($id)
    {
        if (!$id) {
            errorResponse(400, "id capital tidak boleh kosong");
            return;
        }

        if (!isset($_GET['deleted_by'])) {
            errorResponse(400, "id pengguna yang menghapus tidak boleh kosong");
            return;
        }

        $capital_uuid = $id;
        $deleted_by = $_GET['deleted_by'];

        $result = $this->model->delete($capital_uuid, $deleted_by);

        if ($result) {
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            errorResponse(500, "Terjadi kesalahan");
        }
    }

    public function recover($id)
    {
        if (!$id) {
            errorResponse(400, "id capital tidak boleh kosong");
            return;
        }



        $capital_uuid = $id;

        $result = $this->model->recover($capital_uuid, $_GET['recover_by']);

        if ($result) {
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            errorResponse(500, "Terjadi kesalahan");
        }
    }

    public function nonRestFul()
    {
        echo "voila ini non restful";
    }

    public function get_recent_last_capital()
    {
        return $this->model->get_recent_last_capital();
    }

    public function report()
{
    $startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
    $endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

    $result = $this->model->getCapitalReport($startDate, $endDate);

    header('Content-Type: application/json');
    echo json_encode($result);
}


    public function annualReport()
{
     $year = isset($_GET['year']) ? $_GET['year'] : null;

    $result = $this->model->getCapitalReportByYear($year);

    header('Content-Type: application/json');
    echo json_encode($result);
}

    

}
