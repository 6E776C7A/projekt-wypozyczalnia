<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('sqlite:' . __DIR__ . '/../data/mydb.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

use App\Controller\CarController;
use App\Controller\AdminController;
use App\Repository\CarRepository;
use App\Service\BookingService;

$carRepository = new CarRepository($pdo);
$bookingService = new BookingService();
$recaptchaSecretKey = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

$carController = new CarController(
    $carRepository,
    $bookingService,
    $twig,
    $recaptchaSecretKey
);
$adminController = new AdminController($carRepository, $twig);

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

/**
 * Prosta funkcja logowania administratora.
 */
function login(string $username, string $password): bool
{
    //Dane logowania.
    $adminUser = 'admin';
    $adminPass = 'admin123';

    if ($username === $adminUser && $password === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        return true;
    }
    return false;
}

function requireLogin()
{
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: /admin/login');
        exit();
    }
}

function logout()
{
    session_destroy();
    header('Location: /admin/login');
    exit();
}

// --- ROUTER ---
switch (true) {
    // --- Strona główna ---
    case $requestUri === '/':
        echo $twig->render('pages/home.twig');
        break;

    // --- Oferty (lista) ---
    case $requestUri === '/offers' && $requestMethod === 'GET':
        $carController->showSearchPage($_GET);
        break;

    // --- Oferta (szczegóły) + rezerwacja ---
    case preg_match('#^/offers/(\d+)$#', $requestUri, $matches):
    $carId = (int)$matches[1];
    if ($requestMethod === 'POST') {
        $carController->bookCar($carId, $_POST);
    } else {
        $carController->showOffer($carId, $_GET);
    }
    break;

    // --- Resetowanie dat wyszukiwania ---
    case $requestUri === '/reset-dates':
        header('Location: /');
        exit();

    // --- Potwierdzenie rezerwacji ---
    case $requestUri === '/confirmation' && $requestMethod === 'GET':
        $token = $_GET['token'] ?? '';
        $carController->showConfirmationPage($token);
        break;

    // --- Anulowanie rezerwacji przez klienta ---
    case $requestUri === '/reservations/cancel':
        $token = $_GET['token'] ?? '';
        $carController->cancelBooking($token);
        break;

    // --- Panel administratora ---
    case $requestUri === '/admin':
        requireLogin();
        $status = $_GET['status'] ?? null;
        $username = $_SESSION['admin_username'] ?? 'Admin';
        $adminController->showDashboard($status, $username);
        break;

    // --- Logowanie administratora ---
    case $requestUri === '/admin/login':
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

    // --- Wylogowanie ---
    case $requestUri === '/logout':
        logout();
        break;

    // --- Dodawanie samochodu ---
    case $requestUri === '/admin/car/add':
        requireLogin();
        if ($requestMethod === 'POST') {
            $adminController->addCar($_POST);
        }
        header('Location: /admin');
        exit();

    // --- Usuwanie samochodu ---
    case $requestUri === '/delete':
        requireLogin();
        if ($requestMethod === 'POST') {
            $carId = $_POST['id'] ?? null;
            if ($carId) {
                $adminController->deleteCar((int)$carId);
            }
        }
        header('Location: /admin');
        exit();

    // --- Usuwanie rezerwacji ---
    case $requestUri === '/reservations/delete':
        requireLogin();
        if ($requestMethod === 'POST') {
            $reservationId = $_POST['id'] ?? null;
            if ($reservationId) {
                $adminController->deleteReservation((int)$reservationId);
            }
        }
        header('Location: /admin');
        exit();

    // --- Domyślnie: 404 ---
    default:
        echo $twig->render('pages/static/404.twig', [
            'error_message' => 'Nie znaleziono strony.'
        ]);
        break;
    }