<?php
require_once '../../config/app.php';

header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => 'Something went wrong'
];

try {

    // ===============================
    // SESSION VALIDATION
    // ===============================
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Session expired. Please login again.");
    }

    $userId   = (int)$_SESSION['user_id'];
    $roleId   = (int)($_SESSION['role_id'] ?? 0);
    $branchId = (int)($_SESSION['branch_id'] ?? 0);
    $roleName = $_SESSION['role_name'] ?? '';

    // ===============================
    // INPUT VALIDATION
    // ===============================
    $report_date = $_POST['report_date'] ?? '';

    if (!$report_date) {
        throw new Exception("Please select date");
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date)) {
        throw new Exception("Invalid date format");
    }

    // ===============================
    // DETERMINE DEPARTMENT
    // ===============================
    $department = 'front_office';

    if ($roleName === 'HR') {
        $department = 'hr';
    } elseif ($roleName === 'Marketing') {
        $department = 'marketing';
    } elseif ($roleName === 'Corporate Executive') {
        $department = 'corporate';
    }

    // ===============================
    // CHECK EXISTING REPORT
    // ===============================
    $check = $pdo->prepare("
        SELECT id FROM reports 
        WHERE user_id = ? AND report_date = ? 
        LIMIT 1
    ");
    $check->execute([$userId, $report_date]);
    $existing = $check->fetchColumn();

    if ($existing) {
        echo json_encode([
            'status' => 'exists',
            'message' => 'Opening existing report...',
            'report_id' => (int)$existing
        ]);
        exit;
    }

    // ===============================
    // INSERT NEW REPORT
    // ===============================
    $stmt = $pdo->prepare("
        INSERT INTO reports 
        (user_id, role_id, branch_id, report_date, department, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $userId,
        $roleId,
        $branchId,
        $report_date,
        $department
    ]);

    $reportId = (int)$pdo->lastInsertId();

    $response = [
        'status' => 'success',
        'message' => 'Report created successfully',
        'report_id' => $reportId
    ];

} catch (Exception $e) {

    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
exit;