<?php
session_start();
require_once '../includes/init.php';

// التحقق من تسجيل الدخول كمتجر
if (!isset($_SESSION['store_id'])) {
    header("Location: login.php");
    exit();
}

$store_id = $_SESSION['store_id'];
$error_msg = '';
$success_msg = '';

// التحقق من وجود معرف العرض
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: offers.php");
    exit();
}

$offer_id = $_GET['id'] ?? 0;
$sql = "SELECT * FROM offers WHERE id = ? AND store_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offer_id, $store_id);
$stmt->execute();
$result = $stmt->get_result();
$offer = $result->fetch_assoc();

if (!$offer) {
    $_SESSION['error'] = "العرض غير موجود";
    header("Location: offers.php");
    exit;
}

// جلب منتجات المتجر وتحديد المنتجات المشمولة في العرض
$products_sql = "SELECT p.*, c.name as category_name,
                 CASE WHEN op.product_id IS NOT NULL THEN 1 ELSE 0 END as is_in_offer
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 LEFT JOIN offer_products op ON p.id = op.product_id AND op.offer_id = ?
                 WHERE p.store_id = ? AND p.status = 'active'
                 ORDER BY is_in_offer DESC, p.created_at DESC";

$stmt = $conn->prepare($products_sql);
$stmt->bind_param("ii", $offer_id, $store_id);
$stmt->execute();
$products_result = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $discount_percentage = floatval($_POST['discount_percentage']);
    $offer_price = floatval($_POST['offer_price']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];
    $error_msg = '';

    if (empty($title)) {
        $error_msg = 'يرجى إدخال عنوان العرض';
    } elseif ($discount_percentage <= 0 || $discount_percentage > 100) {
        $error_msg = 'نسبة الخصم يجب أن تكون بين 1 و 100';
    } elseif ($offer_price <= 0) {
        $error_msg = 'سعر العرض يجب أن يكون أكبر من 0';
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $error_msg = 'تاريخ البداية يجب أن يكون قبل تاريخ النهاية';
    } else {
        // معالجة الصورة
        $image_path = $offer['image_path']; // الاحتفاظ بالصورة القديمة إذا لم يتم تحميل صورة جديدة
        if (isset($_FILES['offer_image']) && $_FILES['offer_image']['size'] > 0) {
            $upload_result = uploadImage($_FILES['offer_image'], '../uploads/offers/');
            if ($upload_result['status'] === 'error') {
                $error_msg = $upload_result['message'];
            } else {
                $image_path = $upload_result['path'];
                // حذف الصورة القديمة
                if (!empty($offer['image_path']) && file_exists('../' . $offer['image_path'])) {
                    unlink('../' . $offer['image_path']);
                }
            }
        }

        if (empty($error_msg)) {
            // بدء المعاملة
            $conn->begin_transaction();
            
            try {
                // تحديث العرض
                $update_sql = "UPDATE offers 
                              SET title = ?,
                                  description = ?, 
                                  image_path = ?,
                                  discount_percentage = ?, 
                                  offer_price = ?,
                                  start_date = ?, 
                                  end_date = ?,
                                  status = ?
                              WHERE id = ? AND store_id = ?";

                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssddsssii", 
                    $title, 
                    $description, 
                    $image_path,
                    $discount_percentage, 
                    $offer_price,
                    $start_date, 
                    $end_date,
                    $status,
                    $offer_id,
                    $store_id
                );

                if (!$update_stmt->execute()) {
                    throw new Exception("حدث خطأ أثناء تحديث العرض: " . $update_stmt->error);
                }
                
                // التحقق من وجود سجل في جدول offer_store_products
                $check_store_offer_sql = "SELECT id FROM offer_store_products WHERE offer_id = ? AND store_id = ?";
                $check_store_offer_stmt = $conn->prepare($check_store_offer_sql);
                $check_store_offer_stmt->bind_param("ii", $offer_id, $store_id);
                $check_store_offer_stmt->execute();
                $check_store_offer_result = $check_store_offer_stmt->get_result();
                
                // إذا لم يكن موجوداً، أضفه
                if ($check_store_offer_result->num_rows === 0) {
                    $insert_store_offer_sql = "INSERT INTO offer_store_products (offer_id, store_id) VALUES (?, ?)";
                    $insert_store_offer_stmt = $conn->prepare($insert_store_offer_sql);
                    $insert_store_offer_stmt->bind_param("ii", $offer_id, $store_id);
                    
                    if (!$insert_store_offer_stmt->execute()) {
                        throw new Exception('حدث خطأ أثناء ربط العرض بالمتجر: ' . $insert_store_offer_stmt->error);
                    }
                }

                // حذف جميع المنتجات المرتبطة بالعرض
                $delete_products_sql = "DELETE FROM offer_products WHERE offer_id = ?";
                $delete_stmt = $conn->prepare($delete_products_sql);
                $delete_stmt->bind_param("i", $offer_id);
                
                if (!$delete_stmt->execute()) {
                    throw new Exception("حدث خطأ أثناء حذف المنتجات القديمة: " . $delete_stmt->error);
                }

                // إضافة المنتجات المحددة للعرض
                if (isset($_POST['products']) && is_array($_POST['products'])) {
                    $insert_products_sql = "INSERT INTO offer_products (offer_id, product_id) VALUES (?, ?)";
                    $insert_stmt = $conn->prepare($insert_products_sql);
                    $insert_stmt->bind_param("ii", $offer_id, $product_id);

                    foreach ($_POST['products'] as $product_id) {
                        if (!$insert_stmt->execute()) {
                            throw new Exception("حدث خطأ أثناء إضافة المنتج رقم $product_id للعرض: " . $insert_stmt->error);
                        }
                    }
                }

                // حذف جميع منتجات العرض المستقلة
                $delete_items_sql = "DELETE FROM offer_items WHERE offer_id = ?";
                $delete_items_stmt = $conn->prepare($delete_items_sql);
                $delete_items_stmt->bind_param("i", $offer_id);
                
                if (!$delete_items_stmt->execute()) {
                    throw new Exception("حدث خطأ أثناء حذف منتجات العرض المستقلة: " . $delete_items_stmt->error);
                }

                // إضافة منتجات العرض
                if (isset($_POST['items']) && is_array($_POST['items'])) {
                    $insert_items_sql = "INSERT INTO offer_items (offer_id, product_id, name, price, image_url, description) VALUES (?, ?, ?, ?, ?, ?)";
                    $insert_items_stmt = $conn->prepare($insert_items_sql);
                    $insert_items_stmt->bind_param("iisdss", $offer_id, $product_id, $name, $price, $image_url, $description);

                    foreach ($_POST['items']['product_id'] as $index => $product_id) {
                        if (empty($product_id)) continue;
                        
                        $name = $_POST['items']['name'][$index];
                        $price = $_POST['items']['price'][$index];
                        $description = $_POST['items']['description'][$index];
                        $image_url = '';

                        if (isset($_FILES['items']['image'][$index]) && $_FILES['items']['image'][$index]['size'] > 0) {
                            $upload_result = uploadImage($_FILES['items']['image'][$index], '../uploads/offers/');
                            if ($upload_result['status'] === 'error') {
                                throw new Exception("حدث خطأ أثناء تحميل صورة المنتج: " . $upload_result['message']);
                            } else {
                                $image_url = $upload_result['path'];
                            }
                        }

                        if (!$insert_items_stmt->execute()) {
                            throw new Exception("حدث خطأ أثناء إضافة منتج العرض: " . $insert_items_stmt->error);
                        }
                        
                        // إضافة سجل في جدول offer_products إذا كان المنتج موجوداً
                        if (!empty($product_id)) {
                            $check_product_sql = "SELECT id FROM offer_products WHERE offer_id = ? AND product_id = ?";
                            $check_product_stmt = $conn->prepare($check_product_sql);
                            $check_product_stmt->bind_param("ii", $offer_id, $product_id);
                            $check_product_stmt->execute();
                            $check_product_result = $check_product_stmt->get_result();
                            
                            if ($check_product_result->num_rows === 0) {
                                $insert_product_sql = "INSERT INTO offer_products (offer_id, product_id) VALUES (?, ?)";
                                $insert_product_stmt = $conn->prepare($insert_product_sql);
                                $insert_product_stmt->bind_param("ii", $offer_id, $product_id);
                                
                                if (!$insert_product_stmt->execute()) {
                                    throw new Exception('حدث خطأ أثناء ربط المنتج بالعرض: ' . $insert_product_stmt->error);
                                }
                            }
                        }
                    }
                }

                // تأكيد المعاملة
                $conn->commit();
                $_SESSION['success'] = "تم تحديث العرض والمنتجات المشمولة به بنجاح";
                header("Location: offers.php");
                exit;

            } catch (Exception $e) {
                // التراجع عن المعاملة في حالة حدوث خطأ
                $conn->rollback();
                $error_msg = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل العرض - لوحة التحكم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
            padding-top: 0;
            margin: 0;
        }
        
        .dashboard-card {
            border: none;
            border-radius: var(--card-border-radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all var(--transition-speed) ease;
            overflow: hidden;
            height: 100%;
            margin-bottom: 1.5rem;
        }
        
        .dashboard-card .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1rem 1.25rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dashboard-card .card-body {
            padding: 1.5rem;
            background-color: #fff;
        }
        
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
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .page-header {
            background-color: #fff;
            padding: 15px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            margin-bottom: 30px;
        }
        
        .breadcrumb {
            margin-bottom: 0;
        }
        
        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .form-control, .form-select {
            padding: 0.6rem 0.75rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
        }
        
        label.form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #4b5563;
        }
        
        .offer-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .offer-item:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .remove-item {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            color: #6b7280;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .back-button:hover {
            color: var(--primary-color);
        }
        
        .back-button i {
            margin-left: 5px;
        }
    </style>
</head>
<body>
        <?php include '../includes/store_navbar.php'; ?>
    
    <!-- الشريط العلوي مع مسار التنقل -->
    <div class="page-header">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door"></i> الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="offers.php">العروض</a></li>
                    <li class="breadcrumb-item active" aria-current="page">تعديل العرض</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container">
        <a href="offers.php" class="back-button">
            <i class="bi bi-arrow-right"></i>
            العودة إلى قائمة العروض
        </a>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="dashboard-card">
                    <div class="card-header">
                        <span><i class="bi bi-tag-fill me-2 text-primary"></i> تعديل العرض</span>
                        <span class="badge bg-primary"><?php echo $offer['title']; ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success_msg)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?php echo $success_msg; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo $error_msg; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">عنوان العرض</label>
                                <input type="text" name="title" class="form-control" 
                                       value="<?php echo isset($offer['title']) ? htmlspecialchars($offer['title']) : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">وصف العرض</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo isset($offer['description']) ? htmlspecialchars($offer['description']) : ''; ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">صورة العرض</label>
                                <?php if (!empty($offer['image_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo '../' . htmlspecialchars($offer['image_path']); ?>" 
                                             alt="صورة العرض" class="img-thumbnail" style="max-width: 200px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="offer_image" class="form-control" accept="image/*">
                                <small class="text-muted">الصيغ المدعومة: JPG, JPEG, PNG, GIF. الحجم الأقصى: 5 ميجابايت</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">نسبة الخصم (%)</label>
                                        <input type="number" name="discount_percentage" class="form-control" min="1" max="100" 
                                               value="<?php echo isset($offer['discount_percentage']) ? htmlspecialchars($offer['discount_percentage']) : ''; ?>" required>
                                        <div class="form-text">أدخل نسبة الخصم من 1 إلى 100</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">سعر العرض</label>
                                        <input type="number" name="offer_price" class="form-control" step="0.01" 
                                               value="<?php echo isset($offer['offer_price']) ? htmlspecialchars($offer['offer_price']) : ''; ?>" required>
                                        <div class="form-text">أدخل سعر المنتج بعد الخصم</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">تاريخ البداية</label>
                                    <input type="date" name="start_date" class="form-control" 
                                           value="<?php echo isset($offer['start_date']) ? htmlspecialchars($offer['start_date']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">تاريخ النهاية</label>
                                    <input type="date" name="end_date" class="form-control" 
                                           value="<?php echo isset($offer['end_date']) ? htmlspecialchars($offer['end_date']) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">حالة العرض</label>
                                <select name="status" class="form-control" required>
                                    <option value="active" <?php echo ($offer['status'] == 'active') ? 'selected' : ''; ?>>نشط</option>
                                    <option value="inactive" <?php echo ($offer['status'] == 'inactive') ? 'selected' : ''; ?>>غير نشط</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">منتجات العرض</label>
                                <div class="offer-items">
                                    <?php
                                    // جلب منتجات العرض
                                    $items_sql = "SELECT * FROM offer_items WHERE offer_id = ?";
                                    $items_stmt = $conn->prepare($items_sql);
                                    $items_stmt->bind_param("i", $offer_id);
                                    $items_stmt->execute();
                                    $items_result = $items_stmt->get_result();
                                    ?>
                                    
                                    <div id="offer-items-container">
                                        <?php while ($item = $items_result->fetch_assoc()): ?>
                                        <div class="offer-item card mb-3">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="mb-3">
                                                            <label class="form-label">المنتج</label>
                                                            <select name="items[product_id][]" class="form-control product-select" required>
                                                                <option value="">اختر منتجاً</option>
                                                                <?php 
                                                                // إعادة المؤشر لبداية نتائج المنتجات
                                                                $products_result->data_seek(0);
                                                                while ($product = $products_result->fetch_assoc()): 
                                                                ?>
                                                                    <option value="<?php echo $product['id']; ?>" 
                                                                        data-price="<?php echo $product['price']; ?>"
                                                                        <?php echo ($item['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($product['name']); ?> 
                                                                        (<?php echo number_format($product['price'], 2); ?> ريال)
                                                                    </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                            <input type="hidden" name="items[name][]" value="<?php echo htmlspecialchars($item['name']); ?>" class="product-name-input">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="mb-3">
                                                            <label class="form-label">السعر بعد الخصم</label>
                                                            <input type="number" step="0.01" name="items[price][]" 
                                                                   class="form-control item-price" value="<?php echo $item['price']; ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="mb-3">
                                                            <label class="form-label">الصورة</label>
                                                            <input type="file" name="items[image][]" class="form-control" accept="image/*">
                                                            <?php if (!empty($item['image_url'])): ?>
                                                                <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                                     class="mt-2" style="max-height: 50px;">
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">الوصف</label>
                                                    <textarea name="items[description][]" class="form-control" rows="2"><?php 
                                                        echo htmlspecialchars($item['description'] ?? ''); 
                                                    ?></textarea>
                                                </div>
                                                <button type="button" class="btn btn-danger btn-sm remove-item">
                                                    <i class="bi bi-trash"></i> حذف المنتج
                                                </button>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                    
                                    <button type="button" class="btn btn-success" id="add-item">
                                        <i class="bi bi-plus-circle"></i> إضافة منتج جديد
                                    </button>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <a href="offers.php" class="btn btn-secondary">إلغاء</a>
                                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                            </div>
                        </form>

                        <template id="offer-item-template">
                            <div class="offer-item card mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">المنتج</label>
                                                <select name="items[product_id][]" class="form-control product-select" required>
                                                    <option value="">اختر منتجاً</option>
                                                    <?php 
                                                    // إعادة المؤشر لبداية نتائج المنتجات
                                                    $products_result->data_seek(0);
                                                    while ($product = $products_result->fetch_assoc()): 
                                                    ?>
                                                        <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>">
                                                            <?php echo htmlspecialchars($product['name']); ?> 
                                                            (<?php echo number_format($product['price'], 2); ?> ريال)
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <input type="hidden" name="items[name][]" class="product-name-input">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">السعر بعد الخصم</label>
                                                <input type="number" step="0.01" name="items[price][]" class="form-control item-price" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">الصورة</label>
                                                <input type="file" name="items[image][]" class="form-control" accept="image/*">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">الوصف</label>
                                        <textarea name="items[description][]" class="form-control" rows="2"></textarea>
                                    </div>
                                    <button type="button" class="btn btn-danger btn-sm remove-item">
                                        <i class="bi bi-trash"></i> حذف المنتج
                                    </button>
                                </div>
                            </div>
                        </template>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const container = document.getElementById('offer-items-container');
                            const template = document.getElementById('offer-item-template');
                            const addButton = document.getElementById('add-item');
                            const discountInput = document.querySelector('input[name="discount_percentage"]');

                            // تحديث اسم المنتج والسعر عند اختيار منتج
                            function setupProductSelect(selectElement) {
                                selectElement.addEventListener('change', function() {
                                    const selectedOption = this.options[this.selectedIndex];
                                    const nameInput = this.closest('.offer-item').querySelector('.product-name-input');
                                    const priceInput = this.closest('.offer-item').querySelector('.item-price');
                                    
                                    if (this.value) {
                                        // تحديث اسم المنتج
                                        nameInput.value = selectedOption.textContent.trim();
                                        
                                        // حساب السعر بعد الخصم
                                        const originalPrice = parseFloat(selectedOption.getAttribute('data-price'));
                                        const discount = parseFloat(discountInput.value) || 0;
                                        
                                        if (!isNaN(originalPrice) && !isNaN(discount) && discount > 0) {
                                            const discountedPrice = originalPrice - (originalPrice * discount / 100);
                                            priceInput.value = discountedPrice.toFixed(2);
                                        } else {
                                            priceInput.value = originalPrice.toFixed(2);
                                        }
                                    } else {
                                        nameInput.value = '';
                                        priceInput.value = '';
                                    }
                                });
                            }

                            // إضافة منتج جديد
                            addButton.addEventListener('click', function() {
                                const newItem = template.content.cloneNode(true);
                                container.appendChild(newItem);
                                
                                // إعداد القائمة المنسدلة للمنتج الجديد
                                const newProductSelect = container.lastElementChild.querySelector('.product-select');
                                setupProductSelect(newProductSelect);
                            });

                            // حذف منتج
                            container.addEventListener('click', function(e) {
                                if (e.target.classList.contains('remove-item') || 
                                    e.target.closest('.remove-item')) {
                                    const item = e.target.closest('.offer-item');
                                    if (confirm('هل أنت متأكد من حذف هذا المنتج؟')) {
                                        item.remove();
                                    }
                                }
                            });
                            
                            // إعداد القوائم المنسدلة الموجودة
                            document.querySelectorAll('.product-select').forEach(function(select) {
                                setupProductSelect(select);
                            });
                            
                            // تحديث الأسعار عند تغيير نسبة الخصم
                            discountInput.addEventListener('change', function() {
                                const discount = parseFloat(this.value) || 0;
                                
                                document.querySelectorAll('.product-select').forEach(function(select) {
                                    if (select.value) {
                                        const priceInput = select.closest('.offer-item').querySelector('.item-price');
                                        const originalPrice = parseFloat(select.options[select.selectedIndex].getAttribute('data-price'));
                                        
                                        if (!isNaN(originalPrice) && !isNaN(discount) && discount > 0) {
                                            const discountedPrice = originalPrice - (originalPrice * discount / 100);
                                            priceInput.value = discountedPrice.toFixed(2);
                                        }
                                    }
                                });
                            });
                        });
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
