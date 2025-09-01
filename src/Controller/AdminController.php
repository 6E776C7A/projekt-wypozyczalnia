<?php
namespace App\Controller;

use App\Repository\CarRepository;
use Twig\Environment;

class AdminController
{
    private CarRepository $carRepository;
    private Environment $twig;

    public function __construct(CarRepository $carRepository, Environment $twig)
    {
        $this->carRepository = $carRepository;
        $this->twig = $twig;
    }

    public function showDashboard(string $status = null, string $username = 'Admin')
    {
        $cars = $this->carRepository->findAll();
        $reservations = $this->carRepository->findAllReservationsWithCar();
        echo $this->twig->render('pages/admin/dashboard.twig', [
            'cars' => $cars,
            'reservations' => $reservations,
            'status' => $status,
            'username' => $username
        ]);
    }

    public function addCar(array $postData)
    {
        $make = trim($postData['make'] ?? '');
        $model = trim($postData['model'] ?? '');
        $category = trim($postData['category'] ?? '');
        $transmission = trim($postData['transmission'] ?? '');
        $seats = (int)($postData['seats'] ?? 5);
        $workday_price = (float)($postData['workday_price'] ?? 0);
        $weekend_price = (float)($postData['weekend_price'] ?? 0);
        $image_url = trim($postData['image_url'] ?? '');

        if ($make && $model && $category && $transmission && $seats > 0 && $workday_price >= 0 && $weekend_price >= 0) {
            $this->carRepository->addCar($make, $model, $category, $transmission, $seats, $workday_price, $weekend_price, $image_url);
            header('Location: /admin?status=car_added');
        } else {
            header('Location: /admin?status=add_error');
        }
        exit();
    }

    public function deleteCar(int $carId)
    {
        $this->carRepository->deleteCarById($carId);
        header('Location: /admin?status=deleted');
        exit();
    }

    public function deleteReservation(int $reservationId)
    {
        $this->carRepository->deleteReservationById($reservationId);
        header('Location: /admin?status=reservation_deleted');
        exit();
    }
}