<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
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
    echo '<br><a href="index.php?page=students/course_certificate&id=' . (int) $id . '">View saved certificate</a>';
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
        <p>This saved certificate can be viewed and downloaded from the HR course students list.</p>
        <p>Regards,<br>' . h(APP_NAME) . '</p>';
    $textBody = "Dear Student and Parent,\n\n"
        . "The course completion certificate has been generated successfully.\n"
        . "Student: {$studentDisplayName}\n"
        . "Registration No: " . (string) ($snapshot['registration_no'] ?? '') . "\n"
        . "Program: " . (string) ($snapshot['program_name_raw'] ?? '') . "\n"
        . "Issued At: " . (string) ($snapshot['issued_at'] ?? '') . "\n"
        . "Certificate Link: {$certificateUrl}\n\n"
        . "This saved certificate can be viewed and downloaded from the HR course students list.\n\n"
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
            font-size: 17px;
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
            <p>The course certificate has been generated and saved. It is now view-only and can be downloaded from the saved certificate page.</p>
            <a href="index.php?page=students/course_certificate&id=<?= (int) $id ?>">View Saved Certificate</a>
        </div>
    </body>
    </html>
    <?php
    return;
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
$isDownloadRequest = $requestMethod === 'GET' && (string) ($_GET['download'] ?? '') === '1';
if ($isDownloadRequest && !headers_sent()) {
    $downloadFileName = 'course_certificate_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $certificateNo !== '' ? $certificateNo : (string) $id) . '.html';
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= h(BASE_URL) ?>">
    <title>Course Completion Certificate</title>
    <style>
        :root {
            color-scheme: light;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            min-height: 100vh;
            background: #f9edf8;
            font-family: Georgia, 'Times New Roman', serif;
            color: #111;
        }
        .toolbar {
            width: min(1540px, calc(100% - 32px));
            margin: 20px auto 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .toolbar a,
        .toolbar button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            background: #c81d75;
            color: #fff;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .toolbar button:hover,
        .toolbar a:hover {
            background: #9f145d;
        }
        .certificate-shell {
            width: 100%;
            padding: 24px 0 42px;
            display: flex;
            justify-content: center;
        }
        .certificate {
            width: min(1540px, calc(100% - 40px));
            aspect-ratio: 1.414 / 1;
            border: 30px solid #d30079;
            background: #fff;
            padding: 34px 46px 22px;
            box-shadow: 0 26px 80px rgba(0, 0, 0, 0.12);
            position: relative;
            overflow: hidden;
        }
        .certificate::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('assets/images/logo.png') center 54% / 520px auto no-repeat;
            opacity: 0.045;
            pointer-events: none;
        }
        .certificate-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: grid;
            grid-template-rows: auto auto 1fr;
        }
        .certificate-header {
            display: flex;
            justify-content: space-between;
            gap: 38px;
            align-items: flex-start;
        }
        .certificate-brand {
            display: flex;
            gap: 22px;
            align-items: center;
            min-width: 0;
        }
        .brand-logo {
            width: 210px;
            max-width: 24vw;
            height: auto;
            display: block;
            flex: 0 0 auto;
        }
        .brand-text {
            display: grid;
            gap: 8px;
            min-width: 0;
        }
        .brand-name {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: clamp(26px, 2.5vw, 42px);
            line-height: 1;
            font-weight: 800;
            color: #111;
            white-space: nowrap;
        }
        .brand-tagline {
            font-size: clamp(18px, 1.6vw, 27px);
            line-height: 1;
            font-weight: 700;
            color: #d30079;
            font-style: italic;
            text-align: center;
        }
        .certificate-meta {
            text-align: right;
            display: grid;
            gap: 8px;
            justify-items: end;
            flex: 0 1 380px;
            max-width: 380px;
            min-width: 0;
        }
        .certifications {
            width: 285px;
            max-width: 24vw;
            height: auto;
            display: block;
        }
        .certificate-number {
            max-width: 100%;
            font-size: clamp(16px, 1.25vw, 22px);
            line-height: 1.15;
            color: #111;
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .hero {
            display: flex;
            justify-content: center;
            margin: 6px 0 14px;
        }
        .emblem-image {
            width: 400px;
            max-width: 35vw;
            height: auto;
            display: block;
        }
        .certificate-title {
            display: none;
        }
        .certificate-main {
            min-height: 0;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            padding: 0;
        }
        .certificate-description {
            margin: 0;
        }
        .certificate-main p {
            margin: 12px 0 0;
            font-size: clamp(18px, 1.6vw, 27px);
            line-height: 1.65;
            color: #111;
            word-spacing: 0.12em;
        }
        .certificate-main .student-line {
            display: grid;
            grid-template-columns: auto 1fr;
            align-items: end;
            gap: 30px;
            margin-top: 6px;
            font-size: clamp(19px, 1.75vw, 29px);
            line-height: 1.2;
            font-weight: 500;
        }
        .certificate-main .student-line span:first-child {
            white-space: nowrap;
        }
        .student-name {
            display: block;
            border-bottom: 3px solid #111;
            padding: 0 12px 2px;
            text-align: center;
            font-size: clamp(22px, 2vw, 35px);
            line-height: 1;
            font-weight: 900;
            color: #111;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .program-name {
            color: #d30079;
            font-weight: 900;
            white-space: nowrap;
        }
        .remark {
            color: #111;
            font-weight: 900;
            white-space: nowrap;
        }
        .certificate-bottom {
            align-self: end;
            display: flex;
            justify-content: space-between;
            gap: 48px;
            align-items: flex-end;
            margin-top: 20px;
        }
        .portrait-image {
            width: 220px;
            max-width: 22vw;
            height: auto;
            object-fit: contain;
            display: block;
        }
        .signature-block {
            display: grid;
            gap: 8px;
            width: min(420px, 34vw);
            min-width: 0;
            text-align: center;
            justify-items: center;
        }
        .signature-image {
            width: 200px;
            max-height: 90px;
            display: block;
            object-fit: contain;
        }
        .signature-name {
            font-size: clamp(19px, 1.6vw, 27px);
            line-height: 1.1;
            font-weight: 800;
            color: #111;
        }
        .signature-role,
        .signature-company {
            max-width: 100%;
            font-size: clamp(16px, 1.25vw, 22px);
            line-height: 1.15;
            color: #111;
            overflow-wrap: normal;
        }
        .website {
            align-self: end;
            text-align: center;
            margin-top: 6px;
            font-size: clamp(17px, 1.4vw, 25px);
            line-height: 1;
            font-weight: 800;
            color: #d30079;
        }
        @media (max-width: 900px) {
            .certificate {
                padding: 20px;
            }
            .certificate-header,
            .certificate-bottom {
                margin-top: 3mm;
                gap: 8mm;
            }
            .certificate-brand {
                gap: 5mm;
                flex: 1 1 auto;
                min-width: 0;
            }
            .certificate-meta {
            text-align: right;
            display: grid;
            gap: 8px;
            justify-items: end;
            flex: 0 1 380px;
            max-width: 380px;
            min-width: 0;
        }
            .certificate-main .student-line {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
        @page {
            size: A4 landscape;
            margin: 0;
        }
        @media print {
            html,
            body {
                width: 297mm;
                height: 210mm;
                min-height: 0;
                margin: 0;
                padding: 0;
                background: #fff;
                overflow: hidden;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .toolbar {
                display: none !important;
            }
            .certificate-shell {
                width: 297mm;
                height: 210mm;
                padding: 0;
                display: grid;
                place-items: stretch;
                overflow: hidden;
            }
            .certificate {
                width: 297mm;
                height: 210mm;
                aspect-ratio: auto;
                padding: 6mm 9mm 5mm;
                border-width: 4mm;
                box-shadow: none;
                overflow: hidden;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .certificate::before {
                background-size: 105mm auto;
                opacity: 0.04;
            }
            .certificate-content {
                height: 100%;
                display: flex;
                flex-direction: column;
            }
            .certificate-header {
                gap: 8mm;
            }
            .certificate-brand {
                gap: 5mm;
                flex: 1 1 auto;
                min-width: 0;
            }
            .brand-logo {
                width: 35mm;
                max-width: none;
            }
            .brand-name {
                font-size: 24px;
                line-height: 1;
            }
            .brand-tagline {
                font-size: 15px;
            }
            .certificate-meta {
                flex-basis: 54mm;
                max-width: 54mm;
            }
            .certifications {
                width: 41mm;
                max-width: 100%;
            }
            .certificate-number {
                font-size: 10px;
                line-height: 1.15;
                text-align: right;
            }
            .emblem-image {
                width: 65mm;
                max-width: none;
            }
            .hero {
                margin: 1mm 0 2mm;
            }
            .certificate-main p {
                margin-top: 1.5mm;
                font-size: 25px;
                line-height: 1.2;
            }
            .certificate-main {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                min-height: 0;
            }
            .certificate-main .student-line {
                display: flex;
                flex-wrap: nowrap;
                align-items: flex-end;
                justify-content: flex-start;
                gap: 3mm;
                margin-top: 0;
                font-size: 25px;
                text-align: left;
            }
            .certificate-main .student-line span:first-child {
                flex: 0 0 auto;
                white-space: nowrap;
            }
            .student-name {
                flex: 1 1 auto;
                min-width: 0;
                font-size: 35px;
                border-bottom-width: 2px;
            }
            .certificate-bottom {
                align-self: start;
                width: 100%;
                margin-top: 50px;
                gap: 8mm;
            }
            .portrait-image {
                width: 41mm;
                max-width: none;
            }
            .signature-block {
                width: 56mm;
                min-width: 0;
                gap: 2mm;
            }
            .signature-image {
                width: 34mm;
                max-height: 13mm;
            }
            .signature-name {
                font-size: 16px;
            }
            .signature-role,
            .signature-company {
                max-width: 100%;
                font-size: 12px;
                line-height: 1.15;
                overflow-wrap: normal;
            }
            .website {
                position: absolute;
                left: 0;
                right: 0;
                bottom: 1.5mm;
                width: auto;
                margin: 0;
                text-align: center;
                font-size: 17px;
            }
        }
    </style>
</head>
<body>
    <?php if (!$isDownloadRequest): ?>
    <div class="toolbar">
        <a href="index.php?page=students/course">Back to Course Students</a>
        <a href="index.php?page=students/course_certificate&id=<?= (int) $id ?>&download=1">Download Certificate</a>
        <button type="button" onclick="window.print()">Print Certificate</button>
    </div>
    <?php endif; ?>

    <div class="certificate-shell">
        <div class="certificate">
            <div class="certificate-content">
                <div class="certificate-header">
                    <div class="certificate-brand">
                        <img src="assets/images/logo.png" alt="ATS Logo" class="brand-logo">
                        <div class="brand-text">
                            <div class="brand-name">ACCENT TECHNO SOFT</div>
                            <div class="brand-tagline">Quality Matters...</div>
                        </div>
                    </div>
                    <div class="certificate-meta">
                        <img src="assets/images/certificate elements/credentials.png" alt="Certifications" class="certifications">
                        <div class="certificate-number">Certification No: <?= h($certificateNo) ?></div>
                    </div>
                </div>

                <div class="hero">
                    <img src="assets/images/certificate elements/certificate_emblem.png" alt="Certificate Emblem" class="emblem-image">
                </div>

                <div class="certificate-title">CERTIFICATE</div>

                <div class="certificate-main">
                    <div class="certificate-description">
                        <div class="certificate-line student-line">
                            <span>This is to certify that Mr.</span>
                            <span class="student-name"><?= h($studentName) ?></span>
                        </div>
                    </div>
                    <p class="certificate-description">
                        has successfully completed <span class="program-name">"<?= h($programName) ?>"</span> Technology Training and gained Hands-on experience during the period from <?= h($startDateText) ?> to <?= h($endDateText) ?> and has achieved <span class="remark"><?= h($performanceRemark) ?></span> as remark for his performance in the exams conducted by Accent Techno Soft (ATS).
                    </p>

                    <div class="certificate-bottom">
                        <img src="assets/images/certificate elements/abdul_kalam.png" alt="Illustration" class="portrait-image">
                        <div class="signature-block">
                            <?php if ($uploadedSignaturePath !== ''): ?>
                                <img src="<?= h($uploadedSignaturePath) ?>" alt="Authority Signature" class="signature-image">
                            <?php endif; ?>
                            <div class="signature-name"><?= h($hrName) ?></div>
                            <div class="signature-role">Human Resource</div>
                            <div class="signature-company">Accent Techno Soft (ATS)</div>
                        </div>
                    </div>

                    <div class="website">www.accenttechnosoft.com</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

















