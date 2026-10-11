<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => false, "message" => "Method not allowed"]);
    exit;
}

try {
    $sql = "
        SELECT 
            f.name,
            COUNT(oi.item_id) as order_count,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.subtotal) as total_revenue
        FROM orderitems oi
        JOIN fooditems f ON oi.item_id = f.item_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.status = 'completed'
        GROUP BY f.item_id, f.name
        ORDER BY total_revenue DESC
        LIMIT 5
    ";
    
    $result = $conn->query($sql);
    $foods = [];
    
    while ($row = $result->fetch_assoc()) {
        $foods[] = [
            'name' => $row['name'],
            'orders' => intval($row['order_count']),
            'quantity' => intval($row['total_quantity']),
            'revenue' => floatval($row['total_revenue'])
        ];
    }
    
    echo json_encode([
        "status" => true,
        "data" => $foods
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>