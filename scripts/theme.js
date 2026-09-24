
var THEME = (function () {
    var STORAGE_KEY = 'portalTheme';

    function applyTheme(mode) {
        if (mode === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        localStorage.setItem(STORAGE_KEY, mode);
        var chk = document.getElementById('dark-mode-toggle');
        if (chk) chk.checked = (mode === 'dark');
    }

    function currentTheme() {
        return localStorage.getItem(STORAGE_KEY) || 'light';
    }

    function toggleTheme() {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    }
    applyTheme(currentTheme());

    return { applyTheme: applyTheme, currentTheme: currentTheme, toggleTheme: toggleTheme };
}());
