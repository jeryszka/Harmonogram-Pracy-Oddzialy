<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();
ensure_schema();

$self = 'adminPanel/php/manageEmployees.php';

/* ---------- Obsługa akcji (POST, potem redirect) ---------- */
if (is_post()) {
    csrf_check();
    $action = post('action');

    try {
        if ($action === 'add' || $action === 'update') {
            $name    = preg_replace('/\s+/u', ' ', post('name'));
            $surname = preg_replace('/\s+/u', ' ', post('surname'));
            $id      = (int)post('id');

            if (!emp_valid_name($name) || !emp_valid_name($surname)) {
                flash('error', 'Imię i nazwisko mogą zawierać tylko litery, spację, myślnik lub apostrof (2–60 znaków).');
            } elseif (emp_exists_login(emp_make_login($name, $surname), $action === 'update' ? $id : 0)) {
                flash('error', 'Pracownik ' . $name . ' ' . $surname . ' już istnieje w bazie danych.');
            } elseif ($action === 'add') {
                emp_add($name, $surname);
                flash('success', 'Dodano pracownika: ' . $name . ' ' . $surname . '. Hasło tymczasowe: ' . emp_default_password($surname));
            } else {
                if (emp_update($id, $name, $surname)) {
                    flash('success', 'Zapisano zmiany: ' . $name . ' ' . $surname . '.');
                } else {
                    flash('error', 'Nie znaleziono pracownika.');
                }
            }
        } elseif ($action === 'reset') {
            $emp = emp_find((int)post('id'));
            if ($emp) {
                emp_set_password($emp['id'], emp_default_password($emp['surname']), false);
                flash('success', 'Hasło pracownika ' . emp_full_name($emp) . ' zresetowano do domyślnego (' . emp_default_password($emp['surname']) . ').');
            } else {
                flash('error', 'Nie znaleziono pracownika.');
            }
        } elseif ($action === 'delete') {
            $emp = emp_find((int)post('id'));
            if (!$emp) {
                flash('error', 'Nie znaleziono pracownika o podanym ID.');
            } elseif (emp_delete($emp['id'])) {
                flash('success', 'Usunięto pracownika: ' . emp_full_name($emp) . '.');
            } else {
                flash('error', 'Błąd bazy danych. Spróbuj ponownie.');
            }
        }
    } catch (Throwable $e) {
        flash('error', 'Błąd bazy danych' . (cfg('debug') ? ': ' . $e->getMessage() : '. Spróbuj ponownie.'));
    }
    redirect($self);
}

$employees = emp_all();
$hasFlag   = emp_has_pw_flag();

page_start('Pracownicy', [
    'active'   => 'employees',
    'h1'       => 'Pracownicy',
    'subtitle' => 'Dodawaj, edytuj i usuwaj pracowników oddziału',
]);
?>

<!-- Dodaj pracownika -->
<div class="card fade-in" style="flex:1 1 320px; max-width:420px">
    <div class="card-header">
        <div>
            <div class="card-title">Dodaj pracownika</div>
            <div class="card-subtitle">Domyślne hasło: Nazwisko<?= e(cfg('default_password_suffix')) ?></div>
        </div>
    </div>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label for="name">Imię</label>
                <input type="text" id="name" name="name" placeholder="np. Jan" maxlength="60" required>
            </div>
            <div class="form-group">
                <label for="surname">Nazwisko</label>
                <input type="text" id="surname" name="surname" placeholder="np. Kowalski" maxlength="60" required>
            </div>
        </div>

        <div class="info-box">
            <strong>Login:</strong> <span id="previewLogin">Imię@Nazwisko</span><br>
            <strong>Hasło tymczasowe:</strong> <span id="previewPass">Nazwisko<?= e(cfg('default_password_suffix')) ?></span><br>
            Pracownik zostanie poproszony o zmianę hasła przy pierwszym logowaniu.
        </div>

        <button type="submit" class="btn btn-primary">Dodaj pracownika</button>
    </form>
</div>

<!-- Lista pracowników -->
<div class="card fade-in" style="flex:2 1 520px">
    <div class="card-header">
        <div>
            <div class="card-title">Lista pracowników <span class="count-badge"><?= count($employees) ?></span></div>
            <div class="card-subtitle">Usunięcie pracownika kasuje też jego wpisy we wszystkich grafikach</div>
        </div>
        <input type="search" class="search-input" placeholder="Szukaj…" data-filter="#empTable" aria-label="Szukaj pracownika">
    </div>

    <div class="table-wrap">
        <table class="data-table" id="empTable">
            <thead>
                <tr>
                    <th>Pracownik</th>
                    <th>Login</th>
                    <?php if ($hasFlag): ?><th>Hasło</th><?php endif; ?>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr data-search="<?= e($emp['name'] . ' ' . $emp['surname'] . ' ' . $emp['login']) ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <span class="avatar-xs" style="width:30px;height:30px;font-size:11px"><?= e(emp_initials($emp)) ?></span>
                            <?= e($emp['surname'] . ' ' . $emp['name']) ?>
                        </div>
                    </td>
                    <td style="color:var(--gray-500)"><?= e($emp['login']) ?></td>
                    <?php if ($hasFlag): ?>
                    <td>
                        <?php if (emp_uses_default($emp)): ?>
                            <span class="badge badge-warning">domyślne</span>
                        <?php else: ?>
                            <span class="badge badge-success">zmienione</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="btn btn-secondary btn-sm"
                                    data-modal-open="editModal"
                                    data-id="<?= (int)$emp['id'] ?>"
                                    data-name="<?= e($emp['name']) ?>"
                                    data-surname="<?= e($emp['surname']) ?>">Edytuj</button>

                            <form method="POST" style="display:inline"
                                  data-confirm-title="Reset hasła"
                                  data-confirm="Zresetować hasło pracownika <?= e(emp_full_name($emp)) ?> do domyślnego?"
                                  data-confirm-btn="Resetuj">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset">
                                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Reset hasła</button>
                            </form>

                            <form method="POST" style="display:inline"
                                  data-confirm-title="Potwierdź usunięcie"
                                  data-confirm="Usunąć pracownika <?= e(emp_full_name($emp)) ?> wraz ze wszystkimi wpisami w grafikach? Tej operacji nie można cofnąć."
                                  data-confirm-btn="Tak, usuń">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Usuń</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="empty-state no-results" hidden>Brak pasujących pracowników.</p>
    <?php if (!$employees): ?><p class="empty-state">Brak pracowników w bazie danych. Dodaj pierwszego po lewej stronie.</p><?php endif; ?>
</div>

<!-- Modal edycji -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box" role="dialog" aria-modal="true">
        <h3>Edytuj pracownika</h3>
        <p class="modal-text" style="margin-bottom:18px">Zmiana imienia lub nazwiska zmienia też login. Hasło domyślne zostanie przeliczone dla nowego nazwiska.</p>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div class="form-row">
                <div class="form-group">
                    <label for="editName">Imię</label>
                    <input type="text" id="editName" name="name" maxlength="60" required data-autofocus>
                </div>
                <div class="form-group">
                    <label for="editSurname">Nazwisko</label>
                    <input type="text" id="editSurname" name="surname" maxlength="60" required>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-modal-close>Anuluj</button>
                <button type="submit" class="btn btn-primary">Zapisz</button>
            </div>
        </form>
    </div>
</div>

<?php
page_end(['inline' => "
document.addEventListener('modal:open', function (e) {
    if (e.detail.modal.id !== 'editModal') return;
    var t = e.detail.trigger;
    document.getElementById('editId').value = t.dataset.id;
    document.getElementById('editName').value = t.dataset.name;
    document.getElementById('editSurname').value = t.dataset.surname;
});
(function () {
    var n = document.getElementById('name'), s = document.getElementById('surname');
    var pl = document.getElementById('previewLogin'), pp = document.getElementById('previewPass');
    var suffix = " . json_attr(cfg('default_password_suffix')) . ";
    function upd() {
        pl.textContent = (n.value.trim() || 'Imię') + '@' + (s.value.trim() || 'Nazwisko');
        pp.textContent = (s.value.trim() || 'Nazwisko') + suffix;
    }
    n.addEventListener('input', upd); s.addEventListener('input', upd);
})();
"]);
