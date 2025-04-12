<?php
require_once '../includes/init.php';
// بدء الجلسة فقط إذا لم تكن موجودة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق مما إذا كان المستخدم مسجل الدخول بالفعل
if (isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit();
}

// معالجة التسجيل
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $description = $_POST['description'] ?? '';
    $errors = [];

    // التحقق من البيانات
    if (empty($name)) {
        $errors[] = "يرجى إدخال اسم المتجر";
    }
    if (empty($email)) {
        $errors[] = "يرجى إدخال البريد الإلكتروني";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "البريد الإلكتروني غير صالح";
    }
    if (empty($password)) {
        $errors[] = "يرجى إدخال كلمة المرور";
    } elseif (strlen($password) < 6) {
        $errors[] = "يجب أن تكون كلمة المرور 6 أحرف على الأقل";
    }
    if ($password !== $confirm_password) {
        $errors[] = "كلمتا المرور غير متطابقتين";
    }
    if (empty($phone)) {
        $errors[] = "يرجى إدخال رقم الهاتف";
    }
    if (empty($address)) {
        $errors[] = "يرجى إدخال عنوان المتجر";
    }

    // التحقق من وجود البريد الإلكتروني
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM stores WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "البريد الإلكتروني مستخدم بالفعل";
        }
        $stmt->close();
    }

    // إذا لم تكن هناك أخطاء، قم بإنشاء المتجر
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // التحقق من وجود جدول stores
            $check_table = $conn->query("SHOW TABLES LIKE 'stores'");
            if ($check_table->num_rows == 0) {
                // إنشاء جدول stores إذا لم يكن موجوداً
                $create_table = "CREATE TABLE stores (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    phone VARCHAR(20) NOT NULL,
                    address TEXT NOT NULL,
                    description TEXT,
                    status ENUM('pending', 'active', 'blocked') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                $conn->query($create_table);
            }
            
            $stmt = $conn->prepare("INSERT INTO stores (name, email, password, phone, address, description, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            if (!$stmt) {
                throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
            }
            
            $stmt->bind_param("ssssss", $name, $email, $hashed_password, $phone, $address, $description);
            
            if (!$stmt->execute()) {
                throw new Exception("خطأ في تنفيذ الاستعلام: " . $stmt->error);
            }
            
            // عرض رسالة نجاح
            $_SESSION['success_message'] = "تم إنشاء متجرك بنجاح. يرجى الانتظار حتى تتم الموافقة على متجرك من قبل الإدارة.";
            header('Location: login.php');
            exit();
            
        } catch (Exception $e) {
            $errors[] = "حدث خطأ أثناء إنشاء المتجر: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء متجر جديد - السوق الإلكتروني</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .register-container {
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin: 2rem auto;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="register-container">
                    <h2 class="text-center mb-4">إنشاء متجر جديد</h2>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">اسم المتجر</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="password" class="form-label">كلمة المرور</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('#password', '#togglePassword')">
                                    <i class="bi bi-eye" id="togglePassword"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('#confirm_password', '#toggleConfirmPassword')">
                                    <i class="bi bi-eye" id="toggleConfirmPassword"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">رقم الهاتف</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="address" class="form-label">عنوان المتجر</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($address ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label for="description" class="form-label">وصف المتجر</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">إنشاء المتجر</button>
                            </div>
                        </div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p>لديك متجر بالفعل؟ <a href="login.php">تسجيل الدخول</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.querySelector(inputId);
            const icon = document.querySelector(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
