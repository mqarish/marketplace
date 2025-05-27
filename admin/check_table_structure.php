<?php
require_once '../includes/init.php';

// Check if product_category_id column exists in products table
$result = $conn->query("SHOW COLUMNS FROM products LIKE 'product_category_id'");
$exists = ($result->num_rows > 0);

echo "product_category_id column exists: " . ($exists ? "Yes" : "No") . "\n";

// Show all columns in products table
echo "\nAll columns in products table:\n";
$result = $conn->query("SHOW COLUMNS FROM products");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
