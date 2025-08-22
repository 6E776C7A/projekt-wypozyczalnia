<?php
// Plik: src/Repository/CarRepository.php

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