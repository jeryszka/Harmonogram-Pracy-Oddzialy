<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();
ensure_schema();

/** Miniplakietki z liczbą zmian wg kodu. */
function shift_minis(array $counts, ?array $catalog = null)
{
    $html = '';
    foreach ($catalog ?? active_shift_catalog() as $code => $info) {
        if (empty($info['work']) || empty($counts[$code])) {
            continue;
        }
        $html .= '<span class="shift-mini ' . e($info['key']) . '" title="' . e($info['label']) . '">'
            . e($code) . ' ' . (int)$counts[$code] . '</span>';
    }
    return $html;
}

$error       = null;
$employees   = [];
$schedStats  = [];
$totals      = ['shifts' => 0, 'counts' => [], 'catalog' => []];
$todayGroups = [];
$todayAbsent = [];
$week        = [];
$defaultPw   = null;
$today       = new DateTimeImmutable('today');
$curY        = (int)$today->format('Y');
$curM        = (int)$today->format('n');

try {
    $employees = emp_all();
    $empMap    = emp_map();

    // Statystyki każdego grafiku
    foreach (list_schedules() as $sch) {
        $ld     = load_schedule($sch);
        $catalog = active_shift_catalog();
        $counts = [];
        $work   = 0;
        foreach ($ld['data'] as $row) {
            foreach ($row as $code) {
                if ($code !== '' && !empty(active_shift_catalog()[$code]['work'])) {
                    $counts[$code] = ($counts[$code] ?? 0) + 1;
                    $work++;
                }
            }
        }
        $schedStats[] = [
            'sch'       => $sch,
            'days'      => $ld['days'],
            'employees' => count($ld['data']),
            'work'      => $work,
            'counts'    => $counts,
            'catalog'   => $catalog,
        ];
        $totals['catalog'] = array_merge($totals['catalog'], $catalog);
        $totals['shifts'] += $work;
        foreach ($counts as $code => $n) {
            $totals['counts'][$code] = ($totals['counts'][$code] ?? 0) + $n;
        }
    }

    // Kto dziś pracuje
    foreach (shifts_on_date($today) as $empId => $code) {
        if (!isset($empMap[$empId])) {
            continue;
        }
        if (!empty(active_shift_catalog()[$code]['work'])) {
            $todayGroups[$code][] = $empMap[$empId];
        } else {
            $todayAbsent[] = ['emp' => $empMap[$empId], 'code' => $code];
        }
    }

    // Obsada na najbliższe 7 dni
    for ($i = 0; $i < 7; $i++) {
        $date = $today->modify('+' . $i . ' days');
        $map  = shifts_on_date($date);
        $row  = ['date' => $date, 'day' => 0, 'night' => 0, 'off' => 0, 'has' => (bool)find_schedule((int)$date->format('Y'), (int)$date->format('n'))];
        foreach ($map as $code) {
            cover_add($row, $code);
        }
        $week[] = $row;
    }

    if (emp_has_pw_flag()) {
        $defaultPw = count(array_filter($employees, 'emp_uses_default'));
    }
} catch (Throwable $e) {
    $error = cfg('debug') ? $e->getMessage() : 'Nie udało się odczytać danych z bazy.';
}

$last = null;
foreach ($employees as $emp) {
    if ($last === null || $emp['id'] > $last['id']) {
        $last = $emp;
    }
}

$currentSchedule = find_schedule($curY, $curM);
if ($currentSchedule) load_schedule($currentSchedule);
list($nextY, $nextM) = shift_month($curY, $curM, 1);
$nextSchedule    = find_schedule($nextY, $nextM);
$todayShiftCount = 0;
foreach ($todayGroups as $g) {
    $todayShiftCount += count($g);
}

page_start('Panel Administratora', [
    'active'   => 'dashboard',
    'h1'       => 'Zarządzaj oddziałem',
    'subtitle' => 'Twórz grafiki, zarządzaj pracownikami, edytuj grafiki',
    'pageClass' => 'page-col',
]);
?>

<?php if (!$currentSchedule && !$error): ?>
<div class="alert alert-warning fade-in">
    <span>Brak grafiku na bieżący miesiąc (<?= e(MONTHS_PL[$curM] . ' ' . $curY) ?>).</span>
    <a class="btn btn-primary btn-sm alert-action" href="<?= e(url('adminPanel/php/CreateSchedulePanel.php?y=' . $curY . '&m=' . $curM)) ?>">Utwórz grafik</a>
</div>
<?php elseif (!$nextSchedule && !$error && (int)$today->format('j') >= 20): ?>
<div class="alert alert-info fade-in">
    <span>Zbliża się koniec miesiąca – nie ma jeszcze grafiku na <?= e(MONTHS_PL[$nextM] . ' ' . $nextY) ?>.</span>
    <a class="btn btn-primary btn-sm alert-action" href="<?= e(url('adminPanel/php/CreateSchedulePanel.php?y=' . $nextY . '&m=' . $nextM)) ?>">Utwórz grafik</a>
</div>
<?php endif; ?>

<?php if ($defaultPw): ?>
<div class="alert alert-info fade-in">
    <span><?= (int)$defaultPw ?> <?= $defaultPw === 1 ? 'pracownik nie zmienił' : 'pracowników nie zmieniło' ?> jeszcze hasła domyślnego.</span>
    <a class="btn btn-secondary btn-sm alert-action" href="<?= e(url('adminPanel/php/manageEmployees.php')) ?>">Zobacz</a>
</div>
<?php endif; ?>

<!-- KARTA STATYSTYK -->
<div class="stats-card fade-in">
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div class="card-title">Statystyki oddziału</div>
            <div class="card-subtitle">Przegląd pracowników i harmonogramów pracy</div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-top:16px">Błąd bazy danych: <?= e($error) ?></div>
    <?php else: ?>

    <div class="stats-grid">
        <div class="stat-tile accent">
            <span class="stat-label">Pracownicy</span>
            <span class="stat-value"><?= count($employees) ?></span>
            <?php if ($last): ?>
            <div class="last-emp-badge">
                <div class="avatar-xs"><?= e(emp_initials($last)) ?></div>
                Ostatnio: <?= e(emp_full_name($last)) ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="stat-tile accent">
            <span class="stat-label">Grafiki</span>
            <span class="stat-value"><?= count($schedStats) ?></span>
            <span class="stat-sub">Ostatni: <?= e($schedStats ? $schedStats[0]['sch']['label'] : '—') ?></span>
        </div>

        <div class="stat-tile">
            <span class="stat-label">Wszystkich zmian</span>
            <span class="stat-value"><?= (int)$totals['shifts'] ?></span>
            <div class="shift-mini-row"><?= shift_minis($totals['counts'], $totals['catalog']) ?></div>
        </div>

        <div class="stat-tile">
            <span class="stat-label">Zmiany dziś</span>
            <span class="stat-value"><?= $currentSchedule ? $todayShiftCount : '—' ?></span>
            <span class="stat-sub"><?= $currentSchedule ? e(MONTHS_PL[$curM] . ' ' . $curY) : 'Brak grafiku na bieżący miesiąc' ?></span>
        </div>
    </div>

    <div class="schedules-section">
        <div class="schedules-toggle" id="schedulesToggle">
            <span class="schedules-toggle-label">
                Lista grafików
                <span class="count-badge"><?= count($schedStats) ?></span>
            </span>
            <span class="toggle-arrow" id="toggleArrow">Pokaż / ukryj</span>
        </div>

        <div class="schedules-list" id="schedulesList">
            <?php if (!$schedStats): ?>
                <p class="empty-state">Brak grafików w bazie danych.</p>
            <?php else: foreach ($schedStats as $s): ?>
                <div class="schedule-row">
                    <span class="schedule-row-name"><?= e($s['sch']['label']) ?></span>
                    <div class="schedule-row-meta">
                        <span><?= (int)$s['days'] ?> dni</span>
                        <span><?= (int)$s['employees'] ?> pracowników</span>
                        <span><?= (int)$s['work'] ?> zmian</span>
                    </div>
                    <div class="schedule-row-shifts"><?= shift_minis($s['counts'], $s['catalog']) ?></div>
                    <div style="display:flex;gap:6px">
                        <a class="btn btn-secondary btn-sm" href="<?= e(url('adminPanel/php/editSchedule.php?table=' . rawurlencode($s['sch']['table']))) ?>">Edytuj</a>
                        <a class="btn btn-secondary btn-sm" href="<?= e(url('export.php?type=csv&table=' . rawurlencode($s['sch']['table']))) ?>" title="Pobierz do Excela">CSV</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php if (!$error): ?>
<div class="cards-3">
    <!-- DZIŚ W PRACY -->
    <div class="card fade-in">
        <div class="card-header">
            <div>
                <div class="card-title">Dziś w pracy</div>
                <div class="card-subtitle"><?= e(DOW_LONG[(int)$today->format('N')] . ', ' . pl_date($today)) ?></div>
            </div>
        </div>
        <?php if (!$currentSchedule): ?>
            <div class="empty-state">Brak grafiku na bieżący miesiąc.</div>
        <?php elseif (!$todayGroups && !$todayAbsent): ?>
            <div class="empty-state">Nikt nie ma dziś wpisanej zmiany.</div>
        <?php else: ?>
            <div class="today-list">
                <?php foreach (active_shift_catalog() as $code => $info): if (empty($todayGroups[$code])) continue; ?>
                    <div>
                        <div class="today-group-title"><?= shift_badge($code) ?> <?= e($info['label']) ?> · <?= count($todayGroups[$code]) ?></div>
                        <div class="chip-row">
                            <?php foreach ($todayGroups[$code] as $emp): ?>
                                <span class="chip"><span class="avatar-xs"><?= e(emp_initials($emp)) ?></span><?= e(emp_full_name($emp)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($todayAbsent): ?>
                    <div>
                        <div class="today-group-title">Nieobecni · <?= count($todayAbsent) ?></div>
                        <div class="chip-row">
                            <?php foreach ($todayAbsent as $a): ?>
                                <span class="chip"><?= shift_badge($a['code']) ?><?= e(emp_full_name($a['emp'])) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- OBSADA 7 DNI -->
    <div class="card fade-in">
        <div class="card-header">
            <div>
                <div class="card-title">Obsada na 7 dni</div>
                <div class="card-subtitle">Liczba osób na zmianie dziennej i nocnej</div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="cov-table">
                <thead><tr><th>Dzień</th><th>Dzień</th><th>Noc</th><th>Nieob.</th></tr></thead>
                <tbody>
                <?php foreach ($week as $w): ?>
                    <tr>
                        <td><?= e(DOW_SHORT[(int)$w['date']->format('N')] . ' ' . $w['date']->format('j.m')) ?></td>
                        <?php if (!$w['has']): ?>
                            <td colspan="3" style="color:var(--gray-400)">brak grafiku</td>
                        <?php else: ?>
                            <td class="<?= $w['day'] === 0 ? 'cov-low' : '' ?>"><?= (int)$w['day'] ?></td>
                            <td class="<?= $w['night'] === 0 ? 'cov-low' : '' ?>"><?= (int)$w['night'] ?></td>
                            <td><?= $w['off'] ?: '·' ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SZYBKIE AKCJE -->
    <div class="card fade-in">
        <div class="card-header">
            <div>
                <div class="card-title">Szybkie akcje</div>
                <div class="card-subtitle">Najczęstsze czynności</div>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <?php if ($currentSchedule): ?>
                <a class="btn btn-primary" href="<?= e(url('adminPanel/php/editSchedule.php?table=' . rawurlencode($currentSchedule['table']))) ?>">Edytuj grafik: <?= e($currentSchedule['label']) ?></a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url('adminPanel/php/CreateSchedulePanel.php?y=' . $curY . '&m=' . $curM)) ?>">Stwórz grafik: <?= e(MONTHS_PL[$curM] . ' ' . $curY) ?></a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="<?= e(url('adminPanel/php/CreateSchedulePanel.php')) ?>">Stwórz nowy grafik</a>
            <a class="btn btn-secondary" href="<?= e(url('adminPanel/php/manageEmployees.php')) ?>">Dodaj / usuń pracownika</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
page_end(['inline' => "
document.getElementById('schedulesToggle').addEventListener('click', function () {
    document.getElementById('schedulesList').classList.toggle('open');
    
});
"]);
