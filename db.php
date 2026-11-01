<?php
// db.php - database connection, schema and starter data
date_default_timezone_set('Asia/Kolkata');

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;

    $path = getenv('HOTEL_DB');
    if (!$path) {
        $path = is_writable(__DIR__) ? __DIR__ . '/hotel.db' : sys_get_temp_dir() . '/hotel.db';
    }
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
        payment TEXT NOT NULL DEFAULT 'Pending',
        pay_method TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS charges (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_id INTEGER NOT NULL,
        description TEXT NOT NULL,
        amount REAL NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // keep older databases compatible
    $cols = array();
    foreach ($pdo->query("PRAGMA table_info(bookings)")->fetchAll() as $c) $cols[] = $c['name'];
    if (!in_array('payment', $cols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN payment TEXT NOT NULL DEFAULT 'Pending'");
    if (!in_array('pay_method', $cols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN pay_method TEXT");

    seed_demo($pdo);
    return $pdo;
}

function seed_demo($pdo) {
    if ((int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn() > 0) return;

    $rooms = array(
        array('101', 'Standard', 1800), array('102', 'Standard', 1800), array('103', 'Standard', 2000),
        array('201', 'Deluxe', 2800),   array('202', 'Deluxe', 2800),   array('203', 'Deluxe', 3200),
        array('301', 'Suite', 4800),    array('302', 'Suite', 5200),
        array('401', 'Presidential', 9500),
    );
    $ins = $pdo->prepare("INSERT INTO rooms(room_no,type,price) VALUES(?,?,?)");
    foreach ($rooms as $r) $ins->execute($r);

    $guests = array(
        array('Aarav Mehta', '+91 98200 11122', 'aarav.mehta@example.com', 'Aadhaar ending 4821'),
        array('Priya Nair', '+91 98450 22233', 'priya.nair@example.com', 'Passport ending 7710'),
        array('Rohan Deshmukh', '+91 99670 33344', 'rohan.d@example.com', 'Aadhaar ending 1936'),
        array('Sneha Kulkarni', '+91 98190 44455', 'sneha.k@example.com', 'Aadhaar ending 5502'),
        array('Vikram Singh', '+91 97020 55566', 'vikram.singh@example.com', 'Passport ending 3087'),
        array('Ananya Iyer', '+91 98330 66677', 'ananya.iyer@example.com', 'Aadhaar ending 6644'),
        array('Karan Patel', '+91 98920 77788', 'karan.patel@example.com', 'Aadhaar ending 2279'),
        array('Meera Joshi', '+91 98210 88899', 'meera.joshi@example.com', 'Aadhaar ending 9015'),
    );
    $gs = $pdo->prepare("INSERT INTO guests(name,phone,email,id_proof) VALUES(?,?,?,?)");
    foreach ($guests as $g) $gs->execute($g);

    // guest no, room no, check-in offset (days), check-out offset, status, payment, method
    $bookings = array(
        array(1, '101', -6, -3, 'Checked-out', 'Paid', 'Card'),
        array(2, '201', -5, -2, 'Checked-out', 'Paid', 'UPI'),
        array(3, '301', -4, -1, 'Checked-out', 'Paid', 'Cash'),
        array(4, '202', -1, 2, 'Checked-in', 'Pending', null),
        array(5, '401', -2, 0, 'Checked-in', 'Pending', null),
        array(6, '102', 0, 2, 'Booked', 'Pending', null),
        array(7, '203', 2, 5, 'Booked', 'Pending', null),
        array(8, '302', 3, 6, 'Booked', 'Pending', null),
        array(7, '102', 5, 7, 'Cancelled', 'Pending', null),
    );
    $find = $pdo->prepare("SELECT id, price FROM rooms WHERE room_no=?");
    $bk = $pdo->prepare("INSERT INTO bookings(guest_id,room_id,check_in,check_out,nights,total,status,payment,pay_method) VALUES(?,?,?,?,?,?,?,?,?)");
    $occ = $pdo->prepare("UPDATE rooms SET status='Occupied' WHERE id=?");
    foreach ($bookings as $b) {
        $find->execute(array($b[1]));
        $room = $find->fetch();
        $in = date('Y-m-d', strtotime($b[2] . ' days'));
        $out = date('Y-m-d', strtotime($b[3] . ' days'));
        $nights = max(1, $b[3] - $b[2]);
        $bk->execute(array($b[0], $room['id'], $in, $out, $nights, $nights * $room['price'], $b[4], $b[5], $b[6]));
        if ($b[4] === 'Checked-in') $occ->execute(array($room['id']));
    }
    $pdo->exec("UPDATE rooms SET status='Cleaning' WHERE room_no='101'");
    $pdo->exec("UPDATE rooms SET status='Maintenance' WHERE room_no='103'");

    $charges = array(
        array(1, 'Restaurant', 1450), array(1, 'Laundry', 320), array(2, 'Spa', 3200),
        array(3, 'Airport transfer', 1800), array(3, 'Restaurant', 2100),
        array(4, 'Restaurant', 980), array(5, 'Room service', 640),
    );
    $cs = $pdo->prepare("INSERT INTO charges(booking_id,description,amount) VALUES(?,?,?)");
    foreach ($charges as $c) $cs->execute($c);
}
