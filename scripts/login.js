// ============================================================
//  login.js — Login + Registration form logic for login.html
//  Depends on: auth.js (loaded before this script)
// ============================================================

// ── Bootstrap: seed localStorage from users.json if needed ──
AUTH.init(function (err) {
    // Even if seeding fails the page stays usable (empty user list).
    if (err) console.warn('auth.js: could not fetch users.json, starting empty.', err);
});

// ── Redirect if already logged in ────────────────────────────
if (sessionStorage.getItem('loggedIn') === 'true') {
    window.location.replace('home.html');
}

// ── Open Register tab if flagged by landingPage.html ─────────
if (sessionStorage.getItem('openTab') === 'reg') {
    sessionStorage.removeItem('openTab');
    document.getElementById('panel-login').classList.remove('active');
    document.getElementById('panel-reg').classList.add('active');
    document.getElementById('tab-login').classList.remove('active');
    document.getElementById('tab-reg').classList.add('active');
}

// ── Tab switcher ─────────────────────────────────────────────
function switchTab(tab) {
    document.getElementById('panel-login').classList.toggle('active', tab === 'login');
    document.getElementById('panel-reg').classList.toggle('active',   tab === 'reg');
    document.getElementById('tab-login').classList.toggle('active',   tab === 'login');
    document.getElementById('tab-reg').classList.toggle('active',     tab === 'reg');
}

// ── LOGIN ─────────────────────────────────────────────────────
document.getElementById('loginForm').addEventListener('submit', function (e) {
    e.preventDefault();

    var idEl  = document.getElementById('student-id');
    var pwEl  = document.getElementById('login-password');
    var valid = true;

    // Clear previous errors
    document.getElementById('error-banner').style.display = 'none';
    document.getElementById('error-banner').textContent   = '';
    [idEl, pwEl].forEach(function (el) { el.classList.remove('invalid'); });
    document.getElementById('err-id').style.display = 'none';
    document.getElementById('err-pw').style.display = 'none';

    // Basic presence checks
    if (!idEl.value.trim()) {
        idEl.classList.add('invalid');
        document.getElementById('err-id').style.display = 'block';
        valid = false;
    }
    if (!pwEl.value) {
        pwEl.classList.add('invalid');
        document.getElementById('err-pw').style.display = 'block';
        valid = false;
    }
    if (!valid) return;

    // Authenticate against stored users
    var result = AUTH.login(idEl.value.trim(), pwEl.value);
    if (result.ok) {
        window.location.href = 'home.html';
    } else {
        var banner = document.getElementById('error-banner');
        banner.textContent   = result.error || 'Invalid Student ID or password. Please try again.';
        banner.style.display = 'block';
        idEl.classList.add('invalid');
        pwEl.classList.add('invalid');
    }
});

// ── REGISTER ─────────────────────────────────────────────────
(function () {
    var PATTERNS = {
        name:     /^[A-Za-z\s]{3,50}$/,
        email:    /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
        mobile:   /^[6-9]\d{9}$/,
        password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^()\-_=+]).{8,}$/,
        studentId:/^[A-Za-z0-9]{4,15}$/
    };

    function setError(fieldEl, errId, isError) {
        var errEl = document.getElementById(errId);
        if (isError) {
            fieldEl.classList.add('invalid');
            errEl.style.display = 'block';
        } else {
            fieldEl.classList.remove('invalid');
            errEl.style.display = 'none';
        }
        return isError;
    }

    // Password strength meter
    document.getElementById('reg-password').addEventListener('input', function () {
        var val = this.value, score = 0;
        if (val.length >= 8)                      score++;
        if (/[A-Z]/.test(val))                    score++;
        if (/[a-z]/.test(val))                    score++;
        if (/\d/.test(val))                       score++;
        if (/[@$!%*?&#^()\-_=+]/.test(val))       score++;

        var bar    = document.getElementById('strength-bar');
        var text   = document.getElementById('strength-text');
        var widths = ['0%', '20%', '40%', '60%', '80%', '100%'];
        var colors = ['#e2e8f0', '#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
        var labels = ['', 'Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
        bar.style.width      = widths[score];
        bar.style.background = colors[score];
        text.textContent     = val.length ? labels[score] : '';
    });

    function validateRegStudentId() {
        var el = document.getElementById('reg-student-id');
        return setError(el, 'err-reg-id', !PATTERNS.studentId.test(el.value.trim()));
    }
    function validateName()     { var el = document.getElementById('fullname');        return setError(el, 'err-fullname', !PATTERNS.name.test(el.value.trim())); }
    function validateEmail()    { var el = document.getElementById('email');           return setError(el, 'err-email',    !PATTERNS.email.test(el.value.trim())); }
    function validateMobile()   { var el = document.getElementById('mobile');          return setError(el, 'err-mobile',   !PATTERNS.mobile.test(el.value.trim())); }
    function validatePassword() { var el = document.getElementById('reg-password');    return setError(el, 'err-password', !PATTERNS.password.test(el.value)); }
    function validateConfirm() {
        var pw = document.getElementById('reg-password').value;
        var el = document.getElementById('confirm-password');
        return setError(el, 'err-confirm', el.value !== pw || el.value === '');
    }
    function validateCourse() { var el = document.getElementById('course'); return setError(el, 'err-course', el.value === ''); }
    function validateYear()   { var el = document.getElementById('year');   return setError(el, 'err-year',   el.value === ''); }
    function validateGender() {
        var radios  = document.querySelectorAll('input[name="gender"]');
        var checked = Array.prototype.some.call(radios, function (r) { return r.checked; });
        document.getElementById('err-gender').style.display = checked ? 'none' : 'block';
        return !checked;
    }
    function validateTerms() {
        var el = document.getElementById('terms');
        document.getElementById('err-terms').style.display = el.checked ? 'none' : 'block';
        return !el.checked;
    }

    // Live blur validation
    document.getElementById('reg-student-id').addEventListener('blur',    validateRegStudentId);
    document.getElementById('fullname').addEventListener('blur',           validateName);
    document.getElementById('email').addEventListener('blur',              validateEmail);
    document.getElementById('mobile').addEventListener('blur',             validateMobile);
    document.getElementById('reg-password').addEventListener('blur',       validatePassword);
    document.getElementById('confirm-password').addEventListener('blur',   validateConfirm);
    document.getElementById('course').addEventListener('change',           validateCourse);
    document.getElementById('year').addEventListener('change',             validateYear);

    // Submit
    document.getElementById('regForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var hasError = false;
        if (validateRegStudentId()) hasError = true;
        if (validateName())         hasError = true;
        if (validateEmail())        hasError = true;
        if (validateMobile())       hasError = true;
        if (validatePassword())     hasError = true;
        if (validateConfirm())      hasError = true;
        if (validateCourse())       hasError = true;
        if (validateYear())         hasError = true;
        if (validateGender())       hasError = true;
        if (validateTerms())        hasError = true;

        if (hasError) {
            var first = document.querySelector('#panel-reg .invalid');
            if (first) first.focus();
            return;
        }

        // Collect interests
        var interests = Array.prototype.map.call(
            document.querySelectorAll('input[name="interest"]:checked'),
            function (cb) { return cb.value; }
        );

        // Save to localStorage via auth.js
        var result = AUTH.register({
            studentId:  document.getElementById('reg-student-id').value,
            name:       document.getElementById('fullname').value,
            email:      document.getElementById('email').value,
            mobile:     document.getElementById('mobile').value,
            gender:     document.querySelector('input[name="gender"]:checked').value,
            password:   document.getElementById('reg-password').value,
            course:     document.getElementById('course').value,
            year:       document.getElementById('year').value,
            department: document.getElementById('department').value,
            interests:  interests
        });

        if (result.ok) {
            // Auto-logged-in by AUTH.register(); go straight to dashboard
            window.location.href = 'home.html';
        } else {
            // Highlight the specific duplicate field and show an inline error
            var fieldMap = {
                studentId: { elId: 'reg-student-id', errId: 'err-reg-id' },
                email:     { elId: 'email',           errId: 'err-email'  },
                mobile:    { elId: 'mobile',           errId: 'err-mobile' }
            };
            var target = result.field && fieldMap[result.field];
            if (target) {
                var el  = document.getElementById(target.elId);
                var err = document.getElementById(target.errId);
                el.classList.add('invalid');
                err.textContent    = result.error;
                err.style.display  = 'block';
                el.focus();
            } else {
                // Fallback banner for unexpected errors
                var banner = document.createElement('div');
                banner.style.cssText = 'background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:6px;padding:10px 14px;font-size:13px;font-weight:500;margin-bottom:14px;';
                banner.textContent   = result.error;
                var form = document.getElementById('regForm');
                form.insertBefore(banner, form.firstChild);
                setTimeout(function () { if (banner.parentNode) banner.parentNode.removeChild(banner); }, 5000);
            }
        }
    });

    // Reset clears highlights and restores default error messages
    document.getElementById('resetBtn').addEventListener('click', function () {
        document.querySelectorAll('#regForm .invalid').forEach(function (el) { el.classList.remove('invalid'); });
        document.querySelectorAll('#regForm .err').forEach(function (el) { el.style.display = 'none'; });
        // Restore default text for fields that show duplicate errors
        document.getElementById('err-reg-id').textContent = 'Student ID must be 4\u201315 alphanumeric characters.';
        document.getElementById('err-email').textContent  = 'Enter a valid email (e.g. name@domain.com).';
        document.getElementById('err-mobile').textContent = 'Valid 10-digit number starting with 6\u20139.';
        document.getElementById('strength-bar').style.width  = '0';
        document.getElementById('strength-text').textContent = '';
    });
}());
