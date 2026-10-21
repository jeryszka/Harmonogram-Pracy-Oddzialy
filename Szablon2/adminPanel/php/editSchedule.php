<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$self = 'adminPanel/php/editSchedule.php';

/* ---------- Zapis / usunięcie (POST, potem redirect) ---------- */
if (is_post()) {
    csrf_check();
    $sch = find_schedule_by_table(post('table'));
    if (!$sch) {
        flash('error', 'Nie znaleziono wskazanego grafiku.');
        redirect($self);
    }

    $action = post('action');
    try {
        if ($action === 'add_custom_shift') {
            $pendingData = json_decode((string)($_POST['pending_data'] ?? ''), true);
            if (!is_array($pendingData)) {
                throw new InvalidArgumentException('Nie udało się odczytać zmian w grafiku. Zapisz je ponownie.');
            }
            save_schedule($sch, $pendingData);
            $code = create_schedule_custom_shift($sch, $_POST['shift_hours'] ?? null, $_POST['shift_minutes'] ?? null);
            flash('success', 'Dodano zmianę wyrównawczą ' . $code . '.');
        } elseif ($action === 'save') {
            $raw   = $_POST['data'] ?? '';
            $input = json_decode(is_string($raw) ? $raw : '', true);
            if (!is_array($input)) {
                flash('error', 'Nie udało się odczytać zmian w grafiku. Spróbuj ponownie.');
            } else {
                save_schedule($sch, $input);
                flash('success', 'Grafik „' . $sch['label'] . '" zapisany pomyślnie!');
            }
        } elseif ($action === 'delete') {
            delete_schedule($sch);
            flash('success', 'Usunięto grafik: ' . $sch['label'] . '.');
            redirect($self);
        }
    } catch (Throwable $e) {
        $message = $e instanceof InvalidArgumentException || cfg('debug')
            ? ': ' . $e->getMessage()
            : '. Spróbuj ponownie.';
        flash('error', 'Błąd zapisu' . $message);
    }
    redirect($self . '?table=' . rawurlencode($sch['table']));
}

/* ---------- Wybór grafiku ---------- */
$schedules = list_schedules();
$requested = get('table');
$sch       = $requested !== '' ? find_schedule_by_table($requested) : null;

if ($requested !== '' && !$sch) {
    flash('error', 'Nie znaleziono wskazanego grafiku.');
    redirect($self);
}
if (!$sch) {
    $sch = find_schedule((int)date('Y'), (int)date('n')) ?: ($schedules[0] ?? null);
}

$topActions = '';
$editor     = null;
$employees  = [];

if ($sch) {
    $ld        = load_schedule($sch);
    $days      = $ld['days'];
    $employees = emp_all();
    $info      = month_days_info($sch['year'], $sch['month']);
    $norm      = work_norm($sch['year'], $sch['month']);
    $individualNorms = load_schedule_norms($sch);
    $isCurrent = $sch['year'] === (int)date('Y') && $sch['month'] === (int)date('n');
    $todayDay  = $isCurrent ? (int)date('j') : 0;

    $offDays = [];
    foreach ($info as $d => $i) {
        if ($i['weekend'] || $i['holiday']) {
            $offDays[] = $d;
        }
    }

    $empData = [];
    $empList = [];
    foreach ($employees as $emp) {
        $id  = (int)$emp['id'];
        $row = [];
        for ($d = 1; $d <= $days; $d++) {
            $row[] = $ld['data'][$id][$d] ?? '';
        }
        $empData[$id] = $row;
        $personNorm = $individualNorms[$id] ?? schedule_norm_breakdown((int)round($norm / DAILY_NORM_MINUTES), 12);
        $empList[]    = [
            'id' => $id,
            'name' => emp_full_name($emp),
            'norm' => $personNorm['minutes'],
            'workDays' => $personNorm['days'],
            'shiftHours' => $personNorm['shiftHours'],
            'shiftCount' => $personNorm['shiftCount'],
            'adjustmentMinutes' => $personNorm['adjustmentMinutes'],
        ];
    }
    $editor = [
        'days'      => $days,
        'norm'      => $norm,
        'employees' => $empList,
        'data'      => $empData,
        'shifts'    => shifts_for_js(),
        'offDays'   => $offDays,
    ];

    $topActions = '<a class="btn btn-secondary btn-sm" href="' . e(url('adminPanel/php/editScheduleNorms.php?table=' . rawurlencode($sch['table']))) . '">Normy pracowników</a>'
        . '<button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Drukuj</button>'
        . '<a class="btn btn-secondary btn-sm" href="' . e(url('export.php?type=csv&table=' . rawurlencode($sch['table']))) . '">Eksport CSV</a>';
}

page_start('Edytuj grafik', [
    'active'    => 'edit',
    'h1'        => $sch ? 'Grafik: ' . $sch['label'] : 'Edytuj grafik',
    'subtitle'  => 'Modyfikuj istniejące grafiki pracy',
    'actions'   => $topActions,
    'bodyClass' => 'schedule-print-page',
    'pageClass' => 'page-col',
]);
?>

<!-- Wybór grafiku -->
<div class="card fade-in no-print" style="padding:18px 24px">
    <div class="picker-bar">
        <div class="picker-left">
            <?php if ($sch):
                list($py, $pm) = shift_month($sch['year'], $sch['month'], -1);
                list($ny, $nm) = shift_month($sch['year'], $sch['month'], 1);
                $prev = find_schedule($py, $pm);
                $next = find_schedule($ny, $nm);
                $prevHref = $prev ? 'adminPanel/php/editSchedule.php?table=' . rawurlencode($prev['table']) : 'adminPanel/php/CreateSchedulePanel.php?y=' . $py . '&m=' . $pm;
                $nextHref = $next ? 'adminPanel/php/editSchedule.php?table=' . rawurlencode($next['table']) : 'adminPanel/php/CreateSchedulePanel.php?y=' . $ny . '&m=' . $nm;
            ?>
                <a class="btn btn-secondary btn-sm" href="<?= e(url($prevHref)) ?>"
                   title="<?= $prev ? 'Poprzedni grafik' : 'Utwórz grafik: ' . e(MONTHS_PL[$pm] . ' ' . $py) ?>">Poprzedni</a>
            <?php endif; ?>

            <label style="margin:0;white-space:nowrap;font-weight:600;color:var(--navy)" for="schedulePicker">Wybierz grafik:</label>
            <select id="schedulePicker" style="width:220px" onchange="if(this.value){location.href=this.value}">
                <?php if (!$sch): ?><option value="">-- Wybierz grafik --</option><?php endif; ?>
                <?php foreach ($schedules as $s): ?>
                    <option value="<?= e(url($self . '?table=' . rawurlencode($s['table']))) ?>"<?= ($sch && $s['table'] === $sch['table']) ? ' selected' : '' ?>>
                        <?= e($s['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($sch): ?>
                <a class="btn btn-secondary btn-sm" href="<?= e(url($nextHref)) ?>"
                   title="<?= $next ? 'Następny grafik' : 'Utwórz grafik: ' . e(MONTHS_PL[$nm] . ' ' . $ny) ?>">Następny</a>
            <?php endif; ?>
        </div>

        <div class="picker-actions">
            <a class="btn btn-primary btn-sm" href="<?= e(url('adminPanel/php/CreateSchedulePanel.php')) ?>">+ Nowy grafik</a>
            <?php if ($sch): ?>
            <form method="POST" style="display:inline"
                  data-confirm-title="Usunąć grafik?"
                  data-confirm="Grafik „<?= e($sch['label']) ?>” zostanie trwale usunięty razem ze wszystkimi wpisami. Tej operacji nie można cofnąć."
                  data-confirm-btn="Tak, usuń grafik">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="table" value="<?= e($sch['table']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">Usuń grafik</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$sch): ?>
    <div class="card fade-in">
        <div class="empty-state">
            Nie ma jeszcze żadnego grafiku.<br><br>
            <a class="btn btn-primary" href="<?= e(url('adminPanel/php/CreateSchedulePanel.php')) ?>">Stwórz pierwszy grafik</a>
        </div>
    </div>
<?php elseif (!$employees): ?>
    <div class="alert alert-warning">
        <span>Brak pracowników w bazie – dodaj ich, aby uzupełnić grafik.</span>
        <a class="btn btn-primary btn-sm alert-action" href="<?= e(url('adminPanel/php/manageEmployees.php')) ?>">Dodaj pracowników</a>
    </div>
<?php else: ?>

<div class="card editor-card fade-in">
    <div class="print-heading">Grafik pracy — <?= e($sch['label']) ?></div>
    <!-- Pasek narzędzi -->
    <div class="editor-toolbar">
        <?php foreach (SHIFT_GROUPS as $gKey => $gLabel): ?>
        <div class="toolbar-group" role="group" aria-label="<?= e($gLabel) ?>">
            <span class="toolbar-label"><?= e($gLabel) ?>:</span>
            <?php foreach (palette_shift_catalog() as $code => $i): if ($i['group'] !== $gKey) continue; ?>
                <button type="button" class="palette-btn" data-brush="<?= e($code) ?>" data-k="<?= e($i['key']) ?>"
                        title="<?= e(shift_desc($code)) ?><?= $i['hotkey'] ? ' - klawisz ' . e(strtoupper($i['hotkey'])) : '' ?>">
                    <?= e($code) ?><?php if ($i['hotkey']): ?> <kbd><?= e(strtoupper($i['hotkey'])) ?></kbd><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
        <span class="toolbar-sep"></span>
        <?php endforeach; ?>
        <div class="toolbar-group toolbar-actions">
            <button type="button" class="palette-btn erase" data-brush="" title="Gumka - czyści komórki (Delete)">Gumka</button>
        </div>
        <span class="toolbar-sep"></span>
        <div class="toolbar-group">
            <button type="button" class="btn btn-secondary btn-sm" id="btnUndo" title="Cofnij (Ctrl+Z)" disabled>Cofnij</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btnRedo" title="Ponów (Ctrl+Y)" disabled>Ponów</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btnClearAll" title="Wyczyść cały grafik">Wyczyść wszystko</button>
        </div>
        <span class="hint" style="margin-left:auto">Kliknij lub przeciągnij po komórkach. Strzałki + klawisz zmiany (podany na przycisku) = szybkie wpisywanie</span>
    </div>

    <div class="custom-shift-manager no-print">
        <div class="custom-shift-copy">
            <strong>Własny dyżur wyrównawczy</strong>
            <span>Wpisz czas, np. 7 godzin i 10 minut. Utworzony kod pojawi się w palecie dla tego miesiąca.</span>
        </div>
        <form method="POST" class="custom-shift-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_custom_shift">
            <input type="hidden" name="table" value="<?= e($sch['table']) ?>">
            <input type="hidden" name="pending_data" class="pending-data-field" value="">
            <label>Godziny <input type="number" name="shift_hours" min="0" max="11" value="0" required></label>
            <label>Minuty <input type="number" name="shift_minutes" min="0" max="59" value="0" required></label>
            <button type="submit" class="btn btn-secondary btn-sm">Dodaj dyżur</button>
        </form>
        <?php if (active_schedule_custom_shifts()): ?>
            <div class="custom-shift-list">
                <span>Dodane dla tego grafiku:</span>
                <?php foreach (active_schedule_custom_shifts() as $code => $shift): ?>
                    <?= shift_badge($code) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="editor-wrap">
        <table class="grid" id="grid">
            <thead>
                <tr class="r1">
                    <th class="corner" rowspan="2">Pracownik</th>
                    <?php for ($d = 1; $d <= $days; $d++):
                        $cls = trim(($info[$d]['weekend'] ? 'we ' : '') . ($info[$d]['holiday'] ? 'ho ' : '') . ($d === $todayDay ? 'is-today' : '')); ?>
                        <th class="<?= $cls ?>"<?= $info[$d]['holiday'] ? ' title="' . e($info[$d]['holiday']) . '"' : '' ?>><?= $d ?></th>
                    <?php endfor; ?>
                    <th class="sum-h" rowspan="2">Godziny<br><small style="font-weight:500;color:var(--gray-400)">norma indywidualna</small></th>
                    <th class="act-h" rowspan="2"></th>
                </tr>
                <tr class="r2">
                    <?php for ($d = 1; $d <= $days; $d++):
                        $cls = trim(($info[$d]['weekend'] ? 'we ' : '') . ($info[$d]['holiday'] ? 'ho ' : '') . ($d === $todayDay ? 'is-today' : '')); ?>
                        <th class="<?= $cls ?>"><?= e(DOW_SHORT[$info[$d]['dow']]) ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $n => $emp): $id = (int)$emp['id']; ?>
                <tr>
                    <td class="c-name" title="<?= e(emp_full_name($emp)) ?>"><?= e($emp['surname'] . ' ' . $emp['name']) ?></td>
                    <?php for ($d = 1; $d <= $days; $d++):
                        $cls = trim('cell ' . ($info[$d]['weekend'] ? 'we ' : '') . ($info[$d]['holiday'] ? 'ho ' : '') . ($d === $todayDay ? 'is-today' : '')); ?>
                        <td class="<?= $cls ?>" data-e="<?= $id ?>" data-d="<?= $d ?>" tabindex="<?= ($n === 0 && $d === 1) ? 0 : -1 ?>"></td>
                    <?php endfor; ?>
                    <td class="sum" data-e="<?= $id ?>"><span class="sum-h">0:00</span><small class="sum-norm"></small><small class="sum-c"></small></td>
                    <td class="act">
                        <button type="button" class="row-btn" data-act="fill" data-e="<?= $id ?>" title="Wypełnij dni robocze wybraną zmianą">Dni rob.</button>
                        <button type="button" class="row-btn" data-act="clear" data-e="<?= $id ?>" title="Wyczyść wiersz">Wyczyść</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="c-name">Obsada: dzień</td>
                    <?php for ($d = 1; $d <= $days; $d++): ?><td data-cov="day" data-d="<?= $d ?>">0</td><?php endfor; ?>
                    <td colspan="2"></td>
                </tr>
                <tr>
                    <td class="c-name">Obsada: noc</td>
                    <?php for ($d = 1; $d <= $days; $d++): ?><td data-cov="night" data-d="<?= $d ?>">0</td><?php endfor; ?>
                    <td colspan="2"></td>
                </tr>
                <tr>
                    <td class="c-name">Nieobecni</td>
                    <?php for ($d = 1; $d <= $days; $d++): ?><td data-cov="off" data-d="<?= $d ?>">·</td><?php endfor; ?>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <form method="POST" id="saveForm" class="save-bar">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="table" value="<?= e($sch['table']) ?>">
        <input type="hidden" name="data" id="dataField" value="">
        <div class="save-info">
            Edytujesz: <strong><?= e($sch['label']) ?></strong>
            &nbsp;·&nbsp; <?= count($employees) ?> pracowników
            &nbsp;·&nbsp; <?= (int)$days ?> dni
            &nbsp;·&nbsp; wymiar indywidualny
            &nbsp;<span class="dirty-badge" id="dirtyBadge">Niezapisane zmiany</span>
        </div>
        <div class="save-actions">
            <span class="hint no-print">Ctrl+S</span>
            <button type="submit" class="btn btn-primary" id="saveBtn">Zapisz zmiany</button>
        </div>
    </form>
</div>

<div class="legend no-print" style="padding:0 4px">
    <span class="legend-label">Legenda:</span>
    <?php foreach (palette_shift_catalog() as $code => $i): ?>
        <span style="display:inline-flex;align-items:center;gap:6px;margin-right:10px;font-size:12px;color:var(--gray-500)">
            <?= shift_badge($code) ?> <?= e(shift_desc($code)) ?>
        </span>
    <?php endforeach; ?>
</div>
<p class="hint no-print" style="padding:0 4px">
    Godziny liczone są do indywidualnej normy ustawionej dla pracownika w formularzu norm. Możesz wrócić do tego formularza w dowolnym momencie. Dyżur pod telefonem nie wlicza się do normy
    i jest podsumowywany osobno (pod liczbą godzin); jeśli pracownik zostanie wezwany, wpisz dla tego dnia właściwy dyżur.
</p>

<script type="application/json" id="editor-data"><?= json_attr($editor) ?></script>

<?php endif; ?>

<?php
page_end(['scripts' => $sch && $employees ? ['scheduleEditor.js'] : []]);
