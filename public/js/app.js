document.addEventListener('DOMContentLoaded', function() {
    // Czyszczenie dat po kliknięciu w logo/home
    const homeLink = document.getElementById('home-link');
    if (homeLink) {
        homeLink.addEventListener('click', function() {
            localStorage.removeItem('date_from');
            localStorage.removeItem('date_to');
        });
    }

    // Walidacja: data wypożyczenia nie może być datą zwrotu
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    if (dateFrom && dateTo) {
        function validateDates() {
            if (dateFrom.value && dateTo.value && dateFrom.value === dateTo.value) {
                dateTo.setCustomValidity('Data wypożyczenia nie może być datą zwrotu!');
            } else {
                dateTo.setCustomValidity('');
            }
        }
        dateFrom.addEventListener('change', validateDates);
        dateTo.addEventListener('change', validateDates);
    }

    // Walidacja: data od nie może być po dacie do (dla home.twig)
    const from = document.getElementById('from');
    const to = document.getElementById('to');
    if (from && to) {
        from.addEventListener('change', function() {
            to.min = from.value;
        });
        to.addEventListener('change', function() {
            from.max = to.value;
        });
    }
});