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
    <title><?php echo htmlspecialchars($product['name'] ?? 'منتج غير موجود'); ?> | السوق الإلكتروني</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles/marketplace-modern.css">
    <link rel="stylesheet" href="styles/product-details.css">
    <style>
        /* أنماط أزرار التنقل في كاروسيل المنتج */
        .carousel-control-prev {
            right: 10px !important;
            left: auto !important;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 122, 0, 0.7);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .carousel-control-next {
            left: 10px !important;
            right: auto !important;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 122, 0, 0.7);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .carousel-control-prev-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='%23fff' viewBox='0 0 16 16'%3e%3cpath d='M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e") !important;
            width: 20px;
            height: 20px;
        }
        
        .carousel-control-next-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='%23fff' viewBox='0 0 16 16'%3e%3cpath d='M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z'/%3e%3c/svg%3e") !important;
            width: 20px;
            height: 20px;
        }
    </style>
    <style>
    .offer-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 2;
    }
    .offer-badge .badge {
        font-size: 0.9rem;
        padding: 8px 12px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .original-price {
        text-decoration: line-through;
        color: #6c757d;
        font-size: 0.9em;
    }
    .product-image {
        max-width: 100%;
        max-height: 400px;
        object-fit: contain;
    }
    .product-detail-wrapper {
        height: 450px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        position: relative;
        background-color: #f8f9fa;
    }
    .product-price-tag {
        position: absolute;
        bottom: 10px;
        right: 10px;
        background-color: #ff7a00;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-weight: bold;
    }
    </style>
</head>
<body>
    <!-- استدعاء الهيدر الداكن الجديد -->
    <?php 
    $root_path = '../';
    include '../includes/dark_header.php'; 
    ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-6">
                <!-- حاوية صورة المنتج مع الكاروسيل -->
                <div class="product-detail-wrapper rounded">
                    <!-- إضافة كاروسيل للصور -->
                    <div id="productDetailCarousel" class="carousel slide product-image-carousel" data-bs-ride="carousel">
                        <?php
                        // جلب صور المنتج من جدول product_images
                        $images_sql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY display_order ASC";
                        $images_stmt = $conn->prepare($images_sql);
                        $has_multiple_images = false;
                        $product_images = [];
                        
                        if ($images_stmt) {
                            $images_stmt->bind_param("i", $product_id);
                            $images_stmt->execute();
                            $images_result = $images_stmt->get_result();
                            $has_multiple_images = ($images_result->num_rows > 1); // أكثر من صورة واحدة
                            
                            // تخزين الصور في مصفوفة لاستخدامها لاحقًا
                            while ($image = $images_result->fetch_assoc()) {
                                $product_images[] = $image;
                            }
                        }
                        ?>
                        
                        <!-- مؤشرات الكاروسيل (النقاط) -->
                        <?php if ($has_multiple_images): ?>
                        <div class="carousel-indicators">
                            <?php foreach ($product_images as $index => $image): ?>
                                <button type="button" data-bs-target="#productDetailCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo ($index === 0) ? 'active' : ''; ?>" aria-current="<?php echo ($index === 0) ? 'true' : 'false'; ?>" aria-label="<?php echo 'شريحة ' . ($index + 1); ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- محتوى الكاروسيل -->
                        <div class="carousel-inner">
                            <?php if (count($product_images) > 0): ?>
                                <?php foreach ($product_images as $index => $image): ?>
                                    <div class="carousel-item <?php echo ($index === 0) ? 'active' : ''; ?>">
                                        <img 
                                            src="../<?php echo htmlspecialchars($image['image_url']); ?>" 
                                            alt="<?php echo htmlspecialchars($product['name'] ?? ''); ?>"
                                            class="product-image"
                                            onerror="this.src='../assets/images/default-product.jpg';"
                                        >
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- إذا لم تكن هناك صور متعددة، عرض الصورة الرئيسية -->
                                <div class="carousel-item active">
                                    <img 
                                        src="../<?php echo htmlspecialchars($product['image_url'] ?? ''); ?>" 
                                        alt="<?php echo htmlspecialchars($product['name'] ?? ''); ?>"
                                        class="product-image"
                                        onerror="this.src='../assets/images/default-product.jpg';"
                                    >
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- أزرار التنقل -->
                        <?php if ($has_multiple_images): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#productDetailCarousel" data-bs-slide="prev" 
                                style="position: absolute; right: 10px; left: auto; width: 35px; height: 35px; background-color: rgba(255, 153, 51, 0.9); border-radius: 50%; top: 50%; transform: translateY(-50%); opacity: 1; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2);" onclick="event.stopPropagation();">
                            <span class="carousel-control-prev-icon" aria-hidden="true" style="width: 20px; height: 20px;"></span>
                            <span class="visually-hidden">السابق</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#productDetailCarousel" data-bs-slide="next"
                                style="position: absolute; left: 10px; right: auto; width: 35px; height: 35px; background-color: rgba(255, 153, 51, 0.9); border-radius: 50%; top: 50%; transform: translateY(-50%); opacity: 1; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2);" onclick="event.stopPropagation();">
                            <span class="carousel-control-next-icon" aria-hidden="true" style="width: 20px; height: 20px;"></span>
                            <span class="visually-hidden">التالي</span>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($product['price']) && (!isset($product['hide_price']) || $product['hide_price'] != 1)): ?>
                    <div class="product-price-tag">
                        <?php echo number_format($product['price'], 2); ?> ريال
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <!-- زرار العودة إلى صفحة المتجر -->
                <div class="mb-3">
                    <a href="store-page.php?id=<?php echo $product['store_id']; ?>" class="btn" style="background-color: #ff7a00; color: white; border: none;">
                        <i class="bi bi-arrow-right-circle"></i> العودة إلى المتجر
                    </a>
                </div>
                <h1 class="mb-3 fw-bold" style="font-size: 2rem;"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <?php if (isset($product['hide_price']) && $product['hide_price'] == 1): ?>
                    <div class="mb-3">
                        <span class="h3 text-primary">
                            <i class="bi bi-telephone-fill me-1"></i> اتصل للسعر
                        </span>
                        <?php if (!empty($product['store_id'])): 
                            // جلب رقم هاتف المتجر
                            $store_phone_sql = "SELECT phone FROM stores WHERE id = ?";
                            $store_phone_stmt = $conn->prepare($store_phone_sql);
                            $store_phone_stmt->bind_param("i", $product['store_id']);
                            $store_phone_stmt->execute();
                            $store_phone_result = $store_phone_stmt->get_result();
                            $store_phone = $store_phone_result->fetch_assoc();
                            
                            if (!empty($store_phone['phone'])):
                                // تنسيق رقم الهاتف للواتساب (إزالة أي أحرف غير رقمية)
                                $whatsapp_number = preg_replace('/[^0-9]/', '', $store_phone['phone']);
                            ?>
                            <a href="https://wa.me/<?php echo $whatsapp_number; ?>?text=<?php echo urlencode('مرحباً، أنا مهتم بمنتج ' . $product['name'] . ' على السوق الإلكتروني. هل يمكنك إخباري بالسعر؟'); ?>" target="_blank" class="btn btn-success ms-2">
                                <i class="bi bi-whatsapp"></i> تواصل عبر واتساب
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif (!empty($product['offer_id'])): ?>
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
                
                <div class="mb-4">
                    <h5 class="fw-bold">الوصف</h5>
                    <p><?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?></p>
                </div>
                
                <div class="mb-4">
                    <h5 class="fw-bold">التصنيف</h5>
                    <p><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></p>
                </div>
                
                <div class="mb-4">
                    <h5 class="fw-bold">المتجر</h5>
                    <p>
                        <i class="bi bi-shop"></i> <?php echo htmlspecialchars($product['store_name']); ?>
                        <br>
                        <i class="bi bi-geo-alt"></i> 
                        <?php 
                        $address = '';
                        if (!empty($product['store_address'])) {
                            $address .= $product['store_address'];
                        }
                        if (!empty($product['store_city'])) {
                            if (!empty($address)) {
                                $address .= '، ';
                            }
                            $address .= $product['store_city'];
                        }
                        echo htmlspecialchars($address);
                        ?>
                        <?php if (!empty($address)): ?>
                            <button onclick="openMap('<?php echo addslashes($address); ?>')" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-map"></i> عرض على الخريطة
                            </button>
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
    
    // تهيئة الكاروسيل عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        // تهيئة كاروسيل المنتج
        var productCarousel = new bootstrap.Carousel(document.getElementById('productDetailCarousel'), {
            interval: 5000,  // وقت الانتظار بين الشرائح (5 ثوان)
            wrap: true,      // التفاف الكاروسيل عند الوصول إلى النهاية
            touch: true,     // دعم اللمس للأجهزة المحمولة
            ride: 'carousel' // تشغيل الكاروسيل تلقائيًا
        });
        
        // منع انتشار الحدث عند النقر على أزرار التنقل
        document.querySelectorAll('.carousel-control-prev, .carousel-control-next').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    });
    </script>
</body>
</html>
