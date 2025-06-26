<?php

// Krok 1: Automatyczne ładowanie klas (dzięki Composer)
require_once 'vendor/autoload.php';

// Krok 2: Podstawowa konfiguracja aplikacji
define('DB_PATH', __DIR__ . 'data/mydb.sqlite'); // Zmień na poprawną nazwę pliku
define('TEMPLATE_PATH', __DIR__ . '/templates');

// Krok 3: Inicjalizacja Twiga (systemu szablonów)
$loader = new \Twig\Loader\FilesystemLoader(TEMPLATE_PATH);
$twig = new \Twig\Environment($loader, [
    // 'cache' => __DIR__ . '/cache', // Opcjonalnie, włącz dla lepszej wydajności
]);

// Krok 4: Inicjalizacja połączenia z bazą danych (PDO)
try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Błąd krytyczny: Nie można połączyć się z bazą danych. " . $e->getMessage());
}

// Krok 5: Prosty router - analiza adresu URL
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($requestUri) {
    // --- TRASA: Strona główna ---
    case '/':
        // Pobierz wszystkie samochody z bazy, aby je wyświetlić
        $stmt = $pdo->query("SELECT * FROM cars ORDER BY id DESC");
        $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Wyrenderuj szablon strony głównej, przekazując dane o samochodach
        echo $twig->render('pages/home.twig', [
            'featured_cars' => $cars
        ]);
        break;

    // --- TRASA: Strona dodawania samochodu ---
    case '/admin/add-car':
        // Sprawdzamy, czy formularz został wysłany (metoda POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // --- LOGIKA ZAPISU DO BAZY ---
            try {
                // Zbierz dane z formularza
                $name = $_POST['name'] ?? '';
                // ... zbierz pozostałe pola tak jak w poprzedniej odpowiedzi ...
                $year = (int)($_POST['year'] ?? 0);
                $mileage = (int)($_POST['mileage'] ?? 0);
                $fuel_type = $_POST['fuel_type'] ?? '';
                $engine_size = (int)($_POST['engine_size'] ?? 0);
                $monthly_rate = (int)($_POST['monthly_rate'] ?? 0);
                $down_payment = (int)($_POST['down_payment'] ?? 0);
                $image_url = $_POST['image_url'] ?? '';
                $description = $_POST['description'] ?? '';

                $sql = "INSERT INTO cars (name, year, mileage, fuel_type, engine_size, monthly_rate, down_payment, image_url, description) 
                        VALUES (:name, :year, :mileage, :fuel_type, :engine_size, :monthly_rate, :down_payment, :image_url, :description)";

                $stmt = $pdo->prepare($sql);
                
                // Bindowanie parametrów dla bezpieczeństwa
                $stmt->execute([
                    ':name' => $name,
                    ':year' => $year,
                    ':mileage' => $mileage,
                    ':fuel_type' => $fuel_type,
                    ':engine_size' => $engine_size,
                    ':monthly_rate' => $monthly_rate,
                    ':down_payment' => $down_payment,
                    ':image_url' => $image_url,
                    ':description' => $description
                ]);
                
                // Przekieruj z powrotem do formularza z komunikatem o sukcesie
                header('Location: /admin/add-car?status=success');
                exit();

            } catch (PDOException $e) {
                // W przypadku błędu, wyświetl formularz ponownie z komunikatem o błędzie
                echo $twig->render('pages/admin/add_car.twig', [
                    'error' => 'Wystąpił błąd podczas zapisu: ' . $e->getMessage()
                ]);
            }

        } else {
            // --- WYŚWIETLANIE PUSTEGO FORMULARZA ---
            // Jeśli metoda to GET, po prostu renderujemy szablon formularza
            echo $twig->render('pages/admin/add_car.twig', [
                // Możesz przekazać status z URL, aby wyświetlić komunikat o sukcesie
                'success' => ($_GET['status'] ?? '') === 'success'
            ]);
        }
        break;

    // --- Domyślna trasa, gdy strona nie istnieje ---
    default:
        header("HTTP/1.0 404 Not Found");
        echo $twig->render('pages/static/404.twig'); // Potrzebujesz stworzyć plik 404.twig
        break;
}