<?php
// C:\xampp\htdocs\testfood_api\api\orders.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, Authorization");

// Log for debugging
$log_file = __DIR__ . '/order_log.txt';
file_put_contents($log_file, date('Y-m-d H:i:s') . " === REQUEST START ===\n", FILE_APPEND);

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    file_put_contents($log_file, "OPTIONS request handled\n", FILE_APPEND);
    exit();
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "mohamed";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    $error_msg = "Database connection failed: " . $conn->connect_error;
    file_put_contents($log_file, $error_msg . "\n", FILE_APPEND);
    echo json_encode([
        "status" => false,
        "message" => $error_msg
    ]);
    exit();
}

file_put_contents($log_file, "Database connected successfully\n", FILE_APPEND);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents($log_file, "Method not allowed: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
    http_response_code(405);
    echo json_encode([
        "status" => false, 
        "message" => "Method not allowed. Use POST method."
    ]);
    exit;
}

// Get raw input
$input = file_get_contents("php://input");
file_put_contents($log_file, "Raw input received: " . substr($input, 0, 500) . "\n", FILE_APPEND);

if (empty($input)) {
    file_put_contents($log_file, "Empty input received\n", FILE_APPEND);
    echo json_encode([
        "status" => false, 
        "message" => "No data received"
    ]);
    exit;
}

// Decode JSON
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $json_error = "Invalid JSON format: " . json_last_error_msg();
    file_put_contents($log_file, $json_error . "\n", FILE_APPEND);
    echo json_encode([
        "status" => false, 
        "message" => $json_error
    ]);
    exit;
}

file_put_contents($log_file, "JSON decoded successfully\n", FILE_APPEND);

// Check required fields
$required = [
    'customer_name', 
    'customer_phone', 
    'delivery_address',
    'payment_method', 
    'total_amount', 
    'items'
];

foreach ($required as $field) {
    if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
        $error_msg = "Missing or empty required field: $field";
        file_put_contents($log_file, $error_msg . "\n", FILE_APPEND);
        echo json_encode([
            "status" => false, 
            "message" => $error_msg
        ]);
        exit;
    }
}

// Validate items
if (!is_array($data['items']) || empty($data['items'])) {
    file_put_contents($log_file, "Invalid items array\n", FILE_APPEND);
    echo json_encode([
        "status" => false, 
        "message" => "Items must be a non-empty array"
    ]);
    exit;
}

// Start transaction
$conn->begin_transaction();
file_put_contents($log_file, "Transaction started\n", FILE_APPEND);

try {
    // ========================
    // 1. USER DATA - FIXED VERSION
    // ========================
    $name = $conn->real_escape_string(trim($data['customer_name']));
    $phone = $conn->real_escape_string(trim($data['customer_phone']));
    $address = $conn->real_escape_string(trim($data['delivery_address']));
    
    // Email (optional) - FIX: Handle empty email properly
    $email = null;
    if (isset($data['customer_email']) && !empty(trim($data['customer_email']))) {
        $email = $conn->real_escape_string(trim($data['customer_email']));
    }
    
    file_put_contents($log_file, "User data: name=$name, phone=$phone, email=" . ($email ?: 'NULL') . ", address=$address\n", FILE_APPEND);
    
    // Check if user exists by PHONE (not email)
    $checkUser = $conn->query("SELECT user_id, email FROM users WHERE phone = '$phone'");
    
    if ($checkUser && $checkUser->num_rows > 0) {
        // User exists
        $userRow = $checkUser->fetch_assoc();
        $user_id = $userRow['user_id'];
        $existing_email = $userRow['email'];
        
        file_put_contents($log_file, "User exists, ID: $user_id, existing email: $existing_email\n", FILE_APPEND);
        
        // Update user info - FIX: Don't update email if it already exists and new one is empty
        if ($email && $email !== $existing_email) {
            // Check if new email already exists for another user
            $checkEmail = $conn->query("SELECT user_id FROM users WHERE email = '$email' AND user_id != $user_id");
            if ($checkEmail && $checkEmail->num_rows > 0) {
                // Email exists for another user, keep existing email
                $updateSql = "UPDATE users SET name = '$name', address = '$address' WHERE user_id = $user_id";
                file_put_contents($log_file, "Email $email exists for another user, keeping existing email\n", FILE_APPEND);
            } else {
                // Update with new email
                $updateSql = "UPDATE users SET name = '$name', address = '$address', email = '$email' WHERE user_id = $user_id";
                file_put_contents($log_file, "Updating user with new email\n", FILE_APPEND);
            }
        } else {
            // Just update name and address
            $updateSql = "UPDATE users SET name = '$name', address = '$address' WHERE user_id = $user_id";
            file_put_contents($log_file, "Updating user without email change\n", FILE_APPEND);
        }
        
        if (!$conn->query($updateSql)) {
            throw new Exception("User update failed: " . $conn->error);
        }
        file_put_contents($log_file, "User updated successfully\n", FILE_APPEND);
    } else {
        // Create new user - FIX: Handle duplicate email properly
        if ($email) {
            // Check if email already exists
            $checkEmail = $conn->query("SELECT user_id FROM users WHERE email = '$email'");
            if ($checkEmail && $checkEmail->num_rows > 0) {
                // Email exists, create user without email
                $sqlUser = "INSERT INTO users (name, phone, address) VALUES ('$name', '$phone', '$address')";
                file_put_contents($log_file, "Email $email exists, creating user without email\n", FILE_APPEND);
            } else {
                // Email doesn't exist, create with email
                $sqlUser = "INSERT INTO users (name, phone, email, address) VALUES ('$name', '$phone', '$email', '$address')";
                file_put_contents($log_file, "Creating new user with email\n", FILE_APPEND);
            }
        } else {
            // No email provided
            $sqlUser = "INSERT INTO users (name, phone, address) VALUES ('$name', '$phone', '$address')";
            file_put_contents($log_file, "Creating new user without email\n", FILE_APPEND);
        }
        
        file_put_contents($log_file, "SQL: $sqlUser\n", FILE_APPEND);
        
        if (!$conn->query($sqlUser)) {
            throw new Exception("User creation failed: " . $conn->error);
        }
        $user_id = $conn->insert_id;
        file_put_contents($log_file, "User created successfully, ID: $user_id\n", FILE_APPEND);
    }

    // ========================
    // 2. ORDER DATA
    // ========================
    $total_amount = floatval($data['total_amount']);
    $payment_method = $conn->real_escape_string($data['payment_method']);
    
    // Special instructions (optional)
    $special_instructions = "";
    if (isset($data['special_instructions']) && !empty(trim($data['special_instructions']))) {
        $special_instructions = $conn->real_escape_string(trim($data['special_instructions']));
    }
    
    // Insert order
    $sqlOrder = "INSERT INTO orders (user_id, total_amount, delivery_address, special_instructions, payment_method, status, order_date) 
                 VALUES ($user_id, $total_amount, '$address', '$special_instructions', '$payment_method', 'pending', NOW())";
    
    file_put_contents($log_file, "Inserting order: $sqlOrder\n", FILE_APPEND);
    
    if (!$conn->query($sqlOrder)) {
        throw new Exception("Order creation failed: " . $conn->error);
    }
    $order_id = $conn->insert_id;
    file_put_contents($log_file, "Order created successfully, ID: $order_id\n", FILE_APPEND);

    // ========================
    // 3. ORDER ITEMS
    // ========================
    $items_count = 0;
    foreach ($data['items'] as $index => $item) {
        $item_id = intval($item['item_id'] ?? 0);
        $quantity = intval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0.0);
        $subtotal = floatval($item['subtotal'] ?? ($price * $quantity));
        
        file_put_contents($log_file, "Item $index: item_id=$item_id, quantity=$quantity, price=$price, subtotal=$subtotal\n", FILE_APPEND);
        
        $sqlItem = "INSERT INTO orderitems (order_id, item_id, quantity, unit_price, subtotal) 
                    VALUES ($order_id, $item_id, $quantity, $price, $subtotal)";
        
        if (!$conn->query($sqlItem)) {
            throw new Exception("Order item insertion failed: " . $conn->error);
        }
        $items_count++;
    }

    // Commit transaction
    $conn->commit();
    file_put_contents($log_file, "Transaction committed successfully\n", FILE_APPEND);

    // ========================
    // ✅ SUCCESS RESPONSE
    // ========================
    $response = [
        "status" => true,
        "message" => "✅ Order placed successfully!",
        "order_id" => $order_id,
        "user_id" => $user_id,
        "total_amount" => $total_amount,
        "items_count" => $items_count
    ];
    
    if (!empty($special_instructions)) {
        $response["special_instructions"] = $special_instructions;
    }
    
    file_put_contents($log_file, "Sending success response\n", FILE_APPEND);
    echo json_encode($response);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $error_msg = "❌ Order failed: " . $e->getMessage();
    file_put_contents($log_file, $error_msg . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => $error_msg
    ]);
}

$conn->close();
file_put_contents($log_file, "Database connection closed\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " === REQUEST END ===\n\n", FILE_APPEND);
?>