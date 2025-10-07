<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch($method) {
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if($action === 'register') {
            register($db, $input);
        } elseif($action === 'login') {
            login($db, $input);
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

function register($db, $data) {
    if(!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(["message" => "Missing required fields"]);
        return;
    }
    
    $username = $data['username'];
    $email = $data['email'];
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $full_name = isset($data['full_name']) ? $data['full_name'] : '';
    
    $query = "INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    try {
        $stmt->execute([$username, $email, $password, $full_name]);
        http_response_code(201);
        echo json_encode(["message" => "User registered successfully", "user_id" => $db->lastInsertId()]);
    } catch(PDOException $e) {
        http_response_code(400);
        echo json_encode(["message" => "Registration failed: " . $e->getMessage()]);
    }
}

function login($db, $data) {
    if(!isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(["message" => "Username and password required"]);
        return;
    }
    
    $username = $data['username'];
    $password = $data['password'];
    
    $query = "SELECT id, username, email, password_hash, eco_score FROM users WHERE username = ? OR email = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$username, $username]);
    
    if($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        
        if(password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            http_response_code(200);
            echo json_encode(["message" => "Login successful", "user" => $user]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid credentials"]);
        }
    } else {
        http_response_code(401);
        echo json_encode(["message" => "User not found"]);
    }
}
?>
