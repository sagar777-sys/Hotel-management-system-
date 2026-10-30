<?php
// actions.php - business logic (all return arrays)
require_once __DIR__ . '/db.php';

function result($ok, $msg, $extra = []) {
    return array_merge(['ok' => $ok, 'message' => $msg], $extra);
}

function add_room($d) {
    $no = trim($d['room_no'] ?? '');
    $type = $d['type'] ?? 'Standard';
    $price = (float)($d['price'] ?? 0);
    if ($no === '' || $price <= 0) return result(false, 'Room number and a valid price are required.');
    try {
        db()->prepare("INSERT INTO rooms(room_no,type,price) VALUES(?,?,?)")->execute([$no, $type, $price]);
    } catch (PDOException $e) {
        return result(false, "Room $no already exists.");
    }
    return result(true, "Room $no added.");
}

function delete_room($d) {
    $id = (int)($d['room_id'] ?? 0);
    $c = db()->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=?");
    $c->execute([$id]);
    if ($c->fetchColumn() > 0) return result(false, 'Cannot delete: this room has booking history.');
    db()->prepare("DELETE FROM rooms WHERE id=?")->execute([$id]);
    return result(true, 'Room deleted.');
}

function add_guest($d) {
    $name = trim($d['name'] ?? '');
    if ($name === '') return result(false, 'Guest name is required.');
    db()->prepare("INSERT INTO guests(name,phone,email,id_proof) VALUES(?,?,?,?)")
        ->execute([$name, trim($d['phone'] ?? ''), trim($d['email'] ?? ''), trim($d['id_proof'] ?? '')]);
    return result(true, "Guest $name registered.");
}

function book($d) {
    $g = (int)($d['guest_id'] ?? 0);
    $r = (int)($d['room_id'] ?? 0);
    $in = $d['check_in'] ?? '';
    $out = $d['check_out'] ?? '';
    $t1 = strtotime($in);
    $t2 = strtotime($out);
    if (!$g || !$r || !$t1 || !$t2) return result(false, 'Select a guest, a room and valid dates.');
    if ($t2 <= $t1) return result(false, 'Check-out must be after check-in.');

    $s = db()->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status IN ('Booked','Checked-in')
                        AND check_in < ? AND check_out > ?");
    $s->execute([$r, $out, $in]);
    if ($s->fetchColumn() > 0) return result(false, 'Room is not available for those dates.');

    $p = db()->prepare("SELECT price FROM rooms WHERE id=?");
    $p->execute([$r]);
    $price = (float)$p->fetchColumn();
    $nights = (int)round(($t2 - $t1) / 86400);
    $total = $nights * $price;

    db()->prepare("INSERT INTO bookings(guest_id,room_id,check_in,check_out,nights,total) VALUES(?,?,?,?,?,?)")
        ->execute([$g, $r, $in, $out, $nights, $total]);
    $id = (int)db()->lastInsertId();
    return result(true, "Booking #$id confirmed - $nights night(s), subtotal Rs " . number_format($total, 2), ['booking_id' => $id]);
}

function get_booking($id) {
    $s = db()->prepare("SELECT * FROM bookings WHERE id=?");
    $s->execute([$id]);
    return $s->fetch();
}

function check_in($d) {
    $b = get_booking((int)($d['booking_id'] ?? 0));
    if (!$b) return result(false, 'Booking not found.');
    if ($b['status'] !== 'Booked') return result(false, "Cannot check in a booking that is {$b['status']}.");
    db()->prepare("UPDATE bookings SET status='Checked-in' WHERE id=?")->execute([$b['id']]);
    db()->prepare("UPDATE rooms SET status='Occupied' WHERE id=?")->execute([$b['room_id']]);
    return result(true, "Booking #{$b['id']} checked in.");
}

function check_out($d) {
    $b = get_booking((int)($d['booking_id'] ?? 0));
    if (!$b) return result(false, 'Booking not found.');
    if ($b['status'] !== 'Checked-in') return result(false, 'Only checked-in bookings can be checked out.');
    db()->prepare("UPDATE bookings SET status='Checked-out' WHERE id=?")->execute([$b['id']]);
    db()->prepare("UPDATE rooms SET status='Available' WHERE id=?")->execute([$b['room_id']]);
    return result(true, "Booking #{$b['id']} checked out. Invoice is ready.");
}

function cancel_booking($d) {
    $b = get_booking((int)($d['booking_id'] ?? 0));
    if (!$b) return result(false, 'Booking not found.');
    if ($b['status'] !== 'Booked') return result(false, "Cannot cancel a booking that is {$b['status']}.");
    db()->prepare("UPDATE bookings SET status='Cancelled' WHERE id=?")->execute([$b['id']]);
    return result(true, "Booking #{$b['id']} cancelled.");
}

function list_rooms()  { return ['rooms'  => db()->query("SELECT * FROM rooms ORDER BY room_no")->fetchAll()]; }
function list_guests() { return ['guests' => db()->query("SELECT * FROM guests ORDER BY name")->fetchAll()]; }
function list_bookings() {
    return ['bookings' => db()->query("SELECT b.*, g.name AS guest, r.room_no
        FROM bookings b JOIN guests g ON g.id=b.guest_id JOIN rooms r ON r.id=b.room_id
        ORDER BY b.id DESC")->fetchAll()];
}

function stats() {
    $q = function ($sql) { return (float)db()->query($sql)->fetchColumn(); };
    return [
        'rooms'     => (int)$q("SELECT COUNT(*) FROM rooms"),
        'available' => (int)$q("SELECT COUNT(*) FROM rooms WHERE status='Available'"),
        'occupied'  => (int)$q("SELECT COUNT(*) FROM rooms WHERE status='Occupied'"),
        'guests'    => (int)$q("SELECT COUNT(*) FROM guests"),
        'active'    => (int)$q("SELECT COUNT(*) FROM bookings WHERE status IN ('Booked','Checked-in')"),
        'revenue'   => $q("SELECT COALESCE(SUM(total),0) FROM bookings WHERE status='Checked-out'"),
    ];
}
