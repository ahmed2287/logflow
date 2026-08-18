<?php
declare(strict_types=1);

/**
 * Multi-user auth backed by data/users.json (bcrypt hashes).
 * Roles: admin (manage users + settings + delete) / viewer (read-only).
 */

const ROLE_ADMIN  = 'admin';
const ROLE_VIEWER = 'viewer';
const ROLES = [ROLE_ADMIN => 'مدير', ROLE_VIEWER => 'مشاهدة فقط'];

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECS = 300;

function users_all(): array
{
    return json_read(USERS_FILE, []);
}

function users_save(array $users): bool
{
    return json_write(USERS_FILE, array_values($users));
}

function user_find(string $username): ?array
{
    foreach (users_all() as $user) {
        if (strcasecmp((string)($user['username'] ?? ''), $username) === 0) {
            return $user;
        }
    }
    return null;
}

function user_create(string $username, string $password, string $role = ROLE_VIEWER): array
{
    $username = trim($username);
    if ($username === '' || !preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username)) {
        return [false, 'اسم المستخدم يجب أن يكون 3-32 حرفًا (حروف إنجليزية، أرقام، . _ -).'];
    }
    if (mb_strlen($password) < 8) {
        return [false, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.'];
    }
    if (!isset(ROLES[$role])) {
        return [false, 'صلاحية غير معروفة.'];
    }
    if (user_find($username) !== null) {
        return [false, 'اسم المستخدم موجود بالفعل.'];
    }

    $users   = users_all();
    $users[] = [
        'username'   => $username,
        'password'   => password_hash($password, PASSWORD_BCRYPT),
        'role'       => $role,
        'created_at' => date('c'),
        'last_login' => null,
    ];
    if (!users_save($users)) {
        return [false, 'تعذّر حفظ ملف المستخدمين.'];
    }
    return [true, 'تم إنشاء المستخدم.'];
}

function user_delete(string $username): array
{
    $users = users_all();
    $kept  = array_values(array_filter(
        $users,
        fn($u) => strcasecmp((string)($u['username'] ?? ''), $username) !== 0
    ));

    if (count($kept) === count($users)) {
        return [false, 'المستخدم غير موجود.'];
    }
    $admins = array_filter($kept, fn($u) => ($u['role'] ?? '') === ROLE_ADMIN);
    if (!$admins) {
        return [false, 'لا يمكن حذف آخر مدير في النظام.'];
    }
    if (!users_save($kept)) {
        return [false, 'تعذّر حفظ ملف المستخدمين.'];
    }
    return [true, 'تم حذف المستخدم.'];
}

function user_set_password(string $username, string $password): array
{
    if (mb_strlen($password) < 8) {
        return [false, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.'];
    }
    $users = users_all();
    $found = false;
    foreach ($users as &$user) {
        if (strcasecmp((string)($user['username'] ?? ''), $username) === 0) {
            $user['password'] = password_hash($password, PASSWORD_BCRYPT);
            $found = true;
            break;
        }
    }
    unset($user);

    if (!$found) {
        return [false, 'المستخدم غير موجود.'];
    }
    if (!users_save($users)) {
        return [false, 'تعذّر حفظ ملف المستخدمين.'];
    }
    return [true, 'تم تغيير كلمة المرور.'];
}

function user_set_role(string $username, string $role): array
{
    if (!isset(ROLES[$role])) {
        return [false, 'صلاحية غير معروفة.'];
    }
    $users = users_all();
    foreach ($users as &$user) {
        if (strcasecmp((string)($user['username'] ?? ''), $username) === 0) {
            $user['role'] = $role;
        }
    }
    unset($user);

    $admins = array_filter($users, fn($u) => ($u['role'] ?? '') === ROLE_ADMIN);
    if (!$admins) {
        return [false, 'لا بد من وجود مدير واحد على الأقل.'];
    }
    if (!users_save($users)) {
        return [false, 'تعذّر حفظ ملف المستخدمين.'];
    }
    return [true, 'تم تحديث الصلاحية.'];
}

/* ----------------------------------------------------------------- login */

function login_attempt(string $username, string $password): array
{
    if (login_locked_out()) {
        $wait = (int)ceil((($_SESSION['lockout_until'] ?? 0) - time()) / 60);
        return [false, "تم إيقاف المحاولات مؤقتًا. حاول بعد {$wait} دقيقة."];
    }

    $user = user_find($username);
    // Always run a hash comparison so a missing user costs the same as a wrong
    // password — no timing signal about which usernames exist.
    $hash = $user['password'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';

    if (!password_verify($password, $hash) || $user === null) {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
            $_SESSION['lockout_until'] = time() + LOGIN_LOCKOUT_SECS;
            $_SESSION['login_attempts'] = 0;
        }
        audit_log('login_failed', ['username' => $username], $username !== '' ? $username : 'anonymous');
        return [false, 'اسم المستخدم أو كلمة المرور غير صحيحة.'];
    }

    unset($_SESSION['login_attempts'], $_SESSION['lockout_until']);
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'username' => $user['username'],
        'role'     => $user['role'] ?? ROLE_VIEWER,
    ];
    $_SESSION['login_time'] = time();

    $users = users_all();
    foreach ($users as &$stored) {
        if (strcasecmp((string)($stored['username'] ?? ''), $user['username']) === 0) {
            $stored['last_login'] = date('c');
        }
    }
    unset($stored);
    users_save($users);

    audit_log('login', [], $user['username']);
    return [true, 'مرحبًا ' . $user['username']];
}

function login_locked_out(): bool
{
    return isset($_SESSION['lockout_until']) && $_SESSION['lockout_until'] > time();
}

function logout(): void
{
    if (current_user()) {
        audit_log('logout');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], true);
    }
    session_destroy();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === ROLE_ADMIN;
}

function require_login(): void
{
    if (!current_user()) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect('?page=login');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash('error', 'هذه الصفحة متاحة للمديرين فقط.');
        redirect('?page=dashboard');
    }
}

/** True when no users exist yet — the app then shows first-run setup. */
function needs_setup(): bool
{
    return count(users_all()) === 0;
}
