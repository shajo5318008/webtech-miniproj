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
    // Preflight request — no body required
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
        $action = $_GET['action'] ?? '';
        if ($action === 'create') {
            createRide($input);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Invalid action"]);
        }
        break;

    case 'GET':
        $action = $_GET['action'] ?? '';
        if ($action === 'search') {
            searchRides();
        } elseif ($action === 'driver_rides') {
            $driver_id = $_GET['driver_id'] ?? '';
            getDriverRides($driver_id);
        } elseif ($action === 'get') {
            // fetch single ride by id: ?action=get&id=<ride_id>
            $ride_id = $_GET['id'] ?? '';
            getRide($ride_id);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Invalid action"]);
        }
        break;

    case 'PATCH':
        // Optional: update ride (available_seats, status, fare, etc.)
        updateRide();
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}

// ===============================
// CREATE RIDE
// ===============================
function createRide($data) {
    if (!is_array($data) ||
        !isset($data['driver_id'], $data['start_location'], $data['end_location'], $data['departure_time'], $data['available_seats'])) {
        http_response_code(400);
        echo json_encode(["message" => "Missing required fields: driver_id, start_location, end_location, departure_time, available_seats"]);
        return;
    }

    $ride = [
        "driver_id" => $data['driver_id'],
        "vehicle_id" => $data['vehicle_id'] ?? null,
        "start_location" => $data['start_location'],
        "end_location" => $data['end_location'],
        "departure_time" => $data['departure_time'], // should be an ISO timestamp
        "available_seats" => (int)$data['available_seats'],
        "fare" => isset($data['fare']) ? $data['fare'] : null,
        "created_at" => date('Y-m-d H:i:s')
    ];

    // Wrap in array so Supabase returns the created row when using return=representation
    $response = supabaseRequest('POST', 'rides', [$ride]);

    if (is_array($response) && isset($response[0])) {
        http_response_code(201);
        echo json_encode(["message" => "Ride created successfully", "ride" => $response[0]]);
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Failed to create ride", "response" => $response]);
    }
}

// ===============================
// SEARCH RIDES
// Query params: from, to, date (YYYY-MM-DD), min_seats
// Example: /rides.php?action=search&from=Pune&to=Mumbai&date=2025-11-11
// ===============================
function searchRides() {
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $date = $_GET['date'] ?? '';
    $min_seats = isset($_GET['min_seats']) ? (int)$_GET['min_seats'] : 1;

    $filters = [];

    // Only active rides with >= min_seats (if you have a status column, you can add status=eq.active)
    $filters[] = "available_seats=gte.$min_seats";

    if ($from) {
        // case-insensitive partial match
        $filters[] = "start_location=ilike.*" . rawurlencode($from) . "*";
    }
    if ($to) {
        $filters[] = "end_location=ilike.*" . rawurlencode($to) . "*";
    }
    if ($date) {
        // if departure_time is a timestamp, match date portion
        // Using Postgres date casting via eq.<date> on departure_time::date isn't directly supported via REST,
        // so we match departure_time range for that date (00:00 - 23:59)
        $start = rawurlencode($date . " 00:00:00");
        $end = rawurlencode($date . " 23:59:59");
        $filters[] = "departure_time=gte.$start";
        $filters[] = "departure_time=lte.$end";
    }

    // Build query string: select fields + join driver basic info from users table
    $select = "select=id,start_location,end_location,departure_time,available_seats,fare,driver:users(username,full_name,phone)";

    $query = $select;
    if (!empty($filters)) {
        $query .= "&" . implode("&", $filters);
    }

    // Order by earliest departure_time
    $query .= "&order=departure_time.asc";

    $response = supabaseRequest('GET', 'rides?' . $query);

    if (is_array($response)) {
        echo json_encode(["rides" => $response]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Failed to fetch rides", "response" => $response]);
    }
}

// ===============================
// GET RIDES BY DRIVER
// ===============================
function getDriverRides($driver_id) {
    if (!$driver_id) {
        http_response_code(400);
        echo json_encode(["message" => "Driver ID required"]);
        return;
    }

    $query = "?select=*,drivers:users(username,full_name)&driver_id=eq." . rawurlencode($driver_id) . "&order=departure_time.desc";
    $response = supabaseRequest('GET', 'rides' . $query);

    if (is_array($response)) {
        echo json_encode(["rides" => $response]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Failed to fetch driver rides", "response" => $response]);
    }
}

// ===============================
// GET SINGLE RIDE
// ===============================
function getRide($ride_id) {
    if (!$ride_id) {
        http_response_code(400);
        echo json_encode(["message" => "Ride ID required"]);
        return;
    }

    $query = "?select=*,driver:users(username,full_name,phone),vehicle:vehicles(vehicle_number,model,capacity)&id=eq." . rawurlencode($ride_id);
    $response = supabaseRequest('GET', 'rides' . $query);

    if (is_array($response) && count($response) > 0) {
        echo json_encode(["ride" => $response[0]]);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Ride not found"]);
    }
}

// ===============================
// UPDATE RIDE (PATCH)
// Example: PATCH /rides.php?id=<ride_id> with body { "available_seats": 2 }
// ===============================
function updateRide() {
    parse_str($_SERVER['QUERY_STRING'], $qs);
    $ride_id = $qs['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$ride_id || !is_array($input) || empty($input)) {
        http_response_code(400);
        echo json_encode(["message" => "Ride ID and update data required (send PATCH with ?id=<ride_id>)"]);
        return;
    }

    // Permit only certain fields to be updated
    $allowed = ['available_seats', 'fare', 'start_location', 'end_location', 'departure_time', 'vehicle_id'];
    $patch = [];
    foreach ($allowed as $f) {
        if (isset($input[$f])) $patch[$f] = $input[$f];
    }

    if (empty($patch)) {
        http_response_code(400);
        echo json_encode(["message" => "No allowed fields to update"]);
        return;
    }

    $response = supabaseRequest('PATCH', "rides?id=eq." . rawurlencode($ride_id), $patch);

    if ($response) {
        echo json_encode(["message" => "Ride updated", "ride" => $response]);
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Failed to update ride", "response" => $response]);
    }
}
?>
