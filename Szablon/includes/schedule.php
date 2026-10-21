<?php
/**
 * Logika grafików.
 * Każdy miesiąc to osobna tabela `RRRR_Miesiąc` (np. 2026_Październik):
 *   employeeId INT, `1` … `31` VARCHAR(15) – kod zmiany w danym dniu.
 */

/* ------------------------------------------------------------------ */
/*  Słowniki                                                           */
/* ------------------------------------------------------------------ */

/**
 * Słownik dyżurów – jedyne miejsce, w którym je definiujemy.
 *   key      – skrót używany w klasach CSS / atrybutach
 *   label    – nazwa (z czasem trwania)
 *   time     – typowe godziny (informacyjnie)
 *   minutes  – czas PRACY w minutach (liczony do normy czasu pracy)
 *   oncall   – czas gotowości pod telefonem w minutach (NIE liczy się do normy; płatny 50%)
 *   night    – minuty przypadające na porę nocną (21:00–7:00) – podstawa dodatku nocnego
 *   work     – true = dyżur (zmiana), false = nieobecność
 *   cover    – wliczanie do obsady: day | night | both | off | none
 *   group    – grupa na pasku narzędzi edytora
 *   hotkey   – klawisz szybkiego wpisywania w edytorze
 */
const SHIFTS = [
    // --- Dyżury standardowe (system równoważny, 12 h) i dobowe ---
    'D'    => ['key' => 'D',    'label' => 'Dzienna 12 h',            'time' => '7:00–19:00',  'minutes' => 720,  'oncall' => 0,    'night' => 0,   'work' => true,  'cover' => 'day',   'group' => 'std',  'hotkey' => 'd', 'color' => '#2563eb', 'bg' => '#dbeafe'],
    'N'    => ['key' => 'N',    'label' => 'Nocna 12 h',              'time' => '19:00–7:00',  'minutes' => 720,  'oncall' => 0,    'night' => 600, 'work' => true,  'cover' => 'night', 'group' => 'std',  'hotkey' => 'n', 'color' => '#7c3aed', 'bg' => '#ede9fe'],
    'D/N'  => ['key' => 'DN',   'label' => 'Doba 24 h',               'time' => '7:00–7:00',   'minutes' => 1440, 'oncall' => 0,    'night' => 600, 'work' => true,  'cover' => 'both',  'group' => 'std',  'hotkey' => 'o', 'color' => '#db2777', 'bg' => '#fce7f3'],
    // --- System podstawowy / trzyzmianowy ---
    '7:35' => ['key' => 'S735', 'label' => 'Dyżur 7 h 35 min',        'time' => '7:00–15:00',  'minutes' => 455,  'oncall' => 0,    'night' => 0,   'work' => true,  'cover' => 'day',   'group' => 'std',  'hotkey' => 's', 'color' => '#0f766e', 'bg' => '#ccfbf1'],
    'R'    => ['key' => 'R',    'label' => 'Rano 8 h',                'time' => '7:00–15:00',  'minutes' => 480,  'oncall' => 0,    'night' => 0,   'work' => true,  'cover' => 'day',   'group' => 'std',  'hotkey' => 'r', 'color' => '#059669', 'bg' => '#d1fae5'],
    'P'    => ['key' => 'P',    'label' => 'Popołudnie 8 h',          'time' => '15:00–23:00', 'minutes' => 480,  'oncall' => 0,    'night' => 120, 'work' => true,  'cover' => 'day',   'group' => 'std',  'hotkey' => 'p', 'color' => '#d97706', 'bg' => '#fef3c7'],
    'N8'   => ['key' => 'N8',   'label' => 'Noc 8 h',                 'time' => '23:00–7:00',  'minutes' => 480,  'oncall' => 0,    'night' => 480, 'work' => true,  'cover' => 'night', 'group' => 'std',  'hotkey' => 'm', 'color' => '#4338ca', 'bg' => '#e0e7ff'],
    // --- Dyżur pod telefonem (gotowość do pracy) ---
    'PT12' => ['key' => 'PT12', 'label' => 'Pod telefonem 12 h',     'time' => '',            'minutes' => 0,    'oncall' => 720,  'night' => 0,   'work' => true,  'cover' => 'none',  'group' => 'call', 'hotkey' => 't', 'color' => '#c2410c', 'bg' => '#ffedd5'],
    'PT24' => ['key' => 'PT24', 'label' => 'Pod telefonem 24 h',     'time' => '',            'minutes' => 0,    'oncall' => 1440, 'night' => 0,   'work' => true,  'cover' => 'none',  'group' => 'call', 'hotkey' => 'g', 'color' => '#c2410c', 'bg' => '#ffedd5'],
    // --- Nieobecności ---
    'UW'   => ['key' => 'UW',   'label' => 'Urlop wypoczynkowy',      'time' => '',            'minutes' => 0,    'oncall' => 0,    'night' => 0,   'work' => false, 'cover' => 'off',   'group' => 'abs',  'hotkey' => 'w', 'color' => '#0284c7', 'bg' => '#e0f2fe'],
    'UŻ'   => ['key' => 'UZ',   'label' => 'Urlop na żądanie',        'time' => '',            'minutes' => 0,    'oncall' => 0,    'night' => 0,   'work' => false, 'cover' => 'off',   'group' => 'abs',  'hotkey' => 'z', 'color' => '#0e7490', 'bg' => '#cffafe'],
    'L4'   => ['key' => 'L4',   'label' => 'Chorobowe',               'time' => '',            'minutes' => 0,    'oncall' => 0,    'night' => 0,   'work' => false, 'cover' => 'off',   'group' => 'abs',  'hotkey' => 'l', 'color' => '#dc2626', 'bg' => '#fee2e2'],
];

/** Dawne kody pozostają czytelne w zapisanych wcześniej grafikach. */
const LEGACY_ADJUSTMENT_SHIFTS = [
    'W5' => ['key' => 'W5', 'label' => 'Wyrównawczy 5 h', 'time' => '', 'minutes' => 300, 'oncall' => 0, 'night' => 0, 'work' => true, 'cover' => 'day', 'group' => 'adj', 'hotkey' => '', 'color' => '#4d7c0f', 'bg' => '#ecfccb'],
    'W6' => ['key' => 'W6', 'label' => 'Wyrównawczy 6 h', 'time' => '', 'minutes' => 360, 'oncall' => 0, 'night' => 0, 'work' => true, 'cover' => 'day', 'group' => 'adj', 'hotkey' => '', 'color' => '#4d7c0f', 'bg' => '#ecfccb'],
    'W7' => ['key' => 'W7', 'label' => 'Wyrównawczy 7 h', 'time' => '', 'minutes' => 420, 'oncall' => 0, 'night' => 0, 'work' => true, 'cover' => 'day', 'group' => 'adj', 'hotkey' => '', 'color' => '#4d7c0f', 'bg' => '#ecfccb'],
    'W8' => ['key' => 'W8', 'label' => 'Wyrównawczy 8 h', 'time' => '', 'minutes' => 480, 'oncall' => 0, 'night' => 0, 'work' => true, 'cover' => 'day', 'group' => 'adj', 'hotkey' => '', 'color' => '#4d7c0f', 'bg' => '#ecfccb'],
    'W7+10' => ['key' => 'W7', 'label' => 'Wyrównawczy 7 h 10 min', 'time' => '', 'minutes' => 430, 'oncall' => 0, 'night' => 0, 'work' => true, 'cover' => 'day', 'group' => 'adj', 'hotkey' => '', 'color' => '#4d7c0f', 'bg' => '#ecfccb'],
    'W6+45' => ['key' => 'W6', 'label' => 'Wyrównawczy 6 h 45 min', 'time' => '', 'minutes' => 405, 'oncall' => 0, 'night' => 0, 'work' => true, 'cover' => 'day', 'group' => 'adj', 'hotkey' => '', 'color' => '#4d7c0f', 'bg' => '#ecfccb'],
];

const SHIFT_GROUPS = ['std' => 'Dyżury', 'adj' => 'Wyrównawcze', 'call' => 'Pod telefonem', 'abs' => 'Nieobecności'];

/** Dobowa norma czasu pracy pracownika medycznego: 7 h 35 min. */
const DAILY_NORM_MINUTES = 455;

const MONTHS_PL = [
    1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień', 5 => 'Maj', 6 => 'Czerwiec',
    7 => 'Lipiec', 8 => 'Sierpień', 9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
];

const MONTHS_GEN = [
    1 => 'stycznia', 2 => 'lutego', 3 => 'marca', 4 => 'kwietnia', 5 => 'maja', 6 => 'czerwca',
    7 => 'lipca', 8 => 'sierpnia', 9 => 'września', 10 => 'października', 11 => 'listopada', 12 => 'grudnia',
];

const DOW_SHORT = [1 => 'Pn', 2 => 'Wt', 3 => 'Śr', 4 => 'Cz', 5 => 'Pt', 6 => 'Sb', 7 => 'Nd'];
const DOW_LONG  = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];

/* ------------------------------------------------------------------ */
/*  Kody zmian                                                         */
/* ------------------------------------------------------------------ */

/** Ujednolica zapis zmiany (np. historyczne „DN" na „D/N"). */
function normalize_shift($value)
{
    $v = mb_strtoupper(trim((string)$value));
    if ($v === 'DN' || $v === 'D-N') {
        return 'D/N';
    }
    if ($v === 'UZ') {
        return 'UŻ';
    }
    return $v;
}

function ensure_schedule_custom_shifts_table()
{
    db()->exec('CREATE TABLE IF NOT EXISTS schedule_custom_shifts (
        scheduleTable VARCHAR(100) NOT NULL,
        code VARCHAR(15) NOT NULL,
        minutes SMALLINT UNSIGNED NOT NULL,
        PRIMARY KEY (scheduleTable, code),
        UNIQUE KEY unique_schedule_minutes (scheduleTable, minutes)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function custom_shift_info($code, $minutes)
{
    $hours = intdiv((int)$minutes, 60);
    $remainder = (int)$minutes % 60;
    return [
        'key' => 'W' . $hours,
        'label' => 'Wyrównawczy ' . $hours . ' h' . ($remainder ? ' ' . $remainder . ' min' : ''),
        'time' => '',
        'minutes' => (int)$minutes,
        'oncall' => 0,
        'night' => 0,
        'work' => true,
        'cover' => 'day',
        'group' => 'adj',
        'hotkey' => '',
        'color' => '#4d7c0f',
        'bg' => '#ecfccb',
        'custom' => true,
    ];
}

function schedule_custom_shift_map(array $sch)
{
    ensure_schedule_custom_shifts_table();
    $st = db()->prepare('SELECT code, minutes FROM schedule_custom_shifts WHERE scheduleTable = ? ORDER BY minutes');
    $st->execute([$sch['table']]);
    $shifts = [];
    foreach ($st->fetchAll() as $row) {
        $shifts[$row['code']] = custom_shift_info($row['code'], $row['minutes']);
    }
    return $shifts;
}

function activate_schedule_shifts(array $sch)
{
    $GLOBALS['active_schedule_custom_shifts'] = schedule_custom_shift_map($sch);
}

function active_schedule_custom_shifts()
{
    return $GLOBALS['active_schedule_custom_shifts'] ?? [];
}

function active_shift_catalog()
{
    return array_merge(SHIFTS, LEGACY_ADJUSTMENT_SHIFTS, $GLOBALS['active_schedule_custom_shifts'] ?? []);
}

function palette_shift_catalog()
{
    return array_merge(SHIFTS, $GLOBALS['active_schedule_custom_shifts'] ?? []);
}

function create_schedule_custom_shift(array $sch, $hours, $minutes)
{
    $hours = filter_var($hours, FILTER_VALIDATE_INT);
    $minutes = filter_var($minutes, FILTER_VALIDATE_INT);
    if ($hours === false || $minutes === false || $hours < 0 || $hours > 11 || $minutes < 0 || $minutes > 59) {
        throw new InvalidArgumentException('Podaj godziny od 0 do 11 oraz minuty od 0 do 59.');
    }
    $total = $hours * 60 + $minutes;
    if ($total < 1 || $total >= 12 * 60) {
        throw new InvalidArgumentException('Czas dyżuru wyrównawczego musi wynosić od 1 minuty do mniej niż 12 godzin.');
    }
    $code = 'W' . $hours . '+' . sprintf('%02d', $minutes);
    ensure_schedule_custom_shifts_table();
    $existing = db()->prepare('SELECT code FROM schedule_custom_shifts WHERE scheduleTable = ? AND minutes = ? LIMIT 1');
    $existing->execute([$sch['table'], $total]);
    if ($existing->fetchColumn()) {
        throw new InvalidArgumentException('Taki dyżur wyrównawczy został już dodany do tego grafiku.');
    }
    $st = db()->prepare('INSERT INTO schedule_custom_shifts (scheduleTable, code, minutes) VALUES (?, ?, ?)');
    $st->execute([$sch['table'], $code, $total]);
    activate_schedule_shifts($sch);
    return $code;
}

function is_valid_shift($code)
{
    return isset(active_shift_catalog()[$code]);
}

function shift_info($code)
{
    return active_shift_catalog()[$code] ?? [
        'key' => 'X', 'label' => $code, 'time' => '', 'minutes' => 0, 'oncall' => 0, 'night' => 0,
        'work' => false, 'cover' => 'none', 'group' => '', 'hotkey' => '', 'color' => '#64748b', 'bg' => '#f1f5f9',
    ];
}

function shift_minutes($code)
{
    $shifts = active_shift_catalog();
    return $shifts[$code]['minutes'] ?? 0;
}

function shift_cover($code)
{
    $shifts = active_shift_catalog();
    return $shifts[$code]['cover'] ?? 'none';
}

/** Minuty -> „166:50" (do tabel i CSV). */
function format_hm($minutes)
{
    $minutes = (int)$minutes;
    return intdiv($minutes, 60) . ':' . sprintf('%02d', $minutes % 60);
}

/** Minuty -> „166 h 50 min" (do opisów). */
function format_hours_text($minutes)
{
    $minutes = (int)$minutes;
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m > 0 ? $h . ' h ' . $m . ' min' : $h . ' h';
}

/** Nazwa dyżuru z typowymi godzinami, np. „Dzienna 12 h (7:00–19:00)". */
function shift_desc($code)
{
    $i = shift_info($code);
    return $i['label'] . ($i['time'] !== '' ? ' (' . $i['time'] . ')' : '');
}

/** Plakietka zmiany (HTML). */
function shift_badge($code)
{
    if ($code === '' || $code === null) {
        return '<span class="shift-empty">—</span>';
    }
    $i = shift_info($code);
    return '<span class="shift shift-' . e($i['key']) . '" title="' . e(shift_desc($code)) . '">' . e($code) . '</span>';
}

/** Legenda zmian (HTML) – domyślnie tylko dyżury, bez nieobecności. */
function shifts_legend($all = false)
{
    $html = '';
    foreach (active_shift_catalog() as $code => $i) {
        if (!$all && !$i['work']) {
            continue;
        }
        $html .= shift_badge($code);
    }
    return $html;
}

/** Dane dyżurów dla JavaScriptu. */
function shifts_for_js()
{
    $out = [];
    foreach (active_shift_catalog() as $code => $i) {
        $out[$code] = [
            'key'     => $i['key'],
            'label'   => $i['label'],
            'desc'    => shift_desc($code),
            'minutes' => $i['minutes'],
            'oncall'  => $i['oncall'],
            'work'    => $i['work'],
            'cover'   => $i['cover'],
            'hotkey'  => $i['hotkey'],
        ];
    }
    return $out;
}

/** Style kolorów dyżurów generowane ze słownika SHIFTS (wstawiane w <head>). */
function shifts_css()
{
    $css = '';
    foreach (active_shift_catalog() as $i) {
        $k = $i['key'];
        $c = $i['color'];
        $b = $i['bg'];
        $css .= ".shift-$k{background:$b;color:$c}"
            . ".shift-mini.$k{background:$b;color:$c}"
            . ".solid-$k{background:$c}"
            . ".palette-btn[data-k=\"$k\"]{background:$b;color:$c}"
            . ".grid td.cell[data-k=\"$k\"]{background:$b;color:$c}";
    }
    return $css;
}

/* ------------------------------------------------------------------ */
/*  Miesiące i nazwy tabel                                             */
/* ------------------------------------------------------------------ */

/** „Październik" / „pazdziernik" / „PAŹDZIERNIK" to samo, do porównań. */
function month_key($s)
{
    return strtr(mb_strtolower($s), [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
    ]);
}

function month_number($name)
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (MONTHS_PL as $n => $label) {
            $map[month_key($label)] = $n;
        }
    }
    return $map[month_key($name)] ?? null;
}

function schedule_table_name($year, $month)
{
    return (int)$year . '_' . MONTHS_PL[(int)$month];
}

function parse_schedule_table($table)
{
    if (!preg_match('/^(\d{4})_(.+)$/u', $table, $mm)) {
        return null;
    }
    $month = month_number($mm[2]);
    if (!$month) {
        return null;
    }
    return [
        'table' => $table,
        'year'  => (int)$mm[1],
        'month' => $month,
        'label' => MONTHS_PL[$month] . ' ' . (int)$mm[1],
    ];
}

/** Wszystkie grafiki w bazie, od najnowszego. Nazwy tabel są zweryfikowane (bezpieczne do użycia w SQL). */
function list_schedules($refresh = false)
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $p = parse_schedule_table($t);
            if ($p) {
                $cache[] = $p;
            }
        }
        usort($cache, function ($a, $b) {
            return ($b['year'] <=> $a['year']) ?: ($b['month'] <=> $a['month']);
        });
    }
    return $cache;
}

function find_schedule($year, $month)
{
    foreach (list_schedules() as $s) {
        if ($s['year'] === (int)$year && $s['month'] === (int)$month) {
            return $s;
        }
    }
    return null;
}

function find_schedule_by_table($table)
{
    foreach (list_schedules() as $s) {
        if ($s['table'] === $table) {
            return $s;
        }
    }
    return null;
}

function days_in_month($year, $month)
{
    return (int)date('t', mktime(0, 0, 0, (int)$month, 1, (int)$year));
}

/** Rok i miesiąc z parametrów ?y=&m= (domyślnie bieżący). */
function request_month()
{
    // wybór z listy miesięcy: ?ym=2026-10 (inaczej ?y=2026&m=10)
    if (preg_match('/^(\d{4})-(\d{1,2})$/', get('ym'), $mm)) {
        $y = (int)$mm[1];
        $m = (int)$mm[2];
    } else {
        $y = (int)get('y', date('Y'));
        $m = (int)get('m', date('n'));
    }
    if ($y < 2000 || $y > 2100) {
        $y = (int)date('Y');
    }
    if ($m < 1 || $m > 12) {
        $m = (int)date('n');
    }
    return [$y, $m];
}

function shift_month($year, $month, $delta)
{
    $t = mktime(0, 0, 0, (int)$month + (int)$delta, 1, (int)$year);
    return [(int)date('Y', $t), (int)date('n', $t)];
}

/** „14 października 2026" */
function pl_date(DateTimeInterface $d, $withYear = true)
{
    return (int)$d->format('j') . ' ' . MONTHS_GEN[(int)$d->format('n')] . ($withYear ? ' ' . $d->format('Y') : '');
}

/* ------------------------------------------------------------------ */
/*  Odczyt / zapis                                                     */
/* ------------------------------------------------------------------ */

/**
 * Wczytuje grafik. Zwraca ['sch' => wpis, 'days' => n, 'data' => [employeeId => [dzień => kod]]].
 */
function load_schedule(array $sch)
{
    static $cache = [];
    activate_schedule_shifts($sch);
    if (isset($cache[$sch['table']])) {
        return $cache[$sch['table']];
    }
    $pdo   = db();
    $table = $sch['table'];
    $days  = days_in_month($sch['year'], $sch['month']);

    $have = array_flip(array_map('strval', $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN)));
    $cols = ['employeeId'];
    for ($d = 1; $d <= $days; $d++) {
        if (isset($have[(string)$d])) {
            $cols[] = '`' . $d . '`';
        }
    }
    $data = [];
    foreach ($pdo->query('SELECT ' . implode(', ', $cols) . " FROM `$table`")->fetchAll() as $row) {
        $id = (int)$row['employeeId'];
        for ($d = 1; $d <= $days; $d++) {
            $data[$id][$d] = normalize_shift($row[$d] ?? '');
        }
    }
    return $cache[$table] = ['sch' => $sch, 'days' => $days, 'data' => $data];
}

/** Dba o to, by tabela miała komplet kolumn dni oraz kodowanie utf8mb4 (znaki „Ż"). */
function ensure_schedule_table_ready($table, $days)
{
    $pdo = db();
    try {
        $st = $pdo->prepare('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        $collation = (string)$st->fetchColumn();
        if ($collation !== '' && strpos($collation, 'utf8') !== 0) {
            $pdo->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4");
        }
    } catch (Throwable $e) {
        // nie blokuj zapisu, jeśli brak uprawnień
    }
    $have = array_flip(array_map('strval', $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN)));
    $add  = [];
    for ($d = 1; $d <= $days; $d++) {
        if (!isset($have[(string)$d])) {
            $add[] = "ADD COLUMN `$d` VARCHAR(15) NULL";
        }
    }
    if ($add) {
        $pdo->exec("ALTER TABLE `$table` " . implode(', ', $add));
    }
}

/**
 * Zapisuje grafik. $input: [employeeId => [kod_dnia1, kod_dnia2, …]].
 * Zapisywani są tylko istniejący pracownicy, a kody spoza słownika są zamieniane na puste.
 */
function save_schedule(array $sch, array $input)
{
    activate_schedule_shifts($sch);
    $pdo   = db();
    $table = $sch['table'];
    $days  = days_in_month($sch['year'], $sch['month']);
    ensure_schedule_table_ready($table, $days);

    $employees = emp_map();
    $cols = ['employeeId'];
    $ph   = ['?'];
    $upd  = [];
    for ($d = 1; $d <= $days; $d++) {
        $cols[] = '`' . $d . '`';
        $ph[]   = '?';
        $upd[]  = '`' . $d . '` = VALUES(`' . $d . '`)';
    }
    $st = $pdo->prepare(
        "INSERT INTO `$table` (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ') '
        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $upd)
    );

    $pdo->beginTransaction();
    try {
        foreach ($input as $empId => $row) {
            $empId = (int)$empId;
            if (!isset($employees[$empId]) || !is_array($row)) {
                continue;
            }
            $params = [$empId];
            for ($d = 0; $d < $days; $d++) {
                $code     = normalize_shift($row[$d] ?? '');
                $params[] = is_valid_shift($code) ? $code : null;
            }
            $st->execute($params);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Tworzy tabelę nowego miesiąca; opcjonalnie kopiuje zmiany robocze z innego grafiku. */
function create_schedule($year, $month, $copyFrom = null)
{
    $sourceData = $copyFrom ? load_schedule($copyFrom) : null;
    $sourceCustomShifts = $copyFrom ? schedule_custom_shift_map($copyFrom) : [];
    $table = schedule_table_name($year, $month);
    $days  = days_in_month($year, $month);
    $cols  = [];
    for ($d = 1; $d <= $days; $d++) {
        $cols[] = "`$d` VARCHAR(15) NULL";
    }
    db()->exec(
        "CREATE TABLE `$table` (employeeId INT NOT NULL, " . implode(', ', $cols)
        . ', PRIMARY KEY (employeeId)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    list_schedules(true);
    $sch = find_schedule($year, $month);

    if ($sourceCustomShifts) {
        ensure_schedule_custom_shifts_table();
        $copyShift = db()->prepare('INSERT INTO schedule_custom_shifts (scheduleTable, code, minutes) VALUES (?, ?, ?)');
        foreach ($sourceCustomShifts as $code => $shift) {
            $copyShift->execute([$sch['table'], $code, $shift['minutes']]);
        }
    }
    activate_schedule_shifts($sch);

    $src   = $sourceData;
    $input = [];
    foreach (emp_all() as $emp) {
        $row = array_fill(0, $days, '');
        if ($src && isset($src['data'][(int)$emp['id']])) {
            $max = min($days, $src['days']);
            for ($d = 1; $d <= $max; $d++) {
                $code = $src['data'][(int)$emp['id']][$d] ?? '';
                if (!empty(active_shift_catalog()[$code]['work'])) {
                    $row[$d - 1] = $code;
                }
            }
        }
        $input[(int)$emp['id']] = $row;
    }
    save_schedule($sch, $input);
    return $sch;
}

function ensure_schedule_norms_table()
{
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS schedule_norms (
        scheduleTable VARCHAR(100) NOT NULL,
        employeeId INT NOT NULL,
        workDays TINYINT UNSIGNED NOT NULL,
        shiftHours TINYINT UNSIGNED NOT NULL DEFAULT 12,
        normMinutes SMALLINT UNSIGNED NOT NULL,
        PRIMARY KEY (scheduleTable, employeeId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    if (!$pdo->query("SHOW COLUMNS FROM schedule_norms LIKE 'shiftHours'")->fetch()) {
        $pdo->exec('ALTER TABLE schedule_norms ADD COLUMN shiftHours TINYINT UNSIGNED NOT NULL DEFAULT 12 AFTER workDays');
    }
}

/** Return the shift count and adjustment time shown on the paper table. */
function schedule_norm_breakdown($workDays, $shiftHours)
{
    $minutes = (int)$workDays * DAILY_NORM_MINUTES;
    $shiftMinutes = (int)$shiftHours * 60;
    return [
        'days' => (int)$workDays,
        'shiftHours' => (int)$shiftHours,
        'minutes' => $minutes,
        'shiftCount' => intdiv($minutes, $shiftMinutes),
        'adjustmentMinutes' => $minutes % $shiftMinutes,
    ];
}

/** Trwały indywidualny wymiar i wybrany typ dyżurów dla pracownika w grafiku. */
function save_schedule_norms(array $sch, array $settingsByEmployee)
{
    ensure_schedule_norms_table();
    $employees = emp_map();
    if (count($settingsByEmployee) !== count($employees)) {
        throw new InvalidArgumentException('Ustaw wymiar pracy dla każdego pracownika.');
    }
    $maxDays = days_in_month($sch['year'], $sch['month']);
    $validated = [];
    foreach ($settingsByEmployee as $id => $settings) {
        $id = (int)$id;
        $days = filter_var($settings['days'] ?? null, FILTER_VALIDATE_INT);
        $hours = filter_var($settings['shiftHours'] ?? null, FILTER_VALIDATE_INT);
        if (!isset($employees[$id]) || $days === false || $days < 1 || $days > $maxDays || !in_array($hours, [8, 12], true)) {
            throw new InvalidArgumentException('Nieprawidłowy wymiar pracy dla pracownika.');
        }
        $validated[$id] = [$days, $hours];
    }
    foreach ($employees as $id => $_employee) {
        if (!isset($validated[$id])) {
            throw new InvalidArgumentException('Ustaw wymiar pracy dla każdego pracownika.');
        }
    }
    $st = db()->prepare('REPLACE INTO schedule_norms (scheduleTable, employeeId, workDays, shiftHours, normMinutes) VALUES (?, ?, ?, ?, ?)');
    foreach ($validated as $id => $values) {
        list($days, $hours) = $values;
        $st->execute([$sch['table'], $id, $days, $hours, $days * DAILY_NORM_MINUTES]);
    }
}

function load_schedule_norms(array $sch)
{
    ensure_schedule_norms_table();
    $st = db()->prepare('SELECT employeeId, workDays, shiftHours, normMinutes FROM schedule_norms WHERE scheduleTable = ?');
    $st->execute([$sch['table']]);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $out[(int)$row['employeeId']] = schedule_norm_breakdown($row['workDays'], $row['shiftHours']);
        $out[(int)$row['employeeId']]['minutes'] = (int)$row['normMinutes'];
    }
    return $out;
}

function delete_schedule(array $sch)
{
    ensure_schedule_norms_table();
    $st = db()->prepare('DELETE FROM schedule_norms WHERE scheduleTable = ?');
    $st->execute([$sch['table']]);
    ensure_schedule_custom_shifts_table();
    $custom = db()->prepare('DELETE FROM schedule_custom_shifts WHERE scheduleTable = ?');
    $custom->execute([$sch['table']]);
    db()->exec('DROP TABLE `' . $sch['table'] . '`');
    list_schedules(true);
}

/* ------------------------------------------------------------------ */
/*  Statystyki                                                         */
/* ------------------------------------------------------------------ */

/**
 * Podsumowanie jednego wiersza grafiku:
 *   counts  – liczba zmian wg kodu
 *   minutes – czas pracy (do porównania z normą)
 *   night   – minuty pracy w porze nocnej (21:00–7:00)
 *   oncall  – minuty gotowości pod telefonem (poza normą, płatne 50%)
 */
function schedule_summary(array $row, $days)
{
    $counts = [];
    $minutes = 0;
    $night = 0;
    $oncall = 0;
    for ($d = 1; $d <= $days; $d++) {
        $c = $row[$d] ?? '';
        if ($c === '' || !is_valid_shift($c)) {
            continue;
        }
        $counts[$c] = ($counts[$c] ?? 0) + 1;
        $shift = shift_info($c);
        $minutes += $shift['minutes'];
        $night   += $shift['night'];
        $oncall  += $shift['oncall'];
    }
    return ['counts' => $counts, 'minutes' => $minutes, 'night' => $night, 'oncall' => $oncall];
}

/** Obsada na każdy dzień: day, night (dyżury „both" liczą się w obu), off (urlopy, L4). */
function schedule_coverage(array $data, $days)
{
    $cov = [];
    for ($d = 1; $d <= $days; $d++) {
        $cov[$d] = ['day' => 0, 'night' => 0, 'off' => 0];
    }
    foreach ($data as $row) {
        for ($d = 1; $d <= $days; $d++) {
            cover_add($cov[$d], $row[$d] ?? '');
        }
    }
    return $cov;
}

/** Dolicza jeden dyżur do licznika obsady ['day', 'night', 'off']. */
function cover_add(array &$counter, $code)
{
    if ($code === '' || $code === null) {
        return;
    }
    switch (shift_cover($code)) {
        case 'day':
            $counter['day']++;
            break;
        case 'night':
            $counter['night']++;
            break;
        case 'both':
            $counter['day']++;
            $counter['night']++;
            break;
        case 'off':
            $counter['off']++;
            break;
    }
}

/** Zmiany wszystkich pracowników w danym dniu: [employeeId => kod]. */
function shifts_on_date(DateTimeInterface $date)
{
    $sch = find_schedule((int)$date->format('Y'), (int)$date->format('n'));
    if (!$sch) {
        return [];
    }
    $ld  = load_schedule($sch);
    $day = (int)$date->format('j');
    $out = [];
    foreach ($ld['data'] as $empId => $row) {
        $c = $row[$day] ?? '';
        if ($c !== '') {
            $out[$empId] = $c;
        }
    }
    return $out;
}

/** Najbliższe zmiany robocze pracownika (od dziś), także w kolejnych miesiącach. */
function upcoming_shifts_for($empId, $limit = 8, $monthsAhead = 3)
{
    $out   = [];
    $today = new DateTimeImmutable('today');
    $y     = (int)$today->format('Y');
    $m     = (int)$today->format('n');
    for ($i = 0; $i <= $monthsAhead && count($out) < $limit; $i++) {
        list($yy, $mm) = shift_month($y, $m, $i);
        $sch = find_schedule($yy, $mm);
        if (!$sch) {
            continue;
        }
        $ld  = load_schedule($sch);
        $row = $ld['data'][(int)$empId] ?? [];
        for ($d = 1; $d <= $ld['days'] && count($out) < $limit; $d++) {
            $c = $row[$d] ?? '';
            if ($c === '' || empty(active_shift_catalog()[$c]['work'])) {
                continue;
            }
            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $yy, $mm, $d));
            if ($date < $today) {
                continue;
            }
            $out[] = ['date' => $date, 'code' => $c, 'info' => shift_info($c)];
        }
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/*  Kalendarz: święta i norma czasu pracy                              */
/* ------------------------------------------------------------------ */

/** Data Wielkanocy (algorytm Gaussa, kalendarz gregoriański): [miesiąc, dzień]. */
function easter_parts($year)
{
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day   = (($h + $l - 7 * $m + 114) % 31) + 1;
    return [$month, $day];
}

/** Polskie dni ustawowo wolne od pracy w danym roku: 'RRRR-MM-DD' => nazwa. */
function polish_holidays($year)
{
    static $cache = [];
    if (isset($cache[$year])) {
        return $cache[$year];
    }
    list($em, $ed) = easter_parts($year);
    $easter = new DateTime(sprintf('%04d-%02d-%02d', $year, $em, $ed));
    $h = [
        "$year-01-01" => 'Nowy Rok',
        "$year-01-06" => 'Trzech Króli',
        $easter->format('Y-m-d') => 'Wielkanoc',
        (clone $easter)->modify('+1 day')->format('Y-m-d')  => 'Poniedziałek Wielkanocny',
        (clone $easter)->modify('+49 days')->format('Y-m-d') => 'Zielone Świątki',
        (clone $easter)->modify('+60 days')->format('Y-m-d') => 'Boże Ciało',
        "$year-05-01" => 'Święto Pracy',
        "$year-05-03" => 'Święto Konstytucji 3 Maja',
        "$year-08-15" => 'Wniebowzięcie NMP',
        "$year-11-01" => 'Wszystkich Świętych',
        "$year-11-11" => 'Narodowe Święto Niepodległości',
        "$year-12-25" => 'Boże Narodzenie',
        "$year-12-26" => 'Drugi dzień Bożego Narodzenia',
    ];
    if ($year >= 2025) {
        $h["$year-12-24"] = 'Wigilia Bożego Narodzenia';
    }
    return $cache[$year] = $h;
}

/** Opis każdego dnia miesiąca: dow (1–7), weekend, holiday (nazwa lub null). */
function month_days_info($year, $month)
{
    $holidays = polish_holidays((int)$year);
    $n   = days_in_month($year, $month);
    $out = [];
    for ($d = 1; $d <= $n; $d++) {
        $key = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dow = (int)date('N', strtotime($key));
        $out[$d] = [
            'dow'     => $dow,
            'weekend' => $dow >= 6,
            'holiday' => $holidays[$key] ?? null,
        ];
    }
    return $out;
}

/** Wymiar czasu pracy w MINUTACH: 7 h 35 min x (dni pn-pt minus święta wypadające w dni inne niż niedziela). */
function work_norm($year, $month)
{
    $weekdays = 0;
    $holNonSunday = 0;
    foreach (month_days_info($year, $month) as $info) {
        if ($info['dow'] <= 5) {
            $weekdays++;
        }
        if ($info['holiday'] && $info['dow'] !== 7) {
            $holNonSunday++;
        }
    }
    return DAILY_NORM_MINUTES * max(0, $weekdays - $holNonSunday);
}
