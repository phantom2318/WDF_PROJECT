// ============================================================
//  portal.js — Shared auth helpers used by every portal page
// ============================================================

/**
 * Clears the session and sends the user to the public landing page.
 * Called by the Logout button on every page.
 * Also delegates to AUTH.logout() if auth.js is loaded.
 */
function logout() {
    if (typeof AUTH !== 'undefined') {
        AUTH.logout();
    } else {
        sessionStorage.removeItem('loggedIn');
        window.location.replace('landingPage.html');
    }
}

/**
 * Redirects an already-logged-in user straight to the dashboard,
 * skipping the public landing page / login page.
 * Call this at the top of landingPage.html and login.html.
 */
function redirectIfLoggedIn() {
    if (sessionStorage.getItem('loggedIn') === 'true') {
        window.location.replace('home.html');
    }
}

/**
 * Returns the display name of the currently logged-in user,
 * or "User" as a fallback.
 * Requires auth.js to be loaded on the same page.
 */
function getLoggedInName() {
    if (typeof AUTH !== 'undefined') {
        var user = AUTH.currentUser();
        if (user && user.name) return user.name;
    }
    return 'User';
}
