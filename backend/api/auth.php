 <?php
// ======================
// CORS + preflight (must be first, before output)
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
require_once '../config/supabase.php'; // must provide supabaseRequest($method, $table, $data = null)

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Basic router
switch ($method) {
    case 'POST':
        $action = $_GET['action'] ?? '';
        if ($action === 'register') {
            register($input);
        } elseif ($action === 'login') {
            login($input);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Invalid action"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}

// =====================================
// REGISTER
// =====================================
function register($data) {
    if (!is_array($data) || !isset($data['username'], $data['email'], $data['password'])) {
        http_response_code(400);
        echo json_encode(["message" => "Missing required fields"]);
        return;
    }

    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = $data['password'];
    $full_name = $data['full_name'] ?? '';
    $phone = $data['phone'] ?? null;
    $role = in_array($data['role'] ?? 'passenger', ['driver','passenger']) ? $data['role'] : 'passenger';

    // Hash password in PHP
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $payload = [
        "username" => $username,
        "email" => $email,
        "password_hash" => $hashedPassword,
        "full_name" => $full_name,
        "phone" => $phone,
        "eco_score" => $data['eco_score'] ?? 0,
        "role" => $role,
        "created_at" => date('Y-m-d H:i:s')
    ];

    // Use Supabase helper (POST expects an array of objects)
    $response = supabaseRequest('POST', 'users', [$payload]);

    // supabaseRequest returns decoded JSON — on success, Supabase returns an array with the created row(s)
    if (is_array($response) && isset($response[0])) {
        http_response_code(201);
        echo json_encode(["message" => "User registered successfully", "user" => $response[0]]);
    } else {
        // If Supabase returns an error object, it might be decoded as associative array
        http_response_code(400);
        echo json_encode(["message" => "Registration failed", "response" => $response]);
    }
}

// =====================================
// LOGIN
// =====================================
function login($data) {
    if (!is_array($data) || !isset($data['username'], $data['password'])) {
        http_response_code(400);
        echo json_encode(["message" => "Username and password required"]);
        return;
    }

    $raw = $data['username'];
    $password = $data['password'];

    // Build filter: or=(username.eq.value,email.eq.value)
    // Values must be URL-encoded. We will use rawurlencode but also keep simple characters as is.
    $encoded = rawurlencode($raw);
    $filter = "or=(username.eq.$encoded,email.eq.$encoded)";

    // Use helper to GET with query string
    $response = supabaseRequest('GET', "users?$filter");

    if (!is_array($response) || count($response) === 0) {
        http_response_code(401);
        echo json_encode(["message" => "User not found"]);
        return;
    }

    // take first matching user
    $user = $response[0];

    if (!isset($user['password_hash'])) {
        http_response_code(500);
        echo json_encode(["message" => "User record malformed (no password_hash)"]);
        return;
    }

    if (password_verify($password, $user['password_hash'])) {
        // remove sensitive fields
        unset($user['password_hash']);
        http_response_code(200);
        echo json_encode(["message" => "Login successful", "user" => $user]);
    } else {
        http_response_code(401);
        echo json_encode(["message" => "Invalid credentials"]);
    }
}
?>
