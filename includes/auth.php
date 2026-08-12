<?php
declare(strict_types=1);

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect('login.php');
    }
}

function attempt_admin_login(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_user'] = $username;
    return true;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_user']);
}
