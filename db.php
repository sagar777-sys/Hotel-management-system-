<?php
// db.php - SQLite connection, schema and demo data
function db() {
    static $pdo = null;
    if ($pdo) return $pdo;

    $path = getenv('HOTEL_DB') ?: sys_get_temp_dir() . '/hotel_management.db';
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        room_no TEXT UNIQUE NOT NULL,
        type TEXT NOT NULL,
        price REAL NOT NULL,
        status TEXT NOT NULL DEFAULT 'Available'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS guests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT,
        email TEXT,
        id_proof TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        guest_id INTEGER NOT NULL,
        room_id INTEGER NOT NULL,
        check_in TEXT NOT NULL,
        check_out TEXT NOT NULL,
        nights INTEGER NOT NULL,
        total REAL NOT NULL,
        status TEXT NOT NULL DEFAULT 'Booked',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // Demo rooms on first run
    if ((int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn() === 0) {
        $demo = [
            ['101', 'Standard', 1800], ['102', 'Standard', 1800],
            ['201', 'Deluxe', 2800],   ['202', 'Deluxe', 2800],
            ['301', 'Suite', 4800],    ['302', 'Suite', 4800],
            ['401', 'Presidential', 9500],
        ];
        $st = $pdo->prepare("INSERT INTO rooms(room_no,type,price) VALUES(?,?,?)");
        foreach ($demo as $r) $st->execute($r);
    }
    return $pdo;
}
