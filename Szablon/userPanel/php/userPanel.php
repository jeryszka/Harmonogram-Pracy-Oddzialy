<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user  = require_employee();
$empId = (int)$user['id'];

$emp = emp_find($empId);
if (!$emp) { // konto zostało usunięte
    logout_user();
    redirect('index.php');
}

list($year, $month) = request_month();
$self   = 'userPanel/php/userPanel.php';
$today  = new DateTimeImmutable('today');
$todayY = (int)$today->format('Y');
$todayM = (int)$today->format('n');
$todayD = (int)$today->format('j');

$sch     = find_schedule($year, $month);
$days    = days_in_month($year, $month);
$info    = month_days_info($year, $month);
$norm    = work_norm($year, $month);
$ld      = $sch ? load_schedule($sch) : null;
$personNorms = $sch ? load_schedule_norms($sch) : [];
$personNorm = $personNorms[$empId]['minutes'] ?? $norm;
$myRow   = $ld['data'][$empId] ?? [];
$summary = schedule_summary($myRow, $days);
$normSetup = $personNorms[$empId] ?? schedule_norm_breakdown((int)round($norm / DAILY_NORM_MINUTES), 12);
$coreShiftCodes = $normSetup['shiftHours'] === 8 ? ['R', 'P', 'N8'] : ['D', 'N'];
$scheduledCoreShifts = 0;
foreach ($coreShiftCodes as $code) $scheduledCoreShifts += $summary['counts'][$code] ?? 0;
if ($normSetup['shiftHours'] === 12) $scheduledCoreShifts += 2 * ($summary['counts']['D/N'] ?? 0);
$scheduledAdjustment = 0;
foreach (active_shift_catalog() as $code => $shift) {
    if ($shift['group'] === 'adj') {
        $scheduledAdjustment += $shift['minutes'] * ($summary['counts'][$code] ?? 0);
    }
}
$empMap  = emp_map();

// Dane do podglądu dnia (kto jeszcze pracuje)
$dayData = [];
for ($d = 1; $d <= $days; $d++) {
    $team = [];
    if ($ld) {
        foreach ($ld['data'] as $id => $row) {
            $c = $row[$d] ?? '';
            if ($id !== $empId && $c !== '' && isset($empMap[$id])) {
                $team[] = ['n' => emp_full_name($empMap[$id]), 'c' => $c];
            }
        }
    }
    $dayData[$d] = ['mine' => $myRow[$d] ?? '', 'team' => $team];
}

$upcoming = upcoming_shifts_for($empId, 8);
$next     = $upcoming[0] ?? null;
if ($sch) activate_schedule_shifts($sch);

$startDow = (int)date('N', strtotime(sprintf('%04d-%02d-01', $year, $month))) - 1;
$isCurrentMonth = ($year === $todayY && $month === $todayM);

page_start('Panel użytkownika', [
    'active'       => 'dashboard',
    'h1'           => 'Witaj, ' . $emp['name'],
    'subtitleHtml' => 'Grafik: <strong>' . e(MONTHS_PL[$month] . ' ' . $year) . '</strong>',
    'pageClass'    => 'page-col',
]);
?>

<div class="cards-row">

    <?php if ($next):
        $diff = (int)$today->diff($next['date'])->days;
        $when = $diff === 0 ? 'Dziś' : ($diff === 1 ? 'Jutro' : 'Za ' . $diff . ' dni');
        $ni   = $next['info'] ?? shift_info($next['code']);
    ?>
    <div class="next-shift fade-in">
        <div class="ns-badge"><?= e($next['code']) ?></div>
        <div>
            <div class="ns-label">Twoja najbliższa zmiana</div>
            <div class="ns-title"><?= e($when) ?> · <?= e(DOW_LONG[(int)$next['date']->format('N')] . ', ' . pl_date($next['date'], false)) ?></div>
            <div class="ns-sub"><?= e($ni['label'] . ($ni['time'] !== '' ? ' (' . $ni['time'] . ')' : '')) ?></div>
        </div>
        <a class="btn btn-secondary btn-sm ns-action no-print" href="<?= e(url('export.php?type=ics&scope=upcoming')) ?>" title="Pobierz plik do kalendarza w telefonie / Outlooka">Dodaj do kalendarza</a>
    </div>
    <?php endif; ?>

    <!-- KALENDARZ -->
    <div class="card fade-in">
        <div class="card-header" style="flex-wrap:wrap">
            <div>
                <div class="card-title">Mój kalendarz</div>
                <div class="card-subtitle">Kliknij w dzień, aby zobaczyć szczegóły</div>
            </div>
            <?= month_nav_html(url($self), $year, $month) ?>
        </div>

        <?php if (!$sch): ?>
            <div class="empty-state">Grafik na <?= e(MONTHS_PL[$month] . ' ' . $year) ?> nie został jeszcze utworzony.</div>
        <?php endif; ?>

        <div class="calendar-grid" id="calendar">
            <?php foreach (DOW_SHORT as $n => $h): ?>
                <div class="cal-header <?= $n >= 6 ? 'weekend' : '' ?>"><?= e($h) ?></div>
            <?php endforeach; ?>

            <?php for ($b = 0; $b < $startDow; $b++): ?><div class="cal-day blank"></div><?php endfor; ?>

            <?php for ($d = 1; $d <= $days; $d++):
                $code  = $myRow[$d] ?? '';
                $cls   = 'cal-day';
                if ($code !== '') {
                    $cls .= ' has-shift solid-' . shift_info($code)['key'];
                }
                if ($info[$d]['weekend']) $cls .= ' weekend';
                if ($info[$d]['holiday']) $cls .= ' holiday';
                if ($isCurrentMonth && $d === $todayD) $cls .= ' today-marker';
                if (sprintf('%04d%02d%02d', $year, $month, $d) < $today->format('Ymd')) $cls .= ' past';
            ?>
                <div class="<?= $cls ?>" data-d="<?= $d ?>" tabindex="0" role="button"
                     <?= $info[$d]['holiday'] ? 'title="' . e($info[$d]['holiday']) . '"' : '' ?>>
                    <?= $d ?>
                    <?php if ($code !== ''): ?><span class="cal-code"><?= e($code) ?></span><?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>

        <div class="day-detail" id="dayDetail">Kliknij dzień w kalendarzu, aby zobaczyć szczegóły i to, kto jeszcze wtedy pracuje.</div>

        <div class="legend-section">
            <div class="legend-title">Twój bilans</div>
            <div class="shift-mini-row">
                <?php foreach (active_shift_catalog() as $code => $i):
                    $cnt = $summary['counts'][$code] ?? 0;
                    if ($cnt > 0): ?>
                    <div class="shift-mini <?= e($i['key']) ?>" title="<?= e(shift_desc($code)) ?>"><?= e($code) ?>: <?= (int)$cnt ?></div>
                <?php endif; endforeach; ?>
                <?php if (!$summary['counts']): ?><span class="hint">Brak wpisanych zmian w tym miesiącu.</span><?php endif; ?>
            </div>

            <?php
            $pct  = $personNorm > 0 ? min(100, (int)round($summary['minutes'] / $personNorm * 100)) : 0;
            $diff = $summary['minutes'] - $personNorm;
            ?>
            <div class="hours-box">
                <div class="hb-row">
                    <span class="hb-label">Godziny pracy:</span>
                    <span class="hb-value"><?= e(format_hours_text($summary['minutes'])) ?></span>
                </div>
                <div class="progress"><span class="<?= $summary['minutes'] > $personNorm ? 'over' : '' ?>" style="width:<?= $pct ?>%"></span></div>
                <div class="hb-note">
                    Wymiar czasu pracy w tym miesiącu: <?= e(format_hours_text($personNorm)) ?> (<?= (int)$normSetup['days'] ?> przydzielonych dni × 7 h 35 min).
                    <?php if ($personNorm > 0): ?>
                        <?= $diff > 0 ? 'Nadwyżka: ' . e(format_hours_text($diff)) . '.' : ($diff < 0 ? 'Do normy brakuje: ' . e(format_hours_text(-$diff)) . '.' : 'Norma wypełniona.') ?>
                    <?php endif; ?>
                </div>
                <div class="hb-note">
                    Twój przydział: <?= (int)$normSetup['shiftCount'] ?> dyżurów po <?= (int)$normSetup['shiftHours'] ?> h<?= $normSetup['adjustmentMinutes'] ? ' oraz ' . e(format_hours_text($normSetup['adjustmentMinutes'])) . ' do wyrównania' : '' ?>.
                    Wpisano: <?= (int)$scheduledCoreShifts ?>/<?= (int)$normSetup['shiftCount'] ?> takich dyżurów; zmiany wyrównawcze: <?= e(format_hours_text($scheduledAdjustment)) ?><?= $normSetup['adjustmentMinutes'] ? ' / ' . e(format_hours_text($normSetup['adjustmentMinutes'])) : '' ?>.
                </div>
                <?php if ($summary['night'] > 0): ?>
                    <div class="hb-note">Godziny nocne (21:00-7:00): <?= e(format_hours_text($summary['night'])) ?></div>
                <?php endif; ?>
                <?php if ($summary['oncall'] > 0): ?>
                    <div class="hb-note">Dyżury pod telefonem: <?= e(format_hours_text($summary['oncall'])) ?> (nie wliczają się do normy; gotowość płatna 50%)</div>
                <?php endif; ?>
            </div>

            <?php if ($sch): ?>
            <div style="margin-top:14px" class="no-print">
                <a class="btn btn-secondary btn-sm" href="<?= e(url('export.php?type=ics&y=' . $year . '&m=' . $month)) ?>">Eksportuj miesiąc do kalendarza (.ics)</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- NADCHODZĄCE DYŻURY -->
    <div class="card fade-in">
        <div class="card-header">
            <div>
                <div class="card-title">Nadchodzące dyżury</div>
                <div class="card-subtitle">Twoje najbliższe dni w pracy</div>
            </div>
        </div>

        <?php if (!$upcoming): ?>
            <div class="empty-state">Brak zaplanowanych zmian w najbliższym czasie.</div>
        <?php else: ?>
            <div class="shift-list">
                <?php foreach ($upcoming as $i => $s):
                    $inf = $s['info'] ?? shift_info($s['code']);
                    $description = $inf['label'] . ($inf['time'] !== '' ? ' (' . $inf['time'] . ')' : '');
                    $dow = (int)$s['date']->format('N');
                ?>
                    <div class="shift-item <?= $i === 0 ? 'first' : '' ?>">
                        <div class="shift-badge-circle" style="background:<?= e($inf['bg']) ?>; color:<?= e($inf['color']) ?>;"><?= e($s['code']) ?></div>
                        <div>
                            <div style="font-weight:700; color:var(--navy);"><?= e(DOW_SHORT[$dow] . ', ' . pl_date($s['date'], false)) ?></div>
                            <div style="font-size:11px; color:var(--gray-500);"><?= e($description) ?></div>
                        </div>
                        <?php if ($i === 0): ?>
                            <span style="margin-left:auto; font-size:10px; font-weight:700; color:var(--teal); text-transform:uppercase;">Najbliższa</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top:18px" class="no-print">
            <a class="btn btn-secondary btn-sm" href="<?= e(url('userPanel/php/seeSchedule.php?y=' . $year . '&m=' . $month)) ?>">Zobacz grafik całego zespołu</a>
        </div>
    </div>

    <!-- KONTO -->
    <div class="card fade-in" style="display:flex;flex-direction:column">
        <div class="card-header">
            <div>
                <div class="card-title">Twoje konto</div>
                <div class="card-subtitle">Dane zalogowanego użytkownika</div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:20px; margin-bottom:25px;">
            <div class="profile-avatar"><?= e(mb_strtoupper(mb_substr($emp['name'], 0, 1))) ?></div>
            <div>
                <div style="font-size:18px; font-weight:700; color:var(--navy);"><?= e(emp_full_name($emp)) ?></div>
                <div style="color:var(--gray-500); font-size:14px;">Pracownik oddziału</div>
            </div>
        </div>

        <div style="background:var(--gray-50); border-radius:12px; padding:20px; border:1px solid var(--gray-200);">
            <div class="kv"><span class="k">Login systemowy</span><div class="v"><?= e($emp['login']) ?></div></div>
            <div class="kv"><span class="k">ID pracownika</span><div class="v">#<?= (int)$emp['id'] ?></div></div>
            <div class="kv">
                <span class="k">Status konta</span>
                <div class="v" style="color:#10b981; display:flex; align-items:center; gap:6px;">
                    Aktywne
                </div>
            </div>
        </div>

        <div style="margin-top:auto; padding-top:20px;">
            <a href="<?= e(url('userPanel/php/changePassword.php')) ?>" class="btn btn-primary btn-block">Zmień hasło</a>
        </div>
    </div>
</div>

<script type="application/json" id="day-data"><?= json_attr([
    'year'    => $year,
    'month'   => $month,
    'monthGen' => MONTHS_GEN[$month],
    'today'   => $isCurrentMonth ? $todayD : 0,
    'dow'     => array_map(function ($i) { return $i['dow']; }, $info),
    'holiday' => array_map(function ($i) { return $i['holiday']; }, $info),
    'dowNames' => DOW_LONG,
    'days'    => $dayData,
    'shifts'  => shifts_for_js(),
]) ?></script>

<?php
page_end(['scripts' => ['userCalendar.js']]);
