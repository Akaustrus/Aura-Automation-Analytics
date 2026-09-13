(function () {
    const storageKey = 'aura-theme';
    const legacyKey = 'aura-portal-theme';
    const savedTheme = localStorage.getItem(storageKey) || localStorage.getItem(legacyKey) || 'light';
    document.documentElement.dataset.theme = savedTheme === 'dark' ? 'dark' : 'light';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-theme-toggle]').forEach(function (toggle) {
            function refresh() {
                const dark = document.documentElement.dataset.theme === 'dark';
                toggle.setAttribute('aria-pressed', String(dark));
                toggle.setAttribute('aria-label', dark ? 'Activar modo claro' : 'Activar modo oscuro');
                const icon = toggle.querySelector('i');
                if (icon) icon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            }

            toggle.addEventListener('click', function () {
                const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
                document.documentElement.dataset.theme = next;
                localStorage.setItem(storageKey, next);
                localStorage.removeItem(legacyKey);
                refresh();
            });
            refresh();
        });
    });
}());
