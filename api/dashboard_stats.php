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
    $conn->begin_transaction();
    
    // 1. Total Users
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = intval($stmt->fetch_assoc()['total']);
    
    // 2. Total Orders
    $stmt = $conn->query("SELECT COUNT(*) as total FROM orders");
    $totalOrders = intval($stmt->fetch_assoc()['total']);
    
    // 3. Active Orders (pending or processing)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status IN ('pending', 'processing')");
    $activeOrders = intval($stmt->fetch_assoc()['total']);
    
    // 4. Total Revenue (completed orders only)
    $stmt = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status = 'completed'");
    $totalRevenue = floatval($stmt->fetch_assoc()['total']);
    
    // 5. Today's Orders
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE DATE(order_date) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $todayOrders = intval($stmt->get_result()->fetch_assoc()['total']);
    
    // 6. Today's Revenue
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE DATE(order_date) = ? AND status = 'completed'");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $todayRevenue = floatval($stmt->get_result()->fetch_assoc()['total']);
    
    // 7. Average order value
    $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
    
    $conn->commit();
    
    echo json_encode([
        "status" => true,
        "data" => [
            "total_users" => $totalUsers,
            "total_orders" => $totalOrders,
            "active_orders" => $activeOrders,
            "total_revenue" => $totalRevenue,
            "today_orders" => $todayOrders,
            "today_revenue" => $todayRevenue,
            "avg_order_value" => $avgOrderValue
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>