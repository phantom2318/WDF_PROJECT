document.addEventListener('DOMContentLoaded', function() {
    if (typeof AUTH === 'undefined') return;

    var user = AUTH.currentUser();
    if (!user || !user.studentId) return;

    fetch('../data/profile.json')
        .then(response => response.json())
        .then(profiles => {
            var profileData = profiles[user.studentId];
            if (!profileData) return;
            var elements = document.querySelectorAll('[data-profile]');
            elements.forEach(function(el) {
                var field = el.getAttribute('data-profile');
                if (profileData[field] !== undefined) {
                    el.textContent = profileData[field];
                }
            });
        })
        .catch(error => console.error('Error loading profile.json:', error));
});
