<?php
// Plik: public/index.php

// Krok 1: Załaduj zależności zainstalowane przez Composer
require_once '../vendor/autoload.php';

// Krok 2: Użyj przestrzeni nazw, aby klasy były dostępne
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use App\Controller\CarController;
use App\Repository\CarRepository;
use App\Service\BookingService;
use App\Service\MailerService;

// --- KONFIGURACJA APLIKACJI ---
// UZUPEŁNIJ DANYMI SWOJEJ BAZY DANYCH
$dbHost = 'localhost';
$dbName = 'wypozyczalnia'; // Nazwa bazy danych, którą stworzyłeś
$dbUser = 'root';         // Domyślny użytkownik w XAMPP
$dbPass = '';             // Domyślnie brak hasła w XAMPP

// UZUPEŁNIJ KLUCZEM RECAPTCHA (otrzymasz go od Google)
$recaptchaSecretKey = 'TWOJ_SEKRETNY_KLUCZ_RECAPTCHA';

// Krok 3: Inicjalizacja połączenia z bazą danych (PDO)
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // W przypadku błędu połączenia, zakończ działanie i wyświetl komunikat
    die("Błąd połączenia z bazą danych. Upewnij się, że serwer MySQL jest uruchomiony i dane w pliku index.php są poprawne. Błąd: " . $e->getMessage());
}

// Krok 4: Inicjalizacja systemu szablonów Twig
$loader = new FilesystemLoader('../templates');
$twig = new Environment($loader, [
    // 'cache' => '../cache', // Można włączyć dla przyspieszenia działania
]);

// Krok 5: Inicjalizacja wszystkich potrzebnych klas
$carRepository = new CarRepository($pdo);
$bookingService = new BookingService();
$mailerService = new MailerService($twig); // Inicjalizacja serwisu mailowego
$carController = new CarController(
    $carRepository,
    $bookingService,
    $mailerService, // Przekazanie serwisu mailowego do kontrolera
    $twig,
    $recaptchaSecretKey
);

// Krok 6: Prosty router, który decyduje, co zrobić na podstawie parametru 'action' w URL
$action = $_GET['action'] ?? 'home'; // Jeśli nie ma akcji, domyślną jest 'home'

switch ($action) {
    case 'search':
        $carController->listAvailableCars($_GET);
        break;

    case 'book':
        // Rezerwacja może być wykonana tylko metodą POST (z formularza)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $carController->bookCar($_POST);
        } else {
            header('Location: /'); // Jeśli ktoś wejdzie na ten URL bezpośrednio, przekieruj na stronę główną
            exit();
        }
        break;

    case 'cancel':
        $token = $_GET['token'] ?? '';
        $carController->cancelBooking($token);
        break;

    case 'confirmation':
        $carController->showConfirmationPage();
        break;

    case 'home':
    default:
        $carController->showSearchPage();
        break;
}