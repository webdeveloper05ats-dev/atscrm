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

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    echo 'Invalid student.';
    return;
}

$params = [$id];
$sql = "
    SELECT
        r.*,
        COALESCE(rp.student_name, r.enquiry_snapshot_name) AS student_name,
        rp.college_name,
        rp.qualification,
        shi.sent_to_hr_at
    FROM registrations r
    INNER JOIN student_hr_interviews shi ON shi.registration_id = r.id
    LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
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

$issuedAt = trim((string) ($student['internship_certificate_issued_at'] ?? ''));
if ($issuedAt === '' || $issuedAt === '0000-00-00 00:00:00') {
    $issuedAt = date('Y-m-d H:i:s');
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
        $student['registration_status'] = 'completed';
        $student['internship_completion_status'] = 'completed';
        $student['internship_certificate_status'] = 'given';
        $student['internship_certificate_issued_at'] = $issuedAt;
    } catch (Exception $e) {
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Course Completion Certificate</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #f6f8fc;
            font-family: Georgia, "Times New Roman", serif;
            color: #1f2937;
        }
        .toolbar {
            max-width: 1100px;
            margin: 0 auto 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .toolbar button,
        .toolbar a {
            border: 1px solid #e5c2d3;
            background: #fff;
            color: #c2185b;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .certificate-shell {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border: 14px solid #f4d8e5;
            box-shadow: 0 24px 50px rgba(17, 24, 39, .12);
        }
        .certificate {
            border: 3px solid #d81b60;
            margin: 14px;
            padding: 56px 68px;
            position: relative;
            background:
                radial-gradient(circle at top right, rgba(216, 27, 96, .08), transparent 22%),
                radial-gradient(circle at bottom left, rgba(59, 130, 246, .08), transparent 28%),
                linear-gradient(180deg, #fffdfd 0%, #fff8fb 100%);
        }
        .title {
            text-align: center;
            font-size: 18px;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #be185d;
            font-weight: 700;
        }
        .name {
            text-align: center;
            font-size: 50px;
            margin: 28px 0 12px;
            color: #a21caf;
            font-weight: 700;
        }
        .subtitle {
            text-align: center;
            font-size: 20px;
            line-height: 1.8;
            color: #374151;
            max-width: 820px;
            margin: 0 auto;
        }
        .program {
            text-align: center;
            margin-top: 18px;
            font-size: 28px;
            color: #111827;
            font-weight: 700;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-top: 44px;
        }
        .meta-card {
            border: 1px solid #f2d3e0;
            border-radius: 16px;
            padding: 18px;
            background: rgba(255,255,255,.86);
        }
        .meta-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #9d174d;
        }
        .meta-value {
            margin-top: 8px;
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }
        .footer-row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-top: 56px;
            align-items: end;
        }
        .signature {
            width: 240px;
            border-top: 1px solid #9ca3af;
            padding-top: 10px;
            text-align: center;
            font-weight: 700;
            color: #374151;
        }
        .seal {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 3px solid #d81b60;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #d81b60;
            font-weight: 700;
            line-height: 1.4;
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
                border: none;
            }
            .certificate {
                margin: 0;
            }
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
            <div class="title">Course Completion Certificate</div>
            <div class="name"><?= h($student['student_name'] ?: '-') ?></div>
            <div class="subtitle">
                This is to certify that the above student has successfully completed the course requirements and training program conducted by ATS CRM.
            </div>
            <div class="program"><?= h($student['program_name'] ?: '-') ?></div>

            <div class="meta-grid">
                <div class="meta-card">
                    <div class="meta-label">Registration No</div>
                    <div class="meta-value"><?= h($student['registration_no'] ?: '-') ?></div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Batch</div>
                    <div class="meta-value"><?= h($student['batch_name'] ?: '-') ?></div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Issued On</div>
                    <div class="meta-value"><?= h(date('d-m-Y', strtotime($issuedAt))) ?></div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Qualification</div>
                    <div class="meta-value"><?= h($student['qualification'] ?: '-') ?></div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">College</div>
                    <div class="meta-value"><?= h($student['college_name'] ?: '-') ?></div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Paid Status</div>
                    <div class="meta-value"><?= h(ucfirst((string) ($student['payment_status'] ?? '-'))) ?></div>
                </div>
            </div>

            <div class="footer-row">
                <div class="signature">Authorized Signatory</div>
                <div class="seal">ATS CRM<br>Certified</div>
            </div>
        </div>
    </div>
</body>
</html>
