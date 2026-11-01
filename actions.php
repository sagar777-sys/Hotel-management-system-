<?php
// actions.php - business logic
require_once __DIR__ . '/db.php';

define('ADMIN_HASH', '47ee6655fee0c469f8f9646b0de829980745758774a7afe2ec4018eac10730d3');

function result($ok, $msg, $extra = array()) {
    return array_merge(array('ok' => $ok, 'message' => $msg), $extra);
}

function arg($d, $key, $default = '') {
    return isset($d[$key]) ? $d[$key] : $default;
}

function login($d) {
    $u = trim((string)arg($d, 'username'));
    $p = (string)arg($d, 'password');
    if ($u === 'admin' && hash('sha256', $p) === ADMIN_HASH) return result(true, 'Signed in.');
    return result(false, 'Incorrect username or password.');
}

/* ---------- rooms ---------- */
function add_room($d) {
    $no = trim((string)arg($d, 'room_no'));
    $type = arg($d, 'type', 'Standard');
    $price = (float)arg($d, 'price', 0);
    if ($no === '' || $price <= 0) return result(false, 'Room number and a valid price are required.');
    try {
        db()->prepare("INSERT INTO rooms(room_no,type,price) VALUES(?,?,?)")->execute(array($no, $type, $price));
    } catch (PDOException $e) {
        return result(false, "Room $no already exists.");
    }
    return result(true, "Room $no added.");
}

function delete_room($d) {
    $id = (int)arg($d, 'room_id', 0);
    $c = db()->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=?");
    $c->execute(array($id));
    if ($c->fetchColumn() > 0) return result(false, 'This room has reservation history and cannot be removed.');
    db()->prepare("DELETE FROM rooms WHERE id=?")->execute(array($id));
    return result(true, 'Room removed.');
}

function set_room_status($d) {
    $id = (int)arg($d, 'room_id', 0);
    $st = arg($d, 'status');
    if (!in_array($st, array('Available', 'Cleaning', 'Maintenance'))) return result(false, 'Invalid status.');
    $s = db()->prepare("SELECT room_no, status FROM rooms WHERE id=?");
    $s->execute(array($id));
    $room = $s->fetch();
    if (!$room) return result(false, 'Room not found.');
    if ($room['status'] === 'Occupied') return result(false, 'An occupied room cannot be changed. Check the guest out first.');
    db()->prepare("UPDATE rooms SET status=? WHERE id=?")->execute(array($st, $id));
    return result(true, "Room {$room['room_no']} is now $st.");
}

function list_rooms($d = array()) {
    $sql = "SELECT * FROM rooms WHERE 1=1";
    $p = array();
    $type = arg($d, 'type', 'All');
    $status = arg($d, 'status', 'All');
    if ($type !== 'All' && $type !== '') { $sql .= " AND type=?"; $p[] = $type; }
    if ($status !== 'All' && $status !== '') { $sql .= " AND status=?"; $p[] = $status; }
    $s = db()->prepare($sql . " ORDER BY room_no");
    $s->execute($p);
    return array('rooms' => $s->fetchAll());
}

function available_rooms($d) {
    $in = arg($d, 'check_in');
    $out = arg($d, 'check_out');
    $t1 = strtotime($in);
    $t2 = strtotime($out);
    if (!$t1 || !$t2) return result(false, 'Please choose valid dates.', array('rooms' => array(), 'nights' => 0));
    if ($t2 <= $t1) return result(false, 'Check-out must be after check-in.', array('rooms' => array(), 'nights' => 0));
    $sql = "SELECT * FROM rooms WHERE status <> 'Maintenance' AND id NOT IN (
              SELECT room_id FROM bookings WHERE status IN ('Booked','Checked-in') AND check_in < ? AND check_out > ?)";
    $p = array($out, $in);
    $type = arg($d, 'type', 'Any');
    if ($type !== 'Any' && $type !== '' && $type !== 'All') { $sql .= " AND type=?"; $p[] = $type; }
    $s = db()->prepare($sql . " ORDER BY price");
    $s->execute($p);
    return result(true, '', array('rooms' => $s->fetchAll(), 'nights' => (int)round(($t2 - $t1) / 86400),
                                  'check_in' => $in, 'check_out' => $out));
}

/* ---------- guests ---------- */
function add_guest($d) {
    $name = trim((string)arg($d, 'name'));
    if ($name === '') return result(false, 'Guest name is required.');
    db()->prepare("INSERT INTO guests(name,phone,email,id_proof) VALUES(?,?,?,?)")
        ->execute(array($name, trim((string)arg($d, 'phone')), trim((string)arg($d, 'email')), trim((string)arg($d, 'id_proof'))));
    return result(true, "Guest $name registered.");
}

function list_guests($d = array()) {
    $q = trim((string)arg($d, 'q'));
    if ($q === '') return array('guests' => db()->query("SELECT * FROM guests ORDER BY name")->fetchAll());
    $s = db()->prepare("SELECT * FROM guests WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY name");
    $like = '%' . $q . '%';
    $s->execute(array($like, $like, $like));
    return array('guests' => $s->fetchAll());
}

/* ---------- reservations ---------- */
function book($d) {
    $g = (int)arg($d, 'guest_id', 0);
    $r = (int)arg($d, 'room_id', 0);
    $in = arg($d, 'check_in');
    $out = arg($d, 'check_out');
    $t1 = strtotime($in);
    $t2 = strtotime($out);
    if (!$g || !$r || !$t1 || !$t2) return result(false, 'Select a guest, a room and valid dates.');
    if ($t2 <= $t1) return result(false, 'Check-out must be after check-in.');
    if ($in < date('Y-m-d')) return result(false, 'Check-in cannot be in the past.');

    $rs = db()->prepare("SELECT price, status FROM rooms WHERE id=?");
    $rs->execute(array($r));
    $room = $rs->fetch();
    if (!$room) return result(false, 'Room not found.');
    if ($room['status'] === 'Maintenance') return result(false, 'This room is under maintenance.');

    $s = db()->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status IN ('Booked','Checked-in')
                        AND check_in < ? AND check_out > ?");
    $s->execute(array($r, $out, $in));
    if ($s->fetchColumn() > 0) return result(false, 'Room is not available for those dates.');

    $nights = (int)round(($t2 - $t1) / 86400);
    $total = $nights * (float)$room['price'];
    db()->prepare("INSERT INTO bookings(guest_id,room_id,check_in,check_out,nights,total) VALUES(?,?,?,?,?,?)")
        ->execute(array($g, $r, $in, $out, $nights, $total));
    $id = (int)db()->lastInsertId();
    return result(true, "Reservation #$id confirmed: $nights night(s), room charges ₹" . number_format($total, 2), array('booking_id' => $id));
}

function online_booking($d) {
    $name = trim((string)arg($d, 'name'));
    $phone = trim((string)arg($d, 'phone'));
    if ($name === '' || $phone === '') return result(false, 'Please enter your name and phone number.');
    $pdo = db();
    $pdo->prepare("INSERT INTO guests(name,phone,email) VALUES(?,?,?)")->execute(array($name, $phone, trim((string)arg($d, 'email'))));
    $gid = (int)$pdo->lastInsertId();
    $d['guest_id'] = $gid;
    $res = book($d);
    if (!$res['ok']) {
        $pdo->prepare("DELETE FROM guests WHERE id=?")->execute(array($gid));
        return $res;
    }
    return result(true, "Reservation #{$res['booking_id']} confirmed for $name. We look forward to welcoming you!", array('booking_id' => $res['booking_id']));
}

function get_booking($id) {
    $s = db()->prepare("SELECT * FROM bookings WHERE id=?");
    $s->execute(array($id));
    return $s->fetch();
}

function get_charges($id) {
    $s = db()->prepare("SELECT * FROM charges WHERE booking_id=? ORDER BY id");
    $s->execute(array($id));
    return $s->fetchAll();
}

function check_in($d) {
    $b = get_booking((int)arg($d, 'booking_id', 0));
    if (!$b) return result(false, 'Reservation not found.');
    if ($b['status'] !== 'Booked') return result(false, "Cannot check in a reservation that is {$b['status']}.");
    $rs = db()->prepare("SELECT status FROM rooms WHERE id=?");
    $rs->execute(array($b['room_id']));
    $rstatus = $rs->fetchColumn();
    if ($rstatus === 'Occupied') return result(false, 'The room is still occupied by another guest.');
    if ($rstatus === 'Maintenance') return result(false, 'The room is under maintenance.');
    db()->prepare("UPDATE bookings SET status='Checked-in' WHERE id=?")->execute(array($b['id']));
    db()->prepare("UPDATE rooms SET status='Occupied' WHERE id=?")->execute(array($b['room_id']));
    return result(true, "Reservation #{$b['id']} checked in. Welcome!");
}

function check_out($d) {
    $b = get_booking((int)arg($d, 'booking_id', 0));
    if (!$b) return result(false, 'Reservation not found.');
    if ($b['status'] !== 'Checked-in') return result(false, 'Only checked-in guests can be checked out.');
    db()->prepare("UPDATE bookings SET status='Checked-out' WHERE id=?")->execute(array($b['id']));
    db()->prepare("UPDATE rooms SET status='Cleaning' WHERE id=?")->execute(array($b['room_id']));
    return result(true, "Reservation #{$b['id']} checked out. The invoice is ready in Billing.");
}

function cancel_booking($d) {
    $b = get_booking((int)arg($d, 'booking_id', 0));
    if (!$b) return result(false, 'Reservation not found.');
    if ($b['status'] !== 'Booked') return result(false, "Cannot cancel a reservation that is {$b['status']}.");
    db()->prepare("UPDATE bookings SET status='Cancelled' WHERE id=?")->execute(array($b['id']));
    return result(true, "Reservation #{$b['id']} cancelled.");
}

function add_charge($d) {
    $b = get_booking((int)arg($d, 'booking_id', 0));
    $amount = (float)arg($d, 'amount', 0);
    $desc = trim((string)arg($d, 'description'));
    if (!$b) return result(false, 'Reservation not found.');
    if ($b['status'] !== 'Checked-in') return result(false, 'Services can only be added to checked-in guests.');
    if ($amount <= 0 || $desc === '') return result(false, 'Enter a service and a valid amount.');
    db()->prepare("INSERT INTO charges(booking_id,description,amount) VALUES(?,?,?)")->execute(array($b['id'], $desc, $amount));
    return result(true, "$desc ₹" . number_format($amount, 2) . " added to reservation #{$b['id']}.");
}

function pay_booking($d) {
    $b = get_booking((int)arg($d, 'booking_id', 0));
    if (!$b) return result(false, 'Reservation not found.');
    if (!in_array($b['status'], array('Checked-in', 'Checked-out'))) return result(false, 'Payment can be recorded after check-in.');
    if ($b['payment'] === 'Paid') return result(false, 'This reservation is already paid.');
    db()->prepare("UPDATE bookings SET payment='Paid', pay_method=? WHERE id=?")->execute(array(arg($d, 'method', 'Cash'), $b['id']));
    return result(true, "Payment recorded for reservation #{$b['id']}.");
}

function list_bookings($d = array()) {
    $sql = "SELECT b.*, g.name AS guest, r.room_no, r.type AS room_type
            FROM bookings b JOIN guests g ON g.id=b.guest_id JOIN rooms r ON r.id=b.room_id";
    $p = array();
    $status = arg($d, 'status', 'All');
    if ($status !== 'All' && $status !== '') { $sql .= " WHERE b.status=?"; $p[] = $status; }
    $s = db()->prepare($sql . " ORDER BY b.id DESC");
    $s->execute($p);
    return array('bookings' => $s->fetchAll());
}

/* ---------- numbers ---------- */
function stats() {
    $q = function ($sql) { return (float)db()->query($sql)->fetchColumn(); };
    $today = date('Y-m-d');
    $room_rev = $q("SELECT COALESCE(SUM(total),0) FROM bookings WHERE status='Checked-out'");
    $svc_rev = $q("SELECT COALESCE(SUM(c.amount),0) FROM charges c JOIN bookings b ON b.id=c.booking_id WHERE b.status='Checked-out'");
    return array(
        'rooms'      => (int)$q("SELECT COUNT(*) FROM rooms"),
        'available'  => (int)$q("SELECT COUNT(*) FROM rooms WHERE status='Available'"),
        'occupied'   => (int)$q("SELECT COUNT(*) FROM rooms WHERE status='Occupied'"),
        'service'    => (int)$q("SELECT COUNT(*) FROM rooms WHERE status IN ('Cleaning','Maintenance')"),
        'guests'     => (int)$q("SELECT COUNT(*) FROM guests"),
        'active'     => (int)$q("SELECT COUNT(*) FROM bookings WHERE status IN ('Booked','Checked-in')"),
        'revenue'    => $room_rev + $svc_rev,
        'arrivals'   => (int)$q("SELECT COUNT(*) FROM bookings WHERE status='Booked' AND check_in='$today'"),
        'departures' => (int)$q("SELECT COUNT(*) FROM bookings WHERE status='Checked-in' AND check_out='$today'"),
    );
}

function report_data() {
    return array(
        'revenue_by_type' => db()->query("SELECT r.type AS label, SUM(b.total) AS value FROM bookings b JOIN rooms r ON r.id=b.room_id
            WHERE b.status IN ('Checked-in','Checked-out') GROUP BY r.type ORDER BY value DESC")->fetchAll(),
        'status_counts' => db()->query("SELECT status AS label, COUNT(*) AS value FROM bookings GROUP BY status ORDER BY value DESC")->fetchAll(),
        'services' => db()->query("SELECT description AS label, SUM(amount) AS value FROM charges GROUP BY description ORDER BY value DESC")->fetchAll(),
    );
}
