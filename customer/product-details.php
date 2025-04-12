<?php
session_start();
require_once '../includes/init.php';

// التحقق من وجود معرف المنتج
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$product_id = (int)$_GET['id'];

// جلب تفاصيل المنتج مع معلومات المتجر
$product_sql = "SELECT p.*, s.name as store_name, s.address as store_address, s.city as store_city,
                c.name as category_name,
                o.id as offer_id, o.discount_percentage, o.end_date,
                CASE 
                    WHEN o.id IS NOT NULL 
                    AND o.start_date <= NOW() 
                    AND o.end_date >= NOW() 
                    AND o.status = 'active'
                    THEN ROUND(p.price - (p.price * o.discount_percentage / 100), 2)
                    ELSE p.price 
                END as final_price
                FROM products p
                LEFT JOIN stores s ON p.store_id = s.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN (
                    SELECT DISTINCT store_id, offer_id 
                    FROM offer_store_products
                ) osp ON p.store_id = osp.store_id
                LEFT JOIN offers o ON osp.offer_id = o.id 
                    AND o.start_date <= NOW() 
                    AND o.end_date >= NOW()
                    AND o.status = 'active'
                WHERE p.id = ? AND p.status = 'active'";

$stmt = $conn->prepare($product_sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - السوق</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
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

    <div class="container my-5">
        <div class="row">
            <div class="col-md-6">
                <?php if (!empty($product['image'])): ?>
                    <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                         class="img-fluid rounded" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <?php else: ?>
                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                         style="height: 400px;">
                        <i class="bi bi-image text-secondary" style="font-size: 4rem;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h1 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <?php if (!empty($product['offer_id'])): ?>
                    <div class="mb-3">
                        <span class="badge bg-danger">
                            خصم <?php echo $product['discount_percentage']; ?>%
                        </span>
                        <div class="mt-2">
                            <span class="h3 text-danger">
                                <?php echo number_format($product['final_price'], 2); ?> ريال
                            </span>
                            <br>
                            <span class="original-price">
                                <?php echo number_format($product['price'], 2); ?> ريال
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <span class="h3">
                            <?php echo number_format($product['price'], 2); ?> ريال
                        </span>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <h5>التصنيف</h5>
                    <p><?php echo htmlspecialchars($product['category_name']); ?></p>
                </div>

                <div class="mb-3">
                    <h5>الوصف</h5>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>

                <div class="mb-3">
                    <h5>المتجر</h5>
                    <p>
                        <a href="store-page.php?id=<?php echo $product['store_id']; ?>" 
                           class="text-decoration-none">
                            <?php echo htmlspecialchars($product['store_name']); ?>
                        </a>
                        <?php if (!empty($product['store_address'])): ?>
                            <br>
                            <small class="text-muted">
                                <i class="bi bi-geo-alt"></i>
                                <a href="#" onclick="openMap('<?php echo htmlspecialchars($product['store_address'] . ', ' . $product['store_city']); ?>')" 
                                   class="text-decoration-none text-muted">
                                    <?php echo htmlspecialchars($product['store_address']); ?>
                                    <?php if (!empty($product['store_city'])): ?>
                                        ، <?php echo htmlspecialchars($product['store_city']); ?>
                                    <?php endif; ?>
                                </a>
                            </small>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function openMap(address) {
        if (address) {
            const mapUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address);
            window.open(mapUrl, '_blank');
        }
    }
    </script>
</body>
</html>
