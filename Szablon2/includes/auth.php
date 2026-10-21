<?php
/**
 * Uwierzytelnianie i autoryzacja.
 * W sesji ($_SESSION['user']) trzymamy:
 *   role (admin|employee), login, id (employeeId lub null), name, surname, mustChange (bool)
 */
function current_user()
{
    return $_SESSION['user'] ?? null;
}

function is_admin()
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function login_user(array $user)
{
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['last_activity'] = time();
    unset($_SESSION['login_fails'], $_SESSION['login_locked_until'], $_SESSION['timed_out']);
}

function logout_user()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function home_for(array $user)
{
    return $user['role'] === 'admin' ? 'adminPanel/php/adminPanelMain.php' : 'userPanel/php/userPanel.php';
}

function require_login()
{
    $u = current_user();
    if (!$u) {
        redirect('index.php' . (!empty($_SESSION['timed_out']) ? '?timeout=1' : ''));
    }
    return $u;
}

function require_admin()
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        redirect(home_for($u));
    }
    return $u;
}

/** Strony pracownika. Pracownik z hasłem domyślnym jest kierowany do zmiany hasła. */
function require_employee($allowMustChange = false)
{
    $u = require_login();
    if ($u['role'] !== 'employee') {
        redirect(home_for($u));
    }
    if (!$allowMustChange && !empty($u['mustChange'])) {
        flash('warning', 'Zanim zaczniesz korzystać z systemu, zmień hasło domyślne.');
        redirect('userPanel/php/changePassword.php');
    }
    return $u;
}

/** Hash „wabik" – wyrównuje czas odpowiedzi, gdy login nie istnieje (utrudnia zgadywanie loginów). */
function dummy_hash()
{
    static $h = null;
    if ($h === null) {
        $h = password_hash('dummy-password', PASSWORD_DEFAULT);
    }
    return $h;
}

/**
 * Sprawdza login i hasło. Zwraca dane użytkownika do sesji albo null.
 * Najpierw szukamy w tabeli `admins`, potem w `employees`.
 */
function authenticate($login, $password)
{
    $st = db()->prepare('SELECT login, `password` FROM admins WHERE login = ? LIMIT 1');
    $st->execute([$login]);
    if ($admin = $st->fetch()) {
        if (password_verify($password, (string)$admin['password'])) {
            return ['role' => 'admin', 'login' => $admin['login'], 'id' => null, 'name' => '', 'surname' => '', 'mustChange' => false];
        }
        return null;
    }

    $emp = emp_find_by_login($login);
    if ($emp && password_verify($password, (string)$emp['password'])) {
        return [
            'role'       => 'employee',
            'login'      => $emp['login'],
            'id'         => (int)$emp['id'],
            'name'       => $emp['name'],
            'surname'    => $emp['surname'],
            // zalogowano hasłem domyślnym: wymuś zmianę
            'mustChange' => hash_equals(emp_default_password($emp['surname']), $password),
        ];
    }
    if (!$emp) {
        password_verify($password, dummy_hash());
    }
    return null;
}
