<?php
// views.php - HTML rendering (styled by style.css, inlined so it works inside Streamlit)
require_once __DIR__ . '/actions.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v) { return 'Rs ' . number_format((float)$v, 2); }

function page($body) {
    $css = @file_get_contents(__DIR__ . '/style.css') ?: '';
    return "<!DOCTYPE html><html><head><meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>$css</style></head><body>$body</body></html>";
}

function badge($status) {
    $cls = strtolower(str_replace(' ', '-', $status));
    return "<span class='badge $cls'>" . h($status) . "</span>";
}

function table($headers, $rows) {
    if (!$rows) return "<div class='empty'>No records yet.</div>";
    $t = "<div class='table-wrap'><table><thead><tr>";
    foreach ($headers as $hd) $t .= "<th>" . h($hd) . "</th>";
    $t .= "</tr></thead><tbody>";
    foreach ($rows as $r) {
        $t .= "<tr>";
        foreach ($r as $cell) $t .= "<td>$cell</td>";   // cells are pre-escaped
        $t .= "</tr>";
    }
    return $t . "</tbody></table></div>";
}

function view_dashboard() {
    $s = stats();
    $cards = [
        ['Total Rooms', $s['rooms'], ''], ['Available', $s['available'], 'green'],
        ['Occupied', $s['occupied'], 'red'], ['Guests', $s['guests'], ''],
        ['Active Bookings', $s['active'], 'blue'], ['Revenue', money($s['revenue']), 'gold'],
    ];
    $html = "<h2>Hotel Dashboard</h2><div class='cards'>";
    foreach ($cards as $c) $html .= "<div class='card {$c[2]}'><div class='lbl'>" . h($c[0]) . "</div><div class='val'>" . h($c[1]) . "</div></div>";
    $html .= "</div><h3>Recent Bookings</h3>";
    $rows = [];
    foreach (array_slice(list_bookings()['bookings'], 0, 8) as $b) {
        $rows[] = ["#" . $b['id'], h($b['guest']), h($b['room_no']), h($b['check_in']), h($b['check_out']), money($b['total']), badge($b['status'])];
    }
    $html .= table(['ID', 'Guest', 'Room', 'Check-in', 'Check-out', 'Subtotal', 'Status'], $rows);
    return page($html);
}

function view_rooms() {
    $rows = [];
    foreach (list_rooms()['rooms'] as $r) {
        $rows[] = [h($r['room_no']), h($r['type']), money($r['price']), badge($r['status'])];
    }
    return page("<h2>Rooms</h2>" . table(['Room No', 'Type', 'Price / Night', 'Status'], $rows));
}

function view_guests() {
    $rows = [];
    foreach (list_guests()['guests'] as $g) {
        $rows[] = [$g['id'], h($g['name']), h($g['phone']), h($g['email']), h($g['id_proof'])];
    }
    return page("<h2>Guests</h2>" . table(['ID', 'Name', 'Phone', 'Email', 'ID Proof'], $rows));
}

function view_bookings() {
    $rows = [];
    foreach (list_bookings()['bookings'] as $b) {
        $rows[] = ["#" . $b['id'], h($b['guest']), h($b['room_no']), h($b['check_in']), h($b['check_out']),
                   $b['nights'], money($b['total']), badge($b['status'])];
    }
    return page("<h2>Bookings</h2>" . table(['ID', 'Guest', 'Room', 'Check-in', 'Check-out', 'Nights', 'Subtotal', 'Status'], $rows));
}

function view_invoice($d) {
    $id = (int)($d['booking_id'] ?? 0);
    $s = db()->prepare("SELECT b.*, g.name, g.phone, g.email, r.room_no, r.type, r.price
        FROM bookings b JOIN guests g ON g.id=b.guest_id JOIN rooms r ON r.id=b.room_id WHERE b.id=?");
    $s->execute([$id]);
    $b = $s->fetch();
    if (!$b) return page("<div class='empty'>Booking not found.</div>");

    $gst = $b['total'] * 0.12;
    $grand = $b['total'] + $gst;
    $html = "<div class='invoice'>
      <div class='inv-head'><div><h2>Grand Stay Hotel</h2><div class='muted'>Tax Invoice</div></div>
      <div class='right'><b>Invoice #INV-" . str_pad($b['id'], 5, '0', STR_PAD_LEFT) . "</b><br>" . badge($b['status']) . "</div></div>
      <div class='inv-grid'>
        <div><div class='muted'>Billed To</div><b>" . h($b['name']) . "</b><br>" . h($b['phone']) . "<br>" . h($b['email']) . "</div>
        <div><div class='muted'>Stay</div>Room " . h($b['room_no']) . " (" . h($b['type']) . ")<br>" . h($b['check_in']) . " to " . h($b['check_out']) . "</div>
      </div>
      <table><thead><tr><th>Description</th><th>Nights</th><th>Rate</th><th>Amount</th></tr></thead><tbody>
        <tr><td>Room charges</td><td>{$b['nights']}</td><td>" . money($b['price']) . "</td><td>" . money($b['total']) . "</td></tr>
        <tr><td colspan='3' class='right'>GST (12%)</td><td>" . money($gst) . "</td></tr>
        <tr class='grand'><td colspan='3' class='right'>Grand Total</td><td>" . money($grand) . "</td></tr>
      </tbody></table>
      <div class='muted center'>Thank you for staying with us!</div></div>";
    return page($html);
}
