<?php
require_once './models/Bincang_Salary.php';


class SalaryController
{
    private $model;

    public function __construct($db)
    {
        $this->model = new Bincang_Salary($db);
    }

    public function index() {}

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
        echo "get by id " . $id;
    }

    public function patch($id)
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $updateData = [
        'user_uuid'        => $data['user_uuid'] ?? null,
        'payee_user_uuid'  => $data['payee_user_uuid'] ?? null,
        'month'            => $data['month'] ?? null,
        'year'             => $data['year'] ?? null,
        'basic_salary'     => $data['basic_salary'] ?? null,
        'allowance'        => $data['allowance'] ?? null,
        'bonus'            => $data['bonus'] ?? null,
        'deduction'        => $data['deduction'] ?? null,
        'payment_date'     => $data['payment_date'] ?? null,
        'status'           => $data['status'] ?? null,
        'proof_of_payment' => $data['proof_of_payment'] ?? null,
    ];

    $result = $this->model->update($id, $updateData);
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}

public function delete($id)
{
    if (!$id) {
        errorResponse(400, "id salary tidak boleh kosong");
        return;
    }

    if (!isset($_GET['deleted_by'])) {
        errorResponse(400, "id pengguna yang menghapus tidak boleh kosong");
        return;
    }

    $salary_uuid = $id;
    $deleted_by = $_GET['deleted_by'];

    $result = $this->model->delete($salary_uuid, $deleted_by);

    if ($result) {
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        errorResponse(500, "Terjadi kesalahan");
    }
}


    public function post()
    {
        // Hitung total salary
        $basic_salary = isset($_POST['basic_salary']) && $_POST['basic_salary'] !== '' ? $_POST['basic_salary'] : 0;
        $allowance    = isset($_POST['allowance']) && $_POST['allowance'] !== '' ? $_POST['allowance'] : 0;
        $bonus        = isset($_POST['bonus']) && $_POST['bonus'] !== '' ? $_POST['bonus'] : 0;
        $deduction    = isset($_POST['deduction']) && $_POST['deduction'] !== '' ? $_POST['deduction'] : 0;

        $total_salary = $basic_salary + $allowance + $bonus - $deduction;

        // Upload gambar bukti pembayaran
        $proof_of_payment_path = null;
        if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/salary_proof/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $filename = uniqid() . '_' . basename($_FILES['proof_of_payment']['name']);
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $target_file)) {
                $proof_of_payment_path = 'uploads/salary_proof/' . $filename;
            } else {
                errorResponse(500, "Gagal mengunggah bukti pembayaran");
                return;
            }
        }

        $data = [
            'salary_uuid'       => generate_uuid(),
            'user_uuid'         => $_POST['user_uuid'] ?? '',
            'payee_user_uuid'   => $_POST['payee_user_uuid'] ?? '',
            'month'             => $_POST['month'] ?? 0,
            'year'              => $_POST['year'] ?? 0,
            'basic_salary'      => $basic_salary,
            'allowance'         => $allowance,
            'bonus'             => $bonus,
            'deduction'         => $deduction,
            'total_salary'      => $total_salary,
            'payment_date'      => $_POST['payment_date'] ?? null,
            'status'            => $_POST['status'] ?? 'pending',
            'proof_of_payment'  => $proof_of_payment_path,
            'created_at'        => time(),
        ];

        $result = $this->model->insert($data);

        if ($result) {
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            errorResponse(500, "Terjadi kesalahan saat menyimpan gaji");
        }
    }

    public function recover($id)
    {
        if (!$id) {
            errorResponse(400, "id salary tidak boleh kosong");
            return;
        }



        $salary_uuid = $id;

        $result = $this->model->recover($salary_uuid);

        if ($result) {
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            errorResponse(500, "Terjadi kesalahan");
        }
    }

    public function put($id)
    {
        echo "update by id " . $id;
    }


    public function updateProofImage($salary_id)
{

    if (!$salary_id || !isset($_FILES['proof_of_payment']) || $_FILES['proof_of_payment']['error'] !== UPLOAD_ERR_OK) {
        errorResponse(400, "ID gaji dan file bukti pembayaran wajib diisi dan valid");
        return;
    }

    // Cek data lama dari database
    $existing = $this->model->findById($salary_id);
    if (!$existing) {
        errorResponse(404, "Data gaji tidak ditemukan");
        return;
    }

    // Upload file baru
    $upload_dir = __DIR__ . '/../uploads/salary_proof/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = uniqid() . '_' . basename($_FILES['proof_of_payment']['name']);
    $target_file = $upload_dir . $filename;

    if (!move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $target_file)) {
        errorResponse(500, "Gagal mengunggah bukti pembayaran baru");
        return;
    }

    $new_file_path = 'uploads/salary_proof/' . $filename;

    // Hapus file lama jika ada
    if (!empty($existing['proof_of_payment'])) {
        $old_file = __DIR__ . '/../' . $existing['proof_of_payment'];
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }

    // Update ke database
    $updated = $this->model->updateProofImage($salary_id, $new_file_path);

    if ($updated) {
        echo json_encode([
            "status" => "success",
            "code" => 200,
            "message" => "Bukti pembayaran berhasil diperbarui",
            "data" => $new_file_path
        ]);
    } else {
        errorResponse(500, "Gagal memperbarui referensi gambar di database");
    }
}

public function report()
{
    $month = isset($_GET['month']) ? $_GET['month'] : null;
    $year = isset($_GET['year']) ? $_GET['year'] : null;

    $result = $this->model->getReport($month, $year);

    header('Content-Type: application/json');
    echo json_encode($result);
}

}
