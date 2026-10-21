/*
 * Edytor grafiku.
 *  - „pędzel": wybierz zmianę na pasku i klikaj / przeciągaj po komórkach (ponowne kliknięcie tej samej zmiany czyści komórkę)
 *  - klawiatura: strzałki = ruch, klawisz zmiany (pokazany na przycisku) = wpisz zmianę i przejdź dalej, Delete = wyczyść
 *  - Ctrl+Z / Ctrl+Y = cofnij / ponów, Ctrl+S = zapisz
 */
(function () {
    'use strict';

    const holder = document.getElementById('editor-data');
    if (!holder) return;

    const cfg    = JSON.parse(holder.textContent);
    const days   = cfg.days;
    const shifts = cfg.shifts;
    const order  = cfg.employees.map(function (e) { return e.id; });
    const norms  = {};
    const employeesById = {};
    cfg.employees.forEach(function (employee) {
        employeesById[employee.id] = employee;
        norms[employee.id] = employee.norm || cfg.norm;
    });
    const offDays = cfg.offDays || [];

    /* ---------- Stan ---------- */
    const state = {};
    order.forEach(function (id) {
        const row = (cfg.data[id] || []).slice(0, days);
        while (row.length < days) row.push('');
        state[id] = row;
    });
    const initialJson = JSON.stringify(state);

    const table = document.getElementById('grid');
    const cells = {};
    table.querySelectorAll('td.cell').forEach(function (td) {
        cells[td.dataset.e + ':' + td.dataset.d] = td;
    });
    const sumCells = {};
    table.querySelectorAll('td.sum').forEach(function (td) { sumCells[td.dataset.e] = td; });
    const cov = { day: {}, night: {}, off: {} };
    table.querySelectorAll('tfoot td[data-cov]').forEach(function (td) { cov[td.dataset.cov][td.dataset.d] = td; });

    const btnUndo = document.getElementById('btnUndo');
    const btnRedo = document.getElementById('btnRedo');
    const dirtyBadge = document.getElementById('dirtyBadge');
    const saveForm = document.getElementById('saveForm');
    const dataField = document.getElementById('dataField');
    const saveBtn = document.getElementById('saveBtn');

    /* ---------- Rysowanie ---------- */
    function render(e, d) {
        const td = cells[e + ':' + d];
        if (!td) return;
        const v = state[e][d - 1];
        td.textContent = v;
        if (v) td.dataset.k = shifts[v] ? shifts[v].key : 'X';
        else delete td.dataset.k;
        td.title = v && shifts[v] ? shifts[v].label : '';
    }

    function fmt(minutes) {
        const h = Math.floor(minutes / 60), m = minutes % 60;
        return h + ':' + (m < 10 ? '0' : '') + m;
    }

    function refreshRow(e) {
        let work = 0, oncall = 0;
        state[e].forEach(function (v) {
            if (v && shifts[v]) { work += shifts[v].minutes; oncall += shifts[v].oncall; }
        });
        const td = sumCells[e];
        if (!td) return;
        td.querySelector('.sum-h').textContent = fmt(work);
        const c = td.querySelector('.sum-c');
        if (c) c.textContent = oncall ? 'tel. ' + fmt(oncall) : '';
        const target = norms[e] || cfg.norm;
        const normLabel = td.querySelector('.sum-norm');
        if (normLabel) {
            const employee = employeesById[e];
            const details = employee
                ? employee.workDays + ' dni · ' + employee.shiftCount + ' × ' + employee.shiftHours + ' h' + (employee.adjustmentMinutes ? ' + ' + fmt(employee.adjustmentMinutes) + ' wyr.' : '')
                : 'norma ' + fmt(target);
            normLabel.textContent = details;
            normLabel.title = 'Indywidualna norma: ' + fmt(target);
        }
        td.classList.remove('ok', 'over');
        if (work > target) td.classList.add('over');
        else if (work === target && work > 0) td.classList.add('ok');
    }

    function refreshCol(d) {
        let day = 0, night = 0, off = 0;
        order.forEach(function (id) {
            const v = state[id][d - 1];
            const cover = v && shifts[v] ? shifts[v].cover : 'none';
            if (cover === 'day') day++;
            else if (cover === 'night') night++;
            else if (cover === 'both') { day++; night++; }
            else if (cover === 'off') off++;
        });
        if (cov.day[d]) {
            cov.day[d].textContent = day;
            cov.day[d].classList.toggle('low', day === 0);
        }
        if (cov.night[d]) {
            cov.night[d].textContent = night;
            cov.night[d].classList.toggle('low', night === 0);
        }
        if (cov.off[d]) cov.off[d].textContent = off || '.';
    }

    function renderAll() {
        order.forEach(function (id) {
            for (let d = 1; d <= days; d++) render(id, d);
            refreshRow(id);
        });
        for (let d = 1; d <= days; d++) refreshCol(d);
    }

    /* ---------- Zmiany i historia ---------- */
    let stroke = null;
    const undoStack = [];
    const redoStack = [];
    let submitting = false;

    function isDirty() {
        return JSON.stringify(state) !== initialJson;
    }

    function updateUi() {
        const dirty = isDirty();
        if (dirtyBadge) dirtyBadge.classList.toggle('show', dirty);
        if (btnUndo) btnUndo.disabled = undoStack.length === 0;
        if (btnRedo) btnRedo.disabled = redoStack.length === 0;
    }

    function beginStroke() { stroke = []; }
    function endStroke() {
        if (stroke && stroke.length) {
            undoStack.push(stroke);
            redoStack.length = 0;
        }
        stroke = null;
        updateUi();
    }

    function setCell(e, d, v) {
        const old = state[e][d - 1];
        if (old === v) return false;
        state[e][d - 1] = v;
        if (stroke) stroke.push([e, d, old, v]);
        render(e, d);
        refreshRow(e);
        refreshCol(d);
        return true;
    }

    function applyEntries(entries, useNew) {
        entries.forEach(function (it) {
            state[it[0]][it[1] - 1] = useNew ? it[3] : it[2];
            render(it[0], it[1]);
        });
        const rows = {}, cols = {};
        entries.forEach(function (it) { rows[it[0]] = 1; cols[it[1]] = 1; });
        Object.keys(rows).forEach(function (e) { refreshRow(+e); });
        Object.keys(cols).forEach(function (d) { refreshCol(+d); });
    }

    function undo() {
        const s = undoStack.pop();
        if (!s) return;
        applyEntries(s.slice().reverse(), false);
        redoStack.push(s);
        updateUi();
    }

    function redo() {
        const s = redoStack.pop();
        if (!s) return;
        applyEntries(s, true);
        undoStack.push(s);
        updateUi();
    }

    /* ---------- Pędzel ---------- */
    let brush = 'D';
    const brushBtns = Array.from(document.querySelectorAll('[data-brush]'));
    function setBrush(value) {
        brush = value;
        brushBtns.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-brush') === value); });
    }
    brushBtns.forEach(function (b) {
        b.addEventListener('click', function () { setBrush(b.getAttribute('data-brush')); });
    });
    setBrush('D');

    /* ---------- Mysz: klik i przeciąganie ---------- */
    let painting = false;
    let dragValue = '';

    table.addEventListener('mousedown', function (ev) {
        if (ev.button !== 0) return;
        const td = ev.target.closest('td.cell');
        if (!td) return;
        ev.preventDefault();
        td.focus();
        const e = +td.dataset.e, d = +td.dataset.d;
        beginStroke();
        dragValue = state[e][d - 1] === brush ? '' : brush;
        setCell(e, d, dragValue);
        painting = true;
    });

    table.addEventListener('mouseover', function (ev) {
        if (!painting) return;
        const td = ev.target.closest('td.cell');
        if (td) setCell(+td.dataset.e, +td.dataset.d, dragValue);
    });

    document.addEventListener('mouseup', function () {
        if (painting) {
            painting = false;
            endStroke();
        }
    });

    /* ---------- Klawiatura ---------- */
    // klawisze skrótów pochodzą ze słownika dyżurów (pole hotkey)
    const letterMap = {};
    Object.keys(shifts).forEach(function (code) {
        if (shifts[code].hotkey) letterMap[shifts[code].hotkey] = code;
    });

    function focusCell(e, d) {
        const td = cells[e + ':' + d];
        if (td) td.focus();
    }

    table.addEventListener('keydown', function (ev) {
        const td = ev.target.closest('td.cell');
        if (!td || ev.ctrlKey || ev.metaKey || ev.altKey) return;
        const e = +td.dataset.e, d = +td.dataset.d;
        const idx = order.indexOf(e);
        const key = ev.key;

        if (key === 'ArrowRight' && d < days) { ev.preventDefault(); focusCell(e, d + 1); return; }
        if (key === 'ArrowLeft' && d > 1) { ev.preventDefault(); focusCell(e, d - 1); return; }
        if (key === 'ArrowDown' && idx < order.length - 1) { ev.preventDefault(); focusCell(order[idx + 1], d); return; }
        if (key === 'ArrowUp' && idx > 0) { ev.preventDefault(); focusCell(order[idx - 1], d); return; }

        let value = null;
        if (key === 'Delete' || key === 'Backspace' || key === '0') value = '';
        else if (key === ' ' || key === 'Enter') value = state[e][d - 1] === brush ? '' : brush;
        else if (key.length === 1 && Object.prototype.hasOwnProperty.call(letterMap, key.toLowerCase())) value = letterMap[key.toLowerCase()];

        if (value === null) return;
        ev.preventDefault();
        beginStroke();
        setCell(e, d, value);
        endStroke();
        if (key !== 'Delete' && key !== 'Backspace' && d < days) focusCell(e, d + 1);
    });

    document.addEventListener('keydown', function (ev) {
        if (!(ev.ctrlKey || ev.metaKey)) return;
        const k = ev.key.toLowerCase();
        if (k === 'z' && !ev.shiftKey) { ev.preventDefault(); undo(); }
        else if (k === 'y' || (k === 'z' && ev.shiftKey)) { ev.preventDefault(); redo(); }
        else if (k === 's') { ev.preventDefault(); if (saveForm) saveForm.requestSubmit ? saveForm.requestSubmit() : saveForm.submit(); }
    });

    /* ---------- Akcje wierszy i całego grafiku ---------- */
    table.addEventListener('click', function (ev) {
        const btn = ev.target.closest('[data-act]');
        if (!btn) return;
        const e = +btn.dataset.e;
        beginStroke();
        if (btn.dataset.act === 'clear') {
            for (let d = 1; d <= days; d++) setCell(e, d, '');
        } else if (btn.dataset.act === 'fill') {
            if (brush === '') {
                endStroke();
                if (window.toast) window.toast('Wybierz zmianę na pasku, aby wypełnić dni robocze.');
                return;
            }
            for (let d = 1; d <= days; d++) {
                if (offDays.indexOf(d) === -1) setCell(e, d, brush);
            }
        }
        endStroke();
    });

    const clearAll = document.getElementById('btnClearAll');
    if (clearAll) {
        clearAll.addEventListener('click', function () {
            if (!window.confirm('Wyczyścić wszystkie zmiany w tym grafiku? (możesz to cofnąć Ctrl+Z)')) return;
            beginStroke();
            order.forEach(function (id) { for (let d = 1; d <= days; d++) setCell(id, d, ''); });
            endStroke();
        });
    }

    if (btnUndo) btnUndo.addEventListener('click', undo);
    if (btnRedo) btnRedo.addEventListener('click', redo);

    /* ---------- Zapis ---------- */
    if (saveForm) {
        saveForm.addEventListener('submit', function () {
            dataField.value = JSON.stringify(state);
            submitting = true;
            if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Zapisywanie…'; }
        });
    }

    const customShiftForm = document.querySelector('.custom-shift-form');
    if (customShiftForm) {
        customShiftForm.addEventListener('submit', function () {
            customShiftForm.querySelector('.pending-data-field').value = JSON.stringify(state);
            submitting = true;
        });
    }

    window.addEventListener('beforeunload', function (ev) {
        if (isDirty() && !submitting) {
            ev.preventDefault();
            ev.returnValue = '';
        }
    });

    /* ---------- Start ---------- */
    renderAll();
    updateUi();
})();
