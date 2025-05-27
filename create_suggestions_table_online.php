<?php
// استدعاء ملف الاتصال بقاعدة البيانات
require_once 'includes/config.php';

// إنشاء جدول الاقتراحات
$create_table_sql = "CREATE TABLE IF NOT EXISTS `suggestions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `suggestion_text` TEXT NOT NULL,
    `status` ENUM('pending', 'reviewed', 'implemented', 'rejected') DEFAULT 'pending',
    `admin_notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_table_sql) === TRUE) {
    echo "تم إنشاء جدول الاقتراحات بنجاح";
    
    // إضافة بعض البيانات التجريبية (اختياري)
    $insert_data = "INSERT INTO `suggestions` (`customer_id`, `name`, `email`, `suggestion_text`, `status`) VALUES
    (NULL, 'محمد أحمد', 'mohamed@example.com', 'أقترح إضافة خاصية البحث عن المنتجات حسب اللون', 'pending'),
    (NULL, 'سارة محمد', 'sara@example.com', 'أتمنى لو كان هناك خيار للدفع عند الاستلام', 'pending'),
    (NULL, 'أحمد علي', 'ahmed@example.com', 'أقترح إضافة تقييمات للمتاجر وليس فقط للمنتجات', 'pending')";
    
    if ($conn->query($insert_data) === TRUE) {
        echo "<br>تم إضافة بيانات تجريبية بنجاح";
    } else {
        echo "<br>خطأ في إضافة البيانات التجريبية: " . $conn->error;
    }
} else {
    echo "خطأ في إنشاء جدول الاقتراحات: " . $conn->error;
}

// عرض معلومات عن قاعدة البيانات للتشخيص
echo "<h2>معلومات قاعدة البيانات:</h2>";
echo "<p>اسم قاعدة البيانات: " . $database . "</p>";

// التحقق من الجداول الموجودة
$tables_query = "SHOW TABLES";
$tables_result = $conn->query($tables_query);

if ($tables_result->num_rows > 0) {
    echo "<h3>الجداول الموجودة في قاعدة البيانات:</h3>";
    echo "<ul>";
    while($table = $tables_result->fetch_array()) {
        echo "<li>" . $table[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>لا توجد جداول في قاعدة البيانات</p>";
}

// إغلاق الاتصال
$conn->close();
?>
