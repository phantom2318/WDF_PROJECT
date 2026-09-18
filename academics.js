function toggleResultForm() {
    var form = document.getElementById("result-form");
    if (form.style.display === "none") {
        form.style.display = "block";
        
        // Dynamically set max semester based on user
        var maxSem = 8; // Default
        if (typeof AUTH !== 'undefined') {
            var user = AUTH.currentUser();
            if (user && user.year) {
                // If user is in year 3, they could be in sem 5 or 6. We will set max to year * 2.
                maxSem = parseInt(user.year) * 2;
            }
        }
        document.getElementById("semester-input").setAttribute("max", maxSem);
    } else {
        form.style.display = "none";
    }
}

function displayResult() {
    var semesterInput = document.getElementById("semester-input");
    var semester = parseInt(semesterInput.value);
    var examType = document.getElementById("exam-input").value;
    var displayArea = document.getElementById("result-display");

    var maxSem = parseInt(semesterInput.getAttribute("max")) || 8;

    if (!semester || isNaN(semester)) {
        alert("Please enter a valid semester.");
        return;
    }
    
    if (semester > maxSem) {
        alert("You cannot enter a semester greater than your current maximum semester (" + maxSem + ").");
        return;
    }

    displayArea.innerHTML = "<h4>Result Details</h4>" +
                            "<p><b>Semester:</b> <span class='badge badge-info'>" + semester + "</span></p>" +
                            "<p><b>Exam Type:</b> <span class='badge badge-info'>" + examType + "</span></p>" +
                            "<div style='margin-top: 10px; padding: 10px; background: var(--surface-muted); border-radius: var(--radius-sm); border: 1px solid var(--border); color: var(--text-muted); font-size: 13px; text-align: center;'>" +
                            "<i>(Mock result data will be displayed here)</i></div>";
}
