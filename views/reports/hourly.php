<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$reportId = (int)($_GET['report_id'] ?? 0);

if (!$reportId) {
    echo "<div class='alert alert-danger'>Invalid Report</div>";
    return;
}

$stmt = $pdo->prepare("SELECT * FROM report_hourly WHERE report_id=? ORDER BY id ASC");
$stmt->execute([$reportId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="crm-page">

    <h2>⏰ Hourly Report</h2>

    <form id="hourlyForm">

        <input type="hidden" name="report_id" value="<?= $reportId ?>">

        <table class="table table-bordered no-mobile-cards">
            <thead>
                <tr>
                    <th>Time Slot</th>
                    <th>Particulars</th>
                    <th>Activities</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody id="hourlyBody">

                <?php foreach($rows as $r): ?>
                <tr>
                    <td><input name="time_slot[]" value="<?= $r['time_slot'] ?>" class="form-control"></td>
                    <td><input name="particulars[]" value="<?= $r['particulars'] ?>" class="form-control"></td>
                    <td><input name="activities[]" value="<?= $r['activities'] ?>" class="form-control"></td>
                    <td><button type="button" class="btn btn-danger removeRow">X</button></td>
                </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

        <button type="button" id="addHourlyRow" class="btn btn-success">➕ Add Row</button>
        <button type="submit" class="btn btn-primary">💾 Save</button>

    </form>

</div>
