<?php
require_once '../includes/init.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['store_id'])) {
    header('Location: login.php');
    exit();
}

$store_id = $_SESSION['store_id'];

// جلب معلومات المتجر
$stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();

// جلب جميع التصنيفات
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$all_categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// جلب تصنيفات المتجر الحالية
$store_categories_query = "SELECT category_id FROM store_categories WHERE store_id = ?";
$stmt = $conn->prepare($store_categories_query);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$store_categories = [];
while ($row = $result->fetch_assoc()) {
    $store_categories[] = $row['category_id'];
}

// معالجة تحديث المعلومات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $description = trim($_POST['description']);
    $city = trim($_POST['city']);
    $selected_categories = isset($_POST['categories']) ? $_POST['categories'] : [];

    $errors = [];

    // التحقق من البيانات
    if (empty($name)) {
        $errors[] = "اسم المتجر مطلوب";
    }
    if (empty($email)) {
        $errors[] = "البريد الإلكتروني مطلوب";
    }
    if (empty($phone)) {
        $errors[] = "رقم الهاتف مطلوب";
    }
    if (empty($selected_categories)) {
        $errors[] = "يجب اختيار تصنيف واحد على الأقل";
    }

    // معالجة تحديث التصنيفات
    if (!empty($selected_categories)) {
        // حذف التصنيفات القديمة
        $delete_categories = $conn->prepare("DELETE FROM store_categories WHERE store_id = ?");
        $delete_categories->bind_param("i", $store_id);
        $delete_categories->execute();

        // إضافة التصنيفات الجديدة
        $insert_category = $conn->prepare("INSERT INTO store_categories (store_id, category_id) VALUES (?, ?)");
        foreach ($selected_categories as $category_id) {
            $insert_category->bind_param("ii", $store_id, $category_id);
            $insert_category->execute();
        }
    }

    if (empty($errors)) {
        // تحديث معلومات المتجر
        $update_query = "UPDATE stores SET name = ?, email = ?, phone = ?, address = ?, city = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssssssi", $name, $email, $phone, $address, $city, $description, $store_id);
        
        if ($stmt->execute()) {
            // معالجة تحميل الشعار إذا تم تحديده
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logo_name = $_FILES['logo']['name'];
                $logo_tmp = $_FILES['logo']['tmp_name'];
                $logo_ext = strtolower(pathinfo($logo_name, PATHINFO_EXTENSION));
                
                // التحقق من نوع الملف
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($logo_ext, $allowed_types)) {
                    $new_logo_name = uniqid() . '.' . $logo_ext;
                    $upload_path = '../uploads/stores/' . $new_logo_name;
                    
                    if (move_uploaded_file($logo_tmp, $upload_path)) {
                        // تحديث اسم الشعار في قاعدة البيانات
                        $update_logo = $conn->prepare("UPDATE stores SET logo = ? WHERE id = ?");
                        $update_logo->bind_param("si", $new_logo_name, $store_id);
                        $update_logo->execute();
                    }
                }
            }
            
            $_SESSION['success_message'] = "تم تحديث معلومات المتجر بنجاح";
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "حدث خطأ أثناء تحديث المعلومات";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي للمتجر - السوق الإلكتروني</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .store-logo {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 1rem;
            border: 3px solid #0d6efd;
        }
        .categories-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
        }
        .category-item {
            background: white;
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        .category-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .category-item .form-check-input:checked + .form-check-label {
            color: #0d6efd;
            font-weight: 600;
        }
        .category-item .bi {
            font-size: 1.2rem;
            vertical-align: middle;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .category-checkbox {
            margin-left: 10px;
        }
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .category-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            background-color: #f8f9fa;
        }
        .category-item:hover {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <?php include '../includes/store_navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="<?php echo !empty($store['logo']) ? '../uploads/stores/' . $store['logo'] : '../assets/images/store-placeholder.jpg'; ?>" 
                             alt="شعار المتجر" 
                             class="img-fluid store-logo mb-3" 
                             style="width: 150px; height: 150px; object-fit: cover;">
                        <h5 class="card-title"><?php echo htmlspecialchars($store['name']); ?></h5>
                        <p class="card-text">
                            <small class="text-muted">
                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($store['email']); ?>
                            </small>
                        </p>
                        <button class="btn btn-outline-primary btn-sm" onclick="copyStoreUrl()">
                            <i class="bi bi-link-45deg"></i> نسخ رابط المتجر
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">تحديث معلومات المتجر</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo $_SESSION['success_message'];
                                unset($_SESSION['success_message']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">اسم المتجر</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($store['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">البريد الإلكتروني</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($store['email']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">رقم الهاتف</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($store['phone']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="address" class="form-label">العنوان</label>
                                        <input type="text" class="form-control" id="address" name="address" 
                                               value="<?php echo htmlspecialchars($store['address']); ?>"
                                               required>
                                        <div class="form-text">مهم لتمكين العملاء من الوصول إلى متجرك</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="city" class="form-label">المدينة</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($store['city']); ?>"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="categories-section mb-4">
                                <label class="form-label fw-bold mb-3">تصنيفات المتجر</label>
                                <div class="row g-3">
                                    <?php foreach ($all_categories as $category): ?>
                                        <div class="col-md-4">
                                            <div class="form-check category-item">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="categories[]" 
                                                       value="<?php echo $category['id']; ?>"
                                                       id="category_<?php echo $category['id']; ?>"
                                                       <?php echo in_array($category['id'], $store_categories) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="category_<?php echo $category['id']; ?>">
                                                    <i class="bi bi-<?php echo htmlspecialchars($category['icon']); ?> me-2"></i>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">وصف المتجر</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4" required><?php echo htmlspecialchars($store['description']); ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="logo" class="form-label">شعار المتجر</label>
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                <div class="form-text">اختياري - اترك فارغاً للاحتفاظ بالشعار الحالي</div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>
                                    حفظ التغييرات
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function copyStoreUrl() {
        var storeSlug = '<?php echo $store['slug']; ?>';
        var storeUrl = window.location.origin + '/marketplace/store-page.php?slug=' + storeSlug;
        
        navigator.clipboard.writeText(storeUrl).then(function() {
            alert('تم نسخ رابط المتجر بنجاح!');
        }).catch(function(err) {
            console.error('فشل نسخ الرابط: ', err);
            alert('حدث خطأ أثناء نسخ الرابط');
        });
    }
    </script>
</body>
</html>