# New Application Flow - Wypożyczalnia Samochodów

## 🔄 **Zmiany w przepływie aplikacji**

### **Przed (stary przepływ)**
1. Użytkownik wchodzi na stronę główną
2. Musi przejść weryfikację reCAPTCHA
3. Po weryfikacji jest przekierowany do listy ofert
4. Na liście ofert wybiera daty
5. Przegląda dostępne samochody
6. Rezerwuje samochód

### **Po (nowy przepływ)**
1. **Użytkownik wchodzi na stronę główną** ← **NOWE: Wybór dat**
2. **Wybiera datę rozpoczęcia i zakończenia** ← **NOWE: Formularz dat**
3. **Jest przekierowany do listy ofert** ← **NOWE: Automatyczne przekierowanie**
4. **Przegląda dostępne samochody** ← **Bez zmian**
5. **Może zmienić termin** ← **NOWE: Przycisk "Zmień termin"**
6. **Rezerwuje samochód** ← **NOWE: reCAPTCHA na formularzu rezerwacji**

## 🏠 **Strona główna (`/`)**

### **Nowa funkcjonalność**
- ✅ **Formularz wyboru dat** - główny element strony
- ✅ **Walidacja dat** - sprawdza poprawność zakresu
- ✅ **Błędy walidacji** - czytelne komunikaty błędów
- ✅ **Responsywny design** - nowoczesny wygląd

### **Walidacja dat**
- Data początkowa nie może być w przeszłości
- Data końcowa musi być późniejsza niż początkowa
- Automatyczne przekierowanie z błędem jeśli daty są nieprawidłowe

### **Przykład błędu**
```
URL: /?date_error=Data_początkowa_nie_może_być_w_przeszłości
Komunikat: "Błąd: Data początkowa nie może być w przeszłości. Wybierz datę od dzisiaj."
```

## 🚗 **Lista ofert (`/offers`)**

### **Nowa funkcjonalność**
- ✅ **Brak formularza dat** - daty są już wybrane
- ✅ **Przycisk "Zmień termin"** - powrót do strony głównej
- ✅ **Wyświetlanie wybranych dat** - informacja o terminie
- ✅ **Automatyczne przekierowanie** - jeśli brak dat

### **Przycisk zmiany terminu**
```html
<a href="/" class="btn btn-outline-primary">
    <i class="fas fa-calendar-alt me-2"></i>Zmień termin
</a>
```

## 📝 **Formularz rezerwacji**

### **Nowa funkcjonalność**
- ✅ **reCAPTCHA** - weryfikacja przed rezerwacją
- ✅ **Walidacja formularza** - sprawdzenie wszystkich pól
- ✅ **Komunikaty błędów** - czytelne informacje o problemach

### **reCAPTCHA**
```html
<div id="g-recaptcha" class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"></div>
```

### **Weryfikacja w kodzie**
```php
// Sprawdź reCAPTCHA
$recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
if (!verifyRecaptcha($recaptchaResponse)) {
    header('Location: /offers/' . $carId . '?status=captcha_error');
    exit();
}
```

## 🔒 **Bezpieczeństwo**

### **reCAPTCHA**
- **Przed**: Na stronie głównej (blokuje dostęp do całej aplikacji)
- **Po**: Na formularzu rezerwacji (blokuje tylko spam rezerwacji)

### **Walidacja dat**
- Sprawdzanie poprawności zakresu dat
- Sprawdzanie czy data nie jest w przeszłości
- Automatyczne przekierowania z błędami

## 🎨 **Design i UX**

### **Strona główna**
- Gradient tło w sekcji hero
- Biały formularz z cieniem
- Ikony FontAwesome w sekcji "Jak to działa"
- Responsywny layout

### **Lista ofert**
- Czytelne wyświetlanie wybranych dat
- Przycisk zmiany terminu w prawym górnym rogu
- Lepsze komunikaty o braku wyników

## 📱 **Responsywność**

### **Formularz dat**
- Grid layout na większych ekranach
- Stack layout na mobilnych
- Duże przyciski i pola na dotyk

### **Przyciski**
- Hover efekty z animacjami
- Gradient tła
- Cienie i transformacje

## 🚀 **Korzyści nowego przepływu**

1. **Lepsze UX** - użytkownik od razu widzi co ma robić
2. **Bezpieczeństwo** - reCAPTCHA tylko tam gdzie jest potrzebne
3. **Walidacja** - lepsze sprawdzanie dat
4. **Design** - nowoczesny i atrakcyjny wygląd
5. **Nawigacja** - łatwy powrót do zmiany terminu

## 🔧 **Implementacja**

### **Pliki zmienione**
- `templates/pages/home.twig` - nowy formularz dat
- `templates/pages/offer/list.twig` - usunięcie formularza dat, dodanie przycisku zmiany
- `templates/pages/offer/show.twig` - dodanie reCAPTCHA
- `public/index.php` - nowa logika routingu i walidacji
- `public/css/style.css` - nowe style

### **Nowe funkcje**
- Walidacja dat z przekierowaniami
- Obsługa błędów dat
- reCAPTCHA na formularzu rezerwacji
- Automatyczne przekierowania

## 📋 **Testowanie**

### **Scenariusze testowe**
1. **Wybór poprawnych dat** → Przekierowanie do listy ofert
2. **Wybór dat w przeszłości** → Błąd i powrót do strony głównej
3. **Nieprawidłowy zakres dat** → Błąd i powrót do strony głównej
4. **Rezerwacja bez reCAPTCHA** → Błąd na stronie rezerwacji
5. **Przycisk "Zmień termin"** → Powrót do strony głównej

### **URL testowe**
- `/` - Strona główna z formularzem dat
- `/offers?date_from=2024-01-15&date_to=2024-01-20` - Lista ofert z datami
- `/offers/1` - Szczegóły samochodu
- `/offers/1/book` - Formularz rezerwacji (POST)
