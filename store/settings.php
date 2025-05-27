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

$success_msg = '';
$error_msg = '';

// معالجة تحديث الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تغيير كلمة المرور
    if (isset($_POST['change_password'])) {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // التحقق من البيانات
        if (empty($current_password)) {
            $error_msg = 'يرجى إدخال كلمة المرور الحالية';
        } elseif (empty($new_password)) {
            $error_msg = 'يرجى إدخال كلمة المرور الجديدة';
        } elseif (strlen($new_password) < 6) {
            $error_msg = 'يجب أن تكون كلمة المرور الجديدة 6 أحرف على الأقل';
        } elseif ($new_password !== $confirm_password) {
            $error_msg = 'كلمة المرور الجديدة وتأكيدها غير متطابقين';
        } else {
            // التحقق من كلمة المرور الحالية
            $check_password = $conn->prepare("SELECT password FROM stores WHERE id = ?");
            $check_password->bind_param("i", $store_id);
            $check_password->execute();
            $result = $check_password->get_result();
            $store_data = $result->fetch_assoc();
            
            if (password_verify($current_password, $store_data['password'])) {
                // تحديث كلمة المرور
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_password = $conn->prepare("UPDATE stores SET password = ? WHERE id = ?");
                $update_password->bind_param("si", $hashed_password, $store_id);
                
                if ($update_password->execute()) {
                    $success_msg = 'تم تحديث كلمة المرور بنجاح';
                } else {
                    $error_msg = 'حدث خطأ أثناء تحديث كلمة المرور';
                }
            } else {
                $error_msg = 'كلمة المرور الحالية غير صحيحة';
            }
        }
    }
    
    // تحديث إعدادات الإشعارات
    if (isset($_POST['update_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $order_notifications = isset($_POST['order_notifications']) ? 1 : 0;
        $marketing_notifications = isset($_POST['marketing_notifications']) ? 1 : 0;
        
        // تحديث إعدادات الإشعارات في قاعدة البيانات
        $update_notifications = $conn->prepare("UPDATE stores SET 
            email_notifications = ?, 
            order_notifications = ?, 
            marketing_notifications = ? 
            WHERE id = ?");
        $update_notifications->bind_param("iiis", $email_notifications, $order_notifications, $marketing_notifications, $store_id);
        
        if ($update_notifications->execute()) {
            $success_msg = 'تم تحديث إعدادات الإشعارات بنجاح';
        } else {
            $error_msg = 'حدث خطأ أثناء تحديث إعدادات الإشعارات';
        }
    }
    
    // تحديث إعدادات الخصوصية
    if (isset($_POST['update_privacy'])) {
        $show_phone = isset($_POST['show_phone']) ? 1 : 0;
        $show_email = isset($_POST['show_email']) ? 1 : 0;
        $show_address = isset($_POST['show_address']) ? 1 : 0;
        
        // تحديث إعدادات الخصوصية في قاعدة البيانات
        $update_privacy = $conn->prepare("UPDATE stores SET 
            show_phone = ?, 
            show_email = ?, 
            show_address = ? 
            WHERE id = ?");
        $update_privacy->bind_param("iiis", $show_phone, $show_email, $show_address, $store_id);
        
        if ($update_privacy->execute()) {
            $success_msg = 'تم تحديث إعدادات الخصوصية بنجاح';
        } else {
            $error_msg = 'حدث خطأ أثناء تحديث إعدادات الخصوصية';
        }
    }
}

// التحقق من وجود الأعمدة المطلوبة في جدول المتاجر وإضافتها إذا لم تكن موجودة
$columns_to_check = [
    'email_notifications' => 'TINYINT(1) DEFAULT 1',
    'order_notifications' => 'TINYINT(1) DEFAULT 1',
    'marketing_notifications' => 'TINYINT(1) DEFAULT 1',
    'show_phone' => 'TINYINT(1) DEFAULT 1',
    'show_email' => 'TINYINT(1) DEFAULT 1',
    'show_address' => 'TINYINT(1) DEFAULT 1'
];

// التحقق من وجود كل عمود وإضافته إذا لم يكن موجودًا
foreach ($columns_to_check as $column => $definition) {
    $check_column = $conn->query("SHOW COLUMNS FROM stores LIKE '$column'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE stores ADD COLUMN $column $definition");
    }
}

// جلب إعدادات المتجر الحالية
$settings_query = $conn->prepare("SELECT * FROM stores WHERE id = ?");
$settings_query->bind_param("i", $store_id);
$settings_query->execute();
$settings = $settings_query->get_result()->fetch_assoc();

// استخدام القيم الافتراضية إذا كانت الإعدادات غير موجودة
$email_notifications = isset($settings['email_notifications']) ? $settings['email_notifications'] : 1;
$order_notifications = isset($settings['order_notifications']) ? $settings['order_notifications'] : 1;
$marketing_notifications = isset($settings['marketing_notifications']) ? $settings['marketing_notifications'] : 1;
$show_phone = isset($settings['show_phone']) ? $settings['show_phone'] : 1;
$show_email = isset($settings['show_email']) ? $settings['show_email'] : 1;
$show_address = isset($settings['show_address']) ? $settings['show_address'] : 1;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات المتجر - السوق الإلكتروني</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .settings-card {
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .settings-card .card-header {
            border-radius: 10px 10px 0 0;
            font-weight: bold;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include '../includes/store_navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row">
            <div class="col-md-12">
                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card settings-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">تغيير كلمة المرور</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">يجب أن تكون كلمة المرور 6 أحرف على الأقل</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">تغيير كلمة المرور</button>
                        </form>
                    </div>
                </div>
                
                <div class="card settings-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">إعدادات الإشعارات</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_notifications">تلقي الإشعارات عبر البريد الإلكتروني</label>
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="order_notifications" name="order_notifications" <?php echo $order_notifications ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="order_notifications">إشعارات الطلبات الجديدة</label>
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="marketing_notifications" name="marketing_notifications" <?php echo $marketing_notifications ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="marketing_notifications">إشعارات تسويقية وعروض</label>
                            </div>
                            <button type="submit" name="update_notifications" class="btn btn-primary">حفظ الإعدادات</button>
                        </form>
                    </div>
                </div>
                
                <div class="card settings-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">إعدادات الخصوصية</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="show_phone" name="show_phone" <?php echo $show_phone ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_phone">عرض رقم الهاتف للعملاء</label>
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="show_email" name="show_email" <?php echo $show_email ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_email">عرض البريد الإلكتروني للعملاء</label>
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="show_address" name="show_address" <?php echo $show_address ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_address">عرض العنوان للعملاء</label>
                            </div>
                            <button type="submit" name="update_privacy" class="btn btn-primary">حفظ الإعدادات</button>
                        </form>
                    </div>
                </div>
                

            </div>
        </div>
    </div>
    


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
