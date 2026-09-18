// ============================================================
//  auth.js — User store backed by localStorage + users.json
//
//  Strategy:
//    • On first load the app fetches users.json and copies it into
//      localStorage under the key "portalUsers".
//    • All reads/writes go through localStorage for instant access.
//    • After every successful registration, the updated array is also
//      POSTed to POST /save-users (served by server.js) so that
//      users.json is kept in sync on disk.
//    • No external internet connection is required — server.js is a
//      plain Node.js http server using only built-in modules.
// ============================================================

var AUTH = (function () {

    var STORAGE_KEY = 'portalUsers';
    var SESSION_KEY = 'loggedInUser';  // stores studentId of current user

    // ── Internal helpers ──────────────────────────────────────

    /** Load the user array from localStorage. Returns [] if empty. */
    function loadUsers() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        } catch (e) {
            return [];
        }
    }

    /** Persist the user array to localStorage. */
    function saveUsers(users) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(users));
    }

    /**
     * Persist the user array to users.json via server.js.
     * Silently swallowed if server is not running (file:// usage).
     */
    function persistToFile(users) {
        fetch('/save-users', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(users)
        }).catch(function () {
            // server not running — localStorage is still updated, so
            // data is not lost within the current browser session.
        });
    }

    /**
     * Bootstrap: if localStorage has no users yet, fetch users.json
     * and seed it.  Accepts an optional callback(err) when done.
     */
    function init(callback) {
        if (localStorage.getItem(STORAGE_KEY) !== null) {
            if (callback) callback(null);
            return;
        }
        fetch('../data/users.json')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                saveUsers(data);
                if (callback) callback(null);
            })
            .catch(function (err) {
                // If fetch fails (e.g. file:// protocol restrictions),
                // seed with an empty array so the app still works.
                saveUsers([]);
                if (callback) callback(err);
            });
    }

    // ── Public API ────────────────────────────────────────────

    /**
     * Attempt to log in.
     * @param {string} studentId
     * @param {string} password
     * @returns {{ ok: boolean, user?: object, error?: string }}
     */
    function login(studentId, password) {
        var users = loadUsers();
        var id    = studentId.trim().toUpperCase();
        var user  = null;

        for (var i = 0; i < users.length; i++) {
            if (users[i].studentId.toUpperCase() === id) {
                user = users[i];
                break;
            }
        }

        if (!user) {
            return { ok: false, error: 'Student ID not found.' };
        }
        if (user.password !== password) {
            return { ok: false, error: 'Incorrect password.' };
        }

        // Store logged-in state
        sessionStorage.setItem('loggedIn', 'true');
        sessionStorage.setItem(SESSION_KEY, user.studentId);
        return { ok: true, user: user };
    }

    /**
     * Register a new user.
     * @param {object} data  — fields: studentId, name, email, mobile, gender,
     *                         password, course, year, department, interests
     * @returns {{ ok: boolean, error?: string }}
     */
    function register(data) {
        var users = loadUsers();

        // ── Duplicate checks (studentId, email, mobile) ──────────
        for (var i = 0; i < users.length; i++) {
            if (users[i].studentId.toUpperCase() === data.studentId.trim().toUpperCase()) {
                return { ok: false, field: 'studentId', error: 'Student ID is already registered.' };
            }
            if (users[i].email.toLowerCase() === data.email.trim().toLowerCase()) {
                return { ok: false, field: 'email', error: 'Email address is already registered.' };
            }
            if (users[i].mobile.trim() === data.mobile.trim()) {
                return { ok: false, field: 'mobile', error: 'Mobile number is already registered.' };
            }
        }

        var newUser = {
            studentId:  data.studentId.trim().toUpperCase(),
            name:       data.name.trim(),
            email:      data.email.trim().toLowerCase(),
            mobile:     data.mobile.trim(),
            gender:     data.gender,
            password:   data.password,       // plain-text (local-only project)
            course:     data.course,
            year:       data.year,
            department: data.department || '',
            interests:  data.interests  || []
        };

        users.push(newUser);
        saveUsers(users);
        persistToFile(users);   // also write back to users.json via server.js

        // Auto-login the newly registered user
        sessionStorage.setItem('loggedIn', 'true');
        sessionStorage.setItem(SESSION_KEY, newUser.studentId);

        return { ok: true };
    }

    /**
     * Return the full user object for the currently logged-in user,
     * or null if nobody is logged in.
     */
    function currentUser() {
        var id = sessionStorage.getItem(SESSION_KEY);
        if (!id) return null;
        var users = loadUsers();
        for (var i = 0; i < users.length; i++) {
            if (users[i].studentId === id) return users[i];
        }
        return null;
    }

    /** Log out the current user. */
    function logout() {
        sessionStorage.removeItem('loggedIn');
        sessionStorage.removeItem(SESSION_KEY);
        window.location.replace('landingPage.html');
    }

    return { init: init, login: login, register: register, currentUser: currentUser, logout: logout };
}());
