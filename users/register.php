<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");

require_once '../config/db.php';

// Read JSON input
$data = json_decode(file_get_contents("php://input"));

// ==========================
// VALIDATION
// ==========================
if (
    empty($data->name) ||
    empty($data->email) ||
    empty($data->password)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Name, email and password are required"
    ]);
    exit;
}

if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid email format"
    ]);
    exit;
}

if (strlen($data->password) < 6) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 6 characters"
    ]);
    exit;
}

// Optional fields (NULL-safe)
$name    = $data->name;
$phone   = $data->phone ?? '';
$email   = $data->email;
$address = $data->address ?? '';

// ==========================
// CHECK DUPLICATE EMAIL
// ==========================
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "Email already exists"
    ]);
    exit;
}
$check->close();

// ==========================
// HASH PASSWORD
// ==========================
$hashedPassword = password_hash($data->password, PASSWORD_DEFAULT);

// ==========================
// INSERT USER (ALL COLUMNS)
// ==========================
$stmt = $conn->prepare("
    INSERT INTO users (name, phone, email, password, address, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    "sssss",
    $name,
    $phone,
    $email,
    $hashedPassword,
    $address
);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "User registered successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Registration failed",
        "error" => $stmt->error // 🔥 debugging
    ]);
}

$stmt->close();
$conn->close();
