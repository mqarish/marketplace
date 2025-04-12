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

// التحقق من وجود معرف المنتج
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$product_id = (int)$_GET['id'];

// جلب بيانات المنتج
$sql = "SELECT * FROM products WHERE id = ? AND store_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $product_id, $store_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : 'inactive';
    
    // التحقق من البيانات
    if (empty($name)) $errors[] = "اسم المنتج مطلوب";
    if (empty($description)) $errors[] = "وصف المنتج مطلوب";
    if ($price <= 0) $errors[] = "السعر يجب أن يكون أكبر من 0";
    
    // معالجة الصورة الجديدة إذا تم رفعها
    $image_path = $product['image_url']; // الاحتفاظ بالصورة القديمة كقيمة افتراضية
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $upload_path = __DIR__ . '/../uploads/products/';
            $upload_result = uploadImage($_FILES['product_image'], $upload_path);
            
            if (!$upload_result['success']) {
                throw new Exception($upload_result['message']);
            }
            
            // حذف الصورة القديمة
            if (!empty($product['image_url']) && file_exists('../' . $product['image_url'])) {
                unlink('../' . $product['image_url']);
            }
            $image_path = 'uploads/products/' . $upload_result['filename'];
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    // تحديث المنتج إذا لم يكن هناك أخطاء
    if (empty($errors)) {
        $update_sql = "UPDATE products SET name = ?, description = ?, price = ?, image_url = ?, status = ? WHERE id = ? AND store_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssdssis", $name, $description, $price, $image_path, $status, $product_id, $store_id);
        
        if ($stmt->execute()) {
            $success = true;
            // تحديث بيانات المنتج المعروضة
            $product['name'] = $name;
            $product['description'] = $description;
            $product['price'] = $price;
            $product['image_url'] = $image_path;
            $product['status'] = $status;
        } else {
            $errors[] = "حدث خطأ أثناء تحديث المنتج";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المنتج - لوحة التحكم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../includes/store_navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <?php include '../includes/store_sidebar.php'; ?>
            </div>

            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">تعديل المنتج</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">تم تحديث المنتج بنجاح</div>
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
                                <label for="name" class="form-label">اسم المنتج</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($product['name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">وصف المنتج</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="price" class="form-label">السعر</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo htmlspecialchars($product['price']); ?>" 
                                       min="0.01" step="0.01" required>
                            </div>

                            <div class="mb-3">
                                <label for="product_image" class="form-label">صورة المنتج</label>
                                <?php if (!empty($product['image_url'])): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo '../' . htmlspecialchars($product['image_url']); ?>" 
                                             alt="صورة المنتج" class="img-thumbnail" style="max-width: 200px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="product_image" 
                                       name="product_image" accept="image/*">
                                <small class="text-muted">اترك هذا الحقل فارغاً إذا كنت لا تريد تغيير الصورة</small>
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">حالة المنتج</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $product['status'] == 'active' ? 'selected' : ''; ?>>نشط</option>
                                    <option value="inactive" <?php echo $product['status'] == 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                                </select>
                            </div>

                            <div class="text-end">
                                <a href="products.php" class="btn btn-secondary">إلغاء</a>
                                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
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
