<?php
require_once '../includes/init.php';
require_once 'check_admin.php';

// Define BASEPATH for included files
define('BASEPATH', true);

$page_title = 'تعديل المنتج';
$page_icon = 'fa-edit';

// التحقق من وجود معرف المنتج
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'معرف المنتج غير صحيح';
    header('Location: products.php');
    exit();
}

$product_id = intval($_GET['id']);

// جلب معلومات المنتج
$query = "SELECT * FROM products WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'المنتج غير موجود';
    header('Location: products.php');
    exit();
}

$product = $result->fetch_assoc();

// جلب قائمة المتاجر
$stores_query = "SELECT id, name FROM stores WHERE status = 'active' ORDER BY name ASC";
$stores_result = $conn->query($stores_query);
$stores = [];
if ($stores_result) {
    while ($row = $stores_result->fetch_assoc()) {
        $stores[] = $row;
    }
}

// جلب قائمة التصنيفات من جدول product_categories
// نجلب تصنيفات المنتجات الخاصة بجميع المتاجر
$categories_query = "SELECT pc.id, pc.name, pc.store_id, s.name as store_name 
                   FROM product_categories pc 
                   JOIN stores s ON pc.store_id = s.id 
                   ORDER BY s.name ASC, pc.name ASC";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// جلب التصنيف الحالي للمنتج إن وجد
$current_category = null;
if (!empty($product['category_id'])) {
    $current_category_query = "SELECT pc.id, pc.name, pc.store_id, s.name as store_name 
                            FROM product_categories pc 
                            JOIN stores s ON pc.store_id = s.id 
                            WHERE pc.id = ?";
    $current_category_stmt = $conn->prepare($current_category_query);
    $current_category_stmt->bind_param("i", $product['category_id']);
    $current_category_stmt->execute();
    $current_category_result = $current_category_stmt->get_result();
    if ($current_category_result->num_rows > 0) {
        $current_category = $current_category_result->fetch_assoc();
    }
}

// معالجة تعديل المنتج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استلام البيانات
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $currency = $_POST['currency'] ?? 'SAR';
    $hide_price = isset($_POST['hide_price']) ? 1 : 0;
    $store_id = intval($_POST['store_id'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    // التحقق من البيانات
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'يرجى إدخال اسم المنتج';
    }
    
    if ($price <= 0 && $hide_price == 0) {
        $errors[] = 'يرجى إدخال سعر صحيح للمنتج أو تفعيل خيار إخفاء السعر';
    }
    
    if ($store_id <= 0) {
        $errors[] = 'يرجى اختيار المتجر';
    }
    
    // معالجة الصورة
    $image_name = $product['image'];
    $image_url = $product['image_url'];
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = 'نوع الملف غير مسموح به. الأنواع المسموح بها: JPG, PNG, GIF, WEBP';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = 'حجم الصورة كبير جدًا. الحد الأقصى هو 5 ميجابايت';
        } else {
            // حذف الصورة القديمة إذا كانت موجودة
            if (!empty($image_name)) {
                $old_image_path = "../uploads/products/" . $image_name;
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            
            // إنشاء اسم فريد للصورة الجديدة
            $image_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = uniqid('product_') . '.' . $image_extension;
            $upload_dir = '../uploads/products/';
            $image_url = 'uploads/products/' . $image_name;
            
            // التأكد من وجود المجلد
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // نقل الصورة
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name)) {
                $errors[] = 'حدث خطأ أثناء رفع الصورة';
                $image_name = $product['image']; // استعادة اسم الصورة القديمة
                $image_url = $product['image_url']; // استعادة مسار الصورة القديمة
            }
        }
    }
    
    // إذا لم تكن هناك أخطاء، قم بتحديث المنتج
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, currency = ?, hide_price = ?, store_id = ?, category_id = ?, status = ?, image = ?, image_url = ? WHERE id = ?");
            $stmt->bind_param("ssdsiiisssi", $name, $description, $price, $currency, $hide_price, $store_id, $category_id, $status, $image_name, $image_url, $product_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'تم تحديث المنتج بنجاح';
                header('Location: view_product.php?id=' . $product_id);
                exit();
            } else {
                $errors[] = 'حدث خطأ أثناء تحديث المنتج: ' . $stmt->error;
                
                // إذا كانت هناك صورة جديدة تم رفعها، قم بحذفها في حالة حدوث خطأ
                if ($image_name !== $product['image'] && !empty($image_name) && file_exists($upload_dir . $image_name)) {
                    unlink($upload_dir . $image_name);
                }
            }
        } catch (Exception $e) {
            $errors[] = 'حدث خطأ أثناء تحديث المنتج: ' . $e->getMessage();
            
            // إذا كانت هناك صورة جديدة تم رفعها، قم بحذفها في حالة حدوث خطأ
            if ($image_name !== $product['image'] && !empty($image_name) && file_exists($upload_dir . $image_name)) {
                unlink($upload_dir . $image_name);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <?php include 'admin_header.php'; ?>
    <style>
        .form-label {
            font-weight: 500;
        }
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 8px;
        }
        .required::after {
            content: " *";
            color: red;
        }
        .current-image {
            max-width: 100%;
            max-height: 200px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas <?php echo $page_icon; ?>"></i> <?php echo $page_title; ?>: <?php echo htmlspecialchars($product['name']); ?></h2>
            <div>
                <a href="view_product.php?id=<?php echo $product_id; ?>" class="btn btn-secondary me-2">
                    <i class="fas fa-eye"></i>
                    عرض المنتج
                </a>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i>
                    العودة إلى قائمة المنتجات
                </a>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form action="edit_product.php?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- معلومات المنتج الأساسية -->
                            <div class="mb-3">
                                <label for="name" class="form-label required">اسم المنتج</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">وصف المنتج</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="price" class="form-label required">السعر</label>
                                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="currency" class="form-label">العملة</label>
                                        <select class="form-select" id="currency" name="currency">
                                            <option value="SAR" <?php echo $product['currency'] === 'SAR' ? 'selected' : ''; ?>>ريال سعودي (SAR)</option>
                                            <option value="YER" <?php echo $product['currency'] === 'YER' ? 'selected' : ''; ?>>ريال يمني (YER)</option>
                                            <option value="USD" <?php echo $product['currency'] === 'USD' ? 'selected' : ''; ?>>دولار أمريكي (USD)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label d-block">&nbsp;</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="hide_price" name="hide_price" value="1" <?php echo $product['hide_price'] == 1 ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="hide_price">
                                                إخفاء السعر
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="store_id" class="form-label required">المتجر</label>
                                        <select class="form-select" id="store_id" name="store_id" required>
                                            <option value="">-- اختر المتجر --</option>
                                            <?php foreach ($stores as $store): ?>
                                                <option value="<?php echo $store['id']; ?>" <?php echo $product['store_id'] == $store['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($store['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">تصنيف المنتج</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">بدون تصنيف</option>
                                            <?php 
                                            // تنظيم التصنيفات حسب المتجر
                                            $store_categories = [];
                                            foreach ($categories as $category) {
                                                $store_categories[$category['store_id']][] = $category;
                                            }
                                            
                                            // عرض التصنيفات مجمعة حسب المتجر
                                            foreach ($store_categories as $store_id => $store_cats): 
                                                $store_name = $store_cats[0]['store_name'];
                                            ?>
                                                <optgroup label="<?php echo htmlspecialchars($store_name); ?>">
                                                    <?php foreach ($store_cats as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>" 
                                                                <?php echo (isset($product['category_id']) && $product['category_id'] == $category['id']) ? 'selected' : ''; ?>
                                                                data-store-id="<?php echo $category['store_id']; ?>">
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                            
                                            <?php if ($current_category && !in_array($current_category['id'], array_column($categories, 'id'))): ?>
                                            <!-- إذا كان التصنيف الحالي غير موجود في القائمة (مثلاً إذا تم حذفه) -->
                                            <optgroup label="التصنيف الحالي">
                                                <option value="<?php echo $current_category['id']; ?>" selected>
                                                    <?php echo htmlspecialchars($current_category['name']); ?> (من <?php echo htmlspecialchars($current_category['store_name']); ?>)
                                                </option>
                                            </optgroup>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">الحالة</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>نشط</option>
                                    <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- صورة المنتج -->
                            <div class="mb-3">
                                <label for="image" class="form-label">صورة المنتج</label>
                                
                                <?php if (!empty($product['image_url']) && file_exists('../' . $product['image_url'])): ?>
                                    <div class="text-center mb-3">
                                        <p class="text-muted">الصورة الحالية:</p>
                                        <img src="../<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="current-image">
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="text-muted">اترك هذا الحقل فارغًا إذا كنت لا ترغب في تغيير الصورة. الحد الأقصى لحجم الصورة: 5 ميجابايت. الأنواع المسموح بها: JPG, PNG, GIF, WEBP</small>
                                <img id="imagePreview" class="image-preview" style="display: none;" alt="معاينة الصورة">
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-save me-2"></i>
                            حفظ التغييرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // معاينة الصورة قبل الرفع
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
        
        // التحقق من إخفاء السعر
        document.getElementById('hide_price').addEventListener('change', function() {
            const priceField = document.getElementById('price');
            if (this.checked) {
                priceField.removeAttribute('required');
            } else {
                priceField.setAttribute('required', 'required');
            }
        });
        
        // تصفية التصنيفات بناءً على المتجر المحدد
        document.getElementById('store_id').addEventListener('change', function() {
            const storeId = this.value;
            const categorySelect = document.getElementById('category_id');
            const options = categorySelect.querySelectorAll('option');
            const optgroups = categorySelect.querySelectorAll('optgroup');
            
            // إذا لم يتم تحديد متجر، أظهر جميع التصنيفات
            if (!storeId) {
                optgroups.forEach(optgroup => {
                    optgroup.style.display = '';
                });
                options.forEach(option => {
                    option.style.display = '';
                });
                return;
            }
            
            // إخفاء/إظهار التصنيفات بناءً على المتجر المحدد
            let hasVisibleOptions = false;
            
            // إخفاء جميع المجموعات أولاً
            optgroups.forEach(optgroup => {
                optgroup.style.display = 'none';
            });
            
            // إظهار الخيارات المناسبة فقط
            options.forEach(option => {
                if (option.value === '') {
                    // دائماً أظهر خيار "بدون تصنيف"
                    option.style.display = '';
                } else if (option.getAttribute('data-store-id') === storeId) {
                    option.style.display = '';
                    
                    // إظهار مجموعة هذا الخيار
                    const parentOptgroup = option.closest('optgroup');
                    if (parentOptgroup) {
                        parentOptgroup.style.display = '';
                    }
                    
                    hasVisibleOptions = true;
                } else {
                    option.style.display = 'none';
                    
                    // إذا كان هذا الخيار محدداً حالياً، ألغي تحديده
                    if (option.selected) {
                        option.selected = false;
                        categorySelect.value = '';
                    }
                }
            });
            
            // إذا لم تكن هناك تصنيفات متاحة لهذا المتجر، أظهر رسالة
            if (!hasVisibleOptions) {
                // يمكنك إضافة رسالة أو إظهار تنبيه هنا
                console.log('لا توجد تصنيفات متاحة لهذا المتجر');
            }
        });
        
        // تشغيل التصفية عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // لا تقم بتصفية التصنيفات عند التحميل للحفاظ على التصنيف الحالي
            // لكن قم بتفعيل التصفية للتغييرات اللاحقة
        });
        
        // تحقق من حالة إخفاء السعر عند تحميل الصفحة
        window.addEventListener('DOMContentLoaded', function() {
            const hidePriceCheckbox = document.getElementById('hide_price');
            const priceField = document.getElementById('price');
            
            if (hidePriceCheckbox.checked) {
                priceField.removeAttribute('required');
            } else {
                priceField.setAttribute('required', 'required');
            }
        });
    </script>
</body>
</html>
