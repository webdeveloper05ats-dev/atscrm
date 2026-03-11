<?php
// ============================================
// ATS CRM - Branch Filter Core
// ============================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

/*
|--------------------------------------------------------------------------
| Check If User Is Super Admin
|--------------------------------------------------------------------------
*/

function isSuperAdmin()
{
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

/*
|--------------------------------------------------------------------------
| Get Current User Branch ID
|--------------------------------------------------------------------------
*/

function getUserBranchId()
{
    return $_SESSION['branch_id'] ?? null;
}

/*
|--------------------------------------------------------------------------
| Apply Branch Condition For Queries
|--------------------------------------------------------------------------
| Usage:
| $sql = "SELECT * FROM leads WHERE 1=1 " . branchCondition();
|--------------------------------------------------------------------------
*/

function branchCondition($table_alias = '')
{
    if (isSuperAdmin()) {
        return ''; // No restriction
    }

    $branch_id = getUserBranchId();

    if (!$branch_id) {
        return ' AND 1=0 '; // Safety block
    }

    if ($table_alias) {
        return " AND {$table_alias}.branch_id = " . intval($branch_id) . " ";
    }

    return " AND branch_id = " . intval($branch_id) . " ";
}

/*
|--------------------------------------------------------------------------
| Secure Insert Branch ID
|--------------------------------------------------------------------------
*/

function getBranchIdForInsert()
{
    if (isSuperAdmin()) {
        return null; // Super admin can choose branch manually
    }

    return getUserBranchId();
}