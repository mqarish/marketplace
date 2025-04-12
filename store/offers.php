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

// حذف العرض إذا تم طلب ذلك
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $offer_id = (int)$_GET['delete'];
    
    // التحقق من أن العرض يخص المتجر
    $check_sql = "SELECT image_path FROM offers WHERE id = ? AND store_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $offer_id, $store_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $offer = $result->fetch_assoc();
        
        // حذف الصورة إذا كانت موجودة
        if (!empty($offer['image_path'])) {
            $image_path = "../" . $offer['image_path'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        // حذف العرض
        $delete_sql = "DELETE FROM offers WHERE id = ? AND store_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $offer_id, $store_id);
        
        if ($delete_stmt->execute()) {
            $success_msg = "تم حذف العرض بنجاح";
        } else {
            $error_msg = "حدث خطأ أثناء حذف العرض";
        }
    }
}

// جلب العروض
$offers_sql = "SELECT o.*, 
               COUNT(DISTINCT p.id) as products_count
               FROM offers o
               LEFT JOIN products p ON o.store_id = p.store_id
               WHERE o.store_id = ?
               GROUP BY o.id
               ORDER BY o.created_at DESC";

$offers_stmt = $conn->prepare($offers_sql);
$offers_stmt->bind_param("i", $store_id);
$offers_stmt->execute();
$offers_result = $offers_stmt->get_result();

// التأكد من وجود مجلد الصور
$upload_dir = '../uploads/offers/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة العروض - لوحة التحكم</title>
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
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title">إدارة العروض</h5>
                            <a href="add-offers.php" class="btn btn-primary">
                                <i class="bi bi-plus-lg"></i> إضافة عرض جديد
                            </a>
                        </div>
                        
                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success_msg)): ?>
                            <div class="alert alert-success"><?php echo $success_msg; ?></div>
                        <?php endif; ?>

                        <?php if ($offers_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>الصورة</th>
                                            <th>العنوان</th>
                                            <th>نسبة الخصم</th>
                                            <th>تاريخ البداية</th>
                                            <th>تاريخ النهاية</th>
                                            <th>الحالة</th>
                                            <th>عدد المنتجات</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($offer = $offers_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($offer['image_path'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($offer['image_path']); ?>" 
                                                             alt="صورة العرض" class="img-thumbnail" 
                                                             style="max-width: 50px; max-height: 50px;">
                                                    <?php else: ?>
                                                        <div class="text-center">
                                                            <i class="bi bi-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($offer['title']); ?></td>
                                                <td><?php echo $offer['discount_percentage']; ?>%</td>
                                                <td><?php echo $offer['start_date']; ?></td>
                                                <td><?php echo $offer['end_date']; ?></td>
                                                <td>
                                                    <?php if ($offer['status'] == 'active'): ?>
                                                        <span class="badge bg-success">نشط</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">غير نشط</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $offer['products_count']; ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="edit-offer.php?id=<?php echo $offer['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="confirmDelete(<?php echo $offer['id']; ?>)">
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
                                لا توجد عروض حالياً. <a href="add-offers.php">أضف عرضاً جديداً</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmDelete(offerId) {
        if (confirm('هل أنت متأكد من حذف هذا العرض؟')) {
            window.location.href = 'offers.php?delete=' + offerId;
        }
    }
    </script>
</body>
</html>
