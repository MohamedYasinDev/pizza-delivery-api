<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../config/db.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* =====================================
   GET → LOAD ALL ORDERS (REPORT)
===================================== */
if ($method === "GET") {

    $sql = "
    SELECT 
        o.order_id,
        u.name AS customer_name,
        u.phone,
        f.name AS item_name,
        oi.unit_price,
        oi.quantity,
        (oi.unit_price * oi.quantity) AS subtotal,
        o.total_amount,
        o.delivery_address,
        o.payment_method,
        o.payment_status,
        o.status,
        o.order_date
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN orderitems oi ON o.order_id = oi.order_id
    JOIN fooditems f ON oi.item_id = f.item_id
    ORDER BY o.order_date DESC
    ";

    $result = $conn->query($sql);
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode([
        "status" => true,
        "data" => $data
    ]);
    exit;
}

/* =====================================
   POST → UPDATE ORDER + ORDER ITEMS
===================================== */
if ($method === "POST") {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['order_id'])) {
        echo json_encode(["status"=>false,"message"=>"order_id required"]);
        exit;
    }

    $conn->begin_transaction();

    try {
        $order_id = intval($data['order_id']);
        $status = $data['status'] ?? 'completed';
        $payment_status = $data['payment_status'] ?? 'paid';

        // 1️⃣ Update order
        $stmt = $conn->prepare(
            "UPDATE orders 
             SET status = ?, payment_status = ?
             WHERE order_id = ?"
        );
        $stmt->bind_param("ssi", $status, $payment_status, $order_id);
        $stmt->execute();

        // 2️⃣ Update order items
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $order_item_id = intval($item['order_item_id']);
                $quantity = intval($item['quantity']);
                $unit_price = floatval($item['unit_price']);
                $subtotal = $quantity * $unit_price;

                $stmtItem = $conn->prepare(
                    "UPDATE orderitems
                     SET quantity = ?, unit_price = ?, subtotal = ?
                     WHERE order_item_id = ? AND order_id = ?"
                );
                $stmtItem->bind_param(
                    "iddii",
                    $quantity,
                    $unit_price,
                    $subtotal,
                    $order_item_id,
                    $order_id
                );
                $stmtItem->execute();
            }
        }

        // 3️⃣ Recalculate total amount
        $conn->query("
            UPDATE orders 
            SET total_amount = (
                SELECT SUM(subtotal) FROM orderitems WHERE order_id = $order_id
            )
            WHERE order_id = $order_id
        ");

        $conn->commit();

        echo json_encode([
            "status" => true,
            "message" => "Order updated successfully"
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            "status" => false,
            "message" => $e->getMessage()
        ]);
    }

    exit;
}

/* =====================================
   INVALID METHOD
===================================== */
echo json_encode([
    "status" => false,
    "message" => "Invalid request method"
]);
