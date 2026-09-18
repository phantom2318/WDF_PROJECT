document.addEventListener('DOMContentLoaded', function() {
    // Check if AUTH is loaded and a user is logged in
    if (typeof AUTH === 'undefined') return;
    
    var user = AUTH.currentUser();
    if (!user || !user.studentId) return;

    fetch('../data/profile.json')
        .then(response => response.json())
        .then(profiles => {
            var profileData = profiles[user.studentId];
            if (!profileData) return; // No extended profile found for this user
            
            // Find all elements that want profile data
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
