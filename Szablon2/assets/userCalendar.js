/* Kalendarz pracownika: kliknięcie w dzień pokazuje własną zmianę oraz osoby pracujące tego dnia. */
(function () {
    'use strict';

    const holder = document.getElementById('day-data');
    const cal = document.getElementById('calendar');
    const box = document.getElementById('dayDetail');
    if (!holder || !cal || !box) return;

    const data = JSON.parse(holder.textContent);
    let selected = null;

    function el(tag, cls, text) {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }

    function badge(code) {
        const info = data.shifts[code];
        const b = el('span', 'shift shift-' + (info ? info.key : 'X'), code);
        if (info) b.title = info.label;
        return b;
    }

    function render(d) {
        const day = data.days[d];
        box.textContent = '';

        const dow = data.dowNames[data.dow[d]];
        box.appendChild(el('div', 'dd-title', dow + ', ' + d + ' ' + data.monthGen + ' ' + data.year));

        const sub = el('div', 'dd-sub');
        if (data.holiday[d]) sub.appendChild(el('div', '', 'Święto: ' + data.holiday[d]));
        if (day.mine) {
            const info = data.shifts[day.mine];
            const row = el('div', '');
            row.style.cssText = 'display:flex;align-items:center;gap:8px;margin-top:4px';
            row.appendChild(badge(day.mine));
            row.appendChild(el('span', '', 'Twoja zmiana: ' + (info ? info.label : day.mine)));
            sub.appendChild(row);
        } else {
            sub.appendChild(el('div', '', 'Nie masz wpisanej zmiany tego dnia.'));
        }
        box.appendChild(sub);

        // Pozostali pracownicy pogrupowani wg zmiany
        const groups = {};
        day.team.forEach(function (p) { (groups[p.c] = groups[p.c] || []).push(p.n); });
        const codes = Object.keys(data.shifts).filter(function (c) { return groups[c]; });
        if (!codes.length) {
            box.appendChild(el('div', 'dd-group', 'Nikt inny nie ma wpisanej zmiany tego dnia.'));
            return;
        }
        codes.forEach(function (code) {
            const g = el('div', 'dd-group');
            const t = el('div', 'dd-group-title');
            t.appendChild(badge(code));
            t.appendChild(document.createTextNode(' ' + data.shifts[code].label + ' · ' + groups[code].length));
            t.style.cssText = 'display:flex;align-items:center;gap:6px';
            g.appendChild(t);
            const chips = el('div', 'chip-row');
            groups[code].forEach(function (name) { chips.appendChild(el('span', 'chip', name)); });
            chips.querySelectorAll('.chip').forEach(function (c) { c.style.padding = '4px 10px'; });
            g.appendChild(chips);
            box.appendChild(g);
        });
    }

    function select(cell) {
        if (selected) selected.classList.remove('selected');
        selected = cell;
        cell.classList.add('selected');
        render(+cell.dataset.d);
    }

    cal.addEventListener('click', function (e) {
        const cell = e.target.closest('.cal-day[data-d]');
        if (cell) select(cell);
    });
    cal.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const cell = e.target.closest('.cal-day[data-d]');
        if (cell) { e.preventDefault(); select(cell); }
    });

    // Na start – dzisiejszy dzień (jeśli oglądamy bieżący miesiąc)
    if (data.today) {
        const t = cal.querySelector('.cal-day[data-d="' + data.today + '"]');
        if (t) select(t);
    }
})();
