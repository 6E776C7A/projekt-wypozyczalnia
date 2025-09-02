<?php
namespace App\Service;

use App\Repository\CarRepository;
use DateInterval;
use DatePeriod;
use DateTime;

class BookingService
{
    /**
     * Oblicza cenę rezerwacji na podstawie dat i cen.
     */
    public function calculatePrice(string $dateFrom, string $dateTo, float $workdayPrice, float $weekendPrice): float
    {
        $start = new DateTime($dateFrom);
        $end = new DateTime($dateTo);
        $end->modify('+1 day'); // uwzględnij ostatni dzień

        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        $total = 0.0;

        foreach ($period as $day) {
            $dayOfWeek = (int)$day->format('N');
            if ($dayOfWeek >= 6) { // 6 = sobota, 7 = niedziela
                $total += $weekendPrice;
            } else {
                $total += $workdayPrice;
            }
        }
        return $total;
    }

    /**
     * Tworzy rezerwację po walidacji danych.
     * Zwraca token anulowania lub null jeśli walidacja nie przeszła.
     */
    public function createBooking(
        CarRepository $carRepository,
        int $carId,
        string $email,
        string $firstName,
        string $lastName,
        string $dateFrom,
        string $dateTo
    ): ?string {
        // Walidacja: czy samochód istnieje?
        $car = $carRepository->findById($carId);
        if (!$car) {
            return null;
        }

        // Walidacja: e-mail
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        // Walidacja: imię i nazwisko (min. 2 znaki, tylko litery i myślnik/spacja)
        if (!preg_match('/^[\p{L}\s\-]{2,}$/u', $firstName)) {
            return null;
        }
        if (!preg_match('/^[\p{L}\s\-]{2,}$/u', $lastName)) {
            return null;
        }

        // Walidacja: daty
        if (!$this->validateDate($dateFrom) || !$this->validateDate($dateTo)) {
            return null;
        }
        $start = new DateTime($dateFrom);
        $end = new DateTime($dateTo);
        if ($end < $start) {
            return null;
        }

        // Walidacja: czy samochód dostępny w tym terminie?
        if (!$carRepository->isCarAvailable($carId, $dateFrom, $dateTo)) {
            return null;
        }

        $totalCost = $this->calculatePrice(
            $dateFrom,
            $dateTo,
            $car['workday_price'],
            $car['weekend_price']
        );
        $cancellation_token = bin2hex(random_bytes(32));

        $carRepository->saveBooking(
            $carId,
            $email,
            $firstName,
            $lastName,
            $dateFrom,
            $dateTo,
            $totalCost,
            $cancellation_token
        );

        return $cancellation_token;
    }

    /**
     * Sprawdza poprawność daty (Y-m-d).
     */
    private function validateDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}