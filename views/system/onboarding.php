<?php
if (!defined('APP_NAME')) {
    die('Unauthorized access.');
}

$roleName = trim((string)($_SESSION['role_name'] ?? 'Staff'));
$roleKey = strtolower($roleName);

$roleTips = [
    'super admin' => [
        'Own full CRM control: menu setup, user setup, and permission control.',
        'Give menu access to each user through role permissions.',
        'Monitor complete CRM activity from dashboard and system tools.',
    ],
    'hr' => [
        'Create, assign, and follow leads from first enquiry to closure.',
        'Set and monitor targets for all roles except Staff.',
        'Handle post-course follow-up: payment, interview help, student details, and certificate process.',
    ],
    'marketing' => [
        'Capture and maintain fresh leads and enquiries.',
        'Update follow-up outcomes on time.',
        'Track assigned monthly targets and conversion outcomes.',
    ],
    'corporate' => [
        'Work on lead follow-up and daily report flow similar to Front Office.',
        'Handle assigned leads and move qualified leads to next stage quickly.',
        'Keep follow-up notes clear so HR/Front Office can continue without delay.',
    ],
    'corporate executive' => [
        'Work on lead follow-up and daily report flow similar to Front Office.',
        'Handle assigned leads and move qualified leads to next stage quickly.',
        'Keep follow-up notes clear so HR/Front Office can continue without delay.',
    ],
    'front office' => [
        'Handle walk-ins/enquiries and registration handoff.',
        'Keep follow-ups updated for pending students.',
        'Coordinate with HR/Marketing for closures.',
    ],
    'staff' => [
        'Use assigned modules and keep records updated.',
        'Maintain follow-up discipline and status updates.',
        'Coordinate with reporting owner for daily closure.',
    ],
];

$activeTips = $roleTips[$roleKey] ?? $roleTips['staff'];

$rolePlaybooks = [
    'Super Admin' => [
        'daily' => [
            'Open dashboard and review all branch data, alerts, and pending issues.',
            'Create and manage menus, users, roles, and module visibility.',
            'Give menu access to users using permission settings (only required menus per role).',
            'Check Audit Logs to monitor who changed what and when.',
            'Resolve access problems quickly so teams can continue work.',
            'Run Backup and Health checks before major updates or risky changes.',
            'Track escalations from all roles and ensure final closure.',
        ],
        'kpi' => 'Full system control, correct permission mapping, and clear monitoring of all operations.',
    ],
    'HR' => [
        'daily' => [
            'Create new leads and ensure each lead has complete details.',
            'Assign leads to the right team member and track ownership.',
            'Follow leads daily and update call outcome, status, and next follow-up date.',
            'Set and maintain targets for all roles except Staff.',
            'Review target progress role-wise and push pending teams for closure.',
            'After student course and mock interview completion by Staff, continue HR follow-up.',
            'Track student payment status, interview support, and complete interview details.',
            'Initiate and complete certificate generation after all criteria are met.',
        ],
        'kpi' => 'Lead closure quality, target achievement tracking, and complete post-course student support.',
    ],
    'Marketing' => [
        'daily' => [
            'Add new leads with full contact details and source information.',
            'Contact fresh leads quickly and record their interest level.',
            'Update enquiry notes in simple words so any team member can understand.',
            'Schedule follow-ups and update the result after every call.',
            'Highlight hot or ready-to-convert leads to Front Office or HR immediately.',
            'Review daily target numbers and adjust effort on weak channels.',
        ],
        'kpi' => 'Good lead quality, quick response, and better conversion readiness.',
    ],
    'Corporate Executive' => [
        'daily' => [
            'Work assigned leads and follow up based on due date.',
            'Update every call outcome and next step clearly in CRM.',
            'Coordinate quickly with HR and Front Office for ready leads.',
            'Use daily report flow to record activity, registration updates, and hourly progress.',
            'Track assigned targets and close pending actions before day end.',
            'Escalate blocked leads with proper reason and expected help needed.',
        ],
        'kpi' => 'Follow-up consistency, clean updates, and timely lead movement.',
    ],
    'Front Office' => [
        'daily' => [
            'Handle walk-ins and calls politely and collect complete details.',
            'Confirm name, phone, course interest, and basic eligibility details.',
            'Create or update enquiry records without missing required fields.',
            'Help eligible candidates start registration draft correctly.',
            'Coordinate pending documents or payment points with candidate and team.',
            'Handoff complete and clear records so next team can continue without confusion.',
        ],
        'kpi' => 'Accurate first-time data capture and clean candidate handoff.',
    ],
    'Staff' => [
        'daily' => [
            'Work only your assigned queue and keep each record updated.',
            'After each task, update status, notes, and next action date.',
            'Close completed tasks before shift end and leave no blank status.',
            'Mark pending items with clear reason and expected closure date.',
            'Inform reporting owner early if you are blocked.',
            'Keep data clean so reports show correct numbers.',
        ],
        'kpi' => 'Consistent task closure and clean, reliable records.',
    ],
];

$roleFlowDocs = [
    'super_admin' => [
        'label' => 'Super Admin',
        'goal' => 'Control the full CRM system, manage access, and monitor all operations.',
        'flow' => [
            'Step 1: Login and open dashboard to view full branch-wise CRM data.',
            'Step 2: Check urgent alerts, pending issues, and unresolved escalations.',
            'Step 3: Create or update menu structure based on business need.',
            'Step 4: Create users and assign proper role for each user.',
            'Step 5: Give menu access through permission mapping (only required modules).',
            'Step 6: Verify access by role so users can see only allowed pages.',
            'Step 7: Review Audit Logs to monitor every major change.',
            'Step 8: Run Backup and Health checks before sensitive updates.',
            'Step 9: Monitor closure of blocked issues raised by HR/Front Office/Marketing/Staff/Corporate.',
            'Step 10: Close day with access review and critical issue summary.',
        ],
        'handoff' => 'Share critical issues with role owner including problem, permission impact, owner, and expected closure date.',
    ],
    'hr' => [
        'label' => 'HR',
        'goal' => 'Own lead lifecycle, manage targets, and handle post-course student follow-up.',
        'flow' => [
            'Step 1: Create lead with full details (name, contact, source, course interest, location).',
            'Step 2: Assign lead to the right team member and confirm ownership.',
            'Step 3: Follow lead daily and update status after each call or meeting.',
            'Step 4: Keep next follow-up date mandatory so no lead is missed.',
            'Step 5: Set monthly/weekly targets for all roles except Staff.',
            'Step 6: Track target achievement role-wise and follow pending performers.',
            'Step 7: After student completes course and mock interview by Staff, continue HR process.',
            'Step 8: Follow payment completion, interview support requests, and student interview updates.',
            'Step 9: Maintain complete student interview records in HR module.',
            'Step 10: Generate certificate after required checks are complete.',
            'Step 11: Close with final status and handoff notes for next support step.',
        ],
        'handoff' => 'Handoff must include lead/student status, last update, pending action, owner name, and target impact.',
    ],
    'marketing' => [
        'label' => 'Marketing',
        'goal' => 'Create a strong lead pipeline and keep every lead actively engaged.',
        'flow' => [
            'Step 1: Add fresh leads with complete contact details and lead source.',
            'Step 2: Contact new leads quickly and mark interest level.',
            'Step 3: Convert warm leads to enquiry with clear notes.',
            'Step 4: Create follow-up schedule and update outcomes daily.',
            'Step 5: Track channel performance and improve weak sources.',
            'Step 6: Highlight hot leads for immediate conversion support.',
            'Step 7: Review target progress and next-day plan before closing.',
        ],
        'handoff' => 'Share conversion-ready leads with Front Office or HR including all communication notes.',
    ],
    'corporate' => [
        'label' => 'Corporate Executive',
        'goal' => 'Handle assigned lead follow-up and keep daily report updates complete.',
        'flow' => [
            'Step 1: Open assigned leads and prioritize based on due follow-up date.',
            'Step 2: Contact leads and update response clearly after each interaction.',
            'Step 3: Keep next follow-up date mandatory for every pending lead.',
            'Step 4: Move interested leads to the next stage without delay.',
            'Step 5: Coordinate with HR and Front Office for conversion-ready leads.',
            'Step 6: Update daily report sections (activity, registration, hourly, follow-up).',
            'Step 7: Track target progress and highlight risks early.',
            'Step 8: Close day with pending list and handoff notes.',
        ],
        'handoff' => 'Share lead status, last response, next action date, and owner details with HR/Front Office.',
    ],
    'front_office' => [
        'label' => 'Front Office',
        'goal' => 'Ensure accurate enquiry intake and quick registration movement.',
        'flow' => [
            'Step 1: Attend walk-ins and calls promptly and professionally.',
            'Step 2: Capture full enquiry details without missing required fields.',
            'Step 3: Confirm candidate interest, program, and payment expectation.',
            'Step 4: Help eligible candidates start registration draft.',
            'Step 5: Track pending documents and update status daily.',
            'Step 6: Coordinate with Super Admin or HR for quick closure support.',
            'Step 7: Keep records clear so next team can continue without rework.',
        ],
        'handoff' => 'Handoff complete candidate profile with documents status, payment note, and next action.',
    ],
    'staff' => [
        'label' => 'Staff',
        'goal' => 'Keep assigned work updated accurately and on time.',
        'flow' => [
            'Step 1: Open assigned tasks and process them one by one.',
            'Step 2: After each action, update record status and simple notes.',
            'Step 3: Add next action date so tasks do not become stale.',
            'Step 4: Close completed tasks before shift end.',
            'Step 5: Mark pending tasks with reason and expected closure date.',
            'Step 6: Escalate blockers early to reporting owner with details.',
            'Step 7: Review your queue at end of day for any missing updates.',
        ],
        'handoff' => 'Share unresolved tasks daily with module owner including blocker reason and required support.',
    ],
];
?>

<style>
.onboard-wrap{max-width:1320px;margin:0 auto;display:grid;gap:14px;}
.onboard-card{background:#fff;border:1px solid #f0cddd;border-radius:14px;box-shadow:0 8px 22px rgba(15,23,42,.05);}
.onboard-hero{padding:20px 22px;background:linear-gradient(140deg,#fff8fc 0%,#fff 65%);}
.onboard-title{margin:0;color:#1f2a44;font-size:1.8rem !important;font-weight:800;display:flex;align-items:center;gap:10px;}
.onboard-title i{color:#e91e63;}
.onboard-sub{margin:8px 0 0;color:#5f6980;font-weight:600;}
.onboard-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;}
.onboard-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px;border:1px solid #eac6d8;background:#fff;color:#b01757;font-weight:800;text-decoration:none;}
.onboard-btn.primary{background:linear-gradient(135deg,#eb1f73,#d81b60);color:#fff;border-color:#d81b60;}
.onboard-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.onboard-box{padding:16px 18px;}
.onboard-h{margin:0 0 10px;color:#1f2a44;font-size:1.05rem !important;font-weight:800;}
.onboard-list{margin:0;padding-left:18px;color:#44506a;line-height:1.6;}
.onboard-role{display:inline-block;padding:6px 10px;border-radius:999px;background:#ffeaf3;color:#b71556;font-weight:800;font-size:.82rem;}
.onboard-table{width:100%;border-collapse:collapse;}
.onboard-table th,.onboard-table td{border:1px solid #f3d5e3;padding:10px 12px;vertical-align:top;}
.onboard-table th{background:#fff4f9;color:#9b2a61;font-size:.82rem !important;letter-spacing:.03em;text-transform:uppercase;}
.onboard-table td{color:#36435d;}
.onboard-roles{display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:12px;}
.onboard-rolecard{padding:14px;border:1px solid #f2d8e4;border-radius:12px;background:linear-gradient(180deg,#fff 0%,#fff9fc 100%);}
.onboard-rolecard h6{margin:0 0 8px;color:#1f2a44;font-size:1rem !important;font-weight:800;}
.onboard-kpi{margin-top:8px;color:#6a7287;font-weight:700;font-size:.88rem !important;}
.onboard-flow-grid{display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:12px;}
.onboard-flow-card{padding:14px;border:1px solid #f2d9e5;border-radius:12px;background:#fff;}
.onboard-flow-title{margin:0 0 8px;color:#1f2a44;font-weight:800;}
.onboard-flow-goal{margin:0 0 8px;color:#5f6980;font-weight:700;font-size:.9rem !important;}
.onboard-flow-handoff{margin-top:8px;color:#6d4c63;font-size:.88rem !important;font-weight:700;}
.onboard-download-grid{display:grid;grid-template-columns:repeat(3,minmax(200px,1fr));gap:10px;}
.onboard-download-card{padding:12px;border:1px solid #f2d9e5;border-radius:12px;background:#fffafc;}
.onboard-download-card h6{margin:0 0 8px;color:#1f2a44;font-weight:800;}
.onboard-download-row{display:flex;gap:8px;flex-wrap:wrap;}
@media (max-width: 900px){.onboard-grid{grid-template-columns:1fr;}}
@media (max-width: 1100px){.onboard-roles{grid-template-columns:1fr 1fr;}}
@media (max-width: 700px){.onboard-roles{grid-template-columns:1fr;}}
@media (max-width: 1100px){.onboard-flow-grid{grid-template-columns:1fr;}.onboard-download-grid{grid-template-columns:1fr 1fr;}}
@media (max-width: 700px){.onboard-download-grid{grid-template-columns:1fr;}}
</style>

<div class="container-fluid py-3">
    <div class="onboard-wrap">
        <div class="onboard-card onboard-hero">
            <h3 class="onboard-title"><i class="fas fa-book-open"></i>Onboarding Guide</h3>
            <p class="onboard-sub">Role-wise CRM usage, access visibility, and operating flow.</p>
            <div style="margin-top:10px;">
                <span class="onboard-role">Your role: <?= htmlspecialchars($roleName) ?></span>
            </div>
            <div class="onboard-actions">
                <a href="index.php?page=system/onboarding_export&download=pdf" class="onboard-btn primary">
                    <i class="fas fa-file-pdf"></i>Download SOP PDF
                </a>
                <a href="index.php?page=system/onboarding_export&download=html" class="onboard-btn">
                    <i class="fas fa-file-alt"></i>Download SOP HTML
                </a>
            </div>
        </div>

        <div class="onboard-grid">
            <div class="onboard-card onboard-box">
                <h5 class="onboard-h">Your Focus</h5>
                <ul class="onboard-list">
                    <?php foreach ($activeTips as $tip): ?>
                        <li><?= htmlspecialchars($tip) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="onboard-card onboard-box">
                <h5 class="onboard-h">Daily Flow</h5>
                <ul class="onboard-list">
                    <li>Capture lead/enquiry updates immediately after interaction.</li>
                    <li>Close or schedule follow-ups before end of day.</li>
                    <li>Convert qualified enquiries to registration/draft properly.</li>
                    <li>Keep payment, target, and status data consistent.</li>
                </ul>
            </div>
        </div>

        <div class="onboard-card onboard-box">
            <h5 class="onboard-h">Access Matrix</h5>
            <div style="overflow:auto;">
                <table class="onboard-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Super Admin</th>
                            <th>HR</th>
                            <th>Front Office/Marketing/Corporate/Staff</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Onboarding Guide</td>
                            <td>Yes</td>
                            <td>Yes</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>Audit Logs</td>
                            <td>Yes</td>
                            <td>Based on assigned permissions</td>
                            <td>Based on assigned permissions</td>
                        </tr>
                        <tr>
                            <td>Backup &amp; Health</td>
                            <td>Yes</td>
                            <td>No</td>
                            <td>No</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="onboard-card onboard-box">
            <h5 class="onboard-h">Role Playbooks</h5>
            <div class="onboard-roles">
                <?php foreach ($rolePlaybooks as $playbookRole => $playbook): ?>
                    <div class="onboard-rolecard">
                        <h6><?= htmlspecialchars($playbookRole) ?></h6>
                        <ul class="onboard-list">
                            <?php foreach (($playbook['daily'] ?? []) as $step): ?>
                                <li><?= htmlspecialchars((string)$step) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="onboard-kpi">KPI focus: <?= htmlspecialchars((string)($playbook['kpi'] ?? '')) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="onboard-card onboard-box">
            <h5 class="onboard-h">Role-Wise CRM Flow</h5>
            <div class="onboard-flow-grid">
                <?php foreach ($roleFlowDocs as $roleSlug => $flowDoc): ?>
                    <div class="onboard-flow-card">
                        <h6 class="onboard-flow-title"><?= htmlspecialchars((string)$flowDoc['label']) ?></h6>
                        <p class="onboard-flow-goal">Goal: <?= htmlspecialchars((string)$flowDoc['goal']) ?></p>
                        <ul class="onboard-list">
                            <?php foreach (($flowDoc['flow'] ?? []) as $step): ?>
                                <li><?= htmlspecialchars((string)$step) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="onboard-flow-handoff">Handoff: <?= htmlspecialchars((string)$flowDoc['handoff']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="onboard-card onboard-box">
            <h5 class="onboard-h">Download SOP By Role</h5>
            <div class="onboard-download-grid">
                <?php foreach ($roleFlowDocs as $roleSlug => $flowDoc): ?>
                    <div class="onboard-download-card">
                        <h6><?= htmlspecialchars((string)$flowDoc['label']) ?></h6>
                        <div class="onboard-download-row">
                            <a class="onboard-btn primary" href="index.php?page=system/onboarding_export&download=pdf&role=<?= urlencode((string)$roleSlug) ?>">
                                <i class="fas fa-file-pdf"></i>PDF
                            </a>
                            <a class="onboard-btn" href="index.php?page=system/onboarding_export&download=html&role=<?= urlencode((string)$roleSlug) ?>">
                                <i class="fas fa-file-alt"></i>HTML
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
