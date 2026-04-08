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

function followupMakeRegistrationNo(PDO $pdo): string {
    $prefix = 'REG-' . date('Ym') . '-';
    $like   = $prefix . '%';

    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrations
        WHERE registration_no LIKE ?
    ");
    $st->execute([$like]);
    $seq = (int)$st->fetchColumn() + 1;

    do {
        $candidate = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE registration_no=?");
        $chk->execute([$candidate]);
        $exists = (int)$chk->fetchColumn() > 0;
        $seq++;
    } while ($exists);

    return $candidate;
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

     // Load followups by tab
    // Load followups by tab
if (isset($_GET['tab'])) {

    $tab = $_GET['tab'] ?? 'today';

    $where = "";

    if ($tab === "today") {
        $where = "WHERE f.followup_date = CURDATE()";
    }
    elseif ($tab === "pending") {
        $where = "WHERE f.status = 'pending'";
    }
    elseif ($tab === "missed") {
        $where = "WHERE f.status = 'missed'";
    }
    elseif ($tab === "done") {
        $where = "WHERE f.status = 'done'";
    }

    $sql = "
        SELECT
            f.*,
            e.enquiry_no,
            e.name,
            e.phone
        FROM enquiry_followups f
        JOIN enquiries e ON e.id = f.enquiry_id
        $where
        ORDER BY f.followup_date DESC
    ";

    $st = $pdo->query($sql);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {

       
        exit;
    }

foreach ($rows as $r) {

    $status = $r['status'] ?? 'pending';

    if($status == "done"){
        $statusBadge = '<span class="tag" style="color:#2e7d32;background:#e8f5e9;">Done</span>';
    }
    elseif($status == "missed"){
        $statusBadge = '<span class="tag" style="color:#d32f2f;background:#ffebee;">Missed</span>';
    }
    else{
        $statusBadge = '<span class="tag" style="color:#ff9800;background:#fff4e5;">Pending</span>';
    }

    echo "<tr>";

    echo "<td>".$r['followup_date']."</td>";

    echo "<td>
            <b>".$r['enquiry_no']."</b><br>
            <small>".$r['name']."</small>
          </td>";

    echo "<td>".$r['phone']."</td>";

    echo "<td>".$r['followup_type']."</td>";

    echo "<td class='tc'>".$statusBadge."</td>";

    echo "<td>".$r['next_followup_date']."</td>";

    echo "<td>";

    // View button
    echo "<button type='button'
    class='icon-btn btn-view'
    onclick='openHistoryModal(".$r['enquiry_id'].")'>
    <span class='btn-inner'>
    <i class='fas fa-eye'></i>
    <span class='btn-mobile-label'>View</span>
    </span>
    </button>";

    // Edit button
    echo "<button type='button'
    class='icon-btn btn-edit'
    onclick='openEditModal(".$r['id'].")'>
    <span class='btn-inner'>
    <i class='fas fa-pen'></i>
    <span class='btn-mobile-label'>Edit</span>
    </span>
    </button>";

    // Done button only if not done
    if($status != "done"){

        echo "<form method='POST' class='doneForm' style='display:inline;'>

        <input type='hidden' name='csrf_token' value='".h(generateCSRF())."'>

        <input type='hidden' name='followup_id' value='".$r['id']."'> 

        <button type='submit'
        name='mark_done'
        class='icon-btn btn-done'>
        <span class='btn-inner'>
        <i class='fas fa-check'></i>
        <span class='btn-mobile-label'>Done</span>
        </span>

        </button>

        </form>";
    }

    echo "</td>";

    echo "</tr>";
}
    exit;
}

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
                <div class="modal-title"><?= h($enq['enquiry_no'] ?? ('ENQ-'.$enq['id'])) ?> � <?= h($enq['name'] ?? '-') ?></div>
                <div class="muted">
                    Phone: <?= h($enq['phone'] ?? '-') ?> � 
                    Email: <?= h($enq['email'] ?? '-') ?> � 
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
                                    <?= h($f['followup_date']) ?> <?= h($f['followup_time'] ?? '') ?> � <?= h($f['followup_type'] ?? '-') ?>
                                </div>
                                <div class="muted">
                                    By: <?= h($f['created_by_name'] ?? '-') ?>
                                    <?php if (!empty($f['next_followup_date'])): ?>
                                        � Next: <?= h($f['next_followup_date']) ?> <?= h($f['next_followup_time'] ?? '') ?>
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
                                <?= !empty($f['verified_at']) ? ' � ' . h($f['verified_at']) : '' ?>
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
                <div class="muted"><?= h($f['enquiry_no'] ?? ('ENQ-'.$f['enquiry_id'])) ?> � <?= h($f['enquiry_name'] ?? '-') ?></div>
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
        $regMode = trim($_POST['reg_mode'] ?? 'draft'); // ?? IMPORTANT

        if ($convert !== 1) {
            $error = "Please choose Course or Internship, then select Convert Now or Save Draft.";
        } elseif ($fid <= 0) {
            $error = "Invalid follow-up.";
        } else {

            try {
                if (!in_array($regType, ['course', 'internship'], true)) {
                    throw new Exception("Please select whether the student is joining Course or Internship.");
                }

                if (!in_array($regMode, ['active', 'draft'], true)) {
                    throw new Exception("Please choose whether to convert now or save as draft.");
                }

                // GET FOLLOWUP
                $st = $pdo->prepare("SELECT id, enquiry_id, branch_id FROM enquiry_followups WHERE id=? LIMIT 1");
                $st->execute([$fid]);
                $fu = $st->fetch(PDO::FETCH_ASSOC);

                if (!$fu) {
                    throw new Exception("Follow-up not found.");
                }

                $enquiryId = (int)$fu['enquiry_id'];

                // GET ENQUIRY
                $eq = $pdo->prepare("
                    SELECT id, handled_by, branch_id, name, phone, email, course_interest
                    FROM enquiries
                    WHERE id=? LIMIT 1
                ");
                $eq->execute([$enquiryId]);
                $enq = $eq->fetch(PDO::FETCH_ASSOC);

                if (!$enq) {
                    throw new Exception("Enquiry not found.");
                }

                $assignedTo  = (int)($enq['handled_by'] ?? 0);
                $useBranch   = (int)($enq['branch_id'] ?? 0);
                $studentName = trim((string)($enq['name'] ?? ''));
                $studentPhone = trim((string)($enq['phone'] ?? ''));
                $studentEmail = trim((string)($enq['email'] ?? ''));
                $programName = trim((string)($enq['course_interest'] ?? ''));
                $joinedOn    = date('Y-m-d');
                $sourceType  = 'direct';

                $pdo->beginTransaction();

                // ? MARK FOLLOWUP DONE
                $pdo->prepare("
                    UPDATE enquiry_followups
                    SET status='done', done_at=NOW(), updated_at=NOW()
                    WHERE id=?
                ")->execute([$fid]);

                // ? UPDATE ENQUIRY STATUS
                $pdo->prepare("
                    UPDATE enquiries
                    SET status='converted', updated_at=NOW()
                    WHERE id=?
                ")->execute([$enquiryId]);

                $regId = 0;

                if ($convert === 1) {

                    // CHECK EXISTING REGISTRATION
                    $chk = $pdo->prepare("
                        SELECT id 
                        FROM registrations 
                        WHERE enquiry_id=? AND reg_type=? 
                        ORDER BY id DESC LIMIT 1
                    ");
                    $chk->execute([$enquiryId, $regType]);
                    $existing = (int)$chk->fetchColumn();

                    if ($existing > 0) {

                        $regId = $existing;

                        // ?? UPDATE STATUS (VERY IMPORTANT)
                        $stReg = $pdo->prepare("
                            SELECT registration_no
                            FROM registrations
                            WHERE id=? LIMIT 1
                        ");
                        $stReg->execute([$regId]);
                        $regRow = $stReg->fetch(PDO::FETCH_ASSOC) ?: [];

                        $registrationNo = trim((string)($regRow['registration_no'] ?? ''));
                        if ($registrationNo === '') {
                            $registrationNo = followupMakeRegistrationNo($pdo);
                        }

                        $pdo->prepare("
                            UPDATE registrations
                            SET
                                registration_no=?,
                                source_type=COALESCE(source_type, ?),
                                joined_on=COALESCE(joined_on, ?),
                                enquiry_snapshot_name=CASE
                                    WHEN COALESCE(NULLIF(enquiry_snapshot_name, ''), '')='' THEN ?
                                    ELSE enquiry_snapshot_name
                                END,
                                enquiry_snapshot_phone=CASE
                                    WHEN COALESCE(NULLIF(enquiry_snapshot_phone, ''), '')='' THEN ?
                                    ELSE enquiry_snapshot_phone
                                END,
                                enquiry_snapshot_email=CASE
                                    WHEN COALESCE(NULLIF(enquiry_snapshot_email, ''), '')='' THEN ?
                                    ELSE enquiry_snapshot_email
                                END,
                                program_name=CASE
                                    WHEN COALESCE(NULLIF(program_name, ''), '')='' THEN ?
                                    ELSE program_name
                                END,
                                registration_status=?,
                                updated_at=NOW()
                            WHERE id=?
                        ")->execute([
                            $registrationNo,
                            $sourceType,
                            $joinedOn,
                            $studentName,
                            $studentPhone,
                            $studentEmail,
                            $programName,
                            $regMode,
                            $regId
                        ]);

                    } else {

                        // ?? INSERT NEW REGISTRATION
                        $registrationNo = followupMakeRegistrationNo($pdo);
                        $ins = $pdo->prepare("
                            INSERT INTO registrations
                            (
                                registration_no,
                                enquiry_id,
                                branch_id,
                                reg_type,
                                source_type,
                                registration_status,
                                assigned_to,
                                created_by,
                                joined_on,
                                enquiry_snapshot_name,
                                enquiry_snapshot_phone,
                                enquiry_snapshot_email,
                                program_name,
                                created_at,
                                updated_at
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");

                        $ins->execute([
                            $registrationNo,
                            $enquiryId,
                            $useBranch,
                            $regType,
                            $sourceType,
                            $regMode, // ?? FIXED
                            ($assignedTo > 0 ? $assignedTo : null),
                            $userId,
                            $joinedOn,
                            $studentName,
                            $studentPhone,
                            $studentEmail,
                            $programName
                        ]);

                        $regId = (int)$pdo->lastInsertId();
                    }
                }

                $pdo->commit();

                // ? REDIRECT ONLY IF CONVERT NOW
                if ($regMode === 'active') {

                    redirect("index.php?page=registrations/convert&enquiry_id={$enquiryId}&type={$regType}&reg_id={$regId}");
                    exit;
                }

                redirect("index.php?page=registrations/drafts");
                exit;

            } catch (Exception $e) {

                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Error: " . $e->getMessage();

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

if ($tab === 'today') {
    $where[] = "f.followup_date = CURDATE()";
}
elseif ($tab === 'pending') {
    $where[] = "f.status = 'pending'";
}
elseif ($tab === 'missed') {
    $where[] = "f.followup_date < CURDATE()";
    $where[] = "f.status = 'pending'";
}
elseif ($tab === 'done') {
    $where[] = "f.status = 'done'";
}

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




// ==============================
// FOLLOWUP NOTIFICATION COUNTS
// ==============================

$todayCount = 0;
$missedCount = 0;
$upcomingCount = 0;

try {

    // Today Followups
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM enquiry_followups
        WHERE followup_date = CURDATE()
        AND status = 'pending'
        AND created_by = ?
    ");
    $st->execute([$userId]);
    $todayCount = (int)$st->fetchColumn();


    // Missed Followups
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM enquiry_followups
        WHERE followup_date < CURDATE()
        AND status = 'pending'
        AND created_by = ?
    ");
    $st->execute([$userId]);
    $missedCount = (int)$st->fetchColumn();


    // Upcoming Followups
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM enquiry_followups
        WHERE followup_date > CURDATE()
        AND status = 'pending'
        AND created_by = ?
    ");
    $st->execute([$userId]);
    $upcomingCount = (int)$st->fetchColumn();

} catch(Exception $e) {

    $todayCount = 0;
    $missedCount = 0;
    $upcomingCount = 0;

}

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
 .filter-grid{
display:flex;
flex-wrap:wrap;
gap:14px;
align-items:flex-end;
}

.filter-grid > div{
flex:1;
min-width:220px;
}

.filter-actions{
display:flex;
gap:10px;
align-items:flex-end;
}

@media(max-width:900px){

.filter-grid{
flex-direction:column;
}

.filter-actions{
width:100%;
}

.filter-actions button,
.filter-actions a{
flex:1;
text-align:center;
}

}   


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


.lbl{ font-weight:900; font-size:13px; display:block; margin-bottom:6px; color:#111; }

.modal-form input, .modal-form select, .modal-form textarea{
  width:100%;
  padding:10px 12px;
  border-radius:12px;
  border:1px solid #e5e7eb;
  outline:none;
  background:#fff;
}

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


.crm-followup-layout{
display:grid;
grid-template-columns:1fr 2fr;
gap:20px;
align-items:start;
}

.crm-followup-left{
min-width:0;
order:2;
}

.crm-followup-right{
position:sticky;
top:20px;
order:1;
}

@media(max-width:1100px){

.crm-followup-layout{
grid-template-columns:1fr;
}

.crm-followup-right{
position:relative;
}

}


.followup-filter-row{
display:flex;
align-items:flex-end;
gap:14px;
flex-wrap:wrap;
}

.filter-field{
flex:1;
min-width:200px;
}

.filter-actions{
display:flex;
gap:10px;
align-items:center;
}

.icon-filter-btn{
width:42px;
height:42px;
border-radius:10px;
border:none;
display:flex;
align-items:center;
justify-content:center;
font-size:16px;
cursor:pointer;
transition:0.2s;
}

.apply-btn{
background:#e91e63;
color:#fff;
}

.apply-btn:hover{
background:#d81b60;
}

.reset-btn{
background:#fff;
border:1px solid #f1b7c8;
color:#e91e63;
}

.reset-btn:hover{
background:#fff0f5;
}

@media(max-width:900px){

.followup-filter-row{
flex-direction:column;
}

.filter-actions{
width:100%;
}

.icon-filter-btn{
width:100%;
}

}



/*Datatable CSS */
.crm-card {
  background: #fff;
  border-radius: 14px;
  padding: 20px;
  box-shadow: 0 8px 20px rgba(0,0,0,.05);
  border: 1px solid #f1d6e3;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.crm-card h3 {
  margin-bottom: 16px;
}

/* Table */
.crm-table-wrapper {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.crm-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #f1d6e3;
}

.crm-table th,
.crm-table td {
  border: 1px solid #f1d6e3;
  padding: 10px;
  font-size: 13px;
  white-space: nowrap;
}

.crm-table th {
  background: #fff0f5;
  font-weight: 600;
  text-align: left;
}

/* Actions */
.crm-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  color: #fff;
  margin-right: 5px;
  text-decoration: none;
  transition: var(--transition);
}

.crm-btn:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}

.crm-edit {
  background: #e91e63;
}

.crm-delete {
  background: #dc3545;
}

/* DataTable header/footer */
.crm-table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  flex-wrap: wrap;
  gap: 10px;
}

.crm-table-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 15px;
  flex-wrap: wrap;
  gap: 10px;
}

/* Search + length */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dt-buttons {
  width: 100%;
  display: flex;
  justify-content: center;
  margin-bottom: 10px;
}

.dataTables_wrapper .dataTables_filter input {
  width: 100% !important;
  max-width: 260px;
  padding: 10px 14px;
  border: 1px solid var(--primary-light);
  border-radius: 999px;
  outline: none;
  transition: var(--transition);
}

.dataTables_wrapper .dataTables_filter input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(233,30,99,.12);
}

.dataTables_wrapper .dataTables_length select {
  
  border: 1px solid #000000;
  border-radius: 50px;
  background: #fff;
  outline: none;
}

/* Export button */
.crm-export-btn {
  background: var(--primary) !important;
  color: #fff !important;
  border: none !important;
  padding: 10px 16px !important;
  border-radius: var(--radius-md) !important;
  font-weight: 600 !important;
  transition: var(--transition) !important;
}

.crm-export-btn:hover {
  background: var(--primary-dark) !important;
}

/* Pagination */
.dataTables_wrapper .dataTables_paginate {
  display: flex;
  justify-content: center;
  margin-top: 10px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
  border: 1px solid var(--gray-300) !important;
  background: #fff !important;
  border-radius: var(--radius-md) !important;
  color: var(--gray-700) !important;
  padding: 6px 12px !important;
  margin-left: 4px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
  background: var(--primary) !important;
  color: #fff !important;
  border-color: var(--primary) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
  background: #fff5f9 !important;
  color: var(--primary-dark) !important;
  border-color: #f1d6e3 !important;
}

/* Mobile */
@media (max-width: 768px) {
  .crm-table {
    min-width: 700px;
  }

  .crm-export-btn {
    width: 100% !important;
  }

  .dataTables_wrapper .dataTables_filter input {
    max-width: 100%;
  }
}


/* DATATABLE HEADER FIX */

/* Fix DataTables header alignment */

.crm-table-header{
display:flex !important;
align-items:center;
justify-content:space-between;
flex-wrap:nowrap;
gap:20px;
width:100%;
}

/* Length */

.crm-table-header .dataTables_length{
display:flex;
align-items:center;
gap:6px;
}

/* Search */

.crm-table-header .dataTables_filter{
margin-left:auto;
display:flex;
align-items:center;
}

/* Search input */

.crm-table-header .dataTables_filter input{
width:220px;
margin-left:6px;
}

/* Export button */

.crm-table-header .dt-buttons{
margin-left:10px;
}

/* Keep everything in one row */

.crm-table-header > div{
display:flex;
align-items:center;
}

/* Mobile */

@media(max-width:768px){

.crm-table-header{
flex-wrap:wrap;
}

.crm-table-header .dataTables_filter input{
width:100%;
}

}


.followup-alerts{
display:flex;
gap:14px;
margin-bottom:18px;
flex-wrap:wrap;
}

.alert-card{
flex:1;
min-width:180px;
background:#fff;
border-radius:12px;
padding:16px;
display:flex;
align-items:center;
gap:12px;
box-shadow:0 8px 20px rgba(0,0,0,.05);
border:1px solid #f1d6e3;
}

.alert-card i{
font-size:20px;
}

.alert-card b{
font-size:20px;
display:block;
}

.alert-card span{
font-size:12px;
color:#777;
}

.alert-card.today i{ color:#e91e63; }
.alert-card.missed i{ color:#ff5722; }
.alert-card.upcoming i{ color:#3f51b5; }



/* ===============================
DATATABLE HEADER FINAL FIX
=============================== */

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dt-buttons{
width:auto !important;
margin-bottom:0 !important;
}

.dataTables_wrapper .dataTables_length{
float:left;
display:flex;
align-items:center;
gap:6px;
}

.dataTables_wrapper .dataTables_filter{
float:right;
display:flex;
align-items:center;
gap:6px;
}

.dataTables_wrapper .dataTables_filter input{
width:220px !important;
}

.dataTables_wrapper .dataTables_length select{
border-radius:8px;
padding:4px 8px;
}

.dataTables_wrapper .dataTables_filter input{
border-radius:20px;
padding:6px 12px;
}

.fu-page-head{
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
flex-wrap:wrap;
margin-bottom:12px;
}
.fu-page-title{
margin:0;
color:#be185d;
font-weight:900;
display:flex;
align-items:center;
gap:10px;
}
.fu-quick-nav{
display:flex;
gap:8px;
flex-wrap:wrap;
}
.fu-nav-btn{
display:inline-flex;
align-items:center;
gap:7px;
padding:8px 12px;
border-radius:10px;
border:1px solid #f1d6e3;
background:#fff;
color:#9d174d;
font-weight:800;
font-size:13px;
text-decoration:none;
transition:.2s ease;
}
.fu-nav-btn:hover{
background:#fff1f7;
border-color:#e91e63;
}
.fu-sec-title{
display:flex;
align-items:center;
gap:8px;
font-weight:900;
}
.fu-sec-sub{
font-size:12px;
color:#6b7280;
margin-top:2px;
}
.fu-table-title{
margin:0;
font-size:15px;
font-weight:900;
color:#374151;
line-height:1.2;
}
.followup-tabs-wrap{
padding:14px 14px 0;
}
.followup-filters-wrap{
padding:14px;
padding-top:10px;
}
.followup-filters-wrap .followup-filter-row{
padding:12px;
border:1px solid #f1d6e3;
border-radius:12px;
background:#fff8fc;
}
.followup-records-head{
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
flex-wrap:nowrap;
margin-bottom:12px;
}
.followup-records-left{
display:flex;
align-items:center;
gap:12px;
flex-wrap:nowrap;
}
.followup-table-controls,
.followup-table-footer{
width:auto;
}
.followup-table-controls .dt-top,
.followup-table-footer .dt-bottom{
display:flex;
align-items:center;
justify-content:flex-end;
gap:12px;
flex-wrap:nowrap;
margin:0;
}
.followup-table-footer{
width:100%;
}
.followup-table-footer .dt-bottom{
justify-content:space-between;
flex-wrap:wrap;
}
.followup-table-controls .dt-top > *{
margin:0 !important;
}
.followup-table-controls .dataTables_length,
.followup-table-controls .dataTables_filter,
.followup-table-controls .dt-buttons{
display:flex;
align-items:center;
gap:8px;
margin:0;
line-height:1;
min-height:38px;
}
.followup-table-controls .dt-buttons{
display:none !important;
}
.followup-table-controls .dataTables_length label{
display:inline-flex;
align-items:center;
gap:8px;
white-space:nowrap;
margin:0;
}
.followup-table-controls .dataTables_filter label{
display:inline-flex;
align-items:center;
gap:8px;
white-space:nowrap;
margin:0;
}
.followup-table-controls .dataTables_filter{
margin:0 !important;
}
.followup-table-controls .dataTables_filter input{
min-width:220px;
border:1px solid #f1b7c8;
border-radius:999px;
padding:8px 12px;
margin:0 !important;
}
@media(max-width:1100px){
  .followup-records-head{
    flex-wrap:wrap;
  }
  .followup-records-left{
    flex-wrap:wrap;
  }
  .followup-table-controls{
    width:100%;
  }
  .followup-table-controls .dt-top{
    justify-content:space-between;
    flex-wrap:wrap;
  }
}
.followup-table-controls .dataTables_length select{
border:1px solid #f1b7c8;
border-radius:10px;
padding:6px 10px;
}
.followup-table-controls .dt-buttons .buttons-csv,
.followup-table-controls .dt-buttons button{
background:#e91e63 !important;
border:1px solid #e91e63 !important;
color:#fff !important;
border-radius:10px !important;
padding:8px 14px !important;
font-weight:800 !important;
}
.followup-table-footer .dataTables_info{
font-weight:600;
color:#6b7280;
}
.followup-table-footer .dataTables_paginate{
display:flex;
align-items:center;
gap:6px;
}
.followup-table-footer .dataTables_paginate .paginate_button{
display:inline-flex !important;
align-items:center;
justify-content:center;
min-width:34px;
height:34px;
padding:0 10px !important;
margin:0 !important;
border:1px solid #f1d6e3 !important;
border-radius:8px !important;
background:#fff !important;
color:#374151 !important;
font-weight:700 !important;
}
.followup-table-footer .dataTables_paginate .paginate_button.current{
background:#e91e63 !important;
border-color:#e91e63 !important;
color:#fff !important;
}
.followup-table-footer .dataTables_paginate .paginate_button:hover{
background:#fff1f7 !important;
border-color:#e91e63 !important;
color:#9d174d !important;
}
.followup-records-meta{
display:inline-flex;
align-items:center;
gap:8px;
padding:5px 10px;
border-radius:999px;
border:1px solid #f1d6e3;
background:#fff;
font-size:12px;
font-weight:800;
color:#9d174d;
}
.crm-table tbody tr:hover{
background:#fff5fa;
}
.crm-table-wrapper{
position:relative;
}

/* Follow-ups table: keep controls/dropdowns from creating temporary scrollbars */
#followupsSection .crm-table-wrapper{
overflow: visible !important;
}

#followupsSection .crm-table-wrapper .dataTables_wrapper{
overflow: visible !important;
}

#followupsSection #usersTable.crm-table{
min-width: 0 !important;
table-layout: auto;
}

#followupsSection #usersTable.crm-table th,
#followupsSection #usersTable.crm-table td{
white-space: normal;
}
.followup-table-loading{
position:absolute;
inset:0;
background:rgba(255,255,255,.8);
display:flex;
align-items:center;
justify-content:center;
gap:10px;
z-index:5;
font-weight:800;
color:#9d174d;
}
.followup-loader-dot{
width:18px;
height:18px;
border:2px solid #f3c0d6;
border-top-color:#e91e63;
border-radius:50%;
animation:fuSpin .8s linear infinite;
}
@keyframes fuSpin { to { transform: rotate(360deg); } }

#addFollowupSection .section-card{
border:1px solid #f1d6e3;
box-shadow:0 12px 28px rgba(0,0,0,.06);
}
#addFollowupSection .card-header{
background:linear-gradient(135deg,#fff4fa,#fff);
}
.add-fu-form{
padding:16px;
}
.add-fu-grid{
display:grid;
grid-template-columns:1fr 1fr;
gap:14px;
}
.add-fu-grid .fu-full{
grid-column:1 / -1;
}
.add-fu-triple{
display:grid;
grid-template-columns:repeat(2,minmax(0,1fr));
gap:14px;
}
.add-fu-triple > div:last-child{
grid-column:1 / -1;
}
.add-fu-form input,
.add-fu-form select,
.add-fu-form textarea{
width:100%;
padding:10px 12px;
border:1px solid #e5e7eb;
border-radius:12px;
background:#fff;
outline:none;
transition:.18s ease;
}
.add-fu-form input:focus,
.add-fu-form select:focus,
.add-fu-form textarea:focus{
border-color:rgba(233,30,99,.55);
box-shadow:0 0 0 4px rgba(233,30,99,.12);
}
.add-fu-actions{
margin-top:14px;
display:flex;
justify-content:flex-end;
gap:10px;
flex-wrap:wrap;
}
.add-fu-save{
min-width:180px;
}
.add-fu-save:disabled{
opacity:.55;
cursor:not-allowed;
box-shadow:none;
transform:none;
}
.add-fu-form select.js-enhanced{
display:none;
}
.fu-smart-select{
position:relative;
}
.fu-smart-toggle{
width:100%;
min-height:44px;
border:1px solid #e5e7eb;
border-radius:14px;
background:#fff;
padding:10px 12px;
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
cursor:pointer;
font-weight:600;
color:#374151;
}
.fu-smart-toggle:focus{
outline:none;
border-color:rgba(233,30,99,.55);
box-shadow:0 0 0 4px rgba(233,30,99,.12);
}
.fu-smart-toggle i{
color:#9ca3af;
}
.fu-smart-panel{
position:absolute;
left:0;
right:0;
top:calc(100% + 8px);
background:#fff;
border:1px solid #f3d8e5;
border-radius:14px;
box-shadow:0 14px 28px rgba(0,0,0,.12);
padding:10px;
z-index:40;
display:none;
}
.fu-smart-select.open .fu-smart-panel{
display:block;
}
.fu-smart-search{
width:100%;
border:1px solid #f1d6e3;
border-radius:10px;
min-height:38px;
padding:8px 10px;
font-size:13px;
margin-bottom:8px;
}
.fu-smart-search:focus{
outline:none;
border-color:#e91e63;
box-shadow:0 0 0 3px rgba(233,30,99,.12);
}
.fu-smart-list{
max-height:220px;
overflow:auto;
display:flex;
flex-direction:column;
gap:6px;
padding-right:2px;
}
.fu-smart-item{
border:1px solid #f5dce8;
border-radius:10px;
background:#fff;
padding:8px 10px;
font-size:13px;
text-align:left;
cursor:pointer;
transition:.15s ease;
}
.fu-smart-item:hover{
background:#fff1f7;
border-color:#ef9cc0;
}
.fu-smart-item.active{
background:#ffe6f1;
border-color:#e91e63;
color:#9d174d;
font-weight:700;
}
.fu-smart-empty{
padding:10px;
font-size:12px;
color:#6b7280;
text-align:center;
border:1px dashed #f1d6e3;
border-radius:10px;
}
.add-fu-list{
display:inline-flex;
align-items:center;
justify-content:center;
text-decoration:none;
padding:10px 14px;
border-radius:10px;
font-weight:700;
border:1px solid #f1d6e3;
background:#fff;
color:#9d174d;
transition:.2s ease;
}
.add-fu-list:hover{
background:#fff1f7;
border-color:#e91e63;
}
@media(max-width:1100px){
  .add-fu-grid{ grid-template-columns:1fr; }
  .add-fu-triple{ grid-template-columns:1fr; }
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

/* ===== GLOBAL BUTTON STANDARDIZATION ===== */
button,
.btn,
.crm-action-btn,
.btn-filter,
.btn-reset,
.btn-add,
.btn-excel,
.action-btn,
.btn-icon-only,
a.btn,
input[type="button"],
input[type="submit"],
input[type="reset"],
[role="button"] {
    font-size: 0.92rem;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-icon-only,
.crm-action-btn,
.action-btn,
.btn-sm,
.btn-xs,
button.btn-icon,
a.btn-icon,
.btn i:only-child,
button i:only-child {
    font-size: 0.9rem;
    min-height: 34px;
    padding: 8px;
    border-radius: 10px;
    font-weight: 600;
}

/* ===== Followups: Assessment-style responsive behavior ===== */
.crm-followup-left .btn-mobile-label {
  display: none;
}

.crm-followup-left .icon-filter-btn,
.crm-followup-left .icon-btn {
  text-decoration: none;
}

.crm-followup-left .icon-filter-btn .btn-inner,
.crm-followup-left .icon-btn .btn-inner {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  line-height: 1;
}

.crm-followup-left .followup-records-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:nowrap;
}

.crm-followup-left .followup-records-left{
  display:flex;
  align-items:center;
  flex-wrap:nowrap;
  min-width:0;
}

.crm-followup-left .followup-table-controls{
  margin-left:auto;
}

.crm-followup-left .followup-table-controls .dt-top{
  display:flex !important;
  align-items:center !important;
  justify-content:flex-end !important;
  flex-wrap:nowrap !important;
  gap:10px !important;
}

.crm-followup-left .followup-table-controls .dataTables_length,
.crm-followup-left .followup-table-controls .dataTables_filter{
  width:auto !important;
}

.crm-followup-left .followup-table-controls .dataTables_length label,
.crm-followup-left .followup-table-controls .dataTables_filter label{
  width:auto !important;
  display:inline-flex !important;
  align-items:center !important;
  gap:8px !important;
  white-space:nowrap !important;
}

.crm-followup-left .followup-table-controls .dataTables_filter input{
  width:220px !important;
  min-width:220px !important;
}

@media (max-width: 479px){
  .crm-followup-left .followup-records-head{
    flex-direction:column !important;
    align-items:flex-start !important;
    gap:10px !important;
  }
  .crm-followup-left .followup-records-left{
    width:100% !important;
  }
  .crm-followup-left .followup-table-controls{
    width:100% !important;
    margin-left:0 !important;
  }
  .crm-followup-left .followup-table-controls .dt-top{
    width:100% !important;
    justify-content:flex-start !important;
    align-items:center !important;
    flex-wrap:wrap !important;
    gap:8px !important;
  }
  .crm-followup-left .followup-table-controls .dataTables_length,
  .crm-followup-left .followup-table-controls .dataTables_filter{
    width:100% !important;
  }
  .crm-followup-left .followup-table-controls .dataTables_length label,
  .crm-followup-left .followup-table-controls .dataTables_filter label{
    width:100% !important;
    white-space:nowrap !important;
  }
  .crm-followup-left .followup-table-controls .dataTables_filter input{
    width:100% !important;
    min-width:0 !important;
  }

  .crm-followup-left .followup-filter-row{
    flex-direction:column !important;
    align-items:stretch !important;
  }
  .crm-followup-left .filter-field{
    width:100% !important;
    min-width:0 !important;
  }
  .crm-followup-left .filter-actions{
    width:100% !important;
    display:grid !important;
    grid-template-columns:repeat(2,minmax(0,1fr)) !important;
    gap:8px !important;
    align-items:stretch !important;
  }
  .crm-followup-left .icon-filter-btn{
    width:100% !important;
    min-width:0 !important;
    height:44px !important;
    min-height:44px !important;
    border-radius:10px !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    padding:0 10px !important;
  }
  .crm-followup-left .icon-filter-btn .btn-mobile-label{
    display:inline-block !important;
    font-size:11px !important;
    font-weight:700 !important;
    line-height:1 !important;
  }
}

@media (min-width: 480px) and (max-width: 767px){
  .crm-followup-left .followup-records-head{
    flex-direction:column !important;
    align-items:flex-start !important;
    gap:10px !important;
  }
  .crm-followup-left .followup-records-left,
  .crm-followup-left .followup-table-controls{
    width:100% !important;
    margin-left:0 !important;
  }
  .crm-followup-left .followup-table-controls .dt-top{
    width:100% !important;
    justify-content:flex-start !important;
    align-items:center !important;
    flex-wrap:wrap !important;
    gap:10px !important;
  }
  .crm-followup-left .followup-table-controls .dataTables_filter input{
    width:220px !important;
    min-width:220px !important;
  }

  .crm-followup-left .followup-filter-row{
    flex-direction:column !important;
    align-items:stretch !important;
  }
  .crm-followup-left .filter-field{
    width:100% !important;
    min-width:0 !important;
  }
  .crm-followup-left .filter-actions{
    width:100% !important;
    display:flex !important;
    flex-wrap:wrap !important;
    justify-content:flex-start !important;
    gap:8px !important;
  }
  .crm-followup-left .icon-filter-btn{
    width:calc(50% - 4px) !important;
    min-width:140px !important;
    max-width:100% !important;
    flex:0 0 calc(50% - 4px) !important;
    height:44px !important;
    min-height:44px !important;
    border-radius:10px !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    padding:0 10px !important;
  }
  .crm-followup-left .icon-filter-btn .btn-mobile-label{
    display:inline-block !important;
    font-size:11px !important;
    font-weight:700 !important;
    line-height:1 !important;
  }
}

@media (min-width: 768px) and (max-width: 1024px){
  .crm-followup-left .followup-records-head{
    flex-direction:row !important;
    align-items:center !important;
    justify-content:space-between !important;
    flex-wrap:nowrap !important;
  }
  .crm-followup-left .followup-records-left{
    width:auto !important;
  }
  .crm-followup-left .followup-table-controls{
    width:auto !important;
    margin-left:auto !important;
  }
  .crm-followup-left .followup-table-controls .dt-top{
    flex-wrap:nowrap !important;
    align-items:center !important;
    justify-content:flex-end !important;
    gap:10px !important;
  }
  .crm-followup-left .followup-table-controls .dataTables_length,
  .crm-followup-left .followup-table-controls .dataTables_filter{
    width:auto !important;
  }
  .crm-followup-left .followup-table-controls .dataTables_filter input{
    width:200px !important;
    min-width:200px !important;
  }

  .crm-followup-left .followup-filter-row{
    flex-wrap:wrap !important;
  }
  .crm-followup-left .filter-actions{
    width:auto !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:flex-end !important;
    gap:8px !important;
  }
  .crm-followup-left .icon-filter-btn{
    width:140px !important;
    min-width:140px !important;
    max-width:140px !important;
    height:44px !important;
    min-height:44px !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    padding:0 10px !important;
  }
  .crm-followup-left .icon-filter-btn .btn-mobile-label{
    display:inline-block !important;
    font-size:11px !important;
    font-weight:700 !important;
    line-height:1 !important;
  }
}

@media (hover: none), (pointer: coarse), (max-width: 1024px){
  .crm-followup-left .icon-btn{
    width:auto !important;
    min-width:68px !important;
    height:auto !important;
    min-height:40px !important;
    padding:6px 8px !important;
    border-radius:10px !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
  }
  .crm-followup-left .icon-btn .btn-mobile-label{
    display:inline-block !important;
    font-size:10px !important;
    font-weight:700 !important;
    line-height:1.1 !important;
    white-space:nowrap !important;
  }
  .crm-followup-left .icon-btn .btn-inner{
    gap:4px !important;
  }
}

/* Followups final alignment lock (tablet/mobile) */
@media (max-width: 1024px){
  .crm-followup-left .followup-filter-row{
    display:grid !important;
    grid-template-columns:repeat(3,minmax(0,1fr)) auto !important;
    align-items:end !important;
    gap:10px !important;
  }
  .crm-followup-left .followup-filter-row .filter-field{
    min-width:0 !important;
    width:100% !important;
  }
  .crm-followup-left .followup-filter-row .filter-actions{
    width:auto !important;
    align-self:end !important;
    justify-content:flex-end !important;
    flex-wrap:nowrap !important;
  }

  .crm-followup-left .followup-table-controls .dataTables_length{
    display:inline-flex !important;
    align-items:center !important;
    width:auto !important;
    white-space:nowrap !important;
    flex:0 0 auto !important;
  }
  .crm-followup-left .followup-table-controls .dataTables_length label{
    display:inline-flex !important;
    flex-direction:row !important;
    align-items:center !important;
    justify-content:flex-start !important;
    gap:8px !important;
    margin:0 !important;
    white-space:nowrap !important;
    line-height:1 !important;
  }
  .crm-followup-left .followup-table-controls .dataTables_length select{
    width:auto !important;
    min-width:82px !important;
    margin:0 !important;
    flex:0 0 auto !important;
  }
  .crm-followup-left .followup-table-controls .dataTables_filter{
    width:auto !important;
    margin:0 !important;
    flex:0 0 auto !important;
  }

  .crm-followup-left #usersTable td .icon-btn{
    width:auto !important;
    min-width:72px !important;
    height:auto !important;
    min-height:38px !important;
    padding:6px 9px !important;
    margin:0 4px 4px 0 !important;
  }
  .crm-followup-left #usersTable td .icon-btn .btn-inner{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:4px !important;
  }
  .crm-followup-left #usersTable td .icon-btn .btn-mobile-label{
    display:inline-block !important;
    font-size:10px !important;
    font-weight:700 !important;
    line-height:1.1 !important;
    white-space:nowrap !important;
  }
}

@media (max-width: 767px){
  .crm-followup-left .followup-filter-row{
    grid-template-columns:1fr !important;
  }
  .crm-followup-left .followup-filter-row .filter-actions{
    width:100% !important;
    justify-content:stretch !important;
  }
}
</style>

<div class="fu-page-head">
  <h2 class="fu-page-title"><i class="fas fa-calendar-check"></i> Enquiry Follow-ups</h2>
  <div class="fu-quick-nav">
    <a class="fu-nav-btn" href="#followupsSection"><i class="fas fa-list"></i> Follow-up List</a>
    <a class="fu-nav-btn" href="#addFollowupSection"><i class="fas fa-plus"></i> Add Follow-up</a>
  </div>
</div>


<div class="followup-alerts">

<div class="alert-card today">
<i class="fas fa-bell"></i>
<div>
<b><?= $todayCount ?></b>
<span>Today Followups</span>
</div>
</div>

<div class="alert-card missed">
<i class="fas fa-exclamation-triangle"></i>
<div>
<b><?= $missedCount ?></b>
<span>Missed Followups</span>
</div>
</div>

<div class="alert-card upcoming">
<i class="fas fa-calendar"></i>
<div>
<b><?= $upcomingCount ?></b>
<span>Upcoming Followups</span>
</div>
</div>

</div>


<div class="crm-followup-layout">

<!-- ================= LEFT SIDE : FOLLOWUPS ================= -->

<div class="crm-followup-left" id="followupsSection">

<div class="card section-card">

<div class="card-header">
  <div>
    <div class="fu-sec-title"><i class="fas fa-list"></i> Follow-ups</div>
    <div class="fu-sec-sub">Track today, pending, missed and completed follow-ups in one place.</div>
  </div>
</div>

<div class="followup-tabs-wrap">

<div class="top-tabs" style="margin:0;">
<a class="followupTab <?= $tab==='today'?'active':''; ?>" data-tab="today">Today</a>
<a class="followupTab <?= $tab==='pending'?'active':''; ?>" data-tab="pending">Pending</a>
<a class="followupTab <?= $tab==='missed'?'active':''; ?>" data-tab="missed">Missed</a>
<a class="followupTab <?= $tab==='done'?'active':''; ?>" data-tab="done">Done</a>
<a class="followupTab <?= $tab==='all'?'active':''; ?>" data-tab="all">All</a>
</div>

</div>

<form method="GET" action="index.php" class="followup-filters-wrap">

<input type="hidden" name="page" value="enquiries/followups">
<input type="hidden" name="tab" value="<?= h($tab) ?>">

<div class="followup-filter-row">

<div class="filter-field">
<label class="lbl">Search</label>
<input type="text" name="q" value="<?= h($q) ?>" placeholder="Name / Phone / Email / Enquiry No">
</div>

<div class="filter-field">
<label class="lbl">Date From</label>
<input type="date" name="from" value="<?= h($from) ?>">
</div>

<div class="filter-field">
<label class="lbl">Date To</label>
<input type="date" name="to" value="<?= h($to) ?>">
</div>

<div class="filter-actions">

<button type="submit"
class="icon-filter-btn apply-btn"
title="Apply Filter">
<span class="btn-inner">
<i class="fas fa-search"></i>
<span class="btn-mobile-label">Apply</span>
</span>

</button>

<a href="index.php?page=enquiries/followups&tab=<?= h($tab) ?>"
class="icon-filter-btn reset-btn"
title="Reset Filter">
<span class="btn-inner">
<i class="fas fa-rotate-left"></i>
<span class="btn-mobile-label">Reset</span>
</span>

</a>

</div>

</div>

</form>

<div style="padding:14px;">

<div class="crm-card">

<div class="crm-table-wrapper">

<div class="followup-records-head">
  <div class="followup-records-left">
    <h3 class="fu-table-title">Follow-up Records</h3>
  </div>
  <div id="followupTableControls" class="followup-table-controls"></div>
</div>
<table  id="usersTable" class="crm-table">
<thead>

<tr>
<th >Follow-up</th>
<th>Enquiry</th>
<th>Contact</th>
<th >Type</th>
<th >Status</th>
<th >Next</th>
<th >Actions</th>
</tr>

</thead>

<tbody>



<?php foreach ($followups as $f): ?>

<?php
$status = $f['status'] ?? 'pending';

$sBadge = ($status==='done')
? badge('Done','green')
: (($status==='missed') ? badge('Missed','red') : badge('Pending','orange'));

$enqNo = $f['enquiry_no'] ?: ('ENQ-'.$f['enquiry_id']);
?>

<tr>

<td >
<div class="strong"><?= h($f['followup_date']) ?> <?= h($f['followup_time'] ?? '') ?></div>
<div class="sub">#<?= (int)$f['id'] ?></div>
</td>

<td>
<div class="strong"><?= h($enqNo) ?></div>
<div class="sub"><?= h($f['enquiry_name'] ?? '-') ?></div>
</td>

<td><?= h($f['enquiry_phone'] ?? '-') ?></td>

<td ><?= h($f['followup_type'] ?? '-') ?></td>

<td class="tc"><?= $sBadge ?></td>

<td>

<?php if (!empty($f['next_followup_date'])): ?>

<div class="strong">
<?= h($f['next_followup_date']) ?> <?= h($f['next_followup_time'] ?? '') ?>
</div>

<?php else: ?>

<div class="sub">-</div>

<?php endif; ?>

</td>

<td>

<button type="button"
class="icon-btn btn-view"
onclick="openHistoryModal(<?= (int)$f['enquiry_id'] ?>)">

<span class="btn-inner">
<i class="fas fa-eye"></i>
<span class="btn-mobile-label">View</span>
</span>

</button>

<button type="button"
class="icon-btn btn-edit"
onclick="openEditModal(<?= (int)$f['id'] ?>)">

<span class="btn-inner">
<i class="fas fa-pen"></i>
<span class="btn-mobile-label">Edit</span>
</span>

</button>

<?php if (($f['status'] ?? '') !== 'done'): ?>

<form method="POST" class="doneForm" style="display:inline;">

<input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
<input type="hidden" name="followup_id" value="<?= (int)$f['id'] ?>">

<button type="submit"
name="mark_done"
class="icon-btn btn-done">

<span class="btn-inner">
<i class="fas fa-check"></i>
<span class="btn-mobile-label">Done</span>
</span>

</button>

</form>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>



</tbody>

</table>
<div class="followup-table-loading" id="followupTableLoading" style="display:none;">
  <span class="followup-loader-dot"></span>
  <span>Loading follow-ups...</span>
</div>
</div>
<div id="followupTableFooter" class="followup-table-footer"></div>

</div>

</div>

</div>

</div>

<!-- ================= RIGHT SIDE : ADD FOLLOWUP ================= -->

<div class="crm-followup-right" id="addFollowupSection">

<div class="card section-card">

<div class="card-header">
  <div>
    <div class="fu-sec-title"><i class="fas fa-plus-circle"></i> Add Follow-up</div>
    <div class="fu-sec-sub">Create the next interaction note and schedule reminders quickly.</div>
  </div>
</div>

    <form method="POST" enctype="multipart/form-data" id="addFollowupForm" class="add-fu-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">

        <div class="add-fu-grid">
            <div class="fu-full">
                <label class="lbl">Select Enquiry</label>
                <select name="enquiry_id" data-modern-select="off" required>
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
                <div class="modern-input-wrap">
                    <input type="date" name="followup_date" value="<?= h(date('Y-m-d')) ?>" required>
                </div>
            </div>

            <div>
                <label class="lbl">Follow-up Time</label>
                <div class="modern-input-wrap">
                    <input type="time" name="followup_time">
                </div>
            </div>

            <div id="scheduleBanner" class="schedule-banner fu-full" style="display:none;">
                <div class="sb-left">
                    <div class="sb-ico"><i class="fas fa-bell"></i></div>
                    <div>
                        <div class="sb-title">Scheduled Follow-up</div>
                        <div class="sb-text" id="scheduleBannerText">�</div>
                    </div>
                </div>
                <button type="button" class="sb-close" onclick="hideScheduleBanner()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="fu-full add-fu-triple">
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
                    <div class="modern-input-wrap">
                        <input type="date" name="next_followup_date">
                    </div>
                </div>

                <div>
                    <label class="lbl">Next Follow-up Time</label>
                    <div class="modern-input-wrap">
                        <input type="time" name="next_followup_time">
                    </div>
                </div>
            </div>

            <div class="fu-full">
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

            <div class="fu-full">
                <label class="lbl">Notes</label>
                <textarea name="notes" rows="4" placeholder="Add follow-up notes..."></textarea>
            </div>
        </div>

        <div class="add-fu-actions">
            <button class="btn btn-primary add-fu-save" id="addFollowupSubmit" type="submit" name="add_followup" disabled>Save Follow-up</button>
            <a class="add-fu-list" href="index.php?page=enquiries/followups&ui=list&tab=today">Go to List</a>
        </div>
    </form>

</div>

</div>

</div>






<script>
// SweetAlert safety shim: if Swal is missing, use browser dialogs without breaking page logic.
(function () {
    if (window.Swal && typeof window.Swal.fire === 'function') return;

    window.Swal = window.Swal || {};
    window.Swal.showValidationMessage = function (msg) { window.alert(msg || 'Validation failed'); };
    window.Swal.showLoading = function () {};

    window.Swal.fire = function (opts) {
        opts = opts || {};

        return new Promise(function (resolve) {
            const title = opts.title || 'Notice';
            const text = opts.text || '';

            // Fallback for the "Select Student Type" dialog used in mark-done flow.
            if (typeof opts.html === 'string' && opts.html.indexOf('regTypeSelect') !== -1) {
                let selected = '';
                while (!selected) {
                    const val = (window.prompt(title + '\nEnter type: course / internship', '') || '').trim().toLowerCase();
                    if (!val) {
                        resolve({ isConfirmed: false, isDenied: false, isDismissed: true, value: null });
                        return;
                    }
                    if (val === 'course' || val === 'internship') {
                        selected = val;
                    } else {
                        window.alert('Please type "course" or "internship".');
                    }
                }
                resolve({ isConfirmed: true, isDenied: false, isDismissed: false, value: selected });
                return;
            }

            if (opts.showDenyButton) {
                const choice = (window.prompt(
                    title + (text ? '\n' + text : '') + '\nType: 1 = Convert, 2 = Save Draft, 0 = Cancel',
                    '1'
                ) || '').trim();
                if (choice === '1') {
                    resolve({ isConfirmed: true, isDenied: false, isDismissed: false });
                } else if (choice === '2') {
                    resolve({ isConfirmed: false, isDenied: true, isDismissed: false });
                } else {
                    resolve({ isConfirmed: false, isDenied: false, isDismissed: true });
                }
                return;
            }

            if (opts.showCancelButton) {
                const ok = window.confirm(title + (text ? '\n' + text : ''));
                resolve({ isConfirmed: ok, isDenied: false, isDismissed: !ok });
                return;
            }

            window.alert(title + (text ? '\n' + text : ''));
            resolve({ isConfirmed: true, isDenied: false, isDismissed: false });
        });
    };
})();
</script>

<?php if ($success): ?>
<script>
    
Swal.fire({
    icon:'success',
    title:'Success',
    text:'<?= addslashes($success) ?>',
    confirmButtonColor:'#e91e63'
}).then(() => {

    <?php if ($uiTab === 'add'): ?>

    // After Follow-up Add ? go to list
    document.querySelector('.followupTab.active').click();

    <?php else: ?>

    document.querySelector('.followupTab.active').click();

    <?php endif; ?>

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
    console.log("DONE SCRIPT LOADED");
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

document.addEventListener('submit', function(e){
const f = e.target;
if(!f || !f.classList || !f.classList.contains('doneForm')) return;
if(f.dataset.doneFlowBound === '1' || f.dataset.confirmed === '1') return;

e.preventDefault();

Swal.fire({
title:'Select Student Type',
html:`
<select id="regTypeSelect" class="swal-modern-select">
<option value="">Select type</option>
<option value="course">Course</option>
<option value="internship">Internship</option>
</select>
`,
showCancelButton:true,
confirmButtonText:'Next',
cancelButtonText:'Cancel',
confirmButtonColor:'#e91e63',
preConfirm:()=>{
const t=document.getElementById('regTypeSelect').value;
if(!t){
Swal.showValidationMessage('Please select type');
return false;
}
return t;
}
}).then((r)=>{
if(!r.isConfirmed) return;

const type=r.value;

Swal.fire({
title:'Complete Follow-up',
text:'Choose where this student should go next.',
showDenyButton:true,
showCancelButton:true,
confirmButtonText:'Convert Now',
denyButtonText:'Save Draft',
cancelButtonText:'Cancel',
confirmButtonColor:'#e91e63'
}).then((x)=>{
if(x.isConfirmed){
setHiddenField(f,'convert','1');
setHiddenField(f,'reg_type',type);
setHiddenField(f,'reg_mode','active');
ensureMarkDoneField(f);
f.dataset.confirmed = '1';
f.submit();
}

if(x.isDenied){
setHiddenField(f,'convert','1');
setHiddenField(f,'reg_type',type);
setHiddenField(f,'reg_mode','draft');
ensureMarkDoneField(f);
f.dataset.confirmed = '1';
f.submit();
}
});
});
});

(function () {

const forms = document.querySelectorAll('.doneForm');
if (!forms.length) return;

forms.forEach((f) => {
f.dataset.doneFlowBound = '1';

f.addEventListener('submit', function(e){

e.preventDefault();

// STEP 1 ? SELECT TYPE
Swal.fire({
title:'Select Student Type',
html:`
<select id="regTypeSelect" class="swal-modern-select">
<option value="">Select type</option>
<option value="course">Course</option>
<option value="internship">Internship</option>
</select>
`,
showCancelButton:true,
confirmButtonText:'Next',
cancelButtonText:'Cancel',
confirmButtonColor:'#e91e63',

preConfirm:()=>{
const t=document.getElementById('regTypeSelect').value;
if(!t){
Swal.showValidationMessage('Please select type');
return false;
}
return t;
}

}).then((r)=>{

if(!r.isConfirmed) return;

const type=r.value;

// STEP 2 ? CONVERT OR DRAFT
Swal.fire({
title:'Complete Follow-up',
text:'Choose where this student should go next.',
showDenyButton:true,
showCancelButton:true,
confirmButtonText:'Convert Now',
denyButtonText:'Save Draft',
cancelButtonText:'Cancel',
confirmButtonColor:'#e91e63'

}).then((x)=>{

if(x.isConfirmed){

setHiddenField(f,'convert','1');
setHiddenField(f,'reg_type',type);
setHiddenField(f,'reg_mode','active');
ensureMarkDoneField(f);
f.dataset.confirmed = '1';
f.submit();

}

if(x.isDenied){

setHiddenField(f,'convert','1');
setHiddenField(f,'reg_type',type);
setHiddenField(f,'reg_mode','draft');
ensureMarkDoneField(f);
f.dataset.confirmed = '1';
f.submit();

}

if(x.isDismissed){
return;
}

});

});

});

});

})();

(function () {
    const addForm = document.getElementById('addFollowupForm');
    if (!addForm) return;
    addForm.noValidate = true;

    const enquirySel = addForm.querySelector('select[name="enquiry_id"]');
    const fDate = addForm.querySelector('input[name="followup_date"]');
    const fTime = addForm.querySelector('input[name="followup_time"]');
    const fType = addForm.querySelector('select[name="followup_type"]');
    const nextDate = addForm.querySelector('input[name="next_followup_date"]');
    const nextTime = addForm.querySelector('input[name="next_followup_time"]');
    const notes = addForm.querySelector('textarea[name="notes"]');
    const submitBtn = addForm.querySelector('#addFollowupSubmit');

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
        showBanner(`? This follow-up date is in the past (${toPretty(chosen)}). Please confirm.`);
    }

    function fileIcon(name) {
        const ext = (name.split('.').pop() || '').toLowerCase();
        if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) return 'fa-image';
        if (['mp3', 'wav', 'm4a', 'aac', 'ogg'].includes(ext)) return 'fa-headphones';
        if (['mp4', 'mov', 'avi', 'mkv', 'webm'].includes(ext)) return 'fa-video';
        if (['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'].includes(ext)) return 'fa-file-alt';
        return 'fa-paperclip';
    }

    function initModernEnquirySelect(nativeSelect) {
        if (!nativeSelect || nativeSelect.dataset.enhanced === '1') return;
        nativeSelect.dataset.enhanced = '1';

        const holder = document.createElement('div');
        holder.className = 'fu-smart-select';
        holder.innerHTML = `
            <button type="button" class="fu-smart-toggle" aria-haspopup="listbox" aria-expanded="false">
                <span class="fu-smart-label">-- Select --</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="fu-smart-panel">
                <input type="text" class="fu-smart-search" placeholder="Search enquiry...">
                <div class="fu-smart-list" role="listbox"></div>
            </div>
        `;

        nativeSelect.classList.add('js-enhanced');
        nativeSelect.insertAdjacentElement('afterend', holder);

        const toggle = holder.querySelector('.fu-smart-toggle');
        const label = holder.querySelector('.fu-smart-label');
        const panel = holder.querySelector('.fu-smart-panel');
        const search = holder.querySelector('.fu-smart-search');
        const list = holder.querySelector('.fu-smart-list');

        const getAllOptions = () => Array.from(nativeSelect.options).filter(opt => String(opt.value || '').trim() !== '');

        function syncLabel() {
            const selectedText = nativeSelect.options[nativeSelect.selectedIndex]?.text || '-- Select --';
            label.textContent = selectedText;
        }

        function renderOptions(query) {
            const q = (query || '').trim().toLowerCase();
            const options = getAllOptions().filter(opt => !q || opt.text.toLowerCase().includes(q));
            list.innerHTML = '';

            if (!options.length) {
                list.innerHTML = '<div class="fu-smart-empty">No enquiry found</div>';
                return;
            }

            options.forEach(function (opt) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'fu-smart-item' + (String(nativeSelect.value) === String(opt.value) ? ' active' : '');
                item.textContent = opt.text;
                item.addEventListener('click', function () {
                    nativeSelect.value = opt.value;
                    nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    closePanel();
                });
                list.appendChild(item);
            });
        }

        function openPanel() {
            holder.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
            renderOptions(search.value);
            setTimeout(() => search.focus(), 0);
        }

        function closePanel() {
            holder.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function () {
            if (holder.classList.contains('open')) closePanel();
            else openPanel();
        });

        search.addEventListener('input', function () {
            renderOptions(this.value);
        });

        nativeSelect.addEventListener('change', function () {
            syncLabel();
            renderOptions(search.value);
        });

        document.addEventListener('click', function (e) {
            if (!holder.contains(e.target)) closePanel();
        });

        syncLabel();
        renderOptions('');
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
    initModernEnquirySelect(enquirySel);

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

    function updateAddFollowupSubmitState() {
        if (!submitBtn) return;
        const enq = (enquirySel?.value || '').trim();
        const fd = (fDate?.value || '').trim();
        const nd = (nextDate?.value || '').trim();
        const ntt = (nextTime?.value || '').trim();
        const nt = (notes?.value || '').trim();

        let canSubmit = !!enq && !!fd && nt.length >= 3;
        if (canSubmit && nd && nd < fd) canSubmit = false;
        if (canSubmit && !nd && ntt) canSubmit = false;

        submitBtn.disabled = !canSubmit;
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

    [enquirySel, fDate, nextDate, nextTime, notes, fType].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', updateAddFollowupSubmitState);
        el.addEventListener('change', updateAddFollowupSubmitState);
    });

    updateBannerByDate();
    updateAddFollowupSubmitState();
})();
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const tableLoader = document.getElementById('followupTableLoading');
    const controlsTarget = document.getElementById('followupTableControls');
    const footerTarget = document.getElementById('followupTableFooter');
    const showTableLoader = () => { if (tableLoader) tableLoader.style.display = 'flex'; };
    const hideTableLoader = () => { if (tableLoader) tableLoader.style.display = 'none'; };

    function relocateFollowupTableControls() {
        var wrapper = document.getElementById('usersTable_wrapper');
        if (!wrapper || !controlsTarget || !footerTarget) return false;

        var top = wrapper.querySelector('.dt-top') || controlsTarget.querySelector('.dt-top');
        var bottom = wrapper.querySelector('.dt-bottom') || footerTarget.querySelector('.dt-bottom');

        if (!top) {
            var length = wrapper.querySelector('.dataTables_length');
            var filter = wrapper.querySelector('.dataTables_filter');
            var buttons = wrapper.querySelector('.dt-buttons');
            if (length || filter || buttons) {
                top = document.createElement('div');
                top.className = 'dt-top';
                if (length) top.appendChild(length);
                if (filter) top.appendChild(filter);
                if (buttons) top.appendChild(buttons);
            }
        }

        if (!bottom) {
            var info = wrapper.querySelector('.dataTables_info');
            var paginate = wrapper.querySelector('.dataTables_paginate');
            if (info || paginate) {
                bottom = document.createElement('div');
                bottom.className = 'dt-bottom';
                if (info) bottom.appendChild(info);
                if (paginate) bottom.appendChild(paginate);
            }
        }

        controlsTarget.innerHTML = '';
        footerTarget.innerHTML = '';

        if (top) {
            controlsTarget.appendChild(top);
        }
        if (bottom) {
            footerTarget.appendChild(bottom);
        }

        // Remove any leftover native nodes in wrapper to prevent duplicate UI.
        wrapper.querySelectorAll('.dataTables_length, .dataTables_filter, .dt-buttons, .dataTables_info, .dataTables_paginate').forEach(function(node){
            if (!node.closest('.dt-top') && !node.closest('.dt-bottom')) {
                node.remove();
            }
        });

        return !!(top && bottom);
    }

    initFollowupTable();
    setTimeout(relocateFollowupTableControls, 60);
    setTimeout(relocateFollowupTableControls, 220);

    if (window.jQuery) {
        window.jQuery('#usersTable').on('draw.dt', relocateFollowupTableControls);
    }

    document.querySelectorAll('.followupTab').forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();

            document.querySelectorAll('.followupTab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const tabName = this.dataset.tab;
            showTableLoader();

            fetch(`index.php?page=enquiries/followups&ajax=1&tab=${tabName}`)
                .then(res => {
                    if (!res.ok) throw new Error('Network response was not ok');
                    return res.text();
                })
                .then(html => {
                    if (window.jQuery && jQuery.fn && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable('#usersTable')) {
                        jQuery('#usersTable').DataTable().clear().destroy();
                    }

                    document.querySelector("#usersTable tbody").innerHTML = html;

                    initFollowupTable();
                    setTimeout(relocateFollowupTableControls, 80);
                    setTimeout(relocateFollowupTableControls, 260);
                })
                .catch(error => {
                    console.error('Error loading tab data:', error);
                    document.querySelector("#usersTable tbody").innerHTML =
                        '<tr><td colspan="7" style="text-align:center; padding:20px; color:#d32f2f;">Failed to load data. Please try again.</td></tr>';
                })
                .finally(() => {
                    hideTableLoader();
                });
        });
    });
});

function initFollowupTable() {
    const controlsTarget = document.getElementById('followupTableControls');
    const footerTarget = document.getElementById('followupTableFooter');

    // Idempotent init: destroy existing instance before creating a new one.
    if (window.jQuery && jQuery.fn && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable('#usersTable')) {
        jQuery('#usersTable').DataTable().clear().destroy();
    }

    var oldWrapper = document.getElementById('usersTable_wrapper');
    if (oldWrapper) {
        oldWrapper.querySelectorAll('.dataTables_length, .dataTables_filter, .dt-buttons, .dataTables_info, .dataTables_paginate').forEach(function(node){
            node.remove();
        });
    }

    if (controlsTarget) controlsTarget.innerHTML = '';
    if (footerTarget) footerTarget.innerHTML = '';

    if (typeof crmDataTable === 'function') {
        crmDataTable('#usersTable', {
            pageLength: 5,
            lengthMenu: [5, 10, 20, 50],
            ordering: true,
            order: [[0, 'desc']],
            scrollX: false,
            searchPlaceholder: "Search follow-ups..."
        });
        return;
    }

    if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
        jQuery('#usersTable').DataTable({
            pageLength: 5,
            lengthMenu: [5, 10, 20, 50],
            ordering: true,
            order: [[0, 'desc']],
            scrollX: false
        });
    }
}
</script>

