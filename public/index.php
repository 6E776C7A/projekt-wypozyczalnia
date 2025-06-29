<?php

// Włączamy wyświetlanie błędów na czas dewelopmentu
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Konfiguracja sesji - sesja będzie niszczona po zamknięciu przeglądarki
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);

// Uruchomienie sesji
session_start();

// Krok 1: Automatyczne ładowanie klas (Composer)
require_once __DIR__ . '/../vendor/autoload.php';

// Krok 2: Podstawowa konfiguracja aplikacji
define('DB_PATH', __DIR__ . '/../data/mydb.sqlite');
define('TEMPLATE_PATH', __DIR__ . '/../templates');

// Konfiguracja administratora - hardkodowane dane logowania
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// Funkcje pomocnicze do autoryzacji
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /admin');
        exit();
    }
}

function login($username, $password) {
    // Hardkodowane dane logowania
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: /admin');
    exit();
}

// Funkcja do weryfikacji reCAPTCHA
function verifyRecaptcha($recaptchaResponse) {
    // Sprawdź czy reCAPTCHA została zaznaczona
    if (empty($recaptchaResponse)) {
        return false;
    }
    
    // Weryfikacja reCAPTCHA v2 z Twoim kluczem tajnym
    $secretKey = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secretKey,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === false) {
        // W przypadku błędu połączenia, akceptujemy dla celów demo
        return true;
    }
    
    $response = json_decode($result, true);
    
    // Dla reCAPTCHA v2 sprawdzamy tylko success
    return $response['success'] ?? false;
}

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
        if ($requestMethod === 'POST') {
            // Sprawdź reCAPTCHA
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
            
            if (verifyRecaptcha($recaptchaResponse)) {
                // reCAPTCHA przeszła weryfikację
                $_SESSION['captcha_verified'] = true;
                header('Location: /');
                exit();
            } else {
                // reCAPTCHA nie przeszła weryfikacji
                echo $twig->render('pages/home.twig', [
                    'featured_cars' => [],
                    'captcha_verified' => false,
                    'captcha_error' => 'Proszę zaznaczyć reCAPTCHA przed przejściem dalej.'
                ]);
                exit();
            }
        } else {
            // GET request - sprawdź czy użytkownik przeszedł weryfikację
            $captchaVerified = $_SESSION['captcha_verified'] ?? false;
            
            if ($captchaVerified) {
                // Użytkownik przeszedł weryfikację - pokaż treść
                $stmt = $pdo->query("SELECT * FROM cars ORDER BY id DESC");
                $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo $twig->render('pages/home.twig', [
                    'featured_cars' => $cars,
                    'captcha_verified' => true
                ]);
            } else {
                // Użytkownik nie przeszedł weryfikacji - pokaż reCAPTCHA
                echo $twig->render('pages/home.twig', [
                    'featured_cars' => [],
                    'captcha_verified' => false
                ]);
            }
        }
        break;

    // --- TRASA: Wylogowanie ---
    // Adres: http://localhost:8080/logout
    case '/logout':
        logout();
        break;

    // --- TRASA: Panel Administratora ---
    // Adres: http://localhost:8080/admin
    case '/admin':
        if ($requestMethod === 'POST') {
            // Sprawdź czy to logowanie czy dodawanie samochodu
            if (isset($_POST['username']) && isset($_POST['password'])) {
                // To jest próba logowania
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if (login($username, $password)) {
                    header('Location: /admin');
                    exit();
                } else {
                    // Pobierz samochody do wyświetlenia w panelu
                    $stmt = $pdo->query("SELECT * FROM cars ORDER BY id DESC");
                    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo $twig->render('pages/admin/dashboard.twig', [
                        'cars' => $cars,
                        'status' => $_GET['status'] ?? null,
                        'username' => $_SESSION['admin_username'] ?? 'Admin',
                        'login_error' => 'Nieprawidłowa nazwa użytkownika lub hasło'
                    ]);
                    exit();
                }
            } else {
                // To jest dodawanie nowego samochodu - wymaga logowania
                requireLogin();
                
                $make = $_POST['make'] ?? '';
                $model = $_POST['model'] ?? '';
                $category = $_POST['category'] ?? 'Osobowy';
                $transmission = $_POST['transmission'] ?? 'Manual';
                $seats = $_POST['seats'] ?? 5;
                $workdayPrice = $_POST['workday_price'] ?? 0;
                $weekendPrice = $_POST['weekend_price'] ?? 0;
                $imageUrl = $_POST['image_url'] ?? '';

                if ($make && $model && $workdayPrice > 0 && $weekendPrice > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO cars (make, model, category, transmission, seats, workday_price, weekend_price, image_url) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$make, $model, $category, $transmission, $seats, $workdayPrice, $weekendPrice, $imageUrl]);
                    
                    header('Location: /admin?status=added');
                    exit();
                } else {
                    header('Location: /admin?status=error');
                    exit();
                }
            }
        } else {
            // GET request - wyświetl panel lub formularz logowania
            if (isLoggedIn()) {
                // Użytkownik jest zalogowany - pokaż panel
                $stmt = $pdo->query("SELECT * FROM cars ORDER BY id DESC");
                $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo $twig->render('pages/admin/dashboard.twig', [
                    'cars' => $cars,
                    'status' => $_GET['status'] ?? null,
                    'username' => $_SESSION['admin_username'] ?? 'Admin'
                ]);
            } else {
                // Użytkownik nie jest zalogowany - pokaż formularz logowania
                echo $twig->render('pages/admin/dashboard.twig', [
                    'cars' => [],
                    'status' => null,
                    'username' => null,
                    'show_login' => true
                ]);
            }
        }
        break;

    // --- TRASA: Akcja usuwania samochodu ---
    // Adres: http://localhost:8080/delete (np. z formularza metodą POST)
    case '/delete':
        requireLogin(); // Zabezpieczenie akcji usuwania
        
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