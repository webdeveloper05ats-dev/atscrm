<?php
if (!defined('APP_NAME')) {
    die('Unauthorized access.');
}

if (!function_exists('auditFmtWhen')) {
    function auditFmtWhen($value): string
    {
        $raw = trim((string)$value);
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        return date('d/m/Y h:ia', $ts);
    }
}

if (!function_exists('auditFmtIp')) {
    function auditFmtIp($value): string
    {
        $ip = trim((string)$value);
        if ($ip === '') {
            return '-';
        }
        if ($ip === '::1' || $ip === '127.0.0.1' || $ip === '::ffff:127.0.0.1') {
            return 'Localhost (' . $ip . ')';
        }
        return $ip;
    }
}

if (!function_exists('auditHumanizeModule')) {
    function auditHumanizeModule($module): string
    {
        $text = trim((string)$module);
        if ($text === '') {
            return 'record';
        }
        $text = str_replace(['_', '-', '/'], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return ucwords(strtolower(trim($text)));
    }
}

if (!function_exists('auditFmtAction')) {
    function auditExtractTargetPerson(string $text): string
    {
        $patterns = [
            '/\bset\s+to\s+(.+?)\s*-\s*rs\b/i',
            '/\bfor\s+(.+?)\s+to\s+rs\b/i',
            '/\bfor\s+(.+?)\s*-\s*rs\b/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $name = trim((string)($m[1] ?? ''));
                $name = preg_replace('/\s+/', ' ', $name) ?? $name;
                if ($name !== '' && strlen($name) <= 100) {
                    return $name;
                }
            }
        }
        return '';
    }

    function auditExtractPaymentAmount(string $text): string
    {
        $patterns = [
            '/(?:rs\.?|inr)\s*[:\-]?\s*([0-9][0-9,]*(?:\.\d{1,2})?)/i',
            '/(?:amount|amt)\s*[:=]?\s*([0-9][0-9,]*(?:\.\d{1,2})?)/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $raw = trim((string)($m[1] ?? ''));
                $plain = str_replace(',', '', $raw);
                if ($plain !== '' && is_numeric($plain)) {
                    $decimals = (strpos($plain, '.') !== false) ? 2 : 0;
                    return number_format((float)$plain, $decimals, '.', ',');
                }
                return $raw;
            }
        }
        return '';
    }

    function auditFmtActionCleanup(string $text): string
    {
        // Remove explicit id markers to keep UI name-first.
        $text = preg_replace('/\(\s*Record\s*#\d+\s*\)/i', '', $text) ?? $text;
        $text = preg_replace('/\s*#\d+\b/', '', $text) ?? $text;
        $text = preg_replace('/\b(id|record id|reg id)\s*[:=]?\s*\d+\b/i', '', $text) ?? $text;
        $text = preg_replace('/\s+:\s+/', ': ', $text) ?? $text;
        $text = preg_replace('/\s+-\s+/', ' - ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    function auditRecordNameFromAction(string $action, string $tableName = '', string $userName = ''): string
    {
        $raw = trim($action);
        $table = strtolower(trim($tableName));
        if ($raw === '') {
            return '';
        }

        if ($table === 'monthly_targets') {
            $name = auditExtractTargetPerson($raw);
            if ($name !== '') {
                return $name;
            }
        }

        if ($table === 'registration_payments' && preg_match('/\bfor\s+(.+?)(?:\s+via|\s+\[|\s+on|$)/i', $raw, $m)) {
            $name = trim((string)($m[1] ?? ''));
            $name = preg_replace('/\s+/', ' ', $name) ?? $name;
            if ($name !== '' && !preg_match('/\bregistration\b/i', $name)) {
                return $name;
            }
        }

        if (($table === 'leads' || $table === 'enquiries') && preg_match('/:\s*([^,(]+)/', $raw, $m)) {
            $name = trim((string)($m[1] ?? ''));
            if ($name !== '' && !in_array(strtolower($name), ['unknown', 'lead', 'enquiry'], true)) {
                return $name;
            }
        }
        if ($table === 'leads' && preg_match('/\bassigned\s+to\s+(.+?)$/i', $raw, $m)) {
            $name = trim((string)($m[1] ?? ''));
            $name = preg_replace('/\s+/', ' ', $name) ?? $name;
            if ($name !== '' && !in_array(strtolower($name), ['selected staff', 'staff'], true)) {
                return $name;
            }
        }

        if ($table === 'registrations' && preg_match('/\bfor\s+(.+?)(?:\s+-|\s+\(|\s+\[|$)/i', $raw, $m)) {
            $name = trim((string)($m[1] ?? ''));
            if ($name !== '' && !in_array(strtolower($name), ['student', 'registration'], true)) {
                return $name;
            }
        }

        if ($table === 'users') {
            $name = trim($userName);
            if ($name !== '' && $name !== '-') {
                return $name;
            }
        }

        return '';
    }

    function auditFmtAction($action, $tableName = '', $recordId = 0, $entityName = ''): string
    {
        $raw = trim((string)$action);
        if ($raw === '') {
            return '-';
        }

        $upper = strtoupper($raw);
        $tableKey = strtolower(trim((string)$tableName));
        $module = auditHumanizeModule($tableKey);
        $entity = trim((string)$entityName) !== '' ? trim((string)$entityName) : $module;
        $isPayment = ($upper === 'PAYMENT' || strpos($upper, 'PAYMENT') !== false || strtolower((string)$tableName) === 'registration_payments');
        if ($isPayment) {
            $amount = auditExtractPaymentAmount($raw);
            if (trim((string)$entityName) !== '') {
                $paymentText = $amount !== ''
                    ? ('Payment of Rs ' . $amount . ' added for ' . trim((string)$entityName))
                    : ('Payment added for ' . trim((string)$entityName));
                return auditFmtActionCleanup($paymentText);
            }
            return auditFmtActionCleanup($amount !== '' ? ('Payment of Rs ' . $amount . ' added') : 'Payment added');
        }

        if ($tableKey === 'monthly_targets' || strpos($upper, 'TARGET') !== false) {
            $amount = auditExtractPaymentAmount($raw);
            $amountText = $amount !== '' ? (' - Rs ' . $amount) : '';
            $periodText = '';
            if (preg_match('/\(([A-Za-z]+\s+\d{4})\)/', $raw, $m)) {
                $periodText = ' (' . trim((string)$m[1]) . ')';
            }
            $targetPerson = trim((string)$entityName);
            if ($targetPerson === '') {
                $targetPerson = auditExtractTargetPerson($raw);
            }
            if ($targetPerson === '') {
                $targetPerson = 'user';
            }
            if (preg_match('/\b(DELETE|REMOVED|REMOVE)\b/i', $raw)) {
                return auditFmtActionCleanup('Target removed for ' . $targetPerson . $amountText . $periodText);
            }
            if (preg_match('/\b(CREATE|NEW|SET)\b/i', $raw)) {
                return auditFmtActionCleanup('New target set for ' . $targetPerson . $amountText . $periodText);
            }
            return auditFmtActionCleanup('Target updated for ' . $targetPerson . $amountText . $periodText);
        }

        if ($tableKey === 'enquiry_followups' || strpos($upper, 'FOLLOW-UP') !== false || strpos($upper, 'FOLLOWUP') !== false) {
            if (preg_match('/\b(CONVERT|CONVERTED)\b/i', $raw)) {
                return auditFmtActionCleanup('Follow-up converted to registration for ' . $entity);
            }
            if (preg_match('/\b(MARKED DONE|DONE)\b/i', $raw)) {
                return auditFmtActionCleanup('Follow-up marked done for ' . $entity);
            }
            if (preg_match('/\b(VERIFY|VERIFICATION)\b/i', $raw)) {
                return auditFmtActionCleanup('Follow-up verification updated for ' . $entity);
            }
            if (preg_match('/\b(CREATE|NEW|ADD|ADDED)\b/i', $raw)) {
                return auditFmtActionCleanup('New follow-up added for ' . $entity);
            }
            if (preg_match('/\b(DELETE|REMOVED|REMOVE)\b/i', $raw)) {
                return auditFmtActionCleanup('Follow-up deleted for ' . $entity);
            }
            return auditFmtActionCleanup('Follow-up updated for ' . $entity);
        }

        if ($tableKey === 'registrations' || strpos($upper, 'REGISTRATION') !== false) {
            if (preg_match('/\b(DELETE|REMOVED|REMOVE)\b/i', $raw)) {
                return auditFmtActionCleanup('Registration deleted for ' . $entity);
            }
            if (preg_match('/\b(CREATE|NEW)\b/i', $raw)) {
                return auditFmtActionCleanup('New registration created for ' . $entity);
            }
            if (preg_match('/\b(CONVERT|CONVERTED)\b/i', $raw)) {
                return auditFmtActionCleanup('Registration converted for ' . $entity);
            }
            return auditFmtActionCleanup('Registration updated for ' . $entity);
        }

        if ($tableKey === 'enquiries' || strpos($upper, 'ENQUIRY') !== false) {
            if (preg_match('/\b(DELETE|REMOVED|REMOVE)\b/i', $raw)) {
                return auditFmtActionCleanup('Enquiry deleted for ' . $entity);
            }
            if (preg_match('/\b(CREATE|NEW)\b/i', $raw)) {
                return auditFmtActionCleanup('New enquiry added for ' . $entity);
            }
            return auditFmtActionCleanup('Enquiry updated for ' . $entity);
        }

        if ($tableKey === 'leads' || strpos($upper, 'LEAD') !== false) {
            if (preg_match('/\bleads?\s+assigned\s+to\s+(.+)$/i', $raw, $m)) {
                $to = trim((string)($m[1] ?? ''));
                return auditFmtActionCleanup('Leads assigned to ' . $to);
            }
            if (preg_match('/\blead\s+assigned\s*:\s*(.+)$/i', $raw, $m)) {
                $payload = trim((string)($m[1] ?? ''));
                return auditFmtActionCleanup('Lead assigned: ' . $payload);
            }
            if (preg_match('/\bassignment\s+updated(?:\s+to\s+(.+))?$/i', $raw, $m)) {
                $to = trim((string)($m[1] ?? ''));
                return auditFmtActionCleanup('Lead assignment updated' . ($to !== '' ? (' to ' . $to) : ''));
            }
            if (preg_match('/\b(DELETE|REMOVED|REMOVE)\b/i', $raw)) {
                return auditFmtActionCleanup('Lead deleted for ' . $entity);
            }
            if (preg_match('/\b(CREATE|NEW)\b/i', $raw)) {
                return auditFmtActionCleanup('New lead added for ' . $entity);
            }
            if (
                preg_match('/\s/', $raw)
                && strpos($raw, '[') === false
                && strpos($raw, '_') === false
                && preg_match('/[a-z]/', $raw)
            ) {
                return auditFmtActionCleanup($raw);
            }
            return auditFmtActionCleanup('Lead updated for ' . $entity);
        }

        if ($tableKey === 'users') {
            if ($upper === 'LOGIN_SUCCESS') {
                return 'Logged in successfully';
            }
            if ($upper === 'LOGIN_FAILED') {
                return 'Login failed';
            }
            if ($upper === 'LOGOUT') {
                return 'Logged out';
            }
            if (preg_match('/\b(DELETE|REMOVED|REMOVE)\b/i', $raw)) {
                return auditFmtActionCleanup('User deleted: ' . $entity);
            }
            if (preg_match('/\b(CREATE|NEW)\b/i', $raw)) {
                return auditFmtActionCleanup('New user created: ' . $entity);
            }
            if (preg_match('/\b(UPDATE|EDIT)\b/i', $raw)) {
                return auditFmtActionCleanup('User updated: ' . $entity);
            }
        }

        if ($tableKey === 'roles') {
            if (preg_match('/\b(DELETE|REMOVED|REMOVE)\b/i', $raw)) {
                return auditFmtActionCleanup('Role deleted: ' . $entity);
            }
            if (preg_match('/\b(CREATE|NEW)\b/i', $raw)) {
                return auditFmtActionCleanup('New role created: ' . $entity);
            }
            if (preg_match('/\b(UPDATE|EDIT|ASSIGN|PERMISSION)\b/i', $raw)) {
                return auditFmtActionCleanup('Role updated: ' . $entity);
            }
        }

        $simpleMap = [
            'LOGIN_SUCCESS' => 'Logged in successfully',
            'LOGIN_FAILED' => 'Login failed',
            'LOGOUT' => 'Logged out',
            'CREATE' => 'Created ' . $entity,
            'UPDATE' => 'Updated ' . $entity,
            'DELETE' => 'Deleted ' . $entity,
            'ASSIGN' => 'Assigned ' . $entity,
            'IMPORT' => 'Imported ' . $entity,
            'CONVERT' => 'Converted ' . $entity,
        ];
        if (isset($simpleMap[$upper])) {
            return auditFmtActionCleanup(trim($simpleMap[$upper]));
        }

        // Pattern example: UPDATE [targets/setup] via id
        if (preg_match('/^\s*([A-Z_]+)\s*\[([^\]]+)\](?:\s*via\s*(.+))?\s*$/i', $raw, $m)) {
            $verb = strtoupper(trim((string)$m[1]));
            $path = auditHumanizeModule((string)$m[2]);
            $via = trim((string)($m[3] ?? ''));
            $entity = trim((string)$entityName) !== '' ? trim((string)$entityName) : ((strtolower($module) !== 'record') ? $module : $path);
            $verbText = [
                'CREATE' => 'Created',
                'UPDATE' => 'Updated',
                'DELETE' => 'Deleted',
                'ASSIGN' => 'Assigned',
                'IMPORT' => 'Imported',
                'CONVERT' => 'Converted',
                'PAYMENT' => 'Added payment for',
            ][$verb] ?? ucwords(strtolower(str_replace('_', ' ', $verb)));
            $viaKey = strtolower($via);
            $showVia = ($viaKey !== '' && !in_array($viaKey, ['id', 'pk', 'uid', 'record id', 'record_id'], true));
            $viaText = $showVia ? ' via ' . $viaKey : '';
            return auditFmtActionCleanup(trim($verbText . ' ' . $entity . $viaText));
        }

        // Keep already-human sentence style as-is after cleanup.
        if (
            preg_match('/\s/', $raw)
            && strpos($raw, '[') === false
            && strpos($raw, '_') === false
            && preg_match('/[a-z]/', $raw)
        ) {
            return auditFmtActionCleanup($raw);
        }

        // Generic fallback: make tokenized text readable.
        $text = str_replace(['_', '/', '-', '[', ']'], ' ', $raw);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);
        $text = ucwords(strtolower($text));
        $text = auditFmtActionCleanup($text);
        return $text !== '' ? $text : auditFmtActionCleanup($raw);
    }
}

if (!function_exists('auditBuildEntityNameMap')) {
    function auditBuildEntityNameMap(PDO $pdo, array $rows): array
    {
        $idsByTable = [];
        foreach ($rows as $row) {
            $table = strtolower(trim((string)($row['table_name'] ?? '')));
            $id = (int)($row['record_id'] ?? 0);
            if ($table === '' || $id <= 0) {
                continue;
            }
            if (!isset($idsByTable[$table])) {
                $idsByTable[$table] = [];
            }
            $idsByTable[$table][$id] = true;
        }

        $map = [];
        $fetchSimple = static function (string $table, string $idCol, string $nameExpr) use ($pdo, &$idsByTable, &$map): void {
            if (empty($idsByTable[$table])) {
                return;
            }
            $ids = array_keys($idsByTable[$table]);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT {$idCol} AS rid, {$nameExpr} AS label FROM {$table} WHERE {$idCol} IN ({$ph})";
            $st = $pdo->prepare($sql);
            $st->execute($ids);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $rid = (int)($r['rid'] ?? 0);
                if ($rid <= 0) {
                    continue;
                }
                $label = trim((string)($r['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $map[$table . ':' . $rid] = $label;
            }
        };

        try { $fetchSimple('leads', 'id', "COALESCE(NULLIF(TRIM(name),''), 'Lead')"); } catch (Throwable $e) {}
        try { $fetchSimple('enquiries', 'id', "COALESCE(NULLIF(TRIM(name),''), 'Enquiry')"); } catch (Throwable $e) {}
        try { $fetchSimple('users', 'id', "COALESCE(NULLIF(TRIM(name),''), 'User')"); } catch (Throwable $e) {}
        try { $fetchSimple('roles', 'id', "COALESCE(NULLIF(TRIM(role_name),''), 'Role')"); } catch (Throwable $e) {}

        try {
            if (!empty($idsByTable['registrations'])) {
                $ids = array_keys($idsByTable['registrations']);
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql = "
                    SELECT r.id AS rid,
                           COALESCE(
                               NULLIF(TRIM(r.student_name), ''),
                               NULLIF(TRIM(r.enquiry_snapshot_name), ''),
                               NULLIF(TRIM(e.name), ''),
                               CONCAT('Registration ', COALESCE(r.registration_no, ''))
                           ) AS label
                    FROM registrations r
                    LEFT JOIN enquiries e ON e.id = r.enquiry_id
                    WHERE r.id IN ({$ph})
                ";
                $st = $pdo->prepare($sql);
                $st->execute($ids);
                foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
                    $rid = (int)($r['rid'] ?? 0);
                    $label = trim((string)($r['label'] ?? ''));
                    if ($rid > 0 && $label !== '') {
                        $map['registrations:' . $rid] = $label;
                    }
                }
            }
        } catch (Throwable $e) {}

        try {
            if (!empty($idsByTable['registration_payments'])) {
                $ids = array_keys($idsByTable['registration_payments']);
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql = "
                    SELECT p.id AS rid,
                           COALESCE(
                               NULLIF(TRIM(r.student_name), ''),
                               NULLIF(TRIM(r.enquiry_snapshot_name), ''),
                               NULLIF(TRIM(e.name), ''),
                               CONCAT('Registration ', COALESCE(r.registration_no, '')),
                               'registration'
                           ) AS label
                    FROM registration_payments p
                    LEFT JOIN registrations r ON r.id = p.registration_id
                    LEFT JOIN enquiries e ON e.id = r.enquiry_id
                    WHERE p.id IN ({$ph})
                ";
                $st = $pdo->prepare($sql);
                $st->execute($ids);
                foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
                    $rid = (int)($r['rid'] ?? 0);
                    $label = trim((string)($r['label'] ?? ''));
                    if ($rid > 0 && $label !== '') {
                        $map['registration_payments:' . $rid] = $label;
                    }
                }
            }
        } catch (Throwable $e) {}

        try {
            if (!empty($idsByTable['enquiry_followups'])) {
                $ids = array_keys($idsByTable['enquiry_followups']);
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql = "
                    SELECT f.id AS rid,
                           COALESCE(
                               NULLIF(TRIM(e.name), ''),
                               CONCAT('Enquiry ', COALESCE(CAST(e.id AS CHAR), '')),
                               'Enquiry'
                           ) AS label
                    FROM enquiry_followups f
                    LEFT JOIN enquiries e ON e.id = f.enquiry_id
                    WHERE f.id IN ({$ph})
                ";
                $st = $pdo->prepare($sql);
                $st->execute($ids);
                foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
                    $rid = (int)($r['rid'] ?? 0);
                    $label = trim((string)($r['label'] ?? ''));
                    if ($rid > 0 && $label !== '') {
                        $map['enquiry_followups:' . $rid] = $label;
                    }
                }
            }
        } catch (Throwable $e) {}

        try {
            if (!empty($idsByTable['monthly_targets'])) {
                $ids = array_keys($idsByTable['monthly_targets']);
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql = "
                    SELECT mt.id AS rid,
                           COALESCE(NULLIF(TRIM(u.name), ''), 'User') AS label
                    FROM monthly_targets mt
                    LEFT JOIN users u ON u.id = mt.user_id
                    WHERE mt.id IN ({$ph})
                ";
                $st = $pdo->prepare($sql);
                $st->execute($ids);
                foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
                    $rid = (int)($r['rid'] ?? 0);
                    $label = trim((string)($r['label'] ?? ''));
                    if ($rid > 0 && $label !== '') {
                        $map['monthly_targets:' . $rid] = $label;
                    }
                }
            }
        } catch (Throwable $e) {}

        return $map;
    }
}

if (!function_exists('auditParseYmd')) {
    function auditParseYmd(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$dt) {
            return null;
        }
        $err = DateTimeImmutable::getLastErrors();
        if (!empty($err['warning_count']) || !empty($err['error_count'])) {
            return null;
        }
        if ($dt->format('Y-m-d') !== $value) {
            return null;
        }
        return $dt;
    }
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdmin = crmIsSuperAdminRole();
$isHr = crmIsHrRole();

$defaultFrom = (new DateTimeImmutable('today'))->modify('-7 days');
$defaultTo = new DateTimeImmutable('today');

$fromRaw = trim((string)($_GET['from'] ?? $defaultFrom->format('Y-m-d')));
$toRaw = trim((string)($_GET['to'] ?? $defaultTo->format('Y-m-d')));
$module = trim((string)($_GET['module'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$limit = (int)($_GET['limit'] ?? 150);
if ($limit < 50) {
    $limit = 50;
}
if ($limit > 500) {
    $limit = 500;
}

$fromDt = auditParseYmd($fromRaw) ?: $defaultFrom;
$toDt = auditParseYmd($toRaw) ?: $defaultTo;
if ($fromDt > $toDt) {
    $fromDt = $defaultFrom;
    $toDt = $defaultTo;
}
$rangeClamped = false;
$rangeDays = (int)$fromDt->diff($toDt)->days;
if ($rangeDays > 90) {
    $fromDt = $toDt->modify('-90 days');
    $rangeClamped = true;
}
$from = $fromDt->format('Y-m-d');
$to = $toDt->format('Y-m-d');
$toExclusive = $toDt->modify('+1 day');

$where = ["a.created_at >= :from_dt", "a.created_at < :to_dt"];
$params = [
    ':from_dt' => $fromDt->format('Y-m-d') . ' 00:00:00',
    ':to_dt' => $toExclusive->format('Y-m-d') . ' 00:00:00',
];

if ($module !== '') {
    $where[] = "a.table_name = :module";
    $params[':module'] = $module;
}

if ($search !== '') {
    $where[] = "(a.action LIKE :search OR COALESCE(u.name,'') LIKE :search OR COALESCE(r.role_name,'') LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($isSuperAdmin) {
    // Full logs.
} elseif ($isHr) {
    $where[] = "u.branch_id = :branch_id";
    $params[':branch_id'] = $branchId;
    $where[] = "a.table_name IN ('enquiries','enquiry_followups','monthly_targets','registration_payments','users','roles')";
} else {
    $where[] = "a.user_id = :user_id";
    $params[':user_id'] = $userId;
}

$rows = [];
$moduleOptions = [];
$auditLoadError = '';
$entityNameMap = [];
try {
    crmEnsureAuditLogsTable($pdo);

    $moduleWhere = ["created_at >= :from_dt", "created_at < :to_dt"];
    $moduleParams = [
        ':from_dt' => $fromDt->format('Y-m-d') . ' 00:00:00',
        ':to_dt' => $toExclusive->format('Y-m-d') . ' 00:00:00',
    ];
    if ($isSuperAdmin) {
        $moduleOptionsSql = "
            SELECT DISTINCT COALESCE(table_name, '') AS table_name
            FROM audit_logs
            WHERE table_name IS NOT NULL AND table_name <> ''
              AND " . implode(" AND ", $moduleWhere) . "
            ORDER BY table_name ASC
        ";
    } elseif ($isHr) {
        $moduleWhere = ["a.created_at >= :from_dt", "a.created_at < :to_dt", "u.branch_id = :branch_id"];
        $moduleParams[':branch_id'] = $branchId;
        $moduleWhere[] = "a.table_name IN ('enquiries','enquiry_followups','monthly_targets','registration_payments','users','roles')";
        $moduleOptionsSql = "
            SELECT DISTINCT COALESCE(a.table_name, '') AS table_name
            FROM audit_logs a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.table_name IS NOT NULL AND a.table_name <> ''
              AND " . implode(" AND ", $moduleWhere) . "
            ORDER BY a.table_name ASC
        ";
    } else {
        $moduleWhere = ["created_at >= :from_dt", "created_at < :to_dt", "user_id = :user_id"];
        $moduleParams[':user_id'] = $userId;
        $moduleOptionsSql = "
            SELECT DISTINCT COALESCE(table_name, '') AS table_name
            FROM audit_logs
            WHERE table_name IS NOT NULL AND table_name <> ''
              AND " . implode(" AND ", $moduleWhere) . "
            ORDER BY table_name ASC
        ";
    }

    $moduleSt = $pdo->prepare($moduleOptionsSql);
    $moduleSt->execute($moduleParams);
    $moduleOptions = $moduleSt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $sql = "
        SELECT
            a.id,
            a.user_id,
            a.action,
            a.table_name,
            a.record_id,
            a.ip_address,
            a.browser,
            a.device_type,
            a.latitude,
            a.longitude,
            a.location_text,
            a.location_source,
            a.created_at,
            COALESCE(u.name, '-') AS user_name,
            COALESCE(r.role_name, '-') AS role_name,
            COALESCE(b.branch_name, '-') AS branch_name
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        LEFT JOIN roles r ON r.id = u.role_id
        LEFT JOIN branches b ON b.id = u.branch_id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT " . $limit;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $entityNameMap = auditBuildEntityNameMap($pdo, $rows);
} catch (Exception $e) {
    $rows = [];
    $moduleOptions = [];
    $auditLoadError = 'Audit logs could not be loaded right now. Please refresh and try again.';
}

$rowCount = count($rows);
$activeFilterCount = 0;
if ($module !== '') {
    $activeFilterCount++;
}
if ($search !== '') {
    $activeFilterCount++;
}
if ($from !== $defaultFrom->format('Y-m-d') || $to !== $defaultTo->format('Y-m-d')) {
    $activeFilterCount++;
}
?>

<style>
.audit-wrap { display:grid; gap:16px; min-width:0; max-width:100%; overflow-x:hidden; }
.audit-hero {
  border:1px solid #f3bfd4;
  border-radius:18px;
  padding:18px;
  background:linear-gradient(120deg, #fff7fb 0%, #ffeef6 55%, #fff 100%);
  display:grid;
  gap:14px;
  min-width:0;
  max-width:100%;
}
.audit-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.audit-title { margin:0; font-size:2rem; font-weight:800; color:#b01757; line-height:1.1; }
.audit-sub { margin:4px 0 0; color:#5b6578; font-weight:600; }
.audit-meta { color:#4a5568; font-weight:700; font-size:.88rem; background:#fff; border:1px solid #f2d7e3; border-radius:999px; padding:6px 12px; }
.audit-insights { display:grid; grid-template-columns:repeat(4,minmax(120px,1fr)); gap:10px; }
.audit-kpi {
  border:1px solid #f1d4e1;
  border-radius:14px;
  background:#fff;
  padding:10px 12px;
}
.audit-kpi-label { color:#7a8598; font-size:.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
.audit-kpi-value { color:#223047; font-size:1.15rem; font-weight:800; margin-top:2px; }
.audit-card { border:1px solid #f4c6d7; border-radius:16px; padding:14px; background:#fff; min-width:0; max-width:100%; }
.audit-filters { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; align-items:end; }
.audit-field { grid-column:auto; }
.audit-field-wide { grid-column:auto; }
.audit-actions { grid-column:auto; display:flex; gap:8px; align-items:center; }
.audit-filters .form-control, .audit-filters .form-select { height:42px; border-radius:12px; }
.audit-btn { height:42px; border-radius:12px; border:0; background:#f72585; color:#fff; font-weight:700; padding:0 16px; white-space:nowrap; }
.audit-btn-link { display:inline-flex; align-items:center; justify-content:center; height:42px; border-radius:12px; border:1px solid #f3c3d8; color:#b01757; background:#fff; font-weight:700; padding:0 14px; text-decoration:none; white-space:nowrap; }
.audit-toolbar { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px; }
.audit-hint { color:#7a8598; font-size:.84rem; font-weight:600; }
.audit-soft-error{
  border:1px solid #f4d2d2;
  border-radius:12px;
  background:#fff5f5;
  color:#a12a2a;
  font-size:.9rem;
  font-weight:700;
  padding:10px 12px;
  margin-bottom:10px;
}
.audit-table-wrap { width:100%; max-width:100%; overflow-x:auto; overflow-y:visible; border:1px solid #f4c6d7; border-radius:14px; padding:10px; background:#fff; box-shadow:0 10px 28px rgba(176,23,87,.08); -webkit-overflow-scrolling:touch; }
.audit-table-wrap .crm-table { min-width:1320px; width:1320px; border-radius:12px; overflow:hidden; table-layout:auto; border-collapse:collapse; }
.audit-table-wrap .crm-table th {
  background:#fff1f7;
  color:#6d1d45;
  font-weight:800;
  font-size:.78rem;
  letter-spacing:.03em;
  text-transform:uppercase;
  padding:12px 10px;
  border-bottom:1px solid #f1cadb;
}
.audit-table-wrap .crm-table td {
  vertical-align:middle;
  color:#24344d;
  font-size:.94rem;
  line-height:1.35;
  padding:11px 10px;
  border-bottom:1px solid #f6deea;
}
.audit-table-wrap .crm-table tbody tr:nth-child(even){ background:#fffafd; }
.audit-table-wrap .crm-table tbody tr:hover{ background:#fff3f8; }
.audit-badge { display:inline-block; padding:3px 9px; border-radius:999px; background:#ffe2ef; color:#8a1146; font-size:.72rem; font-weight:800; line-height:1.2; }
.audit-geo-cell{
  display:block;
  min-width:240px;
  max-width:none;
  white-space:normal;
  overflow-wrap:anywhere;
  line-height:1.3;
}
.audit-empty { padding:22px; text-align:center; color:#607087; font-weight:700; }
.audit-table-wrap .crm-table-header,
.audit-table-wrap .crm-table-footer{
  padding:4px 2px 10px;
}
.audit-table-wrap .dataTables_wrapper{
  width:100%;
  min-width:0;
  max-width:100%;
}
.audit-table-wrap .dataTables_scroll,
.audit-table-wrap .dataTables_scrollHead,
.audit-table-wrap .dataTables_scrollBody{
  width:100% !important;
  max-width:100% !important;
}
.audit-table-wrap .dataTables_scroll{
  overflow-x:hidden !important;
  overflow-y:visible !important;
}
.audit-table-wrap .dataTables_scrollHead{
  overflow-x:hidden !important;
}
.audit-table-wrap .dataTables_scrollBody{
  overflow-x:auto !important;
  overflow-y:hidden !important;
  max-height:none;
  cursor:grab;
  -webkit-overflow-scrolling:touch;
  border:1px solid #f3d4e2;
  border-radius:12px;
  background:#fff;
}
.audit-table-wrap .dataTables_scrollBody.is-dragging{ cursor:grabbing; }
.audit-table-wrap .dataTables_scrollBody.is-dragging,
.audit-table-wrap .dataTables_scrollBody.is-dragging *{
  user-select:none !important;
}
#auditLogsTable th,
#auditLogsTable td{
  white-space:nowrap !important;
  vertical-align:middle;
}
#auditLogsTable th:nth-child(11),
#auditLogsTable td:nth-child(11){
  min-width:320px !important;
  white-space:normal !important;
}
#auditLogsTable th:nth-child(12),
#auditLogsTable td:nth-child(12){
  min-width:120px !important;
}
.audit-table-wrap .dataTables_scrollBody::-webkit-scrollbar{
  height:10px;
  width:12px;
}
.audit-table-wrap::-webkit-scrollbar-track{
  background:#f6e6ee;
  border-radius:999px;
}
.audit-table-wrap::-webkit-scrollbar-thumb{
  background:#d74f85;
  border-radius:999px;
}

/* Product-like DataTable controls */
#auditLogsTable_wrapper .crm-table-header,
#auditLogsTable_wrapper .crm-table-footer{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
}
#auditLogsTable_wrapper .dataTables_length label,
#auditLogsTable_wrapper .dataTables_filter label,
#auditLogsTable_wrapper .dataTables_info{
  margin:0;
  font-size:.92rem;
  color:#556177;
  font-weight:600;
}
#auditLogsTable_wrapper .dataTables_length select{
  min-width:72px;
  height:38px;
  border:1px solid #edc5d8;
  border-radius:12px;
  padding:0 30px 0 12px;
  background:#fff;
  color:#253247;
  font-weight:700;
}
#auditLogsTable_wrapper .dataTables_filter input{
  min-width:260px;
  height:40px;
  border:1px solid #edc5d8;
  border-radius:999px;
  padding:0 14px;
  background:#fff;
  color:#253247;
}
#auditLogsTable_wrapper .dataTables_filter input:focus,
#auditLogsTable_wrapper .dataTables_length select:focus{
  outline:none;
  border-color:#d84c85;
  box-shadow:0 0 0 3px rgba(216,76,133,.14);
}
#auditLogsTable_wrapper .dataTables_paginate{
  display:flex;
  align-items:center;
  gap:6px;
}
#auditLogsTable_wrapper .dataTables_paginate .paginate_button{
  min-width:34px;
  height:34px;
  border-radius:10px !important;
  border:1px solid #edc5d8 !important;
  background:#fff !important;
  color:#8a1146 !important;
  font-weight:700;
  display:inline-flex !important;
  align-items:center;
  justify-content:center;
  padding:0 10px !important;
}
#auditLogsTable_wrapper .dataTables_paginate .paginate_button.current{
  background:#e31b72 !important;
  border-color:#e31b72 !important;
  color:#fff !important;
}
#auditLogsTable_wrapper .dataTables_paginate .paginate_button:hover{
  background:#fff3f8 !important;
  border-color:#e9aac4 !important;
  color:#8a1146 !important;
}
#auditLogsTable_wrapper .dataTables_paginate .paginate_button.disabled{
  opacity:.55;
}
@media (max-width:1300px){
  .audit-insights { grid-template-columns:repeat(2,minmax(120px,1fr)); }
}
@media (max-width:900px){
  .audit-filters { grid-template-columns:repeat(2,minmax(140px,1fr)); }
  .audit-actions { display:grid; grid-template-columns:1fr; }
  #auditLogsTable_wrapper .dataTables_filter input{ min-width:200px; }
}
</style>

<div class="audit-wrap">
    <div class="audit-hero">
        <div class="audit-head">
            <div>
                <h2 class="audit-title">Audit Logs</h2>
                <p class="audit-sub">Track who changed what and when, without losing important details in hidden columns.</p>
            </div>
            <div class="audit-meta">
                <?php if ($isSuperAdmin): ?>
                    Scope: All modules, all users
                <?php elseif ($isHr): ?>
                    Scope: Team operational logs (branch scoped)
                <?php else: ?>
                    Scope: My activity only
                <?php endif; ?>
            </div>
        </div>
        <div class="audit-insights">
            <div class="audit-kpi">
                <div class="audit-kpi-label">Rows Loaded</div>
                <div class="audit-kpi-value"><?= (int)$rowCount ?></div>
            </div>
            <div class="audit-kpi">
                <div class="audit-kpi-label">Date Range</div>
                <div class="audit-kpi-value"><?= htmlspecialchars(date('d M', strtotime($from)) . ' - ' . date('d M', strtotime($to))) ?></div>
            </div>
            <div class="audit-kpi">
                <div class="audit-kpi-label">Module</div>
                <div class="audit-kpi-value"><?= htmlspecialchars($module !== '' ? $module : 'All Modules') ?></div>
            </div>
            <div class="audit-kpi">
                <div class="audit-kpi-label">Active Filters</div>
                <div class="audit-kpi-value"><?= (int)$activeFilterCount ?></div>
            </div>
        </div>
    </div>

    <form method="get" class="audit-card audit-filters">
        <input type="hidden" name="page" value="system/audit_logs">
        <div class="audit-field">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="audit-field">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="audit-field">
            <label class="form-label">Module</label>
            <select name="module" class="form-select">
                <option value="">All Modules</option>
                <?php foreach ($moduleOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>" <?= ($module === $opt ? 'selected' : '') ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="audit-field-wide">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Action, user, role...">
        </div>
        <div class="audit-field">
            <label class="form-label">Rows</label>
            <select name="limit" class="form-select">
                <?php foreach ([50, 100, 150, 250, 500] as $l): ?>
                    <option value="<?= $l ?>" <?= ($limit === $l ? 'selected' : '') ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="audit-actions">
            <button type="submit" class="audit-btn">Apply Filter</button>
            <a class="audit-btn-link" href="index.php?page=system/audit_logs">Reset</a>
        </div>
        <?php if ($rangeClamped): ?>
            <div class="audit-field-wide">
                <div class="audit-hint">Date range is limited to 90 days for performance. Older range was auto-adjusted.</div>
            </div>
        <?php endif; ?>
    </form>

    <div class="audit-card">
        <?php if ($auditLoadError !== ''): ?>
            <div class="audit-soft-error"><?= htmlspecialchars($auditLoadError) ?></div>
        <?php endif; ?>
        <div class="audit-toolbar">
            <div class="audit-hint">Tip: Scroll inside the table area to view every column. Geo details now wrap instead of cutting off.</div>
        </div>
        <div class="audit-table-wrap crm-table-wrapper">
            <table id="auditLogsTable" class="crm-table no-mobile-cards no-card-mobile">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>When</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Record</th>
                        <th>Browser</th>
                        <th>Device</th>
                        <th>Geo</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $i => $row): ?>
                            <tr>
                                <?php
                                $tblKey = strtolower(trim((string)($row['table_name'] ?? '')));
                                $ridKey = (int)($row['record_id'] ?? 0);
                                $entityName = ($tblKey !== '' && $ridKey > 0 && isset($entityNameMap[$tblKey . ':' . $ridKey]))
                                    ? (string)$entityNameMap[$tblKey . ':' . $ridKey]
                                    : '';
                                $recordDisplay = $entityName;
                                if ($recordDisplay === '') {
                                    $recordDisplay = auditRecordNameFromAction(
                                        (string)($row['action'] ?? ''),
                                        (string)($row['table_name'] ?? ''),
                                        (string)($row['user_name'] ?? '')
                                    );
                                }
                                ?>
                                <td><?= (int)$i + 1 ?></td>
                                <td data-order="<?= (int)strtotime((string)($row['created_at'] ?? '')) ?>"><?= htmlspecialchars(auditFmtWhen($row['created_at'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)$row['user_name']) ?></td>
                                <td><?= htmlspecialchars((string)$row['role_name']) ?></td>
                                <td><?= htmlspecialchars((string)$row['branch_name']) ?></td>
                                <td><span class="audit-badge"><?= htmlspecialchars((string)$row['table_name']) ?></span></td>
                                <td title="<?= htmlspecialchars((string)$row['action']) ?>">
                                    <?= htmlspecialchars(auditFmtAction($row['action'] ?? '', $row['table_name'] ?? '', (int)($row['record_id'] ?? 0), $entityName)) ?>
                                </td>
                                <td><?= htmlspecialchars($recordDisplay !== '' ? $recordDisplay : '-') ?></td>
                                <td><?= htmlspecialchars((string)($row['browser'] ?: '-')) ?></td>
                                <td><?= htmlspecialchars((string)($row['device_type'] ?: '-')) ?></td>
                                <td>
                                    <?php
                                    $geoParts = [];
                                    $latRaw = $row['latitude'] ?? null;
                                    $lonRaw = $row['longitude'] ?? null;
                                    $hasLat = ($latRaw !== null && $latRaw !== '' && is_numeric($latRaw));
                                    $hasLon = ($lonRaw !== null && $lonRaw !== '' && is_numeric($lonRaw));
                                    if ($hasLat && $hasLon) {
                                        $lat = (float)$latRaw;
                                        $lon = (float)$lonRaw;
                                        $isZeroPair = (abs($lat) < 0.0000005 && abs($lon) < 0.0000005);
                                        if (!$isZeroPair) {
                                            $geoParts[] = number_format($lat, 6) . ', ' . number_format($lon, 6);
                                        }
                                    }
                                    if (trim((string)($row['location_text'] ?? '')) !== '') {
                                        $geoParts[] = (string)$row['location_text'];
                                    }
                                    $geoText = !empty($geoParts) ? implode(' | ', $geoParts) : '-';
                                    $src = strtolower(trim((string)($row['location_source'] ?? '')));
                                    if ($geoText !== '-') {
                                        if ($src === 'gps') {
                                            $geoText .= ' (Exact)';
                                        } elseif ($src === 'ip') {
                                            $geoText .= ' (Approx)';
                                        }
                                    }
                                    ?>
                                    <span class="audit-geo-cell" title="<?= htmlspecialchars($geoText) ?>">
                                        <?= htmlspecialchars($geoText) ?>
                                    </span>
                                    <?php
                                    ?>
                                </td>
                                <td><?= htmlspecialchars(auditFmtIp($row['ip_address'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="12" class="audit-empty">No activity logs found for this filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) return;
    if (jQuery.fn.DataTable.isDataTable('#auditLogsTable')) return;

    function bindAuditRowNumbers(dt) {
        if (!dt || typeof dt.on !== 'function') return;
        var redraw = function () {
            var info = dt.page.info();
            dt.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, i) {
                cell.textContent = String(i + 1);
            });
        };
        dt.on('order.dt search.dt page.dt draw.dt', redraw);
        redraw();
    }

    function bindAuditDragScroll() {
        var body = document.querySelector('#auditLogsTable_wrapper .dataTables_scrollBody');
        if (!body || body.dataset.dragBound === '1') return;
        body.dataset.dragBound = '1';

        var isDown = false;
        var startX = 0;
        var scrollLeft = 0;

        body.addEventListener('mousedown', function (e) {
            isDown = true;
            startX = e.pageX - body.offsetLeft;
            scrollLeft = body.scrollLeft;
            body.classList.add('is-dragging');
        });

        body.addEventListener('mouseleave', function () {
            isDown = false;
            body.classList.remove('is-dragging');
        });

        body.addEventListener('mouseup', function () {
            isDown = false;
            body.classList.remove('is-dragging');
        });

        body.addEventListener('mousemove', function (e) {
            if (!isDown) return;
            e.preventDefault();
            var x = e.pageX - body.offsetLeft;
            var walk = (x - startX) * 1.2;
            body.scrollLeft = scrollLeft - walk;
        });
    }

    if (typeof window.crmDataTable === 'function') {
        var dt = window.crmDataTable('#auditLogsTable', {
            pageLength: 25,
            ordering: true,
            responsive: false,
            scrollX: true,
            scrollCollapse: true,
            autoWidth: false,
            searching: true,
            info: true,
            order: [[1, 'desc']],
            columnDefs: [
                { targets: 0, orderable: false }
            ]
        });
        if (dt && typeof dt.columns === 'function') {
            dt.columns.adjust().draw(false);
            window.addEventListener('resize', function () {
                dt.columns.adjust();
            });
        }
        bindAuditRowNumbers(dt);
        setTimeout(bindAuditDragScroll, 0);
        return;
    }

    var fallbackDt = jQuery('#auditLogsTable').DataTable({
        pageLength: 25,
        ordering: true,
        responsive: false,
        scrollX: true,
        scrollCollapse: true,
        autoWidth: false,
        searching: true,
        info: true,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[1, 'desc']],
        columnDefs: [
            { targets: 0, orderable: false }
        ]
    });
    if (fallbackDt && typeof fallbackDt.columns === 'function') {
        fallbackDt.columns.adjust().draw(false);
    }
    bindAuditRowNumbers(fallbackDt);
    setTimeout(bindAuditDragScroll, 0);
});
</script>
