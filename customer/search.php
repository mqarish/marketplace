<?php
/**
 * صفحة نتائج البحث - تعرض نتائج البحث عن المنتجات والمتاجر
 * 
 * تستخدم الهيدر الداكن وتعرض نتائج البحث بتنسيق جميل وسهل الاستخدام
 */

session_start();
require_once '../includes/init.php';
require_once '../includes/functions.php';

// ===== التحقق من المستخدم =====

// التأكد من تسجيل الدخول
if (!isset($_SESSION['customer_id'])) {
    header('Location: /marketplace/customer/login.php');
    exit();
}

// التحقق من حالة العميل
$customer_id = $_SESSION['customer_id'];
$stmt = $conn->prepare("SELECT status FROM customers WHERE id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $customer = $result->fetch_assoc();
    if ($customer['status'] !== 'active') {
        session_destroy();
        header('Location: /marketplace/customer/login.php?error=inactive');
        exit();
    }
} else {
    session_destroy();
    header('Location: /marketplace/customer/login.php?error=invalid');
    exit();
}

// ===== معالجة معايير البحث =====

// الحصول على معايير البحث
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : '';
$view_type = isset($_GET['view']) ? $_GET['view'] : 'all'; // all, products, stores
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // newest, price_low, price_high, rating
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

// الحصول على معلومات الموقع إن وجدت
$use_location = isset($_GET['use_location']) && $_GET['use_location'] == '1';

// التحقق من وجود موقع مخزن في الجلسة
$location_coords = null;
if (isset($_SESSION['current_location'])) {
    $location_coords = explode(',', $_SESSION['current_location']);
}

// تعيين الإحداثيات من الجلسة إذا كانت متوفرة
$latitude = null;
$longitude = null;
if ($use_location && $location_coords && count($location_coords) == 2) {
    $latitude = (float)$location_coords[0];
    $longitude = (float)$location_coords[1];
}

$location_radius = 5; // نصف قطر البحث بالكيلومترات

// التحقق من وجود مصطلح بحث
if (empty($search) && !$use_location) {
    $search_error = 'يرجى إدخال كلمة بحث';
}

// التحقق من وجود موقع محدد إذا تم تفعيل البحث بالموقع
if ($use_location && (!$latitude || !$longitude)) {
    $location_error = 'لم يتم تحديد موقعك بشكل صحيح. الرجاء المحاولة مرة أخرى.';
}

// ===== جلب التصنيفات =====
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
$categories = [];
while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
}

// ===== جلب نتائج البحث =====

// تحديد نوع البحث (منتجات، متاجر، الكل)
$products_count = 0;
$stores_count = 0;
$products = [];
$stores = [];

// البحث في المنتجات
if ($view_type == 'all' || $view_type == 'products') {
    $products_sql = "SELECT p.*, c.name as category_name, s.name as store_name, s.logo as store_logo,
                    IFNULL(AVG(r.rating), 0) as avg_rating,
                    COUNT(DISTINCT r.id) as rating_count,
                    COUNT(DISTINCT l.id) as likes_count";
                    
    // إضافة حساب المسافة إذا تم تحديد الموقع
    if ($use_location && $latitude && $longitude) {
        $products_sql .= ",
                    (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) AS distance";
    }
                    
    $products_sql .= " FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    LEFT JOIN stores s ON p.store_id = s.id 
                    LEFT JOIN reviews r ON p.id = r.product_id
                    LEFT JOIN product_likes l ON p.id = l.product_id";
    
    $where_clauses = ["p.status = 'active'", "s.status = 'active'"];
    $params = [];
    $types = "";
    
    // إضافة معلمات الموقع إلى الاستعلام
    if ($use_location && $latitude && $longitude) {
        $params[] = $latitude;
        $params[] = $longitude;
        $params[] = $latitude;
        $types .= "ddd";
        
        // إضافة شرط للمتاجر التي لديها إحداثيات
        $where_clauses[] = "s.latitude IS NOT NULL AND s.longitude IS NOT NULL";
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(p.name LIKE ? OR p.description LIKE ? OR s.name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }
    
    if (!empty($category_id)) {
        $where_clauses[] = "p.category_id = ?";
        $params[] = $category_id;
        $types .= "i";
    }
    
    $products_sql .= " WHERE " . implode(" AND ", $where_clauses);
    $products_sql .= " GROUP BY p.id";
    
    // ترتيب النتائج
    if ($use_location && $latitude && $longitude && $sort == 'newest') {
        // إذا تم تحديد الموقع ولم يتم تحديد ترتيب مختلف، نرتب حسب المسافة
        $products_sql .= " ORDER BY distance ASC";
        $sort = 'distance'; // تغيير نوع الترتيب للعرض في الواجهة
    } else {
        switch ($sort) {
            case 'price_low':
                $products_sql .= " ORDER BY p.price ASC";
                break;
            case 'price_high':
                $products_sql .= " ORDER BY p.price DESC";
                break;
            case 'rating':
                $products_sql .= " ORDER BY avg_rating DESC";
                break;
            case 'distance':
                if ($use_location && $latitude && $longitude) {
                    $products_sql .= " ORDER BY distance ASC";
                } else {
                    $products_sql .= " ORDER BY p.created_at DESC";
                }
                break;
            case 'newest':
            default:
                $products_sql .= " ORDER BY p.created_at DESC";
                break;
        }
    }
    
    // استخدام استعلام أبسط للعد لتجنب المشاكل
    try {
        // استخدام استعلام مباشر بدلاً من prepared statement لتجنب المشاكل
        $simple_count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    LEFT JOIN stores s ON p.store_id = s.id 
                    WHERE p.status = 'active' AND s.status = 'active'";
                    
        if (!empty($search)) {
            $simple_count_sql .= " AND (p.name LIKE '%" . $conn->real_escape_string($search) . "%' 
                                OR p.description LIKE '%" . $conn->real_escape_string($search) . "%' 
                                OR s.name LIKE '%" . $conn->real_escape_string($search) . "%')";
        }
        
        if (!empty($category_id)) {
            $simple_count_sql .= " AND p.category_id = " . (int)$category_id;
        }
        
        $count_result = $conn->query($simple_count_sql);
        if ($count_result) {
            $products_count = $count_result->fetch_assoc()['total'];
        } else {
            error_log("Error in simple count query: " . $conn->error);
            $products_count = 0;
        }
    } catch (Exception $e) {
        error_log("Exception in count query: " . $e->getMessage());
        $products_count = 0;
    }
    
    try {
        // إنشاء استعلام للمنتجات مع حساب المسافة
        $simple_products_sql = "SELECT p.*, c.name as category_name, s.name as store_name, s.logo as store_logo,
                        IFNULL(AVG(r.rating), 0) as avg_rating,
                        COUNT(DISTINCT r.id) as rating_count,
                        COUNT(DISTINCT l.id) as likes_count";
                        
        // إضافة حساب المسافة إذا تم تحديد الموقع
        if ($use_location && $latitude && $longitude) {
            $simple_products_sql .= ",
                        (6371 * acos(cos(radians(" . (float)$latitude . ")) * 
                        cos(radians(s.latitude)) * 
                        cos(radians(s.longitude) - radians(" . (float)$longitude . ")) + 
                        sin(radians(" . (float)$latitude . ")) * 
                        sin(radians(s.latitude)))) AS distance";
        }
        
        $simple_products_sql .= " FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        LEFT JOIN stores s ON p.store_id = s.id 
                        LEFT JOIN reviews r ON p.id = r.product_id
                        LEFT JOIN product_likes l ON p.id = l.product_id
                        WHERE p.status = 'active' AND s.status = 'active'";
                        
        if ($use_location && $latitude && $longitude) {
            // إضافة شرط للمتاجر التي لديها إحداثيات وضمن مسافة 10 كم
            $simple_products_sql .= " AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL";
            
            // إضافة شرط للمسافة (ضمن 5 كم فقط)
            $simple_products_sql .= " HAVING distance <= 5";
        }
                        
        if (!empty($search)) {
            $simple_products_sql .= " AND (p.name LIKE '%" . $conn->real_escape_string($search) . "%' 
                                OR p.description LIKE '%" . $conn->real_escape_string($search) . "%' 
                                OR s.name LIKE '%" . $conn->real_escape_string($search) . "%')";
        }
        
        if (!empty($category_id)) {
            $simple_products_sql .= " AND p.category_id = " . (int)$category_id;
        }
        
        $simple_products_sql .= " GROUP BY p.id";
        
        // إضافة الترتيب
        if ($use_location && $latitude && $longitude && $sort == 'nearest') {
            // ترتيب حسب المسافة إذا تم تحديد الموقع واختيار الترتيب حسب الأقرب
            $simple_products_sql .= " ORDER BY distance ASC";
        } else {
            switch ($sort) {
                case 'price_asc':
                    $simple_products_sql .= " ORDER BY p.price ASC";
                    break;
                case 'price_desc':
                    $simple_products_sql .= " ORDER BY p.price DESC";
                    break;
                case 'rating':
                    $simple_products_sql .= " ORDER BY avg_rating DESC";
                    break;
                case 'popular':
                    $simple_products_sql .= " ORDER BY likes_count DESC";
                    break;
                case 'newest':
                default:
                    // إذا تم تحديد الموقع ولم يتم تحديد نوع ترتيب محدد، نرتب حسب المسافة
                    if ($use_location && $latitude && $longitude) {
                        $simple_products_sql .= " ORDER BY distance ASC";
                    } else {
                        $simple_products_sql .= " ORDER BY p.created_at DESC";
                    }
                    break;
            }
        }
        
        // إضافة حدود الصفحة
        $simple_products_sql .= " LIMIT " . (int)$items_per_page . " OFFSET " . (int)$offset;
        
        $products_result = $conn->query($simple_products_sql);
        
        if ($products_result === false) {
            error_log("Error in simple products query: " . $conn->error);
            error_log("SQL: " . $simple_products_sql);
            $products = [];
        }
    } catch (Exception $e) {
        error_log("Exception in products query: " . $e->getMessage());
        $products_result = false;
        $products = [];
    }
    
    if ($products_result !== false) {
        while ($product = $products_result->fetch_assoc()) {
            $products[] = $product;
        }
    }
}

// البحث في المتاجر
if ($view_type == 'all' || $view_type == 'stores') {
    try {
        // إنشاء استعلام للمتاجر مع حساب المسافة
        $simple_stores_sql = "SELECT s.*, 
                       COUNT(DISTINCT p.id) as products_count,
                       COUNT(DISTINCT CASE 
                           WHEN o.id IS NOT NULL 
                           AND o.start_date <= CURDATE()
                           AND o.end_date >= CURDATE()
                           AND o.status = 'active'
                           THEN o.id
                       END) as active_offers_count";
                       
        // إضافة حساب المسافة إذا تم تحديد الموقع
        if ($use_location && $latitude && $longitude) {
            $simple_stores_sql .= ",
                       (6371 * acos(cos(radians(" . (float)$latitude . ")) * 
                       cos(radians(s.latitude)) * 
                       cos(radians(s.longitude) - radians(" . (float)$longitude . ")) + 
                       sin(radians(" . (float)$latitude . ")) * 
                       sin(radians(s.latitude)))) AS distance";
        }
        
        $simple_stores_sql .= " FROM stores s
                       LEFT JOIN products p ON s.id = p.store_id AND p.status = 'active'
                       LEFT JOIN offers o ON s.id = o.store_id
                           AND o.start_date <= CURDATE()
                           AND o.end_date >= CURDATE()
                           AND o.status = 'active'
                       WHERE s.status = 'active'";
        
        if ($use_location && $latitude && $longitude) {
            // إضافة شرط للمتاجر التي لديها إحداثيات وضمن مسافة 10 كم
            $simple_stores_sql .= " AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL";
            
            // إضافة شرط للمسافة (ضمن 5 كم فقط)
            $simple_stores_sql .= " HAVING distance <= 5";
        }
        
        if (!empty($search)) {
            $simple_stores_sql .= " AND (s.name LIKE '%" . $conn->real_escape_string($search) . "%' 
                                OR s.description LIKE '%" . $conn->real_escape_string($search) . "%')";
        }
        
        $simple_stores_sql .= " GROUP BY s.id";
        
        // إضافة الترتيب
        if ($use_location && $latitude && $longitude) {
            // إذا تم تحديد الموقع، نرتب حسب المسافة
            $simple_stores_sql .= " ORDER BY distance ASC";
        } else {
            $simple_stores_sql .= " ORDER BY s.name ASC";
        }
        
        // إجمالي عدد المتاجر (للترقيم)
        $simple_count_stores_sql = "SELECT COUNT(DISTINCT s.id) as total FROM stores s WHERE s.status = 'active'";
        
        if ($use_location && $latitude && $longitude) {
            $simple_count_stores_sql .= " AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL";
        }
        
        if (!empty($search)) {
            $simple_count_stores_sql .= " AND (s.name LIKE '%" . $conn->real_escape_string($search) . "%' 
                                OR s.description LIKE '%" . $conn->real_escape_string($search) . "%')";
        }
        
        $count_stores_result = $conn->query($simple_count_stores_sql);
        if ($count_stores_result) {
            $stores_count = $count_stores_result->fetch_assoc()['total'];
        } else {
            error_log("Error in simple count stores query: " . $conn->error);
            $stores_count = 0;
        }
        
        // إضافة حدود للصفحة الحالية
        $simple_stores_sql .= " LIMIT " . (int)$items_per_page . " OFFSET " . (int)$offset;
        
        $stores_result = $conn->query($simple_stores_sql);
        
        if ($stores_result === false) {
            error_log("Error in simple stores query: " . $conn->error);
            error_log("SQL: " . $simple_stores_sql);
            $stores = [];
        } else {
            while ($store = $stores_result->fetch_assoc()) {
                $stores[] = $store;
            }
        }
    } catch (Exception $e) {
        error_log("Exception in stores query: " . $e->getMessage());
        $stores_result = false;
        $stores = [];
    }
}

// إجمالي عدد النتائج
$total_results = $products_count + $stores_count;

// حساب عدد الصفحات
$total_pages = ceil($total_results / $items_per_page);

// تعيين عنوان الصفحة
$page_title = 'نتائج البحث: ' . htmlspecialchars($search);

// تحديد مسار الجذر
$root_path = '../';

// تضمين الهيدر الداكن
include_once('../includes/dark_header.php');
?>

<!-- تضمين ملف تنسيقات صفحة البحث مع منع التخزين المؤقت -->
<link rel="stylesheet" href="styles/search-page.css?v=<?php echo time(); ?>">

<!-- تنسيقات مباشرة للأزرار لضمان ظهورها بشكل صحيح -->
<style>
    /* تنسيقات الأزرار الرئيسية */
    .btn-primary {
        background-color: #333 !important;
        border-color: #333 !important;
        color: #fff !important;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        background-color: #555 !important;
        border-color: #555 !important;
    }
    
    .btn-primary:active, .btn-primary:focus {
        background-color: #FF7A00 !important;
        border-color: #FF7A00 !important;
        box-shadow: 0 0 0 0.25rem rgba(255, 122, 0, 0.25) !important;
    }
    
    /* تنسيقات أزرار الفلترة والترتيب */
    .mobile-filter-button, .mobile-sort-button {
        background-color: #333 !important;
        color: #fff !important;
        border: none !important;
        transition: all 0.3s ease;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
    }
    
    .mobile-filter-button:hover, .mobile-sort-button:hover {
        background-color: #555 !important;
    }
    
    .mobile-filter-button:active, .mobile-sort-button:active,
    .mobile-filter-button:focus, .mobile-sort-button:focus {
        background-color: #FF7A00 !important;
        color: #fff !important;
        box-shadow: 0 0 0 0.25rem rgba(255, 122, 0, 0.25) !important;
    }
    
    /* تنسيقات زر زيارة المتجر */
    .btn-outline-primary {
        color: #333 !important;
        border-color: #333 !important;
        background-color: transparent !important;
        transition: all 0.3s ease;
    }
    
    .btn-outline-primary:hover {
        background-color: #f8f9fa !important;
        color: #333 !important;
        border-color: #333 !important;
    }
    
    .btn-outline-primary:active, .btn-outline-primary:focus {
        background-color: #FF7A00 !important;
        border-color: #FF7A00 !important;
        color: #fff !important;
        box-shadow: 0 0 0 0.25rem rgba(255, 122, 0, 0.25) !important;
    }
</style>

<!-- تنسيقات إضافية للنوافذ المنبثقة على الأجهزة المحمولة -->
<style>
    /* طبقة التغطية للنوافذ المنبثقة */
    .mobile-filter-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .mobile-filter-overlay.show {
        display: block;
        opacity: 1;
    }
    
    /* نافذة الفلاتر المنبثقة */
    .mobile-filter-modal, .mobile-sort-modal {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background-color: #fff;
        border-radius: 15px 15px 0 0;
        z-index: 1001;
        transform: translateY(100%);
        transition: transform 0.3s ease;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .mobile-filter-modal.show, .mobile-sort-modal.show {
        transform: translateY(0);
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }
    
    .modal-header h5 {
        margin: 0;
        font-weight: 600;
    }
    
    .close-modal {
        background: none;
        border: none;
        font-size: 1.2rem;
        color: #6c757d;
        cursor: pointer;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-footer {
        padding: 1rem;
        border-top: 1px solid #dee2e6;
    }
    
    /* خيارات الترتيب */
    .sort-options {
        display: flex;
        flex-direction: column;
    }
    
    .sort-option {
        padding: 1rem;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
    }
    
    .sort-option:last-child {
        border-bottom: none;
    }
    
    .sort-option.active {
        color: #FF7A00;
        font-weight: 500;
    }
</style>

<!-- قسم رأس البحث -->
<section class="search-header">
    <div class="container">
        <h1 class="search-title">نتائج البحث: "<?php echo htmlspecialchars($search); ?>"</h1>
        <p class="search-info">تم العثور على <?php echo $total_results; ?> نتيجة</p>
        
        <!-- علامات تبويب البحث -->
        <div class="search-tabs">
            <a href="search.php?search=<?php echo urlencode($search); ?>&view=all<?php echo !empty($category_id) ? '&category=' . $category_id : ''; ?>&sort=<?php echo $sort; ?>" class="search-tab <?php echo $view_type == 'all' ? 'active' : ''; ?>">
                الكل <span class="badge"><?php echo $total_results; ?></span>
            </a>
            <a href="search.php?search=<?php echo urlencode($search); ?>&view=products<?php echo !empty($category_id) ? '&category=' . $category_id : ''; ?>&sort=<?php echo $sort; ?>" class="search-tab <?php echo $view_type == 'products' ? 'active' : ''; ?>">
                المنتجات <span class="badge"><?php echo $products_count; ?></span>
            </a>
            <a href="search.php?search=<?php echo urlencode($search); ?>&view=stores<?php echo !empty($category_id) ? '&category=' . $category_id : ''; ?>&sort=<?php echo $sort; ?>" class="search-tab <?php echo $view_type == 'stores' ? 'active' : ''; ?>">
                المتاجر <span class="badge"><?php echo $stores_count; ?></span>
            </a>
        </div>
    </div>
</section>

<div class="container mb-5">
    <div class="row">
        <!-- شريط الفلاتر للأجهزة المحمولة -->
        <div class="d-lg-none mb-4">
            <div class="mobile-filter-bar">
                <button type="button" class="mobile-filter-button" id="mobileFilterButton">
                    <i class="bi bi-funnel"></i> فلترة
                </button>
                <?php if ($view_type == 'all' || $view_type == 'products'): ?>
                <button type="button" class="mobile-sort-button" id="mobileSortButton">
                    <i class="bi bi-sort-down"></i> ترتيب
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- قسم الفلاتر -->
        <div class="col-lg-3 mb-4 d-none d-lg-block">
            <div class="search-filters">
                <h4 class="filter-title">
                    فلترة النتائج
                    <button type="button" class="filter-toggle" id="filterToggle">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </h4>
                
                <div class="filter-content" id="filterContent">
                    <!-- فلتر التصنيفات -->
                    <?php if ($view_type == 'all' || $view_type == 'products'): ?>
                    <div class="filter-group">
                        <label class="filter-label">التصنيف</label>
                        <select class="sort-select" id="categoryFilter" onchange="applyFilters()">
                            <option value="">جميع التصنيفات</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- فلتر الترتيب -->
                    <?php if ($view_type == 'all' || $view_type == 'products'): ?>
                    <div class="filter-group">
                        <label class="filter-label">ترتيب حسب</label>
                        <select class="sort-select" id="sortFilter" onchange="applyFilters()">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>الأحدث</option>
                            <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>السعر: من الأقل للأعلى</option>
                            <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>السعر: من الأعلى للأقل</option>
                            <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>التقييم</option>
                            <?php if ($use_location && $latitude && $longitude): ?>
                            <option value="distance" <?php echo $sort == 'distance' ? 'selected' : ''; ?>>الأقرب إليك</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- زر تطبيق الفلاتر -->
                    <div class="mt-3">
                        <button class="btn btn-primary w-100" onclick="applyFilters()">تطبيق الفلاتر</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- قسم النتائج -->
        <div class="col-lg-9 search-results-container">
            <?php if (isset($location_error)): ?>
                <!-- رسالة خطأ الموقع -->
                <div class="no-results">
                    <i class="bi bi-geo-alt-fill"></i>
                    <h3>خطأ في تحديد الموقع</h3>
                    <p><?php echo $location_error; ?></p>
                    <button class="btn btn-primary mt-3" onclick="getLocation()">تحديد موقعي مرة أخرى</button>
                </div>
            <?php elseif (empty($search) && !$use_location): ?>
                <!-- رسالة إدخال كلمة بحث -->
                <div class="no-results">
                    <i class="bi bi-search"></i>
                    <h3>يرجى إدخال كلمة بحث</h3>
                    <p>قم بإدخال كلمة بحث في شريط البحث أعلاه للعثور على المنتجات والمتاجر.</p>
                </div>
            <?php elseif ($total_results == 0): ?>
                <!-- رسالة عدم وجود نتائج -->
                <div class="no-results">
                    <i class="bi bi-emoji-frown"></i>
                    <h3>لم يتم العثور على نتائج</h3>
                    <p>لم نتمكن من العثور على أي نتائج تطابق بحثك "<?php echo htmlspecialchars($search); ?>". يرجى تجربة كلمات بحث أخرى أو تصفية مختلفة.</p>
                </div>
            <?php else: ?>
                <!-- عرض نتائج المنتجات -->
                <?php if (($view_type == 'all' || $view_type == 'products') && count($products) > 0): ?>
                    <h2 class="section-title mb-4">المنتجات</h2>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-5">
                        <?php foreach ($products as $product): ?>
                            <div class="col">
                                <div class="card h-100 product-card <?php echo ($use_location && $latitude && $longitude && isset($product['distance']) && $product['distance'] <= 10) ? 'nearby-product' : ''; ?>">
                                    <?php if ($use_location && $latitude && $longitude && isset($product['distance']) && $product['distance'] <= 10): ?>
                                    <div class="nearby-badge">
                                        <i class="bi bi-geo-alt"></i> قريب منك
                                    </div>
                                    <?php endif; ?>
                                    <a href="product-details.php?id=<?php echo $product['id']; ?>" class="product-link">
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                 class="card-img-top product-image" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                                 style="height: 200px;">
                                                <i class="bi bi-image text-secondary" style="font-size: 4rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h5>
                                        <p class="card-text small text-muted">
                                            <?php echo htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : ''); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <span class="fw-bold text-primary"><?php echo number_format($product['price'], 2); ?> ريال</span>
                                            <a href="store-page.php?id=<?php echo $product['store_id']; ?>" class="text-decoration-none">
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($product['store_name']); ?></span>
                                            </a>
                                        </div>
                                        <div class="product-meta mt-3">
                                            <div class="rating-pill">
                                                <span class="value"><?php echo number_format($product['avg_rating'], 1); ?></span>
                                                <div class="stars-wrap">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= floor($product['avg_rating'])): ?>
                                                            <i class="bi bi-star-fill"></i>
                                                        <?php elseif ($i - 0.5 <= $product['avg_rating']): ?>
                                                            <i class="bi bi-star-half"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                                <?php if ($use_location && $latitude && $longitude && isset($product['distance'])): ?>
                                                <div class="distance-pill">
                                                    <i class="bi bi-geo-alt"></i>
                                                    <span><?php echo number_format($product['distance'], 1); ?> كم</span>
                                                </div>
                                                <?php endif; ?>
                                                <span class="meta-count">(<?php echo $product['rating_count']; ?>)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- عرض نتائج المتاجر -->
                <?php if (($view_type == 'all' || $view_type == 'stores') && count($stores) > 0): ?>
                    <h2 class="section-title mb-4">المتاجر</h2>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-5">
                        <?php foreach ($stores as $store): ?>
                            <div class="col">
                                <div class="card h-100 store-card">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <?php if (!empty($store['logo'])): ?>
                                                <img src="../uploads/stores/<?php echo htmlspecialchars($store['logo']); ?>" 
                                                     class="store-logo rounded-circle" 
                                                     alt="<?php echo htmlspecialchars($store['name']); ?>"
                                                     style="width: 100px; height: 100px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="store-logo-placeholder rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto"
                                                     style="width: 100px; height: 100px;">
                                                    <i class="bi bi-shop text-secondary" style="font-size: 2.5rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <h5 class="card-title">
                                            <a href="store-page.php?id=<?php echo $store['id']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($store['name']); ?>
                                            </a>
                                        </h5>
                                        <p class="card-text small text-muted">
                                            <?php echo htmlspecialchars(substr($store['description'], 0, 100)) . (strlen($store['description']) > 100 ? '...' : ''); ?>
                                        </p>
                                        <div class="store-stats d-flex justify-content-center gap-3 mt-3">
                                            <div class="stat-badge">
                                                <i class="bi bi-box-seam text-primary me-1"></i>
                                                <span><?php echo $store['products_count']; ?> منتج</span>
                                            </div>
                                            <?php if ($store['active_offers_count'] > 0): ?>
                                                <div class="stat-badge">
                                                    <i class="bi bi-tags text-success me-1"></i>
                                                    <span><?php echo $store['active_offers_count']; ?> عرض</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent border-top-0 text-center">
                                        <a href="store-page.php?id=<?php echo $store['id']; ?>" class="btn btn-outline-primary">زيارة المتجر</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- ترقيم الصفحات -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="search.php?search=<?php echo urlencode($search); ?>&view=<?php echo $view_type; ?><?php echo !empty($category_id) ? '&category=' . $category_id : ''; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="search.php?search=<?php echo urlencode($search); ?>&view=<?php echo $view_type; ?><?php echo !empty($category_id) ? '&category=' . $category_id : ''; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="search.php?search=<?php echo urlencode($search); ?>&view=<?php echo $view_type; ?><?php echo !empty($category_id) ? '&category=' . $category_id : ''; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- تضمين سكريبت صفحة البحث -->
<script src="js/search-page.js"></script>

<!-- سكريبت تطبيق الفلاتر -->
<script>
    function applyFilters() {
        const searchParams = new URLSearchParams(window.location.search);
        const category = document.getElementById('categoryFilter')?.value;
        const sort = document.getElementById('sortFilter')?.value;
        
        if (category) {
            searchParams.set('category', category);
        } else {
            searchParams.delete('category');
        }
        
        if (sort) {
            searchParams.set('sort', sort);
        }
        
        // إعادة تعيين رقم الصفحة عند تغيير الفلاتر
        searchParams.set('page', '1');
        
        window.location.href = `search.php?${searchParams.toString()}`;
    }
</script>

<!-- كود جافاسكريبت لتحديد الموقع -->
<script>
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const location = position.coords.latitude + ',' + position.coords.longitude;
            // توجيه المستخدم إلى صفحة تعيين الموقع
            window.location.href = 'set_location.php?location=' + encodeURIComponent(location) + '&search=<?php echo urlencode($search); ?>&view=<?php echo $view_type; ?>';
        }, function(error) {
            alert('عذراً، لم نتمكن من تحديد موقعك. الرجاء المحاولة مرة أخرى.');
        });
    } else {
        alert('عذراً، متصفحك لا يدعم تحديد الموقع.');
    }
}
</script>

<?php include_once('../includes/footer.php'); ?>
