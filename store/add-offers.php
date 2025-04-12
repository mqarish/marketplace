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

// معالجة إضافة العرض
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $discount = floatval($_POST['discount']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if (empty($title)) {
        $error_msg = 'يرجى إدخال عنوان العرض';
    } elseif ($discount <= 0 || $discount > 100) {
        $error_msg = 'نسبة الخصم يجب أن تكون بين 1 و 100';
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $error_msg = 'تاريخ البداية يجب أن يكون قبل تاريخ النهاية';
    } else {
        // معالجة صورة العرض
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_path = __DIR__ . '/../uploads/offers/';
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            try {
                $file_info = pathinfo($_FILES['image']['name']);
                $file_extension = strtolower($file_info['extension']);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception('نوع الملف غير مدعوم. الأنواع المدعومة هي: ' . implode(', ', $allowed_extensions));
                }
                
                if ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5 MB
                    throw new Exception('حجم الملف كبير جداً. الحد الأقصى هو 5 ميجابايت');
                }
                
                $new_file_name = uniqid() . '.' . $file_extension;
                $destination = $upload_path . $new_file_name;
                
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    throw new Exception('فشل في تحميل الصورة');
                }
                
                $image_path = 'uploads/offers/' . $new_file_name;
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
            }
        }

        if (empty($error_msg)) {
            try {
                // بدء المعاملة
                $conn->begin_transaction();

                // إضافة العرض
                $insert_sql = "INSERT INTO offers (store_id, title, description, image_path, 
                                         discount_percentage, start_date, end_date, status, offer_price) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0.00)";
        
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("isssiss", 
                    $store_id, 
                    $title, 
                    $description, 
                    $image_path,
                    $discount, 
                    $start_date, 
                    $end_date
                );

                if (!$insert_stmt->execute()) {
                    throw new Exception('حدث خطأ أثناء إضافة العرض: ' . $insert_stmt->error);
                }

                $offer_id = $conn->insert_id;

                // إضافة منتجات العرض
                if (isset($_POST['items']) && is_array($_POST['items'])) {
                    $insert_items_sql = "INSERT INTO offer_items (offer_id, product_id, name, price) 
                                       VALUES (?, ?, ?, ?)";
                    $insert_items_stmt = $conn->prepare($insert_items_sql);

                    foreach ($_POST['items']['product_id'] as $index => $product_id) {
                        if (empty($product_id)) continue;
                        
                        // Get product details from database
                        $product_query = "SELECT name FROM products WHERE id = ?";
                        $product_stmt = $conn->prepare($product_query);
                        $product_stmt->bind_param("i", $product_id);
                        $product_stmt->execute();
                        $product_result = $product_stmt->get_result();
                        $product = $product_result->fetch_assoc();
                        
                        $name = $product['name'];
                        $price = floatval($_POST['items']['price'][$index]);
                        
                        $insert_items_stmt->bind_param("iiss", 
                            $offer_id,
                            $product_id,
                            $name,
                            $price
                        );

                        if (!$insert_items_stmt->execute()) {
                            throw new Exception('حدث خطأ أثناء إضافة منتج العرض: ' . $insert_items_stmt->error);
                        }
                    }
                } else {
                    throw new Exception('يجب إضافة منتج واحد على الأقل للعرض');
                }

                // تأكيد المعاملة
                $conn->commit();
                header("Location: offers.php?success=1");
                exit();

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
    <title>إضافة عرض جديد - لوحة التحكم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../includes/store_navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row">
            <div class="col-md-3">
                <?php include '../includes/store_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">إضافة عرض جديد</h5>
                        
                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">عنوان العرض</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">وصف العرض</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">صورة العرض</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <small class="text-muted">الصيغ المدعومة: JPG, JPEG, PNG, GIF. الحجم الأقصى: 5 ميجابايت</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">نسبة الخصم (%)</label>
                                <input type="number" name="discount" class="form-control" min="1" max="100" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">تاريخ البداية</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">تاريخ النهاية</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">منتجات العرض</label>
                                <div class="offer-items">
                                    <div id="offer-items-container">
                                        <!-- سيتم إضافة منتجات العرض هنا -->
                                    </div>
                                    
                                    <button type="button" class="btn btn-success mb-3" id="add-item">
                                        <i class="bi bi-plus-circle"></i> إضافة منتج للعرض
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">حفظ العرض</button>
                        </form>

                        <template id="offer-item-template">
                            <div class="offer-item card mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">المنتج</label>
                                                <select name="items[product_id][]" class="form-select product-select" required>
                                                    <option value="">اختر المنتج</option>
                                                    <?php
                                                    $products_query = "SELECT id, name, price FROM products WHERE store_id = ? AND status = 'active'";
                                                    $products_stmt = $conn->prepare($products_query);
                                                    $products_stmt->bind_param('i', $store_id);
                                                    $products_stmt->execute();
                                                    $products_result = $products_stmt->get_result();
                                                    
                                                    while ($product = $products_result->fetch_assoc()):
                                                    ?>
                                                    <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>">
                                                        <?php echo $product['name']; ?> - <?php echo formatPrice($product['price']); ?>
                                                    </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">السعر الجديد</label>
                                                <input type="number" step="0.01" name="items[price][]" class="form-control item-price" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">الصورة</label>
                                                <input type="file" name="items[image][]" class="form-control" accept="image/*">
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger remove-item" style="margin-top: 32px;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const container = document.getElementById('offer-items-container');
                                const template = document.getElementById('offer-item-template');
                                const addButton = document.getElementById('add-item');

                                // Add first item by default
                                addNewItem();

                                // Add new item when clicking the add button
                                addButton.addEventListener('click', addNewItem);

                                // Function to add new item
                                function addNewItem() {
                                    const clone = template.content.cloneNode(true);
                                    
                                    // Add event listener for product selection
                                    const productSelect = clone.querySelector('.product-select');
                                    const priceInput = clone.querySelector('.item-price');
                                    
                                    productSelect.addEventListener('change', function() {
                                        const selectedOption = this.options[this.selectedIndex];
                                        const price = selectedOption.dataset.price;
                                        if (price) {
                                            priceInput.value = price;
                                        }
                                    });

                                    // Add event listener for remove button
                                    const removeButton = clone.querySelector('.remove-item');
                                    removeButton.addEventListener('click', function() {
                                        this.closest('.offer-item').remove();
                                    });

                                    container.appendChild(clone);
                                }
                            });
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
