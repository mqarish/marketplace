<?php
// التحقق من تسجيل الدخول
if (!isset($_SESSION['store_id'])) {
    header('Location: login.php');
    exit();
}

// تحديد الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="card mb-4">
    <div class="list-group list-group-flush">
        <a href="index.php" class="list-group-item list-group-item-action <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2 me-2"></i>لوحة التحكم
        </a>
        <a href="profile.php" class="list-group-item list-group-item-action <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <i class="bi bi-person me-2"></i>الملف الشخصي
        </a>
        <a href="products.php" class="list-group-item list-group-item-action <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
            <i class="bi bi-box me-2"></i>المنتجات
        </a>
        <a href="add-product.php" class="list-group-item list-group-item-action <?php echo $current_page === 'add-product.php' ? 'active' : ''; ?>">
            <i class="bi bi-plus-circle me-2"></i>إضافة منتج
        </a>
        <a href="offers.php" class="list-group-item list-group-item-action <?php echo $current_page === 'offers.php' ? 'active' : ''; ?>">
            <i class="bi bi-tag me-2"></i>العروض
        </a>
        <a href="add-offers.php" class="list-group-item list-group-item-action <?php echo $current_page === 'add-offers.php' ? 'active' : ''; ?>">
            <i class="bi bi-plus-circle me-2"></i>إضافة عرض
        </a>
        <a href="orders.php" class="list-group-item list-group-item-action <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
            <i class="bi bi-cart me-2"></i>الطلبات
        </a>
        <a href="settings.php" class="list-group-item list-group-item-action <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
            <i class="bi bi-gear me-2"></i>الإعدادات
        </a>
    </div>
</div>
