<?php
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
            (new DateTime($dateTo))->modify('+1 day')
        );

        $totalPrice = 0;
        foreach ($period as $date) {
            $dayOfWeek = $date->format('N'); // 1=pon, ..., 6=sob, 7=nd
            if ($dayOfWeek >= 6) {
                $totalPrice += $priceWeekend;
            } else {
                $totalPrice += $priceWeekday;
            }
        }
        return $totalPrice;
    }
}