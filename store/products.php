<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['store_id'])) {
    header("Location: login.php");
    exit;
}

$store_id = $_SESSION['store_id'];

// معالجة البحث والتصفية
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

// جلب تصنيفات المنتجات
$categories_sql = "SELECT * FROM product_categories WHERE store_id = ? ORDER BY name ASC";
$categories_stmt = $conn->prepare($categories_sql);
$categories_stmt->bind_param("i", $store_id);
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();
$categories = [];
while ($category = $categories_result->fetch_assoc()) {
    $categories[$category['id']] = $category;
}

// نهج جديد للبحث - استخدام استعلامات منفصلة للحصول على البيانات

// 1. جلب جميع المنتجات للمتجر الحالي
try {
    $base_sql = "SELECT * FROM products WHERE store_id = ?";
    $stmt = $conn->prepare($base_sql);
    
    if ($stmt === false) {
        throw new Exception("Error preparing base query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $products_result = $stmt->get_result();
    $all_products = [];
    
    // تحويل النتائج إلى مصفوفة
    while ($row = $products_result->fetch_assoc()) {
        $all_products[$row['id']] = $row;
    }
    
    // 2. جلب التصنيفات للمنتجات
    $categories_data = [];
    if (!empty($all_products)) {
        $cat_sql = "SELECT * FROM product_categories WHERE store_id = ?";
        $cat_stmt = $conn->prepare($cat_sql);
        $cat_stmt->bind_param("i", $store_id);
        $cat_stmt->execute();
        $cat_result = $cat_stmt->get_result();
        
        while ($cat = $cat_result->fetch_assoc()) {
            $categories_data[$cat['id']] = $cat;
        }
        
        // إضافة اسم التصنيف إلى كل منتج
        foreach ($all_products as $id => $product) {
            if (isset($product['category_id']) && isset($categories_data[$product['category_id']])) {
                $all_products[$id]['category_name'] = $categories_data[$product['category_id']]['name'];
            } else {
                $all_products[$id]['category_name'] = '';
            }
        }
    }
    
    // 3. تطبيق الفلترة على النتائج باستخدام PHP
    $filtered_products = [];
    
    foreach ($all_products as $product) {
        $include_product = true;
        
        // فلترة البحث
        if (!empty($search)) {
            $search_term = strtolower($search);
            $name_match = stripos(strtolower($product['name']), $search_term) !== false;
            $desc_match = isset($product['description']) && stripos(strtolower($product['description']), $search_term) !== false;
            
            if (!$name_match && !$desc_match) {
                $include_product = false;
            }
        }
        
        // فلترة الحالة
        if ($status_filter != 'all' && $product['status'] != $status_filter) {
            $include_product = false;
        }
        
        // فلترة التصنيف
        if ($category_filter > 0 && $product['category_id'] != $category_filter) {
            $include_product = false;
        }
        
        if ($include_product) {
            $filtered_products[] = $product;
        }
    }
    
    // 4. ترتيب النتائج حسب تاريخ الإنشاء
    usort($filtered_products, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // إنشاء كائن نتيجة مخصص للعرض
    $result = new stdClass();
    $result->num_rows = count($filtered_products);
    $result->filtered_products = $filtered_products;
    $query_error = false;
    
} catch (Exception $e) {
    // تسجيل الخطأ وإنشاء نتيجة فارغة
    error_log("Error in products.php: " . $e->getMessage());
    $result = new stdClass();
    $result->num_rows = 0;
    $result->filtered_products = [];
    $query_error = true;
    $_SESSION['error'] = "حدث خطأ أثناء جلب المنتجات. يرجى المحاولة مرة أخرى لاحقًا.";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المنتجات - لوحة التحكم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --info-color: #06b6d4;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-color: #f3f4f6;
            --dark-color: #1f2937;
            --card-border-radius: 10px;
            --transition-speed: 0.15s;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f5f7fb;
            color: #333;
        }
        
        /* تنسيق البطاقات */
        .dashboard-card {
            border: none;
            border-radius: var(--card-border-radius);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            transition: all var(--transition-speed) ease;
            overflow: hidden;
            height: 100%;
        }
        
        .dashboard-card .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1rem 1.25rem;
        }
        
        .dashboard-card .card-body {
            padding: 1.25rem;
        }
        
        /* تنسيق الجدول */
        .products-table {
            border-radius: var(--card-border-radius);
            overflow: hidden;
        }
        
        .products-table th {
            background-color: #f9fafb;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            padding: 0.75rem 1rem;
        }
        
        .products-table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }
        
        .products-table tr:hover {
            background-color: rgba(37, 99, 235, 0.03);
        }
        
        /* تنسيق الأزرار */
        .btn-dashboard-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            color: white;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.5rem 1.25rem;
            transition: all var(--transition-speed) ease;
        }
        
        .btn-dashboard-primary:hover {
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
            color: white;
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            margin-right: 0.25rem;
            color: #6b7280;
            background-color: #f3f4f6;
            border: none;
            transition: all var(--transition-speed) ease;
        }
        
        .btn-action:hover {
            background-color: #e5e7eb;
            color: #374151;
        }
        
        .btn-action.edit:hover {
            background-color: rgba(59, 130, 246, 0.2);
            color: var(--primary-color);
        }
        
        .btn-action.view:hover {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }
        
        .btn-action.delete:hover {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
        }
        
        /* تنسيق صور المنتجات */
        .product-image-container {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background-color: #f9f9f9;
            position: relative;
        }
        
        .product-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
            display: block !important; /* تأكيد العرض */
        }
        
        /* للتأكد من عرض الصور بشكل صحيح */
        .product-img-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: 1;
        }
        
        .product-img-placeholder {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f3f4f6;
            border-radius: 8px;
            color: #9ca3af;
            font-size: 1.5rem;
        }
        
        /* تنسيق البحث والتصفية */
        .search-filter-container {
            background-color: #f9fafb;
            border-radius: var(--card-border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            border-color: #e5e7eb;
            padding: 0.5rem 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.25);
        }
        
        /* تنسيق الشارات */
        .badge {
            padding: 0.35em 0.65em;
            font-weight: 500;
            border-radius: 6px;
        }
        
        .bg-success-subtle {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--success-color);
        }
        
        .bg-danger-subtle {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--danger-color);
        }
        
        /* تنسيق الصفحة الفارغة */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #9ca3af;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/store_navbar.php'; ?>

    <div class="container-fluid py-4 px-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- عنوان الصفحة -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">إدارة المنتجات</h2>
                <p class="text-muted">قم بإدارة وتنظيم جميع منتجات متجرك</p>
            </div>
            <a href="add-product.php" class="btn btn-dashboard-primary">
                <i class="bi bi-plus-circle me-2"></i> إضافة منتج جديد
            </a>
        </div>

        <!-- البحث والتصفية -->
        <div class="dashboard-card mb-4">
            <div class="card-body search-filter-container p-3">
                <form method="GET" class="row g-3 mb-0">
                    <div class="col-md-6 col-lg-8">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="البحث عن منتج..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>جميع الحالات</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>منتجات نشطة</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>منتجات غير نشطة</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="category" onchange="this.form.submit()">
                            <option value="0">جميع التصنيفات</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 text-end">
                        <a href="add-product.php" class="btn btn-dashboard-primary w-100">
                            <i class="bi bi-plus-lg me-1"></i> إضافة منتج
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- جدول المنتجات -->
        <div class="dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">قائمة المنتجات</h5>
                <span class="badge bg-primary"><?php echo ($result && $result->num_rows) ? $result->num_rows : 0; ?> منتج</span>
            </div>
            <div class="card-body p-0">
                <?php if ($result && $result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table products-table mb-0">
                            <thead>
                                <tr>
                                    <th width="60">الصورة</th>
                                    <th>اسم المنتج</th>
                                    <th>التصنيف</th>
                                    <th>السعر</th>
                                    <th>الحالة</th>
                                    <th>تاريخ الإضافة</th>
                                    <th width="120">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result->filtered_products as $row): ?>
                                    <tr data-product-id="<?php echo $row['id']; ?>">
                                        <td>
                                            <div class="product-image-container">
                                                <div class="product-img-placeholder">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="product-name fw-medium"><?php echo htmlspecialchars($row['name']); ?></div>
                                            <div class="text-muted small">
                                                <?php 
                                                $desc = isset($row['description']) ? $row['description'] : '';
                                                echo mb_substr(htmlspecialchars($desc), 0, 50) . (mb_strlen($desc) > 50 ? '...' : ''); 
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['category_name'])): ?>
                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($row['category_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($row['hide_price']) && $row['hide_price'] == 1): ?>
                                                <span class="badge bg-secondary">اتصل للسعر</span>
                                            <?php else: ?>
                                                <?php 
                                                $currency_symbol = 'ر.س'; // افتراضياً ريال سعودي
                                                if (isset($row['currency'])) {
                                                    switch ($row['currency']) {
                                                        case 'YER':
                                                            $currency_symbol = 'ر.ي'; // ريال يمني
                                                            break;
                                                        case 'USD':
                                                            $currency_symbol = '$'; // دولار أمريكي
                                                            break;
                                                        default:
                                                            $currency_symbol = 'ر.س'; // ريال سعودي
                                                    }
                                                }
                                                echo number_format($row['price'], 2) . ' ' . $currency_symbol; 
                                                ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <?php 
                                            $status = isset($row['status']) ? $row['status'] : 'active';
                                            $status_class = $status == 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                                            $status_text = $status == 'active' ? 'نشط' : 'غير نشط';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <a href="edit-product.php?id=<?php echo $row['id']; ?>" class="btn-action edit" title="تعديل">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="../customer/product-details.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn-action view" title="عرض">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" class="btn-action delete" title="حذف" onclick="deleteProduct(<?php echo $row['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-box"></i>
                        <?php if (isset($query_error) && $query_error): ?>
                            <h4>حدث خطأ أثناء جلب المنتجات</h4>
                            <p>يرجى المحاولة مرة أخرى لاحقًا أو تعديل معايير البحث</p>
                        <?php elseif (!empty($search)): ?>
                            <h4>لا توجد نتائج تطابق بحثك</h4>
                            <p>حاول استخدام كلمات بحث مختلفة أو تصفية أخرى</p>
                            <a href="products.php" class="btn btn-outline-primary">عرض جميع المنتجات</a>
                        <?php else: ?>
                            <h4>لا توجد منتجات حالياً</h4>
                            <p>قم بإضافة منتجات جديدة لعرضها هنا</p>
                            <a href="add-product.php" class="btn btn-primary">إضافة منتج جديد</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal حذف المنتج -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteProductModalLabel">تأكيد الحذف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>هل أنت متأكد من رغبتك في حذف هذا المنتج؟ هذا الإجراء لا يمكن التراجع عنه.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <form id="deleteProductForm" method="POST" action="delete-product.php">
                        <input type="hidden" id="product_id" name="product_id" value="">
                        <button type="submit" class="btn btn-danger">حذف</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteProduct(productId) {
        // تعيين معرف المنتج في النموذج
        document.getElementById('product_id').value = productId;
        
        // عرض مربع حوار التأكيد
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
        deleteModal.show();
    }

    // استخدام AJAX لحذف المنتج
    document.getElementById('deleteProductForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var productId = document.getElementById('product_id').value;
        var formData = new FormData();
        formData.append('product_id', productId);

        fetch('delete-product.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // إغلاق مربع الحوار
            var deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteProductModal'));
            deleteModal.hide();

            if (data.success) {
                // إنشاء عنصر التنبيه
                var alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="bi bi-check-circle-fill me-2"></i>
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;

                // إضافة التنبيه إلى أعلى الصفحة
                var container = document.querySelector('.container');
                container.insertBefore(alertDiv, container.firstChild);

                // حذف صف المنتج من الجدول
                var productRow = document.querySelector(`tr[data-product-id="${productId}"]`);
                if (productRow) {
                    productRow.remove();
                } else {
                    // إعادة تحميل الصفحة إذا لم يتم العثور على الصف
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                // إظهار رسالة خطأ
                var alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;

                var container = document.querySelector('.container');
                container.insertBefore(alertDiv, container.firstChild);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // إظهار رسالة خطأ عامة
            var alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                حدث خطأ أثناء حذف المنتج. الرجاء المحاولة مرة أخرى.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            var container = document.querySelector('.container');
            container.insertBefore(alertDiv, container.firstChild);
        });
    });
    </script>
</body>
</html>
