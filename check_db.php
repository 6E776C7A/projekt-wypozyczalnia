<?php
try {
    $pdo = new PDO('sqlite:data/mydb.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM cars');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Liczba samochodów w bazie: " . $result['count'] . "\n";
    
    if ($result['count'] > 0) {
        $stmt = $pdo->query('SELECT * FROM cars LIMIT 3');
        $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Przykładowe samochody:\n";
        foreach ($cars as $car) {
            echo "- " . $car['make'] . " " . $car['model'] . " (" . $car['workday_price'] . " zł/dzień)\n";
        }
    }
    
} catch (Exception $e) {
    echo "Błąd: " . $e->getMessage() . "\n";
}
?> 