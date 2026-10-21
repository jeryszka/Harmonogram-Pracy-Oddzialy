/* Wspólne elementy interfejsu: toasty, modale, potwierdzenia, filtr tabel, pokazywanie hasła. */
(function () {
    'use strict';

    const $  = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

    /* ---------- Toast ---------- */
    window.toast = function (message, type) {
        const stack = document.getElementById('toastStack');
        if (!stack) return;
        const el = document.createElement('div');
        el.className = 'toast ' + (type || '');
        el.textContent = message;
        stack.appendChild(el);
        setTimeout(function () {
            el.style.transition = 'opacity .3s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 3200);
    };

    /* ---------- Modale ---------- */
    function openModal(modal) {
        modal.classList.add('open');
        const focusEl = $('[data-autofocus]', modal) || $('input:not([type=hidden]), select, button', modal);
        if (focusEl) setTimeout(function () { focusEl.focus(); }, 40);
    }
    function closeModal(modal) { modal.classList.remove('open'); }
    window.openModal = openModal;
    window.closeModal = closeModal;

    document.addEventListener('click', function (e) {
        const opener = e.target.closest('[data-modal-open]');
        if (opener) {
            e.preventDefault();
            const modal = document.getElementById(opener.getAttribute('data-modal-open'));
            if (modal) {
                document.dispatchEvent(new CustomEvent('modal:open', { detail: { modal: modal, trigger: opener } }));
                openModal(modal);
            }
            return;
        }
        if (e.target.closest('[data-modal-close]')) {
            const modal = e.target.closest('.modal-overlay');
            if (modal) closeModal(modal);
            return;
        }
        if (e.target.classList && e.target.classList.contains('modal-overlay')) closeModal(e.target);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') $$('.modal-overlay.open').forEach(closeModal);
    });

    /* ---------- Potwierdzenie wysłania formularza: <form data-confirm="Tekst"> ---------- */
    let confirmModal = null;
    function getConfirmModal() {
        if (confirmModal) return confirmModal;
        confirmModal = document.createElement('div');
        confirmModal.className = 'modal-overlay';
        confirmModal.innerHTML =
            '<div class="modal-box" role="dialog" aria-modal="true">' +
            '<h3 data-title>Potwierdź</h3><p class="modal-text" data-text></p>' +
            '<div class="modal-actions">' +
            '<button type="button" class="btn btn-secondary" data-modal-close>Anuluj</button>' +
            '<button type="button" class="btn btn-danger-solid" data-yes data-autofocus>Potwierdź</button>' +
            '</div></div>';
        document.body.appendChild(confirmModal);
        return confirmModal;
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form.hasAttribute || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') return;
        e.preventDefault();
        const modal = getConfirmModal();
        $('[data-title]', modal).textContent = form.getAttribute('data-confirm-title') || 'Potwierdź';
        $('[data-text]', modal).textContent = form.getAttribute('data-confirm');
        const yes = $('[data-yes]', modal);
        yes.textContent = form.getAttribute('data-confirm-btn') || 'Potwierdź';
        yes.onclick = function () {
            closeModal(modal);
            form.dataset.confirmed = '1';
            form.submit();
        };
        openModal(modal);
    }, true);

    /* ---------- Filtr tabeli: <input data-filter="#tabela"> + <tr data-search="tekst"> ---------- */
    function normalize(s) {
        return String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l');
    }
    $$('[data-filter]').forEach(function (input) {
        const target = $(input.getAttribute('data-filter'));
        if (!target) return;
        input.addEventListener('input', function () {
            const q = normalize(input.value.trim());
            let shown = 0;
            $$('tbody tr[data-search]', target).forEach(function (tr) {
                const hit = normalize(tr.getAttribute('data-search')).indexOf(q) !== -1;
                tr.hidden = !hit;
                if (hit) shown++;
            });
            const empty = $('.no-results', target.closest('.card') || target.parentElement);
            if (empty) empty.hidden = shown !== 0;
        });
    });

    /* ---------- Pokaż / ukryj hasło: <button data-toggle-pass="#pole"> ---------- */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-toggle-pass]');
        if (!btn) return;
        const input = $(btn.getAttribute('data-toggle-pass'));
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Ukryj' : 'Pokaż';
    });

    /* ---------- Zniknięcie komunikatów sukcesu ---------- */
    $$('.alert-success').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 6000);
    });
})();
