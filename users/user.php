<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

// 🔗 DB Connection
$conn = new mysqli("localhost", "root", "", "mohamed");
if ($conn->connect_error) {
    echo json_encode(["status" => false, "message" => "DB connection failed"]);
    exit;
}

// 🔍 Get request method
$method = $_SERVER['REQUEST_METHOD'];

// 📥 JSON input
$data = json_decode(file_get_contents("php://input"), true);

// ==========================
// 📌 CREATE USER (POST)
// ==========================
if ($method === "POST") {
    // ✅ Check if required fields exist
    if (!isset($data['name'], $data['phone'], $data['email'], $data['password'], $data['address'])) {
        echo json_encode(["status" => false, "message" => "Missing fields"]);
        exit;
    }

    // 🔒 Sanitize input
    $name     = $conn->real_escape_string($data['name']);
    $phone    = $conn->real_escape_string($data['phone']);
    $email    = $conn->real_escape_string($data['email']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $address  = $conn->real_escape_string($data['address']);

    $sql = "INSERT INTO Users (name, phone, email, password, address)
            VALUES ('$name','$phone','$email','$password','$address')";

    if ($conn->query($sql)) {
        echo json_encode(["status" => true, "message" => "User created successfully"]);
    } else {
        echo json_encode(["status" => false, "message" => $conn->error]);
    }
}

// ==========================
// 📌 READ USERS (GET)
// ==========================
elseif ($method === "GET") {
    // Hal user
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $sql = "SELECT user_id, name, phone, email, address, created_at 
                FROM Users WHERE user_id=$id";
    } 
    // Dhammaan users
    else {
        $sql = "SELECT user_id, name, phone, email, address, created_at 
                FROM Users ORDER BY user_id DESC";
    }

    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode(["status" => true, "data" => $users]);
}

// ==========================
// 📌 UPDATE USER (PUT)
// ==========================
elseif ($method === "PUT") {
    if (!isset($data['user_id'], $data['name'], $data['phone'], $data['email'], $data['address'])) {
        echo json_encode(["status" => false, "message" => "Missing fields"]);
        exit;
    }

    $id      = (int)$data['user_id'];
    $name    = $conn->real_escape_string($data['name']);
    $phone   = $conn->real_escape_string($data['phone']);
    $email   = $conn->real_escape_string($data['email']);
    $address = $conn->real_escape_string($data['address']);

    $sql = "UPDATE Users SET 
            name='$name',
            phone='$phone',
            email='$email',
            address='$address'
            WHERE user_id=$id";

    if ($conn->query($sql)) {
        echo json_encode(["status" => true, "message" => "User updated successfully"]);
    } else {
        echo json_encode(["status" => false, "message" => $conn->error]);
    }
}

// ==========================
// 📌 DELETE USER (DELETE)
// ==========================
elseif ($method === "DELETE") {
    if (!isset($_GET['id'])) {
        echo json_encode(["status" => false, "message" => "Missing user id"]);
        exit;
    }

    $id = (int)$_GET['id'];
    $sql = "DELETE FROM Users WHERE user_id=$id";

    if ($conn->query($sql)) {
        echo json_encode(["status" => true, "message" => "User deleted successfully"]);
    } else {
        echo json_encode(["status" => false, "message" => $conn->error]);
    }
}

// 🔚 Close DB connection
$conn->close();
?>
