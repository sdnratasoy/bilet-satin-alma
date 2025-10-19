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

        $trips = [];
        $routes = [
            [1, 'İstanbul', 'Ankara', 5, 250],
            [1, 'Ankara', 'İstanbul', 5, 250],
            [2, 'İstanbul', 'İzmir', 8, 300],
            [2, 'İzmir', 'İstanbul', 8, 300],
            [3, 'İstanbul', 'Antalya', 12, 400],
            [3, 'Antalya', 'İstanbul', 12, 400],
            [1, 'İstanbul', 'Bursa', 3, 150],
            [1, 'Bursa', 'İstanbul', 3, 150],
            [2, 'Ankara', 'İzmir', 8, 280],
            [2, 'İzmir', 'Ankara', 8, 280],
            [3, 'Ankara', 'Antalya', 10, 350],
            [3, 'Antalya', 'Ankara', 10, 350],
            [4, 'İzmir', 'Antalya', 7, 320],
            [4, 'Antalya', 'İzmir', 7, 320],
            [1, 'İstanbul', 'Adana', 14, 450],
            [1, 'Adana', 'İstanbul', 14, 450],
            [2, 'İstanbul', 'Gaziantep', 16, 500],
            [2, 'Gaziantep', 'İstanbul', 16, 500],
            [3, 'İstanbul', 'Konya', 10, 350],
            [3, 'Konya', 'İstanbul', 10, 350],
            [4, 'İstanbul', 'Samsun', 12, 380],
            [4, 'Samsun', 'İstanbul', 12, 380],
            [1, 'Ankara', 'Konya', 4, 200],
            [1, 'Konya', 'Ankara', 4, 200],
            [2, 'Ankara', 'Adana', 9, 320],
            [2, 'Adana', 'Ankara', 9, 320],
            [3, 'İzmir', 'Bursa', 6, 220],
            [3, 'Bursa', 'İzmir', 6, 220],
            [4, 'Bursa', 'Ankara', 6, 230],
            [4, 'Ankara', 'Bursa', 6, 230],
            [1, 'İstanbul', 'Kayseri', 11, 370],
            [1, 'Kayseri', 'İstanbul', 11, 370],
            [2, 'Ankara', 'Samsun', 7, 270],
            [2, 'Samsun', 'Ankara', 7, 270],
            [3, 'İzmir', 'Adana', 13, 420],
            [3, 'Adana', 'İzmir', 13, 420],
            [4, 'Antalya', 'Konya', 5, 250],
            [4, 'Konya', 'Antalya', 5, 250],
        ];

        for ($day = 0; $day < 7; $day++) {
            $date = date('Y-m-d', strtotime("+$day days"));

            foreach ($routes as $route) {
                list($company, $origin, $dest, $duration, $price) = $route;

                $dept1 = "$date 08:00:00";
                $arrv1 = date('Y-m-d H:i:s', strtotime($dept1) + ($duration * 3600));
                $trips[] = [$company, $origin, $dest, $dept1, $arrv1, $price, 40];

                $dept2 = "$date 14:00:00";
                $arrv2 = date('Y-m-d H:i:s', strtotime($dept2) + ($duration * 3600));
                $trips[] = [$company, $origin, $dest, $dept2, $arrv2, $price, 40];

                $dept3 = "$date 21:00:00";
                $arrv3 = date('Y-m-d H:i:s', strtotime($dept3) + ($duration * 3600));
                $trips[] = [$company, $origin, $dest, $dept3, $arrv3, $price, 40];
            }
        }

        foreach ($trips as $trip) {
            try {
                $this->pdo->exec("INSERT INTO Trips (company_id, origin_city, destination_city, departure_time, arrival_time, price, capacity)
                                 VALUES ({$trip[0]}, '{$trip[1]}', '{$trip[2]}', '{$trip[3]}', '{$trip[4]}', {$trip[5]}, {$trip[6]})");
            } catch (Exception $e) {
                error_log("Trip insert error: " . $e->getMessage());
            }
        }

        $coupons = [
            ['ROADFINDER10', 10.0, NULL, 1000, '2025-12-31 23:59:59'],
            ['ROADFINDER15', 15.0, NULL, 500, '2025-12-31 23:59:59'],
            ['YENIYIL25', 25.0, NULL, 100, '2026-01-15 23:59:59'],
            ['SONBAHAR20', 20.0, NULL, 200, '2025-11-30 23:59:59'],

            ['METRO20', 20.0, 1, 300, '2025-12-31 23:59:59'],
            ['METRO30', 30.0, 1, 100, '2025-11-30 23:59:59'],
            ['PAMUKKALE15', 15.0, 2, 250, '2025-12-31 23:59:59'],
            ['PAMUKKALE25', 25.0, 2, 150, '2025-11-15 23:59:59'],
            ['KAMIL20', 20.0, 3, 200, '2025-12-31 23:59:59'],
            ['NILUFER15', 15.0, 4, 300, '2025-12-31 23:59:59'],

            ['ERKENREZERVASYON', 12.0, NULL, 500, '2025-12-31 23:59:59'],
            ['OGRENCI10', 10.0, NULL, 1000, '2025-12-31 23:59:59'],
            ['KARAPAZMARTESI', 50.0, NULL, 50, '2025-11-29 23:59:59'],
        ];

        foreach ($coupons as $coupon) {
            try {
                $company_id_value = $coupon[2] === NULL ? 'NULL' : $coupon[2];
                $this->pdo->exec("INSERT INTO Coupons (code, discount, company_id, usage_limit, expire_date)
                                 VALUES ('{$coupon[0]}', {$coupon[1]}, $company_id_value, {$coupon[3]}, '{$coupon[4]}')");
            } catch (Exception $e) {
                error_log("Coupon insert error: " . $e->getMessage());
            }
        }
    }
}


$db = new Database();
$pdo = $db->getConnection();
?>