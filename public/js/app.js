// Podstawowy kod JavaScript dla aplikacji Leasing Pro

document.addEventListener('DOMContentLoaded', function() {
    // Obsługa potwierdzenia usuwania samochodów
    const deleteButtons = document.querySelectorAll('.button-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Czy na pewno chcesz usunąć ten samochód?')) {
                e.preventDefault();
            }
        });
    });

    // Obsługa formularza dodawania samochodu
    const carForm = document.querySelector('.car-form');
    if (carForm) {
        carForm.addEventListener('submit', function(e) {
            const requiredFields = carForm.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#dc3545';
                } else {
                    field.style.borderColor = '#ddd';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Proszę wypełnić wszystkie wymagane pola.');
            }
        });
    }

    // Automatyczne ukrywanie tylko dla alertów z klasą .auto-hide
    const alerts = document.querySelectorAll('.alert.auto-hide');
    alerts.forEach(alert => {
        const delay = parseInt(alert.getAttribute('data-hide-delay')) || 5000;
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, delay);
    });
});
