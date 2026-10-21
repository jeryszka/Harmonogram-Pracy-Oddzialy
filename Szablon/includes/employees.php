<?php
/**
 * Pracownicy i hasła.
 * Tabela `employees`: employeeId, name, surname, login, password [, passwordChanged]
 * Kolumnę `passwordChanged` aplikacja dodaje sama (ensure_schema), jeśli ma uprawnienia ALTER.
 */

function emp_has_pw_flag($refresh = false)
{
    static $has = null;
    if ($has === null || $refresh) {
        try {
            $has = (bool)db()->query("SHOW COLUMNS FROM employees LIKE 'passwordChanged'")->fetch();
        } catch (Throwable $e) {
            $has = false;
        }
    }
    return $has;
}

/** Jednorazowa, automatyczna migracja schematu (flaga „hasło zmienione"). */
function ensure_schema()
{
    if (!empty($_SESSION['schema_checked'])) {
        return;
    }
    if (!emp_has_pw_flag()) {
        try {
            $pdo = db();
            $pdo->exec('ALTER TABLE employees ADD COLUMN passwordChanged TINYINT(1) NOT NULL DEFAULT 0');
            $rows = $pdo->query('SELECT employeeId AS id, surname, `password` FROM employees')->fetchAll();
            $upd  = $pdo->prepare('UPDATE employees SET passwordChanged = 1 WHERE employeeId = ?');
            foreach ($rows as $r) {
                if (!password_verify(emp_default_password($r['surname']), (string)$r['password'])) {
                    $upd->execute([$r['id']]);
                }
            }
            emp_has_pw_flag(true);
        } catch (Throwable $e) {
            // brak uprawnień ALTER – działamy dalej bez flagi
        }
    }
    $_SESSION['schema_checked'] = 1;
}

function emp_columns()
{
    return 'employeeId AS id, `name`, surname, login, `password`'
        . (emp_has_pw_flag() ? ', passwordChanged AS pwChanged' : '');
}

function emp_all()
{
    return db()->query('SELECT ' . emp_columns() . ' FROM employees ORDER BY surname, `name`')->fetchAll();
}

/** Mapa id => rekord pracownika. */
function emp_map()
{
    $map = [];
    foreach (emp_all() as $row) {
        $map[(int)$row['id']] = $row;
    }
    return $map;
}

function emp_find($id)
{
    $st = db()->prepare('SELECT ' . emp_columns() . ' FROM employees WHERE employeeId = ? LIMIT 1');
    $st->execute([(int)$id]);
    return $st->fetch() ?: null;
}

function emp_find_by_login($login)
{
    $st = db()->prepare('SELECT ' . emp_columns() . ' FROM employees WHERE login = ? LIMIT 1');
    $st->execute([$login]);
    return $st->fetch() ?: null;
}

function emp_exists_login($login, $exceptId = 0)
{
    $st = db()->prepare('SELECT COUNT(*) FROM employees WHERE login = ? AND employeeId <> ?');
    $st->execute([$login, (int)$exceptId]);
    return (int)$st->fetchColumn() > 0;
}

function emp_make_login($name, $surname)
{
    return $name . '@' . $surname;
}

function emp_default_password($surname)
{
    return $surname . cfg('default_password_suffix', '@OH3');
}

function emp_full_name(array $row)
{
    return trim($row['name'] . ' ' . $row['surname']);
}

function emp_initials(array $row)
{
    return mb_strtoupper(mb_substr($row['name'], 0, 1) . mb_substr($row['surname'], 0, 1));
}

/** Czy pracownik nadal ma hasło domyślne (wg flagi, a bez flagi – przez weryfikację hasha). */
function emp_uses_default(array $row)
{
    if (array_key_exists('pwChanged', $row)) {
        return !(int)$row['pwChanged'];
    }
    return password_verify(emp_default_password($row['surname']), (string)$row['password']);
}

/** Poprawne imię/nazwisko: litery (także polskie), spacja, myślnik, apostrof; 2–60 znaków. */
function emp_valid_name($value)
{
    return (bool)preg_match('/^[\p{L}][\p{L}\'’ \-]{1,59}$/u', $value);
}

/** Ustawia hasło; $changed=false oznacza hasło domyślne (wymusza zmianę przy logowaniu). */
function emp_set_password($id, $plain, $changed)
{
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    if (emp_has_pw_flag()) {
        $st = db()->prepare('UPDATE employees SET `password` = ?, passwordChanged = ? WHERE employeeId = ?');
        $st->execute([$hash, $changed ? 1 : 0, (int)$id]);
    } else {
        $st = db()->prepare('UPDATE employees SET `password` = ? WHERE employeeId = ?');
        $st->execute([$hash, (int)$id]);
    }
}

function emp_add($name, $surname)
{
    $pdo   = db();
    $login = emp_make_login($name, $surname);
    $hash  = password_hash(emp_default_password($surname), PASSWORD_DEFAULT);
    if (emp_has_pw_flag()) {
        $st = $pdo->prepare('INSERT INTO employees (`name`, surname, login, `password`, passwordChanged) VALUES (?, ?, ?, ?, 0)');
    } else {
        $st = $pdo->prepare('INSERT INTO employees (`name`, surname, login, `password`) VALUES (?, ?, ?, ?)');
    }
    $st->execute([$name, $surname, $login, $hash]);
    return (int)$pdo->lastInsertId();
}

/** Zmiana danych; jeśli hasło było domyślne – przelicza je dla nowego nazwiska. */
function emp_update($id, $name, $surname)
{
    $old = emp_find($id);
    if (!$old) {
        return false;
    }
    $login = emp_make_login($name, $surname);
    $st = db()->prepare('UPDATE employees SET `name` = ?, surname = ?, login = ? WHERE employeeId = ?');
    $st->execute([$name, $surname, $login, (int)$id]);
    if (emp_uses_default($old)) {
        emp_set_password($id, emp_default_password($surname), false);
    }
    return true;
}

/** Usuwa pracownika wraz z jego wpisami we wszystkich grafikach. */
function emp_delete($id)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach (list_schedules() as $sch) {
            $st = $pdo->prepare('DELETE FROM `' . $sch['table'] . '` WHERE employeeId = ?');
            $st->execute([(int)$id]);
        }
        $st = $pdo->prepare('DELETE FROM employees WHERE employeeId = ?');
        $st->execute([(int)$id]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}

/** Walidacja nowego hasła – zwraca komunikat błędu lub null. */
function password_policy_error($pass)
{
    $min = (int)cfg('password_min_length', 8);
    if (mb_strlen($pass) < $min) {
        return 'Hasło musi mieć minimum ' . $min . ' znaków.';
    }
    if (!preg_match('/\p{Lu}/u', $pass)) {
        return 'Hasło musi zawierać wielką literę.';
    }
    if (!preg_match('/[0-9]/', $pass)) {
        return 'Hasło musi zawierać cyfrę.';
    }
    return null;
}
