# MVC Architecture - Wypożyczalnia Samochodów

## Przegląd

Aplikacja została zrefaktoryzowana do architektury MVC (Model-View-Controller) z proper dependency injection.

## Struktura MVC

### 📁 **Controller** (`src/Controller/`)
- **`CarController.php`** - Obsługuje logikę biznesową dla samochodów i rezerwacji
- Metody:
  - `showSearchPage()` - Wyświetla stronę główną
  - `listAvailableCars()` - Wyszukuje dostępne samochody
  - `bookCar()` - Tworzy rezerwację
  - `cancelBooking()` - Anuluje rezerwację

### 📁 **Repository** (`src/Repository/`)
- **`CarRepository.php`** - Warstwa dostępu do danych
- Metody:
  - `findById()` - Znajduje samochód po ID
  - `findAvailableCars()` - Znajduje dostępne samochody
  - `saveBooking()` - Zapisuje rezerwację
  - `getDistinctMakes/Models/Seats()` - Pobiera unikalne wartości

### 📁 **Service** (`src/Service/`)
- **`BookingService.php`** - Logika biznesowa dla rezerwacji
- **`MailerService.php`** - Wysyłanie e-maili (zaktualizowany)

## 🔧 **Konfiguracja**

### 1. **Plik konfiguracyjny** (`config/mail.php`)
```php
return [
    'smtp' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.poczta.onet.pl',
        'username' => $_ENV['MAIL_USERNAME'] ?? 'your_email@onet.pl',
        'password' => $_ENV['MAIL_PASSWORD'] ?? 'your_password',
        // ... więcej opcji
    ]
];
```

### 2. **Zmienne środowiskowe** (`env.example`)
Skopiuj `env.example` do `.env` i wypełnij:
```bash
MAIL_HOST=smtp.poczta.onet.pl
MAIL_USERNAME=twoj_email@onet.pl
MAIL_PASSWORD=twoje_haslo
ADMIN_USERNAME=admin
ADMIN_PASSWORD=twoje_bezpieczne_haslo
RECAPTCHA_SECRET_KEY=twoj_rzeczywisty_klucz
```

## 🚀 **Użycie**

### **Przykład podstawowy** (`public/mvc-example.php`)
```php
// Inicjalizacja serwisów
$carRepository = new \App\Repository\CarRepository($pdo);
$bookingService = new \App\Service\BookingService();
$mailerService = new \App\Service\MailerService($twig, $host, $user, $pass);

// Inicjalizacja kontrolera
$carController = new \App\Controller\CarController(
    $carRepository,
    $bookingService,
    $mailerService,
    $twig,
    $recaptchaKey
);

// Użycie
$carController->showSearchPage();
$carController->listAvailableCars($_GET);
```

### **Endpointy MVC**
- `GET /mvc-example` - Strona główna
- `GET /mvc-example/search?date_from=...&date_to=...` - Wyszukiwanie
- `POST /mvc-example/book` - Rezerwacja

## 🔒 **Bezpieczeństwo**

### ✅ **Poprawione**
- Usunięto hardkodowane hasła z kodu
- Konfiguracja przez zmienne środowiskowe
- Proper dependency injection
- Walidacja danych wejściowych

### ⚠️ **Do zrobienia**
- Implementacja reCAPTCHA w kontrolerze
- Dodanie middleware dla autoryzacji
- Logowanie błędów
- Rate limiting

## 🗄️ **Baza danych**

### **Tabele**
- `cars` - Samochody (make, model, workday_price, weekend_price, etc.)
- `reservations` - Rezerwacje (car_id, start_date, end_date, customer_email, etc.)

### **Schema**
Wszystkie zapytania używają teraz poprawnej struktury bazy danych.

## 📝 **Następne kroki**

1. **Zintegruj MVC z główną aplikacją** - zastąp logikę z `public/index.php`
2. **Dodaj middleware** - autoryzacja, walidacja, logowanie
3. **Implementuj reCAPTCHA** - w kontrolerze
4. **Dodaj testy** - unit tests dla serwisów
5. **Cache** - Redis/Memcached dla wydajności

## 🔄 **Migracja z monolitów**

Aktualna aplikacja w `public/index.php` może być stopniowo zastąpiona przez wywołania kontrolerów MVC, zachowując istniejące endpointy i funkcjonalność.
