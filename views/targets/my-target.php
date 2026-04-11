<?php
if (!defined('APP_NAME')) die("Unauthorized");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$userId   = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$currentYear  = (int)($_GET['year'] ?? date('Y'));
$currentMonth = (int)($_GET['month'] ?? date('n'));

$monthNames = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

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
$stmt->execute([$branchId, $userId]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $paymentsMap[$p['y'] . '-' . $p['m']] = (float)$p['total'];
}

/* =========================
   TARGET
========================= */
$stmt = $pdo->prepare("
SELECT * FROM monthly_targets
WHERE branch_id=? AND user_id=? AND target_year=? AND target_month=?
LIMIT 1
");
$stmt->execute([$branchId, $userId, $currentYear, $currentMonth]);
$currentTarget = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   ACHIEVED
========================= */
$key = $currentYear . '-' . $currentMonth;
$achievedAmount = $paymentsMap[$key] ?? 0;

/* =========================
   CARRY
========================= */
$stmtPrev = $pdo->prepare("
SELECT target_year,target_month,target_amount
FROM monthly_targets
WHERE branch_id=? AND user_id=?
AND (target_year < ? OR (target_year=? AND target_month < ?))
ORDER BY target_year,target_month
");
$stmtPrev->execute([$branchId, $userId, $currentYear, $currentYear, $currentMonth]);

$carry = 0;
foreach ($stmtPrev->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $k = $row['target_year'] . '-' . $row['target_month'];
    $ach = $paymentsMap[$k] ?? 0;
    $target = (float)$row['target_amount'];

    $effective = $target + $carry;
    $carry = ($ach >= $effective) ? 0 : ($effective - $ach);
}

$baseTarget = (float)($currentTarget['target_amount'] ?? 0);
$effectiveTarget = $baseTarget + $carry;

$shortfall = max($effectiveTarget - $achievedAmount, 0);
$excess = max($achievedAmount - $effectiveTarget, 0);
$progress = $effectiveTarget > 0 ? ($achievedAmount / $effectiveTarget) * 100 : 0;
$progressClamped = max(0, min($progress, 100));

/* =========================
   SMART INSIGHTS
========================= */
$daysInMonth = (int)date('t');
$currentDay = (int)date('d');
$daysLeft = max(1, $daysInMonth - $currentDay);
$dailyRequired = $shortfall > 0 ? $shortfall / $daysLeft : 0;

$status = 'Not Started';
$statusTone = 'is-danger';

if ($achievedAmount >= $effectiveTarget && $effectiveTarget > 0) {
    $status = 'Achieved';
    $statusTone = 'is-success';
} elseif ($achievedAmount > 0) {
    $status = 'In Progress';
    $statusTone = 'is-warning';
}
?>

<div class="target-shell">
  <div class="target-hero">
    <section class="hero-card">
      <div class="hero-eyebrow">Personal Target Snapshot</div>
      <h1 class="hero-title"><?= h($monthNames[$currentMonth] . ' ' . $currentYear) ?></h1>
      <div class="hero-copy">
        Track your approved collection performance against the live target for this period, including carry-forward impact and the remaining pace needed to close strong.
      </div>
      <div class="hero-meta">
        <div class="hero-meta-card">
          <div class="hero-meta-label">Effective Target</div>
          <div class="hero-meta-value"><?= inr_symbol() ?> <?= number_format($effectiveTarget, 0) ?></div>
        </div>
        <div class="hero-meta-card">
          <div class="hero-meta-label">Achieved So Far</div>
          <div class="hero-meta-value"><?= inr_symbol() ?> <?= number_format($achievedAmount, 0) ?></div>
        </div>
        <div class="hero-meta-card">
          <div class="hero-meta-label">Current Status</div>
          <div class="hero-meta-value"><?= h($status) ?></div>
        </div>
      </div>
    </section>

    <aside class="hero-card toolbar-card">
      <div class="toolbar-title">View Period</div>
      <form method="GET" action="index.php" class="toolbar-form">
        <input type="hidden" name="page" value="targets/my-target">
        <div class="toolbar-field">
          <label for="month">Month</label>
          <select name="month" id="month">
            <?php foreach($monthNames as $monthNo => $monthLabel): ?>
              <option value="<?= (int)$monthNo ?>" <?= $currentMonth === (int)$monthNo ? 'selected' : '' ?>>
                <?= h($monthLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="toolbar-field">
          <label for="year">Year</label>
          <select name="year" id="year">
            <?php for($year = (int)date('Y') - 3; $year <= (int)date('Y') + 1; $year++): ?>
              <option value="<?= $year ?>" <?= $currentYear === $year ? 'selected' : '' ?>>
                <?= $year ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="toolbar-actions">
          <div class="crm-icon-actions">
            <button type="submit" class="crm-icon-btn is-primary" data-mobile-label="Apply" data-modern-tooltip="Apply filters" aria-label="Apply filters">
              <i class="fas fa-filter"></i>
            </button>
            <a href="index.php?page=targets/my-target" class="crm-icon-btn is-muted" data-mobile-label="Reset" data-modern-tooltip="Reset filters" aria-label="Reset filters">
              <i class="fas fa-rotate-left"></i>
            </a>
          </div>
        </div>
      </form>
    </aside>
  </div>

  <section class="target-grid">
    <article class="metric-card highlight">
      <div class="metric-label">Achieved</div>
      <div class="metric-value"><?= inr_symbol() ?> <?= number_format($achievedAmount, 0) ?></div>
      <div class="metric-note">Approved collections credited to you for the selected month.</div>
    </article>

    <article class="metric-card">
      <div class="metric-label">Target</div>
      <div class="metric-value"><?= inr_symbol() ?> <?= number_format($effectiveTarget, 0) ?></div>
      <div class="metric-note">Base target plus carry-forward still active for this period.</div>
    </article>

    <article class="metric-card">
      <div class="metric-label">Carry Forward</div>
      <div class="metric-value"><?= inr_symbol() ?> <?= number_format($carry, 0) ?></div>
      <div class="metric-note">Previous pending target amount rolled into this month.</div>
    </article>

    <article class="metric-card">
      <div class="metric-label">Shortfall</div>
      <div class="metric-value"><?= inr_symbol() ?> <?= number_format($shortfall, 0) ?></div>
      <div class="metric-note"><?= $shortfall > 0 ? 'Remaining amount needed to close the month on target.' : 'No shortfall for this period.' ?></div>
    </article>
  </section>

  <section class="content-grid">
    <div class="surface-card">
      <div class="section-head">
        <div>
          <div class="section-title">Target Progress</div>
          <div class="section-copy">A quick view of where you stand against the effective target for the selected month.</div>
        </div>
      </div>

      <div class="progress-panel">
        <div>
          <div class="progress-meta">
            <span>Collected vs Target</span>
            <span><?= number_format($progress, 1) ?>%</span>
          </div>

          <div class="progress">
            <div class="progress-bar" style="width:<?= $progressClamped ?>%"></div>
          </div>

          <div class="progress-foot">
            <span>Achieved: <?= inr_symbol() ?> <?= number_format($achievedAmount, 0) ?></span>
            <span>Effective Target: <?= inr_symbol() ?> <?= number_format($effectiveTarget, 0) ?></span>
            <span>Excess: <?= inr_symbol() ?> <?= number_format($excess, 0) ?></span>
          </div>
        </div>

        <div class="progress-ring" style="--ring-pct:<?= $progressClamped ?>%;">
          <div class="progress-ring-inner">
            <div class="progress-ring-value"><?= number_format($progress, 0) ?>%</div>
            <div class="progress-ring-label">Completion</div>
          </div>
        </div>
      </div>
    </div>

    <div class="surface-card">
      <div class="section-head">
        <div>
          <div class="section-title">Performance Insight</div>
          <div class="section-copy">A concise reading of your current pace and what remains to be closed.</div>
        </div>
        <div class="status-pill <?= h($statusTone) ?>"><?= h($status) ?></div>
      </div>

      <div class="insight-note">
        <?php if($shortfall > 0): ?>
          You need approximately <strong><?= inr_symbol() ?> <?= number_format($dailyRequired, 0) ?></strong> per day over the next <strong><?= (int)$daysLeft ?></strong> days to hit the effective target.
        <?php else: ?>
          You have already met the effective target for this period. Any additional approved collection now strengthens your overachievement.
        <?php endif; ?>
      </div>

      <div class="insight-list">
        <div class="insight-item">
          <div class="insight-label">Base Target</div>
          <div class="insight-value"><?= inr_symbol() ?> <?= number_format($baseTarget, 0) ?></div>
        </div>
        <div class="insight-item">
          <div class="insight-label">Days Left</div>
          <div class="insight-value"><?= (int)$daysLeft ?></div>
        </div>
        <div class="insight-item">
          <div class="insight-label">Collected</div>
          <div class="insight-value"><?= inr_symbol() ?> <?= number_format($achievedAmount, 0) ?></div>
        </div>
        <div class="insight-item">
          <div class="insight-label">Excess</div>
          <div class="insight-value"><?= inr_symbol() ?> <?= number_format($excess, 0) ?></div>
        </div>
      </div>

      <?php if($achievedAmount == 0): ?>
        <div class="empty-state">
          No approved collections have been recorded for this month yet. Once collections start getting credited, this panel will update automatically.
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="surface-card" style="margin-top:18px;">
    <div class="section-head">
      <div>
        <div class="section-title">Target vs Achieved</div>
        <div class="section-copy">A simple visual comparison of the live target amount and your approved collection performance.</div>
      </div>
    </div>
    <div class="chart-wrap">
      <canvas id="chart"></canvas>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: ['Target', 'Achieved'],
        datasets: [{
            data: [<?= $effectiveTarget ?>, <?= $achievedAmount ?>],
            backgroundColor: ['#e61b72', '#32b3a8'],
            borderRadius: 10,
            borderSkipped: false,
            barThickness: 58
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#111827',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                padding: 12,
                displayColors: false,
                callbacks: {
                    label: function(c) {
                        return 'Rs ' + c.raw.toLocaleString();
                    }
                }
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: '#6b7280',
                    font: {
                        size: 12,
                        weight: '600'
                    }
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(107, 114, 128, 0.12)'
                },
                ticks: {
                    color: '#6b7280',
                    callback: function(v) {
                        return 'Rs ' + Number(v).toLocaleString();
                    }
                }
            }
        }
    }
});
</script>



