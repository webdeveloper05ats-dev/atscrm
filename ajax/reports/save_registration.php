<?php
require_once '../../config/app.php';
require_once ROOT_PATH . '/core/helper.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit;
}

function reportRegistrationMailKey(array $row): string
{
    return strtolower(trim((string)($row['name'] ?? '')))
        . '|'
        . preg_replace('/\D+/', '', (string)($row['contact_no'] ?? ''))
        . '|'
        . strtolower(trim((string)($row['course'] ?? '')))
        . '|'
        . trim((string)($row['date_of_reg'] ?? ''));
}

function reportRegistrationH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function reportRegistrationFindRecipients(PDO $pdo, string $name, string $contactNo): array
{
    $name = trim($name);
    $phone = preg_replace('/\D+/', '', $contactNo);
    $recipients = [];

    if ($name === '' && $phone === '') {
        return [];
    }

    try {
        $parentEmailSelect = crmBuildParentEmailFallbackSelect($pdo, 'rp', 'e');
        $conditions = [];
        $params = [];

        if ($phone !== '') {
            $conditions[] = "(REPLACE(REPLACE(REPLACE(COALESCE(r.enquiry_snapshot_phone, ''), ' ', ''), '-', ''), '+91', '') = ?
                OR REPLACE(REPLACE(REPLACE(COALESCE(e.phone, ''), ' ', ''), '-', ''), '+91', '') = ?
                OR REPLACE(REPLACE(REPLACE(COALESCE(rp.parent_phone, ''), ' ', ''), '-', ''), '+91', '') = ?
                OR REPLACE(REPLACE(REPLACE(COALESCE(e.father_contact_no, ''), ' ', ''), '-', ''), '+91', '') = ?)";
            $params = array_merge($params, [$phone, $phone, $phone, $phone]);
        }

        if ($name !== '') {
            $nameKey = strtolower($name);
            $conditions[] = "(LOWER(COALESCE(r.enquiry_snapshot_name, '')) = ?
                OR LOWER(COALESCE(rp.student_name, '')) = ?
                OR LOWER(COALESCE(e.name, '')) = ?)";
            $params = array_merge($params, [$nameKey, $nameKey, $nameKey]);
        }

        if ($conditions) {
            $sql = "
                SELECT
                    COALESCE(r.enquiry_snapshot_name, rp.student_name, e.name) AS student_name,
                    COALESCE(r.enquiry_snapshot_email, e.email) AS student_email,
                    COALESCE(rp.parent_name, e.father_name) AS parent_name,
                    $parentEmailSelect AS parent_email
                FROM registrations r
                LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
                LEFT JOIN enquiries e ON e.id = r.enquiry_id
                WHERE " . implode(' OR ', $conditions) . "
                ORDER BY r.id DESC
                LIMIT 1
            ";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $studentName = trim((string)($row['student_name'] ?? $name));
                $parentName = trim((string)($row['parent_name'] ?? 'Parent'));
                $recipients[] = ['email' => (string)($row['student_email'] ?? ''), 'name' => $studentName];
                $recipients[] = ['email' => (string)($row['parent_email'] ?? ''), 'name' => $parentName !== '' ? $parentName : 'Parent'];
                return crmUniqueEmailRecipients($recipients);
            }
        }
    } catch (Exception $e) {
        // Fall through to lighter lookup below.
    }

    try {
        $conditions = [];
        $params = [];

        if ($phone !== '') {
            $conditions[] = "(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '+91', '') = ?
                OR REPLACE(REPLACE(REPLACE(COALESCE(father_contact_no, ''), ' ', ''), '-', ''), '+91', '') = ?)";
            $params = array_merge($params, [$phone, $phone]);
        }

        if ($name !== '') {
            $conditions[] = "LOWER(COALESCE(name, '')) = ?";
            $params[] = strtolower($name);
        }

        if ($conditions) {
            $st = $pdo->prepare("
                SELECT name, email, father_name, parent_email
                FROM enquiries
                WHERE " . implode(' OR ', $conditions) . "
                ORDER BY id DESC
                LIMIT 1
            ");
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $recipients[] = ['email' => (string)($row['email'] ?? ''), 'name' => trim((string)($row['name'] ?? $name))];
                $recipients[] = ['email' => (string)($row['parent_email'] ?? ''), 'name' => trim((string)($row['father_name'] ?? 'Parent')) ?: 'Parent'];
                return crmUniqueEmailRecipients($recipients);
            }
        }
    } catch (Exception $e) {
        // Fall through to contacts lookup.
    }

    try {
        $conditions = [];
        $params = [];

        if ($phone !== '') {
            $conditions[] = "REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '+91', '') = ?";
            $params[] = $phone;
        }

        if ($name !== '') {
            $conditions[] = "LOWER(COALESCE(name, '')) = ?";
            $params[] = strtolower($name);
        }

        if ($conditions) {
            $st = $pdo->prepare("
                SELECT name, email
                FROM contacts_master
                WHERE " . implode(' OR ', $conditions) . "
                ORDER BY id DESC
                LIMIT 1
            ");
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $recipients[] = ['email' => (string)($row['email'] ?? ''), 'name' => trim((string)($row['name'] ?? $name))];
            }
        }
    } catch (Exception $e) {
        return [];
    }

    return crmUniqueEmailRecipients($recipients);
}

function reportRegistrationSendEmail(PDO $pdo, array $row, ?string &$mailError = null): bool
{
    $mailError = null;

    $name = trim((string)($row['name'] ?? ''));
    $recipients = reportRegistrationFindRecipients($pdo, $name, (string)($row['contact_no'] ?? ''));

    if (!$recipients) {
        $mailError = 'No matching student/parent email found for ' . ($name !== '' ? $name : 'this registration row') . '.';
        crmLogMailEvent('Skipped report registration mail: ' . $mailError);
        return false;
    }

    $subject = 'Registration recorded for ' . ($name !== '' ? $name : 'student');
    $htmlBody = '
        <p>Dear Student and Parent,</p>
        <p>A registration entry has been recorded successfully.</p>
        <p><strong>Student:</strong> ' . reportRegistrationH($name) . '<br>
        <strong>Department:</strong> ' . reportRegistrationH($row['department'] ?? '') . '<br>
        <strong>College:</strong> ' . reportRegistrationH($row['college'] ?? '') . '<br>
        <strong>Course:</strong> ' . reportRegistrationH($row['course'] ?? '') . '<br>
        <strong>Date:</strong> ' . reportRegistrationH($row['date_of_reg'] ?? '') . '<br>
        <strong>Billing:</strong> ' . reportRegistrationH(number_format((float)($row['billing'] ?? 0), 2)) . '<br>
        <strong>Collection:</strong> ' . reportRegistrationH(number_format((float)($row['collection'] ?? 0), 2)) . '<br>
        <strong>Balance:</strong> ' . reportRegistrationH(number_format((float)($row['balance'] ?? 0), 2)) . '<br>
        <strong>Payment Mode:</strong> ' . reportRegistrationH($row['payment_mode'] ?? '') . '</p>
        <p>Regards,<br>' . reportRegistrationH(APP_NAME) . '</p>';
    $textBody = "Dear Student and Parent,\n\n"
        . "A registration entry has been recorded successfully.\n"
        . "Student: {$name}\n"
        . "Department: " . (string)($row['department'] ?? '') . "\n"
        . "College: " . (string)($row['college'] ?? '') . "\n"
        . "Course: " . (string)($row['course'] ?? '') . "\n"
        . "Date: " . (string)($row['date_of_reg'] ?? '') . "\n"
        . "Billing: " . number_format((float)($row['billing'] ?? 0), 2) . "\n"
        . "Collection: " . number_format((float)($row['collection'] ?? 0), 2) . "\n"
        . "Balance: " . number_format((float)($row['balance'] ?? 0), 2) . "\n"
        . "Payment Mode: " . (string)($row['payment_mode'] ?? '') . "\n\n"
        . "Regards,\n" . APP_NAME;

    return crmSendEmail($recipients, $subject, $htmlBody, $textBody, $mailError);
}

try {

    $reportId = (int)($_POST['report_id'] ?? 0);
    $userId   = (int)$_SESSION['user_id'];

    if (!$reportId) throw new Exception("Invalid report");

    // Verify report ownership
    $ownerCheck = $pdo->prepare("SELECT id FROM reports WHERE id = ? AND user_id = ? LIMIT 1");
    $ownerCheck->execute([$reportId, $userId]);
    if (!$ownerCheck->fetchColumn()) {
        throw new Exception("Access denied. You can only modify your own reports.");
    }

    $existingKeys = [];
    $existingRows = $pdo->prepare("SELECT name, contact_no, course, date_of_reg FROM report_registrations WHERE report_id=?");
    $existingRows->execute([$reportId]);
    foreach ($existingRows->fetchAll(PDO::FETCH_ASSOC) as $existingRow) {
        $existingKeys[reportRegistrationMailKey($existingRow)] = true;
    }

    // DELETE OLD
    $pdo->prepare("DELETE FROM report_registrations WHERE report_id=?")->execute([$reportId]);

    $names = $_POST['name'] ?? [];
    $mailWarnings = [];

    foreach ($names as $i => $name) {

        $row = [
            'name' => trim((string)$name),
            'department' => $_POST['department'][$i] ?? '',
            'contact_no' => $_POST['contact_no'][$i] ?? '',
            'college' => $_POST['college'][$i] ?? '',
            'date_of_reg' => $_POST['date_of_reg'][$i] ?? null,
            'course' => $_POST['course'][$i] ?? '',
            'billing' => $_POST['billing'][$i] ?? 0,
            'collection' => $_POST['collection'][$i] ?? 0,
            'balance' => $_POST['balance'][$i] ?? 0,
            'payment_mode' => $_POST['payment_mode'][$i] ?? ''
        ];

        if ($row['name'] === '') continue;

        $stmt = $pdo->prepare("
            INSERT INTO report_registrations
            (report_id,name,department,contact_no,college,date_of_reg,course,billing,collection,balance,payment_mode)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            $reportId,
            $row['name'],
            $row['department'],
            $row['contact_no'],
            $row['college'],
            $row['date_of_reg'],
            $row['course'],
            $row['billing'],
            $row['collection'],
            $row['balance'],
            $row['payment_mode']
        ]);

        $mailKey = reportRegistrationMailKey($row);
        if (!isset($existingKeys[$mailKey])) {
            $mailError = null;
            if (!reportRegistrationSendEmail($pdo, $row, $mailError) && $mailError) {
                $mailWarnings[] = $mailError;
            }
        }
    }

    $message = 'Registration saved successfully';
    if ($mailWarnings) {
        $message .= '. Email warning: ' . implode(' | ', array_slice($mailWarnings, 0, 3));
    }

    echo json_encode([
        'status'=>'success',
        'message'=>$message
    ]);

} catch (Exception $e) {
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
