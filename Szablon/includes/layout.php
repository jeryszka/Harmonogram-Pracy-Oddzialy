<?php
/**
 * Wspólny szablon stron paneli (sidebar + topbar + treść).
 *
 * page_start('Tytuł', [
 *   'role'         => 'admin' | 'employee'   (domyślnie z sesji)
 *   'active'       => klucz aktywnej pozycji menu
 *   'h1'           => nagłówek (domyślnie tytuł)
 *   'subtitle'     => podtytuł (tekst) / 'subtitleHtml' => podtytuł (HTML)
 *   'subtitleClass'=> dodatkowa klasa podtytułu (np. 'hide-mobile' ukrywa go na telefonie)
 *   'actions'      => HTML wstawiany po prawej stronie paska górnego
 *   'pageClass'    => dodatkowe klasy kontenera treści (np. 'page-narrow')
 * ]);
 * ... treść ...
 * page_end(['scripts' => ['plik.js'], 'inline' => '...js...']);
 */

function asset($path)
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . $path) . '?v=' . $v;
}

function nav_for($role)
{
    if ($role === 'admin') {
        return [
            'Zarządzanie' => [
                ['dashboard', 'Panel administratora',   'adminPanel/php/adminPanelMain.php'],
                ['employees', 'Zarządzaj pracownikami', 'adminPanel/php/manageEmployees.php'],
                ['new',       'Nowy grafik',            'adminPanel/php/CreateSchedulePanel.php'],
                ['edit',      'Edytuj grafik',          'adminPanel/php/editSchedule.php'],
                ['norms',     'Normy pracowników',      'adminPanel/php/editScheduleNorms.php'],
            ],
            'Konto' => [
                ['password',  'Zmień hasło',            'userPanel/php/changePassword.php'],
                ['logout',    'Wyloguj',                'logout.php'],
            ],
        ];
    }
    return [
        'Menu' => [
            ['dashboard', 'Panel użytkownika', 'userPanel/php/userPanel.php'],
            ['team',      'Grafik zespołu',    'userPanel/php/seeSchedule.php'],
        ],
        'Konto' => [
            ['password',  'Zmień hasło',       'userPanel/php/changePassword.php'],
            ['logout',    'Wyloguj',           'logout.php'],
        ],
    ];
}

function render_flash()
{
    foreach (pull_flash() as $f) {
        $type = in_array($f['type'], ['success', 'error', 'warning', 'info'], true) ? $f['type'] : 'info';
        echo '<div class="alert alert-' . $type . ' fade-in" role="alert" style="width:100%">'
            . '<span>' . e($f['message']) . '</span></div>';
    }
}

function page_start($title, array $o = [])
{
    $user      = current_user();
    $role      = $o['role'] ?? ($user['role'] ?? 'employee');
    $active    = $o['active'] ?? '';
    $h1        = $o['h1'] ?? $title;
    $subHtml   = $o['subtitleHtml'] ?? e($o['subtitle'] ?? '');
    $pageClass = trim('page-body ' . ($o['pageClass'] ?? ''));
    $bodyClass = trim($o['bodyClass'] ?? '');
    $actions   = $o['actions'] ?? '';
    $subClass  = isset($o['subtitleClass']) ? ' ' . $o['subtitleClass'] : '';
    $brandSub  = $role === 'admin'
        ? 'Administrator'
        : ($user && !empty($user['name']) ? trim($user['name'] . ' ' . $user['surname']) : 'Panel Pracownika');

    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — <?= e(cfg('app_name')) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('img/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
    <style><?= shifts_css() ?></style>
    <script>try{if(localStorage.getItem('sidebarCollapsed')==='1'&&window.innerWidth>900){document.documentElement.classList.add('sidebar-collapsed');}}catch(e){}</script>
</head>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
<div class="app-layout">

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h2>Harmonogram<br>Pracy</h2>
            <p><?= e($brandSub) ?></p>
        </div>
        <nav class="sidebar-nav">
            <?php $first = true; foreach (nav_for($role) as $label => $items): ?>
                <span class="nav-label"<?= $first ? '' : ' style="margin-top:12px"' ?>><?= e($label) ?></span>
                <?php foreach ($items as $it): ?>
                    <a class="nav-item<?= $it[0] === $active ? ' active' : '' ?>" href="<?= e(url($it[2])) ?>"><?= e($it[1]) ?></a>
                <?php endforeach; ?>
                <?php $first = false; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-content">
        <div class="topbar">
            <button type="button" class="menu-btn" id="menuBtn" aria-label="Pokaż / ukryj menu" title="Menu"><span></span><span></span><span></span></button>
            <div class="topbar-title">
                <h1><?= e($h1) ?></h1>
                <?php if ($subHtml !== ''): ?><p class="subtitle<?= e($subClass) ?>"><?= $subHtml ?></p><?php endif; ?>
            </div>
            <?php if ($actions !== ''): ?><div class="topbar-actions"><?= $actions ?></div><?php endif; ?>
        </div>

        <div class="<?= e($pageClass) ?>">
            <?php render_flash(); ?>
<?php
}

function page_end(array $o = [])
{
    ?>
        </div><!-- /page-body -->
    </div><!-- /main-content -->
</div><!-- /app-layout -->
<div class="toast-stack" id="toastStack" aria-live="polite"></div>
<script src="<?= e(asset('app.js')) ?>"></script>
<script src="<?= e(asset('HandleMenu.js')) ?>"></script>
<?php foreach (($o['scripts'] ?? []) as $s): ?>
<script src="<?= e(asset($s)) ?>"></script>
<?php endforeach; ?>
<?php if (!empty($o['inline'])): ?>
<script><?= $o['inline'] ?></script>
<?php endif; ?>
</body>
</html>
<?php
}

/**
 * Nawigacja miesięczna: lista miesięcy (GET, wysyłana po wyborze) oraz przyciski Poprzedni / Następny / Dziś.
 * Na liście są istniejące grafiki, miesiąc bieżący i aktualnie oglądany (miesiące bez grafiku są opisane).
 * Na telefonie lista zajmuje całą szerokość, a pod nią są trzy równe przyciski (patrz style.css).
 */
function month_nav_html($baseUrl, $year, $month, array $extraQuery = [])
{
    list($py, $pm) = shift_month($year, $month, -1);
    list($ny, $nm) = shift_month($year, $month, 1);
    $href = function ($y, $m) use ($baseUrl, $extraQuery) {
        return e($baseUrl . '?' . http_build_query(array_merge($extraQuery, ['y' => $y, 'm' => $m])));
    };

    $viewKey = sprintf('%04d-%02d', $year, $month);
    $nowKey  = date('Y-m');
    $items   = [];
    foreach (list_schedules() as $s) {
        $items[sprintf('%04d-%02d', $s['year'], $s['month'])] = true;
    }
    foreach ([$viewKey, $nowKey] as $k) {
        if (!isset($items[$k])) {
            $items[$k] = false;
        }
    }
    krsort($items); // od najnowszego

    $options = '';
    foreach ($items as $key => $exists) {
        list($y, $m) = array_map('intval', explode('-', $key));
        $options .= '<option value="' . e($key) . '"' . ($key === $viewKey ? ' selected' : '') . '>'
            . e(MONTHS_PL[$m] . ' ' . $y . ($exists ? '' : ' (brak grafiku)')) . '</option>';
    }

    $hidden = '';
    foreach ($extraQuery as $k => $v) {
        $hidden .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }

    return '<form class="month-nav" method="get" action="' . e($baseUrl) . '">' . $hidden
        . '<a class="btn btn-secondary btn-sm mn-prev" href="' . $href($py, $pm) . '" title="Poprzedni miesiąc">Poprzedni</a>'
        . '<select class="mn-select" name="ym" aria-label="Wybierz miesiąc" onchange="this.form.submit()">' . $options . '</select>'
        . '<noscript><button type="submit" class="btn btn-secondary btn-sm">Pokaż</button></noscript>'
        . '<a class="btn btn-secondary btn-sm mn-next" href="' . $href($ny, $nm) . '" title="Następny miesiąc">Następny</a>'
        . '<a class="btn btn-secondary btn-sm mn-today' . ($viewKey === $nowKey ? ' is-disabled' : '') . '" href="'
        . $href((int)date('Y'), (int)date('n')) . '"' . ($viewKey === $nowKey ? ' aria-disabled="true" tabindex="-1"' : '') . '>Dziś</a>'
        . '</form>';
}
