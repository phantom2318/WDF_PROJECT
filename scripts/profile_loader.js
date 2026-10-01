document.addEventListener('DOMContentLoaded', function () {
    var studentId;
    var currentUser = null;

    if (typeof AUTH !== 'undefined' && AUTH.currentUser()) {
        currentUser = AUTH.currentUser();
        studentId = currentUser.studentId;
    }

    fetch('../data/profile.json')
        .then(response => {
            if (!response.ok) {
                throw new Error("Network response was not ok");
            }
            return response.json();
        })
        .then(profiles => {
            var profileData = studentId ? profiles[studentId] : null;

            // If the user is logged in (registered), use the exact data we collected during registration
            if (currentUser) {
                profileData = {
                    studentId: currentUser.studentId,
                    firstName: currentUser.firstName,
                    middleName: currentUser.middleName,
                    lastName: currentUser.lastName,
                    fullName: (currentUser.firstName + ' ' + currentUser.middleName + ' ' + currentUser.lastName).replace(/\s+/g, ' ').trim(),
                    email: currentUser.email,
                    phone: currentUser.mobile,
                    gender: currentUser.gender,
                    course: currentUser.course,
                    year: currentUser.year,
                    department: currentUser.department
                };
            } else if (!profileData) {
                var keys = Object.keys(profiles);
                if (keys.length > 0) {
                    profileData = profiles[keys[0]];
                }
            }

            if (!profileData) return;

            var elements = document.querySelectorAll('[data-profile]');
            elements.forEach(function (el) {
                var field = el.getAttribute('data-profile');
                if (profileData[field] !== undefined) {
                    if (field === "year" && el.nextSibling && el.nextSibling.nodeType === 3) {
                        el.textContent = profileData[field];
                    } else {
                        el.textContent = profileData[field];
                    }
                }
            });
        })
        .catch(error => console.error(error));
});
