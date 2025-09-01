<?php
namespace App\Controller;

use App\Repository\CarRepository;
use App\Service\BookingService;
use Twig\Environment;

class CarController
{
    private CarRepository $carRepository;
    private BookingService $bookingService;
    private Environment $twig;
    private string $recaptchaSecretKey;

    public function __construct(
        CarRepository $carRepository,
        BookingService $bookingService,
        Environment $twig,
        string $recaptchaSecretKey
    ) {
        $this->carRepository = $carRepository;
        $this->bookingService = $bookingService;
        $this->twig = $twig;
        $this->recaptchaSecretKey = $recaptchaSecretKey;
    }

    public function showSearchPage(array $filters = [])
    {
        $dateFrom = $filters['from'] ?? date('Y-m-d');
        $dateTo = $filters['to'] ?? date('Y-m-d', strtotime('+1 day'));

        $cars = $this->carRepository->findAvailableCars($dateFrom, $dateTo, $filters);

        echo $this->twig->render('pages/offer/list.twig', [
            'cars' => $cars,
            'dates' => ['from' => $dateFrom, 'to' => $dateTo],
            'requireDates' => true,
            'filters' => $filters,
            'makes' => $this->carRepository->getDistinctMakes(),
            'models' => $this->carRepository->getDistinctModels(),
            'seatsOptions' => $this->carRepository->getDistinctSeats()
        ]);
    }

    private function verifyRecaptcha($recaptchaResponse) {
    if (empty($recaptchaResponse)) {
        return false;
    }
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
        return true; 
    }
    $response = json_decode($result, true);
    return $response['success'] ?? false;
    }

    public function bookCar(int $carId, array $postData)
    {
    $email = trim($postData['email'] ?? '');
    $firstName = trim($postData['first_name'] ?? '');
    $lastName = trim($postData['last_name'] ?? '');
    $dateFrom = $postData['date_from'] ?? '';
    $dateTo = $postData['date_to'] ?? '';

    // Sprawdzenie reCAPTCHA
    if (method_exists($this, 'verifyRecaptcha')) {
        if (!$this->verifyRecaptcha($postData['g-recaptcha-response'] ?? '')) {
            $car = $this->carRepository->findById($carId);
            echo $this->twig->render('pages/offer/show.twig', [
                'car' => $car,
                'dates' => ['from' => $dateFrom, 'to' => $dateTo],
                'form_error' => 'Nie potwierdzono reCAPTCHA.'
            ]);
            return;
        }
    }

    // Utwórz rezerwację przez BookingService
    $token = $this->bookingService->createBooking(
        $this->carRepository,
        $carId,
        $email,
        $firstName,
        $lastName,
        $dateFrom,
        $dateTo
    );

    if (!$token) {
        $car = $this->carRepository->findById($carId);
        echo $this->twig->render('pages/offer/show.twig', [
            'car' => $car,
            'dates' => ['from' => $dateFrom, 'to' => $dateTo],
            'form_error' => 'Nie udało się utworzyć rezerwacji. Sprawdź poprawność danych i dostępność terminu.'
        ]);
        return;
    }

    // Przekierowanie na stronę potwierdzenia
    header('Location: /confirmation?token=' . urlencode($token));
    exit();
    }

    public function cancelBooking(string $token)
    {
    $status = null;

    if (empty($token)) {
        $status = 'cancel_error';
    } else {
        $booking = $this->carRepository->findBookingByToken($token);

        if (!$booking) {
            $status = 'cancel_error';
        } else {
            // Dodaj godzinę końca dnia do start_date
            $bookingTimestamp = strtotime($booking['start_date'] . ' 23:59:59');
            $nowTimestamp = time();
            $hoursUntilStart = ($bookingTimestamp - $nowTimestamp) / 3600;

            if ($hoursUntilStart < 48) {
                $status = 'cancel_error';
            } else {
                $this->carRepository->deleteBookingById((int)$booking['id']);
                $status = 'cancelled';
            }
        }
    }

    echo $this->twig->render('pages/home.twig', [
        'status' => $status,
    ]);
    }

    public function showOffer(int $carId, array $dates = [])
    {
        $car = $this->carRepository->findById($carId);
        if (!$car) {
            echo $this->twig->render('pages/static/404.twig', [
                'error_message' => 'Nie znaleziono samochodu.'
            ]);
            return;
        }

        $dateFrom = $dates['from'] ?? date('Y-m-d');
        $dateTo = $dates['to'] ?? date('Y-m-d', strtotime('+1 day'));

        $totalPrice = $this->bookingService->calculatePrice(
            $dateFrom,
            $dateTo,
            $car['workday_price'],
            $car['weekend_price']
        );

        echo $this->twig->render('pages/offer/show.twig', [
            'car' => $car,
            'dates' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_price' => $totalPrice
        ]);
    }

    public function showConfirmationPage(string $token)
    {
        if (empty($token)) {
            echo $this->twig->render('pages/static/404.twig', [
                'error_message' => 'Brak tokenu potwierdzenia.'
            ]);
            return;
        }

        $booking = $this->carRepository->findBookingByToken($token);

        if (!$booking) {
            echo $this->twig->render('pages/static/404.twig', [
                'error_message' => 'Rezerwacja nie została znaleziona.'
            ]);
            return;
        }

        $car = $this->carRepository->findById((int)$booking['car_id']);

        $bookingTimestamp = strtotime($booking['start_date']);
        $nowTimestamp = time();
        $hoursUntilStart = ($bookingTimestamp - $nowTimestamp) / 3600;
        $canCancel = $hoursUntilStart >= 48;

        echo $this->twig->render('pages/offer/confirmation.twig', [
            'booking' => $booking,
            'car' => $car,
            'canCancel' => $canCancel
        ]);
    }
}