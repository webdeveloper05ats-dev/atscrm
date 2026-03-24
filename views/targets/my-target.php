<?php
if (!defined('APP_NAME')) die("Unauthorized");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$userId   = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$currentYear  = (int)($_GET['year'] ?? date('Y'));
$currentMonth = (int)($_GET['month'] ?? date('n'));

$monthNames=[1=>'January','February','March','April','May','June','July','August','September','October','November','December'];

/* =========================
   PAYMENT MAP
========================= */
$paymentsMap = [];

$stmt = $pdo->prepare("
SELECT YEAR(payment_date) y, MONTH(payment_date) m, SUM(amount) total
FROM registration_payments
WHERE branch_id=? AND collected_by=? AND approval_status='approved'
GROUP BY y,m
");
$stmt->execute([$branchId,$userId]);

foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $p){
    $paymentsMap[$p['y'].'-'.$p['m']] = (float)$p['total'];
}

/* =========================
   TARGET
========================= */
$stmt=$pdo->prepare("
SELECT * FROM monthly_targets
WHERE branch_id=? AND user_id=? AND target_year=? AND target_month=?
LIMIT 1
");
$stmt->execute([$branchId,$userId,$currentYear,$currentMonth]);
$currentTarget=$stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   ACHIEVED
========================= */
$key = $currentYear.'-'.$currentMonth;
$achievedAmount = $paymentsMap[$key] ?? 0;

/* =========================
   CARRY
========================= */
$stmtPrev=$pdo->prepare("
SELECT target_year,target_month,target_amount
FROM monthly_targets
WHERE branch_id=? AND user_id=?
AND (target_year < ? OR (target_year=? AND target_month < ?))
ORDER BY target_year,target_month
");
$stmtPrev->execute([$branchId,$userId,$currentYear,$currentYear,$currentMonth]);

$carry=0;
foreach($stmtPrev->fetchAll(PDO::FETCH_ASSOC) as $row){
    $k=$row['target_year'].'-'.$row['target_month'];
    $ach=$paymentsMap[$k]??0;
    $target=$row['target_amount'];

    $effective=$target+$carry;
    $carry=($ach >= $effective)?0:($effective-$ach);
}

$baseTarget = $currentTarget['target_amount'] ?? 0;
$effectiveTarget = $baseTarget + $carry;

$shortfall = max($effectiveTarget - $achievedAmount,0);
$excess = max($achievedAmount - $effectiveTarget,0);

$progress = $effectiveTarget>0 ? ($achievedAmount/$effectiveTarget)*100 : 0;

/* =========================
   SMART INSIGHTS
========================= */
$daysInMonth = (int)date('t');
$currentDay  = (int)date('d');
$daysLeft = max(1, $daysInMonth - $currentDay);

$dailyRequired = $shortfall>0 ? $shortfall/$daysLeft : 0;

$status="🔴 Not Started"; 
$color="red";

if($achievedAmount >= $effectiveTarget && $effectiveTarget>0){
    $status="🟢 Achieved"; 
    $color="green";
}elseif($achievedAmount>0){
    $status="🟡 In Progress"; 
    $color="orange";
}
?>

<style>
:root{
--pink:#ec1670;
--pink-dark:#c8135b;
--soft:#fff6fb;
}

.wrap{background:#fcf8fb;padding:20px;border-radius:20px}

.grid{
display:grid;
grid-template-columns:repeat(4,1fr);
gap:12px;
margin-bottom:15px;
}

.card{
background:white;
padding:16px;
border-radius:14px;
border:1px solid #eee;
box-shadow:0 6px 18px rgba(0,0,0,0.05);
}

.highlight{
background:linear-gradient(135deg,var(--pink),var(--pink-dark));
color:white;
}

.progress{
height:14px;
background:#eee;
border-radius:20px;
overflow:hidden;
}

.progress-bar{
height:100%;
background:linear-gradient(90deg,var(--pink),#ff4fa3);
}

</style>

<div class="wrap">

<h4>🎯 My Target - <?=$monthNames[$currentMonth].' '.$currentYear?></h4>

<!-- KPI -->
<div class="grid">

<div class="card highlight">
Achieved<br><b>₹<?=number_format($achievedAmount)?></b>
</div>

<div class="card">
Target<br><b>₹<?=number_format($effectiveTarget)?></b>
</div>

<div class="card">
Carry<br><b>₹<?=number_format($carry)?></b>
</div>

<div class="card">
Shortfall<br><b>₹<?=number_format($shortfall)?></b>
</div>

</div>

<!-- Progress -->
<div class="card">
<div style="display:flex;justify-content:space-between">
<span>Progress</span>
<span><?=number_format($progress,1)?>%</span>
</div>

<div class="progress">
<div class="progress-bar" style="width:<?=$progress?>%"></div>
</div>

<div style="margin-top:8px">
₹<?=number_format($achievedAmount)?> / ₹<?=number_format($effectiveTarget)?>
</div>
</div>

<!-- Insight -->
<div class="card">
<b>📊 Performance Insight</b>
<span style="float:right;color:<?=$color?>;font-weight:600">
<?=$status?>
</span>

<div style="margin-top:8px">
<?php if($shortfall>0): ?>
You need <b>₹<?=number_format($dailyRequired)?></b> per day for next 
<b><?=$daysLeft?></b> days to reach your target.
<?php else: ?>
🎉 You have achieved your target. Great job!
<?php endif;?>
</div>
</div>

<?php if($achievedAmount == 0): ?>
<div style="color:#999;font-size:13px;margin-bottom:5px;">
No collections recorded yet for this month
</div>
<?php endif; ?>

<!-- Chart -->
<div class="card">
<div style="height:250px;">
<canvas id="chart"></canvas>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: ['Target', 'Achieved'],
        datasets: [{
            data: [<?= $effectiveTarget ?>, <?= $achievedAmount ?>],
            backgroundColor: ['#ec1670','#4cafef'],
            borderRadius: 10,
            barThickness: 60
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
       plugins: {
    legend: { display: false },
    tooltip: {
        callbacks: {
            label: function(c) {
                return '₹ ' + c.raw.toLocaleString();
            }
        }
    },
    datalabels: {
        anchor: 'end',
        align: 'top',
        formatter: function(value) {
            return '₹ ' + value.toLocaleString();
        }
    }
},
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(v){
                        return '₹ ' + v.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

</div>