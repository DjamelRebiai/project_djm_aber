<?php
require_once 'config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // جلب البيانات من النموذج
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $user_type = 'user'; // نوع المستخدم الافتراضي

    // التحقق من صحة البيانات
    if (empty($username)) {
        $errors['username'] = 'اسم المستخدم مطلوب';
    } elseif (strlen($username) < 4) {
        $errors['username'] = 'اسم المستخدم يجب أن يكون 4 أحرف على الأقل';
    }

    if (empty($email)) {
        $errors['email'] = 'البريد الإلكتروني مطلوب';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'البريد الإلكتروني غير صالح';
    }
    if (empty($phone)) {
        $errors['email'] = 'your phone';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'phone';
    }

    if (empty($password)) {
        $errors['password'] = 'كلمة المرور مطلوبة';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'كلمة المرور غير متطابقة';
    }

    // التحقق من عدم وجود مستخدم بنفس البريد أو اسم المستخدم
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $errors['general'] = 'اسم المستخدم أو البريد الإلكتروني موجود بالفعل';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
        }
    }
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR phone = ?");
            $stmt->execute([$username, $phone]);
            
            if ($stmt->rowCount() > 0) {
                $errors['general'] = 'اسم المستخدم أو رقم الهاتف موجود بالفعل';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
        }
    }
    // إذا لم تكن هناك أخطاء، قم بتسجيل المستخدم
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, user_type, v_org,phone) VALUES (?, ?, ?, ?, ?, ?,?)");
            $stmt->execute([$username, $email, $hashed_password, $full_name, $user_type, '',$phone]);
            $success = true;
            
            // تسجيل الدخول تلقائياً بعد التسجيل
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = $user_type;
            
            // إعادة التوجيه إلى الصفحة الرئيسية بعد 3 ثواني
            header("Refresh: 3; url=user.php");
            
        } catch (PDOException $e) {
            $errors['general'] = 'حدث خطأ في التسجيل: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب - السياحة الغابية</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-color: #2e8b57;
        --primary-dark: #1e5631;
        --secondary-color: #3cb371;
        --light-color: #f8f9fa;
        --dark-color: #1a3e2a;
        --gray-color: #95a5a6;
        --border-color: #dfe6e9;
        --error-color: #e74c3c;
    }
    
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Tajawal', sans-serif;
    }
    
    body {
        background-color: var(--light-color);
        color: var(--dark-color);
        line-height: 1.6;
        background-image: url('images/forest-bg.jpg');
        background-size: cover;
        background-position: center;
        min-height: 100vh;
    }
    
    .register-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
    }
    
    .register-box {
        background-color: rgba(255, 255, 255, 0.95);
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 500px;
        transition: all 0.3s ease;
    }
    
    .register-box:hover {
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }
    
    .logo {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 30px;
    }
    
    .logo i {
        font-size: 32px;
        margin-left: 12px;
        color: var(--primary-color);
    }
    
    .logo span {
        font-size: 24px;
        font-weight: 700;
        color: var(--dark-color);
    }
    
    h2 {
        text-align: center;
        margin-bottom: 30px;
        color: var(--dark-color);
        position: relative;
        padding-bottom: 15px;
    }
    
    h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 3px;
        background: var(--primary-color);
        border-radius: 3px;
    }
    
    .form-group {
        margin-bottom: 20px;
        position: relative;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--dark-color);
    }
    
    .form-group .input-icon {
        position: absolute;
        left: 15px;
        top: 42px;
        color: var(--gray-color);
        font-size: 18px;
    }
    
    .form-group input {
        width: 100%;
        padding: 14px 15px 14px 45px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s;
    }
    
    .form-group input:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
    }
    
    .error-message {
        color: var(--error-color);
        font-size: 14px;
        margin-top: 5px;
        display: block;
    }
    
    .register-btn {
        width: 100%;
        padding: 15px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 10px;
    }
    
    .register-btn:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(46, 139, 87, 0.3);
    }
    
    .success-message {
        background-color: #e8f5e9;
        color: var(--primary-dark);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        text-align: center;
        border-left: 4px solid var(--primary-color);
    }
    
    .login-link {
        text-align: center;
        margin-top: 25px;
        color: var(--gray-color);
    }
    
    .login-link a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
    }
    
    .login-link a:hover {
        text-decoration: underline;
    }
    
    .password-toggle {
        position: absolute;
        left: 15px;
        top: 42px;
        cursor: pointer;
        color: var(--gray-color);
        font-size: 18px;
        z-index: 2;
    }
    
    .password-toggle:hover {
        color: var(--primary-color);
    }
    
    @media (max-width: 576px) {
        .register-box {
            padding: 30px 20px;
        }
        
        .logo i {
            font-size: 28px;
        }
        
        .logo span {
            font-size: 20px;
        }
        
        h2 {
            font-size: 22px;
        }
    }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <div class="logo">
                <i class="fas fa-tree"></i>
                <span>السياحة الغابية</span>
            </div>
            
            <h2>إنشاء حساب جديد</h2>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> تم إنشاء الحساب بنجاح! سيتم تحويلك خلال ثواني...
                </div>
            <?php elseif (isset($errors['general'])): ?>
                <div class="error-message" style="margin-bottom: 20px; text-align: center;">
                    <i class="fas fa-exclamation-circle"></i> <?= $errors['general'] ?>
                </div>
            <?php endif; ?>
            
            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="full_name">الاسم الكامل</label>
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="username">اسم المستخدم</label>
                    <i class="fas fa-user-tag input-icon"></i>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    <?php if (isset($errors['username'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['username'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="phone"> phone</label>
                    <i class="fas fa-phone input-icon"></i>
                    <input type="phone" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                    <?php if (isset($errors['phone'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['phone'] ?></span>
                    <?php endif; ?>
                </div> 


                <div class="form-group">
                    <label for="email">البريد الإلكتروني</label>
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['email'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    <input type="password" id="password" name="password" required>
                    <?php if (isset($errors['password'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['password'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">تأكيد كلمة المرور</label>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['confirm_password'] ?></span>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="register-btn">
                    <i class="fas fa-user-plus"></i> إنشاء حساب
                </button>
            </form>
            
            <div class="login-link">
                لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
            </div>
        </div>
    </div>

    <script>
        // إظهار/إخفاء كلمة المرور
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPassword = document.getElementById('confirm_password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // تأثيرات عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            const registerBox = document.querySelector('.register-box');
            registerBox.style.opacity = '0';
            registerBox.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                registerBox.style.opacity = '1';
                registerBox.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>