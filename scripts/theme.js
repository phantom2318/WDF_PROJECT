// ============================================================
//  theme.js — Dark / light mode manager for the Student Portal
//
//  Strategy:
//    • Reads 'portalTheme' from localStorage on every page load.
//    • Default is 'light' (no class on <html>).
//    • Dark mode adds the class "dark" to <html>.
//    • The <script> tag must be in <head> (before any CSS renders)
//      so there is NO flash of the wrong theme on load.
//
//  Public API:
//    toggleTheme()      — flip between light and dark, save to localStorage
//    applyTheme(mode)   — explicitly set 'light' or 'dark'
//    currentTheme()     — returns 'light' or 'dark'
// ============================================================

var THEME = (function () {
    var STORAGE_KEY = 'portalTheme';

    function applyTheme(mode) {
        if (mode === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        localStorage.setItem(STORAGE_KEY, mode);
        // Keep any toggle switch on the page in sync
        var chk = document.getElementById('dark-mode-toggle');
        if (chk) chk.checked = (mode === 'dark');
    }

    function currentTheme() {
        return localStorage.getItem(STORAGE_KEY) || 'light';
    }

    function toggleTheme() {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    }

    // ── Apply immediately on script execution (in <head>) ────
    applyTheme(currentTheme());

    return { applyTheme: applyTheme, currentTheme: currentTheme, toggleTheme: toggleTheme };
}());
