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

// معالجة البحث والتصفية
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// بناء استعلام المنتجات
$sql = "SELECT * FROM products WHERE store_id = ?";
$params = [$store_id];
$types = "i";

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($status_filter != 'all') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المنتجات - لوحة التحكم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../includes/store_navbar.php'; ?>

    <div class="container-fluid px-4 py-4">
        <div class="row">
            <!-- القائمة الجانبية -->
            <div class="col-md-3">
                <?php include '../includes/store_sidebar.php'; ?>
            </div>

            <!-- المحتوى الرئيسي -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">إدارة المنتجات</h5>
                        <a href="add-product.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>
                            إضافة منتج جديد
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" placeholder="بحث عن منتج..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="bi bi-search"></i>
                                        بحث
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>جميع الحالات</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>نشط</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                                </select>
                            </div>
                        </form>

                        <?php if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>الصورة</th>
                                            <th>المنتج</th>
                                            <th>السعر</th>
                                            <th>تاريخ الإضافة</th>
                                            <th>الحالة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($row['image_url']) && file_exists('../' . $row['image_url'])): ?>
                                                        <img src="../<?php echo $row['image_url']; ?>" 
                                                             alt="<?php echo htmlspecialchars($row['name']); ?>"
                                                             class="img-thumbnail"
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <img src="../assets/images/product-placeholder.jpg" 
                                                             alt="صورة افتراضية" 
                                                             class="img-thumbnail"
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo mb_substr(htmlspecialchars($row['description']), 0, 50); ?>...</small>
                                                </td>
                                                <td><?php echo formatPrice($row['price']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $row['status'] == 'active' ? 'نشط' : 'غير نشط'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="edit-product.php?id=<?php echo $row['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-danger" 
                                                                onclick="deleteProduct(<?php echo $row['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <?php if (!empty($search)): ?>
                                    لا توجد نتائج تطابق بحثك
                                <?php else: ?>
                                    لا توجد منتجات بعد
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteProduct(productId) {
        if (confirm('هل أنت متأكد من حذف هذا المنتج؟')) {
            window.location.href = 'delete-product.php?id=' + productId;
        }
    }
    </script>
</body>
</html>
