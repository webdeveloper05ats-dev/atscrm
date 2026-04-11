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
    title='View History'
    data-tooltip='View History'
    aria-label='View History'
    onclick='openHistoryModal(".$r['enquiry_id'].")'>
    <span class='btn-inner'>
    <i class='fas fa-eye'></i>
    <span class='btn-mobile-label'>View</span>
    </span>
    </button>";

    // Edit button
    echo "<button type='button'
    class='icon-btn btn-edit'
    title='Edit Follow-up'
    data-tooltip='Edit Follow-up'
    aria-label='Edit Follow-up'
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
        class='icon-btn btn-done'
        title='Mark as Done'
        data-tooltip='Mark as Done'
        aria-label='Mark as Done'>
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
class="icon-filter-btn btn-icon-only apply"
title="Apply Filter"
data-tooltip="Apply Filter"
aria-label="Apply Filter">
<span class="btn-inner">
<i class="fas fa-search"></i>
<span class="btn-mobile-label">Apply</span>
</span>

</button>

<a href="index.php?page=enquiries/followups&tab=<?= h($tab) ?>"
class="icon-filter-btn btn-icon-only reset"
title="Reset Filter"
data-tooltip="Reset Filter"
aria-label="Reset Filter">
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
title="View History"
data-tooltip="View History"
aria-label="View History"
onclick="openHistoryModal(<?= (int)$f['enquiry_id'] ?>)">

<span class="btn-inner">
<i class="fas fa-eye"></i>
<span class="btn-mobile-label">View</span>
</span>

</button>

<button type="button"
class="icon-btn btn-edit"
title="Edit Follow-up"
data-tooltip="Edit Follow-up"
aria-label="Edit Follow-up"
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
class="icon-btn btn-done"
title="Mark as Done"
data-tooltip="Mark as Done"
aria-label="Mark as Done">

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
                    if (typeof window.initializeFloatingTooltips === 'function') {
                        window.initializeFloatingTooltips(document.querySelector("#usersTable tbody"));
                    }

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


