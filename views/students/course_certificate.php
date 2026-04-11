<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

if (($_SESSION['role_name'] ?? '') !== 'HR') {
    http_response_code(403);
    echo 'Access denied.';
    return;
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('uploadCourseCertificateSignature')) {
    function uploadCourseCertificateSignature($file): ?string
    {
        if (!isset($file) || empty($file['name'])) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if (!empty($file['size']) && (int) $file['size'] > (2 * 1024 * 1024)) {
            return '__ERROR__SIZE__';
        }

        $allowedExt = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return '__ERROR__TYPE__';
        }

        $saved = uploadFile($file, 'course_certificates');
        if ($saved === false) {
            return '__ERROR__UPLOAD__';
        }

        return 'uploads/course_certificates/' . $saved;
    }
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = 0;

try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$id = (int) (($requestMethod === 'POST' ? ($_POST['registration_id'] ?? 0) : ($_GET['id'] ?? 0)));
if ($id <= 0) {
    echo 'Invalid student.';
    return;
}

$existingSnapshot = crmLoadCourseCertificateSnapshot($id);
$existingSnapshotViewConsumed = crmCourseCertificateSnapshotViewConsumed($id);
$submittedRemark = '';
$submittedHrName = '';
$uploadedSignaturePath = '';

if ($requestMethod === 'POST' && $existingSnapshot === null) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        echo 'Invalid CSRF token.';
        return;
    }

    $submittedRemark = trim((string) ($_POST['certificate_remarks'] ?? ''));
    if ($submittedRemark === '') {
        echo 'Certificate remark is required.';
        return;
    }

    $submittedHrName = trim((string) ($_POST['hr_name'] ?? ''));
    if ($submittedHrName === '') {
        echo 'HR name is required.';
        return;
    }

    $uploadedSignaturePath = (string) uploadCourseCertificateSignature($_FILES['authority_signature'] ?? null);
    if ($uploadedSignaturePath === '' || $uploadedSignaturePath === null) {
        echo 'Signature image is required.';
        return;
    }
    if ($uploadedSignaturePath === '__ERROR__SIZE__') {
        echo 'Signature image must be under 2 MB.';
        return;
    }
    if ($uploadedSignaturePath === '__ERROR__TYPE__') {
        echo 'Signature image must be JPG or PNG.';
        return;
    }
    if ($uploadedSignaturePath === '__ERROR__UPLOAD__') {
        echo 'Failed to upload signature image.';
        return;
    }
}

$params = [$id];
$sql = "
    SELECT
        r.*,
        COALESCE(rp.student_name, r.enquiry_snapshot_name) AS student_name,
        rp.college_name,
        rp.qualification,
        COALESCE(rp.parent_name, e.father_name) AS parent_name,
        " . crmBuildParentEmailFallbackSelect($pdo, 'rp', 'e') . " AS parent_email,
        shi.sent_to_hr_at
    FROM registrations r
    INNER JOIN student_hr_interviews shi ON shi.registration_id = r.id
    LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
    LEFT JOIN enquiries e ON e.id = r.enquiry_id
    WHERE r.id = ?
      AND r.reg_type = 'course'
      AND r.payment_status = 'paid'
";

if ($canAllBranches !== 1 && $branchId > 0) {
    $sql .= " AND r.branch_id = ?";
    $params[] = $branchId;
}

$sql .= " LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute($params);
$student = $st->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo 'Student not found or not eligible for course certificate.';
    return;
}

if ($requestMethod === 'POST' && $existingSnapshot !== null) {
    echo 'Certificate has already been generated and cannot be generated again.';
    if (!$existingSnapshotViewConsumed) {
        echo '<br><a href="index.php?page=students/course_certificate&id=' . (int) $id . '">View saved certificate once</a>';
    }
    return;
}

if ($requestMethod === 'GET' && $existingSnapshotViewConsumed) {
    echo 'This certificate has already been viewed once. Further viewing is not allowed.';
    return;
}

$assessmentAverage = null;
$mockAverage = null;
try {
    $st = $pdo->prepare("SELECT average_marks FROM assessment WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$id]);
    $assessmentAverage = $st->fetchColumn();
} catch (Exception $e) {
    $assessmentAverage = null;
}

try {
    $st = $pdo->prepare("SELECT mock_average FROM mock_interviews WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$id]);
    $mockAverage = $st->fetchColumn();
} catch (Exception $e) {
    $mockAverage = null;
}

$performanceNumbers = [];
if ($assessmentAverage !== null && $assessmentAverage !== false && is_numeric($assessmentAverage)) {
    $performanceNumbers[] = (float) $assessmentAverage;
}
if ($mockAverage !== null && $mockAverage !== false && is_numeric($mockAverage)) {
    $performanceNumbers[] = (float) $mockAverage;
}
$overallScore = $performanceNumbers ? round(array_sum($performanceNumbers) / count($performanceNumbers), 2) : null;

$performanceRemark = 'Satisfactory';
if ($overallScore !== null) {
    if ($overallScore >= 85) {
        $performanceRemark = 'Excellent';
    } elseif ($overallScore >= 70) {
        $performanceRemark = 'Very Good';
    } elseif ($overallScore >= 55) {
        $performanceRemark = 'Good';
    }
}
if ($submittedRemark !== '') {
    $performanceRemark = $submittedRemark;
}

$snapshot = $existingSnapshot;
if ($snapshot === null) {
    if ($requestMethod !== 'POST') {
        echo 'Certificate has not been generated yet.';
        return;
    }
    if (crmCourseCertificateSnapshotExists($id)) {
        echo 'Certificate has already been generated and cannot be generated again.';
        return;
    }

    $issuedAt = trim((string) ($student['internship_certificate_issued_at'] ?? ''));
    if ($issuedAt === '' || $issuedAt === '0000-00-00 00:00:00') {
        $issuedAt = date('Y-m-d H:i:s');
    }

    try {
        $upd = $pdo->prepare("
            UPDATE registrations
            SET registration_status = 'completed',
                internship_completion_status = 'completed',
                internship_certificate_status = 'given',
                internship_certificate_issued_at = ?,
                updated_at = NOW()
            WHERE id = ?
              AND reg_type = 'course'
            LIMIT 1
        ");
        $upd->execute([$issuedAt, $id]);
    } catch (Exception $e) {
    }

    $student['registration_status'] = 'completed';
    $student['internship_completion_status'] = 'completed';
    $student['internship_certificate_status'] = 'given';
    $student['internship_certificate_issued_at'] = $issuedAt;

    $startDate = trim((string) ($student['joined_on'] ?? ''));
    $startDateText = $startDate !== '' ? date('d.m.Y', strtotime($startDate)) : date('d.m.Y', strtotime($issuedAt));
    $endDateText = date('d.m.Y', strtotime($issuedAt));
    $studentName = strtoupper(trim((string) ($student['student_name'] ?? '-')));
    $programName = strtoupper(trim((string) ($student['program_name'] ?? '-')));
    $hrName = $submittedHrName !== ''
        ? $submittedHrName
        : (string) ($_SESSION['full_name'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? 'Human Resource');
    $certificateNo = 'ATS-COURSE-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);

    $snapshot = [
        'certificate_no' => $certificateNo,
        'issued_at' => $issuedAt,
        'student_name' => $studentName,
        'student_name_raw' => (string) ($student['student_name'] ?? ''),
        'program_name' => $programName,
        'program_name_raw' => (string) ($student['program_name'] ?? ''),
        'start_date_text' => $startDateText,
        'end_date_text' => $endDateText,
        'performance_remark' => $performanceRemark,
        'hr_name' => $hrName,
        'signature_path' => $uploadedSignaturePath,
        'registration_no' => (string) ($student['registration_no'] ?? ''),
        'student_email' => (string) ($student['enquiry_snapshot_email'] ?? ''),
        'parent_name' => (string) ($student['parent_name'] ?? ''),
        'parent_email' => (string) ($student['parent_email'] ?? ''),
        'overall_score' => $overallScore,
    ];

    if (!crmSaveCourseCertificateSnapshot($id, $snapshot)) {
        echo 'Failed to store certificate snapshot.';
        return;
    }

    $studentDisplayName = trim((string) ($snapshot['student_name_raw'] ?? 'Student'));
    $certificateUrl = BASE_URL . 'index.php?page=students/course_certificate&id=' . $id;
    $recipients = [
        ['email' => $snapshot['student_email'] ?? '', 'name' => $studentDisplayName],
        ['email' => $snapshot['parent_email'] ?? '', 'name' => trim((string) ($snapshot['parent_name'] ?? 'Parent'))],
    ];
    $htmlBody = '
        <p>Dear Student and Parent,</p>
        <p>The course completion certificate has been generated successfully.</p>
        <p><strong>Student:</strong> ' . h($studentDisplayName) . '<br>
        <strong>Registration No:</strong> ' . h((string) ($snapshot['registration_no'] ?? '')) . '<br>
        <strong>Program:</strong> ' . h((string) ($snapshot['program_name_raw'] ?? '')) . '<br>
        <strong>Issued At:</strong> ' . h((string) ($snapshot['issued_at'] ?? '')) . '<br>
        <strong>Certificate Link:</strong> <a href="' . h($certificateUrl) . '">' . h($certificateUrl) . '</a></p>
        <p>This saved certificate link can be viewed only once.</p>
        <p>Regards,<br>' . h(APP_NAME) . '</p>';
    $textBody = "Dear Student and Parent,\n\n"
        . "The course completion certificate has been generated successfully.\n"
        . "Student: {$studentDisplayName}\n"
        . "Registration No: " . (string) ($snapshot['registration_no'] ?? '') . "\n"
        . "Program: " . (string) ($snapshot['program_name_raw'] ?? '') . "\n"
        . "Issued At: " . (string) ($snapshot['issued_at'] ?? '') . "\n"
        . "Certificate Link: {$certificateUrl}\n\n"
        . "This saved certificate link can be viewed only once.\n\n"
        . "Regards,\n" . APP_NAME;
    crmSendEmail($recipients, 'Course certificate generated for ' . $studentDisplayName, $htmlBody, $textBody);

    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Course Certificate Saved</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: #f6f7fb;
                color: #1f2937;
                font-family: Arial, sans-serif;
            }
            .message {
                width: min(520px, calc(100% - 32px));
                border: 1px solid #d7dee8;
                border-radius: 8px;
                background: #fff;
                padding: 24px;
                box-shadow: 0 14px 32px rgba(15, 23, 42, 0.12);
            }
            h1 {
                margin: 0 0 10px;
                font-size: 24px;
            }
            p {
                line-height: 1.5;
            }
            a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 40px;
                border-radius: 8px;
                background: #0f766e;
                color: #fff;
                padding: 0 16px;
                font-weight: 700;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="message">
            <h1>Certificate saved</h1>
            <p>The course certificate has been generated and saved. It can be viewed only once from the saved certificate link.</p>
            <a href="index.php?page=students/course_certificate&id=<?= (int) $id ?>">View Certificate Once</a>
        </div>
    </body>
    </html>
    <?php
    return;
}

if ($requestMethod === 'GET') {
    crmMarkCourseCertificateSnapshotViewed($id);
}

$issuedAt = (string) ($snapshot['issued_at'] ?? '');
$startDateText = (string) ($snapshot['start_date_text'] ?? '');
$endDateText = (string) ($snapshot['end_date_text'] ?? '');
$studentName = (string) ($snapshot['student_name'] ?? '-');
$programName = (string) ($snapshot['program_name'] ?? '-');
$certificateNo = (string) ($snapshot['certificate_no'] ?? '');
$hrName = (string) ($snapshot['hr_name'] ?? 'Human Resource');
$performanceRemark = (string) ($snapshot['performance_remark'] ?? 'Satisfactory');
$uploadedSignaturePath = (string) ($snapshot['signature_path'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Course Completion Certificate</title>
    
</head>
<body>
    <div class="toolbar">
        <a href="index.php?page=students/course">Back to Course Students</a>
        <button type="button" onclick="window.print()">Print Certificate</button>
    </div>

    <div class="certificate-shell">
        <div class="certificate">
            <div class="topbar">
                <div class="brand-block">
                    <img src="assets/images/logo.png" alt="ATS Logo" class="brand-logo">
                    <div class="brand-copy">
                        <div class="brand-name">ACCENT TECHNO SOFT</div>
                        <div class="brand-tagline">Quality Matters...</div>
                    </div>
                </div>
                <div class="accreditation">
                    <img src="assets/images/certificate elements/credentials.png" alt="ISO, KAB and IAF Credentials" class="credentials-image">
                </div>
            </div>

            <div class="hero">
                <img src="assets/images/certificate elements/certificate_emblem.png" alt="Certificate Emblem" class="emblem-image">
            </div>

            <div class="content">
                <p class="certificate-intro">This is to certify that Mr.</p>
                <div class="student-name-wrap">
                    <span class="student-inline"><?= h($studentName) ?></span>
                </div>
                <p class="certificate-body">
                    has successfully completed <span class="highlight">"<?= h($programName) ?>"</span> Technology Training and gained Hands-on
                    experience during the period from <?= h($startDateText) ?> to <?= h($endDateText) ?> and has achieved
                    <span class="highlight-remark"><?= h($performanceRemark) ?></span> as remark for his performance in the exams conducted by Accent Techno Soft (ATS).
                </p>

                <div class="footer-area">
                    <img src="assets/images/certificate elements/abdul_kalam.png" alt="Dr. A.P.J. Abdul Kalam Illustration" class="portrait-image">
                    <div class="signatory">
                        <?php if ($uploadedSignaturePath !== ''): ?>
                            <img src="<?= h($uploadedSignaturePath) ?>" alt="Authority Signature" class="signature-image">
                        <?php endif; ?>
                        <div class="signatory-name"><?= h($hrName) ?></div>
                        <div class="signatory-role">Human Resource</div>
                        <div class="signatory-org">Accent Techno Soft (ATS)</div>
                    </div>
                </div>
            </div>

            <div class="website">www.accenttechnosoft.com</div>
        </div>
    </div>
</body>
</html>


