<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// ==========================
// ➕ CREATE FOOD (POST)
// ==========================
if ($method === "POST") {
    // ✅ Check required fields
    if (!isset($data['name'], $data['description'], $data['price'], $data['category'], $data['image_url'])) {
        echo json_encode(["status" => false, "message" => "Missing fields"]);
        exit;
    }

    // 🔒 Sanitize inputs
    $name        = $conn->real_escape_string($data['name']);
    $description = $conn->real_escape_string($data['description']);
    $price       = floatval($data['price']); // Ensure numeric
    $category    = $conn->real_escape_string($data['category']);
    $image_url   = $conn->real_escape_string($data['image_url']);

    $sql = "INSERT INTO FoodItems (name, description, price, category, image_url)
            VALUES ('$name', '$description', $price, '$category', '$image_url')";

    if ($conn->query($sql)) {
        echo json_encode(["status" => true, "message" => "Food added successfully"]);
    } else {
        echo json_encode(["status" => false, "message" => $conn->error]);
    }
}

// ==========================
// 📥 READ FOOD (GET)
// ==========================
elseif ($method === "GET") {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $sql = "SELECT * FROM FoodItems WHERE item_id=$id";
    } else {
        $sql = "SELECT * FROM FoodItems WHERE is_available=1 ORDER BY item_id DESC";
    }

    $result = $conn->query($sql);
    $foods = [];
    while ($row = $result->fetch_assoc()) {
        $foods[] = $row;
    }

    echo json_encode(["status" => true, "data" => $foods]);
}

// ==========================
// ✏ UPDATE FOOD (PUT)
// ==========================
elseif ($method === "PUT") {
    if (!isset($data['item_id'], $data['name'], $data['description'], $data['price'], $data['category'])) {
        echo json_encode(["status" => false, "message" => "Missing fields"]);
        exit;
    }

    $id          = (int)$data['item_id'];
    $name        = $conn->real_escape_string($data['name']);
    $description = $conn->real_escape_string($data['description']);
    $price       = floatval($data['price']);
    $category    = $conn->real_escape_string($data['category']);

    $sql = "UPDATE FoodItems SET
            name='$name',
            description='$description',
            price=$price,
            category='$category'
            WHERE item_id=$id";

    if ($conn->query($sql)) {
        echo json_encode(["status" => true, "message" => "Food updated successfully"]);
    } else {
        echo json_encode(["status" => false, "message" => $conn->error]);
    }
}

// ==========================
// ❌ DELETE FOOD (DELETE)
// ==========================
elseif ($method === "DELETE") {
    if (!isset($_GET['id'])) {
        echo json_encode(["status" => false, "message" => "Missing item id"]);
        exit;
    }

    $id = (int)$_GET['id'];
    $sql = "DELETE FROM FoodItems WHERE item_id=$id";

    if ($conn->query($sql)) {
        echo json_encode(["status" => true, "message" => "Food deleted successfully"]);
    } else {
        echo json_encode(["status" => false, "message" => $conn->error]);
    }
}

// 🔚 Close DB connection
$conn->close();
?>
