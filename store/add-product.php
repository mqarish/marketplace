<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['store_id'])) {
    header("Location: ../login.php");
    exit;
}

$store_id = $_SESSION['store_id'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    
    // التحقق من البيانات
    if (empty($name)) $errors[] = "اسم المنتج مطلوب";
    if (empty($description)) $errors[] = "وصف المنتج مطلوب";
    if ($price <= 0) $errors[] = "السعر يجب أن يكون أكبر من 0";
    
    // معالجة الصورة
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $upload_result = uploadImage($_FILES['product_image'], '../uploads/products/');
        if (!$upload_result['success']) {
            $errors[] = $upload_result['message'];
        }
    } else {
        $errors[] = "صورة المنتج مطلوبة";
    }
    
    // إذا لم يكن هناك أخطاء، قم بإضافة المنتج
    if (empty($errors)) {
        $image_filename = $upload_result['filename'];
        $sql = "INSERT INTO products (store_id, name, description, price, image_url, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'active', NOW())";
        $stmt = $conn->prepare($sql);
        $image_path = 'uploads/products/' . $image_filename;
        $stmt->bind_param("issds", $store_id, $name, $description, $price, $image_path);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = "حدث خطأ أثناء إضافة المنتج";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة منتج جديد - لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/store_navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">القائمة</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="dashboard.php" class="list-group-item list-group-item-action">لوحة التحكم</a>
                        <a href="products.php" class="list-group-item list-group-item-action">المنتجات</a>
                        <a href="offers.php" class="list-group-item list-group-item-action">العروض</a>
                        <a href="add-offers.php" class="list-group-item list-group-item-action">إضافة عرض</a>
                        <a href="add-product.php" class="list-group-item list-group-item-action active">إضافة منتج</a>
                        <a href="profile.php" class="list-group-item list-group-item-action">الملف الشخصي</a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">إضافة منتج جديد</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                تم إضافة المنتج بنجاح!
                                <a href="products.php" class="alert-link">العودة إلى قائمة المنتجات</a>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">اسم المنتج</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">وصف المنتج</label>
                                <textarea name="description" class="form-control" rows="4" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">السعر</label>
                                <div class="input-group">
                                    <input type="number" name="price" class="form-control" step="0.01" required>
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">صورة المنتج</label>
                                <input type="file" name="product_image" class="form-control" accept="image/*" required>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">إضافة المنتج</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
