
(function () {
    var registerButton = document.getElementById('register-btn');

    if (registerButton) {
        registerButton.addEventListener('click', function () {
            sessionStorage.setItem('openTab', 'reg');
        });
    }

    redirectIfLoggedIn();
}());
