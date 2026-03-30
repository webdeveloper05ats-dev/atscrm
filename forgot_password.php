<?php
// ============================================
// ATS CRM - Forgot Password (PHPMailer + Reset Link)
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/helper.php';

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJson('error', 'Invalid request.');
}

$email = trim($_POST['email'] ?? '');
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responseJson('error', 'Please enter a valid email.');
}

// Generic message (prevents user enumeration)
$genericMsg = 'If this email exists, a password reset link has been sent. Please check your inbox.';

// Find user (do NOT reveal if not found)
$stmt = $pdo->prepare("SELECT id, name, email, status FROM users WHERE email = :email LIMIT 1");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || (int)$user['status'] !== 1) {
    responseJson('success', $genericMsg);
}

// -------------------------------
// Generate reset token (store hash in DB)
// -------------------------------
$token = bin2hex(random_bytes(32)); // 64 chars
$tokenHash = password_hash($token, PASSWORD_DEFAULT);
$expires = (new DateTime())->modify('+30 minutes')->format('Y-m-d H:i:s');

$upd = $pdo->prepare("
    UPDATE users
    SET reset_token_hash = :h,
        reset_expires = :e
    WHERE id = :id
    LIMIT 1
");
$upd->execute([
    ':h'  => $tokenHash,
    ':e'  => $expires,
    ':id' => (int)$user['id']
]);

// Build reset link
// IMPORTANT: BASE_URL should point to your project base URL, e.g. https://yourdomain.com/ats_crm/
$resetLink = rtrim(BASE_URL, '/') . "/reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($user['email']);

// -------------------------------
// Send Email with PHPMailer (your local PHPMailer folder)
// -------------------------------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

try {
    $mail = new PHPMailer(true);

    // SMTP config (from config/app.php)
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;

    // ✅ Always setFrom as YOUR domain email (NOT user input)
    $mail->setFrom($mail->Username, APP_NAME);
    $mail->addAddress($user['email'], $user['name'] ?? '');

    $mail->isHTML(true);
    $mail->Subject = APP_NAME . ' - Reset Your Password';

    $safeName = htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
    $safeApp  = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');

    $mail->Body = "
        <div style='font-family:Arial,sans-serif;font-size:14px;color:#111;'>
            <h2 style='margin:0 0 10px;'>$safeApp</h2>
            <p>Hi <b>$safeName</b>,</p>
            <p>We received a request to reset your password.</p>
            <p>
              <a href='$resetLink' style='display:inline-block;background:#e91e63;color:#fff;
                 padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:700;'>
                 Reset Password
              </a>
            </p>
            <p style='color:#6b7280;'>This link will expire in <b>30 minutes</b>.</p>
            <p style='color:#6b7280;'>If you didn't request this, you can ignore this email.</p>
        </div>
    ";

    $mail->AltBody = "Reset your password: $resetLink (expires in 30 minutes)";

    $mail->send();
} catch (Exception $e) {
    // Don’t reveal SMTP errors to user (security + UX)
    // (Optional) log $e->getMessage() to a server log file
}

// Always return generic success
responseJson('success', $genericMsg);