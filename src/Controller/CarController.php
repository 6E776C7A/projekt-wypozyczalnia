<?php
// Plik: src/Controller/CarController.php

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

    public function showSearchPage()
    {
        echo $this->twig->render('pages/offer/list.twig', [
            'cars' => [],
            'dates' => ['from' => '', 'to' => ''],
            'requireDates' => true,
            'filters' => [],
            'makes' => [],
            'models' => [],
            'seatsOptions' => []
        ]);
    }

    public function listAvailableCars(array $getData)
    {
        $dateFrom = $getData['date_from'] ?? '';
        $dateTo = $getData['date_to'] ?? '';

        if (empty($dateFrom) || empty($dateTo) || $dateTo <= $dateFrom) {
            echo $this->twig->render('pages/offer/list.twig', [
                'cars' => [],
                'dates' => ['from' => $dateFrom, 'to' => $dateTo],
                'requireDates' => true,
                'filters' => [],
                'makes' => [],
                'models' => [],
                'seatsOptions' => [],
                'error' => 'Proszę podać poprawny zakres dat.'
            ]);
            return;
        }

        $cars = $this->carRepository->findAvailableCars($dateFrom, $dateTo);

        foreach ($cars as &$car) {
            $car['total_price'] = $this->bookingService->calculatePrice(
                $dateFrom,
                $dateTo,
                $car['workday_price'],
                $car['weekend_price']
            );
        }

        echo $this->twig->render('pages/offer/list.twig', [
            'cars' => $cars,
            'dates' => ['from' => $dateFrom, 'to' => $dateTo],
            'requireDates' => false,
            'filters' => $getData,
            'makes' => $this->carRepository->getDistinctMakes(),
            'models' => $this->carRepository->getDistinctModels(),
            'seatsOptions' => $this->carRepository->getDistinctSeats()
        ]);
    }

    public function bookCar(array $postData)
    {
        $carId = $postData['car_id'] ?? null;
        $email = $postData['email'] ?? '';
        $firstName = $postData['first_name'] ?? '';
        $lastName = $postData['last_name'] ?? '';
        $dateFrom = $postData['date_from'] ?? '';
        $dateTo = $postData['date_to'] ?? '';

        if (empty($carId) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || 
            empty($firstName) || empty($lastName)) {
            die("Błąd: nieprawidłowe dane w formularzu.");
        }

        $car = $this->carRepository->findById((int)$carId);
        if (!$car) {
            die("Błąd: samochód nie istnieje.");
        }

        $totalCost = $this->bookingService->calculatePrice($dateFrom, $dateTo, $car['workday_price'], $car['weekend_price']);
        $cancellation_token = bin2hex(random_bytes(32));

        $this->carRepository->saveBooking((int)$carId, $email, $firstName, $lastName, $dateFrom, $dateTo, $totalCost, $cancellation_token);

        header('Location: /offers?status=booked');
        exit();
    }

    public function showConfirmationPage()
    {
        echo $this->twig->render('pages/offer/list.twig', [
            'cars' => [],
            'dates' => ['from' => '', 'to' => ''],
            'requireDates' => true,
            'filters' => [],
            'makes' => [],
            'models' => [],
            'seatsOptions' => [],
            'status' => 'booked'
        ]);
    }

    public function cancelBooking(string $token)
    {
        if (empty($token)) {
            die("Brak tokenu anulowania.");
        }

        $booking = $this->carRepository->findBookingByToken($token);

        if (!$booking) {
            echo $this->twig->render('pages/static/404.twig', [
                'error_message' => 'Rezerwacja nie została znaleziona lub została już anulowana.'
            ]);
            return;
        }

        $bookingTimestamp = strtotime($booking['start_date']);
        $nowTimestamp = time();

        if (($bookingTimestamp - $nowTimestamp) < (48 * 3600)) {
            echo $this->twig->render('pages/static/404.twig', [
                'error_message' => 'Nie można anulować rezerwacji na mniej niż 48 godzin przed datą odbioru.'
            ]);
            return;
        }

        $this->carRepository->deleteBookingById((int)$booking['id']);

        header('Location: /offers?status=cancelled');
        exit();
    }
}