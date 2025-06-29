<?php

// Włączamy wyświetlanie błędów na czas dewelopmentu
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Krok 1: Automatyczne ładowanie klas (Composer)
require_once __DIR__ . '/../vendor/autoload.php';

// Krok 2: Podstawowa konfiguracja aplikacji
define('DB_PATH', __DIR__ . '/../data/mydb.sqlite');
define('TEMPLATE_PATH', __DIR__ . '/../templates');

// Krok 3: Inicjalizacja Twiga (systemu szablonów)
$loader = new \Twig\Loader\FilesystemLoader(TEMPLATE_PATH);
$twig = new \Twig\Environment($loader, [
    // Opcjonalnie: włącz auto-odświeżanie szablonów podczas dewelopmentu
    'auto_reload' => true
]);

// Krok 4: Inicjalizacja połączenia z bazą danych (PDO)
try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Błąd krytyczny: Nie można połączyć się z bazą danych. " . $e->getMessage());
}

// Krok 5: NOWY, LEPSZY ROUTER (obsługa "ładnych adresów")
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

switch ($requestUri) {
    // --- TRASA: Strona główna ---
    // Adres: http://localhost:8080/
    case '/':
        $stmt = $pdo->query("SELECT * FROM cars ORDER BY id DESC");
        $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo $twig->render('pages/home.twig', ['cars' => $cars]);
        break;

    // --- TRASA: Panel Administratora ---
    // Adres: http://localhost:8080/admin
    case '/admin':
        if ($requestMethod === 'POST') {
            // Tutaj logika dodawania nowego samochodu
            // ...
            // PRZEKIEROWANIE na ładny URL
            header('Location: /admin?status=added');
            exit();
        } else {
            // Pobierz samochody do wyświetlenia w panelu
            $stmt = $pdo->query("SELECT * FROM cars ORDER BY id DESC");
            $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo $twig->render('pages/admin/dashboard.twig', [
                'cars' => $cars,
                'status' => $_GET['status'] ?? null // $_GET wciąż działa dla parametrów!
            ]);
        }
        break;

    // --- TRASA: Akcja usuwania samochodu ---
    // Adres: http://localhost:8080/delete (np. z formularza metodą POST)
    case '/delete':
        if ($requestMethod === 'POST') {
            // Pobierz ID z formularza
            $idToDelete = $_POST['id'] ?? null;
            if ($idToDelete) {
                $stmt = $pdo->prepare("DELETE FROM cars WHERE id = ?");
                $stmt->execute([$idToDelete]);
            }
            // PRZEKIEROWANIE na ładny URL
            header('Location: /admin?status=deleted');
            exit();
        }
        // Jeśli ktoś wejdzie na /delete metodą GET, przekieruj go
        header('Location: /admin');
        exit();

    // --- Domyślna trasa, gdy strona nie istnieje ---
    default:
        header("HTTP/1.1 404 Not Found");
        echo $twig->render('pages/static/404.twig');
        break;
}