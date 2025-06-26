CREATE TABLE samochody (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marka VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    kategoria VARCHAR(50) NOT NULL,
    skrzynia_biegow VARCHAR(20) NOT NULL CHECK(skrzynia_biegow IN ('Automatyczna', 'Manualna')),
    liczba_miejsc INT NOT NULL,
    cena_dzien_roboczy DECIMAL(10, 2) NOT NULL,
    cena_dzien_weekend DECIMAL(10, 2) NOT NULL,
    zdjecie_url VARCHAR(255)
);

CREATE TABLE rezerwacje (
    id INT AUTO_INCREMENT PRIMARY KEY,
    samochod_id INT NOT NULL,
    data_od DATE NOT NULL,
    data_do DATE NOT NULL,
    email_klienta VARCHAR(255) NOT NULL,
    calkowity_koszt DECIMAL(10, 2) NOT NULL,
    token_anulowania VARCHAR(255) NOT NULL UNIQUE,
    data_utworzenia TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (samochod_id) REFERENCES samochody(id)
);