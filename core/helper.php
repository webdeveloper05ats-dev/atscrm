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
        'status'  => $status,
        'message' => $message,
        'data'    => $data
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
    if (!$date) return '';
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
        $st = $pdo->prepare("SHOW TABLES LIKE ?");
        $st->execute([$tableName]);
        $cache[$key] = (bool) $st->fetchColumn();
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
    $snapshot['stored_at'] = date('Y-m-d H:i:s');

    $encoded = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }

    return file_put_contents(crmCourseCertificateSnapshotAbsolutePath($registrationId), $encoded) !== false;
}

if (isset($pdo) && $pdo instanceof PDO) {
    crmEnsureRegistrationProfileParentEmailColumn($pdo);
}
