<?php
session_start();
require_once '../includes/init.php';

// التحقق من وجود معرف المتجر
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$store_id = (int)$_GET['id'];
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// جلب تفاصيل المتجر
$store_sql = "SELECT s.*, 
              COUNT(DISTINCT p.id) as products_count,
              COUNT(DISTINCT CASE 
                  WHEN o.id IS NOT NULL 
                  AND o.start_date <= CURDATE()
                  AND o.end_date >= CURDATE()
                  AND o.status = 'active'
                  THEN o.id
              END) as active_offers_count,
              s.phone,
              s.address,
              s.city
              FROM stores s
              LEFT JOIN products p ON s.id = p.store_id
              LEFT JOIN offers o ON s.id = o.store_id
                  AND o.start_date <= CURDATE()
                  AND o.end_date >= CURDATE()
                  AND o.status = 'active'
              WHERE s.id = ? AND s.status = 'active'
              GROUP BY s.id";

$store_stmt = $conn->prepare($store_sql);
$store_stmt->bind_param("i", $store_id);
$store_stmt->execute();
$store = $store_stmt->get_result()->fetch_assoc();

if (!$store) {
    header("Location: index.php");
    exit();
}

// جلب المنتجات مع البحث
$products_sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.store_id = ? AND p.status = 'active'";
$params = array($store_id);
$types = "i";

if (!empty($search_query)) {
    $products_sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$products_sql .= " ORDER BY p.created_at DESC";
$products_stmt = $conn->prepare($products_sql);
$products_stmt->bind_param($types, ...$params);
$products_stmt->execute();
$products_result = $products_stmt->get_result();

// جلب العروض النشطة للمتجر
$active_offers_sql = "SELECT o.*, 
                     COUNT(DISTINCT p.id) as products_count
                     FROM offers o
                     LEFT JOIN products p ON o.store_id = p.store_id
                     WHERE o.store_id = ? 
                     AND o.status = 'active'
                     AND o.start_date <= CURDATE()
                     AND o.end_date >= CURDATE()
                     GROUP BY o.id
                     ORDER BY o.created_at DESC";

$active_offers_stmt = $conn->prepare($active_offers_sql);
$active_offers_stmt->bind_param("i", $store_id);
$active_offers_stmt->execute();
$active_offers_result = $active_offers_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store['name']); ?> - السوق</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .store-header {
            background: linear-gradient(135deg, #007bff, #6610f2);
            padding: 3rem 0;
            margin-bottom: 2rem;
            color: white;
        }
        .search-form {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 0.5rem;
            backdrop-filter: blur(10px);
        }
        .search-form .form-control {
            background: rgba(255, 255, 255, 0.9);
        }
        .search-form .form-control:focus {
            background: white;
        }
        .search-results {
            margin-top: 1rem;
            color: rgba(255, 255, 255, 0.9);
        }
        .product-card {
            height: 100%;
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
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
        .product-image-container {
            position: relative;
            width: 100%;
            padding-top: 100%;
            overflow: hidden;
            background-color: #f8f9fa;
        }
        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 10px;
        }
        .offer-image-container {
            position: relative;
            width: 100%;
            padding-top: 75%;
            overflow: hidden;
            background-color: #f8f9fa;
        }
        .offer-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 15px;
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_navbar.php'; ?>

    <!-- رأس صفحة المتجر -->
    <section class="store-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center mb-3">
                    <?php if (!empty($store['logo'])): ?>
                        <img src="/uploads/stores/<?php echo htmlspecialchars($store['logo']); ?>" 
                             alt="<?php echo htmlspecialchars($store['name']); ?>" 
                             class="img-fluid rounded-circle" 
                             style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                             style="width: 150px; height: 150px;">
                            <i class="bi bi-shop text-primary" style="font-size: 4rem;"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-8">
                            <h1 class="display-4 mb-3"><?php echo htmlspecialchars($store['name']); ?></h1>
                            <?php if (!empty($store['description'])): ?>
                                <p class="lead mb-3"><?php echo htmlspecialchars($store['description']); ?></p>
                            <?php endif; ?>
                            <div class="store-info mb-4">
                                <?php if (!empty($store['phone'])): ?>
                                    <p class="mb-2">
                                        <i class="bi bi-telephone-fill me-2 text-white"></i>
                                        <a href="tel:<?php echo htmlspecialchars($store['phone']); ?>" class="text-white text-decoration-none">
                                            <?php echo htmlspecialchars($store['phone']); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($store['address'])): ?>
                                    <p class="mb-2">
                                        <i class="bi bi-geo-alt-fill me-2 text-white"></i>
                                        <?php echo htmlspecialchars($store['address']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($store['city'])): ?>
                                    <p class="mb-2">
                                        <i class="bi bi-building me-2 text-white"></i>
                                        <?php echo htmlspecialchars($store['city']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="store-stats mb-3">
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-box me-1"></i>
                                    <?php echo $store['products_count']; ?> منتج
                                </span>
                                <?php if ($store['active_offers_count'] > 0): ?>
                                    <span class="badge bg-danger ms-2">
                                        <i class="bi bi-tag me-1"></i>
                                        <?php echo $store['active_offers_count']; ?> عروض نشطة
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="search-form">
                                <form action="" method="GET" class="d-flex flex-column gap-2">
                                    <input type="hidden" name="id" value="<?php echo $store_id; ?>">
                                    <div class="input-group">
                                        <input type="search" name="search" class="form-control" 
                                               placeholder="ابحث في منتجات المتجر..." 
                                               value="<?php echo htmlspecialchars($search_query); ?>">
                                        <button type="submit" class="btn btn-light">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                    <?php if (!empty($search_query)): ?>
                                        <div class="search-results">
                                            <small>
                                                نتائج البحث عن: "<?php echo htmlspecialchars($search_query); ?>"
                                                <a href="?id=<?php echo $store_id; ?>" class="text-white ms-2">
                                                    <i class="bi bi-x-circle"></i> مسح البحث
                                                </a>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container mb-5">
        <!-- العروض النشطة -->
        <?php if ($active_offers_result->num_rows > 0): ?>
            <section class="mb-5">
                <h2 class="mb-4">العروض النشطة</h2>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php while ($offer = $active_offers_result->fetch_assoc()): ?>
                        <div class="col">
                            <div class="card h-100 product-card">
                                <div class="offer-image-container">
                                    <?php if (!empty($offer['image_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($offer['image_path']); ?>" 
                                             class="offer-image" alt="<?php echo htmlspecialchars($offer['title']); ?>">
                                    <?php else: ?>
                                        <div class="offer-image d-flex align-items-center justify-content-center">
                                            <i class="bi bi-tag text-secondary" style="font-size: 4rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($offer['title']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($offer['description']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-danger">خصم <?php echo $offer['discount_percentage']; ?>%</span>
                                            <?php if (isset($offer['offer_price'])): ?>
                                                <span class="badge bg-success ms-2"><?php echo number_format($offer['offer_price'], 2); ?> ريال</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            ينتهي في <?php echo date('d/m/Y', strtotime($offer['end_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($products_result->num_rows > 0): ?>
            <section>
                <h2 class="mb-4">المنتجات المتوفرة</h2>
                <div class="row row-cols-1 row-cols-md-4 g-4">
                    <?php while ($product = $products_result->fetch_assoc()): ?>
                        <div class="col">
                            <div class="card h-100">
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         style="height: 200px; object-fit: contain;">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 200px;">
                                        <i class="bi bi-image text-secondary" style="font-size: 4rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text">
                                        <?php if (!empty($product['description'])): ?>
                                            <?php echo htmlspecialchars($product['description']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-primary fs-5 fw-bold mb-0">
                                        <?php echo number_format($product['price'], 2); ?> ريال
                                    </p>
                                </div>
                                <?php if (!empty($product['category_name'])): ?>
                                <div class="card-footer">
                                    <small class="text-muted">
                                        التصنيف: <?php echo htmlspecialchars($product['category_name']); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php else: ?>
            <div class="alert alert-info">
                لا توجد منتجات متوفرة حالياً
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
