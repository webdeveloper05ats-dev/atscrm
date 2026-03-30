<?php
// fix_scoping.php
$file = 'views/students/registered.php';
$content = file_get_contents($file);

// Add missing role name extraction
if (strpos($content, '$roleName =') === false) {
    $content = str_replace(
        '$branchId = (int)($_SESSION[\'branch_id\'] ?? 0);',
        '$branchId = (int)($_SESSION[\'branch_id\'] ?? 0);' . "\n" . '$roleName = trim((string)($_SESSION[\'role_name\'] ?? \'\'));',
        $content
    );
}

// Add the created_by logical scope
$target = "if (\$canAllBranches !== 1 && \$branchId > 0) {
    \$where[] = \"r.branch_id = ?\";
    \$params[] = \$branchId;
}";

$replacement = "if (\$canAllBranches !== 1 && \$branchId > 0) {
    \$where[] = \"r.branch_id = ?\";
    \$params[] = \$branchId;
}

if (strtolower(\$roleName) === 'front office') {
    \$where[] = \"r.created_by = ?\";
    \$params[] = \$userId;
} elseif (\$roleName === 'Staff') {
    \$where[] = \"(
        EXISTS (SELECT 1 FROM registration_courses rc WHERE rc.registration_id = r.id AND rc.guide_staff_id = ?)
        OR
        EXISTS (SELECT 1 FROM registration_internships ri WHERE ri.registration_id = r.id AND ri.guide_staff_id = ?)
    )\";
    \$params[] = \$userId;
    \$params[] = \$userId;
} elseif (!in_array(strtolower(\$roleName), ['super admin', 'hr'], true)) {
    \$where[] = \"r.assigned_to = ?\";
    \$params[] = \$userId;
}";

// Handle CRLF safely
$targetCRLF = str_replace("\n", "\r\n", $target);
if (strpos($content, $targetCRLF) !== false) {
    $content = str_replace($targetCRLF, $replacement, $content);
} elseif (strpos($content, $target) !== false) {
    $content = str_replace($target, $replacement, $content);
}

file_put_contents($file, $content);
echo "Updated registered.php\n";

// Also check list.php to restrict front office
$file2 = 'views/registrations/list.php';
$content2 = file_get_contents($file2);

$target2 = "if (!in_array(\$roleName, ['super admin', 'hr'], true)) {
    \$where[] = \"r.assigned_to = ?\";
    \$params[] = \$userId;
}";

$replacement2 = "if (strtolower(\$roleName) === 'front office') {
    \$where[] = \"r.created_by = ?\";
    \$params[] = \$userId;
} elseif (!in_array(strtolower(\$roleName), ['super admin', 'hr'], true)) {
    \$where[] = \"r.assigned_to = ?\";
    \$params[] = \$userId;
}";

$target2CRLF = str_replace("\n", "\r\n", $target2);
if (strpos($content2, $target2CRLF) !== false) {
    $content2 = str_replace($target2CRLF, $replacement2, $content2);
    file_put_contents($file2, $content2);
    echo "Updated list.php\n";
} elseif (strpos($content2, $target2) !== false) {
    $content2 = str_replace($target2, $replacement2, $content2);
    file_put_contents($file2, $content2);
    echo "Updated list.php\n";
}
