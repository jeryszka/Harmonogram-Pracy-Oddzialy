<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$self = 'adminPanel/php/editScheduleNorms.php';
$requested = get('table');
$sch = $requested !== '' ? find_schedule_by_table($requested) : null;

if ($requested !== '' && !$sch) {
    flash('error', 'Nie znaleziono wskazanego grafiku.');
    redirect($self);
}

if (is_post()) {
    csrf_check();
    $sch = find_schedule_by_table(post('table'));
    if (!$sch) {
        flash('error', 'Nie znaleziono wskazanego grafiku.');
        redirect($self);
    }

    try {
        $employees = emp_all();
        $daysByEmployee = $_POST['work_days'] ?? null;
        $hoursByEmployee = $_POST['shift_hours'] ?? null;
        if (!is_array($daysByEmployee) || !is_array($hoursByEmployee)) {
            throw new InvalidArgumentException('Uzupełnij wymiar pracy dla wszystkich pracowników.');
        }

        $settings = [];
        foreach ($employees as $employee) {
            $id = (int)$employee['id'];
            $settings[$id] = [
                'days' => $daysByEmployee[$id] ?? null,
                'shiftHours' => $hoursByEmployee[$id] ?? null,
            ];
        }
        save_schedule_norms($sch, $settings);
        flash('success', 'Zapisano indywidualne wymiary pracy dla grafiku „' . $sch['label'] . '”.');
    } catch (Throwable $e) {
        $message = $e instanceof InvalidArgumentException || cfg('debug')
            ? ': ' . $e->getMessage()
            : '. Spróbuj ponownie.';
        flash('error', 'Nie udało się zapisać norm' . $message);
    }

    $target = post('after_save') === 'editor' ? 'adminPanel/php/editSchedule.php' : $self;
    redirect($target . '?table=' . rawurlencode($sch['table']));
}

$schedules = list_schedules();
if (!$sch) {
    $sch = find_schedule((int)date('Y'), (int)date('n')) ?: ($schedules[0] ?? null);
}

$employees = [];
$individualNorms = [];
$days = 0;
$topActions = '';
if ($sch) {
    load_schedule($sch);
    $employees = emp_all();
    $days = days_in_month($sch['year'], $sch['month']);
    $norm = work_norm($sch['year'], $sch['month']);
    $individualNorms = load_schedule_norms($sch);
    $topActions = '<a class="btn btn-primary btn-sm" href="' . e(url('adminPanel/php/editSchedule.php?table=' . rawurlencode($sch['table']))) . '">Edytuj grafik</a>';
}

page_start('Normy grafiku', [
    'active' => 'norms',
    'h1' => 'Wymiar pracy pracowników',
    'subtitle' => $sch ? 'Ustaw normy dla grafiku: ' . $sch['label'] : 'Wybierz grafik, aby ustawić normy pracowników',
    'actions' => $topActions,
    'pageClass' => 'page-col',
]);
?>

<div class="card fade-in" style="padding:18px 24px">
    <div class="picker-bar">
        <div class="picker-left">
            <label for="schedulePicker" style="margin:0;white-space:nowrap;font-weight:600;color:var(--navy)">Wybierz grafik:</label>
            <select id="schedulePicker" style="width:240px" onchange="if(this.value){location.href=this.value}">
                <?php if (!$sch): ?><option value="">-- Wybierz grafik --</option><?php endif; ?>
                <?php foreach ($schedules as $schedule): ?>
                    <option value="<?= e(url($self . '?table=' . rawurlencode($schedule['table']))) ?>"<?= ($sch && $schedule['table'] === $sch['table']) ? ' selected' : '' ?>>
                        <?= e($schedule['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<?php if (!$sch): ?>
    <div class="card fade-in">
        <div class="empty-state">
            Nie ma jeszcze żadnego grafiku.<br><br>
            <a class="btn btn-primary" href="<?= e(url('adminPanel/php/CreateSchedulePanel.php')) ?>">Utwórz pierwszy grafik</a>
        </div>
    </div>
<?php elseif (!$employees): ?>
    <div class="alert alert-warning">
        <span>Brak pracowników w bazie – dodaj ich, aby ustawić indywidualne normy.</span>
        <a class="btn btn-primary btn-sm alert-action" href="<?= e(url('adminPanel/php/manageEmployees.php')) ?>">Dodaj pracowników</a>
    </div>
<?php else: ?>
    <div class="card fade-in schedule-setup-card">
        <form method="POST" id="scheduleNormsForm" class="schedule-norm-form">
            <?= csrf_field() ?>
            <input type="hidden" name="table" value="<?= e($sch['table']) ?>">

            <div class="setup-section-heading">
                <h2>Indywidualny wymiar na <?= e($sch['label']) ?></h2>
                <p>Każdy dzień to 7 h 35 min normy. Zmiany możesz zapisywać tutaj i wracać do siatki grafiku w dowolnej chwili.</p>
            </div>

            <div class="setup-table-wrap">
                <table class="setup-table">
                    <thead>
                        <tr><th>Pracownik</th><th>Dni pracy</th><th>Rodzaj dyżuru</th><th>Wymagana norma</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $employee):
                        $id = (int)$employee['id'];
                        $fallbackDays = max(1, min($days, (int)round($norm / DAILY_NORM_MINUTES)));
                        $settings = $individualNorms[$id] ?? schedule_norm_breakdown($fallbackDays, 12);
                    ?>
                        <tr>
                            <td class="setup-employee"><?= e(emp_full_name($employee)) ?></td>
                            <td><input class="setup-days" type="number" name="work_days[<?= $id ?>]" min="1" max="<?= (int)$days ?>" value="<?= (int)$settings['days'] ?>" required aria-label="Liczba dni pracy: <?= e(emp_full_name($employee)) ?>"></td>
                            <td>
                                <select class="setup-hours" name="shift_hours[<?= $id ?>]" aria-label="Rodzaj dyżuru: <?= e(emp_full_name($employee)) ?>">
                                    <option value="8"<?= $settings['shiftHours'] === 8 ? ' selected' : '' ?>>Dyżury 8-godzinne</option>
                                    <option value="12"<?= $settings['shiftHours'] === 12 ? ' selected' : '' ?>>Dyżury 12-godzinne</option>
                                </select>
                            </td>
                            <td class="setup-result"><strong class="setup-total"></strong><span class="setup-breakdown"></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="hint setup-hint" style="margin-top:14px">Wybrany tryb określa liczbę pełnych dyżurów oraz czas do wyrównania. Pracownik zobaczy te wartości w swoim panelu.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="submit" name="after_save" value="stay" class="btn btn-secondary">Zapisz normy</button>
                <button type="submit" name="after_save" value="editor" class="btn btn-primary">Zapisz i wróć do edycji grafiku</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php page_end(['scripts' => $sch && $employees ? ['scheduleNorms.js'] : []]); ?>
