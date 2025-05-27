<?php
// التأكد من عدم وجود مخرجات قبل بدء الجلسة
ob_start();

// بدء الجلسة إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تعيين ترميز الصفحة
header('Content-Type: text/html; charset=utf-8');

// تضمين ملف الإعدادات إذا لم يكن موجوداً
if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}

// تعريف المسار الأساسي
if (!defined('BASE_URL')) {
    // التحقق من البيئة الحالية (محلية أو إنتاجية)
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    // إذا كانت البيئة محلية
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        define('BASE_URL', '/marketplace');
    } 
    // إذا كانت البيئة إنتاجية
    else {
        define('BASE_URL', '');
    }
}

// تعريف مسارات الملفات
if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', dirname(__DIR__) . '/uploads');
}

// تضمين الدوال المساعدة
require_once __DIR__ . '/functions.php';

// التحقق من حالة المستخدم إذا كان مسجل الدخول
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];
    $stmt = $conn->prepare("SELECT status FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    // إذا كان الحساب معلقاً أو محظوراً
    if ($customer && $customer['status'] !== 'active') {
        session_destroy();
        $_SESSION['error'] = "عذراً، حسابك غير نشط. يرجى الانتظار حتى تتم الموافقة على حسابك من قبل الإدارة.";
        header('Location: ' . BASE_URL . '/customer/login.php');
        exit();
    }
}
