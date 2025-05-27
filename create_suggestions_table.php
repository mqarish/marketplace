<?php
require_once 'includes/init.php';

// إنشاء جدول المقترحات إذا لم يكن موجودًا
$create_table_sql = "CREATE TABLE IF NOT EXISTS suggestions (
    id INT(11) NOT NULL AUTO_INCREMENT,
    customer_id INT(11) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    suggestion_text TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'implemented', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY customer_id (customer_id),
    CONSTRAINT fk_suggestions_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_table_sql) === TRUE) {
    echo "تم إنشاء جدول المقترحات بنجاح";
} else {
    echo "خطأ في إنشاء جدول المقترحات: " . $conn->error;
}
?>
