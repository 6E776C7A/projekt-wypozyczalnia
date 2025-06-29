CREATE TABLE cars (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    make TEXT NOT NULL,
    model TEXT NOT NULL,
    category TEXT NOT NULL,
    transmission TEXT NOT NULL CHECK(transmission IN ('Automatic', 'Manual')),
    seats INTEGER NOT NULL,
    workday_price REAL NOT NULL,
    weekend_price REAL NOT NULL,
    image_url TEXT
);

CREATE TABLE reservations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    car_id INTEGER NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    customer_email TEXT NOT NULL,
    total_cost REAL NOT NULL,
    cancellation_token TEXT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (car_id) REFERENCES cars(id)
);