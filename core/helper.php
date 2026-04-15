<?php
// ============================================
// ATS CRM - Helper Functions
// ============================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/


function redirect(string $url): void
{
    // If output already started, do JS redirect (safe fallback)
    if (headers_sent($file, $line)) {
        echo "<script>window.location.href=" . json_encode($url) . ";</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'></noscript>";
        exit;
    }

    header("Location: " . $url);
    exit;
}

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

function setFlash($key, $message)
{
    $_SESSION['flash'][$key] = $message;
}

function getFlash($key)
{
    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    return null;
}

/*
|--------------------------------------------------------------------------
| Sanitize Input
|--------------------------------------------------------------------------
*/

function sanitize($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| JSON Response
|--------------------------------------------------------------------------
*/

function responseJson($status, $message, $data = [])
{
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Generate Unique Code
|--------------------------------------------------------------------------
*/

function generateUniqueCode($prefix = '')
{
    return $prefix . strtoupper(uniqid());
}

/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

function formatDate($date, $format = 'd M Y')
{
    if (!$date)
        return '';
    return date($format, strtotime($date));
}

/*
|--------------------------------------------------------------------------
| File Upload
|--------------------------------------------------------------------------
*/

function uploadFile($file, $destinationFolder)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $fileName = $file['name'];
    $fileTmp = $file['tmp_name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedTypes)) {
        return false;
    }

    $newName = time() . '_' . rand(1000, 9999) . '.' . $ext;
    $uploadPath = __DIR__ . '/../uploads/' . $destinationFolder . '/' . $newName;

    if (!is_dir(dirname($uploadPath))) {
        mkdir(dirname($uploadPath), 0755, true);
    }

    if (move_uploaded_file($fileTmp, $uploadPath)) {
        return $newName;
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

function generateCSRF()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCSRF($token)
{
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        // Rotate token after successful verification to prevent replay
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) && empty($_POST['ajax_save_attendance'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return true;
    }
    return false;
}

function isStaffRestrictedFromStudentContacts(): bool
{
    $roleName = strtolower(trim((string) ($_SESSION['role_name'] ?? '')));
    return $roleName === 'staff';
}

function visibleStudentContactValue($value, string $fallback = '-'): string
{
    if (isStaffRestrictedFromStudentContacts()) {
        return $fallback;
    }

    $value = trim((string) $value);
    return $value !== '' ? $value : $fallback;
}

function visibleStudentContactPair($phone = null, $email = null, string $fallback = ''): string
{
    if (isStaffRestrictedFromStudentContacts()) {
        return $fallback;
    }

    $parts = [];
    $phone = trim((string) $phone);
    $email = trim((string) $email);

    if ($phone !== '') {
        $parts[] = $phone;
    }
    if ($email !== '') {
        $parts[] = $email;
    }

    return !empty($parts) ? implode(' | ', $parts) : $fallback;
}

function crmTableExists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    $key = strtolower($tableName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        // SHOW TABLES LIKE with placeholders can be inconsistent across drivers.
        // information_schema is more reliable for exact table existence checks.
        $st = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $st->execute([$tableName]);
        $cache[$key] = ((int) $st->fetchColumn() > 0);
    } catch (Exception $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function crmColumnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = strtolower($tableName . '.' . $columnName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$tableName` LIKE ?");
        $st->execute([$columnName]);
        $cache[$key] = (bool) $st->fetchColumn();
    } catch (Exception $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function crmEnsureRegistrationProfileParentEmailColumn(PDO $pdo): bool
{
    if (!crmTableExists($pdo, 'registration_profiles')) {
        return false;
    }

    if (crmColumnExists($pdo, 'registration_profiles', 'parent_email')) {
        return true;
    }

    try {
        $pdo->exec("ALTER TABLE `registration_profiles` ADD COLUMN `parent_email` varchar(150) DEFAULT NULL AFTER `parent_occupation`");
        return true;
    } catch (Exception $e) {
        return crmColumnExists($pdo, 'registration_profiles', 'parent_email');
    }
}

function crmBuildRegistrationProfileParentEmailSelect(PDO $pdo, string $alias = 'rp'): string
{
    return crmColumnExists($pdo, 'registration_profiles', 'parent_email')
        ? "$alias.parent_email"
        : "NULL";
}

function crmBuildParentEmailFallbackSelect(PDO $pdo, string $profileAlias = 'rp', string $enquiryAlias = 'e'): string
{
    $profileEmail = crmBuildRegistrationProfileParentEmailSelect($pdo, $profileAlias);
    if ($profileEmail === 'NULL') {
        return "$enquiryAlias.parent_email";
    }

    return "COALESCE($profileEmail, $enquiryAlias.parent_email)";
}

function crmNormalizeEmail(?string $email): string
{
    return strtolower(trim((string) $email));
}

function crmUniqueEmailRecipients(array $recipients): array
{
    $unique = [];
    $seen = [];

    foreach ($recipients as $recipient) {
        $email = crmNormalizeEmail($recipient['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if (isset($seen[$email])) {
            continue;
        }

        $seen[$email] = true;
        $unique[] = [
            'email' => $email,
            'name' => trim((string) ($recipient['name'] ?? '')),
        ];
    }

    return $unique;
}

function crmEnsureLogDirectory(): void
{
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
}

function crmLogMailEvent(string $message): void
{
    crmEnsureLogDirectory();
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(LOG_PATH . 'mail.log', $line, FILE_APPEND);
}

function crmBuildSmtpAttempts(): array
{
    $host = trim((string) SMTP_HOST);
    $ipv4Host = $host !== '' ? gethostbyname($host) : '';
    $attempts = [];

    $attempts[] = [
        'label' => 'primary',
        'host' => $host,
        'port' => (int) SMTP_PORT,
        'secure' => trim((string) SMTP_ENCRYPTION),
        'auto_tls' => true,
    ];

    if ($ipv4Host !== '' && $ipv4Host !== $host) {
        $attempts[] = [
            'label' => 'ipv4',
            'host' => $ipv4Host,
            'port' => (int) SMTP_PORT,
            'secure' => trim((string) SMTP_ENCRYPTION),
            'auto_tls' => true,
        ];
    }

    if ((int) SMTP_PORT === 465) {
        $attempts[] = [
            'label' => 'tls587',
            'host' => $host,
            'port' => 587,
            'secure' => 'tls',
            'auto_tls' => true,
        ];

        if ($ipv4Host !== '' && $ipv4Host !== $host) {
            $attempts[] = [
                'label' => 'ipv4-tls587',
                'host' => $ipv4Host,
                'port' => 587,
                'secure' => 'tls',
                'auto_tls' => true,
            ];
        }
    }

    return $attempts;
}

function crmEmailPreviewText(string $htmlBody): string
{
    $plain = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
    $plain = preg_replace('/\s+/', ' ', $plain ?? '');
    $plain = trim((string) $plain);

    if ($plain === '') {
        return APP_NAME . ' update';
    }

    return function_exists('mb_substr')
        ? mb_substr($plain, 0, 140)
        : substr($plain, 0, 140);
}

function crmWrapEmailHtml(string $subject, string $htmlBody): string
{
    if (stripos($htmlBody, '<html') !== false) {
        return $htmlBody;
    }

    $appName = htmlspecialchars((string) APP_NAME, ENT_QUOTES, 'UTF-8');
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $previewText = htmlspecialchars(crmEmailPreviewText($htmlBody), ENT_QUOTES, 'UTF-8');
    $year = date('Y');

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>' . $safeSubject . '</title>
    <style>
        body{
            margin:0;
            padding:0;
            background:#fff7fa;
            color:#333333;
            font-family:Poppins,Segoe UI,Arial,sans-serif;
            -webkit-font-smoothing:antialiased;
        }
        table{
            border-collapse:collapse;
            border-spacing:0;
        }
        img{
            border:0;
            outline:none;
            text-decoration:none;
            max-width:100%;
        }
        .crm-email-shell{
            width:100%;
            background:
                radial-gradient(circle at top left, rgba(233,30,99,0.12), transparent 24%),
                radial-gradient(circle at top right, rgba(233,30,99,0.08), transparent 22%),
                #fff7fa;
            padding:32px 12px;
        }
        .crm-email-card{
            width:100%;
            max-width:680px;
            margin:0 auto;
            background:#ffffff;
            border:1px solid #f3c6d3;
            border-radius:24px;
            overflow:hidden;
            box-shadow:0 18px 42px rgba(233,30,99,0.10);
        }
        .crm-email-hero{
            padding:30px 32px 24px;
            background:
                linear-gradient(135deg, rgba(233,30,99,0.16), rgba(255,255,255,0.96)),
                linear-gradient(180deg, #ffffff 0%, #fff3f8 100%);
            border-bottom:1px solid #f7d7e4;
        }
        .crm-email-kicker{
            display:inline-block;
            margin:0 0 14px;
            padding:7px 12px;
            border-radius:999px;
            background:#ffffff;
            border:1px solid rgba(233,30,99,0.18);
            color:#c2185b;
            font-size:11px;
            font-weight:800;
            letter-spacing:0.08em;
            text-transform:uppercase;
        }
        .crm-email-brand{
            margin:0 0 8px;
            color:#e91e63;
            font-size:28px;
            font-weight:800;
            line-height:1.15;
        }
        .crm-email-subject{
            margin:0;
            color:#2f1c28;
            font-size:22px;
            font-weight:800;
            line-height:1.3;
        }
        .crm-email-copy{
            margin:12px 0 0;
            color:#6f6170;
            font-size:13px;
            line-height:1.7;
        }
        .crm-email-body{
            padding:28px 32px 12px;
            font-size:14px;
            line-height:1.75;
            color:#334155;
        }
        .crm-email-body h1,
        .crm-email-body h2,
        .crm-email-body h3,
        .crm-email-body h4{
            margin:0 0 12px;
            color:#2f1c28;
            line-height:1.3;
        }
        .crm-email-body p{
            margin:0 0 14px;
        }
        .crm-email-body ul,
        .crm-email-body ol{
            margin:0 0 16px;
            padding-left:20px;
        }
        .crm-email-body li{
            margin-bottom:8px;
        }
        .crm-email-body strong{
            color:#2f1c28;
        }
        .crm-email-body a{
            color:#c2185b;
            font-weight:700;
            text-decoration:none;
        }
        .crm-email-body table{
            width:100%;
            margin:18px 0;
            overflow:hidden;
            border:1px solid #f3d6e1;
            border-radius:16px;
            background:#ffffff;
        }
        .crm-email-body th{
            padding:12px 14px;
            background:#fff1f6;
            color:#7a294c;
            font-size:12px;
            font-weight:800;
            letter-spacing:0.04em;
            text-transform:uppercase;
            text-align:left;
            border-bottom:1px solid #f3d6e1;
        }
        .crm-email-body td{
            padding:12px 14px;
            color:#475569;
            border-bottom:1px solid #f8e4ec;
        }
        .crm-email-body tr:last-child td{
            border-bottom:none;
        }
        .crm-email-body blockquote{
            margin:18px 0;
            padding:14px 16px;
            border-left:4px solid #e91e63;
            background:#fff5f9;
            color:#5b4b58;
            border-radius:0 14px 14px 0;
        }
        .crm-email-panel,
        .crm-email-note{
            margin:18px 0;
            padding:16px 18px;
            border-radius:18px;
            border:1px solid #f3d6e1;
            background:linear-gradient(180deg,#ffffff 0%,#fff8fb 100%);
        }
        .crm-email-highlight{
            margin:18px 0;
            padding:18px 20px;
            border-radius:20px;
            background:linear-gradient(135deg,#fff0f6,#ffffff);
            border:1px solid #f3c6d3;
            box-shadow:0 10px 24px rgba(233,30,99,0.08);
        }
        .crm-email-badge{
            display:inline-block;
            margin:0 8px 8px 0;
            padding:6px 10px;
            border-radius:999px;
            background:#fff0f5;
            border:1px solid #f4c9d7;
            color:#be185d;
            font-size:11px;
            font-weight:800;
            letter-spacing:0.03em;
            text-transform:uppercase;
        }
        .crm-email-button{
            display:inline-block;
            margin:10px 0 14px;
            padding:12px 20px;
            border-radius:999px;
            background:linear-gradient(135deg,#ff4d8d,#e91e63);
            color:#ffffff !important;
            font-size:13px;
            font-weight:800;
            text-decoration:none;
            box-shadow:0 14px 26px rgba(233,30,99,0.22);
        }
        .crm-email-meta{
            margin:18px 0;
            padding:14px 16px;
            border-radius:16px;
            background:#f8fafc;
            border:1px solid #e5e7eb;
            color:#64748b;
            font-size:12px;
            line-height:1.7;
        }
        .crm-email-divider{
            height:1px;
            margin:22px 0;
            background:linear-gradient(90deg,rgba(233,30,99,0),rgba(233,30,99,0.18),rgba(233,30,99,0));
        }
        .crm-email-footer{
            padding:0 32px 30px;
            color:#7b8190;
            font-size:12px;
            line-height:1.7;
        }
        .crm-email-footer-card{
            padding:16px 18px;
            border-radius:18px;
            background:#fff8fb;
            border:1px solid #f3d6e1;
        }
        @media only screen and (max-width: 640px){
            .crm-email-shell{
                padding:18px 8px;
            }
            .crm-email-hero,
            .crm-email-body,
            .crm-email-footer{
                padding-left:20px !important;
                padding-right:20px !important;
            }
            .crm-email-brand{
                font-size:24px;
            }
            .crm-email-subject{
                font-size:20px;
            }
        }
    </style>
</head>
<body>
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">' . $previewText . '</div>
    <div class="crm-email-shell">
        <div class="crm-email-card">
            <div class="crm-email-hero">
                <div class="crm-email-kicker">ATS CRM Notification</div>
                <div class="crm-email-brand">' . $appName . '</div>
                <h1 class="crm-email-subject">' . $safeSubject . '</h1>
            </div>
            <div class="crm-email-body">
                ' . $htmlBody . '
            </div>
            <div class="crm-email-footer">
                <div class="crm-email-footer-card">
                    <strong style="color:#2f1c28;">' . $appName . '</strong><br>
                    Student lifecycle updates, payments, interviews, certificates, and communication in one place.<br>
                    &copy; ' . $year . ' ' . $appName . '
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
}

function crmSendEmail(array $recipients, string $subject, string $htmlBody, string $textBody = '', ?string &$errorMessage = null, array $attachments = []): bool
{
    $errorMessage = null;
    $recipients = crmUniqueEmailRecipients($recipients);
    if (!$recipients) {
        $errorMessage = 'No valid recipient email address found.';
        crmLogMailEvent('Skipped mail "' . $subject . '": ' . $errorMessage);
        return false;
    }

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        require_once ROOT_PATH . '/PHPMailer/src/Exception.php';
        require_once ROOT_PATH . '/PHPMailer/src/PHPMailer.php';
        require_once ROOT_PATH . '/PHPMailer/src/SMTP.php';
    }

    $failedRecipients = [];
    $wrappedHtmlBody = crmWrapEmailHtml($subject, $htmlBody);
    $generatedTextBody = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $wrappedHtmlBody)));

    foreach ($recipients as $recipient) {
        $recipientSent = false;
        $attemptErrors = [];

        foreach (crmBuildSmtpAttempts() as $attempt) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $attempt['host'];
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->Port = (int) $attempt['port'];
                $mail->SMTPSecure = $attempt['secure'] === 'smtps'
                    ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : ($attempt['secure'] === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : $attempt['secure']);
                $mail->SMTPAutoTLS = (bool) ($attempt['auto_tls'] ?? true);
                $mail->Timeout = 30;
                $mail->CharSet = 'UTF-8';
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
                $mail->setFrom(SMTP_USERNAME, APP_NAME);
                $mail->addAddress($recipient['email'], $recipient['name']);
                foreach ($attachments as $attachment) {
                    $content = (string) ($attachment['content'] ?? '');
                    $filename = trim((string) ($attachment['filename'] ?? 'attachment.txt'));
                    $mimeType = trim((string) ($attachment['mime_type'] ?? 'application/octet-stream'));
                    if ($content === '' || $filename === '') {
                        continue;
                    }
                    $mail->addStringAttachment($content, $filename, \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64, $mimeType);
                }
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $wrappedHtmlBody;
                $mail->AltBody = $generatedTextBody;
                $mail->send();
                crmLogMailEvent(
                    'Sent mail "' . $subject . '" to ' . $recipient['email']
                    . ' via ' . $attempt['label'] . ' (' . $attempt['host'] . ':' . $attempt['port'] . ', ' . $attempt['secure'] . ')'
                );
                $recipientSent = true;
                break;
            } catch (Exception $e) {
                $attemptErrors[] = $attempt['label'] . ': ' . $e->getMessage();
            }
        }

        if (!$recipientSent) {
            $failedRecipients[] = $recipient['email'] . ' [' . implode(' | ', $attemptErrors) . ']';
            crmLogMailEvent('Failed mail "' . $subject . '" to ' . $recipient['email'] . ': ' . implode(' | ', $attemptErrors));
        }
    }

    if (!$failedRecipients) {
        return true;
    }

    $errorMessage = 'Failed recipients: ' . implode(' ; ', $failedRecipients);
    error_log('CRM mail send failed: ' . $errorMessage);
    return false;
}

function crmCourseCertificateDirectory(): string
{
    $dir = UPLOAD_PATH . 'course_certificates/generated/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function crmCourseCertificateSnapshotRelativePath(int $registrationId): string
{
    return 'uploads/course_certificates/generated/course_certificate_' . $registrationId . '.json';
}

function crmCourseCertificateSnapshotAbsolutePath(int $registrationId): string
{
    return crmCourseCertificateDirectory() . 'course_certificate_' . $registrationId . '.json';
}

function crmCourseCertificateSnapshotExists(int $registrationId): bool
{
    if ($registrationId <= 0) {
        return false;
    }

    return is_file(crmCourseCertificateSnapshotAbsolutePath($registrationId));
}

function crmLoadCourseCertificateSnapshot(int $registrationId): ?array
{
    if (!crmCourseCertificateSnapshotExists($registrationId)) {
        return null;
    }

    $raw = @file_get_contents(crmCourseCertificateSnapshotAbsolutePath($registrationId));
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function crmSaveCourseCertificateSnapshot(int $registrationId, array $snapshot): bool
{
    if ($registrationId <= 0) {
        return false;
    }
    $snapshot['registration_id'] = $registrationId;
    if (empty($snapshot['stored_at'])) {
        $snapshot['stored_at'] = date('Y-m-d H:i:s');
    }
    $snapshot['updated_at'] = date('Y-m-d H:i:s');

    $encoded = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }

    return file_put_contents(crmCourseCertificateSnapshotAbsolutePath($registrationId), $encoded, LOCK_EX) !== false;
}

function crmCourseCertificateSnapshotViewConsumed(int $registrationId): bool
{
    $snapshot = crmLoadCourseCertificateSnapshot($registrationId);
    if ($snapshot === null) {
        return false;
    }

    return trim((string) ($snapshot['view_consumed_at'] ?? '')) !== '';
}

function crmMarkCourseCertificateSnapshotViewed(int $registrationId): bool
{
    $snapshot = crmLoadCourseCertificateSnapshot($registrationId);
    if ($snapshot === null) {
        return false;
    }
    if (trim((string) ($snapshot['view_consumed_at'] ?? '')) !== '') {
        return true;
    }

    $snapshot['view_consumed_at'] = date('Y-m-d H:i:s');
    $snapshot['view_consumed_by'] = (string) ($_SESSION['username'] ?? $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'HR');

    return crmSaveCourseCertificateSnapshot($registrationId, $snapshot);
}

if (isset($pdo) && $pdo instanceof PDO) {
    crmEnsureRegistrationProfileParentEmailColumn($pdo);
}

function crmEnsureAuditLogsTable(PDO $pdo): bool
{
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `audit_logs` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) DEFAULT NULL,
              `action` varchar(255) NOT NULL,
              `table_name` varchar(100) DEFAULT NULL,
              `record_id` int(11) DEFAULT NULL,
              `ip_address` varchar(50) DEFAULT NULL,
              `user_agent` varchar(255) DEFAULT NULL,
              `browser` varchar(80) DEFAULT NULL,
              `device_type` varchar(40) DEFAULT NULL,
              `latitude` decimal(10,7) DEFAULT NULL,
              `longitude` decimal(10,7) DEFAULT NULL,
              `location_text` varchar(255) DEFAULT NULL,
              `location_source` varchar(20) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_audit_user` (`user_id`),
              KEY `idx_audit_table` (`table_name`),
              KEY `idx_audit_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    } catch (Exception $e) {
        return false;
    }

    if (!crmTableExists($pdo, 'audit_logs')) {
        return false;
    }

    $schemaAdds = [
        "ALTER TABLE `audit_logs` ADD COLUMN `user_agent` varchar(255) DEFAULT NULL AFTER `ip_address`",
        "ALTER TABLE `audit_logs` ADD COLUMN `browser` varchar(80) DEFAULT NULL AFTER `user_agent`",
        "ALTER TABLE `audit_logs` ADD COLUMN `device_type` varchar(40) DEFAULT NULL AFTER `browser`",
        "ALTER TABLE `audit_logs` ADD COLUMN `latitude` decimal(10,7) DEFAULT NULL AFTER `device_type`",
        "ALTER TABLE `audit_logs` ADD COLUMN `longitude` decimal(10,7) DEFAULT NULL AFTER `latitude`",
        "ALTER TABLE `audit_logs` ADD COLUMN `location_text` varchar(255) DEFAULT NULL AFTER `longitude`",
        "ALTER TABLE `audit_logs` ADD COLUMN `location_source` varchar(20) DEFAULT NULL AFTER `location_text`",
    ];
    foreach ($schemaAdds as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // ignore if already exists
        }
    }

    return true;
}

function crmAuditRoleName(): string
{
    return strtolower(trim((string)($_SESSION['role_name'] ?? '')));
}

function crmIsSuperAdminRole(): bool
{
    return crmAuditRoleName() === 'super admin';
}

function crmIsHrRole(): bool
{
    $role = crmAuditRoleName();
    return $role === 'hr' || strpos($role, 'human') !== false;
}

function crmAuditDetectBrowser(string $ua): string
{
    $agent = strtolower($ua);
    if ($agent === '') return 'Unknown';
    if (strpos($agent, 'edg/') !== false) return 'Edge';
    if (strpos($agent, 'opr/') !== false || strpos($agent, 'opera') !== false) return 'Opera';
    if (strpos($agent, 'chrome/') !== false && strpos($agent, 'edg/') === false) return 'Chrome';
    if (strpos($agent, 'firefox/') !== false) return 'Firefox';
    if (strpos($agent, 'safari/') !== false && strpos($agent, 'chrome/') === false) return 'Safari';
    if (strpos($agent, 'msie') !== false || strpos($agent, 'trident/') !== false) return 'Internet Explorer';
    return 'Other';
}

function crmAuditDetectDeviceType(string $ua): string
{
    $agent = strtolower($ua);
    if ($agent === '') return 'Unknown';
    if (strpos($agent, 'bot') !== false || strpos($agent, 'spider') !== false || strpos($agent, 'crawl') !== false) return 'Bot';
    if (strpos($agent, 'tablet') !== false || strpos($agent, 'ipad') !== false) return 'Tablet';
    if (strpos($agent, 'mobile') !== false || strpos($agent, 'android') !== false || strpos($agent, 'iphone') !== false) return 'Mobile';
    return 'Desktop';
}

function crmAuditGeoSession(): array
{
    $geo = $_SESSION['audit_geo'] ?? [];
    if (!is_array($geo)) {
        return [];
    }
    return $geo;
}

function crmAuditIsPrivateOrLocalIp(string $ip): bool
{
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
}

function crmAuditLookupIpLocation(string $ip): array
{
    $ip = trim($ip);
    if ($ip === '') {
        return [];
    }
    if (crmAuditIsPrivateOrLocalIp($ip)) {
        return [
            'location_text' => 'Local/Private Network',
            'location_source' => 'ip',
        ];
    }

    if (!isset($_SESSION['audit_ip_geo_cache']) || !is_array($_SESSION['audit_ip_geo_cache'])) {
        $_SESSION['audit_ip_geo_cache'] = [];
    }
    if (isset($_SESSION['audit_ip_geo_cache'][$ip]) && is_array($_SESSION['audit_ip_geo_cache'][$ip])) {
        return $_SESSION['audit_ip_geo_cache'][$ip];
    }

    $url = 'https://ipwho.is/' . rawurlencode($ip);
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 2.5,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: ATS-CRM-Audit/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $result = [];
    try {
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw !== false && trim($raw) !== '') {
            $json = json_decode($raw, true);
            if (is_array($json) && !empty($json['success'])) {
                $city = trim((string)($json['city'] ?? ''));
                $region = trim((string)($json['region'] ?? ''));
                $country = trim((string)($json['country'] ?? ''));
                $parts = array_values(array_filter([$city, $region, $country], static function ($v) {
                    return $v !== '';
                }));
                $result = [
                    'latitude' => (isset($json['latitude']) && is_numeric($json['latitude'])) ? (float)$json['latitude'] : null,
                    'longitude' => (isset($json['longitude']) && is_numeric($json['longitude'])) ? (float)$json['longitude'] : null,
                    'location_text' => !empty($parts) ? implode(', ', $parts) : '',
                    'location_source' => 'ip',
                ];
            }
        }
    } catch (Exception $e) {
        $result = [];
    }

    $_SESSION['audit_ip_geo_cache'][$ip] = $result;
    return $result;
}

function crmAuditResolveClientIpFromServer(array $server): string
{
    $candidates = [];

    $pushHeaderValues = static function (?string $raw) use (&$candidates): void {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return;
        }
        foreach (explode(',', $raw) as $part) {
            $value = trim((string)$part);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }
    };

    $pushHeaderValues($server['HTTP_CF_CONNECTING_IP'] ?? '');
    $pushHeaderValues($server['HTTP_TRUE_CLIENT_IP'] ?? '');
    $pushHeaderValues($server['HTTP_X_REAL_IP'] ?? '');
    $pushHeaderValues($server['HTTP_X_FORWARDED_FOR'] ?? '');
    $pushHeaderValues($server['HTTP_CLIENT_IP'] ?? '');
    $pushHeaderValues($server['REMOTE_ADDR'] ?? '');

    foreach ($candidates as $candidate) {
        $candidate = preg_replace('/:\d+$/', '', $candidate);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '';
}

function crmAuditInsert(PDO $pdo, array $payload): bool
{
    if (!crmEnsureAuditLogsTable($pdo)) {
        return false;
    }

    $userId = (int)($payload['user_id'] ?? ($_SESSION['user_id'] ?? 0));
    $recordId = (int)($payload['record_id'] ?? 0);
    $action = trim((string)($payload['action'] ?? ''));
    $tableName = trim((string)($payload['table_name'] ?? ''));
    $ip = trim((string)($payload['ip_address'] ?? crmAuditResolveClientIpFromServer($_SERVER)));
    $ua = trim((string)($payload['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    $browser = trim((string)($payload['browser'] ?? ''));
    $deviceType = trim((string)($payload['device_type'] ?? ''));
    $geo = crmAuditGeoSession();

    $latRaw = $payload['latitude'] ?? ($geo['latitude'] ?? null);
    $lngRaw = $payload['longitude'] ?? ($geo['longitude'] ?? null);
    $locationText = trim((string)($payload['location_text'] ?? ($geo['location_text'] ?? '')));
    $locationSource = trim((string)($payload['location_source'] ?? ($geo['source'] ?? '')));

    $lat = null;
    $lng = null;
    if ($latRaw !== null && is_numeric($latRaw)) {
        $latV = (float)$latRaw;
        if ($latV >= -90 && $latV <= 90) {
            $lat = $latV;
        }
    }
    if ($lngRaw !== null && is_numeric($lngRaw)) {
        $lngV = (float)$lngRaw;
        if ($lngV >= -180 && $lngV <= 180) {
            $lng = $lngV;
        }
    }

    if (($lat === null || $lng === null || $locationText === '') && $ip !== '') {
        $ipGeo = crmAuditLookupIpLocation($ip);
        if ($lat === null && isset($ipGeo['latitude']) && is_numeric($ipGeo['latitude'])) {
            $lat = (float)$ipGeo['latitude'];
        }
        if ($lng === null && isset($ipGeo['longitude']) && is_numeric($ipGeo['longitude'])) {
            $lng = (float)$ipGeo['longitude'];
        }
        if ($locationText === '') {
            $locationText = trim((string)($ipGeo['location_text'] ?? ''));
        }
        if ($locationSource === '') {
            $locationSource = trim((string)($ipGeo['location_source'] ?? ''));
        }
    }

    if ($action === '') {
        return false;
    }

    if (strlen($action) > 255) {
        $action = substr($action, 0, 252) . '...';
    }
    if (strlen($tableName) > 100) {
        $tableName = substr($tableName, 0, 100);
    }
    if (strlen($ip) > 50) {
        $ip = substr($ip, 0, 50);
    }
    if (strlen($ua) > 255) {
        $ua = substr($ua, 0, 255);
    }
    if ($browser === '') {
        $browser = crmAuditDetectBrowser($ua);
    }
    if ($deviceType === '') {
        $deviceType = crmAuditDetectDeviceType($ua);
    }
    if (strlen($browser) > 80) {
        $browser = substr($browser, 0, 80);
    }
    if (strlen($deviceType) > 40) {
        $deviceType = substr($deviceType, 0, 40);
    }
    if (strlen($locationText) > 255) {
        $locationText = substr($locationText, 0, 255);
    }
    if ($locationSource === '') {
        if ($lat !== null && $lng !== null) {
            $locationSource = 'gps';
        } elseif ($locationText !== '') {
            $locationSource = 'ip';
        }
    }
    if (strlen($locationSource) > 20) {
        $locationSource = substr($locationSource, 0, 20);
    }

    try {
        $st = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id, action, table_name, record_id, ip_address,
                user_agent, browser, device_type, latitude, longitude, location_text, location_source, created_at
            )
            VALUES (
                :user_id, :action, :table_name, :record_id, :ip_address,
                :user_agent, :browser, :device_type, :latitude, :longitude, :location_text, :location_source, NOW()
            )
        ");
        return $st->execute([
            ':user_id' => ($userId > 0 ? $userId : null),
            ':action' => $action,
            ':table_name' => ($tableName !== '' ? $tableName : null),
            ':record_id' => ($recordId > 0 ? $recordId : null),
            ':ip_address' => ($ip !== '' ? $ip : null),
            ':user_agent' => ($ua !== '' ? $ua : null),
            ':browser' => ($browser !== '' ? $browser : null),
            ':device_type' => ($deviceType !== '' ? $deviceType : null),
            ':latitude' => $lat,
            ':longitude' => $lng,
            ':location_text' => ($locationText !== '' ? $locationText : null),
            ':location_source' => ($locationSource !== '' ? $locationSource : null),
        ]);
    } catch (Exception $e) {
        return false;
    }
}

function crmAuditGuessActionFromRequest(): string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '';
    }

    // High-confidence explicit flags first.
    if (isset($_POST['delete_registration']) || isset($_POST['delete_target']) || isset($_POST['delete_id'])) {
        return 'DELETE';
    }
    if (isset($_POST['save_payment'])) {
        return 'PAYMENT';
    }
    if (isset($_POST['mark_done']) && (int)($_POST['convert'] ?? 0) === 1) {
        return 'CONVERT';
    }
    if (isset($_POST['add_followup'])) {
        return 'CREATE';
    }
    if (isset($_POST['update_followup']) || isset($_POST['verify_followup'])) {
        return 'UPDATE';
    }

    foreach ($_POST as $k => $v) {
        $key = strtolower((string)$k);
        $val = is_scalar($v) ? strtolower(trim((string)$v)) : '';

        if (
            $key === 'action'
            || $key === 'submit_action'
            || $key === 'mode'
            || $key === 'task'
            || $key === 'op'
        ) {
            if (strpos($val, 'delete') !== false || strpos($val, 'remove') !== false) {
                return 'DELETE';
            }
            if (strpos($val, 'convert') !== false) {
                return 'CONVERT';
            }
            if (strpos($val, 'import') !== false) {
                return 'IMPORT';
            }
            if (strpos($val, 'assign') !== false) {
                return 'ASSIGN';
            }
            if (strpos($val, 'payment') !== false || strpos($val, 'pay') !== false) {
                return 'PAYMENT';
            }
            if (strpos($val, 'create') !== false || strpos($val, 'add') !== false || strpos($val, 'save') !== false) {
                return 'CREATE';
            }
            if (strpos($val, 'update') !== false || strpos($val, 'edit') !== false) {
                return 'UPDATE';
            }
        }

        if (strpos($key, 'delete') !== false) {
            return 'DELETE';
        }
        if (strpos($key, 'update') !== false || strpos($key, 'edit') !== false) {
            return 'UPDATE';
        }
        if (strpos($key, 'add') !== false || strpos($key, 'create') !== false || strpos($key, 'save') !== false) {
            return 'CREATE';
        }
        if (strpos($key, 'assign') !== false) {
            return 'ASSIGN';
        }
        if (strpos($key, 'convert') !== false) {
            return 'CONVERT';
        }
        if (strpos($key, 'import') !== false) {
            return 'IMPORT';
        }
        if (strpos($key, 'pay') !== false) {
            return 'PAYMENT';
        }
    }

    return 'UPDATE';
}

function crmAuditGuessRecordIdFromRequest(): int
{
    $candidates = [
        'id',
        'reg_id',
        'delete_id',
        'record_id',
        'lead_id',
        'enquiry_id',
        'followup_id',
        'registration_id',
        'target_id',
        'payment_id',
        'user_id',
        'role_id',
        'branch_id',
    ];
    foreach ($candidates as $key) {
        if (isset($_POST[$key]) && is_scalar($_POST[$key]) && (int)$_POST[$key] > 0) {
            return (int)$_POST[$key];
        }
    }
    foreach ($candidates as $key) {
        if (isset($_GET[$key]) && is_scalar($_GET[$key]) && (int)$_GET[$key] > 0) {
            return (int)$_GET[$key];
        }
    }
    return 0;
}

function crmAuditMonthNameFromInt(int $month): string
{
    static $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    return $months[$month] ?? ('Month ' . $month);
}

function crmAuditFmtRs($amount): string
{
    if ($amount === null || $amount === '') {
        return 'Rs 0';
    }
    $num = (float)$amount;
    $decimals = (abs($num - floor($num)) < 0.00001) ? 0 : 2;
    return 'Rs ' . number_format($num, $decimals);
}

function crmAuditTargetActionText(PDO $pdo, string $page, string $action, int $recordId): string
{
    $page = strtolower(trim($page));
    $action = strtoupper(trim($action));

    $postedUserId = (int)($_POST['user_id'] ?? 0);
    $postedAmount = (string)($_POST['target_amount'] ?? '');
    $postedMonth = (int)($_POST['target_month'] ?? 0);
    $postedYear = (int)($_POST['target_year'] ?? 0);
    $postedId = (int)($_POST['id'] ?? 0);

    if ($page === 'targets/setup') {
        if ($postedId > 0) {
            $action = 'UPDATE';
        } elseif ($postedUserId > 0 && $postedAmount !== '') {
            $action = 'CREATE';
        }
    }

    $name = '';
    if ($postedUserId > 0) {
        try {
            $stUser = $pdo->prepare("SELECT name FROM users WHERE id = :id LIMIT 1");
            $stUser->execute([':id' => $postedUserId]);
            $name = trim((string)$stUser->fetchColumn());
        } catch (Exception $e) {
            $name = '';
        }
    }

    if ($action === 'DELETE' && (int)($_POST['delete_id'] ?? 0) > 0) {
        $deleteId = (int)$_POST['delete_id'];
        try {
            $st = $pdo->prepare("
                SELECT mt.target_amount, mt.target_month, mt.target_year, COALESCE(u.name,'') AS user_name
                FROM monthly_targets mt
                LEFT JOIN users u ON u.id = mt.user_id
                WHERE mt.id = :id
                LIMIT 1
            ");
            $st->execute([':id' => $deleteId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($row)) {
                $name = trim((string)($row['user_name'] ?? $name));
                $postedAmount = (string)($row['target_amount'] ?? $postedAmount);
                $postedMonth = (int)($row['target_month'] ?? $postedMonth);
                $postedYear = (int)($row['target_year'] ?? $postedYear);
                $recordId = $deleteId;
            }
        } catch (Exception $e) {
            // keep fallback with available posted fields
        }
    }

    $person = ($name !== '') ? $name : 'user';
    $amountText = ($postedAmount !== '') ? crmAuditFmtRs($postedAmount) : 'target amount';
    $periodText = ($postedMonth >= 1 && $postedMonth <= 12 && $postedYear >= 2000)
        ? ' (' . crmAuditMonthNameFromInt($postedMonth) . ' ' . $postedYear . ')'
        : '';

    if ($action === 'CREATE') {
        return 'New target set to ' . $person . ' - ' . $amountText . $periodText;
    }
    if ($action === 'UPDATE') {
        return 'Target updated for ' . $person . ' to ' . $amountText . $periodText;
    }
    if ($action === 'DELETE') {
        return 'Target removed for ' . $person . ' - ' . $amountText . $periodText;
    }
    if ($recordId > 0) {
        return ucwords(strtolower($action)) . ' monthly target (Record #' . $recordId . ')';
    }
    return ucwords(strtolower($action)) . ' monthly target';
}

function crmAuditLeadActionText(PDO $pdo, string $action, int $recordId): string
{
    $action = strtoupper(trim($action));
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $course = trim((string)($_POST['course_interest'] ?? ''));
    $assignedTo = (int)($_POST['assigned_to'] ?? 0);

    if ($recordId > 0 && $action === 'CREATE') {
        $action = 'UPDATE';
    } elseif ($recordId <= 0 && $action === 'UPDATE') {
        $action = 'CREATE';
    }

    $assignedName = '';
    if ($assignedTo > 0) {
        try {
            $st = $pdo->prepare("SELECT name FROM users WHERE id = :id LIMIT 1");
            $st->execute([':id' => $assignedTo]);
            $assignedName = trim((string)$st->fetchColumn());
        } catch (Exception $e) {
            $assignedName = '';
        }
    }

    $who = $name !== '' ? $name : 'Unknown';
    $phoneText = $phone !== '' ? ' (' . $phone . ')' : '';
    $courseText = $course !== '' ? ' for ' . $course : '';
    $assignText = $assignedName !== '' ? ' assigned to ' . $assignedName : '';

    if ($action === 'CREATE') {
        return 'New lead added: ' . $who . $phoneText . $courseText . $assignText;
    }
    if ($action === 'UPDATE') {
        return 'Lead updated: ' . $who . $phoneText . $courseText . $assignText;
    }
    if ($action === 'DELETE') {
        return 'Lead deleted' . ($recordId > 0 ? ' (Record #' . $recordId . ')' : '');
    }
    return ucwords(strtolower($action)) . ' lead' . ($recordId > 0 ? ' (Record #' . $recordId . ')' : '');
}

function crmAuditEnquiryActionText(PDO $pdo, string $action, int $recordId): string
{
    $action = strtoupper(trim($action));
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $course = trim((string)($_POST['course_interest'] ?? ''));
    $handledBy = (int)($_POST['handled_by'] ?? 0);

    if ($recordId > 0 && $action === 'CREATE') {
        $action = 'UPDATE';
    }

    $handledByName = '';
    if ($handledBy > 0) {
        try {
            $st = $pdo->prepare("SELECT name FROM users WHERE id = :id LIMIT 1");
            $st->execute([':id' => $handledBy]);
            $handledByName = trim((string)$st->fetchColumn());
        } catch (Exception $e) {
            $handledByName = '';
        }
    }

    $who = $name !== '' ? $name : 'Unknown';
    $phoneText = $phone !== '' ? ' (' . $phone . ')' : '';
    $courseText = $course !== '' ? ' for ' . $course : '';
    $ownerText = $handledByName !== '' ? ' handled by ' . $handledByName : '';

    if ($action === 'CREATE') {
        return 'New enquiry added: ' . $who . $phoneText . $courseText . $ownerText;
    }
    if ($action === 'UPDATE') {
        return 'Enquiry updated: ' . $who . $phoneText . $courseText . $ownerText;
    }
    if ($action === 'DELETE') {
        return 'Enquiry deleted' . ($recordId > 0 ? ' (Record #' . $recordId . ')' : '');
    }
    return ucwords(strtolower($action)) . ' enquiry' . ($recordId > 0 ? ' (Record #' . $recordId . ')' : '');
}

function crmAuditRegistrationActionText(string $action, int $recordId): string
{
    $action = strtoupper(trim($action));
    $student = trim((string)($_POST['student_name'] ?? ''));
    $program = trim((string)($_POST['program_name'] ?? ''));
    $regNo = trim((string)($_POST['registration_no'] ?? ''));
    $status = strtolower(trim((string)($_POST['registration_status'] ?? '')));
    $regIdPost = (int)($_POST['reg_id'] ?? 0);

    if ($regIdPost > 0 || $recordId > 0) {
        if ($action === 'CREATE') {
            $action = 'UPDATE';
        }
    } elseif ($action === 'UPDATE') {
        $action = 'CREATE';
    }

    if (isset($_POST['delete_registration'])) {
        $action = 'DELETE';
    }

    $who = $student !== '' ? $student : 'Student';
    $programText = $program !== '' ? ' - ' . $program : '';
    $regNoText = $regNo !== '' ? ' (' . $regNo . ')' : '';
    $statusText = $status !== '' ? ' [' . strtoupper($status) . ']' : '';

    if ($action === 'CREATE') {
        return 'New registration created for ' . $who . $programText . $regNoText . $statusText;
    }
    if ($action === 'UPDATE') {
        return 'Registration updated for ' . $who . $programText . $regNoText . $statusText;
    }
    if ($action === 'DELETE') {
        return 'Registration deleted' . ($recordId > 0 ? ' (Record #' . $recordId . ')' : '');
    }
    return ucwords(strtolower($action)) . ' registration' . ($recordId > 0 ? ' (Record #' . $recordId . ')' : '');
}

function crmAuditFollowupActionText(string $action, int $recordId): string
{
    $action = strtoupper(trim($action));
    $enquiryId = (int)($_POST['enquiry_id'] ?? 0);
    $followupId = (int)($_POST['followup_id'] ?? $recordId);
    $fdate = trim((string)($_POST['followup_date'] ?? ''));
    $ftime = trim((string)($_POST['followup_time'] ?? ''));
    $ftype = trim((string)($_POST['followup_type'] ?? ''));

    if (isset($_POST['add_followup'])) {
        $dateText = $fdate !== '' ? $fdate : date('Y-m-d');
        $timeText = $ftime !== '' ? (' ' . $ftime) : '';
        $typeText = $ftype !== '' ? (' [' . ucfirst($ftype) . ']') : '';
        return 'New follow-up added for enquiry #' . ($enquiryId > 0 ? $enquiryId : '-') . ' on ' . $dateText . $timeText . $typeText;
    }

    if (isset($_POST['mark_done'])) {
        $convert = (int)($_POST['convert'] ?? 0) === 1;
        $regMode = strtolower(trim((string)($_POST['reg_mode'] ?? 'draft')));
        $regType = strtolower(trim((string)($_POST['reg_type'] ?? '')));
        if ($convert) {
            $modeText = ($regMode === 'active') ? 'Active Registration' : 'Draft Registration';
            $typeText = ($regType !== '') ? (' (' . ucfirst($regType) . ')') : '';
            return 'Follow-up marked done and converted to ' . $modeText . $typeText . ' for enquiry #' . ($enquiryId > 0 ? $enquiryId : '-');
        }
        return 'Follow-up marked done for enquiry #' . ($enquiryId > 0 ? $enquiryId : '-');
    }

    if (isset($_POST['update_followup'])) {
        return 'Follow-up updated' . ($followupId > 0 ? ' (Record #' . $followupId . ')' : '');
    }
    if (isset($_POST['verify_followup'])) {
        return 'Follow-up verification updated' . ($followupId > 0 ? ' (Record #' . $followupId . ')' : '');
    }

    return ucwords(strtolower($action)) . ' enquiry follow-up' . ($followupId > 0 ? ' (Record #' . $followupId . ')' : '');
}

function crmAuditPaymentActionText(string $action, int $recordId): string
{
    $action = strtoupper(trim($action));
    $registrationId = (int)($_POST['registration_id'] ?? 0);
    $amount = trim((string)($_POST['amount'] ?? ''));
    $mode = trim((string)($_POST['payment_mode'] ?? ''));
    $type = trim((string)($_POST['payment_type'] ?? ''));
    $date = trim((string)($_POST['payment_date'] ?? ''));

    if ($action === 'PAYMENT' || isset($_POST['save_payment'])) {
        $amountText = $amount !== '' ? crmAuditFmtRs($amount) : 'Amount';
        $modeText = $mode !== '' ? (' via ' . $mode) : '';
        $typeText = $type !== '' ? (' [' . ucfirst($type) . ']') : '';
        $dateText = $date !== '' ? (' on ' . $date) : '';
        return 'Payment recorded: ' . $amountText . ' for registration #' . ($registrationId > 0 ? $registrationId : '-') . $modeText . $typeText . $dateText;
    }

    if ($action === 'DELETE') {
        return 'Payment deleted' . ($recordId > 0 ? ' (Record #' . $recordId . ')' : '');
    }

    return ucwords(strtolower($action)) . ' payment' . ($recordId > 0 ? ' (Record #' . $recordId . ')' : '');
}

function crmAuditMapPageToTableName(string $page): string
{
    $page = trim(strtolower($page), '/');
    if ($page === '') {
        return '';
    }

    if (strpos($page, 'leads/') === 0) {
        return 'leads';
    }
    if (strpos($page, 'enquiries/followups') === 0) {
        return 'enquiry_followups';
    }
    if (strpos($page, 'enquiries/') === 0) {
        return 'enquiries';
    }
    if (strpos($page, 'targets/') === 0) {
        return 'monthly_targets';
    }
    if (strpos($page, 'payments/') === 0) {
        return 'registration_payments';
    }
    if (strpos($page, 'registrations/') === 0) {
        return 'registrations';
    }
    if (strpos($page, 'user') !== false) {
        return 'users';
    }
    if (strpos($page, 'role') !== false || strpos($page, 'permission') !== false) {
        return 'roles';
    }

    return strtok($page, '/') ?: '';
}

function crmAuditLogPageMutation(PDO $pdo, string $page): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $action = crmAuditGuessActionFromRequest();
    if ($action === '') {
        return;
    }

    $tableName = crmAuditMapPageToTableName($page);
    if ($tableName === '') {
        return;
    }

    $recordId = crmAuditGuessRecordIdFromRequest();
    $postKeys = array_keys($_POST);
    $firstKey = '';
    if (!empty($postKeys)) {
        $firstKey = (string)$postKeys[0];
    }
    $actionText = '';
    if ($tableName === 'monthly_targets') {
        $actionText = crmAuditTargetActionText($pdo, $page, $action, $recordId);
    } elseif ($tableName === 'leads') {
        $actionText = crmAuditLeadActionText($pdo, $action, $recordId);
    } elseif ($tableName === 'enquiries') {
        $actionText = crmAuditEnquiryActionText($pdo, $action, $recordId);
    } elseif ($tableName === 'enquiry_followups') {
        $actionText = crmAuditFollowupActionText($action, $recordId);
    } elseif ($tableName === 'registrations') {
        $actionText = crmAuditRegistrationActionText($action, $recordId);
    } elseif ($tableName === 'registration_payments') {
        $actionText = crmAuditPaymentActionText($action, $recordId);
    }
    if ($actionText === '') {
        $actionText = $action . ' [' . $page . ']';
        if ($firstKey !== '') {
            $actionText .= ' via ' . $firstKey;
        }
    }

    crmAuditInsert($pdo, [
        'action' => $actionText,
        'table_name' => $tableName,
        'record_id' => $recordId,
    ]);
}
