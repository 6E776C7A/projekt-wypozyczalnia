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
        $stmt = $this->pdo->prepare("SELECT * FROM samochody WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findAvailableCars(string $dateFrom, string $dateTo, array $filters = []): array
    {
        $sql = "SELECT * FROM samochody s
                WHERE s.id NOT IN (
                    SELECT r.samochod_id FROM rezerwacje r
                    WHERE (r.data_od < :date_to) AND (r.data_do > :date_from)
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
        return $stmt->fetchAll();
    }

    public function saveBooking(int $carId, string $email, string $dateFrom, string $dateTo, float $totalCost, string $token): bool
    {
        $sql = "INSERT INTO rezerwacje (samochod_id, email_klienta, data_od, data_do, calkowity_koszt, token_anulowania)
                VALUES (:car_id, :email, :date_from, :date_to, :cost, :token)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':car_id' => $carId,
            ':email' => $email,
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
            ':cost' => $totalCost,
            ':token' => $token
        ]);
    }

    public function findBookingByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rezerwacje WHERE token_anulowania = :token");
        $stmt->execute([':token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function deleteBookingById(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM rezerwacje WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}