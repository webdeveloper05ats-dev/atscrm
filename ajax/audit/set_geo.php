<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/helper.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$lat = isset($_POST['latitude']) && is_numeric($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$lng = isset($_POST['longitude']) && is_numeric($_POST['longitude']) ? (float)$_POST['longitude'] : null;
$locationText = trim((string)($_POST['location_text'] ?? ''));

if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid coordinates']);
    exit;
}

if (strlen($locationText) > 255) {
    $locationText = substr($locationText, 0, 255);
}

$_SESSION['audit_geo'] = [
    'latitude' => round($lat, 7),
    'longitude' => round($lng, 7),
    'location_text' => $locationText,
    'source' => 'gps',
    'captured_at' => date('Y-m-d H:i:s'),
];

echo json_encode([
    'ok' => true,
    'saved' => true,
]);
