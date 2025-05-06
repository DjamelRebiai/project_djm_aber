<?php
// دالة لإعادة التوجيه
function redirect($url) {
    header("Location: " . SITE_URL . '/' . ltrim($url, '/'));
    exit();
}

// دالة للتحقق من تسجيل الدخول
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// دالة للتحقق من نوع المستخدم
function checkUserType($allowedTypes = []) {
    if (!isLoggedIn() || !in_array($_SESSION['user_type'], $allowedTypes)) {
        redirect('login.php');
    }
}

// دوال التحقق من الصلاحيات
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

function isOrganizer() {
    return isLoggedIn() && $_SESSION['user_type'] === 'organizer';
}

// دالة لحماية المدخلات
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// دالة لتحميل الملفات بشكل آمن
function uploadFile($file, $target_dir = UPLOAD_DIR, $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4']) {
    // التحقق من وجود أخطاء في الرفع
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'حدث خطأ أثناء رفع الملف'];
    }

    // إنشاء المجلد إذا لم يكن موجوداً
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // التحقق من نوع الملف
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime, $allowed_types)) {
        return ['success' => false, 'message' => 'نوع الملف غير مدعوم'];
    }
    
    // تحديد الامتداد المناسب
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4'
    ];
    $extension = $extensions[$mime] ?? pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // إنشاء اسم فريد للملف
    $new_filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $target_path = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'path' => $target_path, 'type' => strpos($mime, 'image') !== false ? 'image' : 'video'];
    }
    
    return ['success' => false, 'message' => 'حدث خطأ أثناء حفظ الملف'];
}
?>