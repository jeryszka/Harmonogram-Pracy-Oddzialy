<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user  = require_employee();
$empId = (int)$user['id'];

list($year, $month) = request_month();
$self  = 'userPanel/php/seeSchedule.php';
$today = new DateTimeImmutable('today');
$isCurrentMonth = $year === (int)$today->format('Y') && $month === (int)$today->format('n');
$todayD = $isCurrentMonth ? (int)$today->format('j') : 0;

$sch  = find_schedule($year, $month);
$info = month_days_info($year, $month);
$days = days_in_month($year, $month);
$ld   = $sch ? load_schedule($sch) : null;

$rows = [];
if ($ld) {
    foreach (emp_all() as $emp) {
        if (isset($ld['data'][(int)$emp['id']])) {
            $rows[] = $emp;
        }
    }
    $cov = schedule_coverage($ld['data'], $days);
}

$actions = '';
if ($sch) {
    $actions = '<button type="button" class="btn btn-secondary btn-sm hide-mobile" onclick="window.print()">Drukuj</button>'
        . '<a class="btn btn-secondary btn-sm" href="' . e(url('export.php?type=csv&table=' . rawurlencode($sch['table']))) . '">Eksport CSV</a>';
}

page_start('Grafik zespołu', [
    'active'    => 'team',
    'h1'        => 'Grafik zespołu',
    'subtitle'  => 'Przeglądaj harmonogram całego oddziału',
    'subtitleClass' => 'hide-mobile',
    'actions'   => $actions,
    'pageClass' => 'page-col',
]);
?>

<div class="card fade-in no-print picker-card">
    <div class="picker-bar">
        <?= month_nav_html(url($self), $year, $month) ?>
        <div class="picker-tools">
            <input type="search" class="search-input" placeholder="Szukaj pracownika…" data-filter="#teamTable" aria-label="Szukaj pracownika">
            <div class="legend"><?= shifts_legend(true) ?></div>
        </div>
    </div>
</div>

<?php if (!$sch): ?>
    <div class="card fade-in"><div class="empty-state">Grafik na <?= e(MONTHS_PL[$month] . ' ' . $year) ?> nie został jeszcze utworzony.</div></div>
<?php else: ?>
<div class="card fade-in" style="padding:0;overflow:hidden">
    <div style="padding:20px 24px 16px; border-bottom:1px solid var(--gray-100)">
        <div class="card-title"><?= e($sch['label']) ?></div>
        <div class="card-subtitle"><?= count($rows) ?> pracowników · <?= (int)$days ?> dni</div>
    </div>

    <div class="table-wrap" style="border-radius:0;border:none">
        <table class="team-table" id="teamTable">
            <thead>
                <tr>
                    <th class="sticky-col" style="text-align:left">Pracownik</th>
                    <?php for ($d = 1; $d <= $days; $d++):
                        $cls = trim(($info[$d]['weekend'] ? 'we ' : '') . ($info[$d]['holiday'] ? 'ho ' : '') . ($d === $todayD ? 'is-today' : '')); ?>
                        <th class="<?= $cls ?>"<?= $info[$d]['holiday'] ? ' title="' . e($info[$d]['holiday']) . '"' : '' ?>>
                            <?= $d ?><br><span style="font-weight:500;font-size:9px;opacity:.7"><?= e(DOW_SHORT[$info[$d]['dow']]) ?></span>
                        </th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $emp):
                $row  = $ld['data'][(int)$emp['id']];
                $mine = (int)$emp['id'] === $empId;
            ?>
                <tr class="<?= $mine ? 'me' : '' ?>" data-search="<?= e($emp['name'] . ' ' . $emp['surname']) ?>">
                    <td class="sticky-col"><?= e($emp['surname'] . ' ' . $emp['name']) ?><?= $mine ? ' <span class="badge badge-info">Ty</span>' : '' ?></td>
                    <?php for ($d = 1; $d <= $days; $d++):
                        $cls = trim(($info[$d]['weekend'] ? 'we ' : '') . ($info[$d]['holiday'] ? 'ho ' : '') . ($d === $todayD ? 'is-today' : '')); ?>
                        <td class="<?= $cls ?>"><?= shift_badge($row[$d] ?? '') ?></td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>Obsada: dzień</td>
                    <?php for ($d = 1; $d <= $days; $d++): ?><td class="<?= $cov[$d]['day'] === 0 ? 'cov-low' : '' ?>"><?= (int)$cov[$d]['day'] ?></td><?php endfor; ?>
                </tr>
                <tr>
                    <td>Obsada: noc</td>
                    <?php for ($d = 1; $d <= $days; $d++): ?><td class="<?= $cov[$d]['night'] === 0 ? 'cov-low' : '' ?>"><?= (int)$cov[$d]['night'] ?></td><?php endfor; ?>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="empty-state no-results" hidden>Brak pasujących pracowników.</p>
</div>
<?php endif; ?>

<?php
page_end();
