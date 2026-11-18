<?php
declare(strict_types=1);

/** Check a staff login. On success the user is kept in the session. */
function login(string $username, string $password): bool
{
    $st = db()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
    $st->execute([$username]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    ensure_session();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user'] = ['id' => (int) $user['id'], 'username' => $user['username'], 'role' => $user['role']];
    return true;
}

function logout(): void
{
    ensure_session();
    $_SESSION = [];
    session_destroy();
}

function create_user(string $username, string $password, string $role = 'staff'): void
{
    db()->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?,?,?,?)')
        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, now()]);
}
