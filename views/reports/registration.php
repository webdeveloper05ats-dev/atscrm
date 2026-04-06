<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$reportId = (int)($_GET['report_id'] ?? 0);

if (!$reportId) {
    echo "<div class='alert alert-danger'>Invalid Report</div>";
    return;
}

/* FETCH DATA */
$stmt = $pdo->prepare("SELECT * FROM report_registrations WHERE report_id=? ORDER BY id ASC");
$stmt->execute([$reportId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="crm-page">

    <h2>🧾 Registration Report</h2>

    <form id="registrationForm">

        <input type="hidden" name="report_id" value="<?= $reportId ?>">

        <table class="table table-bordered no-mobile-cards">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Contact</th>
                    <th>College</th>
                    <th>Date</th>
                    <th>Course</th>
                    <th>Billing</th>
                    <th>Collection</th>
                    <th>Balance</th>
                    <th>Mode</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody id="regBody">

                <?php foreach($rows as $r): ?>
                <tr>
                    <td><input name="name[]" value="<?= $r['name'] ?>" class="form-control"></td>
                    <td><input name="department[]" value="<?= $r['department'] ?>" class="form-control"></td>
                    <td><input name="contact_no[]" value="<?= $r['contact_no'] ?>" class="form-control"></td>
                    <td><input name="college[]" value="<?= $r['college'] ?>" class="form-control"></td>
                    <td><input type="date" name="date_of_reg[]" value="<?= $r['date_of_reg'] ?>" class="form-control"></td>
                    <td><input name="course[]" value="<?= $r['course'] ?>" class="form-control"></td>
                    <td><input name="billing[]" value="<?= $r['billing'] ?>" class="form-control"></td>
                    <td><input name="collection[]" value="<?= $r['collection'] ?>" class="form-control"></td>
                    <td><input name="balance[]" value="<?= $r['balance'] ?>" class="form-control"></td>
                    <td><input name="payment_mode[]" value="<?= $r['payment_mode'] ?>" class="form-control"></td>
                    <td><button type="button" class="btn btn-danger removeRow">X</button></td>
                </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

        <button type="button" id="addRow" class="btn btn-success">➕ Add Row</button>
        <button type="submit" class="btn btn-primary">💾 Save</button>

    </form>

</div>
