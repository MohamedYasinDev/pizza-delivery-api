<?php
header("Content-Type: application/json");
require_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"));

// Required fields
if (empty($data->email) || empty($data->password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required"
    ]);
    exit;
}

$email = trim($data->email);
$password = trim($data->password);

// Fetch user
$sql = "SELECT user_id, name, email, password FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Wrong password"
    ]);
    exit;
}

// Success
echo json_encode([
    "success" => true,
    "message" => "Login successful",
    "user" => [
        "user_id" => $user['user_id'],
        "name" => $user['name'],
        "email" => $user['email']
    ]
]);

$stmt->close();
$conn->close();


?>
