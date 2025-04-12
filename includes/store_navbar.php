<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="index.php">لوحة تحكم المتجر</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">الرئيسية</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">المنتجات</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">الطلبات</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="offers.php">العروض</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['store_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($_SESSION['store_name'] ?? 'حسابي'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">الملف الشخصي</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">تسجيل الخروج</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">تسجيل الدخول</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
