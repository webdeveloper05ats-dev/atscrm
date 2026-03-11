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
        mkdir(dirname($uploadPath), 0777, true);
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
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}