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
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f5f2f7;
            font-family: "Times New Roman", Georgia, serif;
            color: #231f20;
        }
        .toolbar {
            max-width: 1080px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .toolbar button,
        .toolbar a {
            border: 1px solid #e8bfd0;
            background: #fff;
            color: #c2185b;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .certificate-shell {
            max-width: 1080px;
            margin: 0 auto;
            background: #fff;
            border: 16px solid #e6137f;
            box-shadow: 0 24px 50px rgba(17, 24, 39, .10);
        }
        .certificate {
            position: relative;
            margin: 16px;
            min-height: 700px;
            padding: 28px 36px 26px;
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(230, 19, 127, .18);
        }
        .certificate::before {
            content: "";
            position: relative;
        }
        .certificate::after {
            content: "";
            position: absolute;
            inset: 120px 120px 130px 120px;
            background: url('assets/images/logo.png') center center / contain no-repeat;
            opacity: .08;
            pointer-events: none;
            filter: saturate(120%);
        }
        .topbar {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1.4fr .9fr;
            gap: 18px;
            align-items: start;
        }
        .brand-block {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        .brand-logo {
            width: 160px;
            height: auto;
            object-fit: contain;
            margin-top: -6px;
        }
        .brand-copy {
            padding-top: 8px;
        }
        .brand-name {
            font-size: 34px;
            line-height: 1;
            letter-spacing: .5px;
            font-weight: 700;
            color: #111;
        }
        .brand-tagline {
            margin-top: 6px;
            font-size: 22px;
            font-style: italic;
            color: #cf1c7d;
            text-align: right;
        }
        .accreditation {
            justify-self: end;
            text-align: center;
            padding-top: 10px;
        }
        .credentials-image {
            width: 100%;
            max-width: 310px;
            height: auto;
            display: block;
        }
        .hero {
            position: relative;
            z-index: 1;
            text-align: center;
            margin-top: 18px;
        }
        .emblem-image {
            width: min(320px, 46%);
            height: auto;
            display: inline-block;
        }
        .content {
            position: relative;
            z-index: 1;
            margin-top: 56px;
            padding: 0 12px;
        }
        .certificate-intro {
            margin: 0;
            font-size: 27px;
            line-height: 1.3;
            color: #1d1d1d;
            text-align: left;
        }
        .student-name-wrap {
            margin: 16px 0 18px;
            text-align: center;
        }
        .student-inline {
            display: inline-block;
            min-width: 720px;
            max-width: 100%;
            border-bottom: 3px solid #2d2d2d;
            text-align: center;
            font-size: 30px;
            font-weight: 700;
            letter-spacing: .9px;
            line-height: 1.2;
            padding: 0 12px 6px;
        }
        .certificate-body {
            margin: 0;
            font-size: 25px;
            line-height: 1.52;
            color: #1d1d1d;
            text-align: justify;
            text-align-last: left;
        }
        .highlight {
            color: #d8137d;
            font-weight: 700;
        }
        .highlight-remark {
            font-weight: 700;
        }
        .footer-area {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 54px;
        }
        .portrait-image {
            width: 180px;
            height: auto;
            display: block;
        }
        .signatory {
            text-align: center;
            min-width: 270px;
        }
        .signature-image {
            max-width: 210px;
            max-height: 92px;
            object-fit: contain;
            margin: 0 auto 8px;
            display: block;
        }
        .signatory-name {
            font-size: 20px;
            font-weight: 700;
            color: #1f1f1f;
        }
        .signatory-role,
        .signatory-org {
            font-size: 18px;
            margin-top: 4px;
            color: #1f1f1f;
        }
        .website {
            position: absolute;
            left: 50%;
            bottom: 22px;
            transform: translateX(-50%);
            color: #d8137d;
            text-align: center;
            font-weight: 700;
            font-size: 18px;
        }
        @media (max-width: 1200px) {
            .student-inline {
                min-width: 0;
                width: 66%;
                font-size: 25px;
            }
            .certificate-intro,
            .certificate-body {
                font-size: 22px;
            }
        }
        @media print {
            body {
                padding: 0;
                background: #fff;
            }
            .toolbar {
                display: none;
            }
            .certificate-shell {
                max-width: none;
                box-shadow: none;
                border-width: 14px;
            }
            .certificate {
                margin: 0;
            }
        }
    
/* =====================================================
GLOBAL TYPOGRAPHY STYLECSS SYNC
font-family + font-size + font-weight only
===================================================== */
:where(body,button,input,select,textarea,label,span,p,h1,h2,h3,h4,h5,h6,a,div){
  font-family:'Poppins',sans-serif !important;
}
:where(h1,.h1,.page-title,.crm-page-title,.dashboard-header h2){font-size:clamp(2rem, 2.5vw, 2.4rem) !important;font-weight:700 !important;}
:where(h2,.h2,.section-title){font-size:clamp(1.6rem, 2vw, 2rem) !important;font-weight:600 !important;}
:where(h3,.h3,.card-header,.table-title){font-size:clamp(1.3rem, 1.6vw, 1.5rem) !important;font-weight:600 !important;}
:where(h4,.h4){font-size:1.2rem !important;font-weight:500 !important;}
:where(h5,.h5){font-size:1rem !important;font-weight:500 !important;}
:where(h6,.h6){font-size:0.9rem !important;font-weight:500 !important;}
:where(body){font-size:1rem !important;}
:where(p,.text-body,li,td,.text-muted,.help-text,.form-text,.small,small,.secondary-text){font-size:0.95rem !important;font-weight:400 !important;}
:where(.small,small,.text-muted,.help-text,.form-text,.att-sub,.crm-note){font-size:0.85rem !important;font-weight:400 !important;}
:where(label,.form-label){font-size:0.85rem !important;font-weight:500 !important;}
:where(input,select,textarea,.form-control,.form-select){font-size:0.95rem !important;font-weight:400 !important;}
:where(input::placeholder,textarea::placeholder){font-weight:400 !important;}
:where(button,.btn,.dt-button,.crm-action-btn,.crm-icon-btn,.btn-icon-only,.action-btn,.targets-btn-icon,.iso-report-btn,.iso-report-action-btn){font-size:0.9rem !important;font-weight:600 !important;}
:where(.btn[data-mobile-label],.btn-icon-only[data-mobile-label],.action-btn[data-mobile-label],.crm-icon-btn[data-mobile-label],.targets-btn-icon[data-mobile-label],.iso-report-icon-btn[data-mobile-label],.iso-report-action-btn[data-mobile-label])::after{font-size:0.75rem !important;font-weight:600 !important;}
:where(.table th,.crm-table th,.dataTables_wrapper th,th){font-size:0.75rem !important;font-weight:600 !important;}
:where(.table td,.dataTables_wrapper tbody td){font-size:0.9rem !important;}
:where(.dataTables_wrapper .dataTables_info){font-size:0.85rem !important;font-weight:400 !important;}
:where(.dataTables_wrapper .paginate_button){font-size:0.9rem !important;font-weight:600 !important;}
:where(.badge,.status-badge,.crm-status-badge,.status-pill,.badge-status,[data-status],.tooltip,.ui-tooltip,.floating-ui-tooltip__bubble){font-weight:600 !important;}

/* ===== GLOBAL BUTTON STANDARDIZATION ===== */
button,
.btn,
.crm-action-btn,
.btn-filter,
.btn-reset,
.btn-add,
.btn-excel,
.action-btn,
.btn-icon-only,
a.btn,
input[type="button"],
input[type="submit"],
input[type="reset"],
[role="button"] {
    font-size: 0.92rem;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-icon-only,
.crm-action-btn,
.action-btn,
.btn-sm,
.btn-xs,
button.btn-icon,
a.btn-icon,
.btn i:only-child,
button i:only-child {
    font-size: 0.9rem;
    min-height: 34px;
    padding: 8px;
    border-radius: 10px;
    font-weight: 600;
}
</style>
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

