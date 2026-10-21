<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Zalogowany użytkownik od razu trafia do swojego panelu
if ($u = current_user()) {
    redirect(home_for($u));
}

$errorMsg = '';
$infoMsg  = '';

if (!empty($_GET['logout'])) {
    $infoMsg = 'Wylogowano pomyślnie.';
}
if (!empty($_GET['timeout']) || !empty($_SESSION['timed_out'])) {
    $infoMsg = 'Sesja wygasła z powodu bezczynności. Zaloguj się ponownie.';
    unset($_SESSION['timed_out']);
}

$lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);

if (is_post()) {
    csrf_check();

    if ($lockedUntil > time()) {
        $errorMsg = 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za ' . ($lockedUntil - time()) . ' s.';
    } else {
        $login    = post('login');
        $password = $_POST['password'] ?? '';
        $user     = null;

        try {
            $user = authenticate($login, is_string($password) ? $password : '');
        } catch (Throwable $e) {
            $errorMsg = 'Wystąpił błąd bazy danych. Skontaktuj się z administratorem.'
                . (cfg('debug') ? ' (' . $e->getMessage() . ')' : '');
        }

        if ($user) {
            login_user($user);
            redirect(home_for($user));
        }

        if ($errorMsg === '') {
            $fails = (int)($_SESSION['login_fails'] ?? 0) + 1;
            $_SESSION['login_fails'] = $fails;
            if ($fails >= (int)cfg('login_max_attempts', 5)) {
                $_SESSION['login_locked_until'] = time() + (int)cfg('login_lock_seconds', 120);
                $_SESSION['login_fails'] = 0;
                $errorMsg = 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za ' . (int)cfg('login_lock_seconds', 120) . ' s.';
            } else {
                $errorMsg = 'Nieprawidłowy login lub hasło.';
            }
            usleep(300000);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harmonogram Pracy — Logowanie</title>
    <link rel="icon" type="image/png" href="img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('loginstyle.css')) ?>">
</head>
<body>

<div class="login-wrapper">
    <div class="panel-left">
        <div class="brand">
            <h1>Harmonogram<br>Pracy</h1>
            <p><?= e(cfg('ward_name')) ?></p>
        </div>
        <div class="shift-legend">
            <div class="shifts">
                <div class="shift-badge">Zaprojektowane i wykonane przez: <a class="webpartnersHref" href="https://www.webpartners.pl">Web Partners</a></div>
            </div>
        </div>
    </div>

    <div class="panel-right">
        <h2>Witaj z powrotem</h2>
        <p class="subtitle">Zaloguj się do systemu grafików</p>

        <?php if ($infoMsg): ?>
        <div class="info-msg"><?= e($infoMsg) ?></div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
        <div class="error-msg" role="alert"><?= e($errorMsg) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="login">Login</label>
                <input type="text" id="login" name="login" placeholder="Imię@Nazwisko" autocomplete="username"
                       value="<?= e($_POST['login'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Hasło</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" data-toggle-pass="#password" tabindex="-1">Pokaż</button>
                </div>
                <div class="caps-hint" id="capsHint" hidden>Włączony jest Caps Lock</div>
            </div>
            <button type="submit" class="btn-submit">Zaloguj się</button>
        </form>

        <p class="footer-note">Problemy z logowaniem? Skontaktuj się z administratorem.<br>
            <a href="../index.html" style="display:inline-block; margin-top:10px; color:inherit; font-weight:600; text-underline-offset:3px;">← Wróć do wyboru oddziału</a>
        </p>

    </div>
</div>

<script src="<?= e(asset('app.js')) ?>"></script>
<script>
(function () {
    var pw = document.getElementById('password'), hint = document.getElementById('capsHint');
    function check(e) { if (e.getModifierState) hint.hidden = !e.getModifierState('CapsLock'); }
    pw.addEventListener('keydown', check);
    pw.addEventListener('keyup', check);
    pw.addEventListener('blur', function () { hint.hidden = true; });
})();
</script>
</body>
</html>
