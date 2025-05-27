<?php
/**
 * هيدر احترافي بتصميم داكن للسوق الإلكتروني
 * تصميم عصري مع خلفية سوداء وأزرار برتقالية
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'السوق الإلكتروني'; ?></title>
    
    <!-- روابط الخطوط -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS الأساسية -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $root_path ?? ''; ?>customer/styles/dark-header.css">
    <link rel="stylesheet" href="<?php echo $root_path ?? ''; ?>customer/styles/mobile-fixes.css">
    <link rel="stylesheet" href="<?php echo $root_path ?? ''; ?>customer/styles/responsive-mobile.css">
    <link rel="stylesheet" href="<?php echo $root_path ?? ''; ?>customer/styles/mobile-account.css">
    <link rel="stylesheet" href="<?php echo $root_path ?? ''; ?>customer/styles/logout-button.css">
    
    <!-- ملفات متوافقة مع الأجهزة المحمولة -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <!-- ملفات CSS الخاصة بالصفحات -->
    <?php 
    // تحديد الصفحة الحالية لتضمين ملفات CSS المناسبة
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // ملف CSS لصفحة المتاجر
    if ($current_page == 'stores.php') {
        echo '<link rel="stylesheet" href="' . ($root_path ?? '') . 'customer/styles/stores-mobile.css">';
    }
    
    // ملف CSS للصفحات المميزة (الأكثر تقييماً، الأكثر إعجاباً، العروض)
    if ($current_page == 'top-rated.php' || $current_page == 'most-liked.php' || $current_page == 'deals.php') {
        echo '<link rel="stylesheet" href="' . ($root_path ?? '') . 'customer/styles/featured-pages-mobile.css">';
    }
    ?>
    
    <!-- ملفات CSS الإضافية -->
    <?php if (isset($additional_css)) echo $additional_css; ?>
    
    <!-- ملفات الجافاسكريبت الأساسية -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $root_path ?? ''; ?>customer/js/mobile-navigation.js" defer></script>
    <script src="<?php echo $root_path ?? ''; ?>customer/js/location-search.js" defer></script>
    
    <!-- سكريبت لقائمة المستخدم المنسدلة -->
    <script>
        // فتح وإغلاق قائمة الحساب على سطح المكتب
        function toggleDesktopMenu() {
            var menu = document.getElementById('desktopAccountMenu');
            if (menu.classList.contains('show')) {
                menu.classList.remove('show');
            } else {
                menu.classList.add('show');
            }
        }
        
        // إغلاق قائمة سطح المكتب عند النقر في مكان آخر
        document.addEventListener('click', function(e) {
            var menu = document.getElementById('desktopAccountMenu');
            var button = document.getElementById('desktopAccountBtn');
            
            if (menu && button) {
                if (!menu.contains(e.target) && !button.contains(e.target) && menu.classList.contains('show')) {
                    menu.classList.remove('show');
                }
            }
        });
        
        // فتح قائمة الحساب على الموبايل
        function openMobileAccountMenu() {
            var mobileMenu = document.getElementById('mobileAccountMenu');
            if (mobileMenu) {
                mobileMenu.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }
        
        // إغلاق قائمة الحساب على الموبايل
        function closeMobileAccountMenu() {
            var mobileMenu = document.getElementById('mobileAccountMenu');
            if (mobileMenu) {
                mobileMenu.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        
        // إضافة مستمعي الأحداث عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // إضافة مستمع النقر لزر إغلاق قائمة الموبايل
            var closeBtn = document.getElementById('mobileAccountClose');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeMobileAccountMenu);
            }
            
            // إضافة مستمع النقر للخلفية
            var overlay = document.getElementById('mobileAccountOverlay');
            if (overlay) {
                overlay.addEventListener('click', closeMobileAccountMenu);
            }
            
            // إضافة مستمع النقر لروابط قائمة الموبايل
            var mobileMenu = document.getElementById('mobileAccountMenu');
            if (mobileMenu) {
                var links = mobileMenu.querySelectorAll('a');
                links.forEach(function(link) {
                    link.addEventListener('click', closeMobileAccountMenu);
                });
            }
        });
    </script>
</head>
<body>
    <!-- الهيدر الرئيسي -->
    <header class="dark-header">
        <div class="container">
            <div class="header-top">
                <div class="logo">
                    <a href="<?php echo $root_path ?? ''; ?>customer/index.php">
                        <img src="<?php echo $root_path ?? ''; ?>images/shahbandar-logo.png" alt="Shahbandar" width="208" height="75" style="width: 208px; height: 75px;">
                    </a>
                </div>
                
                <!-- تم إزالة زر البحث بناءً على طلب المستخدم -->
                
                <?php
                // إعداد معلمات مكون البحث للهيدر
                $form_action = ($root_path ?? '') . "customer/search.php";
                $placeholder = "ابحث عن منتجات أو متاجر...";
                $search_value = isset($_GET['search']) ? $_GET['search'] : '';
                $hidden_fields = [];
                $show_type_selector = true;
                $show_location = true;
                $search_class = "search-form";
                $input_class = "search-input";
                $button_class = "round-btn";
                
                // استدعاء مكون البحث
                // استخدام مسار مطلق للملف لتجنب مشاكل المسارات عند الرفع
                $search_component_path = __DIR__ . "/search_component.php";
                
                // التحقق من وجود الملف قبل تضمينه
                if (file_exists($search_component_path)) {
                    include_once $search_component_path;
                } else {
                    // إذا لم يتم العثور على الملف، نقوم بتضمين مكون البحث مباشرة هنا
                    ?>
                    <div class="search-container">
                        <form action="<?php echo htmlspecialchars($form_action); ?>" method="GET" class="<?php echo $search_class; ?>" id="search-form">
                            <?php if ($show_type_selector): ?>
                            <div class="search-type">
                                <select name="view" class="search-select">
                                    <option value="products" <?php echo (isset($_GET['view']) && $_GET['view'] == 'stores') ? '' : 'selected'; ?>>المنتجات</option>
                                    <option value="stores" <?php echo (isset($_GET['view']) && $_GET['view'] == 'stores') ? 'selected' : ''; ?>>المتاجر</option>
                                </select>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                            <?php endif; ?>
                            
                            <div class="search-input-wrap">
                                <input type="text" name="search" class="<?php echo $input_class; ?>" 
                                       placeholder="<?php echo htmlspecialchars($placeholder); ?>" 
                                       value="<?php echo htmlspecialchars($search_value); ?>" 
                                       autocomplete="off">
                            </div>
                            
                            <?php foreach ($hidden_fields as $name => $value): ?>
                                <input type="hidden" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars($value); ?>">
                            <?php endforeach; ?>
                            
                            <?php if ($show_location): ?>
                            <button type="button" class="<?php echo $button_class; ?> location-btn" onclick="getLocation(document.querySelector('#search-form input[name=search]').value)">
                                <i class="bi bi-geo-alt"></i>
                            </button>
                            <?php else: ?>
                            <button type="submit" class="<?php echo $button_class; ?>">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php
                }
                ?>
                
                <div class="user-actions">
                    <?php if (isset($_SESSION['customer_id'])): ?>
                        <!-- قائمة الحساب للأجهزة الكبيرة -->
                        <div class="user-dropdown d-none d-md-block">
                            <a href="javascript:void(0);" class="user-btn" id="desktopAccountBtn" onclick="toggleDesktopMenu()">
                                <i class="bi bi-person"></i>
                                <span>حسابي</span>
                                <i class="bi bi-chevron-down"></i>
                            </a>
                            <div class="dropdown-menu user-menu" id="desktopAccountMenu">
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <i class="bi bi-person-circle"></i>
                                    </div>
                                    <div>
                                        <h6><?php echo $_SESSION['customer_name'] ?? 'المستخدم'; ?></h6>
                                        <p><?php echo $_SESSION['customer_email'] ?? ''; ?></p>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a href="<?php echo $root_path ?? ''; ?>customer/profile.php" class="dropdown-item">
                                    <i class="bi bi-person"></i> الملف الشخصي
                                </a>
                                <a href="<?php echo $root_path ?? ''; ?>customer/orders.php" class="dropdown-item">
                                    <i class="bi bi-bag"></i> طلباتي
                                </a>
                                <a href="<?php echo $root_path ?? ''; ?>customer/wishlist.php" class="dropdown-item">
                                    <i class="bi bi-heart"></i> المفضلة
                                </a>
                                <a href="<?php echo $root_path ?? ''; ?>customer/addresses.php" class="dropdown-item">
                                    <i class="bi bi-geo-alt"></i> العناوين
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="<?php echo $root_path ?? ''; ?>customer/logout.php" class="dropdown-item logout-item">
                                    <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
                                </a>
                            </div>
                        </div>
                        
                        <!-- زر الحساب للأجهزة المحمولة - يفتح قائمة منفصلة -->
                        <a href="javascript:void(0);" class="user-btn d-flex d-md-none mobile-account-link" onclick="openMobileAccountMenu()">
                            <i class="bi bi-person"></i>
                        </a>
                    <?php else: ?>
                        <!-- زر تسجيل الدخول للأجهزة الكبيرة -->
                        <a href="<?php echo $root_path ?? ''; ?>customer/login.php" class="user-btn d-none d-md-flex">
                            <i class="bi bi-person"></i>
                            <span>تسجيل الدخول</span>
                        </a>
                        
                        <!-- زر تسجيل الدخول للأجهزة المحمولة -->
                        <a href="<?php echo $root_path ?? ''; ?>customer/login.php" class="user-btn d-flex d-md-none mobile-account-link">
                            <i class="bi bi-person"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <nav class="main-nav">
                <ul class="nav-list">
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="categoriesDropdown">
                            <i class="bi bi-list-ul"></i> الفئات <i class="bi bi-chevron-down"></i>
                        </a>
                        <div class="dropdown-menu categories-menu" id="categoriesDropdownMenu">
                            <div class="categories-grid">
                                <?php 
                                // عرض الفئات إذا كانت متوفرة
                                if (isset($categories_result) && $categories_result->num_rows > 0) {
                                    $categories_result->data_seek(0);
                                    while ($category = $categories_result->fetch_assoc()): 
                                ?>
                                    <a href="<?php echo $root_path ?? ''; ?>customer/index.php?category=<?php echo $category['id']; ?>" class="category-item">
                                        <div class="category-icon">
                                            <i class="bi bi-tag"></i>
                                        </div>
                                        <span><?php echo htmlspecialchars($category['name']); ?></span>
                                    </a>
                                <?php 
                                    endwhile;
                                } else {
                                    // إذا لم تكن الفئات متوفرة، عرض فئات افتراضية
                                ?>
                                    <a href="#" class="category-item">
                                        <div class="category-icon">
                                            <i class="bi bi-laptop"></i>
                                        </div>
                                        <span>إلكترونيات</span>
                                    </a>
                                    <a href="#" class="category-item">
                                        <div class="category-icon">
                                            <i class="bi bi-bag"></i>
                                        </div>
                                        <span>ملابس</span>
                                    </a>
                                    <a href="#" class="category-item">
                                        <div class="category-icon">
                                            <i class="bi bi-house"></i>
                                        </div>
                                        <span>المنزل</span>
                                    </a>
                                    <a href="#" class="category-item">
                                        <div class="category-icon">
                                            <i class="bi bi-gem"></i>
                                        </div>
                                        <span>اكسسوارات</span>
                                    </a>
                                    <a href="#" class="category-item">
                                        <div class="category-icon">
                                            <i class="bi bi-controller"></i>
                                        </div>
                                        <span>ألعاب</span>
                                    </a>
                                    <a href="#" class="category-item">
                                        <div class="category-icon">
                                            <i class="bi bi-phone"></i>
                                        </div>
                                        <span>هواتف</span>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $root_path ?? ''; ?>customer/products.php" class="nav-link">
                            <i class="bi bi-grid"></i> المنتجات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $root_path ?? ''; ?>customer/stores.php" class="nav-link">
                            <i class="bi bi-shop"></i> المتاجر
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $root_path ?? ''; ?>customer/top-rated.php" class="nav-link">
                            <i class="bi bi-star"></i> الأكثر تقييماً
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $root_path ?? ''; ?>customer/most-liked.php" class="nav-link">
                            <i class="bi bi-heart"></i> الأكثر إعجاباً
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $root_path ?? ''; ?>customer/deals.php" class="nav-link special">
                            <i class="bi bi-lightning"></i> العروض
                        </a>
                    </li>
                </ul>
                
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="bi bi-list"></i>
                </button>
            </nav>
        </div>
    </header>

    <!-- القائمة الجانبية للجوال -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <div class="mobile-logo">
                <img src="<?php echo $root_path ?? ''; ?>images/shahbandar-logo.png" alt="Shahbandar" width="138" height="50" style="width: 138px; height: 50px;">
            </div>
            <button class="mobile-menu-close" id="mobileMenuClose">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="mobile-menu-body">
            <ul class="mobile-nav-list">
                <li class="mobile-nav-item">
                    <a href="#" class="mobile-nav-link mobile-dropdown-toggle">
                        <i class="bi bi-list-ul"></i> الفئات
                        <i class="bi bi-chevron-down"></i>
                    </a>
                    <div class="mobile-dropdown-menu">
                        <a href="#" class="mobile-dropdown-item">
                            <i class="bi bi-laptop"></i> إلكترونيات
                        </a>
                        <a href="#" class="mobile-dropdown-item">
                            <i class="bi bi-bag"></i> ملابس
                        </a>
                        <a href="#" class="mobile-dropdown-item">
                            <i class="bi bi-house"></i> المنزل
                        </a>
                        <a href="#" class="mobile-dropdown-item">
                            <i class="bi bi-gem"></i> اكسسوارات
                        </a>
                    </div>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo $root_path ?? ''; ?>customer/products.php" class="mobile-nav-link">
                        <i class="bi bi-grid"></i> المنتجات
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo $root_path ?? ''; ?>customer/stores.php" class="mobile-nav-link">
                        <i class="bi bi-shop"></i> المتاجر
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo $root_path ?? ''; ?>customer/top-rated.php" class="mobile-nav-link">
                        <i class="bi bi-star"></i> الأكثر تقييماً
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo $root_path ?? ''; ?>customer/most-liked.php" class="mobile-nav-link">
                        <i class="bi bi-heart"></i> الأكثر إعجاباً
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo $root_path ?? ''; ?>customer/deals.php" class="mobile-nav-link special">
                        <i class="bi bi-lightning"></i> العروض
                    </a>
                </li>
                <li class="mobile-nav-divider"></li>
                <li class="mobile-nav-item">
                    <a href="<?php echo $root_path ?? ''; ?>customer/profile.php" class="mobile-nav-link">
                        <i class="bi bi-person"></i> حسابي
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="<?php echo $root_path ?? ''; ?>customer/cart.php" class="mobile-nav-link">
                        <i class="bi bi-cart3"></i> سلة التسوق
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- قائمة الحساب للأجهزة المحمولة -->
    <?php if (isset($_SESSION['customer_id'])): ?>
    <div class="mobile-account-menu" id="mobileAccountMenu">
        <div class="mobile-account-overlay" id="mobileAccountOverlay"></div>
        <div class="mobile-account-content">
            <div class="mobile-account-header">
                <div class="mobile-account-user">
                    <div class="mobile-account-avatar">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="mobile-account-info">
                        <h6><?php echo $_SESSION['customer_name'] ?? 'المستخدم'; ?></h6>
                        <p><?php echo $_SESSION['customer_email'] ?? ''; ?></p>
                    </div>
                </div>
                <button class="mobile-account-close" id="mobileAccountClose">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="mobile-account-body">
                <a href="<?php echo $root_path ?? ''; ?>customer/profile.php" class="mobile-account-item">
                    <i class="bi bi-person"></i>
                    <span>الملف الشخصي</span>
                </a>
                <a href="<?php echo $root_path ?? ''; ?>customer/orders.php" class="mobile-account-item">
                    <i class="bi bi-bag"></i>
                    <span>طلباتي</span>
                </a>
                <a href="<?php echo $root_path ?? ''; ?>customer/wishlist.php" class="mobile-account-item">
                    <i class="bi bi-heart"></i>
                    <span>المفضلة</span>
                </a>
                <a href="<?php echo $root_path ?? ''; ?>customer/addresses.php" class="mobile-account-item">
                    <i class="bi bi-geo-alt"></i>
                    <span>العناوين</span>
                </a>
                <div class="mobile-account-divider"></div>
                <a href="<?php echo $root_path ?? ''; ?>customer/logout.php" class="mobile-account-item mobile-account-logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- المحتوى الرئيسي -->
    <main>
