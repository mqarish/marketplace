<?php
require_once '../includes/init.php';
require_once 'check_admin.php';

// Define BASEPATH for included files
define('BASEPATH', true);

$page_title = 'إعدادات النظام';
$page_icon = 'fa-cog';

// التأكد من وجود جدول الإعدادات
$check_table = $conn->query("SHOW TABLES LIKE 'settings'");
if ($check_table->num_rows == 0) {
    // إنشاء جدول الإعدادات إذا لم يكن موجوداً
    $create_table = "CREATE TABLE settings (
        id INT(11) NOT NULL AUTO_INCREMENT,
        setting_key VARCHAR(191) NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($create_table)) {
        die("خطأ في إنشاء جدول الإعدادات: " . $conn->error);
    }
    
    // إضافة إعدادات افتراضية
    $default_settings = [
        ['site_name', 'السوق الإلكتروني'],
        ['site_description', 'منصة تسوق إلكتروني متكاملة'],
        ['site_email', 'info@marketplace.com'],
        ['site_phone', '+966500000000'],
        ['site_address', 'الرياض، المملكة العربية السعودية'],
        ['maintenance_mode', '0'],
        ['currency', 'SAR'],
        ['currency_symbol', 'ر.س'],
        ['paypal_email', ''],
        ['paypal_enabled', '0'],
        ['stripe_key', ''],
        ['stripe_secret', ''],
        ['stripe_enabled', '0'],
        ['smtp_host', ''],
        ['smtp_port', '587'],
        ['smtp_username', ''],
        ['smtp_password', ''],
        ['smtp_encryption', 'tls'],
        ['smtp_enabled', '0'],
        ['facebook_url', ''],
        ['twitter_url', ''],
        ['instagram_url', ''],
        ['youtube_url', ''],
        ['linkedin_url', '']
    ];
    
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($default_settings as $setting) {
        $stmt->bind_param("ss", $setting[0], $setting[1]);
        $stmt->execute();
    }
}

// تحميل الإعدادات الحالية
$settings = [];
$query = "SELECT * FROM settings";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// معالجة تحديث الإعدادات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من نوع النموذج المرسل
    $form_type = $_POST['form_type'] ?? '';
    
    if ($form_type == 'general_settings') {
        // تحديث الإعدادات العامة
        $site_name = sanitize_input($_POST['site_name']);
        $site_description = sanitize_input($_POST['site_description']);
        $site_email = sanitize_input($_POST['site_email']);
        $site_phone = sanitize_input($_POST['site_phone']);
        $site_address = sanitize_input($_POST['site_address']);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        
        // تحديث قاعدة البيانات
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $settings_to_update = [
            ['site_name', $site_name],
            ['site_description', $site_description],
            ['site_email', $site_email],
            ['site_phone', $site_phone],
            ['site_address', $site_address],
            ['maintenance_mode', $maintenance_mode]
        ];
        
        foreach ($settings_to_update as $setting) {
            $stmt->bind_param("ss", $setting[0], $setting[1]);
            $stmt->execute();
        }
        
        $_SESSION['success'] = "تم تحديث الإعدادات العامة بنجاح";
        
    } elseif ($form_type == 'payment_settings') {
        // تحديث إعدادات الدفع
        $currency = sanitize_input($_POST['currency']);
        $currency_symbol = sanitize_input($_POST['currency_symbol']);
        $paypal_email = sanitize_input($_POST['paypal_email']);
        $paypal_enabled = isset($_POST['paypal_enabled']) ? 1 : 0;
        $stripe_key = sanitize_input($_POST['stripe_key']);
        $stripe_secret = sanitize_input($_POST['stripe_secret']);
        $stripe_enabled = isset($_POST['stripe_enabled']) ? 1 : 0;
        
        // تحديث قاعدة البيانات
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $settings_to_update = [
            ['currency', $currency],
            ['currency_symbol', $currency_symbol],
            ['paypal_email', $paypal_email],
            ['paypal_enabled', $paypal_enabled],
            ['stripe_key', $stripe_key],
            ['stripe_secret', $stripe_secret],
            ['stripe_enabled', $stripe_enabled]
        ];
        
        foreach ($settings_to_update as $setting) {
            $stmt->bind_param("ss", $setting[0], $setting[1]);
            $stmt->execute();
        }
        
        $_SESSION['success'] = "تم تحديث إعدادات الدفع بنجاح";
        
    } elseif ($form_type == 'email_settings') {
        // تحديث إعدادات البريد الإلكتروني
        $smtp_host = sanitize_input($_POST['smtp_host']);
        $smtp_port = sanitize_input($_POST['smtp_port']);
        $smtp_username = sanitize_input($_POST['smtp_username']);
        $smtp_password = sanitize_input($_POST['smtp_password']);
        $smtp_encryption = sanitize_input($_POST['smtp_encryption']);
        $smtp_enabled = isset($_POST['smtp_enabled']) ? 1 : 0;
        
        // تحديث قاعدة البيانات
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $settings_to_update = [
            ['smtp_host', $smtp_host],
            ['smtp_port', $smtp_port],
            ['smtp_username', $smtp_username],
            ['smtp_password', $smtp_password],
            ['smtp_encryption', $smtp_encryption],
            ['smtp_enabled', $smtp_enabled]
        ];
        
        foreach ($settings_to_update as $setting) {
            $stmt->bind_param("ss", $setting[0], $setting[1]);
            $stmt->execute();
        }
        
        $_SESSION['success'] = "تم تحديث إعدادات البريد الإلكتروني بنجاح";
        
    } elseif ($form_type == 'social_settings') {
        // تحديث إعدادات وسائل التواصل الاجتماعي
        $facebook_url = sanitize_input($_POST['facebook_url']);
        $twitter_url = sanitize_input($_POST['twitter_url']);
        $instagram_url = sanitize_input($_POST['instagram_url']);
        $youtube_url = sanitize_input($_POST['youtube_url']);
        $linkedin_url = sanitize_input($_POST['linkedin_url']);
        
        // تحديث قاعدة البيانات
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $settings_to_update = [
            ['facebook_url', $facebook_url],
            ['twitter_url', $twitter_url],
            ['instagram_url', $instagram_url],
            ['youtube_url', $youtube_url],
            ['linkedin_url', $linkedin_url]
        ];
        
        foreach ($settings_to_update as $setting) {
            $stmt->bind_param("ss", $setting[0], $setting[1]);
            $stmt->execute();
        }
        
        $_SESSION['success'] = "تم تحديث إعدادات وسائل التواصل الاجتماعي بنجاح";
    }
    
    // إعادة تحميل الإعدادات بعد التحديث
    $result = $conn->query("SELECT * FROM settings");
    $settings = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // إعادة توجيه لتجنب إعادة إرسال النموذج
    header("Location: settings.php");
    exit;
}

// استرجاع قيم الإعدادات من المصفوفة
$site_name = $settings['site_name'] ?? 'السوق الإلكتروني';
$site_description = $settings['site_description'] ?? 'منصة تسوق إلكتروني متكاملة';
$site_email = $settings['site_email'] ?? 'info@marketplace.com';
$site_phone = $settings['site_phone'] ?? '+966500000000';
$site_address = $settings['site_address'] ?? 'الرياض، المملكة العربية السعودية';
$maintenance_mode = $settings['maintenance_mode'] ?? '0';

$currency = $settings['currency'] ?? 'SAR';
$currency_symbol = $settings['currency_symbol'] ?? 'ر.س';
$paypal_email = $settings['paypal_email'] ?? '';
$paypal_enabled = $settings['paypal_enabled'] ?? '0';
$stripe_key = $settings['stripe_key'] ?? '';
$stripe_secret = $settings['stripe_secret'] ?? '';
$stripe_enabled = $settings['stripe_enabled'] ?? '0';

$smtp_host = $settings['smtp_host'] ?? '';
$smtp_port = $settings['smtp_port'] ?? '587';
$smtp_username = $settings['smtp_username'] ?? '';
$smtp_password = $settings['smtp_password'] ?? '';
$smtp_encryption = $settings['smtp_encryption'] ?? 'tls';
$smtp_enabled = $settings['smtp_enabled'] ?? '0';

$facebook_url = $settings['facebook_url'] ?? '';
$twitter_url = $settings['twitter_url'] ?? '';
$instagram_url = $settings['instagram_url'] ?? '';
$youtube_url = $settings['youtube_url'] ?? '';
$linkedin_url = $settings['linkedin_url'] ?? '';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <?php include 'admin_header.php'; ?>
    <style>
        .nav-pills .nav-link {
            color: #495057;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            margin-bottom: 5px;
        }
        .nav-pills .nav-link.active {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .settings-icon {
            width: 20px;
            text-align: center;
            margin-left: 8px;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .card-header {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas <?php echo $page_icon; ?>"></i> <?php echo $page_title; ?></h2>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">أقسام الإعدادات</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="pill" data-bs-target="#general" type="button" role="tab">
                                <i class="fas fa-cog settings-icon"></i> الإعدادات العامة
                            </button>
                            <button class="nav-link" id="payment-tab" data-bs-toggle="pill" data-bs-target="#payment" type="button" role="tab">
                                <i class="fas fa-credit-card settings-icon"></i> إعدادات الدفع
                            </button>
                            <button class="nav-link" id="email-tab" data-bs-toggle="pill" data-bs-target="#email" type="button" role="tab">
                                <i class="fas fa-envelope settings-icon"></i> إعدادات البريد الإلكتروني
                            </button>
                            <button class="nav-link" id="social-tab" data-bs-toggle="pill" data-bs-target="#social" type="button" role="tab">
                                <i class="fas fa-share-alt settings-icon"></i> وسائل التواصل الاجتماعي
                            </button>
                            <button class="nav-link" id="backup-tab" data-bs-toggle="pill" data-bs-target="#backup" type="button" role="tab">
                                <i class="fas fa-database settings-icon"></i> النسخ الاحتياطي
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <div class="tab-content" id="v-pills-tabContent">
                            <!-- الإعدادات العامة -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                                <h4 class="mb-4">الإعدادات العامة</h4>
                                <form method="POST" action="">
                                    <input type="hidden" name="form_type" value="general_settings">
                                    
                                    <div class="mb-3">
                                        <label for="site_name" class="form-label">اسم الموقع</label>
                                        <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="site_description" class="form-label">وصف الموقع</label>
                                        <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($site_description); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="site_email" class="form-label">البريد الإلكتروني للموقع</label>
                                        <input type="email" class="form-control" id="site_email" name="site_email" value="<?php echo htmlspecialchars($site_email); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="site_phone" class="form-label">رقم الهاتف</label>
                                        <input type="text" class="form-control" id="site_phone" name="site_phone" value="<?php echo htmlspecialchars($site_phone); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="site_address" class="form-label">العنوان</label>
                                        <textarea class="form-control" id="site_address" name="site_address" rows="2"><?php echo htmlspecialchars($site_address); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo $maintenance_mode == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">وضع الصيانة</label>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
                                </form>
                            </div>
                            
                            <!-- إعدادات الدفع -->
                            <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
                                <h4 class="mb-4">إعدادات الدفع</h4>
                                <form method="POST" action="">
                                    <input type="hidden" name="form_type" value="payment_settings">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="currency" class="form-label">العملة</label>
                                            <select class="form-select" id="currency" name="currency">
                                                <option value="SAR" <?php echo $currency == 'SAR' ? 'selected' : ''; ?>>ريال سعودي (SAR)</option>
                                                <option value="YER" <?php echo $currency == 'YER' ? 'selected' : ''; ?>>ريال يمني (YER)</option>
                                                <option value="USD" <?php echo $currency == 'USD' ? 'selected' : ''; ?>>دولار أمريكي (USD)</option>
                                                <option value="EUR" <?php echo $currency == 'EUR' ? 'selected' : ''; ?>>يورو (EUR)</option>
                                                <option value="GBP" <?php echo $currency == 'GBP' ? 'selected' : ''; ?>>جنيه إسترليني (GBP)</option>
                                                <option value="AED" <?php echo $currency == 'AED' ? 'selected' : ''; ?>>درهم إماراتي (AED)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="currency_symbol" class="form-label">رمز العملة</label>
                                            <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?php echo htmlspecialchars($currency_symbol); ?>">
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <h5 class="mb-3">PayPal</h5>
                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="paypal_enabled" name="paypal_enabled" <?php echo $paypal_enabled == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="paypal_enabled">تفعيل الدفع عبر PayPal</label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="paypal_email" class="form-label">البريد الإلكتروني لحساب PayPal</label>
                                        <input type="email" class="form-control" id="paypal_email" name="paypal_email" value="<?php echo htmlspecialchars($paypal_email); ?>">
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <h5 class="mb-3">Stripe</h5>
                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="stripe_enabled" name="stripe_enabled" <?php echo $stripe_enabled == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="stripe_enabled">تفعيل الدفع عبر Stripe</label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="stripe_key" class="form-label">مفتاح Stripe العام (Publishable Key)</label>
                                        <input type="text" class="form-control" id="stripe_key" name="stripe_key" value="<?php echo htmlspecialchars($stripe_key); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="stripe_secret" class="form-label">مفتاح Stripe السري (Secret Key)</label>
                                        <input type="password" class="form-control" id="stripe_secret" name="stripe_secret" value="<?php echo htmlspecialchars($stripe_secret); ?>">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">حفظ إعدادات الدفع</button>
                                </form>
                            </div>
                            
                            <!-- إعدادات البريد الإلكتروني -->
                            <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                                <h4 class="mb-4">إعدادات البريد الإلكتروني</h4>
                                <form method="POST" action="">
                                    <input type="hidden" name="form_type" value="email_settings">
                                    
                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="smtp_enabled" name="smtp_enabled" <?php echo $smtp_enabled == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="smtp_enabled">استخدام SMTP لإرسال البريد الإلكتروني</label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_host" class="form-label">خادم SMTP</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_host); ?>" placeholder="مثال: smtp.gmail.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_port" class="form-label">منفذ SMTP</label>
                                        <input type="text" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtp_port); ?>" placeholder="مثال: 587">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_username" class="form-label">اسم المستخدم SMTP</label>
                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtp_username); ?>" placeholder="عادة ما يكون عنوان البريد الإلكتروني الخاص بك">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_password" class="form-label">كلمة المرور SMTP</label>
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($smtp_password); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_encryption" class="form-label">تشفير SMTP</label>
                                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                            <option value="tls" <?php echo $smtp_encryption == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                            <option value="ssl" <?php echo $smtp_encryption == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                            <option value="" <?php echo $smtp_encryption == '' ? 'selected' : ''; ?>>بدون تشفير</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">حفظ إعدادات البريد الإلكتروني</button>
                                    
                                    <div class="mt-4">
                                        <button type="button" class="btn btn-outline-primary" id="test_email">
                                            <i class="fas fa-paper-plane me-2"></i> إرسال بريد إلكتروني تجريبي
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- وسائل التواصل الاجتماعي -->
                            <div class="tab-pane fade" id="social" role="tabpanel" aria-labelledby="social-tab">
                                <h4 class="mb-4">وسائل التواصل الاجتماعي</h4>
                                <form method="POST" action="">
                                    <input type="hidden" name="form_type" value="social_settings">
                                    
                                    <div class="mb-3">
                                        <label for="facebook_url" class="form-label">
                                            <i class="fab fa-facebook text-primary me-2"></i> فيسبوك
                                        </label>
                                        <input type="url" class="form-control" id="facebook_url" name="facebook_url" value="<?php echo htmlspecialchars($facebook_url); ?>" placeholder="https://facebook.com/yourpage">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="twitter_url" class="form-label">
                                            <i class="fab fa-twitter text-info me-2"></i> تويتر
                                        </label>
                                        <input type="url" class="form-control" id="twitter_url" name="twitter_url" value="<?php echo htmlspecialchars($twitter_url); ?>" placeholder="https://twitter.com/youraccount">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="instagram_url" class="form-label">
                                            <i class="fab fa-instagram text-danger me-2"></i> انستغرام
                                        </label>
                                        <input type="url" class="form-control" id="instagram_url" name="instagram_url" value="<?php echo htmlspecialchars($instagram_url); ?>" placeholder="https://instagram.com/youraccount">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="youtube_url" class="form-label">
                                            <i class="fab fa-youtube text-danger me-2"></i> يوتيوب
                                        </label>
                                        <input type="url" class="form-control" id="youtube_url" name="youtube_url" value="<?php echo htmlspecialchars($youtube_url); ?>" placeholder="https://youtube.com/channel/yourchannel">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="linkedin_url" class="form-label">
                                            <i class="fab fa-linkedin text-primary me-2"></i> لينكد إن
                                        </label>
                                        <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" value="<?php echo htmlspecialchars($linkedin_url); ?>" placeholder="https://linkedin.com/company/yourcompany">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">حفظ إعدادات وسائل التواصل</button>
                                </form>
                            </div>
                            
                            <!-- النسخ الاحتياطي -->
                            <div class="tab-pane fade" id="backup" role="tabpanel" aria-labelledby="backup-tab">
                                <h4 class="mb-4">النسخ الاحتياطي واستعادة البيانات</h4>
                                
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">إنشاء نسخة احتياطية</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>قم بإنشاء نسخة احتياطية كاملة من قاعدة البيانات. سيتم حفظ الملف على الخادم ويمكنك تنزيله.</p>
                                        <button type="button" id="create_backup" class="btn btn-primary">
                                            <i class="fas fa-download me-2"></i> إنشاء نسخة احتياطية
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">استعادة البيانات</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>استعادة قاعدة البيانات من نسخة احتياطية سابقة. <strong class="text-danger">تحذير: سيتم استبدال جميع البيانات الحالية!</strong></p>
                                        <form method="POST" action="process_backup.php" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="restore">
                                            <div class="mb-3">
                                                <label for="backup_file" class="form-label">ملف النسخة الاحتياطية (SQL)</label>
                                                <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sql">
                                            </div>
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-upload me-2"></i> استعادة البيانات
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">النسخ الاحتياطية المحفوظة</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>اسم الملف</th>
                                                        <th>تاريخ الإنشاء</th>
                                                        <th>حجم الملف</th>
                                                        <th>الإجراءات</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="backup_files">
                                                    <?php
                                                    $backup_dir = '../backups/';
                                                    if (!file_exists($backup_dir)) {
                                                        mkdir($backup_dir, 0755, true);
                                                    }
                                                    
                                                    $backup_files = glob($backup_dir . '*.sql');
                                                    if (!empty($backup_files)) {
                                                        foreach ($backup_files as $file) {
                                                            $filename = basename($file);
                                                            $filesize = filesize($file);
                                                            $filedate = date('Y-m-d H:i:s', filemtime($file));
                                                            
                                                            echo '<tr>';
                                                            echo '<td>' . htmlspecialchars($filename) . '</td>';
                                                            echo '<td>' . $filedate . '</td>';
                                                            echo '<td>' . round($filesize / 1024, 2) . ' كيلوبايت</td>';
                                                            echo '<td>';
                                                            echo '<a href="download_backup.php?file=' . urlencode($filename) . '" class="btn btn-sm btn-primary me-2"><i class="fas fa-download"></i> تنزيل</a>';
                                                            echo '<button type="button" class="btn btn-sm btn-danger delete-backup" data-file="' . htmlspecialchars($filename) . '"><i class="fas fa-trash"></i> حذف</button>';
                                                            echo '</td>';
                                                            echo '</tr>';
                                                        }
                                                    } else {
                                                        echo '<tr><td colspan="4" class="text-center">لا توجد نسخ احتياطية محفوظة</td></tr>';
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // إرسال بريد إلكتروني تجريبي
        document.getElementById('test_email').addEventListener('click', function() {
            if (confirm('هل تريد إرسال بريد إلكتروني تجريبي للتأكد من صحة الإعدادات؟')) {
                fetch('send_test_email.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('تم إرسال البريد الإلكتروني التجريبي بنجاح!');
                    } else {
                        alert('فشل إرسال البريد الإلكتروني: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('حدث خطأ: ' + error);
                });
            }
        });
        
        // إنشاء نسخة احتياطية
        document.getElementById('create_backup').addEventListener('click', function() {
            if (confirm('هل تريد إنشاء نسخة احتياطية جديدة من قاعدة البيانات؟')) {
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري الإنشاء...';
                
                fetch('process_backup.php?action=create')
                .then(response => response.json())
                .then(data => {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-download me-2"></i> إنشاء نسخة احتياطية';
                    
                    if (data.success) {
                        alert('تم إنشاء النسخة الاحتياطية بنجاح!');
                        location.reload();
                    } else {
                        alert('فشل إنشاء النسخة الاحتياطية: ' + data.message);
                    }
                })
                .catch(error => {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-download me-2"></i> إنشاء نسخة احتياطية';
                    alert('حدث خطأ: ' + error);
                });
            }
        });
        
        // حذف نسخة احتياطية
        document.querySelectorAll('.delete-backup').forEach(button => {
            button.addEventListener('click', function() {
                const filename = this.getAttribute('data-file');
                if (confirm('هل أنت متأكد من حذف النسخة الاحتياطية: ' + filename + '؟')) {
                    fetch('process_backup.php?action=delete&file=' + encodeURIComponent(filename))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('تم حذف النسخة الاحتياطية بنجاح!');
                            location.reload();
                        } else {
                            alert('فشل حذف النسخة الاحتياطية: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('حدث خطأ: ' + error);
                    });
                }
            });
        });
    </script>
</body>
</html>