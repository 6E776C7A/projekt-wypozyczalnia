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
        header('Location: /admin/login');
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

// Inicjalizacja BookingService (logika kalkulacji ceny)
$bookingService = new \App\Service\BookingService();

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

// Udostępnij bieżącą ścieżkę w szablonach
$twig->addGlobal('current_path', $requestUri);

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
    if ($dateFrom && $dateTo) {
        $df = date_create($dateFrom);
        $dt = date_create($dateTo);
        if ($df && $dt && $dateFrom <= $dateTo) {
            $totalPrice = $bookingService->calculatePrice($dateFrom, $dateTo, (float)$car['workday_price'], (float)$car['weekend_price']);
        }
    }

    echo $twig->render('pages/offer/show.twig', [
        'car' => $car,
        'dates' => [ 'from' => $dateFrom, 'to' => $dateTo ],
        'total_price' => $totalPrice,
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

    // Sprawdź reCAPTCHA
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    if (!verifyRecaptcha($recaptchaResponse)) {
        header('Location: /offers/' . $carId . '?status=captcha_error');
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
    $totalCost = $bookingService->calculatePrice($dateFrom, $dateTo, (float)$car['workday_price'], (float)$car['weekend_price']);
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
        // GET request - pokaż stronę główną z formularzem wyboru dat
        $dateError = '';
        $dates = [];
        
        // Sprawdź czy są błędy walidacji dat
        if (isset($_GET['date_error'])) {
            $dateError = $_GET['date_error'];
        }
        
        // Pokaż stronę główną z formularzem wyboru dat
        echo $twig->render('pages/home.twig', [
            'dates' => $dates,
            'date_error' => $dateError
        ]);
        break;

    // --- TRASA: Wylogowanie ---
    // Adres: http://localhost:8080/logout
    case '/logout':
        logout();
        break;

    // --- TRASA: Reset zakresu dat ---
    // Adres: http://localhost:8080/reset-dates
    case '/reset-dates':
        unset($_SESSION['search_dates']);
        header('Location: /');
        exit();
        break;
    
        // --- TRASA: Logowanie administratora ---
    case '/admin/login':
    if ($requestMethod === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if (login($username, $password)) {
            header('Location: /admin');
            exit();
        } else {
            echo $twig->render('pages/admin/login.twig', [
                'login_error' => 'Nieprawidłowa nazwa użytkownika lub hasło'
            ]);
            exit();
        }
    } else {
        echo $twig->render('pages/admin/login.twig');
        exit();
    }
    break;

    // --- TRASA: Panel Administratora ---
    // Adres: http://localhost:8080/admin
    case '/admin':
        requireLogin();
        // Użytkownik jest zalogowany - pokaż panel
        $stmt = $pdo->query("SELECT * FROM cars ORDER BY id DESC");
        $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rstmt = $pdo->query("SELECT r.*, c.make, c.model FROM reservations r JOIN cars c ON c.id = r.car_id ORDER BY r.created_at DESC");
        $reservations = $rstmt->fetchAll(PDO::FETCH_ASSOC);

        echo $twig->render('pages/admin/dashboard.twig', [
            'cars' => $cars,
            'reservations' => $reservations,
            'status' => $_GET['status'] ?? null,
            'username' => $_SESSION['admin_username'] ?? 'Admin'
        ]);
        break;
    
    // --- TRASA: Dodawanie nowego samochodu ---
    // Adres: http://localhost:8080/admin/car/add (POST)
    case '/admin/car/add':
    requireLogin();
    if ($requestMethod === 'POST') {
        $make = trim($_POST['make'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $transmission = trim($_POST['transmission'] ?? '');
        $seats = (int)($_POST['seats'] ?? 5);
        $workday_price = (float)($_POST['workday_price'] ?? 0);
        $weekend_price = (float)($_POST['weekend_price'] ?? 0);
        $image_url = trim($_POST['image_url'] ?? '');

        // Prosta walidacja
        if ($make && $model && $category && $transmission && $seats > 0 && $workday_price >= 0 && $weekend_price >= 0) {
            $stmt = $pdo->prepare("INSERT INTO cars (make, model, category, transmission, seats, workday_price, weekend_price, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$make, $model, $category, $transmission, $seats, $workday_price, $weekend_price, $image_url]);
            header('Location: /admin?status=car_added');
            exit();
        } else {
            header('Location: /admin?status=add_error');
            exit();
        }
    }
    // Jeśli ktoś wejdzie GET-em, przekieruj na dashboard
    header('Location: /admin');
    exit();

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

        // Jeśli podano nowe daty, zwaliduj je
        if ($dateFromGet && $dateToGet) {
            $dfNew = date_create($dateFromGet);
            $dtNew = date_create($dateToGet);
            
            // Sprawdź czy daty są poprawne
            if ($dfNew && $dtNew && $dateFromGet <= $dateToGet) {
                // Sprawdź czy data początkowa nie jest w przeszłości
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                $dfNew->setTime(0, 0, 0);
                
                if ($dfNew >= $today) {
                    $_SESSION['search_dates'] = ['from' => $dateFromGet, 'to' => $dateToGet];
                } else {
                    // Data w przeszłości - przekieruj z błędem
                    header('Location: /?date_error=Data_początkowa_nie_może_być_w_przeszłości');
                    exit();
                }
            } else {
                // Nieprawidłowy zakres dat - przekieruj z błędem
                header('Location: /?date_error=Data_końcowa_musi_być_późniejsza_niż_początkowa');
                exit();
            }
        }

        // Odczytaj daty z sesji (utrzymanie niezależnie od filtrów)
        $dateFrom = $_SESSION['search_dates']['from'] ?? '';
        $dateTo = $_SESSION['search_dates']['to'] ?? '';

        // Listy opcji (marki, modele, miejsca)
        $makes = $pdo->query("SELECT DISTINCT make FROM cars ORDER BY make ASC")->fetchAll(PDO::FETCH_COLUMN);
        $models = $pdo->query("SELECT DISTINCT model FROM cars ORDER BY model ASC")->fetchAll(PDO::FETCH_COLUMN);
        $seatsOptions = $pdo->query("SELECT DISTINCT seats FROM cars ORDER BY seats ASC")->fetchAll(PDO::FETCH_COLUMN);

        // Walidacja zakresu dat (wymagane)
        $isDateRangeValid = false;
        if ($dateFrom === '' || $dateTo === '') {
            header('Location: /?date_error=Wybierz_poprawny_termin_wypożyczenia');
            exit();
        } else {
            // Prosta walidacja kolejności
            $df = date_create($dateFrom);
            $dt = date_create($dateTo);
            if ($df && $dt && $dateFrom <= $dateTo) {
                // Sprawdź czy data początkowa nie jest w przeszłości
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                $df->setTime(0, 0, 0);
                
                if ($df >= $today) {
                    $isDateRangeValid = true;
                } else {
                    header('Location: /?date_error=Data_początkowa_nie_może_być_w_przeszłości');
                    exit();
                }
            } else {
                header('Location: /?date_error=Data_końcowa_musi_być_późniejsza_niż_początkowa');
                exit();
            }
        }
        
        // Jeśli nie ma ważnych dat, przekieruj do strony głównej
        if (!$isDateRangeValid) {
            header('Location: /?date_error=Wybierz_poprawny_termin_wypożyczenia');
            exit();
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
                $car['total_price'] = $bookingService->calculatePrice($dateFrom, $dateTo, $workday, $weekend);
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

    // --- TRASA: Anulowanie rezerwacji przez klienta ---
    // Adres: GET /reservations/cancel?token=...
    case '/reservations/cancel':
        $token = $_GET['token'] ?? '';
        if (!$token) {
            echo $twig->render('pages/static/404.twig');
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE cancellation_token = :token");
        $stmt->execute([':token' => $token]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reservation) {
            echo $twig->render('pages/static/404.twig');
            break;
        }

        // Sprawdź czy można anulować (>48h przed startem)
        $startDate = new DateTime($reservation['start_date']);
        $now = new DateTime();
        $hoursUntilStart = ($startDate->getTimestamp() - $now->getTimestamp()) / 3600;
        
        if ($hoursUntilStart < 48) {
            echo $twig->render('pages/static/404.twig', [
                'error_message' => 'Nie można anulować rezerwacji na mniej niż 48 godzin przed datą odbioru.'
            ]);
            break;
        }
        
        $del = $pdo->prepare("DELETE FROM reservations WHERE id = :id");
        $del->execute([':id' => $reservation['id']]);

        header('Location: /offers?status=cancelled');
        exit();

    // --- Domyślna trasa, gdy strona nie istnieje ---
    default:
        header("HTTP/1.1 404 Not Found");
        echo $twig->render('pages/static/404.twig');
        break;
}
