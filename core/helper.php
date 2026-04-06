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

function crmSendEmail(array $recipients, string $subject, string $htmlBody, string $textBody = '', ?string &$errorMessage = null): bool
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
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $htmlBody;
                $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
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
