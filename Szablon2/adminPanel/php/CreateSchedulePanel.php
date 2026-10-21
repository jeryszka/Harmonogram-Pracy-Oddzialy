<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$self = 'adminPanel/php/CreateSchedulePanel.php';
$employees = emp_all();

if (is_post()) {
    csrf_check();
    $year = (int)post('year');
    $month = (int)post('month');
    $copy = post('copy_from');

    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        flash('error', 'Podaj poprawny rok i miesiąc.');
        redirect($self);
    }
    if (find_schedule($year, $month)) {
        flash('error', 'Grafik ' . MONTHS_PL[$month] . ' ' . $year . ' już istnieje.');
        redirect($self . '?y=' . $year . '&m=' . $month);
    }
    if (!$employees) {
        flash('error', 'Najpierw dodaj pracowników – grafik jest tworzony dla wszystkich pracowników.');
        redirect('adminPanel/php/manageEmployees.php');
    }

    $monthDays = days_in_month($year, $month);
    $postedDays = $_POST['work_days'] ?? [];
    $postedHours = $_POST['shift_hours'] ?? [];
    if (!is_array($postedDays) || !is_array($postedHours)) {
        flash('error', 'Nie udało się odczytać wymiarów pracowników. Wypełnij formularz ponownie.');
        redirect($self . '?y=' . $year . '&m=' . $month);
    }
    $settings = [];
    foreach ($employees as $emp) {
        $id = (int)$emp['id'];
        $days = filter_var($postedDays[$id] ?? null, FILTER_VALIDATE_INT);
        $hours = filter_var($postedHours[$id] ?? null, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > $monthDays || !in_array($hours, [8, 12], true)) {
            flash('error', 'Uzupełnij liczbę dni oraz wybierz dyżury 8- albo 12-godzinne dla każdej osoby.');
            redirect($self . '?y=' . $year . '&m=' . $month);
        }
        $settings[$id] = ['days' => $days, 'shiftHours' => $hours];
    }

    $source = $copy !== '' ? find_schedule_by_table($copy) : null;
    try {
        ensure_schedule_norms_table();
        $schedule = create_schedule($year, $month, $source);
        save_schedule_norms($schedule, $settings);
        flash('success', 'Utworzono grafik: ' . $schedule['label'] . '. Uzupełnij go poniżej.');
        redirect('adminPanel/php/editSchedule.php?table=' . rawurlencode($schedule['table']));
    } catch (Throwable $e) {
        flash('error', 'Nie udało się utworzyć grafiku' . (cfg('debug') ? ': ' . $e->getMessage() : '.'));
        redirect($self);
    }
}

list($defaultYear, $defaultMonth) = [(int)date('Y'), (int)date('n')];
for ($i = 0; $i < 24 && find_schedule($defaultYear, $defaultMonth); $i++) {
    list($defaultYear, $defaultMonth) = shift_month($defaultYear, $defaultMonth, 1);
}
$year = (int)get('y', $defaultYear);
$month = (int)get('m', $defaultMonth);
if ($year < 2000 || $year > 2100) $year = $defaultYear;
if ($month < 1 || $month > 12) $month = $defaultMonth;

$monthDays = days_in_month($year, $month);
$defaultDays = max(1, min($monthDays, (int)round(work_norm($year, $month) / DAILY_NORM_MINUTES)));
$schedules = list_schedules();

page_start('Nowy grafik', [
    'active' => 'new',
    'h1' => 'Nowy grafik pracy',
    'subtitle' => 'Ustaw indywidualny wymiar i rodzaj dyżurów dla każdej osoby',
    'pageClass' => 'page-col',
]);
?>

<div class="card fade-in schedule-setup-card">
    <form method="POST" id="createScheduleForm" class="schedule-norm-form">
        <?= csrf_field() ?>

        <div class="card-header">
            <div>
                <div class="card-title">Okres grafiku</div>
                <div class="card-subtitle">Wymiar każdej osoby zostanie zapisany osobno dla tego miesiąca.</div>
            </div>
        </div>

        <div class="form-row schedule-period">
            <div class="form-group">
                <label for="year">Rok</label>
                <input type="number" id="year" name="year" min="2000" max="2100" value="<?= $year ?>" required>
            </div>
            <div class="form-group">
                <label for="month">Miesiąc</label>
                <select name="month" id="month" required>
                    <?php foreach (MONTHS_PL as $n => $label): ?>
                        <option value="<?= $n ?>"<?= $n === $month ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group schedule-copy">
                <label for="copy_from">Zawartość początkowa</label>
                <select name="copy_from" id="copy_from">
                    <option value="">Pusty grafik</option>
                    <?php foreach ($schedules as $schedule): ?>
                        <option value="<?= e($schedule['table']) ?>">Skopiuj zmiany z: <?= e($schedule['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="hint setup-hint">Przenoszone są wyłącznie wpisane dyżury robocze. Wymiary pracy ustawiasz poniżej dla nowego miesiąca.</p>

        <div class="setup-section-heading">
            <div>
                <h2>Wymiar pracy według pracownika</h2>
                <p>Jeden przydzielony dzień odpowiada 7 h 35 min. Wybierz też, czy podstawą grafiku są dyżury 8- czy 12-godzinne.</p>
            </div>
        </div>

        <?php if (!$employees): ?>
            <div class="empty-state">Dodaj pracowników, aby utworzyć grafik.</div>
        <?php else: ?>
            <div class="setup-table-wrap">
                <table class="setup-table">
                    <thead>
                        <tr><th>Pracownik</th><th>Dni pracy</th><th>Rodzaj dyżuru</th><th>Wymagana norma</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $employee): $id = (int)$employee['id']; ?>
                        <tr>
                            <td class="setup-employee"><?= e(emp_full_name($employee)) ?></td>
                            <td><input class="setup-days" type="number" name="work_days[<?= $id ?>]" min="1" max="<?= $monthDays ?>" value="<?= $defaultDays ?>" required aria-label="Liczba dni pracy: <?= e(emp_full_name($employee)) ?>"></td>
                            <td>
                                <select class="setup-hours" name="shift_hours[<?= $id ?>]" aria-label="Rodzaj dyżuru: <?= e(emp_full_name($employee)) ?>">
                                    <option value="8">Dyżury 8-godzinne</option>
                                    <option value="12" selected>Dyżury 12-godzinne</option>
                                </select>
                            </td>
                            <td class="setup-result"><strong class="setup-total"></strong><span class="setup-breakdown"></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning setup-warning">
            <span>Dyżur pod telefonem nie wlicza się do normy. Godziny wyrównawcze uzupełnia się zmianami wyrównawczymi w edytorze grafiku.</span>
        </div>
        <button type="submit" class="btn btn-primary">Stwórz grafik</button>
    </form>
</div>

<?php page_end(['scripts' => ['scheduleNorms.js']]); ?>
