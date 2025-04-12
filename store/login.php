<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إذا كان المستخدم مسجل الدخول بالفعل، قم بتوجيهه إلى الصفحة الرئيسية
if (isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit();
}

require_once '../includes/init.php';

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // التحقق من البيانات
    if (empty($email)) {
        $errors[] = "يرجى إدخال البريد الإلكتروني";
    }
    if (empty($password)) {
        $errors[] = "يرجى إدخال كلمة المرور";
    }

    // إذا لم تكن هناك أخطاء، تحقق من صحة بيانات تسجيل الدخول
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, name, email, password, status FROM stores WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $store = $result->fetch_assoc();
            
            if (password_verify($password, $store['password'])) {
                // التحقق من حالة المتجر
                if ($store['status'] === 'pending') {
                    $errors[] = "عذراً، متجرك في انتظار الموافقة من قبل الإدارة";
                } elseif ($store['status'] === 'blocked') {
                    $errors[] = "عذراً، متجرك محظور. يرجى التواصل مع الإدارة";
                } else {
                    // تسجيل الدخول
                    $_SESSION['store_id'] = $store['id'];
                    $_SESSION['store_name'] = $store['name'];
                    $_SESSION['store_email'] = $store['email'];
                    
                    // توجيه إلى الصفحة الرئيسية
                    header('Location: index.php');
                    exit();
                }
            } else {
                $errors[] = "البريد الإلكتروني أو كلمة المرور غير صحيحة";
            }
        } else {
            $errors[] = "البريد الإلكتروني أو كلمة المرور غير صحيحة";
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - المتجر</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
        }
        
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        body {
            background-image: url('store.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(0, 0, 0, 0.6) 0%, 
                rgba(0, 0, 0, 0.5) 50%, 
                rgba(0, 0, 0, 0.4) 100%
            );
            z-index: 1;
        }
        
        .main-content {
            position: relative;
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            z-index: 2;
        }
        
        .brand-logo {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 3;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .brand-logo i {
            font-size: 2rem;
            color: white;
        }
        
        .brand-logo span {
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin: 2rem auto;
            max-width: 450px;
            width: 100%;
            animation: fadeIn 0.5s ease-out;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h2 {
            color: #ffffff;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .login-header p {
            color: rgba(255,255,255,0.9);
            margin-bottom: 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            height: auto;
        }
        
        .form-control::placeholder {
            color: rgba(255,255,255,0.7);
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .input-group-text {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .btn-primary {
            background: rgba(37, 99, 235, 0.8);
            border: none;
            padding: 0.75rem 1rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        
        .btn-primary:hover {
            background: rgba(37, 99, 235, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
        }
        
        .password-toggle {
            cursor: pointer;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .form-label {
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .alert {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            color: white;
            border-radius: 8px;
        }
        
        .register-link {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .register-link:hover {
            color: white;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: rgba(255,255,255,0.7);
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .divider span {
            padding: 0 1rem;
            color: rgba(255,255,255,0.7);
            font-size: 0.875rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin: 1rem 0;
            color: rgba(255,255,255,0.9);
        }
        
        .remember-me input {
            margin-left: 0.5rem;
        }

        .form-check-input {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .brand-logo {
                position: relative;
                top: 0;
                right: 0;
                margin: 1rem auto;
                justify-content: center;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- شعار الموقع -->
    <div class="brand-logo">
        <i class="bi bi-shop"></i>
        <span>بوابة المتاجر</span>
    </div>

    <!-- المحتوى الرئيسي -->
    <div class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-12">
                    <div class="login-container">
                        <div class="login-header">
                            <h2>مرحباً بك في بوابة المتاجر</h2>
                            <p>قم بتسجيل الدخول لإدارة متجرك</p>
                        </div>
                        
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php 
                                echo $_SESSION['success_message'];
                                unset($_SESSION['success_message']);
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

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                        value="<?php echo htmlspecialchars($email); ?>" required 
                                        placeholder="أدخل بريدك الإلكتروني">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" 
                                        id="password" name="password" required 
                                        placeholder="أدخل كلمة المرور">
                                    <button class="input-group-text password-toggle" type="button" 
                                        onclick="togglePasswordVisibility('#password', '#togglePassword')">
                                        <i class="bi bi-eye" id="togglePassword"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="remember-me">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">تذكرني</label>
                                <a href="forgot-password.php" class="ms-auto register-link">نسيت كلمة المرور؟</a>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    تسجيل الدخول
                                </button>
                            </div>
                        </form>
                        
                        <div class="divider">
                            <span>أو</span>
                        </div>
                        
                        <div class="text-center">
                            <p class="text-white-50 mb-0">ليس لديك متجر؟ 
                                <a href="register.php" class="register-link">إنشاء متجر جديد</a>
                            </p>
                        </div>
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

        // التحقق من صحة النموذج
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>
