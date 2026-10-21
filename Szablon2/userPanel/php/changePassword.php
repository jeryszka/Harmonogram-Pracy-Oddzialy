<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user       = require_login();
$isAdmin    = $user['role'] === 'admin';
$mustChange = !$isAdmin && !empty($user['mustChange']);
$minLen     = (int)cfg('password_min_length', 8);

$error = '';

if (is_post()) {
    csrf_check();
    $current = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
    $pass1   = is_string($_POST['new'] ?? null) ? $_POST['new'] : '';
    $pass2   = is_string($_POST['repeat'] ?? null) ? $_POST['repeat'] : '';

    try {
        // Aktualny hash z bazy
        if ($isAdmin) {
            $st = db()->prepare('SELECT `password` FROM admins WHERE login = ? LIMIT 1');
            $st->execute([$user['login']]);
            $hash = (string)$st->fetchColumn();
        } else {
            $emp  = emp_find($user['id']);
            $hash = $emp ? (string)$emp['password'] : '';
        }

        if (!$mustChange && !password_verify($current, $hash)) {
            $error = 'Aktualne hasło jest nieprawidłowe.';
        } elseif ($pass1 !== $pass2) {
            $error = 'Hasła nie są takie same!';
        } elseif (($msg = password_policy_error($pass1)) !== null) {
            $error = $msg;
        } elseif ($hash !== '' && password_verify($pass1, $hash)) {
            $error = 'Nowe hasło musi różnić się od aktualnego.';
        } elseif (!$isAdmin && hash_equals(emp_default_password($user['surname']), $pass1)) {
            $error = 'Nie możesz użyć hasła domyślnego.';
        } else {
            if ($isAdmin) {
                $st = db()->prepare('UPDATE admins SET `password` = ? WHERE login = ?');
                $st->execute([password_hash($pass1, PASSWORD_DEFAULT), $user['login']]);
            } else {
                emp_set_password($user['id'], $pass1, true);
                $_SESSION['user']['mustChange'] = false;
            }
            session_regenerate_id(true);
            flash('success', 'Hasło zostało zmienione.');
            redirect(home_for($_SESSION['user']));
        }
    } catch (Throwable $e) {
        $error = 'Błąd bazy danych' . (cfg('debug') ? ': ' . $e->getMessage() : '. Spróbuj ponownie.');
    }
}

$who = $isAdmin ? 'Administrator (' . $user['login'] . ')' : trim($user['name'] . ' ' . $user['surname']);

page_start('Zmiana hasła', [
    'active'    => 'password',
    'h1'        => 'Zmiana hasła',
    'subtitle'  => 'Zarządzaj bezpieczeństwem swojego konta',
    'pageClass' => 'page-narrow',
]);
?>

<div style="flex:1 1 480px; max-width:480px; display:flex; flex-direction:column; gap:16px">
    <?php if ($mustChange): ?>
        <div class="alert alert-warning fade-in" style="margin:0">Używasz hasła domyślnego. Ustaw własne hasło, aby korzystać z systemu.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error fade-in" style="margin:0" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card fade-in" style="flex:none; max-width:none">
        <div class="card-header" style="display:block; text-align:center; margin-bottom:24px">
            <div class="card-title" style="font-size:18px">Ustaw nowe hasło</div>
            <div class="card-subtitle"><?= e($who) ?></div>
        </div>

        <form method="POST" autocomplete="off">
            <?= csrf_field() ?>

            <?php if (!$mustChange): ?>
            <div class="form-group">
                <label for="current">Aktualne hasło</label>
                <div class="pw-wrap">
                    <input type="password" id="current" name="current" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" data-toggle-pass="#current" tabindex="-1">Pokaż</button>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="pass1">Nowe hasło</label>
                <div class="pw-wrap">
                    <input type="password" id="pass1" name="new" placeholder="Wprowadź hasło" autocomplete="new-password" required>
                    <button type="button" class="pw-toggle" data-toggle-pass="#pass1" tabindex="-1">Pokaż</button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel">Wpisz hasło</div>

                <ul class="req-list">
                    <li id="req-len">Minimum <?= $minLen ?> znaków</li>
                    <li id="req-upper">Wielka litera</li>
                    <li id="req-num">Cyfra</li>
                </ul>
            </div>

            <div class="form-group" style="margin-bottom:25px">
                <label for="pass2">Powtórz hasło</label>
                <div class="pw-wrap">
                    <input type="password" id="pass2" name="repeat" placeholder="Wpisz ponownie" autocomplete="new-password" required>
                    <button type="button" class="pw-toggle" data-toggle-pass="#pass2" tabindex="-1">Pokaż</button>
                </div>
                <div id="matchMsg" style="font-size:12px; margin-top:5px"></div>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px">
                <button type="submit" class="btn btn-primary btn-block">Zaktualizuj hasło</button>
                <?php if (!$mustChange): ?>
                    <a href="<?= e(url(home_for($user))) ?>" class="btn btn-secondary btn-block">Anuluj</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php
page_end(['inline' => "
var MIN_LEN = " . $minLen . ";
function checkStrength(val) {
    var fill = document.getElementById('strengthFill');
    var label = document.getElementById('strengthLabel');
    var hasLen = val.length >= MIN_LEN;
    var hasUpper = /[A-ZĄĆĘŁŃÓŚŹŻ]/.test(val);
    var hasNum = /[0-9]/.test(val);
    document.getElementById('req-len').classList.toggle('met', hasLen);
    document.getElementById('req-upper').classList.toggle('met', hasUpper);
    document.getElementById('req-num').classList.toggle('met', hasNum);

    var score = [hasLen, hasUpper, hasNum, val.length >= 12].filter(Boolean).length;
    if (!val) score = 0;
    var widths = ['0%', '30%', '55%', '80%', '100%'];
    var colors = ['transparent', '#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
    var labels = ['Wpisz hasło', 'Słabe', 'Przeciętne', 'Dobre', 'Silne'];
    fill.style.width = widths[score];
    fill.style.background = colors[score];
    label.textContent = labels[score];
    label.style.color = colors[score] === 'transparent' ? 'var(--gray-400)' : colors[score];
}
function checkMatch() {
    var p1 = document.getElementById('pass1').value;
    var p2 = document.getElementById('pass2').value;
    var msg = document.getElementById('matchMsg');
    if (!p2) { msg.textContent = ''; return; }
    if (p1 === p2) { msg.textContent = 'Hasła są zgodne'; msg.style.color = '#10b981'; }
    else { msg.textContent = 'Hasła się różnią'; msg.style.color = '#ef4444'; }
}
document.getElementById('pass1').addEventListener('input', function () { checkStrength(this.value); checkMatch(); });
document.getElementById('pass2').addEventListener('input', checkMatch);
"]);
