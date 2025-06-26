<?php
// Plik: src/Controller/CarController.php

namespace App\Controller;

use App\Repository\CarRepository;
use App\Service\BookingService;
use App\Service\MailerService;
use Twig\Environment;

class CarController
{
    private CarRepository $carRepository;
    private BookingService $bookingService;
    private MailerService $mailerService;
    private Environment $twig;
    private string $recaptchaSecretKey;

    public function __construct(
        CarRepository $carRepository,
        BookingService $bookingService,
        MailerService $mailerService,
        Environment $twig,
        string $recaptchaSecretKey
    ) {
        $this->carRepository = $carRepository;
        $this->bookingService = $bookingService;
        $this->mailerService = $mailerService;
        $this->twig = $twig;
        $this->recaptchaSecretKey = $recaptchaSecretKey;
    }

    public function showSearchPage()
    {
        // Wyświetla główną stronę z formularzem wyszukiwania
        echo $this->twig->render('car_list.twig');
    }

    public function listAvailableCars(array $getData)
    {
        $dateFrom = $getData['date_from'] ?? '';
        $dateTo = $getData['date_to'] ?? '';

        // Prosta walidacja - obie daty muszą być podane
        if (empty($dateFrom) || empty($dateTo) || $dateTo <= $dateFrom) {
            echo $this->twig->render('car_list.twig', ['error' => 'Proszę podać poprawny zakres dat.']);
            return;
        }

        $cars = $this->carRepository->findAvailableCars($dateFrom, $dateTo);

        // Dla każdego znalezionego samochodu obliczamy cenę za wybrany okres
        foreach ($cars as &$car) { // Znak '&' pozwala modyfikować tablicę w pętli
            $car['calculated_price'] = $this->bookingService->calculatePrice(
                $dateFrom,
                $dateTo,
                $car['cena_dzien_roboczy'],
                $car['cena_dzien_weekend']
            );
        }

        echo $this->twig->render('car_list.twig', [
            'cars' => $cars,
            'dates' => ['from' => $dateFrom, 'to' => $dateTo]
        ]);
    }

    public function bookCar(array $postData)
    {
        // TODO: Implementacja walidacji reCAPTCHA

        $carId = $postData['car_id'];
        $email = $postData['email'];
        $dateFrom = $postData['date_from'];
        $dateTo = $postData['date_to'];

        // Walidacja danych
        if (empty($carId) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            die("Błąd: nieprawidłowe dane w formularzu.");
        }

        $car = $this->carRepository->findById($carId);
        if (!$car) {
            die("Błąd: samochód nie istnieje.");
        }

        $totalCost = $this->bookingService->calculatePrice($dateFrom, $dateTo, $car['cena_dzien_roboczy'], $car['cena_dzien_weekend']);
        $cancellation_token = bin2hex(random_bytes(32));

        $this->carRepository->saveBooking($carId, $email, $dateFrom, $dateTo, $totalCost, $cancellation_token);

        // Przygotuj dane dla szablonu e-maila
        $details = [
            'marka' => $car['marka'],
            'model' => $car['model'],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalCost' => $totalCost,
            'cancelLink' => "http://localhost/index.php?action=cancel&token=" . $cancellation_token
        ];

        // Wyślij e-mail z potwierdzeniem
        $this->mailerService->sendBookingConfirmation($email, $details);

        header('Location: /index.php?action=confirmation');
        exit();
    }

    public function showConfirmationPage()
    {
        echo $this->twig->render('confirmation.twig', ['message' => 'Dziękujemy! Twoja rezerwacja została potwierdzona. Sprawdź swoją skrzynkę e-mail.']);
    }

    public function cancelBooking(string $token)
    {
        if (empty($token)) {
            die("Brak tokenu anulowania.");
        }

        $booking = $this->carRepository->findBookingByToken($token);

        if (!$booking) {
            echo $this->twig->render('cancellation.twig', ['success' => false, 'message' => 'Rezerwacja nie została znaleziona lub została już anulowana.']);
            return;
        }

        $bookingTimestamp = strtotime($booking['data_od']);
        $nowTimestamp = time();

        if (($bookingTimestamp - $nowTimestamp) < (48 * 3600)) {
            echo $this->twig->render('cancellation.twig', ['success' => false, 'message' => 'Nie można anulować rezerwacji na mniej niż 48 godzin przed datą odbioru.']);
            return;
        }

        $this->carRepository->deleteBookingById($booking['id']);

        echo $this->twig->render('cancellation.twig', ['success' => true, 'message' => 'Twoja rezerwacja została pomyślnie anulowana.']);
    }
}