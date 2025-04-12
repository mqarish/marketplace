<?php
// تفعيل عرض الأخطاء
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تعريف ثوابت الموقع
define('SITE_URL', 'http://localhost/marketplace');

// معلومات الاتصال بقاعدة البيانات
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'marketplace';

// إنشاء اتصال جديد بالخادم
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// تعيين ترميز الاتصال
mysqli_set_charset($conn, "utf8mb4");

// التحقق من الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// تعيين المنطقة الزمنية
date_default_timezone_set('Asia/Riyadh');

// دوال مساعدة
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

function generate_slug($text) {
    // تحويل النص إلى حروف صغيرة
    $text = mb_strtolower($text, 'UTF-8');
    
    // استبدال المسافات بشرطات
    $text = str_replace(' ', '-', $text);
    
    // إزالة الأحرف الخاصة
    $text = preg_replace('/[^a-z0-9\-]/', '', $text);
    
    // إزالة الشرطات المتكررة
    $text = preg_replace('/-+/', '-', $text);
    
    // إزالة الشرطات من البداية والنهاية
    $text = trim($text, '-');
    
    return $text;
}

function format_price($price) {
    return number_format($price, 2) . ' ريال';
}

function get_time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    
    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "منذ ثواني";
    } else if ($minutes <= 60) {
        if ($minutes == 1) {
            return "منذ دقيقة";
        } else {
            return "منذ $minutes دقيقة";
        }
    } else if ($hours <= 24) {
        if ($hours == 1) {
            return "منذ ساعة";
        } else {
            return "منذ $hours ساعة";
        }
    } else if ($days <= 7) {
        if ($days == 1) {
            return "منذ يوم";
        } else {
            return "منذ $days أيام";
        }
    } else if ($weeks <= 4.3) {
        if ($weeks == 1) {
            return "منذ أسبوع";
        } else {
            return "منذ $weeks أسابيع";
        }
    } else if ($months <= 12) {
        if ($months == 1) {
            return "منذ شهر";
        } else {
            return "منذ $months أشهر";
        }
    } else {
        if ($years == 1) {
            return "منذ سنة";
        } else {
            return "منذ $years سنوات";
        }
    }
}

// إنشاء قاعدة البيانات إذا لم تكن موجودة
$sql = "CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!$conn->query($sql)) {
    die("خطأ في إنشاء قاعدة البيانات: " . $conn->error);
}

// اختيار قاعدة البيانات
$conn->select_db($db_name);

// إنشاء المستخدم المسؤول إذا لم يكن موجوداً
$admin_check = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if (!$admin_check || $admin_check->num_rows == 0) {
    // التأكد من وجود جدول المستخدمين
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'store', 'customer') NOT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // إنشاء حساب المسؤول
    $admin_username = 'admin';
    $admin_email = 'admin@example.com';
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $admin_role = 'admin';

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->bind_param("ssss", $admin_username, $admin_email, $admin_password, $admin_role);
    $stmt->execute();
}

// Site configuration
define('UPLOADS_PATH', __DIR__ . '/../uploads');

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOADS_PATH)) {
    mkdir(UPLOADS_PATH, 0777, true);
    mkdir(UPLOADS_PATH . '/stores', 0777, true);
    mkdir(UPLOADS_PATH . '/products', 0777, true);
}
?>
