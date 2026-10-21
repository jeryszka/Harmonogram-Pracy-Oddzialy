<?php
/**
 * Eksporty:
 *   export.php?type=csv&table=2026_Październik     – grafik zespołu do Excela (CSV, separator „;")
 *   export.php?type=ics&y=2026&m=10                – własne zmiany pracownika do kalendarza (iCal)
 *   export.php?type=ics&scope=upcoming             – najbliższe zmiany pracownika
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$type = get('type');

function ics_escape($s)
{
    return str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], $s);
}

if ($type === 'csv') {
    $sch = find_schedule_by_table(get('table'));
    if (!$sch) {
        http_response_code(404);
        exit('Nie znaleziono grafiku.');
    }
    $ld   = load_schedule($sch);
    $days = $ld['days'];
    $info = month_days_info($sch['year'], $sch['month']);
    $personNorms = load_schedule_norms($sch);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="grafik_' . $sch['year'] . '_' . sprintf('%02d', $sch['month']) . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM – Excel poprawnie odczyta polskie znaki

    $head1 = [$sch['label']];
    $head2 = ['Pracownik'];
    for ($d = 1; $d <= $days; $d++) {
        $head1[] = DOW_SHORT[$info[$d]['dow']];
        $head2[] = $d;
    }
    $head1[] = 'Norma indywidualna';
    $head1[] = '';
    $head1[] = '';
    $head1[] = '';
    array_push($head2, 'Norma', 'Godziny pracy', 'Godziny nocne', 'Pod telefonem');
    fputcsv($out, $head1, ';', '"', '\\');
    fputcsv($out, $head2, ';', '"', '\\');

    foreach (emp_all() as $emp) {
        $row  = $ld['data'][(int)$emp['id']] ?? [];
        $line = [$emp['surname'] . ' ' . $emp['name']];
        for ($d = 1; $d <= $days; $d++) {
            $code   = $row[$d] ?? '';
            $line[] = $code === '7:35' ? '7h35' : $code; // Excel zamieniłby „7:35" na godzinę
        }
        $sum = schedule_summary($row, $days);
        $personNorm = $personNorms[(int)$emp['id']]['minutes'] ?? work_norm($sch['year'], $sch['month']);
        array_push($line, format_hm($personNorm), format_hm($sum['minutes']), format_hm($sum['night']), format_hm($sum['oncall']));
        fputcsv($out, $line, ';', '"', '\\');
    }
    fclose($out);
    exit;
}

if ($type === 'ics') {
    if ($user['role'] !== 'employee') {
        http_response_code(403);
        exit('Eksport kalendarza jest dostępny dla pracowników.');
    }
    $empId  = (int)$user['id'];
    $events = []; // [DateTimeImmutable, kod]

    if (get('scope') === 'upcoming') {
        foreach (upcoming_shifts_for($empId, 100, 3) as $s) {
            $events[] = [$s['date'], $s['code'], $s['info']];
        }
        $fileName = 'grafik_nadchodzace.ics';
    } else {
        list($y, $m) = request_month();
        $sch = find_schedule($y, $m);
        if ($sch) {
            $ld  = load_schedule($sch);
            $row = $ld['data'][$empId] ?? [];
            for ($d = 1; $d <= $ld['days']; $d++) {
                if (($row[$d] ?? '') !== '') {
                    $events[] = [new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $d)), $row[$d], shift_info($row[$d])];
                }
            }
        }
        $fileName = 'grafik_' . $y . '_' . sprintf('%02d', $m) . '.ics';
    }

    header('Content-Type: text/calendar; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');

    $stamp = gmdate('Ymd\THis\Z');
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Harmonogram Pracy//PL',
        'CALSCALE:GREGORIAN',
        'X-WR-CALNAME:Mój grafik pracy',
    ];
    foreach ($events as $ev) {
        $date = $ev[0];
        $code = $ev[1];
        $inf  = $ev[2] ?? shift_info($code);
        $summary = (!empty($inf['work']) ? 'Dyżur: ' : '') . $inf['label'] . ' (' . $code . ')';
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $date->format('Ymd') . '-' . $empId . '-' . $inf['key'] . '@harmonogram-pracy';
        $lines[] = 'DTSTAMP:' . $stamp;
        $lines[] = 'DTSTART;VALUE=DATE:' . $date->format('Ymd');
        $lines[] = 'DTEND;VALUE=DATE:' . $date->modify('+1 day')->format('Ymd');
        $lines[] = 'SUMMARY:' . ics_escape($summary);
        $lines[] = 'TRANSP:TRANSPARENT';
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    echo implode("\r\n", $lines) . "\r\n";
    exit;
}

http_response_code(400);
echo 'Nieznany typ eksportu.';
