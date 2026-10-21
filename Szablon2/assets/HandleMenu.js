/* Menu boczne: przycisk „hamburger" zwija/rozwija panel (stan pamiętany), na telefonie panel wysuwa się nad treść. */
(function () {
    'use strict';

    const root     = document.documentElement;
    const btn      = document.getElementById('menuBtn');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!btn) return;

    const isMobile = function () { return window.innerWidth <= 900; };

    btn.addEventListener('click', function () {
        if (isMobile()) {
            root.classList.toggle('sidebar-open');
            return;
        }
        const collapsed = root.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) { /* brak localStorage */ }
    });

    if (backdrop) backdrop.addEventListener('click', function () { root.classList.remove('sidebar-open'); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') root.classList.remove('sidebar-open');
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) root.classList.remove('sidebar-open');
    });
})();
