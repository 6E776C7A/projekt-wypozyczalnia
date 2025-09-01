<?php
namespace App\Repository;

use PDO;

class CarRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cars WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM cars ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    public function findAvailableCars(string $dateFrom, string $dateTo, array $filters = []): array
    {
        $sql = "SELECT * FROM cars c
                WHERE c.id NOT IN (
                    SELECT r.car_id FROM reservations r
                    WHERE (r.start_date <= :date_to) AND (r.end_date >= :date_from)
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
        return $stmt->fetchAll();
    }

    public function saveBooking(int $carId, string $email, string $firstName, string $lastName, string $dateFrom, string $dateTo, float $totalCost, string $token): bool
    {
        $sql = "INSERT INTO reservations (car_id, start_date, end_date, customer_email, total_cost, cancellation_token) 
                VALUES (:car_id, :start_date, :end_date, :email, :cost, :token)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':car_id' => $carId,
            ':start_date' => $dateFrom,
            ':end_date' => $dateTo,
            ':email' => $email,
            ':cost' => $totalCost,
            ':token' => $token
        ]);
    }

    public function findBookingByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM reservations WHERE cancellation_token = :token");
        $stmt->execute([':token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findAllReservationsWithCar(): array
    {
        $sql = "SELECT r.*, c.make, c.model, c.category FROM reservations r
                JOIN cars c ON r.car_id = c.id
                ORDER BY r.start_date DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function addCar(string $make, string $model, string $category, string $transmission, int $seats, float $workday_price, float $weekend_price, string $image_url): bool
    {
        $sql = "INSERT INTO cars (make, model, category, transmission, seats, workday_price, weekend_price, image_url)
                VALUES (:make, :model, :category, :transmission, :seats, :workday_price, :weekend_price, :image_url)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':make' => $make,
            ':model' => $model,
            ':category' => $category,
            ':transmission' => $transmission,
            ':seats' => $seats,
            ':workday_price' => $workday_price,
            ':weekend_price' => $weekend_price,
            ':image_url' => $image_url
        ]);
    }

    public function deleteCarById(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM cars WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function deleteReservationById(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM reservations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function deleteBookingById(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM reservations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getDistinctMakes(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT make FROM cars ORDER BY make ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDistinctModels(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT model FROM cars ORDER BY model ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDistinctSeats(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT seats FROM cars ORDER BY seats ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}