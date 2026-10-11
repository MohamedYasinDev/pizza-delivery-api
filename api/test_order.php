<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

echo "=== 🧪 API TEST PAGE ===\n";
echo "Database: mohamed\n";
echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Database connection test
$host = "localhost";
$username = "root";
$password = "";
$database = "mohamed";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo "❌ Database Connection FAILED: " . $conn->connect_error . "\n";
    echo "Trying to create database...\n";
    
    // Try to create database
    $conn = new mysqli($host, $username, $password);
    if ($conn->connect_error) {
        die(json_encode([
            "status" => false,
            "message" => "MySQL connection failed: " . $conn->connect_error
        ]));
    }
    
    $sql = "CREATE DATABASE IF NOT EXISTS $database";
    if ($conn->query($sql) === TRUE) {
        echo "✅ Database '$database' created\n";
        $conn->select_db($database);
    } else {
        die(json_encode([
            "status" => false,
            "message" => "Failed to create database: " . $conn->error
        ]));
    }
} else {
    echo "✅ Database Connection SUCCESS\n";
}

// Check tables
echo "\n=== 📊 CHECKING TABLES ===\n";

$tables = ['users', 'fooditems', 'orders', 'orderitems'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table '$table' exists\n";
    } else {
        echo "❌ Table '$table' NOT found\n";
    }
}

// Check orders table structure
echo "\n=== 🔍 ORDERS TABLE STRUCTURE ===\n";
$result = $conn->query("DESCRIBE orders");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
} else {
    echo "❌ Cannot describe orders table\n";
}

// Count records
echo "\n=== 📈 RECORD COUNTS ===\n";
$tables = ['users', 'fooditems', 'orders', 'orderitems'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "$table: " . $row['count'] . " records\n";
    }
}

// Test sample food items
echo "\n=== 🍕 FOOD ITEMS ===\n";
$result = $conn->query("SELECT item_id, name, price FROM fooditems LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['item_id'] . " | " . $row['name'] . " | $" . $row['price'] . "\n";
    }
} else {
    echo "No food items found. Adding sample data...\n";
    
    $sample_foods = [
        "INSERT INTO fooditems (name, price, category) VALUES ('Margherita Pizza', 12.99, 'Pizza')",
        "INSERT INTO fooditems (name, price, category) VALUES ('Pepperoni Pizza', 14.99, 'Pizza')",
        "INSERT INTO fooditems (name, price, category) VALUES ('Coca-Cola', 2.50, 'Drink')",
        "INSERT INTO fooditems (name, price, category) VALUES ('Garlic Bread', 5.99, 'Appetizer')",
        "INSERT INTO fooditems (name, price, category) VALUES ('Caesar Salad', 8.99, 'Salad')"
    ];
    
    foreach ($sample_foods as $sql) {
        if ($conn->query($sql)) {
            echo "✅ Added: " . substr($sql, 40, 30) . "...\n";
        }
    }
}

// Test POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "\n=== 📨 POST REQUEST TEST ===\n";
    
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
    } else {
        echo "✅ Valid JSON received\n";
        echo "Data keys: " . implode(", ", array_keys($data)) . "\n";
        
        if (isset($data['items'])) {
            echo "Items count: " . count($data['items']) . "\n";
        }
    }
}

echo "\n=== 🚀 TEST COMPLETE ===\n";
echo "API is ready to accept orders!\n";

// Sample JSON for testing
echo "\n=== 📋 SAMPLE ORDER JSON ===\n";
echo json_encode([
    "customer_name" => "Ahmed Ali",
    "customer_phone" => "0611234567",
    "customer_email" => "ahmed@example.com",
    "delivery_address" => "Mogadishu, Hodan District",
    "special_instructions" => "No onions, extra cheese",
    "payment_method" => "cash",
    "total_amount" => 25.98,
    "items" => [
        [
            "item_id" => 1,
            "quantity" => 2,
            "price" => 12.99,
            "subtotal" => 25.98
        ]
    ]
], JSON_PRETTY_PRINT);

$conn->close();
?>