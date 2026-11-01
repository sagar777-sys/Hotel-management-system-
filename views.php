<?php
// views.php - screens
require_once __DIR__ . '/actions.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v) { return '₹' . number_format((float)$v, 2); }

function img_url($key, $w = 900) {
    $ids = array(
        'hero'         => 'photo-1566073771259-6a8506099945',
        'lobby'        => 'photo-1551882547-ff40c63fe5fa',
        'pool'         => 'photo-1520250497591-112f2f40a3f4',
        'dining'       => 'photo-1414235077428-338989a2e8c0',
        'spa'          => 'photo-1540555700478-4be289fbecef',
        'gym'          => 'photo-1534438327276-14e5300c3a48',
        'night'        => 'photo-1542314831-068cd1dbfeeb',
        'Standard'     => 'photo-1631049307264-da0ec9d70304',
        'Deluxe'       => 'photo-1590490360182-c33d57733427',
        'Suite'        => 'photo-1582719478250-c89cae4dc85b',
        'Presidential' => 'photo-1578683010236-d716f9a3f461',
    );
    $id = isset($ids[$key]) ? $ids[$key] : $ids['Standard'];
    return 'https://images.unsplash.com/' . $id . '?auto=format&fit=crop&w=' . (int)$w . '&q=75';
}

function photo($key, $height = 180, $w = 800) {
    $u = h(img_url($key, $w));
    return '<div class="hms-photo" style="height:' . (int)$height . 'px;background-image:url(\'' . $u . '\'),linear-gradient(135deg,#0b1f33,#2a4d6e);"></div>';
}

function badge($status) {
    $cls = strtolower(str_replace(' ', '-', $status));
    return '<span class="hms-badge ' . h($cls) . '">' . h($status) . '</span>';
}

function table($headers, $rows) {
    if (!$rows) return '<div class="hms-empty">Nothing to show yet.</div>';
    $t = '<div class="hms-table-wrap"><table class="hms-table"><thead><tr>';
    foreach ($headers as $hd) $t .= '<th>' . h($hd) . '</th>';
    $t .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $t .= '<tr>';
        foreach ($r as $cell) $t .= '<td>' . $cell . '</td>';   // cells are escaped by the caller
        $t .= '</tr>';
    }
    return $t . '</tbody></table></div>';
}

function bars($rows, $is_money) {
    if (!$rows) return '<div class="hms-empty">No data yet.</div>';
    $max = 0;
    foreach ($rows as $r) { if ((float)$r['value'] > $max) $max = (float)$r['value']; }
    if ($max <= 0) $max = 1;
    $html = '<div class="hms-bars">';
    foreach ($rows as $r) {
        $w = (int)round(((float)$r['value'] / $max) * 100);
        $val = $is_money ? money($r['value']) : (string)(int)$r['value'];
        $html .= '<div class="hms-brow"><div class="hms-blabel">' . h($r['label']) . '</div><div class="hms-btrack"><span style="width:' . $w . '%"></span></div><div class="hms-bval">' . h($val) . '</div></div>';
    }
    return $html . '</div>';
}

function page($body) {
    $css = @file_get_contents(__DIR__ . '/style.css');
    if ($css === false) $css = '';
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
         . $css . '</style></head><body style="background:#fff;padding:16px">' . $body . '</body></html>';
}

function amenities($type) {
    $am = array(
        'Standard'     => array('Free WiFi', 'Air conditioning', 'TV', 'Breakfast'),
        'Deluxe'       => array('Free WiFi', 'Air conditioning', 'Smart TV', 'Balcony', 'Breakfast'),
        'Suite'        => array('Free WiFi', 'Living area', 'Bathtub', 'Minibar', 'Breakfast'),
        'Presidential' => array('Private lounge', 'Jacuzzi', 'Butler service', 'City view', 'Minibar'),
    );
    return isset($am[$type]) ? $am[$type] : $am['Standard'];
}

function room_card($r, $nights = 0) {
    $chips = '';
    foreach (amenities($r['type']) as $a) $chips .= '<span class="hms-chip">' . h($a) . '</span>';
    $total = $nights > 0 ? '<div class="hms-total">' . money($r['price'] * $nights) . ' for ' . (int)$nights . ' night(s)</div>' : '';
    return '<div class="hms-room">' . photo($r['type'], 170, 700)
         . '<div class="hms-body"><div class="hms-row"><div class="hms-rno">Room ' . h($r['room_no']) . '</div>' . badge($r['status']) . '</div>'
         . '<div class="hms-type">' . h($r['type']) . '</div><div class="hms-chips">' . $chips . '</div>'
         . '<div class="hms-price">' . money($r['price']) . '<small> / night</small></div>' . $total . '</div></div>';
}

function view_hero($d) {
    $title = h(arg($d, 'title', 'Grand Stay Hotel'));
    $sub   = h(arg($d, 'subtitle', ''));
    $key   = arg($d, 'image', 'hero');
    $ht    = (int)arg($d, 'height', 260);
    $u     = h(img_url($key, 1400));
    return '<div class="hms-hero" style="min-height:' . $ht . 'px;background-image:linear-gradient(90deg,rgba(11,31,51,.88),rgba(11,31,51,.25)),url(\'' . $u . '\'),linear-gradient(135deg,#0b1f33,#2a4d6e);">'
         . '<div class="hms-hero-in"><div class="hms-kicker">★★★★★ &nbsp;GRAND STAY HOTEL</div><div class="hms-title">' . $title . '</div><div class="hms-sub">' . $sub . '</div></div></div>';
}

/* ---------- public home ---------- */
function view_home_rooms() {
    $desc = array(
        'Standard'     => 'Cosy, bright rooms with everything you need for a restful stay.',
        'Deluxe'       => 'Spacious rooms with a private balcony and premium bedding.',
        'Suite'        => 'A separate living area, a bathtub and a curated minibar.',
        'Presidential' => 'Our finest address: private lounge, jacuzzi and butler service.',
    );
    $rows = db()->query("SELECT type, MIN(price) AS price, COUNT(*) AS n FROM rooms GROUP BY type ORDER BY MIN(price)")->fetchAll();
    if (!$rows) return '<div class="hms-empty">Rooms will be listed soon.</div>';
    $html = '<div class="hms-grid">';
    foreach ($rows as $r) {
        $text = isset($desc[$r['type']]) ? $desc[$r['type']] : 'Comfortable rooms for every traveller.';
        $html .= '<div class="hms-room">' . photo($r['type'], 190, 700)
               . '<div class="hms-body"><div class="hms-rno">' . h($r['type']) . '</div>'
               . '<div class="hms-text">' . h($text) . '</div>'
               . '<div class="hms-row"><div class="hms-price"><small>from </small>' . money($r['price']) . '<small> / night</small></div>'
               . '<span class="hms-chip">' . (int)$r['n'] . ' room(s)</span></div></div></div>';
    }
    return $html . '</div>';
}

function view_facilities() {
    $items = array(
        array('🏊', 'Infinity Pool', 'Swim above the skyline, with poolside service all day.'),
        array('💆', 'Spa &amp; Wellness', 'Massages, steam and therapies by trained professionals.'),
        array('🍽️', 'Fine Dining', 'Multi-cuisine restaurant and a rooftop lounge.'),
        array('🏋️', 'Fitness Centre', 'Modern equipment, open early and late.'),
        array('📶', 'High-speed WiFi', 'Complimentary and fast across the whole property.'),
        array('🚖', 'Airport Transfers', 'Pick-up and drop on request, any hour.'),
        array('🛎️', '24x7 Concierge', 'Tours, tickets and tables, arranged for you.'),
        array('🅿️', 'Valet Parking', 'Secure parking with round-the-clock valet.'),
    );
    $html = '<div class="hms-fac">';
    foreach ($items as $i) {
        $html .= '<div class="hms-facitem"><div class="hms-facic">' . $i[0] . '</div><div class="hms-factitle">' . $i[1] . '</div><div class="hms-factext">' . $i[2] . '</div></div>';
    }
    return $html . '</div>';
}

function view_gallery() {
    $tiles = array(array('lobby', 'Grand Lobby'), array('pool', 'Infinity Pool'), array('dining', 'Fine Dining'), array('spa', 'Spa &amp; Wellness'));
    $html = '<div class="hms-gallery">';
    foreach ($tiles as $t) {
        $html .= '<div class="hms-tile">' . photo($t[0], 200, 700) . '<div class="hms-cap">' . $t[1] . '</div></div>';
    }
    return $html . '</div>';
}

function view_reviews() {
    $items = array(
        array('Neha &amp; Arjun', 'Honeymoon stay', 'The suite was beautiful and the staff remembered our names by the second day. Truly a memorable trip.'),
        array('Capt. R. Menon', 'Business traveller', 'Quick check-in, quiet rooms and a proper desk to work at. The best city stay I have had in years.'),
        array('The Kapoor Family', 'Weekend getaway', 'The kids loved the pool and we loved the breakfast. Everything felt effortless.'),
    );
    $html = '<div class="hms-reviews">';
    foreach ($items as $i) {
        $html .= '<div class="hms-review"><div class="hms-stars">★★★★★</div><div class="hms-quote">' . $i[2] . '</div><div class="hms-by"><b>' . $i[0] . '</b><span>' . $i[1] . '</span></div></div>';
    }
    return $html . '</div>';
}

function view_footer() {
    return '<div class="hms-footer"><div><div class="hms-fbrand">GRAND STAY</div><div class="hms-fsmall">HOTEL &amp; RESORT</div>'
         . '<div class="hms-ftext">Warm hospitality, thoughtful service and rooms you will not want to leave.</div></div>'
         . '<div><div class="hms-fhead">Contact</div><div class="hms-ftext">21 Seaview Road, Mumbai, Maharashtra<br>+91 22 5555 0100<br>reservations@grandstay.example</div></div>'
         . '<div><div class="hms-fhead">Timings</div><div class="hms-ftext">Check-in from 2:00 PM<br>Check-out by 11:00 AM<br>Front desk open 24 hours</div></div></div>'
         . '<div class="hms-copy">© ' . date('Y') . ' Grand Stay Hotel &amp; Resort. All rights reserved.</div>';
}

function view_available($d) {
    $rooms = arg($d, 'rooms', array());
    $n = (int)arg($d, 'nights', 1);
    if (!$rooms) return '<div class="hms-empty">No rooms are available for the selected dates. Please try different dates.</div>';
    $html = '<div class="hms-found">' . count($rooms) . ' room(s) available for ' . $n . ' night(s)</div><div class="hms-grid">';
    foreach ($rooms as $r) $html .= room_card($r, $n);
    return $html . '</div>';
}

/* ---------- staff screens ---------- */
function view_dashboard() {
    $s = stats();
    $occ = $s['rooms'] > 0 ? (int)round($s['occupied'] * 100 / $s['rooms']) : 0;
    $cards = array(
        array('🛏️', 'Total Rooms', $s['rooms'], ''),
        array('✅', 'Available', $s['available'], 'green'),
        array('🔑', 'Occupied', $s['occupied'], 'red'),
        array('🧹', 'Housekeeping', $s['service'], 'gold'),
        array('👥', 'Registered Guests', $s['guests'], ''),
        array('📅', 'Active Reservations', $s['active'], 'blue'),
        array('🛬', 'Arrivals Today', $s['arrivals'], 'blue'),
        array('🛫', 'Departures Today', $s['departures'], 'red'),
        array('💰', 'Revenue', money($s['revenue']), 'gold'),
    );
    $html = '<div class="hms-cards">';
    foreach ($cards as $c) {
        $html .= '<div class="hms-stat ' . $c[3] . '"><div class="hms-ic">' . $c[0] . '</div><div><div class="hms-lbl">' . h($c[1]) . '</div><div class="hms-val">' . h($c[2]) . '</div></div></div>';
    }
    $html .= '</div>';
    $html .= '<div class="hms-panel"><div class="hms-row"><b>Hotel occupancy</b><span>' . $occ . '%</span></div><div class="hms-bar"><span style="width:' . $occ . '%"></span></div></div>';

    $rep = report_data();
    $html .= '<div class="hms-two"><div class="hms-panel"><div class="hms-ptitle">Room revenue by category</div>' . bars($rep['revenue_by_type'], true) . '</div>'
           . '<div class="hms-panel"><div class="hms-ptitle">Reservations by status</div>' . bars($rep['status_counts'], false) . '</div></div>';

    $all = list_bookings();
    $today = date('Y-m-d');
    $arr = '';
    $dep = '';
    foreach ($all['bookings'] as $b) {
        if ($b['status'] === 'Booked' && $b['check_in'] === $today) {
            $arr .= '<div class="hms-li"><b>' . h($b['guest']) . '</b><span>Room ' . h($b['room_no']) . ' · ' . h($b['room_type']) . '</span></div>';
        }
        if ($b['status'] === 'Checked-in' && $b['check_out'] === $today) {
            $dep .= '<div class="hms-li"><b>' . h($b['guest']) . '</b><span>Room ' . h($b['room_no']) . ' · ' . h($b['room_type']) . '</span></div>';
        }
    }
    if ($arr === '') $arr = '<div class="hms-muted">No arrivals scheduled for today.</div>';
    if ($dep === '') $dep = '<div class="hms-muted">No departures scheduled for today.</div>';
    $html .= '<div class="hms-two"><div class="hms-panel"><div class="hms-ptitle">🛬 Arriving today</div>' . $arr . '</div>'
           . '<div class="hms-panel"><div class="hms-ptitle">🛫 Departing today</div>' . $dep . '</div></div>';

    $rows = array();
    foreach (array_slice($all['bookings'], 0, 6) as $b) {
        $rows[] = array('#' . $b['id'], h($b['guest']), h($b['room_no']), h($b['check_in']), h($b['check_out']), money($b['total']), badge($b['status']));
    }
    $html .= '<div class="hms-title2">Recent Reservations</div>' . table(array('ID', 'Guest', 'Room', 'Check-in', 'Check-out', 'Room Charges', 'Status'), $rows);
    return $html;
}

function view_rooms($d) {
    $list = list_rooms($d);
    if (!$list['rooms']) return '<div class="hms-empty">No rooms match the selected filters.</div>';
    $html = '<div class="hms-grid">';
    foreach ($list['rooms'] as $r) $html .= room_card($r, 0);
    return $html . '</div>';
}

function view_guests($d) {
    $rows = array();
    $list = list_guests($d);
    foreach ($list['guests'] as $g) {
        $ini = '?';
        if (preg_match('/^./u', $g['name'], $m)) $ini = strtoupper($m[0]);
        $rows[] = array('<span class="hms-av">' . h($ini) . '</span> ' . h($g['name']), h($g['phone']), h($g['email']), h($g['id_proof']));
    }
    return table(array('Guest', 'Phone', 'Email', 'ID Proof'), $rows);
}

function view_bookings($d) {
    $rows = array();
    $list = list_bookings($d);
    foreach ($list['bookings'] as $b) {
        $rows[] = array('#' . $b['id'], h($b['guest']), h($b['room_no']), h($b['check_in']), h($b['check_out']),
                        (int)$b['nights'], money($b['total']), badge($b['status']), badge($b['payment']));
    }
    return table(array('ID', 'Guest', 'Room', 'Check-in', 'Check-out', 'Nights', 'Room Charges', 'Status', 'Payment'), $rows);
}

function view_reports() {
    $rep = report_data();
    $s = stats();
    $html = '<div class="hms-two"><div class="hms-panel"><div class="hms-ptitle">Room revenue by category</div>' . bars($rep['revenue_by_type'], true) . '</div>'
          . '<div class="hms-panel"><div class="hms-ptitle">Service revenue</div>' . bars($rep['services'], true) . '</div></div>'
          . '<div class="hms-two"><div class="hms-panel"><div class="hms-ptitle">Reservations by status</div>' . bars($rep['status_counts'], false) . '</div>'
          . '<div class="hms-panel"><div class="hms-ptitle">Collected revenue (completed stays)</div><div class="hms-bigval">' . money($s['revenue']) . '</div>'
          . '<div class="hms-muted">Room charges plus services, before taxes.</div></div></div>';
    return $html;
}

function view_invoice($d) {
    $id = (int)arg($d, 'booking_id', 0);
    $s = db()->prepare("SELECT b.*, g.name, g.phone, g.email, r.room_no, r.type, r.price
        FROM bookings b JOIN guests g ON g.id=b.guest_id JOIN rooms r ON r.id=b.room_id WHERE b.id=?");
    $s->execute(array($id));
    $b = $s->fetch();
    if (!$b) return '<div class="hms-empty">Reservation not found.</div>';

    $charges = get_charges($id);
    $svc = 0;
    $svc_rows = '';
    foreach ($charges as $c) {
        $svc += (float)$c['amount'];
        $svc_rows .= '<tr><td>' . h($c['description']) . '</td><td>-</td><td>-</td><td>' . money($c['amount']) . '</td></tr>';
    }
    $gst_room = $b['total'] * 0.12;
    $gst_svc = $svc * 0.18;
    $grand = $b['total'] + $svc + $gst_room + $gst_svc;
    $paid = $b['payment'] === 'Paid' ? ' via ' . $b['pay_method'] : '';

    return '<div class="hms-invoice">'
        . '<div class="hms-inv-head"><div><div class="hms-inv-brand">Grand Stay Hotel</div><div class="hms-muted">Tax Invoice</div></div>'
        . '<div class="hms-right"><b>INV-' . str_pad($b['id'], 5, '0', STR_PAD_LEFT) . '</b><br>' . badge($b['payment']) . h($paid) . '</div></div>'
        . '<div class="hms-inv-grid"><div><div class="hms-muted">BILLED TO</div><b>' . h($b['name']) . '</b><br>' . h($b['phone']) . '<br>' . h($b['email']) . '</div>'
        . '<div><div class="hms-muted">STAY DETAILS</div>Room ' . h($b['room_no']) . ' (' . h($b['type']) . ')<br>' . h($b['check_in']) . ' to ' . h($b['check_out']) . '<br>' . badge($b['status']) . '</div></div>'
        . '<div class="hms-table-wrap"><table class="hms-table"><thead><tr><th>Description</th><th>Nights</th><th>Rate</th><th>Amount</th></tr></thead><tbody>'
        . '<tr><td>Room charges</td><td>' . (int)$b['nights'] . '</td><td>' . money($b['price']) . '</td><td>' . money($b['total']) . '</td></tr>'
        . $svc_rows
        . '<tr><td colspan="3" class="hms-right">GST on room (12%)</td><td>' . money($gst_room) . '</td></tr>'
        . '<tr><td colspan="3" class="hms-right">GST on services (18%)</td><td>' . money($gst_svc) . '</td></tr>'
        . '<tr class="hms-grand"><td colspan="3" class="hms-right">Grand Total</td><td>' . money($grand) . '</td></tr>'
        . '</tbody></table></div>'
        . '<div class="hms-muted hms-center">Thank you for choosing Grand Stay Hotel. We look forward to welcoming you again.</div></div>';
}

function invoice_page($d) { return page(view_invoice($d)); }
