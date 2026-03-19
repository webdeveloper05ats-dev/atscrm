<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$reportId = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

if (!$reportId) {
    echo "<div class='alert alert-danger'>Invalid Report</div>";
    return;
}

/* =========================
   FETCH EXISTING DATA
========================= */
$stmt = $pdo->prepare("SELECT * FROM report_activity WHERE report_id=? LIMIT 1");
$stmt->execute([$reportId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="crm-page">

    <h2>📞 Activity Report</h2>

    <form id="activityForm">

        <input type="hidden" name="report_id" value="<?= $reportId ?>">

        <div class="row">
        <?php
        function input($name,$label,$data){
            $val = htmlspecialchars($data[$name] ?? '');
            echo "
            <div class='col-md-3 mb-3'>
                <label style='font-weight:600;'>$label</label>
                <input type='number' name='$name' value='$val' class='form-control'>
            </div>";
        }

        input('fresh_calls','Fresh Calls',$data);
        input('follow_calls','Follow Calls',$data);
        input('msg_sent','Msg Sent',$data);
        input('mail_sent','Mail Sent',$data);
        input('total_calls','Total Calls',$data);

        input('promo','Promotions',$data);
        input('reference','Reference',$data);
        input('db_calls','DB Calls',$data);
        input('total_reg','Total Reg',$data);

        input('billing','Billing',$data);
        input('fresh_collection','Fresh Collection',$data);
        input('old_collection','Old Collection',$data);
        input('total_collection','Total Collection',$data);

        input('registrations','Registrations',$data);
        input('walkins','Walkins',$data);
        input('conversion_ratio','Conversion Ratio',$data);
        ?>
        </div>

        <button type="submit" class="btn btn-primary">
            💾 Save Activity
        </button>

    </form>

</div>