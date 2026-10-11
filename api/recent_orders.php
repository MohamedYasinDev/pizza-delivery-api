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
            o.order_id,
            u.name AS customer_name,
            o.total_amount,
            o.status,
            o.payment_method,
            o.order_date
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        ORDER BY o.order_date DESC
        LIMIT 10
    ";
    
    $result = $conn->query($sql);
    $orders = [];
    
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'id' => '#' . str_pad($row['order_id'], 4, '0', STR_PAD_LEFT),
            'customer' => $row['customer_name'],
            'amount' => floatval($row['total_amount']),
            'status' => $row['status'],
            'payment_method' => $row['payment_method'],
            'time' => date('h:i A', strtotime($row['order_date']))
        ];
    }
    
    echo json_encode([
        "status" => true,
        "data" => $orders
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>