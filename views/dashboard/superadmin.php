<?php
// Must be logged in
if (!isLoggedIn()) {
    redirect('login.php'); // router uses index.php, so use login.php directly
    exit;
}

// Only Super Admin allowed
if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
    redirect('index.php');
    exit;
}

$pageTitle = "Super Admin Dashboard";

// ==============================
// Fetch Dashboard Stats
// ==============================
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalBranches = $pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();
$totalLeads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$totalRegistrations = $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$totalRevenue = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM payments")->fetchColumn();
?>

<!-- IMPORTANT: Do NOT add .content or .main-content here.
     index.php already provides layout wrappers -->

<div class="card">
    <div class="card-header">
        Welcome Super Admin 👑
    </div>
    <p>
        Hello <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>,
        you have full system control.
    </p>
</div>

<div class="dashboard-grid">

    <div class="card stat-card">
        <h3>Total Users</h3>
        <h2><?= (int)$totalUsers ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Branches</h3>
        <h2><?= (int)$totalBranches ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Leads</h3>
        <h2><?= (int)$totalLeads ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Registrations</h3>
        <h2><?= (int)$totalRegistrations ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Revenue</h3>
        <h2>₹ <?= number_format((float)$totalRevenue, 2) ?></h2>
    </div>

</div>