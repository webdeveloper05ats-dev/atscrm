<?php
// ============================================
// ATS CRM - Remember Me (Secure)
// Selector (public) + Validator (secret)
// ============================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

function remember_cookie_name(): string {
    return 'ats_remember';
}

function remember_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    return false;
}

function remember_cookie_set(string $selector, string $validator, int $days = 30): void {
    $value  = $selector . ':' . $validator;
    $expire = time() + ($days * 86400);

    setcookie(remember_cookie_name(), $value, [
        'expires'  => $expire,
        'path'     => '/',
        'secure'   => remember_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function remember_cookie_clear(): void {
    setcookie(remember_cookie_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => remember_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function remember_selector(): string {
    return bin2hex(random_bytes(12)); // 24 chars
}

function remember_validator(): string {
    return bin2hex(random_bytes(32)); // 64 chars
}

function remember_issue(PDO $pdo, int $userId, int $days = 30): void {
    $selector  = remember_selector();
    $validator = remember_validator();
    $hash      = password_hash($validator, PASSWORD_DEFAULT);
    $expires   = (new DateTime())->modify("+{$days} days")->format('Y-m-d H:i:s');

    $st = $pdo->prepare("
        UPDATE users
        SET remember_selector = :sel,
            remember_token_hash = :hash,
            remember_expires = :exp
        WHERE id = :id
        LIMIT 1
    ");
    $st->execute([
        ':sel'  => $selector,
        ':hash' => $hash,
        ':exp'  => $expires,
        ':id'   => $userId
    ]);

    remember_cookie_set($selector, $validator, $days);
}

function remember_revoke(PDO $pdo, int $userId): void {
    $st = $pdo->prepare("
        UPDATE users
        SET remember_selector = NULL,
            remember_token_hash = NULL,
            remember_expires = NULL
        WHERE id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $userId]);

    remember_cookie_clear();
}

/**
 * Try auto-login. Returns user row (with role_name/branch_name optional) or null.
 * If cookie is invalid -> clears cookie (and revokes token if suspicious).
 */
function remember_consume(PDO $pdo): ?array {
    $cookie = $_COOKIE[remember_cookie_name()] ?? '';
    if (!$cookie || !str_contains($cookie, ':')) return null;

    [$sel, $val] = explode(':', $cookie, 2);
    $sel = trim($sel);
    $val = trim($val);

    if (strlen($sel) !== 24 || strlen($val) < 40) {
        remember_cookie_clear();
        return null;
    }

    $st = $pdo->prepare("
        SELECT u.*, r.role_name, b.branch_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.remember_selector = :sel
          AND u.remember_expires IS NOT NULL
          AND u.remember_expires > NOW()
          AND u.status = 1
        LIMIT 1
    ");
    $st->execute([':sel' => $sel]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        remember_cookie_clear();
        return null;
    }

    $hash = $user['remember_token_hash'] ?? '';
    if (!$hash || !password_verify($val, $hash)) {
        // token mismatch => revoke (possible stolen cookie)
        remember_revoke($pdo, (int)$user['id']);
        return null;
    }

    // ✅ rotate token on successful auto-login
    remember_issue($pdo, (int)$user['id'], 30);

    return $user;
}
