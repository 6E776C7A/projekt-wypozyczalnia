// W klasie CarRepository
public function findAvailableCars(string $dateFrom, string $dateTo, array $filters) {
    // ... połączenie z bazą danych (np. PDO) ...

    $sql = "SELECT * FROM samochody s
            WHERE s.id NOT IN (
                SELECT r.samochod_id FROM rezerwacje r
                WHERE (r.data_od <= :date_to) AND (r.data_do >= :date_from)
            )";

    // Dodawanie filtrów (skrzynia, miejsca, kategoria) do zapytania SQL
    if (!empty($filters['skrzynia_biegow'])) {
        $sql .= " AND s.skrzynia_biegow = :skrzynia";
    }
    // ... podobnie dla innych filtrów ...

    // Dodawanie sortowania
    // ...

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo, /* ...parametry filtrów... */]);
    return $stmt->fetchAll();
}
```**Ważne:** Warunek `(r.data_od <= :date_to) AND (r.data_do >= :date_from)` poprawnie wykrywa każdą, nawet jednodniową, kolizję terminów.

**Obliczanie ceny (`BookingService.php`)**
Ta funkcja iteruje po dniach w wybranym okresie i sumuje koszt.

```php
// W klasie BookingService
public function calculatePrice(string $dateFrom, string $dateTo, float $priceWeekday, float $priceWeekend): float {
    $period = new DatePeriod(
        new DateTime($dateFrom),
        new DateInterval('P1D'),
        (new DateTime($dateTo))->modify('+1 day') // Uwzględniamy ostatni dzień
    );

    $totalPrice = 0;
    foreach ($period as $date) {
        $dayOfWeek = $date->format('N'); // 1 (pon) to 7 (niedz)
        if ($dayOfWeek >= 6) { // Sobota lub Niedziela
            $totalPrice += $priceWeekend;
        } else {
            $totalPrice += $priceWeekday;
        }
    }
    return $totalPrice;
}