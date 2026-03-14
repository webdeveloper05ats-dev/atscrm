<?php
// =====================================
// Enquiries - Followups (Tabs + Table + Modal History)
// Slug: enquiries/followups
// File: views/enquiries/followups.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

// ------------------------------------
// Helpers
// ------------------------------------
if (!function_exists('h')) {
    function h($v){
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function badge($text, $type='default'){
    $map = [
        'default' => ['#607d8b', '#eef2f5'],
        'pink'    => ['#e91e63', '#ffe9f0'],
        'orange'  => ['#ff9800', '#fff4e5'],
        'green'   => ['#2e7d32', '#e8f5e9'],
        'gray'    => ['#455a64', '#eceff1'],
        'red'     => ['#d32f2f', '#ffebee'],
    ];
    $c  = $map[$type][0] ?? '#607d8b';
    $bg = $map[$type][1] ?? '#eef2f5';
    return '<span class="tag" style="color:'.$c.';background:'.$bg.';">'.h($text).'</span>';
}

function detectFileType(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $audio = ['mp3','wav','m4a','aac','ogg'];
    $img   = ['jpg','jpeg','png','webp','gif'];
    $video = ['mp4','mov','avi','mkv','webm'];
    $doc   = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt'];

    if (in_array($ext, $audio, true)) return 'audio';
    if (in_array($ext, $img, true)) return 'image';
    if (in_array($ext, $video, true)) return 'video';
    if (in_array($ext, $doc, true)) return 'document';
    return 'other';
}

function uploadManyFiles(array $files, string $folder): array {
    $out = [];
    if (!isset($files['name']) || !is_array($files['name'])) return $out;

    $baseDir = __DIR__ . '/../../uploads/' . $folder;
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0777, true);
    }

    $allowed = ['jpg','jpeg','png','webp','gif','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','mp3','wav','m4a','aac','ogg','mp4','mov','avi','mkv','webm'];

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

        $orig = $files['name'][$i];
        $tmp  = $files['tmp_name'][$i];
        $size = (int)($files['size'][$i] ?? 0);

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) continue;

        $newName = date('YmdHis') . '_' . rand(1000,9999) . '.' . $ext;
        $dest = $baseDir . '/' . $newName;

        if (move_uploaded_file($tmp, $dest)) {
            $out[] = [
                'path'     => 'uploads/' . $folder . '/' . $newName,
                'original' => $orig,
                'size'     => $size,
                'type'     => detectFileType($orig)
            ];
        }
    }

    return $out;
}

// ------------------------------------
// Session & Role scope
// ------------------------------------
$success = "";
$error   = "";

$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$roleName = $_SESSION['role_name'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$canAllBranches = 0;
try {
    $r = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $r->execute([$roleId]);
    $canAllBranches = (int)($r->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

$isVerifier = in_array($roleName, ['Super Admin', 'HR'], true);

// ====================================
// AJAX (HTML) for Modals
// ====================================
$isAjax = isset($_GET['ajax']) && (int)$_GET['ajax'] === 1;

if ($isAjax) {
    $action = $_GET['action'] ?? '';

    // --------------------------------
    // Enquiry full history modal
    // --------------------------------
    if ($action === 'enquiry_history') {
        $enquiryId = (int)($_GET['enquiry_id'] ?? 0);
        if ($enquiryId <= 0) {
            echo "<div class='muted'>Invalid enquiry.</div>";
            exit;
        }

        if ($canAllBranches !== 1 && $branchId > 0) {
            $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? AND branch_id=? LIMIT 1");
            $st->execute([$enquiryId, $branchId]);
        } else {
            $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? LIMIT 1");
            $st->execute([$enquiryId]);
        }

        $enq = $st->fetch(PDO::FETCH_ASSOC);
        if (!$enq) {
            echo "<div class='muted'>Enquiry not found.</div>";
            exit;
        }

        $st = $pdo->prepare("
            SELECT 
                f.*, 
                u.name AS created_by_name,
                vb.name AS verified_by_name
            FROM enquiry_followups f
            LEFT JOIN users u ON u.id = f.created_by
            LEFT JOIN users vb ON vb.id = f.verified_by
            WHERE f.enquiry_id = ?
            ORDER BY f.followup_date DESC, f.followup_time DESC, f.id DESC
        ");
        $st->execute([$enquiryId]);
        $fups = $st->fetchAll(PDO::FETCH_ASSOC);

        $filesBy = [];
        if (!empty($fups)) {
            $ids = array_map(fn($x) => (int)$x['id'], $fups);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $stf = $pdo->prepare("SELECT * FROM enquiry_followup_files WHERE followup_id IN ($in) ORDER BY id DESC");
            $stf->execute($ids);
            foreach ($stf->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $fid = (int)$f['followup_id'];
                if (!isset($filesBy[$fid])) $filesBy[$fid] = [];
                $filesBy[$fid][] = $f;
            }
        }
        ?>
        <div class="modal-head">
            <div>
                <div class="modal-title"><?= h($enq['enquiry_no'] ?? ('ENQ-'.$enq['id'])) ?> • <?= h($enq['name'] ?? '-') ?></div>
                <div class="muted">
                    Phone: <?= h($enq['phone'] ?? '-') ?> • 
                    Email: <?= h($enq['email'] ?? '-') ?> • 
                    Course: <?= h($enq['course_interest'] ?? '-') ?>
                </div>
            </div>
        </div>

        <div class="hr"></div>

        <div class="history-wrap">
            <?php if (empty($fups)): ?>
                <div class="muted">No follow-ups yet for this enquiry.</div>
            <?php else: ?>
                <?php foreach ($fups as $f): ?>
                    <?php
                        $status  = $f['status'] ?? 'pending';
                        $vstatus = $f['verification_status'] ?? 'pending';

                        $sBadge = ($status === 'done')
                            ? badge('Done', 'green')
                            : (($status === 'missed') ? badge('Missed', 'red') : badge('Pending', 'orange'));

                        $vBadge = ($vstatus === 'approved')
                            ? badge('Approved', 'green')
                            : (($vstatus === 'rejected') ? badge('Rejected', 'red') : badge('Verify Pending', 'gray'));

                        $fid = (int)$f['id'];
                        $files = $filesBy[$fid] ?? [];
                    ?>
                    <div class="history-card">
                        <div class="history-top">
                            <div>
                                <div class="strong">
                                    <?= h($f['followup_date']) ?> <?= h($f['followup_time'] ?? '') ?> • <?= h($f['followup_type'] ?? '-') ?>
                                </div>
                                <div class="muted">
                                    By: <?= h($f['created_by_name'] ?? '-') ?>
                                    <?php if (!empty($f['next_followup_date'])): ?>
                                        • Next: <?= h($f['next_followup_date']) ?> <?= h($f['next_followup_time'] ?? '') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="pill-row">
                                <?= $sBadge ?>
                                <?php if ($isVerifier): ?><?= $vBadge ?><?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($f['notes'])): ?>
                            <div class="hr"></div>
                            <div style="white-space:pre-line;"><?= h($f['notes']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($files)): ?>
                            <div class="file-row">
                                <?php foreach ($files as $ff): ?>
                                    <?php
                                        $icon = 'fa-paperclip';
                                        if (($ff['file_type'] ?? '') === 'audio') $icon = 'fa-headphones';
                                        elseif (($ff['file_type'] ?? '') === 'image') $icon = 'fa-image';
                                        elseif (($ff['file_type'] ?? '') === 'video') $icon = 'fa-video';
                                        elseif (($ff['file_type'] ?? '') === 'document') $icon = 'fa-file-alt';
                                    ?>
                                    <a class="file-pill" target="_blank" href="<?= h($ff['file_path']) ?>">
                                        <i class="fas <?= h($icon) ?>"></i>
                                        <?= h($ff['original_name'] ?? 'Attachment') ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($isVerifier): ?>
                            <div class="hr"></div>
                            <div class="muted">
                                Verified By: <?= h($f['verified_by_name'] ?? '-') ?>
                                <?= !empty($f['verified_at']) ? ' • ' . h($f['verified_at']) : '' ?>
                            </div>

                            <form method="POST" class="verifyForm" style="margin-top:10px;">
                                <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                                <input type="hidden" name="followup_id" value="<?= (int)$f['id'] ?>">

                                <div class="grid-2">
                                    <div>
                                        <label class="lbl">Verification</label>
                                        <select name="verification_status" required>
                                            <option value="approved">Approve</option>
                                            <option value="rejected">Reject</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="lbl">Remarks</label>
                                        <input type="text" name="verification_remarks" value="<?= h($f['verification_remarks'] ?? '') ?>" placeholder="Optional">
                                    </div>
                                </div>

                                <div class="row-right" style="margin-top:10px;">
                                    <button type="submit" name="verify_followup" class="btn btn-primary">Update Verification</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        exit;
    }

    // --------------------------------
    // Edit followup modal
    // --------------------------------
    if ($action === 'edit_followup') {
        $fid = (int)($_GET['id'] ?? 0);
        if ($fid <= 0) {
            echo "<div class='muted'>Invalid follow-up.</div>";
            exit;
        }

        if ($canAllBranches !== 1 && $branchId > 0) {
            $st = $pdo->prepare("
                SELECT f.*, e.enquiry_no, e.name AS enquiry_name
                FROM enquiry_followups f
                JOIN enquiries e ON e.id = f.enquiry_id
                WHERE f.id = ? AND f.branch_id = ?
                LIMIT 1
            ");
            $st->execute([$fid, $branchId]);
        } else {
            $st = $pdo->prepare("
                SELECT f.*, e.enquiry_no, e.name AS enquiry_name
                FROM enquiry_followups f
                JOIN enquiries e ON e.id = f.enquiry_id
                WHERE f.id = ?
                LIMIT 1
            ");
            $st->execute([$fid]);
        }

        $f = $st->fetch(PDO::FETCH_ASSOC);
        if (!$f) {
            echo "<div class='muted'>Follow-up not found.</div>";
            exit;
        }
        ?>
        <div class="modal-head">
            <div>
                <div class="modal-title">Edit Follow-up</div>
                <div class="muted"><?= h($f['enquiry_no'] ?? ('ENQ-'.$f['enquiry_id'])) ?> • <?= h($f['enquiry_name'] ?? '-') ?></div>
            </div>
        </div>

        <div class="hr"></div>

        <form method="POST" enctype="multipart/form-data" class="modal-form">
            <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
            <input type="hidden" name="followup_id" value="<?= (int)$f['id'] ?>">

            <div class="mf-grid">
                <div>
                    <label class="lbl">Follow-up Date</label>
                    <input class="mf-inp" type="date" name="followup_date" value="<?= h($f['followup_date']) ?>" required>
                </div>

                <div>
                    <label class="lbl">Follow-up Time</label>
                    <input class="mf-inp" type="time" name="followup_time" value="<?= h($f['followup_time'] ?? '') ?>">
                </div>

                <div>
                    <label class="lbl">Type</label>
                    <div class="mf-select">
                        <select name="followup_type" class="mf-inp">
                            <?php
                            $types = ['call'=>'Call','whatsapp'=>'WhatsApp','sms'=>'SMS','email'=>'Email','walkin'=>'Walk-in'];
                            foreach ($types as $k => $v):
                            ?>
                                <option value="<?= h($k) ?>" <?= (($f['followup_type'] ?? 'call') === $k) ? 'selected' : '' ?>><?= h($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="mf-chev"><i class="fas fa-chevron-down"></i></span>
                    </div>
                </div>

                <div>
                    <label class="lbl">Status</label>
                    <div class="mf-select">
                        <select name="status" class="mf-inp">
                            <?php
                            $stt = ['pending'=>'Pending','done'=>'Done','missed'=>'Missed'];
                            foreach ($stt as $k => $v):
                            ?>
                                <option value="<?= h($k) ?>" <?= (($f['status'] ?? 'pending') === $k) ? 'selected' : '' ?>><?= h($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="mf-chev"><i class="fas fa-chevron-down"></i></span>
                    </div>
                </div>

                <div>
                    <label class="lbl">Next Follow-up Date</label>
                    <input class="mf-inp" type="date" name="next_followup_date" value="<?= h($f['next_followup_date'] ?? '') ?>">
                </div>

                <div>
                    <label class="lbl">Next Follow-up Time</label>
                    <input class="mf-inp" type="time" name="next_followup_time" value="<?= h($f['next_followup_time'] ?? '') ?>">
                </div>

                <div class="mf-full">
                    <label class="lbl">Notes</label>
                    <textarea class="mf-inp" name="notes" rows="4"><?= h($f['notes'] ?? '') ?></textarea>
                </div>

                <div class="mf-full">
                    <label class="lbl">Add More Attachments</label>
                    <div class="mf-upload" onclick="this.querySelector('input[type=file]').click()">
                        <div class="mf-up-ico"><i class="fas fa-cloud-upload-alt"></i></div>
                        <div class="mf-up-txt">
                            <b>Click to upload</b>
                            <small>Audio / Images / Docs / Videos (Multiple allowed)</small>
                        </div>
                        <div class="mf-up-pill">Choose files</div>
                        <input class="mf-file" type="file" name="attachments[]" multiple onchange="mfShowFiles(this)">
                    </div>
                    <div class="mf-files" id="mfFilesList">No files selected</div>
                </div>
            </div>

            <div class="row-right" style="margin-top:12px;">
                <button class="btn btn-primary" type="submit" name="update_followup">Save Changes</button>
            </div>
        </form>
        <?php
        exit;
    }

    echo "<div class='muted'>Invalid request.</div>";
    exit;
}

// ====================================
// Normal POST actions
// ====================================

// ------------------------------------
// 1) Add Follow-up
// ------------------------------------
// 1) Add Follow-up
if (isset($_POST['add_followup'])) {
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF).";
    } else {
        $enquiry_id = (int)($_POST['enquiry_id'] ?? 0);
        $fdate      = trim($_POST['followup_date'] ?? '');
        $ftime      = trim($_POST['followup_time'] ?? '');
        $ftype      = trim($_POST['followup_type'] ?? 'call');
        $notes      = trim($_POST['notes'] ?? '');
        $nextDate   = trim($_POST['next_followup_date'] ?? '');
        $nextTime   = trim($_POST['next_followup_time'] ?? '');

        if ($enquiry_id <= 0) {
            $error = "Please select enquiry.";
        } elseif ($fdate === '') {
            $error = "Follow-up date is required.";
        } elseif (!in_array($ftype, ['call','whatsapp','sms','email','walkin'], true)) {
            $error = "Invalid follow-up type.";
        } else {
            try {
                // ---------------------------------------------------
                // Fetch enquiry safely
                // If branch is null in old records, use session branch
                // ---------------------------------------------------
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $chk = $pdo->prepare("
                        SELECT 
                            id,
                            branch_id
                        FROM enquiries
                        WHERE id = ?
                          AND (branch_id = ? OR branch_id IS NULL)
                        LIMIT 1
                    ");
                    $chk->execute([$enquiry_id, $branchId]);
                } else {
                    $chk = $pdo->prepare("
                        SELECT id, branch_id
                        FROM enquiries
                        WHERE id = ?
                        LIMIT 1
                    ");
                    $chk->execute([$enquiry_id]);
                }

                $enqRow = $chk->fetch(PDO::FETCH_ASSOC);

                if (!$enqRow) {
                    throw new Exception("Enquiry not found or branch access denied.");
                }

                // If enquiry branch is null, fallback to logged in user's branch
                $useBranchId = (int)($enqRow['branch_id'] ?? 0);
                if ($useBranchId <= 0) {
                    $useBranchId = $branchId;
                }

                $pdo->beginTransaction();

                $ins = $pdo->prepare("
                    INSERT INTO enquiry_followups
                    (
                        enquiry_id,
                        branch_id,
                        followup_date,
                        followup_time,
                        followup_type,
                        status,
                        notes,
                        next_followup_date,
                        next_followup_time,
                        verification_status,
                        created_by,
                        updated_by,
                        ip_address,
                        user_agent,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        :enquiry_id,
                        :branch_id,
                        :followup_date,
                        :followup_time,
                        :followup_type,
                        'pending',
                        :notes,
                        :next_followup_date,
                        :next_followup_time,
                        'pending',
                        :created_by,
                        :updated_by,
                        :ip_address,
                        :user_agent,
                        NOW(),
                        NOW()
                    )
                ");

                $ins->execute([
                    ':enquiry_id'         => $enquiry_id,
                    ':branch_id'          => $useBranchId,
                    ':followup_date'      => $fdate,
                    ':followup_time'      => ($ftime !== '' ? $ftime : null),
                    ':followup_type'      => $ftype,
                    ':notes'              => ($notes !== '' ? $notes : null),
                    ':next_followup_date' => ($nextDate !== '' ? $nextDate : null),
                    ':next_followup_time' => ($nextTime !== '' ? $nextTime : null),
                    ':created_by'         => ($userId > 0 ? $userId : null),
                    ':updated_by'         => ($userId > 0 ? $userId : null),
                    ':ip_address'         => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':user_agent'         => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);

                $followupId = (int)$pdo->lastInsertId();

                // Upload files
                $uploaded = [];
                if (!empty($_FILES['attachments']['name'][0])) {
                    $uploaded = uploadManyFiles($_FILES['attachments'], 'enquiry_followups');
                }

                if (!empty($uploaded)) {
                    $fileIns = $pdo->prepare("
                        INSERT INTO enquiry_followup_files
                        (
                            followup_id,
                            enquiry_id,
                            branch_id,
                            file_path,
                            file_type,
                            original_name,
                            file_size,
                            uploaded_by,
                            uploaded_at
                        )
                        VALUES
                        (
                            :followup_id,
                            :enquiry_id,
                            :branch_id,
                            :file_path,
                            :file_type,
                            :original_name,
                            :file_size,
                            :uploaded_by,
                            NOW()
                        )
                    ");

                    foreach ($uploaded as $f) {
                        $fileIns->execute([
                            ':followup_id'   => $followupId,
                            ':enquiry_id'    => $enquiry_id,
                            ':branch_id'     => $useBranchId,
                            ':file_path'     => $f['path'],
                            ':file_type'     => $f['type'],
                            ':original_name' => $f['original'],
                            ':file_size'     => (int)$f['size'],
                            ':uploaded_by'   => ($userId > 0 ? $userId : null),
                        ]);
                    }
                }

                $pdo->commit();
                $success = "Follow-up created successfully!";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Failed to add follow-up: " . $e->getMessage();
            }
        }
    }
}
// ------------------------------------
// 2) Mark Done (+ Convert)
// ------------------------------------
if (isset($_POST['mark_done'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF).";
    } else {
        $fid     = (int)($_POST['followup_id'] ?? 0);
        $convert = (int)($_POST['convert'] ?? 0);
        $regType = trim($_POST['reg_type'] ?? '');

        if ($fid <= 0) {
            $error = "Invalid follow-up.";
        } else {
            try {
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $st = $pdo->prepare("SELECT id, enquiry_id, branch_id FROM enquiry_followups WHERE id=? AND branch_id=? LIMIT 1");
                    $st->execute([$fid, $branchId]);
                } else {
                    $st = $pdo->prepare("SELECT id, enquiry_id, branch_id FROM enquiry_followups WHERE id=? LIMIT 1");
                    $st->execute([$fid]);
                }

                $fu = $st->fetch(PDO::FETCH_ASSOC);
                if (!$fu) {
                    throw new Exception("Follow-up not found / branch restricted.");
                }

                $enquiryId = (int)$fu['enquiry_id'];
                $fuBranch  = (int)$fu['branch_id'];

                $allowedTypes = ['course', 'internship', 'workshop'];
                if ($convert === 1 && !in_array($regType, $allowedTypes, true)) {
                    throw new Exception("Invalid registration type.");
                }

                if ($canAllBranches !== 1 && $branchId > 0) {
                    $eq = $pdo->prepare("SELECT id, handled_by, branch_id FROM enquiries WHERE id=? AND branch_id=? LIMIT 1");
                    $eq->execute([$enquiryId, $branchId]);
                } else {
                    $eq = $pdo->prepare("SELECT id, handled_by, branch_id FROM enquiries WHERE id=? LIMIT 1");
                    $eq->execute([$enquiryId]);
                }

                $enq = $eq->fetch(PDO::FETCH_ASSOC);
                if (!$enq) {
                    throw new Exception("Enquiry not found / branch restricted.");
                }

                $assignedTo = (int)($enq['handled_by'] ?? 0);
                $useBranch  = (int)($enq['branch_id'] ?? $fuBranch);

                $pdo->beginTransaction();

                if ($canAllBranches !== 1 && $branchId > 0) {
                    $up = $pdo->prepare("
                        UPDATE enquiry_followups
                        SET 
                            status='done',
                            done_at=NOW(),
                            updated_by=?,
                            updated_at=NOW()
                        WHERE id=? AND branch_id=?
                    ");
                    $up->execute([$userId, $fid, $branchId]);
                } else {
                    $up = $pdo->prepare("
                        UPDATE enquiry_followups
                        SET 
                            status='done',
                            done_at=NOW(),
                            updated_by=?,
                            updated_at=NOW()
                        WHERE id=?
                    ");
                    $up->execute([$userId, $fid]);
                }

                if ($canAllBranches !== 1 && $branchId > 0) {
                    $up2 = $pdo->prepare("UPDATE enquiries SET status='converted', updated_at=NOW(), updated_by=? WHERE id=? AND branch_id=?");
                    $up2->execute([$userId, $enquiryId, $branchId]);
                } else {
                    $up2 = $pdo->prepare("UPDATE enquiries SET status='converted', updated_at=NOW(), updated_by=? WHERE id=?");
                    $up2->execute([$userId, $enquiryId]);
                }

                $regId = 0;

                if ($convert === 1) {
                    $chk = $pdo->prepare("SELECT id FROM registrations WHERE enquiry_id=? AND reg_type=? ORDER BY id DESC LIMIT 1");
                    $chk->execute([$enquiryId, $regType]);
                    $existing = (int)($chk->fetchColumn() ?? 0);

                    if ($existing > 0) {
                        $regId = $existing;
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO registrations
                            (
                                enquiry_id,
                                branch_id,
                                reg_type,
                                registration_status,
                                assigned_to,
                                created_by,
                                created_at,
                                updated_at
                            )
                            VALUES
                            (?, ?, ?, 'draft', ?, ?, NOW(), NOW())
                        ");
                        $ins->execute([
                            $enquiryId,
                            $useBranch,
                            $regType,
                            ($assignedTo > 0 ? $assignedTo : null),
                            $userId
                        ]);
                        $regId = (int)$pdo->lastInsertId();
                    }
                }

                $pdo->commit();

                if ($convert === 1) {
                    redirect("index.php?page=registrations/convert&enquiry_id={$enquiryId}&type={$regType}&reg_id={$regId}");
                    exit;
                }

                $success = "Marked as done!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Failed to mark done. " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------
// 3) Verification
// ------------------------------------
if (isset($_POST['verify_followup']) && $isVerifier) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF).";
    } else {
        $fid  = (int)($_POST['followup_id'] ?? 0);
        $vst  = trim($_POST['verification_status'] ?? 'pending');
        $vrem = trim($_POST['verification_remarks'] ?? '');

        if (!in_array($vst, ['approved', 'rejected'], true)) {
            $error = "Invalid verification status.";
        } else {
            try {
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $st = $pdo->prepare("
                        UPDATE enquiry_followups
                        SET 
                            verification_status=?,
                            verified_by=?,
                            verified_at=NOW(),
                            verification_remarks=?,
                            updated_by=?,
                            updated_at=NOW()
                        WHERE id=? AND branch_id=?
                    ");
                    $st->execute([$vst, $userId, ($vrem !== '' ? $vrem : null), $userId, $fid, $branchId]);
                } else {
                    $st = $pdo->prepare("
                        UPDATE enquiry_followups
                        SET 
                            verification_status=?,
                            verified_by=?,
                            verified_at=NOW(),
                            verification_remarks=?,
                            updated_by=?,
                            updated_at=NOW()
                        WHERE id=?
                    ");
                    $st->execute([$vst, $userId, ($vrem !== '' ? $vrem : null), $userId, $fid]);
                }

                $success = "Verification updated!";
            } catch (Exception $e) {
                $error = "Failed to verify. " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------
// 4) Update Follow-up
// ------------------------------------
if (isset($_POST['update_followup'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF).";
    } else {
        $fid    = (int)($_POST['followup_id'] ?? 0);
        $fdate  = trim($_POST['followup_date'] ?? '');
        $ftime  = trim($_POST['followup_time'] ?? '');
        $ftype  = trim($_POST['followup_type'] ?? 'call');
        $status = trim($_POST['status'] ?? 'pending');
        $notes  = trim($_POST['notes'] ?? '');
        $nextD  = trim($_POST['next_followup_date'] ?? '');
        $nextT  = trim($_POST['next_followup_time'] ?? '');

        if ($fid <= 0 || $fdate === '') {
            $error = "Invalid follow-up data.";
        } else {
            try {
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $st = $pdo->prepare("SELECT id, enquiry_id, branch_id FROM enquiry_followups WHERE id=? AND branch_id=? LIMIT 1");
                    $st->execute([$fid, $branchId]);
                } else {
                    $st = $pdo->prepare("SELECT id, enquiry_id, branch_id FROM enquiry_followups WHERE id=? LIMIT 1");
                    $st->execute([$fid]);
                }

                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new Exception("Follow-up not found.");
                }

                $enquiry_id  = (int)$row['enquiry_id'];
                $useBranchId = (int)$row['branch_id'];

                $pdo->beginTransaction();

                $doneAtSql = "";
                if ($status === 'done') {
                    $doneAtSql = ", done_at = COALESCE(done_at, NOW())";
                } elseif ($status !== 'done') {
                    $doneAtSql = ", done_at = NULL";
                }

                $upd = $pdo->prepare("
                    UPDATE enquiry_followups
                    SET
                        followup_date=?,
                        followup_time=?,
                        followup_type=?,
                        status=?,
                        notes=?,
                        next_followup_date=?,
                        next_followup_time=?,
                        updated_by=?,
                        updated_at=NOW()
                        {$doneAtSql}
                    WHERE id=?
                ");

                $upd->execute([
                    $fdate,
                    ($ftime !== '' ? $ftime : null),
                    $ftype,
                    in_array($status, ['pending','done','missed'], true) ? $status : 'pending',
                    ($notes !== '' ? $notes : null),
                    ($nextD !== '' ? $nextD : null),
                    ($nextT !== '' ? $nextT : null),
                    $userId,
                    $fid
                ]);

                $uploaded = [];
                if (!empty($_FILES['attachments']['name'][0])) {
                    $uploaded = uploadManyFiles($_FILES['attachments'], 'enquiry_followups');
                }

                if (!empty($uploaded)) {
                    $fileIns = $pdo->prepare("
                        INSERT INTO enquiry_followup_files
                        (
                            followup_id,
                            enquiry_id,
                            branch_id,
                            file_path,
                            file_type,
                            original_name,
                            file_size,
                            uploaded_by,
                            uploaded_at
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    foreach ($uploaded as $f) {
                        $fileIns->execute([
                            $fid,
                            $enquiry_id,
                            $useBranchId,
                            $f['path'],
                            $f['type'],
                            $f['original'],
                            (int)$f['size'],
                            $userId
                        ]);
                    }
                }

                $pdo->commit();
                $success = "Follow-up updated successfully!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Failed to update follow-up. " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------
// UI State
// ------------------------------------
$uiTab = trim($_GET['ui'] ?? 'list');

// ------------------------------------
// Filters
// ------------------------------------
$tab  = trim($_GET['tab'] ?? 'today');
$q    = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');

$where = [];
$params = [];

if ($canAllBranches !== 1 && $branchId > 0) {
    $where[] = "f.branch_id = ?";
    $params[] = $branchId;
}

if ($tab === 'today')       $where[] = "f.followup_date = CURDATE()";
elseif ($tab === 'pending') $where[] = "f.status = 'pending'";
elseif ($tab === 'missed')  $where[] = "f.status = 'missed'";
elseif ($tab === 'done')    $where[] = "f.status = 'done'";

if ($from !== '') {
    $where[] = "f.followup_date >= ?";
    $params[] = $from;
}

if ($to !== '') {
    $where[] = "f.followup_date <= ?";
    $params[] = $to;
}

if ($q !== '') {
    $where[] = "(e.name LIKE ? OR e.phone LIKE ? OR e.email LIKE ? OR e.enquiry_no LIKE ?)";
    $like = "%".$q."%";
    array_push($params, $like, $like, $like, $like);
}

$whereSql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : "";

// ------------------------------------
// Fetch followups
// ------------------------------------
$followups = [];
try {
    $st = $pdo->prepare("
        SELECT
            f.*,
            e.name AS enquiry_name,
            e.phone AS enquiry_phone,
            e.enquiry_no
        FROM enquiry_followups f
        JOIN enquiries e ON e.id = f.enquiry_id
        $whereSql
        ORDER BY f.followup_date DESC, f.followup_time DESC, f.id DESC
        LIMIT 300
    ");
    $st->execute($params);
    $followups = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $followups = [];
}

// ------------------------------------
// Enquiry dropdown
// ------------------------------------
$enquiryOptions = [];
try {
    if ($canAllBranches !== 1 && $branchId > 0) {
        $st = $pdo->prepare("
            SELECT id, enquiry_no, name, phone
            FROM enquiries
            WHERE branch_id = ?
            ORDER BY id DESC
            LIMIT 300
        ");
        $st->execute([$branchId]);
    } else {
        $st = $pdo->query("
            SELECT id, enquiry_no, name, phone
            FROM enquiries
            ORDER BY id DESC
            LIMIT 300
        ");
    }
    $enquiryOptions = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $enquiryOptions = [];
}
?>

<style>
.top-tabs { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.top-tabs a{
  padding:10px 14px; border-radius:999px;
  border:1px solid rgba(0,0,0,.08);
  background:#fff; text-decoration:none; color:var(--text-dark);
  font-weight:900; font-size:13px;
}
.top-tabs a.active{ background: rgba(233,30,99,.12); border-color: rgba(233,30,99,.25); color: var(--primary); }

.section-card .card-header{ display:flex; align-items:center; justify-content:space-between; }

.grid-2{ display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
@media(max-width: 1100px){ .grid-2{ grid-template-columns: 1fr; } }

.filter-grid{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap:12px;
  align-items:end;
}
.lbl{ font-weight:900; font-size:13px; display:block; margin-bottom:6px; color:#111; }
.filter-grid input, .filter-grid select, textarea,
.modal-form input, .modal-form select, .modal-form textarea{
  width:100%;
  padding:10px 12px;
  border-radius:12px;
  border:1px solid #e5e7eb;
  outline:none;
  background:#fff;
}
.filter-grid input:focus, .filter-grid select:focus, textarea:focus,
.modal-form input:focus, .modal-form select:focus, .modal-form textarea:focus{
  border-color: rgba(233,30,99,.55);
  box-shadow: 0 0 0 4px rgba(233,30,99,.12);
}

.row-right{ display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
.muted{ color: var(--text-light); font-size:12px; margin-top:6px; }
.hr{ height:1px; background:#f1f1f1; margin:12px 0; }

.tag{
  display:inline-flex; align-items:center; justify-content:center;
  padding:6px 12px; border-radius:999px;
  font-size:12px; font-weight:900;
  border:1px solid rgba(0,0,0,.06);
}

.table-wrap{
  background:#fff;
  border:1px solid rgba(0,0,0,.06);
  border-radius:16px;
  box-shadow:0 10px 30px rgba(0,0,0,.04);
  overflow:hidden;
}
.modern-table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  font-size:14px;
}
.modern-table thead{ background:#f7f8fb; }
.modern-table th{
  padding:14px 14px;
  font-weight:900;
  font-size:13px;
  color:#333;
  border-bottom:1px solid #eee;
  text-align:left;
  white-space:nowrap;
}
.modern-table td{
  padding:14px 14px;
  border-bottom:1px solid #f2f2f2;
  vertical-align:middle;
}
.modern-table tbody tr{ transition:.15s; border-left:4px solid transparent; }
.modern-table tbody tr:hover{ background:#fcfcff; border-left:4px solid var(--primary); }
.tc{ text-align:center; }
.nowrap{ white-space:nowrap; }
.strong{ font-weight:900; color:#111; }
.sub{ font-size:12px; color:#888; margin-top:4px; }

.icon-btn{
  display:inline-flex; align-items:center; justify-content:center;
  width:36px; height:36px;
  border-radius:10px;
  border:1px solid rgba(0,0,0,.06);
  background:#fff;
  cursor:pointer;
  transition:.15s;
  text-decoration:none;
}
.icon-btn:hover{ transform:translateY(-1px); box-shadow:0 10px 22px rgba(0,0,0,.08); }
.btn-view{ background:rgba(3,169,244,.10); color:#0288d1; border-color:rgba(3,169,244,.20); }
.btn-view:hover{ background:#0288d1; color:#fff; }
.btn-edit{ background:rgba(233,30,99,.10); color:var(--primary); border-color:rgba(233,30,99,.20); }
.btn-edit:hover{ background:var(--primary); color:#fff; }
.btn-done{ background:rgba(46,125,50,.10); color:#2e7d32; border-color:rgba(46,125,50,.20); }
.btn-done:hover{ background:#2e7d32; color:#fff; }

.modal-backdrop{
  position:fixed; inset:0; background:rgba(0,0,0,.35);
  display:none; align-items:center; justify-content:center;
  padding:18px; z-index:9999;
}
.modal{
  width:min(980px, 98vw);
  background:#fff;
  border-radius:18px;
  border:1px solid rgba(0,0,0,.08);
  box-shadow:0 20px 70px rgba(0,0,0,.22);
  overflow:hidden;
}
.modal-header{
  padding:14px 16px;
  display:flex; justify-content:space-between; align-items:center;
  background:#fff;
}
.modal-title{ font-weight:1000; font-size:16px; color:#111; }
.modal-body{ padding:14px 16px; max-height:75vh; overflow:auto; }
.modal-close{
  width:38px; height:38px; border-radius:12px; border:1px solid rgba(0,0,0,.08);
  background:#fff; cursor:pointer;
}
.modal-close:hover{ background:#f8f9fa; }

.history-wrap{ display:flex; flex-direction:column; gap:12px; }
.history-card{
  background:#fff;
  border:1px solid rgba(0,0,0,.06);
  border-radius:16px;
  box-shadow:0 10px 26px rgba(0,0,0,.03);
  padding:14px;
}
.history-top{ display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.pill-row{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.file-row{ display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }
.file-pill{
  display:inline-flex; gap:8px; align-items:center;
  padding:8px 12px; border-radius:999px;
  border:1px solid rgba(0,0,0,.08);
  background:#fff; text-decoration:none;
  font-weight:900; font-size:12px; color:#333;
}
.file-pill:hover{ box-shadow:0 8px 20px rgba(0,0,0,.06); }

.schedule-banner{
  border:1px solid rgba(233,30,99,.18);
  background: linear-gradient(135deg, rgba(233,30,99,.10), rgba(3,169,244,.08));
  border-radius:16px;
  padding:12px 12px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  box-shadow:0 10px 26px rgba(0,0,0,.04);
}
.sb-left{ display:flex; align-items:center; gap:12px; }
.sb-ico{
  width:44px; height:44px;
  border-radius:14px;
  background: rgba(233,30,99,.14);
  color: var(--primary);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:18px;
  flex:0 0 44px;
}
.sb-title{ font-weight:1000; color:#111; font-size:14px; }
.sb-text{ font-size:12px; color: var(--text-light); margin-top:2px; }
.sb-close{
  width:38px; height:38px;
  border-radius:12px;
  border:1px solid rgba(0,0,0,.08);
  background:#fff;
  cursor:pointer;
}
.sb-close:hover{ background:#f8f9fa; }

.upload-box{
  position:relative;
  border:1.5px dashed rgba(233,30,99,.35);
  background:#fff7fb;
  border-radius:16px;
  padding:14px;
  display:flex;
  align-items:flex-start;
  gap:12px;
  cursor:pointer;
  transition:.18s;
}
.upload-box:hover{
  border-color: rgba(233,30,99,.65);
  box-shadow: 0 0 0 4px rgba(233,30,99,.10);
}
.upload-ico{
  width:46px; height:46px;
  border-radius:14px;
  background: rgba(233,30,99,.12);
  color: var(--primary);
  display:flex;
  align-items:center;
  justify-content:center;
  flex:0 0 46px;
  font-size:18px;
}
.upload-input{
  position:absolute;
  inset:0;
  opacity:0;
  cursor:pointer;
}
.file-preview{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-top:8px;
}
.file-chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 10px;
  border-radius:999px;
  border:1px solid rgba(0,0,0,.08);
  background:#fff;
  font-size:12px;
  font-weight:900;
}
.file-chip i{ opacity:.85; }

.mf-grid{
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap:14px;
}
.mf-full{ grid-column: 1 / -1; }
@media(max-width: 900px){
  .mf-grid{ grid-template-columns: 1fr; }
}
.mf-inp{
  width:100%;
  padding:11px 12px;
  border-radius:14px;
  border:1px solid #e5e7eb;
  background:#fff;
  outline:none;
  transition:.15s;
}
.mf-inp:focus{
  border-color: rgba(233,30,99,.55);
  box-shadow: 0 0 0 4px rgba(233,30,99,.12);
}
.mf-select{ position:relative; }
.mf-select select{
  appearance:none;
  -webkit-appearance:none;
  -moz-appearance:none;
  padding-right:42px;
  cursor:pointer;
}
.mf-chev{
  position:absolute;
  right:12px;
  top:50%;
  transform:translateY(-50%);
  color:#9ca3af;
  pointer-events:none;
}
.mf-upload{
  border:1.5px dashed #f1c2d4;
  background:#fff7fb;
  border-radius:16px;
  padding:14px;
  display:flex;
  align-items:center;
  gap:12px;
  cursor:pointer;
  transition:.15s;
  user-select:none;
  position:relative;
}
.mf-upload:hover{
  border-color: rgba(233,30,99,.55);
  box-shadow: 0 0 0 4px rgba(233,30,99,.10);
}
.mf-up-ico{
  width:46px; height:46px;
  border-radius:16px;
  background: rgba(233,30,99,.12);
  color: var(--primary);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:18px;
  flex:0 0 46px;
}
.mf-up-txt b{ display:block; font-size:13px; font-weight:900; }
.mf-up-txt small{ display:block; margin-top:2px; font-size:12px; color: var(--text-light); }
.mf-up-pill{
  margin-left:auto;
  padding:8px 12px;
  border-radius:999px;
  border:1px solid rgba(0,0,0,.08);
  background:#fff;
  font-weight:900;
  font-size:12px;
}
.mf-file{
  position:absolute;
  inset:0;
  opacity:0;
  cursor:pointer;
}
.mf-files{
  margin-top:8px;
  font-size:12px;
  color: var(--text-light);
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}
.mf-filechip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:7px 10px;
  border-radius:999px;
  border:1px solid rgba(0,0,0,.08);
  background:#fff;
  color:#333;
  font-weight:800;
}
.mf-filechip em{
  font-style:normal;
  color:#9ca3af;
  font-weight:700;
  margin-left:2px;
}

.swal-select-wrap{ margin-top:10px; }
.swal-modern-select{
  width:100%;
  padding:12px 14px;
  border-radius:14px;
  border:1px solid #e5e7eb;
  font-size:14px;
  font-weight:700;
  outline:none;
  appearance:none;
  background:#fff;
  transition:.15s;
}
.swal-modern-select:focus{
  border-color: rgba(233,30,99,.55);
  box-shadow: 0 0 0 4px rgba(233,30,99,.12);
}
</style>

<h2 style="margin-bottom:12px;">Enquiry Follow-ups</h2>

<?php if ($success): ?>
<script>
Swal.fire({
    icon:'success',
    title:'Success',
    text:'<?= addslashes($success) ?>',
    confirmButtonColor:'#e91e63'
}).then(() => {
    window.location.href = "index.php?page=enquiries/followups&ui=<?= h($uiTab) ?>&tab=<?= h($tab) ?>";
});
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
Swal.fire({
    icon:'error',
    title:'Error',
    text:'<?= addslashes($error) ?>',
    confirmButtonColor:'#e91e63'
});
</script>
<?php endif; ?>

<div class="top-tabs">
    <a class="<?= $uiTab==='add' ? 'active' : '' ?>" href="index.php?page=enquiries/followups&ui=add">Add Follow-up</a>
    <a class="<?= $uiTab!=='add' ? 'active' : '' ?>" href="index.php?page=enquiries/followups&ui=list&tab=<?= h($tab) ?>">Follow-ups</a>
</div>

<?php if ($uiTab === 'add'): ?>

<div class="card section-card">
    <div class="card-header">
        <div>Add Follow-up</div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="addFollowupForm" style="padding:14px;">
        <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">

        <div class="filter-grid">
            <div style="grid-column:1 / -1;">
                <label class="lbl">Select Enquiry</label>
                <select name="enquiry_id" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($enquiryOptions as $e): ?>
                        <option value="<?= (int)$e['id'] ?>">
                            <?= h($e['enquiry_no'] ?: ('ENQ-'.$e['id'])) ?> - <?= h($e['name']) ?> (<?= h($e['phone'] ?? '-') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="lbl">Follow-up Date</label>
                <input type="date" name="followup_date" value="<?= h(date('Y-m-d')) ?>" required>
            </div>

            <div>
                <label class="lbl">Follow-up Time</label>
                <input type="time" name="followup_time">
            </div>

            <div id="scheduleBanner" class="schedule-banner" style="display:none; grid-column:1 / -1;">
                <div class="sb-left">
                    <div class="sb-ico"><i class="fas fa-bell"></i></div>
                    <div>
                        <div class="sb-title">Scheduled Follow-up</div>
                        <div class="sb-text" id="scheduleBannerText">—</div>
                    </div>
                </div>
                <button type="button" class="sb-close" onclick="hideScheduleBanner()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div>
                <label class="lbl">Type</label>
                <select name="followup_type">
                    <option value="call">Call</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                    <option value="walkin">Walk-in</option>
                </select>
            </div>

            <div>
                <label class="lbl">Next Follow-up Date</label>
                <input type="date" name="next_followup_date">
            </div>

            <div>
                <label class="lbl">Next Follow-up Time</label>
                <input type="time" name="next_followup_time">
            </div>

            <div style="grid-column:1 / -1;">
                <label class="lbl">Attachments (multiple)</label>

                <label class="upload-box">
                    <div class="upload-ico"><i class="fas fa-cloud-upload-alt"></i></div>
                    <div>
                        <div class="strong">Click to upload files</div>
                        <div class="muted">Audio / Images / Docs / Videos (multiple allowed)</div>
                        <div class="file-preview" id="addFilePreview"></div>
                    </div>
                    <input class="upload-input" type="file" name="attachments[]" id="addAttachments" multiple>
                </label>
            </div>

            <div style="grid-column:1 / -1;">
                <label class="lbl">Notes</label>
                <textarea name="notes" rows="4" placeholder="Add follow-up notes..."></textarea>
            </div>
        </div>

        <div class="row-right" style="margin-top:12px;">
            <button class="btn btn-primary" type="submit" name="add_followup" style="width:200px;">Save Follow-up</button>
            <a class="btn-danger" style="text-decoration:none;padding:10px 14px;border-radius:10px;" href="index.php?page=enquiries/followups&ui=list&tab=today">Go to List</a>
        </div>
    </form>
</div>

<?php else: ?>

<div class="card section-card">
    <div class="card-header">
        <div>Follow-ups</div>
        <a href="index.php?page=enquiries/followups&ui=add" class="btn btn-primary" style="text-decoration:none;">+ Add</a>
    </div>

    <div style="padding:14px; padding-bottom:0;">
        <div class="top-tabs" style="margin:0;">
            <a class="<?= $tab==='today'?'active':''; ?>" href="index.php?page=enquiries/followups&ui=list&tab=today">Today</a>
            <a class="<?= $tab==='pending'?'active':''; ?>" href="index.php?page=enquiries/followups&ui=list&tab=pending">Pending</a>
            <a class="<?= $tab==='missed'?'active':''; ?>" href="index.php?page=enquiries/followups&ui=list&tab=missed">Missed</a>
            <a class="<?= $tab==='done'?'active':''; ?>" href="index.php?page=enquiries/followups&ui=list&tab=done">Done</a>
            <a class="<?= $tab==='all'?'active':''; ?>" href="index.php?page=enquiries/followups&ui=list&tab=all">All</a>
        </div>
    </div>

    <form method="GET" action="index.php" style="padding:14px;">
        <input type="hidden" name="page" value="enquiries/followups">
        <input type="hidden" name="ui" value="list">
        <input type="hidden" name="tab" value="<?= h($tab) ?>">

        <div class="filter-grid">
            <div>
                <label class="lbl">Search</label>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Name / Phone / Email / Enquiry No">
            </div>
            <div>
                <label class="lbl">Date From</label>
                <input type="date" name="from" value="<?= h($from) ?>">
            </div>
            <div>
                <label class="lbl">Date To</label>
                <input type="date" name="to" value="<?= h($to) ?>">
            </div>
            <div class="row-right">
                <button class="btn btn-primary" type="submit">Apply</button>
                <a class="btn-danger" style="text-decoration:none;padding:10px 14px;border-radius:10px;" href="index.php?page=enquiries/followups&ui=list&tab=<?= h($tab) ?>">Reset</a>
            </div>
        </div>
    </form>

    <div style="padding:14px;">
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th class="nowrap">Follow-up</th>
                        <th>Enquiry</th>
                        <th>Contact</th>
                        <th class="nowrap">Type</th>
                        <th class="tc nowrap">Status</th>
                        <th class="nowrap">Next</th>
                        <th class="tc nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($followups)): ?>
                    <tr>
                        <td colspan="7" class="tc" style="padding:26px;color:var(--text-light);">No follow-ups found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($followups as $f): ?>
                        <?php
                            $status = $f['status'] ?? 'pending';
                            $sBadge = ($status==='done') ? badge('Done','green') : (($status==='missed') ? badge('Missed','red') : badge('Pending','orange'));
                            $enqNo = $f['enquiry_no'] ?: ('ENQ-'.$f['enquiry_id']);
                        ?>
                        <tr>
                            <td class="nowrap">
                                <div class="strong"><?= h($f['followup_date']) ?> <?= h($f['followup_time'] ?? '') ?></div>
                                <div class="sub">#<?= (int)$f['id'] ?></div>
                            </td>

                            <td>
                                <div class="strong"><?= h($enqNo) ?></div>
                                <div class="sub"><?= h($f['enquiry_name'] ?? '-') ?></div>
                            </td>

                            <td>
                                <div><?= h(visibleStudentContactValue($f['enquiry_phone'] ?? '-')) ?></div>
                            </td>

                            <td class="nowrap"><?= h($f['followup_type'] ?? '-') ?></td>

                            <td class="tc"><?= $sBadge ?></td>

                            <td class="nowrap">
                                <?php if (!empty($f['next_followup_date'])): ?>
                                    <div class="strong"><?= h($f['next_followup_date']) ?> <?= h($f['next_followup_time'] ?? '') ?></div>
                                <?php else: ?>
                                    <div class="sub">-</div>
                                <?php endif; ?>
                            </td>

                            <td class="tc nowrap">
                                <button type="button" class="icon-btn btn-view" onclick="openHistoryModal(<?= (int)$f['enquiry_id'] ?>)" title="View Enquiry History">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <button type="button" class="icon-btn btn-edit" onclick="openEditModal(<?= (int)$f['id'] ?>)" title="Edit Follow-up">
                                    <i class="fas fa-pen"></i>
                                </button>

                                <?php if (($f['status'] ?? '') !== 'done'): ?>
                                    <form method="POST" class="doneForm" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                                        <input type="hidden" name="followup_id" value="<?= (int)$f['id'] ?>">
                                        <button type="submit" name="mark_done" class="icon-btn btn-done" title="Mark Done">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<div class="modal-backdrop" id="crmModalBackdrop">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="crmModalTitle">Details</div>
            <button class="modal-close" onclick="closeCrmModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="crmModalBody">
            <div class="muted">Loading...</div>
        </div>
    </div>
</div>

<script>
(function () {
    window.ensureMarkDoneField = function (form) {
        if (!form) return;
        let md = form.querySelector('input[name="mark_done"]');
        if (!md) {
            md = document.createElement('input');
            md.type = 'hidden';
            md.name = 'mark_done';
            form.appendChild(md);
        }
        md.value = '1';
    };

    window.setHiddenField = function (form, name, value) {
        let el = form.querySelector(`input[name="${name}"]`);
        if (!el) {
            el = document.createElement('input');
            el.type = 'hidden';
            el.name = name;
            form.appendChild(el);
        }
        el.value = value;
    };
})();

function openCrmModal(title) {
    document.getElementById('crmModalTitle').innerText = title || 'Details';
    document.getElementById('crmModalBody').innerHTML = '<div class="muted">Loading...</div>';
    document.getElementById('crmModalBackdrop').style.display = 'flex';
}
function closeCrmModal() {
    document.getElementById('crmModalBackdrop').style.display = 'none';
    document.getElementById('crmModalBody').innerHTML = '';
}
document.getElementById('crmModalBackdrop').addEventListener('click', function (e) {
    if (e.target === this) closeCrmModal();
});

async function loadModalHtml(url) {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return await res.text();
}

async function openHistoryModal(enquiryId) {
    openCrmModal('Enquiry History');
    const url = `index.php?page=enquiries/followups&ajax=1&action=enquiry_history&enquiry_id=${enquiryId}`;
    const html = await loadModalHtml(url);
    document.getElementById('crmModalBody').innerHTML = html;

    document.querySelectorAll('#crmModalBody .verifyForm').forEach((f) => {
        f.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Confirm Verification?',
                text: 'Approve/Reject this verification.',
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#e91e63',
            }).then((r) => {
                if (r.isConfirmed) f.submit();
            });
        });
    });
}

async function openEditModal(followupId) {
    openCrmModal('Edit Follow-up');
    const url = `index.php?page=enquiries/followups&ajax=1&action=edit_followup&id=${followupId}`;
    const html = await loadModalHtml(url);
    document.getElementById('crmModalBody').innerHTML = html;
}

function mfShowFiles(inp) {
    const el = document.getElementById('mfFilesList');
    if (!el) return;

    const files = inp.files ? Array.from(inp.files) : [];
    if (files.length === 0) {
        el.textContent = 'No files selected';
        return;
    }

    el.innerHTML = files.map((f) => {
        const kb = Math.round((f.size || 0) / 1024);
        return `<span class="mf-filechip"><i class="fas fa-paperclip"></i> ${f.name} <em>${kb} KB</em></span>`;
    }).join(' ');
}

(function () {
    const forms = document.querySelectorAll('.doneForm');
    if (!forms.length) return;

    const swalCss = `
        <style>
            .swal2-popup{ border-radius:18px !important; }
            .swal-select-wrap{ padding:6px 0 0; }
            .swal-modern-select{
                width:100%;
                padding:12px 14px;
                border-radius:14px;
                border:1px solid #e5e7eb;
                outline:none;
                background:#fff;
                font-weight:800;
            }
            .swal-modern-select:focus{
                border-color: rgba(233,30,99,.55);
                box-shadow: 0 0 0 4px rgba(233,30,99,.12);
            }
        </style>
    `;

    forms.forEach((f) => {
        f.addEventListener('submit', function (e) {
            e.preventDefault();

            Swal.fire({
                icon: 'question',
                title: 'Mark as Done?',
                html: `
                    ${swalCss}
                    <div style="text-align:left;font-size:13px;color:#666;margin-bottom:10px;">
                        If you mark as done, this enquiry can be converted to Registration.
                    </div>
                `,
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'Done + Convert',
                denyButtonText: 'Only Done',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#e91e63',
            }).then((r) => {
                if (r.isDenied) {
                    setHiddenField(f, 'convert', '0');
                    ensureMarkDoneField(f);
                    f.submit();
                    return;
                }

                if (r.isConfirmed) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Choose Registration Type',
                        html: `
                            ${swalCss}
                            <div class="swal-select-wrap">
                                <select id="regTypeSelect" class="swal-modern-select">
                                    <option value="">Select type</option>
                                    <option value="course">Course</option>
                                    <option value="internship">Internship</option>
                                    <option value="workshop">Workshop</option>
                                </select>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Convert',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#e91e63',
                        focusConfirm: false,
                        preConfirm: () => {
                            const sel = document.getElementById('regTypeSelect');
                            const value = sel ? sel.value : '';
                            if (!value) {
                                Swal.showValidationMessage('Please choose a type');
                                return false;
                            }
                            return value;
                        },
                    }).then((x) => {
                        if (!x.isConfirmed) return;

                        setHiddenField(f, 'convert', '1');
                        setHiddenField(f, 'reg_type', x.value);
                        ensureMarkDoneField(f);
                        f.submit();
                    });
                }
            });
        });
    });
})();

(function () {
    const addForm = document.getElementById('addFollowupForm');
    if (!addForm) return;

    const enquirySel = addForm.querySelector('select[name="enquiry_id"]');
    const fDate = addForm.querySelector('input[name="followup_date"]');
    const fTime = addForm.querySelector('input[name="followup_time"]');
    const fType = addForm.querySelector('select[name="followup_type"]');
    const nextDate = addForm.querySelector('input[name="next_followup_date"]');
    const nextTime = addForm.querySelector('input[name="next_followup_time"]');
    const notes = addForm.querySelector('textarea[name="notes"]');

    const filesInp = addForm.querySelector('#addAttachments');
    const preview = document.getElementById('addFilePreview');

    const banner = document.getElementById('scheduleBanner');
    const bannerText = document.getElementById('scheduleBannerText');

    function toYMD(d) {
        const z = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
    }
    function toPretty(ymd) {
        const parts = (ymd || '').split('-');
        if (parts.length !== 3) return ymd;
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }

    window.hideScheduleBanner = function () {
        if (banner) banner.style.display = 'none';
    };

    function showBanner(msg) {
        if (!banner || !bannerText) return;
        bannerText.innerText = msg;
        banner.style.display = 'flex';
    }

    function updateBannerByDate() {
        const chosen = (fDate?.value || '').trim();
        if (!chosen) {
            if (banner) banner.style.display = 'none';
            return;
        }

        const today = toYMD(new Date());

        if (chosen === today) {
            showBanner(`Follow-up is set for Today (${toPretty(chosen)}).`);
            return;
        }
        if (chosen > today) {
            const t = (fTime?.value || '').trim();
            showBanner(`Follow-up scheduled on ${toPretty(chosen)}${t ? ' at ' + t : ''}.`);
            return;
        }
        showBanner(`⚠ This follow-up date is in the past (${toPretty(chosen)}). Please confirm.`);
    }

    function fileIcon(name) {
        const ext = (name.split('.').pop() || '').toLowerCase();
        if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) return 'fa-image';
        if (['mp3', 'wav', 'm4a', 'aac', 'ogg'].includes(ext)) return 'fa-headphones';
        if (['mp4', 'mov', 'avi', 'mkv', 'webm'].includes(ext)) return 'fa-video';
        if (['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'].includes(ext)) return 'fa-file-alt';
        return 'fa-paperclip';
    }

    if (filesInp && preview) {
        filesInp.addEventListener('change', function () {
            preview.innerHTML = '';
            const files = Array.from(this.files || []);
            if (!files.length) return;

            files.slice(0, 12).forEach((f) => {
                const chip = document.createElement('div');
                chip.className = 'file-chip';
                chip.innerHTML = `<i class="fas ${fileIcon(f.name)}"></i> ${f.name}`;
                preview.appendChild(chip);
            });

            if (files.length > 12) {
                const chip = document.createElement('div');
                chip.className = 'file-chip';
                chip.innerHTML = `<i class="fas fa-ellipsis-h"></i> +${files.length - 12} more`;
                preview.appendChild(chip);
            }
        });
    }

    fDate && fDate.addEventListener('change', function () {
        updateBannerByDate();
        const chosen = (this.value || '').trim();
        if (!chosen) return;

        Swal.fire({
            icon: 'info',
            title: 'Reminder',
            text: `Follow-up scheduled on ${toPretty(chosen)}.`,
            confirmButtonColor: '#e91e63',
        });
    });

    fTime && fTime.addEventListener('change', updateBannerByDate);

    function validateAddFollowup() {
        const enq = (enquirySel?.value || '').trim();
        const fd = (fDate?.value || '').trim();
        const ft = (fType?.value || 'call').trim();
        const nd = (nextDate?.value || '').trim();
        const ntt = (nextTime?.value || '').trim();
        const nt = (notes?.value || '').trim();

        if (!enq) {
            Swal.fire({ icon: 'error', title: 'Required', text: 'Please select an enquiry.', confirmButtonColor: '#e91e63' });
            return false;
        }
        if (!fd) {
            Swal.fire({ icon: 'error', title: 'Required', text: 'Follow-up date is required.', confirmButtonColor: '#e91e63' });
            return false;
        }
        if (nd && nd < fd) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Next Date',
                text: 'Next follow-up date cannot be before follow-up date.',
                confirmButtonColor: '#e91e63',
            });
            return false;
        }
        if (!nd && ntt) {
            Swal.fire({
                icon: 'error',
                title: 'Next Date Missing',
                text: 'You selected next follow-up time, please select next follow-up date also.',
                confirmButtonColor: '#e91e63',
            });
            return false;
        }
        if (['call', 'whatsapp', 'sms', 'email', 'walkin'].includes(ft) && nt.length < 3) {
            Swal.fire({
                icon: 'error',
                title: 'Notes Required',
                text: 'Please add short notes (minimum 3 characters).',
                confirmButtonColor: '#e91e63',
            });
            return false;
        }
        return true;
    }

    addForm.addEventListener('submit', function (e) {
        if (!validateAddFollowup()) {
            e.preventDefault();
            return;
        }
        e.preventDefault();

        Swal.fire({
            icon: 'question',
            title: 'Save Follow-up?',
            text: 'Do you want to create this follow-up?',
            showCancelButton: true,
            confirmButtonText: 'Yes, Save',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#e91e63',
        }).then((r) => {
            if (r.isConfirmed) {
  let a = addForm.querySelector('input[name="add_followup"]');
  if (!a) {
    a = document.createElement('input');
    a.type = 'hidden';
    a.name = 'add_followup';
    addForm.appendChild(a);
  }
  a.value = '1';
  addForm.submit();
}
        });
    });

    updateBannerByDate();
})();
</script>
