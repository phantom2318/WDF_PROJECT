function filterClubs(category) {
    const clubRows = document.querySelectorAll('.club-row');

    clubRows.forEach(row => {
        if (category === 'All' || row.dataset.category === category) {
            row.style.display = ''; // Show row
        } else {
            row.style.display = 'none'; // Hide row
        }
    });
}
