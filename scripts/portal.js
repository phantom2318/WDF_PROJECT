

function logout() {
    if (typeof AUTH !== 'undefined') {
        AUTH.logout();
    } else {
        sessionStorage.removeItem('loggedIn');
        window.location.replace('landingPage.html');
    }
}


function redirectIfLoggedIn() {
    if (sessionStorage.getItem('loggedIn') === 'true') {
        window.location.replace('home.html');
    }
}


function getLoggedInName() {
    if (typeof AUTH !== 'undefined') {
        var user = AUTH.currentUser();
        if (user && user.name) return user.name;
    }
    return 'User';
}
