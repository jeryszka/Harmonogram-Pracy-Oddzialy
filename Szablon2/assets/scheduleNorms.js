/* Wspólny podgląd norm na formularzu tworzenia i edycji wymiaru grafiku. */
(function () {
    'use strict';

    function formatHours(minutes) {
        var hours = Math.floor(minutes / 60);
        var remainder = minutes % 60;
        return hours + ' h' + (remainder ? ' ' + remainder + ' min' : '');
    }

    function updateRow(row) {
        var daysInput = row.querySelector('.setup-days');
        var hoursSelect = row.querySelector('.setup-hours');
        if (!daysInput || !hoursSelect) return;

        var days = Math.max(0, parseInt(daysInput.value, 10) || 0);
        var shiftHours = parseInt(hoursSelect.value, 10);
        var total = days * 455;
        var shiftMinutes = shiftHours * 60;
        var count = Math.floor(total / shiftMinutes);
        var adjustment = total % shiftMinutes;
        row.querySelector('.setup-total').textContent = formatHours(total);
        row.querySelector('.setup-breakdown').textContent = count + ' × ' + shiftHours + ' h'
            + (adjustment ? ' + ' + formatHours(adjustment) + ' wyrównawcze' : '');
    }

    document.querySelectorAll('.setup-table tbody tr').forEach(function (row) {
        var daysInput = row.querySelector('.setup-days');
        var hoursSelect = row.querySelector('.setup-hours');
        if (!daysInput || !hoursSelect) return;
        daysInput.addEventListener('input', function () { updateRow(row); });
        hoursSelect.addEventListener('change', function () { updateRow(row); });
        updateRow(row);
    });

    var yearInput = document.getElementById('year');
    var monthInput = document.getElementById('month');
    if (yearInput && monthInput) {
        function updateMonthLimit() {
            var year = parseInt(yearInput.value, 10);
            var month = parseInt(monthInput.value, 10);
            if (!year || !month) return;
            var maxDays = new Date(year, month, 0).getDate();
            document.querySelectorAll('.setup-days').forEach(function (input) {
                input.max = maxDays;
                if ((parseInt(input.value, 10) || 0) > maxDays) input.value = maxDays;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }
        yearInput.addEventListener('change', updateMonthLimit);
        monthInput.addEventListener('change', updateMonthLimit);
    }

    var form = document.querySelector('.schedule-norm-form');
    if (!form) return;
    var initial = Array.prototype.map.call(form.elements, function (field) {
        return [field.name, field.value];
    });
    var submitted = false;
    function isDirty() {
        var current = Array.prototype.map.call(form.elements, function (field) {
            return [field.name, field.value];
        });
        return JSON.stringify(current) !== JSON.stringify(initial);
    }

    form.addEventListener('submit', function () { submitted = true; });
    window.addEventListener('beforeunload', function (event) {
        if (isDirty() && !submitted) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}());
