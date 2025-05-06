<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// التحقق من حالة الجلسة أولاً
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // التحقق من صحة الإدخال
    if (empty($email) || empty($password)) {
        $error = "الرجاء إدخال البريد الإلكتروني وكلمة المرور";
    } else {
        // البحث عن المستخدم
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // تجديد معرف الجلسة لمنع fixation attacks
            session_regenerate_id(true);
            
            // تسجيل بيانات المستخدم في الجلسة
            $_SESSION = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'user_type' => $user['user_type'],
                'logged_in' => true
            ];

            // إعادة التوجيه الآمن
            $redirect = match($user['user_type']) {
                'admin' => 'admin.php',
                'organizer' => 'organizer.php',
                default => 'user.php'
            };
            
            header("Location: " . $redirect);
            exit();
        } else {
            // رسالة خطأ عامة لمنع اكتشاف المستخدمين المسجلين
            $error = "بيانات الدخول غير صحيحة";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - السياحة الغابية</title>
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
    }
    
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Tajawal', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    body {
        background-color: var(--light-color);
        color: var(--dark-color);
        line-height: 1.6;
        background-image: url('images/forest-bg.jpg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        min-height: 100vh;
    }
    
    .login-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .login-box {
        background-color: rgba(255, 255, 255, 0.95);
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        width: 100%;
        max-width: 450px;
        transition: all 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .login-box:hover {
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    
    .logo {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 30px;
        color: var(--dark-color);
    }
    
    .logo i {
        font-size: 32px;
        margin-left: 12px;
        color: var(--primary-color);
    }
    
    .logo span {
        font-size: 24px;
        font-weight: 700;
    }
    
    h2 {
        text-align: center;
        margin-bottom: 30px;
        color: var(--dark-color);
        font-weight: 700;
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
        margin-bottom: 25px;
        position: relative;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 10px;
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
        background-color: rgba(255, 255, 255, 0.8);
    }
    
    .form-group input:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        background-color: #fff;
    }
    
    .login-btn {
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
    
    .login-btn:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(46, 139, 87, 0.3);
    }
    
    .error {
        background-color: #fde8e8;
        color: #e74c3c;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        text-align: center;
        border-left: 4px solid #e74c3c;
        animation: fadeIn 0.5s ease;
    }
    
    .footer-text {
        text-align: center;
        margin-top: 25px;
        color: var(--gray-color);
        font-size: 15px;
    }
    
    .footer-text a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .footer-text a:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }
    
    .forgot-password {
        display: block;
        text-align: left;
        margin-top: 10px;
        color: var(--gray-color);
        font-size: 14px;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .forgot-password:hover {
        color: var(--primary-color);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @media (max-width: 576px) {
        .login-box {
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
    
    /* تأثيرات إضافية */
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
    
    /* تأثيرات الطبيعة */
    .nature-effects {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: -1;
    }
    
    .leaf {
        position: absolute;
        background-size: contain;
        background-repeat: no-repeat;
        opacity: 0.6;
        animation: falling linear infinite;
    }
    
    @keyframes falling {
        0% {
            transform: translateY(-10%) rotate(0deg);
        }
        100% {
            transform: translateY(110vh) rotate(360deg);
        }
    }
    </style>
</head>
<body>
    <!-- تأثيرات أوراق الشجر -->
    <div class="nature-effects" id="natureEffects"></div>
    
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <i class="fas fa-tree"></i>
                <span>السياحة الغابية</span>
            </div>
            <h2>تسجيل الدخول</h2>

            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">البريد الإلكتروني</label>
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" required placeholder="أدخل بريدك الإلكتروني">
                </div>

                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    <input type="password" id="password" name="password" required placeholder="أدخل كلمة المرور">
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                </button>
                
                <a href="forgot_password.php" class="forgot-password">
                    <i class="fas fa-key"></i> نسيت كلمة المرور؟
                </a>
            </form>

            <p class="footer-text">ليس لديك حساب؟ <a href="register.php">إنشاء حساب جديد</a></p>
        </div>
    </div>

    <script>
        // إظهار/إخفاء كلمة المرور
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // تأثيرات أوراق الشجر
        function createLeaves() {
            const effectsContainer = document.getElementById('natureEffects');
            const leafTypes = ['🍂', '🍁', '🌿'];
            
            for (let i = 0; i < 15; i++) {
                const leaf = document.createElement('div');
                leaf.className = 'leaf';
                leaf.textContent = leafTypes[Math.floor(Math.random() * leafTypes.length)];
                
                // خصائص عشوائية لكل ورقة
                const size = Math.random() * 20 + 10;
                const left = Math.random() * 100;
                const animationDuration = Math.random() * 10 + 10;
                const animationDelay = Math.random() * 5;
                const rotation = Math.random() * 360;
                
                leaf.style.fontSize = `${size}px`;
                leaf.style.left = `${left}%`;
                leaf.style.animationDuration = `${animationDuration}s`;
                leaf.style.animationDelay = `${animationDelay}s`;
                leaf.style.transform = `rotate(${rotation}deg)`;
                
                effectsContainer.appendChild(leaf);
            }
        }
        
        // تأثيرات عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            createLeaves();
            
            const loginBox = document.querySelector('.login-box');
            loginBox.style.opacity = '0';
            loginBox.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                loginBox.style.opacity = '1';
                loginBox.style.transform = 'translateY(0)';
            }, 100);
        });

        // التحقق من صحة الإيميل أثناء الكتابة
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#e74c3c';
                this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.2)';
            } else {
                this.style.borderColor = 'var(--border-color)';
                this.style.boxShadow = '';
            }
        });
    </script>
</body>
</html>