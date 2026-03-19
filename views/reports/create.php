<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$reportId = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

$report = null;

if ($reportId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id=? LIMIT 1");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    // ❗ Safety check
    if (!$report) {
        $reportId = 0;
    }
}

// Role-based modules
$roleName = $_SESSION['role_name'] ?? 'Front Office';
?>

<div class="crm-page">

    <h2 style="margin-bottom:20px;">📊 Daily Report</h2>

    <?php if (!$report): ?>

        <!-- ========================= -->
        <!-- CREATE REPORT FORM -->
        <!-- ========================= -->

        <div class="card" style="max-width:400px; padding:20px; border-radius:12px; box-shadow:0 5px 20px rgba(0,0,0,0.05);">

            <form id="createReportForm">

                <div style="margin-bottom:15px;">
                    <label style="font-weight:600;">Select Date</label>
                    <input type="date" name="report_date" required class="form-control">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">
                    🚀 Create Report
                </button>

            </form>

        </div>

    <?php else: ?>

        <!-- ========================= -->
        <!-- REPORT HEADER -->
        <!-- ========================= -->

        <div class="alert alert-success" style="border-radius:10px;">
            Report created for:
            <b><?= htmlspecialchars($report['report_date']) ?></b>
        </div>

        <!-- ========================= -->
        <!-- MODULE NAVIGATION -->
        <!-- ========================= -->

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:20px;">

            <!-- COMMON MODULES -->
            <a class="btn btn-outline-primary"
               href="index.php?page=reports/activity&report_id=<?= $reportId ?>">
               📞 Activity
            </a>

            <a class="btn btn-outline-success"
               href="index.php?page=reports/registration&report_id=<?= $reportId ?>">
               🧾 Registration
            </a>

            <a class="btn btn-outline-info"
               href="index.php?page=reports/hourly&report_id=<?= $reportId ?>">
               ⏰ Hourly
            </a>

            <!-- ROLE BASED -->
            <?php if ($roleName === 'Front Office' || $roleName === 'Corporate Executive'): ?>

                <a class="btn btn-outline-warning"
                   href="index.php?page=reports/followups&report_id=<?= $reportId ?>">
                   📌 Follow-ups
                </a>

            <?php endif; ?>

            <?php if ($roleName === 'HR'): ?>

                <a class="btn btn-outline-dark"
                   href="index.php?page=reports/interviews&report_id=<?= $reportId ?>">
                   🎯 Interviews
                </a>

                <a class="btn btn-outline-secondary"
                   href="index.php?page=reports/placements&report_id=<?= $reportId ?>">
                   🏢 Placements
                </a>

            <?php endif; ?>

            <?php if ($roleName === 'Marketing'): ?>

                <a class="btn btn-outline-danger"
                   href="index.php?page=marketing/colleges&report_id=<?= $reportId ?>">
                   🏫 Colleges
                </a>

                <a class="btn btn-outline-primary"
                   href="index.php?page=marketing/prospect&report_id=<?= $reportId ?>">
                   📊 Prospect
                </a>

                <a class="btn btn-outline-success"
                   href="index.php?page=marketing/programs&report_id=<?= $reportId ?>">
                   🎓 Programs
                </a>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>