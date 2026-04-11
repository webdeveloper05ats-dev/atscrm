<?php
// ==============================
// ATS CRM - Database Connection
// ==============================
//Mani Server
$host     = "srv1279.hstgr.io";
$dbname   = "u440631799_crm";
$username = "u440631799_crm";          // change if needed
$password = "Accent@crm2026";              // change if needed
$charset  = "utf8mb4";



$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Show errors properly
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch as associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Ensure MySQL NOW()/CURRENT_TIMESTAMP use IST consistently.
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    // Do NOT show raw error in production
    die("Database connection failed. Please contact administrator.");
}
