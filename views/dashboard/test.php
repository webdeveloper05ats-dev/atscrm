<?php
// =====================================
// Test Dashboard
// File: views/dashboard/test.php
// Slug: dashboard/test
// =====================================

requireView('dashboard/test');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$pageTitle = "Test Dashboard";

$userName = $_SESSION['user_name'] ?? 'User';
$roleName = $_SESSION['role_name'] ?? 'Role';
$branchName = $_SESSION['branch_name'] ?? 'Branch';
?>

<h2 style="margin-bottom:16px;">Test Dashboard</h2>

<div class="card">
    <div class="card-header">Welcome</div>
    <p style="line-height:1.7; color: var(--text-dark);">
        Hello <strong><?= htmlspecialchars($userName) ?></strong><br>
        Role: <strong><?= htmlspecialchars($roleName) ?></strong><br>
        Branch: <strong><?= htmlspecialchars($branchName) ?></strong>
    </p>
</div>

<div class="card" style="margin-top:18px;">
    <div class="card-header">Test Info</div>
    <p style="padding:14px; color: var(--text-light); line-height:1.7;">
        This is a sample dashboard page to test dynamic role dashboards.
        If you can see this page, your default_dashboard_slug setup is working.
    </p>
</div>