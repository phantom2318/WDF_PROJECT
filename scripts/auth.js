var AUTH = (
    function () 
{
    var STORAGE_KEY = 'portalUsers';
    var SESSION_KEY = 'loggedInUser';
    function loadUsers() 
    {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        } catch (e) {
            return [];
        }
    }
    function saveUsers(users) 
    {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(users));
    }
    
    function persistToFile(users) 
    {
        fetch('/save-users', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(users)
        }).catch(function () {
        });
    }


    function init(callback) 
    {
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
                saveUsers([]);
                if (callback) callback(err);
            });
    }


    function login(studentId, password) 
    {
        var users = loadUsers();
        var id    = studentId.trim().toUpperCase();
        var user  = null;

        for (var i = 0; i < users.length; i++) {
            if (users[i].studentId.toUpperCase() === id) {
                user = users[i];
                break;
            }
        }

        if (!user) 
            {
            return { ok: false, error: 'Student ID not found.' };
        }
        if (user.password !== password) 
            {
            return { ok: false, error: 'Incorrect password.' };
        }
        sessionStorage.setItem('loggedIn', 'true');
        sessionStorage.setItem(SESSION_KEY, user.studentId);
        return { ok: true, user: user };
    }


    function register(data) 
    {
        var users = loadUsers();
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

        var newUser = 
        {
            studentId:  data.studentId.trim(),
            firstName:  data.firstName.trim(),
            middleName: data.middleName.trim(),
            lastName:   data.lastName.trim(),
            email:      data.email.trim(),
            mobile:     data.mobile.trim(),
            gender:     data.gender,
            password:   data.password,
            course:     data.course,
            year:       data.year,
            department: data.department || ''
        };

        users.push(newUser);
        saveUsers(users);
        persistToFile(users);
        sessionStorage.setItem('loggedIn', 'true');
        sessionStorage.setItem(SESSION_KEY, newUser.studentId);

        return { ok: true };
    }


    function currentUser() 
    {
        var id = sessionStorage.getItem(SESSION_KEY);
        if (!id) return null;
        var users = loadUsers();
        for (var i = 0; i < users.length; i++) {
            if (users[i].studentId === id) return users[i];
        }
        return null;
    }


    function logout() 
    {
        sessionStorage.removeItem('loggedIn');
        sessionStorage.removeItem(SESSION_KEY);
        window.location.replace('landingPage.html');
    }

    return { init: init, login: login, register: register, currentUser: currentUser, logout: logout };
}());
