<?php
// ======================
// CORS + preflight (must be first, before any output)
// ======================
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Vary: Origin");
header("Access-Control-Allow-Credentials: false"); // set true only when you use cookies/sessions across origins
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight request — return no body
    http_response_code(204);
    exit;
}

// ======================
// Main file
// ======================
require_once '../config/supabase.php'; // must define supabaseRequest($method, $table, $data = null)

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Router
switch ($method) {
    case 'POST':
        createBooking($input);
        break;

    case 'GET':
        getBookings();
        break;

    case 'PATCH':
        // support updating booking status or seats etc.
        updateBooking();
        break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteBooking($_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Missing booking ID"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}

// ===============================
// CREATE A NEW BOOKING
// ===============================
function createBooking($data) {
    // Accept either passenger_id or user_id (backwards compatibility)
    $passenger_id = $data['passenger_id'] ?? $data['user_id'] ?? null;

    if (!$passenger_id || !isset($data['ride_id']) || !isset($data['seats_booked'])) {
        http_response_code(400);
        echo json_encode(["message" => "Missing required fields: ride_id, passenger_id (or user_id) and seats_booked"]);
        return;
    }

    $payload = [
        "ride_id" => $data['ride_id'],
        "passenger_id" => $passenger_id,
        "seats_booked" => (int)$data['seats_booked'],
        "status" => $data['status'] ?? 'pending',
        "booked_at" => date('Y-m-d H:i:s')
    ];

    // Wrap in array so Supabase returns the created row (Prefer: return=representation is recommended)
    $response = supabaseRequest('POST', 'bookings', [$payload]);

    if (is_array($response) && isset($response[0])) {
        http_response_code(201);
        echo json_encode(["message" => "Booking created successfully", "booking" => $response[0]]);
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Failed to create booking", "response" => $response]);
    }
}

// ===============================
// FETCH BOOKINGS
// Supports:
//  - /bookings.php                => all bookings
//  - /bookings.php?passenger_id=..
//  - /bookings.php?ride_id=..
//  - /bookings.php?passenger_id=..&ride_id=..
// ===============================
function getBookings() {
    $params = [];

    // accept passenger_id or user_id
    if (isset($_GET['passenger_id'])) {
        $params[] = "passenger_id=eq." . rawurlencode($_GET['passenger_id']);
    } elseif (isset($_GET['user_id'])) {
        $params[] = "passenger_id=eq." . rawurlencode($_GET['user_id']);
    }

    if (isset($_GET['ride_id'])) {
        $params[] = "ride_id=eq." . rawurlencode($_GET['ride_id']);
    }

    // default select: include ride basic info and passenger basic info (adjust field names as needed)
    // Supabase relationship joins: select=*,ride:rides(start_location,end_location,departure_time),passenger:users(username,email)
    $select = "select=*,ride:rides(start_location,end_location,departure_time),passenger:users(username,email,full_name)";

    $query = $select;
    if (!empty($params)) {
        $query .= "&" . implode("&", $params);
    }

    $response = supabaseRequest('GET', 'bookings?' . $query);

    if (is_array($response)) {
        echo json_encode(["bookings" => $response]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Failed to fetch bookings", "response" => $response]);
    }
}

// ===============================
// UPDATE BOOKING (PATCH) - update status or seats_booked
// Example: PATCH /bookings.php?id=<booking_id> with JSON { "status":"confirmed" }
// ===============================
function updateBooking() {
    parse_str($_SERVER['QUERY_STRING'], $qs);
    $booking_id = $qs['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$booking_id || !is_array($input) || empty($input)) {
        http_response_code(400);
        echo json_encode(["message" => "Booking ID and update data required (send PATCH with ?id=<booking_id>)"]);
        return;
    }

    // Only allow certain fields to be updated
    $allowed = ['status', 'seats_booked'];
    $patch = [];
    foreach ($allowed as $f) {
        if (isset($input[$f])) $patch[$f] = $input[$f];
    }

    if (empty($patch)) {
        http_response_code(400);
        echo json_encode(["message" => "No allowed fields to update"]);
        return;
    }

    $response = supabaseRequest('PATCH', "bookings?id=eq." . rawurlencode($booking_id), $patch);

    if ($response) {
        echo json_encode(["message" => "Booking updated", "booking" => $response]);
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Failed to update booking", "response" => $response]);
    }
}

// ===============================
// DELETE A BOOKING
// ===============================
function deleteBooking($id) {
    $response = supabaseRequest('DELETE', 'bookings?id=eq.' . rawurlencode($id));

    // Supabase may return an empty array on success; ensure we check response shape.
    // If the response is an array of deleted rows, return success.
    if (is_array($response) && count($response) >= 0) {
        echo json_encode(["message" => "Booking deletion response", "response" => $response]);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Booking not found or delete failed", "response" => $response]);
    }
}
?>
