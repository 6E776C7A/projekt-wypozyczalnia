<?php
// Plik: src/Service/BookingService.php

namespace App\Service;

use DateInterval;
use DatePeriod;
use DateTime;

class BookingService
{
    /**
     * Oblicza całkowity koszt wynajmu na podstawie ceny za dni robocze i weekendy.
     */
    public function calculatePrice(string $dateFrom, string $dateTo, float $priceWeekday, float $priceWeekend): float
    {
        $period = new DatePeriod(
            new DateTime($dateFrom),
            new DateInterval('P1D'),
            new DateTime($dateTo)
        );

        $totalPrice = 0;
        foreach ($period as $date) {
            $dayOfWeek = $date->format('N'); // 'N' zwraca 1 dla poniedziałku, ..., 6 dla soboty, 7 dla niedzieli
            if ($dayOfWeek >= 6) { // Jeśli to sobota lub niedziela
                $totalPrice += $priceWeekend;
            } else {
                $totalPrice += $priceWeekday;
            }
        }
        return $totalPrice;
    }
}