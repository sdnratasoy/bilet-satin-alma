<?php

class Database {
    private $db_file;
    private $pdo;

    public function __construct() {
        
        $db_dir = __DIR__ . '/../database';
        if (!is_dir($db_dir)) {
            mkdir($db_dir, 0777, true);
        }
        
        $this->db_file = $db_dir . '/roadfinder.db';
        
        try {
            $this->pdo = new PDO('sqlite:' . $this->db_file);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            
            $this->createTables();
            
            
            $this->insertDefaultData();
        } catch (PDOException $e) {
            die("Database bağlantı hatası: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function createTables() {
        
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS User (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('admin', 'firma_admin', 'user')),
            balance REAL DEFAULT 1000.0,
            company_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES Bus_Company(id) ON DELETE SET NULL
        )");

        
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS Bus_Company (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS Trips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            origin_city TEXT NOT NULL,
            destination_city TEXT NOT NULL,
            departure_time DATETIME NOT NULL,
            arrival_time DATETIME NOT NULL,
            price REAL NOT NULL,
            capacity INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES Bus_Company(id) ON DELETE CASCADE
        )");

        
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS Tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trip_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            seat_number INTEGER NOT NULL,
            total_price REAL NOT NULL,
            status TEXT DEFAULT 'active' CHECK(status IN ('active', 'cancelled')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trip_id) REFERENCES Trips(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
        )");

        
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS Booked_Seats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trip_id INTEGER NOT NULL,
            seat_number INTEGER NOT NULL,
            ticket_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trip_id) REFERENCES Trips(id) ON DELETE CASCADE,
            FOREIGN KEY (ticket_id) REFERENCES Tickets(id) ON DELETE CASCADE,
            UNIQUE(trip_id, seat_number)
        )");

    
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS Coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            discount REAL NOT NULL,
            company_id INTEGER,
            usage_limit INTEGER,
            used_count INTEGER DEFAULT 0,
            expire_date DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES Bus_Company(id) ON DELETE CASCADE
        )");
    }

    private function insertDefaultData() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM User");
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return; 
        }
        $companies = [
            'Metro Turizm',
            'Pamukkale Turizm',
            'Kamil Koç',
            'Nilüfer Turizm'
        ];

        foreach ($companies as $company) {
            $this->pdo->exec("INSERT INTO Bus_Company (name) VALUES ('$company')");
        }

        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $firma_pass = password_hash('firma123', PASSWORD_DEFAULT);
        $user_pass = password_hash('user123', PASSWORD_DEFAULT);

        $this->pdo->exec("INSERT INTO User (full_name, email, password, role, balance) 
                         VALUES ('Admin User', 'admin@roadfinder.com', '$admin_pass', 'admin', 10000.0)");

        $this->pdo->exec("INSERT INTO User (full_name, email, password, role, balance, company_id) 
                         VALUES ('Firma Admin', 'firma@roadfinder.com', '$firma_pass', 'firma_admin', 5000.0, 1)");

        $this->pdo->exec("INSERT INTO User (full_name, email, password, role, balance) 
                         VALUES ('Test User', 'user@roadfinder.com', '$user_pass', 'user', 1000.0)");

        $trips = [
            [1, 'İstanbul', 'Ankara', '2025-10-20 09:00:00', '2025-10-20 14:00:00', 250.0, 45],
            [1, 'Ankara', 'İstanbul', '2025-10-20 15:00:00', '2025-10-20 20:00:00', 250.0, 45],
            [2, 'İstanbul', 'İzmir', '2025-10-21 10:00:00', '2025-10-21 18:00:00', 300.0, 50],
            [2, 'İzmir', 'İstanbul', '2025-10-21 19:00:00', '2025-10-22 03:00:00', 300.0, 50],
            [3, 'İstanbul', 'Antalya', '2025-10-22 08:00:00', '2025-10-22 20:00:00', 400.0, 40],
            [4, 'Ankara', 'İzmir', '2025-10-23 11:00:00', '2025-10-23 19:00:00', 280.0, 45]
        ];

        foreach ($trips as $trip) {
            $this->pdo->exec("INSERT INTO Trips (company_id, origin_city, destination_city, departure_time, arrival_time, price, capacity) 
                             VALUES ({$trip[0]}, '{$trip[1]}', '{$trip[2]}', '{$trip[3]}', '{$trip[4]}', {$trip[5]}, {$trip[6]})");
        }

        $this->pdo->exec("INSERT INTO Coupons (code, discount, company_id, usage_limit, expire_date) 
                         VALUES ('ROADFINDER10', 10.0, NULL, 100, '2025-12-31 23:59:59')");

        $this->pdo->exec("INSERT INTO Coupons (code, discount, company_id, usage_limit, expire_date) 
                         VALUES ('METRO20', 20.0, 1, 50, '2025-11-30 23:59:59')");
    }
}


$db = new Database();
$pdo = $db->getConnection();
?>