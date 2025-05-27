<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إذا كان المستخدم مسجل الدخول بالفعل، قم بتوجيهه إلى الصفحة الرئيسية
if (isset($_SESSION['customer_id'])) {
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
        $stmt = $conn->prepare("SELECT id, name, email, password, status FROM customers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $customer = $result->fetch_assoc();
            
            if (password_verify($password, $customer['password'])) {
                // التحقق من حالة الحساب
                if ($customer['status'] === 'blocked') {
                    $errors[] = "عذراً، حسابك محظور. يرجى التواصل مع الإدارة";
                } else {
                    // تسجيل الدخول
                    $_SESSION['customer_id'] = $customer['id'];
                    $_SESSION['customer_name'] = $customer['name'];
                    $_SESSION['customer_email'] = $customer['email'];
                    
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
    <title>تسجيل الدخول - السوق الإلكتروني</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.6)), url('../assets/images/customer-bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            background-color: #000;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 3;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 15px 25px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .login-container {
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 2.8rem;
            border-radius: 24px;
            box-shadow: 
                0 8px 32px 0 rgba(31, 38, 135, 0.37),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.6s ease-out;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h2 {
            color: #fff;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-floating > .form-control {
            padding: 1rem 0.75rem;
            height: calc(3.5rem + 2px);
            line-height: 1.25;
        }

        .form-floating > label {
            padding: 1rem 0.75rem;
        }

        .btn-primary {
            background-color: #0056b3;
            border-color: #0056b3;
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #004494;
            border-color: #004494;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            body {
                background-size: cover;
                background-position: center;
            }
            
            .brand-logo {
                position: relative;
                top: 0;
                right: 0;
                margin: 1rem auto;
                width: fit-content;
            }

            .main-content {
                padding: 1rem;
            }

            .login-container {
                padding: 2rem;
                margin: 0 1rem;
            }

            .login-header h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="brand-logo">
        <i class="bi bi-shop"></i>
        <span style="color: #fff; font-size: 1.5rem;">السوق الإلكتروني</span>
    </div>

    <div class="main-content">
        <div class="login-container">
            <div class="login-header">
                <h2>مرحباً بعودتك</h2>
                <p>قم بتسجيل الدخول للوصول إلى حسابك</p>
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
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" 
                        value="<?php echo htmlspecialchars($email); ?>" required 
                        placeholder="أدخل بريدك الإلكتروني">
                    <label for="email">البريد الإلكتروني</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" 
                        id="password" name="password" required 
                        placeholder="أدخل كلمة المرور">
                    <label for="password">كلمة المرور</label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        تسجيل الدخول
                    </button>
                </div>
            </form>
            
            <div class="text-center">
                <p class="text-white-50 mb-0">ليس لديك حساب؟ 
                    <a href="register.php" class="register-link">إنشاء حساب جديد</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
