// ============================================
//   SMARTKAS — THEME TOGGLE (pill switch)
// ============================================

(function() {
    const STORAGE_KEY = 'smartkas-theme';
    const saved = localStorage.getItem(STORAGE_KEY);
    const theme = saved || 'dark';
    document.documentElement.setAttribute('data-theme', theme);

    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('themeCheckbox');
        if (!checkbox) return;

        // Light = checked, Dark = unchecked
        checkbox.checked = (theme === 'light');

        checkbox.addEventListener('change', function() {
            const next = this.checked ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem(STORAGE_KEY, next);
        });
    });
})();
