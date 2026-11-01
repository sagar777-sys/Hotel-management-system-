<?php
// cli.php - single entry point used by the app
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/views.php';

$action = isset($argv[1]) ? $argv[1] : '';
$d = json_decode(stream_get_contents(STDIN), true);
if (!is_array($d)) $d = array();

$data_actions = array(
    'login' => 'login', 'add_room' => 'add_room', 'delete_room' => 'delete_room', 'set_room_status' => 'set_room_status',
    'add_guest' => 'add_guest', 'book' => 'book', 'online_booking' => 'online_booking',
    'check_in' => 'check_in', 'check_out' => 'check_out', 'cancel' => 'cancel_booking',
    'add_charge' => 'add_charge', 'pay' => 'pay_booking', 'available_rooms' => 'available_rooms',
    'list_rooms' => 'list_rooms', 'list_guests' => 'list_guests', 'list_bookings' => 'list_bookings',
    'stats' => 'stats', 'report_data' => 'report_data',
);
$view_actions = array(
    'view_hero' => 'view_hero', 'view_home_rooms' => 'view_home_rooms', 'view_facilities' => 'view_facilities',
    'view_gallery' => 'view_gallery', 'view_reviews' => 'view_reviews', 'view_footer' => 'view_footer',
    'view_available' => 'view_available', 'view_dashboard' => 'view_dashboard', 'view_rooms' => 'view_rooms',
    'view_guests' => 'view_guests', 'view_bookings' => 'view_bookings', 'view_reports' => 'view_reports',
    'view_invoice' => 'view_invoice', 'invoice_page' => 'invoice_page',
);

try {
    if (isset($data_actions[$action])) {
        echo json_encode(call_user_func($data_actions[$action], $d));
    } elseif (isset($view_actions[$action])) {
        echo call_user_func($view_actions[$action], $d);
    } else {
        echo json_encode(result(false, 'Unknown action.'));
    }
} catch (Throwable $e) {
    echo json_encode(result(false, 'Something went wrong. Please try again.'));
}
