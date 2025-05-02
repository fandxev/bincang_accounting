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

    public function post()
    {
        // Hitung total salary
        $basic_salary = $_POST['basic_salary'] ?? 0;
        $allowance = $_POST['allowance'] ?? 0;
        $bonus = $_POST['bonus'] ?? 0;
        $deduction = $_POST['deduction'] ?? 0;

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
        generate_uuid();
    }
}
