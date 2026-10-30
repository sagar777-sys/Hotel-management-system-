<?php
// cli.php - entry point called from Streamlit (app.py):  php cli.php <action>   (JSON on stdin)
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/views.php';

$action = $argv[1] ?? '';
$d = json_decode(stream_get_contents(STDIN), true) ?: [];

$json_actions = [
    'add_room' => 'add_room', 'delete_room' => 'delete_room', 'add_guest' => 'add_guest',
    'book' => 'book', 'check_in' => 'check_in', 'check_out' => 'check_out', 'cancel' => 'cancel_booking',
    'list_rooms' => 'list_rooms', 'list_guests' => 'list_guests', 'list_bookings' => 'list_bookings', 'stats' => 'stats',
];
$html_actions = [
    'view_dashboard' => 'view_dashboard', 'view_rooms' => 'view_rooms', 'view_guests' => 'view_guests',
    'view_bookings' => 'view_bookings', 'view_invoice' => 'view_invoice',
];

try {
    if (isset($json_actions[$action])) {
        echo json_encode(call_user_func($json_actions[$action], $d));
    } elseif (isset($html_actions[$action])) {
        echo call_user_func($html_actions[$action], $d);
    } else {
        echo json_encode(result(false, 'Unknown action.'));
    }
} catch (Throwable $e) {
    echo json_encode(result(false, 'Error: ' . $e->getMessage()));
}
