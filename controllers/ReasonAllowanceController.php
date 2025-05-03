<?php
require_once './models/Bincang_Reason_Allowance.php';


class ReasonAllowanceController
{
    private $model;

    public function __construct($db)
    {
        $this->model = new Bincang_Reason_Allowance($db);
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

    public function show($uuid)
    {
        $data = $this->model->getByUUID($uuid);
        if ($data) {
            echo json_encode(['status' => true, 'data' => $data]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Data tidak ditemukan']);
        }
    }

    public function post($input)
    {
        $uuid = generate_uuid(); 
        $timestamp = time();

        $data = [
            'reason_allowance_uuid' => $uuid,
            'salary_uuid' => $_POST['salary_uuid'],
            'user_uuid' => $_POST['user_uuid'],
            'reason' => $_POST['reason'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp
        ];

        $result = $this->model->create($data);

        echo json_encode($result);
    }

    public function patch($id)
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $new_reason   = isset($data['reason']) ? $data['reason'] : null;
    $new_user_uuid  = isset($data['user_uuid']) ? $data['user_uuid'] : null;
    $new_reason_allowance_uuid = isset($data['reason_allowance_uuid']) ? $data['reason_allowance_uuid'] : null;

    $updateData = [
        'reason'     => $new_reason,
        'user_uuid'       => $new_user_uuid,
        'reason_allowance_uuid'  => $new_reason_allowance_uuid,
    ];

    $result = $this->model->update($id, $updateData);

    header('Content-Type: application/json');
    echo json_encode($result);
}

    public function destroy($uuid, $deleted_by)
    {
        $success = $this->model->softDelete($uuid, $deleted_by, time());

        echo json_encode([
            'status' => $success,
            'message' => $success ? 'Data berhasil dihapus' : 'Gagal menghapus data'
        ]);
    }

    public function delete($id)
{
    if (!$id) {
        errorResponse(400, "id reason tidak boleh kosong");
        return;
    }

    if (!isset($_GET['deleted_by'])) {
        errorResponse(400, "id pengguna yang menghapus tidak boleh kosong");
        return;
    }

    $reason_uuid = $id;
    $deleted_by = $_GET['deleted_by'];

    $result = $this->model->delete($reason_uuid, $deleted_by);

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
        errorResponse(400, "id reason tidak boleh kosong");
        return;
    }

    $reason_uuid = $id;

    $result = $this->model->recover($reason_uuid);

    if ($result) {
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        errorResponse(500, "Terjadi kesalahan");
    }
}

}
