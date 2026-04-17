<?php
if (!defined('APP_NAME')) {
    die('Unauthorized access.');
}

$roleName = trim((string)($_SESSION['role_name'] ?? 'Staff'));
$downloadType = strtolower(trim((string)($_GET['download'] ?? 'pdf')));
if ($downloadType !== 'html') {
    $downloadType = 'pdf';
}

$roleFlowDocs = [
    'super_admin' => [
        'label' => 'Super Admin',
        'goal' => 'Govern system quality, access controls, backup readiness, and risk visibility.',
        'flow' => [
            'Review dashboard alerts and unresolved exceptions.',
            'Verify branch/user/role/menu/permission integrity.',
            'Review audit logs for unusual updates or deletions.',
            'Run Backup & Health before major operations.',
            'Approve escalations and close branch-level blockers.',
        ],
        'handoff' => 'Share critical findings with Super Admin/HR owners and track closure.',
    ],
    'hr' => [
        'label' => 'HR',
        'goal' => 'Own lead lifecycle, manage targets, and handle post-course student follow-up.',
        'flow' => [
            'Create leads with complete details and assign the right owner.',
            'Follow leads daily and update status and next follow-up date.',
            'Set monthly targets for Front Office, HR, Marketing, and Corporate users.',
            'Track target progress role-wise and push closure for pending teams.',
            'After Staff completes course and mock interview, continue HR process.',
            'Track payment, interview help, and full interview details.',
            'Generate certificate after required checks are complete.',
        ],
        'handoff' => 'Pass lead/student status with last update, next action, and owner details.',
    ],
    'marketing' => [
        'label' => 'Marketing',
        'goal' => 'Generate qualified pipeline and keep engagement fresh.',
        'flow' => [
            'Capture new leads with complete contact/source context.',
            'Convert warm leads to enquiry with proper notes.',
            'Schedule and update follow-up outcomes daily.',
            'Track assigned targets and conversion trend.',
            'Flag hot leads for immediate registration support.',
        ],
        'handoff' => 'Share conversion-ready cases with Front Office/HR.',
    ],
    'corporate' => [
        'label' => 'Corporate Executive',
        'goal' => 'Handle assigned lead follow-up and keep daily report updates complete.',
        'flow' => [
            'Open assigned leads and follow up by due date.',
            'Update each call outcome and next action clearly.',
            'Move interested leads to next stage quickly.',
            'Coordinate conversion-ready leads with HR and Front Office.',
            'Update daily report sections and keep activity trace clear.',
            'Track assigned targets and close pending actions before day end.',
        ],
        'handoff' => 'Share lead status, last response, and next action date with HR/Front Office.',
    ],
    'front_office' => [
        'label' => 'Front Office',
        'goal' => 'Ensure clean intake and timely registration movement.',
        'flow' => [
            'Handle walk-ins/calls and capture enquiry details correctly.',
            'Validate contact/program/payment expectation fields.',
            'Create/assist registration draft for eligible candidates.',
            'Coordinate pending document/payment follow-ups.',
            'Update status so next team can act without delay.',
        ],
        'handoff' => 'Handoff complete profiles to Super Admin/HR for closure.',
    ],
    'staff' => [
        'label' => 'Staff',
        'goal' => 'Maintain reliable updates in assigned modules.',
        'flow' => [
            'Work assigned queue and update record status accurately.',
            'Complete follow-up notes before shift end.',
            'Escalate blocked items with clear reasons.',
            'Avoid stale records by confirming next action date.',
        ],
        'handoff' => 'Report unresolved tasks to module owner daily.',
    ],
];

$requestedRole = strtolower(trim((string)($_GET['role'] ?? '')));
if (!isset($roleFlowDocs[$requestedRole])) {
    $requestedRole = '';
}

$sections = [];
if ($requestedRole !== '') {
    $sections[$requestedRole] = $roleFlowDocs[$requestedRole];
} else {
    $sections = $roleFlowDocs;
}

$today = date('d M Y');
$sopHtml = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ATS CRM Onboarding SOP</title>
    <style>
        body{font-family:DejaVu Sans, Arial, sans-serif;color:#1f2937;font-size:13px;line-height:1.5;margin:0;padding:24px;background:#fff;}
        .hero{border:1px solid #efcada;border-radius:12px;padding:14px 16px;background:#fff7fb;margin-bottom:12px;}
        h1{margin:0;color:#be185d;font-size:22px;}
        .sub{margin-top:5px;color:#6b7280;}
        h2{font-size:15px;color:#1f2a44;margin:16px 0 8px;}
        table{width:100%;border-collapse:collapse;margin-top:8px;}
        th,td{border:1px solid #f0d6e2;padding:8px 10px;vertical-align:top;}
        th{background:#fff3f8;color:#9a2f60;font-size:12px;text-transform:uppercase;letter-spacing:.03em;}
        ul{margin:0;padding-left:18px;}
        .small{color:#6b7280;font-size:12px;margin-top:12px;}
    </style>
</head>
<body>
    <div class="hero">
        <h1>ATS CRM Onboarding SOP</h1>
        <div class="sub">Generated: ' . htmlspecialchars($today) . ' | Current Role: ' . htmlspecialchars($roleName) . '</div>
    </div>

    <h2>Common Daily Operations Checklist</h2>
    <ul>
        <li>Capture lead/enquiry updates immediately after interaction.</li>
        <li>Close follow-ups due today and update next action.</li>
        <li>Convert qualified enquiry to draft/registration with complete details.</li>
        <li>Keep payment and target status synchronized.</li>
        <li>Close shift/day with report and pending review.</li>
    </ul>

    <h2>Access Matrix</h2>
    <table>
        <thead>
            <tr>
                <th>Feature</th>
                <th>Super Admin</th>
                <th>HR</th>
                <th>Front Office/Marketing/Corporate/Staff</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Onboarding Guide</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
            <tr><td>Audit Logs</td><td>Yes</td><td>Based on assigned permissions</td><td>Based on assigned permissions</td></tr>
            <tr><td>Backup & Health</td><td>Yes</td><td>No</td><td>No</td></tr>
        </tbody>
    </table>

    <h2>Role Playbook (Quick)</h2>
    <table>
        <thead>
            <tr><th>Role</th><th>Primary Focus</th></tr>
        </thead>
        <tbody>
            <tr><td>Super Admin</td><td>System control, permission mapping, and full monitoring.</td></tr>
            <tr><td>HR</td><td>Lead lifecycle, target management, and post-course support.</td></tr>
            <tr><td>Front Office</td><td>Walk-in/call intake and enquiry-to-registration movement.</td></tr>
            <tr><td>Staff</td><td>Student progress updates, mock interview, and send-to-HR flow.</td></tr>
            <tr><td>Marketing</td><td>Lead generation, follow-up quality, and conversion readiness.</td></tr>
            <tr><td>Corporate Executive</td><td>Assigned lead follow-up and daily report discipline.</td></tr>
        </tbody>
    </table>

    <h2>Role-Wise CRM Flows</h2>';

foreach ($sections as $roleKey => $section) {
    $sopHtml .= '
    <table>
        <thead>
            <tr><th colspan="2">' . htmlspecialchars((string)$section['label']) . '</th></tr>
        </thead>
        <tbody>
            <tr><td style="width:140px;"><strong>Goal</strong></td><td>' . htmlspecialchars((string)$section['goal']) . '</td></tr>
            <tr><td><strong>Flow</strong></td><td><ul>';
    foreach (($section['flow'] ?? []) as $step) {
        $sopHtml .= '<li>' . htmlspecialchars((string)$step) . '</li>';
    }
    $sopHtml .= '</ul></td></tr>
            <tr><td><strong>Handoff</strong></td><td>' . htmlspecialchars((string)$section['handoff']) . '</td></tr>
        </tbody>
    </table>';
}

$sopHtml .= '
    <div class="small">Generated by ATS CRM Onboarding module.</div>
</body>
</html>';

if ($downloadType === 'html') {
    $namePart = $requestedRole !== '' ? $requestedRole : 'all-roles';
    $filename = 'ats-crm-onboarding-sop-' . $namePart . '-' . date('Ymd-His') . '.html';
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $sopHtml;
    exit;
}

$autoloadPath = ROOT_PATH . '/vendor/autoload.php';
if (!class_exists(\Dompdf\Dompdf::class) && is_file($autoloadPath)) {
    require_once $autoloadPath;
}

if (!class_exists(\Dompdf\Dompdf::class)) {
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo $sopHtml;
    exit;
}

try {
    $dompdf = new \Dompdf\Dompdf([
        'isRemoteEnabled' => false,
        'isHtml5ParserEnabled' => true,
        'defaultFont' => 'DejaVu Sans',
    ]);
    $dompdf->loadHtml($sopHtml, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdfOutput = $dompdf->output();
    $namePart = $requestedRole !== '' ? $requestedRole : 'all-roles';
    $filename = 'ats-crm-onboarding-sop-' . $namePart . '-' . date('Ymd-His') . '.pdf';
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string)strlen($pdfOutput));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $pdfOutput;
    exit;
} catch (Throwable $e) {
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo $sopHtml;
    exit;
}
