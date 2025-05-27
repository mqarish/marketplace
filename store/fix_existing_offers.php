<?php
/**
 * هذا الملف يقوم بإصلاح العروض الموجودة بإضافة السجلات المفقودة في جداول العلاقات
 * يجب تشغيله مرة واحدة فقط لإصلاح العروض القديمة
 */

session_start();
require_once '../includes/init.php';

// التحقق من تسجيل الدخول كمدير
if (!isset($_SESSION['admin_id'])) {
    // يمكن تعديل هذا التحقق حسب احتياجاتك
    echo "يجب تسجيل الدخول كمدير لتشغيل هذا الملف";
    exit();
}

$fixed_count = 0;
$error_msg = '';

try {
    // بدء المعاملة
    $conn->begin_transaction();

    // جلب جميع العروض النشطة
    $offers_sql = "SELECT id, store_id FROM offers WHERE status = 'active'";
    $offers_result = $conn->query($offers_sql);

    if ($offers_result->num_rows > 0) {
        while ($offer = $offers_result->fetch_assoc()) {
            $offer_id = $offer['id'];
            $store_id = $offer['store_id'];
            
            // التحقق من وجود سجل في جدول offer_store_products
            $check_store_sql = "SELECT id FROM offer_store_products WHERE offer_id = ? AND store_id = ?";
            $check_store_stmt = $conn->prepare($check_store_sql);
            $check_store_stmt->bind_param("ii", $offer_id, $store_id);
            $check_store_stmt->execute();
            $check_store_result = $check_store_stmt->get_result();
            
            // إذا لم يكن موجوداً، أضفه
            if ($check_store_result->num_rows === 0) {
                $insert_store_sql = "INSERT INTO offer_store_products (offer_id, store_id) VALUES (?, ?)";
                $insert_store_stmt = $conn->prepare($insert_store_sql);
                $insert_store_stmt->bind_param("ii", $offer_id, $store_id);
                $insert_store_stmt->execute();
                $fixed_count++;
            }
            
            // جلب منتجات العرض من جدول offer_items
            $items_sql = "SELECT product_id FROM offer_items WHERE offer_id = ? AND product_id IS NOT NULL";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bind_param("i", $offer_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $product_id = $item['product_id'];
                
                // التحقق من وجود سجل في جدول offer_products
                $check_product_sql = "SELECT id FROM offer_products WHERE offer_id = ? AND product_id = ?";
                $check_product_stmt = $conn->prepare($check_product_sql);
                $check_product_stmt->bind_param("ii", $offer_id, $product_id);
                $check_product_stmt->execute();
                $check_product_result = $check_product_stmt->get_result();
                
                // إذا لم يكن موجوداً، أضفه
                if ($check_product_result->num_rows === 0) {
                    $insert_product_sql = "INSERT INTO offer_products (offer_id, product_id) VALUES (?, ?)";
                    $insert_product_stmt = $conn->prepare($insert_product_sql);
                    $insert_product_stmt->bind_param("ii", $offer_id, $product_id);
                    $insert_product_stmt->execute();
                    $fixed_count++;
                }
            }
        }
    }
    
    // تأكيد المعاملة
    $conn->commit();
    $success_msg = "تم إصلاح {$fixed_count} سجل من العروض القديمة بنجاح";
    
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة حدوث خطأ
    $conn->rollback();
    $error_msg = "حدث خطأ أثناء إصلاح العروض: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إصلاح العروض القديمة</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">إصلاح العروض القديمة</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error_msg; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($success_msg)): ?>
                            <div class="alert alert-success">
                                <?php echo $success_msg; ?>
                            </div>
                        <?php endif; ?>
                        
                        <p>هذا الملف يقوم بإصلاح العروض القديمة بإضافة السجلات المفقودة في جداول العلاقات.</p>
                        <p>يجب تشغيله مرة واحدة فقط لإصلاح العروض القديمة.</p>
                        
                        <div class="mt-4">
                            <a href="../admin/index.php" class="btn btn-primary">العودة للوحة التحكم</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
