// ============================================================
//  portal.js — Shared auth helpers used by every portal page
// ============================================================

/**
 * Clears the session and sends the user to the public landing page.
 * Called by the Logout button on every page.
 */
function logout() {
    sessionStorage.removeItem('loggedIn');
    window.location.replace('landingPage.html');
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
