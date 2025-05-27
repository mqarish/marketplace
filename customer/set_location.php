<?php
session_start();

// تحقق من وجود طلب مسح الموقع
if (isset($_GET['clear'])) {
    unset($_SESSION['current_location']);
    header('Location: index.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] : ''));
    exit();
}

// تحقق من وجود بيانات الموقع
if (isset($_GET['location'])) {
    $_SESSION['current_location'] = $_GET['location'];
}

// إعادة التوجيه إلى صفحة البحث مع المعلمات المناسبة
$redirect_url = 'search.php';

// إضافة معلمات URL المناسبة
$params = [];

// إضافة نوع العرض (منتجات أو متاجر)
if (isset($_GET['view'])) {
    $params['view'] = $_GET['view'];
}

// إضافة كلمة البحث إذا وجدت
if (isset($_GET['search'])) {
    $params['search'] = $_GET['search'];
}

// إضافة معلمة لتفعيل البحث بالموقع
$params['use_location'] = '1';

// بناء URL مع المعلمات
if (!empty($params)) {
    $redirect_url .= '?' . http_build_query($params);
}

// إعادة التوجيه
header('Location: ' . $redirect_url);
exit();
?>
