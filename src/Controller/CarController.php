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
                $bookingTimestamp = strtotime($booking['start_date']);
                $nowTimestamp = time();

                if (($bookingTimestamp - $nowTimestamp) < (48 * 3600)) {
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
}