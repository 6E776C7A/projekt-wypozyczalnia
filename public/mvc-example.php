<?php
// Plik: public/mvc-example.php
// Przykład użycia zaktualizowanych klas MVC

// Włączamy wyświetlanie błędów na czas dewelopmentu
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Uruchomienie sesji
session_start();

// Automatyczne ładowanie klas (Composer)
require_once __DIR__ . '/../vendor/autoload.php';

// Konfiguracja aplikacji
define('DB_PATH', __DIR__ . '/../data/mydb.sqlite');
define('TEMPLATE_PATH', __DIR__ . '/../templates');

// Inicjalizacja Twiga
$loader = new \Twig\Loader\FilesystemLoader(TEMPLATE_PATH);
$twig = new \Twig\Environment($loader, [
    'auto_reload' => true
]);

// Inicjalizacja połączenia z bazą danych
try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Błąd krytyczny: Nie można połączyć się z bazą danych. " . $e->getMessage());
}

// Wczytaj konfigurację mailera
$mailConfig = include __DIR__ . '/../config/mail.php';

// Inicjalizacja serwisów z dependency injection
$carRepository = new \App\Repository\CarRepository($pdo);
$bookingService = new \App\Service\BookingService();
$mailerService = new \App\Service\MailerService(
    $twig,
    $mailConfig['smtp']['host'],
    $mailConfig['smtp']['username'],
    $mailConfig['smtp']['password'],
    $mailConfig['smtp']['port'],
    $mailConfig['smtp']['encryption'],
    $mailConfig['smtp']['from_email'],
    $mailConfig['smtp']['from_name']
);

// Inicjalizacja kontrolera
$carController = new \App\Controller\CarController(
    $carRepository,
    $bookingService,
    $mailerService,
    $twig,
    $_ENV['RECAPTCHA_SECRET_KEY'] ?? 'test_key'
);

// Przykład użycia kontrolera
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($requestUri === '/mvc-example') {
    // Pokaż stronę główną
    $carController->showSearchPage();
} elseif ($requestUri === '/mvc-example/search' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Wyszukaj dostępne samochody
    $carController->listAvailableCars($_GET);
} elseif ($requestUri === '/mvc-example/book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Zarezerwuj samochód
    $carController->bookCar($_POST);
} else {
    echo "Dostępne endpointy:<br>";
    echo "- GET /mvc-example - Strona główna<br>";
    echo "- GET /mvc-example/search?date_from=...&date_to=... - Wyszukiwanie<br>";
    echo "- POST /mvc-example/book - Rezerwacja<br>";
}
