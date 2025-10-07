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
        
        if($action === 'create') {
            createCarpool($db, $input);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Invalid action"]);
        }
        break;
        
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if($action === 'search') {
            searchCarpools($db);
        } elseif($action === 'user_carpools') {
            $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';
            if($user_id) {
                getUserCarpools($db, $user_id);
            } else {
                http_response_code(400);
                echo json_encode(["message" => "User ID required"]);
            }
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

function createCarpool($db, $data) {
    if(!isset($data['driver_id']) || !isset($data['from_location']) || 
       !isset($data['to_location']) || !isset($data['departure_date']) || 
       !isset($data['departure_time']) || !isset($data['available_seats'])) {
        http_response_code(400);
        echo json_encode(["message" => "Missing required fields"]);
        return;
    }
    
    $driver_id = $data['driver_id'];
    $from_location = $data['from_location'];
    $to_location = $data['to_location'];
    $departure_date = $data['departure_date'];
    $departure_time = $data['departure_time'];
    $available_seats = $data['available_seats'];
    
    $query = "INSERT INTO carpools (driver_id, from_location, to_location, departure_date, departure_time, available_seats) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    try {
        $stmt->execute([$driver_id, $from_location, $to_location, $departure_date, $departure_time, $available_seats]);
        http_response_code(201);
        echo json_encode([
            "message" => "Carpool created successfully",
            "carpool_id" => $db->lastInsertId()
        ]);
    } catch(PDOException $e) {
        http_response_code(400);
        echo json_encode(["message" => "Failed to create carpool: " . $e->getMessage()]);
    }
}

function searchCarpools($db) {
    $from = isset($_GET['from']) ? $_GET['from'] : '';
    $to = isset($_GET['to']) ? $_GET['to'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    
    $query = "SELECT c.*, u.username as driver_name 
              FROM carpools c 
              JOIN users u ON c.driver_id = u.id 
              WHERE c.status = 'active' AND c.available_seats > 0";
    
    $params = [];
    
    if($from) {
        $query .= " AND c.from_location LIKE ?";
        $params[] = "%$from%";
    }
    
    if($to) {
        $query .= " AND c.to_location LIKE ?";
        $params[] = "%$to%";
    }
    
    if($date) {
        $query .= " AND c.departure_date = ?";
        $params[] = $date;
    }
    
    $query .= " ORDER BY c.departure_date ASC, c.departure_time ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $carpools = $stmt->fetchAll();
    
    echo json_encode(["carpools" => $carpools]);
}

function getUserCarpools($db, $user_id) {
    $query = "SELECT c.*, u.username as driver_name 
              FROM carpools c 
              JOIN users u ON c.driver_id = u.id 
              WHERE c.driver_id = ? 
              ORDER BY c.departure_date DESC, c.departure_time DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $carpools = $stmt->fetchAll();
    
    echo json_encode(["carpools" => $carpools]);
}
?>
