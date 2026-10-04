<?php
$required_role = 'admin';
$page_title    = 'Reports';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db          = get_db();
$departments = $db->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $dept_id    = (int)($_POST['department_id'] ?? 0);
    $from       = $_POST['from_date'] ?? date('Y-m-01');
    $to         = $_POST['to_date']   ?? date('Y-m-d');
    $report     = $_POST['report_type'] ?? 'attendance';
    $export     = $_POST['export_type'] ?? 'excel';

    require_once dirname(__DIR__) . '/vendor/autoload.php';

    // Fetch data
    if ($report === 'attendance') {
        $sql = "SELECT u.name AS student,d.name AS dept,s.name AS subject,a.date,a.status
                FROM attendance a
                JOIN users u ON a.student_id=u.id
                JOIN departments d ON u.department_id=d.id
                JOIN subjects s ON a.subject_id=s.id
                WHERE a.date BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($dept_id) { $sql .= ' AND u.department_id=?'; $params[] = $dept_id; }
        $headers = ['Student','Department','Subject','Date','Status'];
    } else {
        $sql = "SELECT u.name AS student,d.name AS dept,s.name AS subject,
                       asn.title,asub.submitted_at,asub.marks,asub.status
                FROM assignment_submissions asub
                JOIN users u ON asub.student_id=u.id
                JOIN departments d ON u.department_id=d.id
                JOIN assignments asn ON asub.assignment_id=asn.id
                JOIN subjects s ON asn.subject_id=s.id
                WHERE asub.submitted_at BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($dept_id) { $sql .= ' AND u.department_id=?'; $params[] = $dept_id; }
        $headers = ['Student','Department','Subject','Assignment','Submitted At','Marks','Status'];
    }

    $stmt = $db->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

    $filename = $report . '_' . date('Ymd_His');

    if ($export === 'excel') {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        foreach (range('A','G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $path   = EXPORT_PATH . '/' . $filename . '.xlsx';
        $writer->save($path);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        readfile($path); exit;

    } else {
        ini_set('memory_limit','256M');
        $dompdf = new \Dompdf\Dompdf();
        $html = '<html><body><h2>' . ucfirst($report) . ' Report</h2><table border="1" cellpadding="4" cellspacing="0" width="100%"><thead><tr>';
        foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4','landscape');
        $dompdf->render();
        $out = $dompdf->output();
        $path = EXPORT_PATH . '/' . $filename . '.pdf';
        file_put_contents($path, $out);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        echo $out; exit;
    }
}

require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
    <div class="card" style="max-width:640px">
        <div class="card-header fw-bold"><i class="bi bi-file-earmark-arrow-down me-2"></i>Export Reports</div>
        <div class="card-body">
            <form method="POST">
                <?= generate_csrf() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select">
                            <option value="attendance">Attendance</option>
                            <option value="assignments">Assignments</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Export Format</label>
                        <div class="d-flex gap-3">
                            <div class="form-check"><input class="form-check-input" type="radio" name="export_type" value="excel" id="xls" checked><label class="form-check-label" for="xls">Excel (.xlsx)</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="export_type" value="pdf" id="pdf"><label class="form-check-label" for="pdf">PDF</label></div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-download me-1"></i>Export</button>
            </form>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
