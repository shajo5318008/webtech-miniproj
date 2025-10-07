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
        logTrip($db, $input);
        break;
        
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';
        
        if($action === 'analytics' && $user_id) {
            getAnalytics($db, $user_id);
        } elseif($action === 'history' && $user_id) {
            getTripHistory($db, $user_id);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Invalid parameters"]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}

function logTrip($db, $data) {
    if(!isset($data['user_id']) || !isset($data['transport_type']) || !isset($data['distance'])) {
        http_response_code(400);
        echo json_encode(["message" => "Missing required fields"]);
        return;
    }
    
    $user_id = $data['user_id'];
    $transport_type = $data['transport_type'];
    $distance = $data['distance'];
    $trip_date = isset($data['trip_date']) ? $data['trip_date'] : date('Y-m-d');
    
    // Calculate CO2 saved based on transport type
    $co2_saved = calculateCO2Saved($transport_type, $distance);
    $eco_points = calculateEcoPoints($transport_type, $distance);
    
    $query = "INSERT INTO trips (user_id, transport_type, distance, co2_saved, trip_date) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    try {
        $stmt->execute([$user_id, $transport_type, $distance, $co2_saved, $trip_date]);
        
        // Update user's eco score
        updateEcoScore($db, $user_id, $eco_points);
        
        http_response_code(201);
        echo json_encode([
            "message" => "Trip logged successfully",
            "trip_id" => $db->lastInsertId(),
            "co2_saved" => $co2_saved,
            "eco_points" => $eco_points
        ]);
    } catch(PDOException $e) {
        http_response_code(400);
        echo json_encode(["message" => "Failed to log trip: " . $e->getMessage()]);
    }
}

function getAnalytics($db, $user_id) {
    // Get weekly analytics
    $query = "SELECT 
                transport_type,
                COUNT(*) as trip_count,
                SUM(distance) as total_distance,
                SUM(co2_saved) as total_co2_saved
              FROM trips 
              WHERE user_id = ? AND trip_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY transport_type";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $weekly_data = $stmt->fetchAll();
    
    // Get total stats
    $total_query = "SELECT 
                      COUNT(*) as total_trips,
                      SUM(distance) as total_distance,
                      SUM(co2_saved) as total_co2_saved,
                      eco_score
                    FROM trips t
                    JOIN users u ON t.user_id = u.id
                    WHERE user_id = ?";
    
    $total_stmt = $db->prepare($total_query);
    $total_stmt->execute([$user_id]);
    $total_stats = $total_stmt->fetch();
    
    echo json_encode([
        "weekly_analytics" => $weekly_data,
        "total_stats" => $total_stats
    ]);
}

function getTripHistory($db, $user_id) {
    $query = "SELECT * FROM trips WHERE user_id = ? ORDER BY trip_date DESC, created_at DESC LIMIT 50";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $trips = $stmt->fetchAll();
    
    echo json_encode(["trips" => $trips]);
}

function calculateCO2Saved($transport_type, $distance) {
    // CO2 emission factors (kg CO2 per km)
    $car_emission = 0.21; // Average car emission
    
    switch($transport_type) {
        case 'walk':
        case 'bike':
            return $distance * $car_emission; // Full savings
        case 'bus':
            return $distance * $car_emission * 0.7; // 70% savings
        case 'carpool':
            return $distance * $car_emission * 0.5; // 50% savings
        case 'car':
        default:
            return 0; // No savings
    }
}

function calculateEcoPoints($transport_type, $distance) {
    switch($transport_type) {
        case 'walk':
            return $distance * 10;
        case 'bike':
            return $distance * 8;
        case 'bus':
            return $distance * 5;
        case 'carpool':
            return $distance * 3;
        case 'car':
        default:
            return 0;
    }
}

function updateEcoScore($db, $user_id, $points) {
    $query = "UPDATE users SET eco_score = eco_score + ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$points, $user_id]);
}
?>
