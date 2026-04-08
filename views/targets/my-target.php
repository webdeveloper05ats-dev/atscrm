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

<style>
:root{
--pink:#e61b72;
--pink-dark:#b91558;
--pink-soft:#fff1f7;
--ink:#1f2937;
--muted:#6b7280;
--border:#f1d9e4;
--surface:#ffffff;
--surface-alt:#fff9fc;
--success:#1f9d68;
--warning:#cc7a00;
--danger:#d6456b;
--shadow:0 20px 45px rgba(31, 41, 55, 0.08);
}

.target-shell{
background:
radial-gradient(circle at top left, rgba(230, 27, 114, 0.10), transparent 26%),
radial-gradient(circle at top right, rgba(185, 21, 88, 0.08), transparent 24%),
linear-gradient(180deg, #fff9fc 0%, #f7f3f6 100%);
padding:22px;
border-radius:28px;
border:1px solid #f3dde8;
box-shadow:var(--shadow);
}

.target-hero{
display:grid;
grid-template-columns:minmax(0, 1.5fr) minmax(280px, 0.9fr);
gap:18px;
margin-bottom:18px;
}

.hero-card,
.surface-card{
position:relative;
background:var(--surface);
border:1px solid var(--border);
border-radius:24px;
box-shadow:0 12px 30px rgba(31, 41, 55, 0.05);
overflow:hidden;
}

.hero-card{
padding:26px;
background:
linear-gradient(135deg, rgba(230, 27, 114, 0.98), rgba(185, 21, 88, 0.96)),
linear-gradient(135deg, #e61b72, #b91558);
color:#fff;
}

.hero-card::after{
content:"";
position:absolute;
right:-70px;
top:-70px;
width:220px;
height:220px;
border-radius:50%;
background:rgba(255,255,255,0.10);
}

.hero-eyebrow{
display:inline-flex;
align-items:center;
gap:8px;
padding:8px 12px;
border-radius:999px;
background:rgba(255,255,255,0.14);
font-size:12px;
font-weight:700;
letter-spacing:.08em;
text-transform:uppercase;
}

.hero-title{
margin:14px 0 8px;
font-size:32px;
line-height:1.05;
font-weight:800;
letter-spacing:-0.03em;
}

.hero-copy{
max-width:640px;
font-size:14px;
line-height:1.7;
color:rgba(255,255,255,0.86);
}

.hero-meta{
display:flex;
gap:12px;
flex-wrap:wrap;
margin-top:18px;
}

.hero-meta-card{
min-width:150px;
padding:14px 16px;
border-radius:18px;
background:rgba(255,255,255,0.12);
backdrop-filter:blur(4px);
}

.hero-meta-label{
font-size:11px;
text-transform:uppercase;
letter-spacing:.08em;
color:rgba(255,255,255,0.76);
margin-bottom:6px;
}

.hero-meta-value{
font-size:19px;
font-weight:800;
}

.toolbar-card{
padding:22px;
display:flex;
flex-direction:column;
gap:16px;
background:linear-gradient(180deg, #ffffff 0%, #fff8fb 100%);
}

.toolbar-title{
font-size:13px;
font-weight:800;
letter-spacing:.08em;
text-transform:uppercase;
color:var(--muted);
}

.toolbar-form{
display:grid;
grid-template-columns:1fr 1fr;
gap:12px;
}

.toolbar-field label{
display:block;
margin-bottom:6px;
font-size:12px;
font-weight:700;
color:var(--muted);
}

.toolbar-field select{
width:100%;
min-height:44px;
padding:10px 12px;
border:1px solid var(--border);
border-radius:14px;
background:#fff;
font-size:14px;
color:var(--ink);
outline:none;
}

.toolbar-field select:focus{
border-color:var(--pink);
box-shadow:0 0 0 4px rgba(230, 27, 114, 0.10);
}

.toolbar-actions{
display:flex;
gap:10px;
}

.toolbar-btn{
display:inline-flex;
align-items:center;
justify-content:center;
min-height:44px;
padding:0 16px;
border-radius:14px;
border:none;
text-decoration:none;
font-size:14px;
font-weight:700;
cursor:pointer;
transition:all .2s ease;
}

.toolbar-btn.primary{
background:linear-gradient(135deg, var(--pink), var(--pink-dark));
color:#fff;
box-shadow:0 12px 26px rgba(230, 27, 114, 0.22);
}

.toolbar-btn.secondary{
background:#fff;
color:var(--ink);
border:1px solid var(--border);
}

.toolbar-btn:hover{
transform:translateY(-1px);
}

.target-grid{
display:grid;
grid-template-columns:repeat(4, minmax(0, 1fr));
gap:14px;
margin-bottom:18px;
}

.metric-card{
padding:18px;
background:var(--surface);
border:1px solid var(--border);
border-radius:20px;
box-shadow:0 10px 24px rgba(31, 41, 55, 0.04);
}

.metric-card.highlight{
background:linear-gradient(135deg, rgba(230, 27, 114, 0.96), rgba(185, 21, 88, 0.94));
color:#fff;
border-color:transparent;
}

.metric-label{
font-size:12px;
font-weight:800;
text-transform:uppercase;
letter-spacing:.08em;
color:var(--muted);
margin-bottom:10px;
}

.metric-card.highlight .metric-label{
color:rgba(255,255,255,0.74);
}

.metric-value{
font-size:30px;
line-height:1;
font-weight:800;
letter-spacing:-0.03em;
color:var(--ink);
}

.metric-card.highlight .metric-value{
color:#fff;
}

.metric-note{
margin-top:8px;
font-size:13px;
color:var(--muted);
line-height:1.5;
}

.metric-card.highlight .metric-note{
color:rgba(255,255,255,0.84);
}

.content-grid{
display:grid;
grid-template-columns:minmax(0, 1.15fr) minmax(320px, 0.85fr);
gap:18px;
}

.surface-card{
padding:22px;
}

.section-head{
display:flex;
align-items:flex-start;
justify-content:space-between;
gap:12px;
margin-bottom:18px;
}

.section-title{
font-size:20px;
font-weight:800;
color:var(--ink);
letter-spacing:-0.02em;
}

.section-copy{
margin-top:5px;
font-size:13px;
line-height:1.6;
color:var(--muted);
}

.status-pill{
display:inline-flex;
align-items:center;
gap:8px;
padding:10px 14px;
border-radius:999px;
font-size:13px;
font-weight:800;
white-space:nowrap;
}

.status-pill::before{
content:"";
width:10px;
height:10px;
border-radius:50%;
background:currentColor;
}

.status-pill.is-success{
background:rgba(31, 157, 104, 0.12);
color:var(--success);
}

.status-pill.is-warning{
background:rgba(204, 122, 0, 0.12);
color:var(--warning);
}

.status-pill.is-danger{
background:rgba(214, 69, 107, 0.12);
color:var(--danger);
}

.progress-panel{
display:grid;
grid-template-columns:minmax(0, 1fr) 160px;
gap:18px;
align-items:center;
}

.progress{
height:16px;
background:#f3dbe5;
border-radius:999px;
overflow:hidden;
}

.progress-bar{
height:100%;
min-width:8px;
max-width:100%;
background:linear-gradient(90deg, var(--pink), #ff5ba9);
border-radius:999px;
}

.progress-meta{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:10px;
gap:10px;
font-size:14px;
font-weight:700;
color:var(--ink);
}

.progress-foot{
margin-top:12px;
display:flex;
justify-content:space-between;
gap:14px;
flex-wrap:wrap;
font-size:13px;
color:var(--muted);
}

.progress-ring{
display:flex;
align-items:center;
justify-content:center;
width:150px;
height:150px;
margin-left:auto;
border-radius:50%;
background:conic-gradient(var(--pink) 0deg, var(--pink) <?= $progressClamped ?>%, #f5d8e4 <?= $progressClamped ?>%, #f5d8e4 100%);
position:relative;
}

.progress-ring::after{
content:"";
position:absolute;
inset:16px;
background:#fff;
border-radius:50%;
}

.progress-ring-inner{
position:relative;
z-index:1;
text-align:center;
}

.progress-ring-value{
font-size:28px;
font-weight:800;
color:var(--ink);
line-height:1;
}

.progress-ring-label{
margin-top:6px;
font-size:11px;
font-weight:700;
letter-spacing:.08em;
text-transform:uppercase;
color:var(--muted);
}

.insight-list{
display:grid;
grid-template-columns:1fr 1fr;
gap:12px;
margin-top:16px;
}

.insight-item{
padding:14px 16px;
border-radius:16px;
background:var(--surface-alt);
border:1px solid var(--border);
}

.insight-label{
font-size:11px;
text-transform:uppercase;
letter-spacing:.08em;
font-weight:800;
color:var(--muted);
margin-bottom:8px;
}

.insight-value{
font-size:18px;
font-weight:800;
color:var(--ink);
}

.insight-note{
margin-top:8px;
font-size:13px;
line-height:1.6;
color:var(--muted);
}

.empty-state{
margin-top:16px;
padding:14px 16px;
border-radius:16px;
border:1px dashed #f1c5d8;
background:var(--pink-soft);
color:#8b4b66;
font-size:13px;
}

.chart-wrap{
height:320px;
}

@media (max-width: 1100px){
  .target-hero,
  .content-grid,
  .progress-panel{
    grid-template-columns:1fr;
  }

  .progress-ring{
    margin:8px auto 0;
  }
}

@media (max-width: 900px){
  .target-grid{
    grid-template-columns:repeat(2, minmax(0, 1fr));
  }

  .toolbar-form{
    grid-template-columns:1fr;
  }
}

@media (max-width: 640px){
  .target-shell{
    padding:14px;
    border-radius:22px;
  }

  .hero-card,
  .toolbar-card,
  .surface-card,
  .metric-card{
    padding:16px;
    border-radius:18px;
  }

  .hero-title{
    font-size:25px;
  }

  .hero-meta{
    flex-direction:column;
  }

  .hero-meta-card{
    width:100%;
  }

  .target-grid,
  .insight-list{
    grid-template-columns:1fr;
  }

  .toolbar-actions{
    flex-direction:column;
  }

  .toolbar-btn{
    width:100%;
  }

  .section-head{
    flex-direction:column;
  }

  .metric-value{
    font-size:25px;
  }

  .chart-wrap{
    height:260px;
  }
}

@media (max-width: 1024px){
  .crm-icon-actions .crm-icon-btn[data-mobile-label]{
    width:auto !important;
    min-width:64px !important;
    height:auto !important;
    min-height:40px !important;
    padding:6px 8px !important;
    display:inline-flex !important;
    flex-direction:column !important;
    align-items:center !important;
    justify-content:center !important;
    gap:3px !important;
    border-radius:10px !important;
  }

  .crm-icon-actions .crm-icon-btn[data-mobile-label]::after{
    content:attr(data-mobile-label) !important;
    position:static !important;
    display:block !important;
    opacity:1 !important;
    visibility:visible !important;
    transform:none !important;
    background:none !important;
    border:0 !important;
    box-shadow:none !important;
    padding:0 !important;
    margin:0 !important;
    font-size:10px !important;
    line-height:1.1 !important;
    font-weight:700 !important;
    letter-spacing:.1px !important;
    color:currentColor !important;
    white-space:nowrap !important;
  }
}

@media (hover: none), (pointer: coarse){
  .crm-icon-actions .crm-icon-btn[data-mobile-label]{
    width:auto !important;
    min-width:64px !important;
    height:auto !important;
    min-height:40px !important;
    padding:6px 8px !important;
    display:inline-flex !important;
    flex-direction:column !important;
    align-items:center !important;
    justify-content:center !important;
    gap:3px !important;
    border-radius:10px !important;
  }

  .crm-icon-actions .crm-icon-btn[data-mobile-label]::after{
    content:attr(data-mobile-label) !important;
    position:static !important;
    display:block !important;
    opacity:1 !important;
    visibility:visible !important;
    transform:none !important;
    background:none !important;
    border:0 !important;
    box-shadow:none !important;
    padding:0 !important;
    margin:0 !important;
    font-size:10px !important;
    line-height:1.1 !important;
    font-weight:700 !important;
    letter-spacing:.1px !important;
    color:currentColor !important;
    white-space:nowrap !important;
  }
}

/* =====================================================
GLOBAL TYPOGRAPHY STYLECSS SYNC
font-family + font-size + font-weight only
===================================================== */
:where(body,button,input,select,textarea,label,span,p,h1,h2,h3,h4,h5,h6,a,div){
  font-family:'Poppins',sans-serif !important;
}
:where(h1,.h1,.page-title,.crm-page-title,.dashboard-header h2){font-size:clamp(2rem, 2.5vw, 2.4rem) !important;font-weight:700 !important;}
:where(h2,.h2,.section-title){font-size:clamp(1.6rem, 2vw, 2rem) !important;font-weight:600 !important;}
:where(h3,.h3,.card-header,.table-title){font-size:clamp(1.3rem, 1.6vw, 1.5rem) !important;font-weight:600 !important;}
:where(h4,.h4){font-size:1.2rem !important;font-weight:500 !important;}
:where(h5,.h5){font-size:1rem !important;font-weight:500 !important;}
:where(h6,.h6){font-size:0.9rem !important;font-weight:500 !important;}
:where(body){font-size:1rem !important;}
:where(p,.text-body,li,td,.text-muted,.help-text,.form-text,.small,small,.secondary-text){font-size:0.95rem !important;font-weight:400 !important;}
:where(.small,small,.text-muted,.help-text,.form-text,.att-sub,.crm-note){font-size:0.85rem !important;font-weight:400 !important;}
:where(label,.form-label){font-size:0.85rem !important;font-weight:500 !important;}
:where(input,select,textarea,.form-control,.form-select){font-size:0.95rem !important;font-weight:400 !important;}
:where(input::placeholder,textarea::placeholder){font-weight:400 !important;}
:where(button,.btn,.dt-button,.crm-action-btn,.crm-icon-btn,.btn-icon-only,.action-btn,.targets-btn-icon,.iso-report-btn,.iso-report-action-btn){font-size:0.9rem !important;font-weight:600 !important;}
:where(.btn[data-mobile-label],.btn-icon-only[data-mobile-label],.action-btn[data-mobile-label],.crm-icon-btn[data-mobile-label],.targets-btn-icon[data-mobile-label],.iso-report-icon-btn[data-mobile-label],.iso-report-action-btn[data-mobile-label])::after{font-size:0.75rem !important;font-weight:600 !important;}
:where(.table th,.crm-table th,.dataTables_wrapper th,th){font-size:0.75rem !important;font-weight:600 !important;}
:where(.table td,.dataTables_wrapper tbody td){font-size:0.9rem !important;}
:where(.dataTables_wrapper .dataTables_info){font-size:0.85rem !important;font-weight:400 !important;}
:where(.dataTables_wrapper .paginate_button){font-size:0.9rem !important;font-weight:600 !important;}
:where(.badge,.status-badge,.crm-status-badge,.status-pill,.badge-status,[data-status],.tooltip,.ui-tooltip,.floating-ui-tooltip__bubble){font-weight:600 !important;}
</style>

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
          <div class="hero-meta-value">Rs <?= number_format($effectiveTarget, 0) ?></div>
        </div>
        <div class="hero-meta-card">
          <div class="hero-meta-label">Achieved So Far</div>
          <div class="hero-meta-value">Rs <?= number_format($achievedAmount, 0) ?></div>
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
      <div class="metric-value">Rs <?= number_format($achievedAmount, 0) ?></div>
      <div class="metric-note">Approved collections credited to you for the selected month.</div>
    </article>

    <article class="metric-card">
      <div class="metric-label">Target</div>
      <div class="metric-value">Rs <?= number_format($effectiveTarget, 0) ?></div>
      <div class="metric-note">Base target plus carry-forward still active for this period.</div>
    </article>

    <article class="metric-card">
      <div class="metric-label">Carry Forward</div>
      <div class="metric-value">Rs <?= number_format($carry, 0) ?></div>
      <div class="metric-note">Previous pending target amount rolled into this month.</div>
    </article>

    <article class="metric-card">
      <div class="metric-label">Shortfall</div>
      <div class="metric-value">Rs <?= number_format($shortfall, 0) ?></div>
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
            <span>Achieved: Rs <?= number_format($achievedAmount, 0) ?></span>
            <span>Effective Target: Rs <?= number_format($effectiveTarget, 0) ?></span>
            <span>Excess: Rs <?= number_format($excess, 0) ?></span>
          </div>
        </div>

        <div class="progress-ring">
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
          You need approximately <strong>Rs <?= number_format($dailyRequired, 0) ?></strong> per day over the next <strong><?= (int)$daysLeft ?></strong> days to hit the effective target.
        <?php else: ?>
          You have already met the effective target for this period. Any additional approved collection now strengthens your overachievement.
        <?php endif; ?>
      </div>

      <div class="insight-list">
        <div class="insight-item">
          <div class="insight-label">Base Target</div>
          <div class="insight-value">Rs <?= number_format($baseTarget, 0) ?></div>
        </div>
        <div class="insight-item">
          <div class="insight-label">Days Left</div>
          <div class="insight-value"><?= (int)$daysLeft ?></div>
        </div>
        <div class="insight-item">
          <div class="insight-label">Collected</div>
          <div class="insight-value">Rs <?= number_format($achievedAmount, 0) ?></div>
        </div>
        <div class="insight-item">
          <div class="insight-label">Excess</div>
          <div class="insight-value">Rs <?= number_format($excess, 0) ?></div>
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
