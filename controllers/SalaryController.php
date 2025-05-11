<?php
require_once './models/Bincang_Salary.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;


class SalaryController
{
    private $model;
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->model = new Bincang_Salary($conn);
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
        'detail_allowance' => $data['detail_allowance'] ?? null,
        'detail_bonus'     => $data['detail_bonus'] ?? null,
        'detail_deduction' => $data['detail_deduction'] ?? null,
        'note'             => $data['note'] ?? null,
        'proof_of_payment' => $data['proof_of_payment'] ?? null,
    ];

    // Update database
    $result = $this->model->update($id, $updateData, $data['update_by']);

    if ($result && $result['status'] === 'success') {
        // Ambil data terbaru dari DB
        $salaryData = $this->model->findById($id);

        if ($salaryData['status'] === 'paid') {
            // Format tanggal
            if (!empty($salaryData['payment_date'])) {
                $salaryData['payment_date_formatted'] = $this->formatTanggalIndonesia($salaryData['payment_date']);
            }

            // Generate ulang PDF
            $pdfPath = $this->generateSalarySlipPDF($salaryData, $salaryData['pdf_payslip']);

            // Simpan path PDF ke database
            $updateStmt = $this->model->conn->prepare("UPDATE bincang_salary SET pdf_payslip = :pdf WHERE salary_uuid = :uuid");
            $updateStmt->execute([
                ':pdf' => $pdfPath,
                ':uuid' => $salaryData['salary_uuid']
            ]);

            $salaryData['pdf_payslip'] = $pdfPath;
            $result['data'] = $salaryData;
        } elseif ($salaryData['status'] !== 'paid') {
            // Hapus PDF jika ada
            if (!empty($salaryData['pdf_payslip'])) {
                $pdfFilePath =  $salaryData['pdf_payslip'];
                if (file_exists($pdfFilePath)) {
                    unlink($pdfFilePath);
                }

                // Kosongkan field pdf_payslip di DB
                $clearStmt = $this->model->conn->prepare("UPDATE bincang_salary SET pdf_payslip = NULL WHERE salary_uuid = :uuid");
                $clearStmt->execute([':uuid' => $salaryData['salary_uuid']]);

                $salaryData['pdf_payslip'] = null;
                $result['data'] = $salaryData;
            }
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        errorResponse(500, "Gagal memperbarui data gaji");
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
        $detail_allowance = isset($_POST['detail_allowance']) && $_POST['detail_allowance'] !== '' ? $_POST['detail_allowance'] : null;
        $detail_bonus = isset($_POST['detail_bonus']) && $_POST['detail_bonus'] !== '' ? $_POST['detail_bonus'] : null;
        $detail_deduction = isset($_POST['detail_deduction']) && $_POST['detail_deduction'] !== '' ? $_POST['detail_deduction'] : null;
        $note = isset($_POST['note']) && $_POST['note'] !== '' ? $_POST['note'] : null;


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
            'payment_date'      =>  !empty($_POST['payment_date']) ? $_POST['payment_date'] : null,
            'status'            => $_POST['status'] ?? 'pending',
            'proof_of_payment'  => $proof_of_payment_path,
            'created_at'        => time(),
            'detail_allowance' => $detail_allowance,
            'detail_bonus' => $detail_bonus,
            'detail_deduction' => $detail_deduction,
            'note' => $note
        ];

        $result = $this->model->insert($data);

        if ($result && $result['status'] === 'success') {
    $salaryData = $result['data'];

    if ($salaryData['status'] === 'paid') {
        // Generate PDF

        //format ke indo
        if (!empty($salaryData['payment_date'])) {
    $salaryData['payment_date_formatted'] = $this->formatTanggalIndonesia($salaryData['payment_date']);
}

        $pdfPath = $this->generateSalarySlipPDF($salaryData);

        // Simpan referensi file PDF ke database
        $updateStmt = $this->model->conn->prepare("UPDATE bincang_salary SET pdf_payslip = :pdf WHERE salary_uuid = :uuid");
        $updateStmt->execute([
            ':pdf' => $pdfPath,
            ':uuid' => $salaryData['salary_uuid']
        ]);

        // Tambahkan path ke response jika ingin
        $salaryData['pdf_payslip'] = $pdfPath;
        $result['data'] = $salaryData;
    }

    header('Content-Type: application/json');
    echo json_encode($result);
}
        else {
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

        $result = $this->model->recover($salary_uuid,$_GET['recover_by']);

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

        $userIsNotAccountant =  isAccountant($_POST['update_by'],$this->conn);
        if(!empty($userIsNotAccountant)){
            echo json_encode($userIsNotAccountant);
            return;
        }


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


public function salarySlip()
{
    $month = isset($_GET['month']) ? $_GET['month'] : null;
    $year = isset($_GET['year']) ? $_GET['year'] : null;
    $user_uuid = isset($_GET['user_uuid']) ? $_GET['user_uuid'] : null;

    $result = $this->model->getSalarySlip($month, $year, $user_uuid);

    header('Content-Type: application/json');
    echo json_encode($result);
}



public function generateSalarySlipPDF($salaryData, $pathReplaceName = "")
{
    // Setup Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);

    // Konversi angka bulan ke nama bulan (Indonesia)
    $bulanIndonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $namaBulan = $bulanIndonesia[(int)$salaryData['month']] ?? 'Bulan Tidak Valid';

    // Fungsi render detail inline
    $renderDetailInline = function ($jsonString) {
        $items = json_decode($jsonString, true);
        if (empty($items)) return '';

        $html = '<div style="font-size:11px; margin-top:4px;">';
        foreach ($items as $item) {
            $parts = explode(':', $item);
            $label = htmlspecialchars($parts[0] ?? '');
            $value = number_format((int)($parts[1] ?? 0));
            $html .= '<div>- ' . $label . ': Rp ' . $value . '</div>';
        }
        $html .= '</div>';
        return $html;
    };

    // HTML content
    $html = '
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h2 { text-align: center; margin-bottom: 20px; }
        .header-info { margin-bottom: 15px; }
        .header-info p { margin: 2px 0; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 8px 10px;
            border: 1px solid #666;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
            text-align: left;
        }
        .total {
            font-weight: bold;
            background-color: #eef;
        }
        .footer {
            margin-top: 30px;
            font-size: 11px;
            text-align: center;
            color: #777;
        }
    </style>

    <h2>Slip Gaji Bincang</h2>

    <div class="header-info">
        <p><strong>Nama Karyawan:</strong> ' . htmlspecialchars($salaryData['nama_karyawan']) . '</p>
        <p><strong>Periode:</strong> ' . $namaBulan . ' ' . $salaryData['year'] . '</p>
    </div>

    <table>
        <tr>
            <th>Komponen</th>
            <th>Jumlah</th>
        </tr>
        <tr>
            <td><strong>Gaji Pokok</strong></td>
            <td>Rp ' . number_format($salaryData['basic_salary']) . '</td>
        </tr>
        <tr>
            <td><strong>Tunjangan</strong>' . $renderDetailInline($salaryData['detail_allowance']) . '</td>
            <td>Rp ' . number_format($salaryData['allowance']) . '</td>
        </tr>
        <tr>
            <td><strong>Bonus</strong>' . $renderDetailInline($salaryData['detail_bonus']) . '</td>
            <td>Rp ' . number_format($salaryData['bonus']) . '</td>
        </tr>
        <tr>
            <td><strong>Potongan</strong>' . $renderDetailInline($salaryData['detail_deduction']) . '</td>
            <td>Rp ' . number_format($salaryData['deduction']) . '</td>
        </tr>
        <tr class="total">
            <td><strong>Total Gaji Diterima</strong></td>
            <td><strong>Rp ' . number_format($salaryData['total_salary']) . '</strong></td>
        </tr>
        <tr>
            <td><strong>Tanggal Pembayaran</strong></td>
            <td>' . ($salaryData['payment_date_formatted'] ?? '-') . '</td>
        </tr>
        <tr>
            <td><strong>Status</strong></td>
            <td>' . ucfirst($salaryData['status']) . '</td>
        </tr>
        <tr>
            <td><strong>Catatan</strong></td>
            <td>' . nl2br(htmlspecialchars($salaryData['note'] ?? '-')) . '</td>
        </tr>
    </table>

    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis oleh sistem.</p>
    </div>
    ';

    // Generate PDF
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Simpan file PDF ke direktori
    $pdfDir = __DIR__ . '/../uploads/slip_gaji/';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0777, true);
    }

    if (!empty($pathReplaceName)) {
        // Gunakan nama file yang diberikan
        $fileName = $pathReplaceName;
        $filePath = $fileName;



        // Overwrite jika sudah ada
        file_put_contents($filePath, $dompdf->output());
    } else {
        // Generate nama file otomatis
        $namaPegawai = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($salaryData['nama_karyawan']));
        $namaBulanSlug = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($namaBulan));
        $fileName = 'slip_gaji_' . $namaPegawai . '_' . $namaBulanSlug . '_' . $salaryData['year'] . '.pdf';
        $filePath = $pdfDir . $fileName;

        // Jika file sudah ada, tambahkan counter
        $counter = 1;
        while (file_exists($filePath)) {
            $fileName = 'slip_gaji_' . $namaPegawai . '_' . $namaBulanSlug . '_' . $salaryData['year'] . '_' . $counter . '.pdf';
            $filePath = $pdfDir . $fileName;
            $counter++;
        }

        file_put_contents($filePath, $dompdf->output());
    }
    if($pathReplaceName != "")
    {
        return $pathReplaceName;
    }
    else
    {
    return 'uploads/slip_gaji/' . $fileName;
    }
}



function formatTanggalIndonesia($tanggal) {
    $bulan = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember',
    ];

    $dt = new DateTime($tanggal);
    return $dt->format('d') . ' ' . $bulan[$dt->format('F')] . ' ' . $dt->format('Y');
}






}
