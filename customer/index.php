<?php
session_start();

// تضمين الملفات المطلوبة
require_once '../includes/init.php';
require_once '../includes/functions.php';

// التأكد من تسجيل الدخول
if (!isset($_SESSION['customer_id'])) {
    header('Location: /marketplace/customer/login.php');
    exit();
}

// التحقق من حالة العميل
$stmt = $conn->prepare("SELECT status FROM customers WHERE id = ?");
$stmt->bind_param("i", $_SESSION['customer_id']);
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

// الحصول على معايير البحث
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : '';
$current_location = isset($_SESSION['current_location']) ? $_SESSION['current_location'] : '';
$view_type = isset($_GET['view']) ? $_GET['view'] : 'products'; // إضافة متغير view_type

// طباعة قيم البحث للتصحيح
echo "<!-- GET Parameters: ";
print_r($_GET);
echo " -->";
echo "<!-- Search value: " . htmlspecialchars($search) . " -->";

// جلب التصنيفات
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);

// تحضير استعلام المنتجات مع العروض
$products_sql = "SELECT p.*, 
                 s.name as store_name, 
                 s.address as store_address, 
                 s.city as store_city,
                 c.name as category_name,
                 o.id as offer_id, 
                 o.title as offer_title, 
                 o.discount_percentage, 
                 o.start_date,
                 o.end_date,
                 o.status as offer_status,
                 oi.name as offer_item_name,
                 oi.price as offer_item_price,
                 oi.image_url as offer_item_image,
                 CASE 
                    WHEN o.id IS NOT NULL 
                    AND o.status = 'active'
                    AND o.start_date <= CURRENT_DATE()
                    AND o.end_date >= CURRENT_DATE()
                    THEN ROUND(COALESCE(oi.price, p.price) - (COALESCE(oi.price, p.price) * o.discount_percentage / 100), 2)
                    ELSE p.price 
                 END as final_price
                 FROM products p
                 INNER JOIN stores s ON p.store_id = s.id
                 LEFT JOIN categories c ON p.category_id = c.id
                 LEFT JOIN offer_items oi ON p.id = oi.product_id
                 LEFT JOIN offers o ON oi.offer_id = o.id 
                    AND o.store_id = p.store_id 
                    AND o.status = 'active'
                    AND o.start_date <= CURRENT_DATE()
                    AND o.end_date >= CURRENT_DATE()
                 WHERE p.status = 'active' AND s.status = 'active'";

// إضافة شروط البحث إذا وجدت
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $products_sql .= " AND (p.name LIKE ? OR s.name LIKE ? OR c.name LIKE ?)";
}

// إضافة شروط التصنيف إذا وجدت
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $products_sql .= " AND p.category_id = ?";
}

// ترتيب النتائج
$products_sql .= " ORDER BY 
    CASE 
        WHEN o.id IS NOT NULL 
        AND o.status = 'active'
        AND o.start_date <= CURRENT_DATE()
        AND o.end_date >= CURRENT_DATE()
        THEN 0 
        ELSE 1 
    END, 
    p.created_at DESC";

// تحضير وتنفيذ الاستعلام
$stmt = $conn->prepare($products_sql);

if (isset($_GET['search']) && !empty($_GET['search'])) {
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $stmt->bind_param("sssi", $search_term, $search_term, $search_term, $_GET['category']);
    } else {
        $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    }
} elseif (isset($_GET['category']) && !empty($_GET['category'])) {
    $stmt->bind_param("i", $_GET['category']);
}

$stmt->execute();
$products_result = $stmt->get_result();

// استعلام المتاجر
$stores_sql = "SELECT s.*, 
               COUNT(DISTINCT p.id) as products_count,
               COUNT(DISTINCT CASE 
                   WHEN o.id IS NOT NULL 
                   AND o.start_date <= CURDATE()
                   AND o.end_date >= CURDATE()
                   AND o.status = 'active'
                   THEN o.id
               END) as offers_count
               FROM stores s
               LEFT JOIN products p ON s.id = p.store_id
               LEFT JOIN offers o ON s.id = o.store_id
                   AND o.start_date <= CURDATE()
                   AND o.end_date >= CURDATE()
                   AND o.status = 'active'";

if (!empty($search)) {
    $stores_sql .= " WHERE s.status = 'active' AND (s.name LIKE ? OR s.city LIKE ? OR p.name LIKE ?)";
} else {
    $stores_sql .= " WHERE s.status = 'active'";
}

$stores_sql .= " GROUP BY s.id ORDER BY offers_count DESC, s.created_at DESC";

$stores_stmt = $conn->prepare($stores_sql);

if ($stores_stmt === false) {
    die('خطأ في إعداد استعلام المتاجر: ' . $conn->error);
}

// تحضير المعاملات لاستعلام المتاجر
$store_params = [];
$store_types = '';

if (!empty($search)) {
    $store_params[] = "%$search%";
    $store_params[] = "%$search%";
    $store_params[] = "%$search%";
    $store_types .= 'sss';
}

if (!empty($store_params)) {
    $stores_stmt->bind_param($store_types, ...$store_params);
}

// تنفيذ الاستعلام
if (!$stores_stmt->execute()) {
    die('خطأ في تنفيذ استعلام المتاجر: ' . $stores_stmt->error);
}

$stores_result = $stores_stmt->get_result();

if ($stores_result === false) {
    die('خطأ في الحصول على نتائج المتاجر: ' . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المنتجات - السوق الإلكتروني</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
    .search-section {
        background: linear-gradient(135deg, #007bff, #6610f2);
        padding: 2rem 0;
        margin-bottom: 2rem;
        color: white;
    }
    .product-card {
        height: 100%;
        transition: transform 0.2s;
    }
    .product-card:hover {
        transform: translateY(-5px);
    }
    .store-card {
        height: 100%;
        transition: transform 0.2s;
    }
    .store-card:hover {
        transform: translateY(-5px);
    }
    .offer-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 2;
    }
    .original-price {
        text-decoration: line-through;
        color: #6c757d;
        font-size: 0.9em;
    }
    </style>
</head>
<body>
    <?php include '../includes/customer_navbar.php'; ?>

    <!-- قسم البحث -->
    <section class="search-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <form action="" method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="ابحث عن منتج أو متجر..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <select name="category" class="form-select" style="max-width: 150px;">
                                <option value="">كل التصنيفات</option>
                                <?php while ($category = $categories_result->fetch_assoc()): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <select name="view" class="form-select" style="max-width: 120px;">
                                <option value="products" <?php echo ($view_type === 'products') ? 'selected' : ''; ?>>
                                    المنتجات
                                </option>
                                <option value="stores" <?php echo ($view_type === 'stores') ? 'selected' : ''; ?>>
                                    المتاجر
                                </option>
                            </select>
                            <button type="submit" class="btn btn-light">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- زر استخدام موقعي -->
            <div class="text-center mt-3">
                <button type="button" class="btn btn-outline-light" onclick="getLocation()">
                    <i class="bi bi-geo-alt"></i> عرض المنتجات والمتاجر القريبة مني
                </button>
                <?php if (!empty($current_location)): ?>
                    <div class="mt-2">
                        <small class="text-white">
                            <i class="bi bi-info-circle"></i>
                            يتم عرض المنتجات والمتاجر حسب موقعك الحالي
                        </small>
                        <button type="button" class="btn btn-sm btn-link text-white" onclick="clearLocation()">
                            <i class="bi bi-x-circle"></i> إلغاء تحديد الموقع
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="container">
        <?php if ($view_type === 'products'): ?>
            <!-- عرض المنتجات -->
            <?php if ($products_result->num_rows > 0): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                    <?php while ($product = $products_result->fetch_assoc()): ?>
                        <div class="col">
                            <div class="card h-100 product-card">
                                <?php if (!empty($product['offer_id'])): ?>
                                    <div class="offer-badge">
                                        <span class="badge bg-danger">
                                            <?php echo htmlspecialchars($product['offer_title']); ?> - 
                                            خصم <?php echo $product['discount_percentage']; ?>%
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         style="height: 200px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 200px;">
                                        <i class="bi bi-image text-secondary" style="font-size: 4rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    
                                    <p class="card-text text-muted">
                                        <i class="bi bi-shop"></i> <?php echo htmlspecialchars($product['store_name']); ?>
                                    </p>
                                    
                                    <?php if (!empty($product['category_name'])): ?>
                                        <p class="card-text">
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php if (!empty($product['offer_id'])): ?>
                                                <span class="text-danger fw-bold">
                                                    <?php echo number_format($product['final_price'], 2); ?> ريال
                                                </span>
                                                <br>
                                                <small class="text-decoration-line-through text-muted">
                                                    <?php echo number_format($product['price'], 2); ?> ريال
                                                </small>
                                            <?php else: ?>
                                                <span class="fw-bold">
                                                    <?php echo number_format($product['price'], 2); ?> ريال
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <a href="store-page.php?id=<?php echo $product['store_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            زيارة المتجر
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    لا توجد منتجات متاحة حالياً
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- عرض المتاجر -->
            <h2 class="mb-4">المتاجر</h2>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php while ($store = $stores_result->fetch_assoc()): ?>
                    <div class="col">
                        <div class="card store-card h-100">
                            <?php if (!empty($store['logo'])): ?>
                                <img src="../uploads/stores/<?php echo htmlspecialchars($store['logo']); ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($store['name']); ?>"
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 200px;">
                                    <i class="bi bi-shop text-secondary" style="font-size: 4rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($store['name']); ?></h5>
                                <?php if (!empty($store['address'])): ?>
                                    <p class="card-text text-muted">
                                        <i class="bi bi-geo-alt"></i>
                                        <?php echo htmlspecialchars($store['address']); ?>
                                        <?php if (!empty($store['city'])): ?>
                                            ، <?php echo htmlspecialchars($store['city']); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <p class="card-text">
                                    <?php echo nl2br(htmlspecialchars($store['description'])); ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <span class="badge bg-primary">
                                            <?php echo $store['products_count']; ?> منتج
                                        </span>
                                        <?php if ($store['offers_count'] > 0): ?>
                                            <span class="badge bg-danger">
                                                <?php echo $store['offers_count']; ?> عرض نشط
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="store-page.php?id=<?php echo $store['id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        زيارة المتجر
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <?php if ($stores_result->num_rows === 0): ?>
                <div class="alert alert-info text-center">
                    لا توجد متاجر متاحة حالياً
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
    function getLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const location = position.coords.latitude + ',' + position.coords.longitude;
                // تحويل العرض تلقائياً إلى المتاجر عند تحديد الموقع
                window.location.href = 'set_location.php?location=' + encodeURIComponent(location) + '&view=stores';
            }, function(error) {
                alert('عذراً، لم نتمكن من تحديد موقعك. الرجاء المحاولة مرة أخرى.');
            });
        } else {
            alert('عذراً، متصفحك لا يدعم تحديد الموقع.');
        }
    }

    function clearLocation() {
        window.location.href = 'set_location.php?clear=1';
    }

    function openMap(address) {
        if (address) {
            const mapUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address);
            window.open(mapUrl, '_blank');
        }
    }
    </script>
</body>
</html>