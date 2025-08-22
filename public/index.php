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

function calculateTotalRentalCost(string $dateFrom, string $dateTo, float $workdayPrice, float $weekendPrice): float
{
    $start = date_create($dateFrom);
    $end = date_create($dateTo);
    if (!$start || !$end) {
        return 0.0;
    }

    // Zakres zamknięty [from, to] – uwzględnij dzień końcowy
    $endInclusive = (clone $end)->modify('+1 day');
    $period = new DatePeriod($start, new DateInterval('P1D'), $endInclusive);

    $total = 0.0;
    foreach ($period as $day) {
        // Dni tygodnia: 1 (pon) ... 7 (niedziela)
        $dayOfWeek = (int)$day->format('N');
        $isWeekend = ($dayOfWeek >= 6);
        $total += $isWeekend ? $weekendPrice : $workdayPrice;
    }

    return $total;
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

// Dynamiczne trasy dla ofert: szczegóły i rezerwacja
if (preg_match('#^/offers/(\d+)$#', $requestUri, $matches)) {
    $carId = (int)$matches[1];

    // Pobierz auto
    $stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$carId]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$car) {
        header("HTTP/1.1 404 Not Found");
        echo $twig->render('pages/static/404.twig');
        exit();
    }

    // Daty z sesji (wymagane do kalkulacji)
    $dateFrom = $_SESSION['search_dates']['from'] ?? '';
    $dateTo = $_SESSION['search_dates']['to'] ?? '';

    $totalPrice = null;
    $isDateRangeValid = false;
    if ($dateFrom && $dateTo) {
        $df = date_create($dateFrom);
        $dt = date_create($dateTo);
        if ($df && $dt && $dateFrom <= $dateTo) {
            $isDateRangeValid = true;
            $totalPrice = calculateTotalRentalCost($dateFrom, $dateTo, (float)$car['workday_price'], (float)$car['weekend_price']);
        }
    }

    echo $twig->render('pages/offer/show.twig', [
        'car' => $car,
        'dates' => [ 'from' => $dateFrom, 'to' => $dateTo ],
        'total_price' => $totalPrice,
        'requireDates' => !$isDateRangeValid,
        'status' => $_GET['status'] ?? null
    ]);
    exit();
}

if ($requestMethod === 'POST' && preg_match('#^/offers/(\d+)/book$#', $requestUri, $matches)) {
    $carId = (int)$matches[1];

    // Pobierz auto
    $stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$carId]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$car) {
        header("HTTP/1.1 404 Not Found");
        echo $twig->render('pages/static/404.twig');
        exit();
    }

    // Daty z sesji
    $dateFrom = $_SESSION['search_dates']['from'] ?? '';
    $dateTo = $_SESSION['search_dates']['to'] ?? '';
    $df = $dateFrom ? date_create($dateFrom) : null;
    $dt = $dateTo ? date_create($dateTo) : null;
    if (!$df || !$dt || $dateFrom > $dateTo) {
        header('Location: /offers/' . $carId . '?status=invalid_dates');
        exit();
    }

    // Sprawdź dostępność (zakresy zamknięte)
    $stmt = $pdo->prepare("SELECT COUNT(1) FROM reservations WHERE car_id = :car_id AND (start_date <= :date_to) AND (end_date >= :date_from)");
    $stmt->execute([':car_id' => $carId, ':date_from' => $dateFrom, ':date_to' => $dateTo]);
    $conflict = (int)$stmt->fetchColumn() > 0;
    if ($conflict) {
        header('Location: /offers/' . $carId . '?status=unavailable');
        exit();
    }

    // Dane klienta
    $customerEmail = trim($_POST['email'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');

    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL) || $firstName === '' || $lastName === '') {
        header('Location: /offers/' . $carId . '?status=invalid_form');
        exit();
    }

    // Oblicz koszt i zapisz rezerwację
    $totalCost = calculateTotalRentalCost($dateFrom, $dateTo, (float)$car['workday_price'], (float)$car['weekend_price']);
    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("INSERT INTO reservations (car_id, start_date, end_date, customer_email, total_cost, cancellation_token) VALUES (:car_id, :start_date, :end_date, :email, :total_cost, :token)");
    $stmt->execute([
        ':car_id' => $carId,
        ':start_date' => $dateFrom,
        ':end_date' => $dateTo,
        ':email' => $customerEmail,
        ':total_cost' => $totalCost,
        ':token' => $token
    ]);

    header('Location: /offers?status=booked');
    exit();
}

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
                header('Location: /offers');
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
                // Użytkownik przeszedł weryfikację - pokaż listę ofert
                header('Location: /offers');
                exit();
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

                    // Pobierz rezerwacje (z nazwą auta)
                    $rstmt = $pdo->query("SELECT r.*, c.make, c.model FROM reservations r JOIN cars c ON c.id = r.car_id ORDER BY r.created_at DESC");
                    $reservations = $rstmt->fetchAll(PDO::FETCH_ASSOC);

                    echo $twig->render('pages/admin/dashboard.twig', [
                        'cars' => $cars,
                        'reservations' => $reservations,
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
                    $stmt->execute([$make, $model, $category, $transmission, (int)$seats, $workdayPrice, $weekendPrice, $imageUrl]);
                    
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

                // Pobierz rezerwacje (z nazwą auta)
                $rstmt = $pdo->query("SELECT r.*, c.make, c.model FROM reservations r JOIN cars c ON c.id = r.car_id ORDER BY r.created_at DESC");
                $reservations = $rstmt->fetchAll(PDO::FETCH_ASSOC);

                echo $twig->render('pages/admin/dashboard.twig', [
                    'cars' => $cars,
                    'reservations' => $reservations,
                    'status' => $_GET['status'] ?? null,
                    'username' => $_SESSION['admin_username'] ?? 'Admin'
                ]);
            } else {
                // Użytkownik nie jest zalogowany - pokaż formularz logowania
                echo $twig->render('pages/admin/dashboard.twig', [
                    'cars' => [],
                    'reservations' => [],
                    'status' => null,
                    'username' => null,
                    'show_login' => true
                ]);
            }
        }
        break;

    // --- TRASA: Lista ofert / wyszukiwanie ---
    // Adres: http://localhost:8080/offers
    case '/offers':
        // Odczyt filtrów z query string
        $category = $_GET['category'] ?? '';
        $transmission = $_GET['transmission'] ?? '';
        $maxPrice = $_GET['max_price'] ?? '';
        $make = $_GET['make'] ?? '';
        $model = $_GET['model'] ?? '';
        $seatsFilter = $_GET['seats'] ?? '';
        $sort = $_GET['sort'] ?? 'price_asc';
        $dateFromGet = $_GET['date_from'] ?? '';
        $dateToGet = $_GET['date_to'] ?? '';

        // Jeśli podano nowe daty i są poprawne, zapisz w sesji
        $dfNew = $dateFromGet ? date_create($dateFromGet) : null;
        $dtNew = $dateToGet ? date_create($dateToGet) : null;
        if ($dfNew && $dtNew && $dateFromGet <= $dateToGet) {
            $_SESSION['search_dates'] = ['from' => $dateFromGet, 'to' => $dateToGet];
        }

        // Odczytaj daty z sesji (utrzymanie niezależnie od filtrów)
        $dateFrom = $_SESSION['search_dates']['from'] ?? '';
        $dateTo = $_SESSION['search_dates']['to'] ?? '';

        // Listy opcji (marki, modele, miejsca)
        $makes = $pdo->query("SELECT DISTINCT make FROM cars ORDER BY make ASC")->fetchAll(PDO::FETCH_COLUMN);
        $models = $pdo->query("SELECT DISTINCT model FROM cars ORDER BY model ASC")->fetchAll(PDO::FETCH_COLUMN);
        $seatsOptions = $pdo->query("SELECT DISTINCT seats FROM cars ORDER BY seats ASC")->fetchAll(PDO::FETCH_COLUMN);

        // Walidacja zakresu dat (wymagane)
        $requireDates = false;
        $isDateRangeValid = false;
        if ($dateFrom === '' || $dateTo === '') {
            $requireDates = true;
        } else {
            // Prosta walidacja kolejności
            $df = date_create($dateFrom);
            $dt = date_create($dateTo);
            if ($df && $dt && $dateFrom <= $dateTo) {
                $isDateRangeValid = true;
            } else {
                $requireDates = true;
            }
        }

        $cars = [];

        if ($isDateRangeValid) {
            // Budowa zapytania z warunkami opcjonalnymi oraz sprawdzeniem dostępności w podanym zakresie dat
            $sql = "SELECT * FROM cars WHERE 1=1";
            $params = [];

            if ($category !== '') {
                $sql .= " AND category = :category";
                $params[':category'] = $category;
            }
            if ($transmission !== '') {
                $sql .= " AND transmission = :transmission";
                $params[':transmission'] = $transmission;
            }
            if ($maxPrice !== '' && is_numeric($maxPrice)) {
                // Filtrujemy po cenie dnia roboczego
                $sql .= " AND workday_price <= :max_price";
                $params[':max_price'] = (float)$maxPrice;
            }
            if ($make !== '') {
                $sql .= " AND make = :make";
                $params[':make'] = $make;
            }
            if ($model !== '') {
                $sql .= " AND model = :model";
                $params[':model'] = $model;
            }
            if ($seatsFilter !== '' && is_numeric($seatsFilter)) {
                $sql .= " AND seats = :seats";
                $params[':seats'] = (int)$seatsFilter;
            }

            // Dostępność (zakresy zamknięte): brak rezerwacji z częścią wspólną
            // Kolizja jeśli: start_date <= date_to AND end_date >= date_from
            $sql .= " AND id NOT IN (
                SELECT car_id FROM reservations
                WHERE (start_date <= :date_to) AND (end_date >= :date_from)
            )";
            $params[':date_from'] = $dateFrom;
            $params[':date_to'] = $dateTo;

            // Sortowanie wyników
            if ($sort === 'price_desc') {
                $sql .= " ORDER BY workday_price DESC, id DESC";
            } else {
                // Domyślnie od najniższej ceny
                $sql .= " ORDER BY workday_price ASC, id DESC";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Przelicz koszt za cały termin dla każdej oferty
            foreach ($cars as &$car) {
                $workday = (float)$car['workday_price'];
                $weekend = (float)$car['weekend_price'];
                $car['total_price'] = calculateTotalRentalCost($dateFrom, $dateTo, $workday, $weekend);
            }
            unset($car);
        }

        echo $twig->render('pages/offer/list.twig', [
            'cars' => $cars,
            'filters' => [
                'category' => $category,
                'transmission' => $transmission,
                'max_price' => $maxPrice,
                'make' => $make,
                'model' => $model,
                'seats' => $seatsFilter,
                'sort' => $sort
            ],
            'dates' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'requireDates' => $requireDates,
            'makes' => $makes,
            'models' => $models,
            'seatsOptions' => $seatsOptions,
            'status' => $_GET['status'] ?? null
        ]);
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

    // --- TRASA: Usuwanie rezerwacji ---
    // Adres: http://localhost:8080/reservations/delete
    case '/reservations/delete':
        requireLogin();
        if ($requestMethod === 'POST') {
            $reservationId = $_POST['id'] ?? null;
            if ($reservationId) {
                $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
                $stmt->execute([$reservationId]);
            }
            header('Location: /admin?status=reservation_deleted');
            exit();
        }
        header('Location: /admin');
        exit();

    // --- Domyślna trasa, gdy strona nie istnieje ---
    default:
        header("HTTP/1.1 404 Not Found");
        echo $twig->render('pages/static/404.twig');
        break;
}
