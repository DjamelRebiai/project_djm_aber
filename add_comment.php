<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'config.php';

// تسجيل محاولة الإرسال
error_log("محاولة إضافة تعليق جديدة - IP: " . $_SERVER['REMOTE_ADDR']);

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'يجب تسجيل الدخول أولاً';
    header("Location: login.php");
    exit;
}

// التحقق من رمز الحماية
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = 'رمز الحماية غير صحيح';
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// معالجة البيانات المدخلة
$trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
$content = trim(htmlspecialchars($_POST['content'] ?? '', ENT_QUOTES, 'UTF-8'));

// التحقق من صحة البيانات
if (!$trip_id || $trip_id < 1) {
    $_SESSION['error'] = 'معرف الرحلة غير صالح';
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

if (empty($content)) {
    $_SESSION['error'] = 'لا يمكن ترك التعليق فارغاً';
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

if (mb_strlen($content) > 1000) {
    $_SESSION['error'] = 'التعليق طويل جداً (الحد الأقصى 1000 حرف)';
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

try {
    // بدء المعاملة
    $pdo->beginTransaction();
    
    // التحقق من وجود الرحلة (نسخة واحدة فقط)
    $stmt = $pdo->prepare("
        SELECT id, status 
        FROM trips 
        WHERE id = ? 
        AND status = 'actif'
        AND start_date >= CURDATE()
    ");
    $stmt->execute([$trip_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception("الرحلة غير متاحة للتعليق (قد تكون غير نشطة أو منتهية)");
    }
    
    // إضافة التعليق
    $insert = $pdo->prepare("
        INSERT INTO comments (user_id, trip_id, content, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $insert->execute([
        $_SESSION['user_id'],
        $trip_id,
        $content
    ]);
    
    // تحديث وقت النشاط الأخير للمستخدم
    $update_user = $pdo->prepare("
        UPDATE users 
        SET last_activity = NOW() 
        WHERE id = ?
    ");
    $update_user->execute([$_SESSION['user_id']]);

    $pdo->commit();
    
    // مسح بيانات النموذج المحفوظة في حالة وجودها
    if (isset($_SESSION['comment_form'])) {
        unset($_SESSION['comment_form']);
    }
    
    $_SESSION['success'] = 'تم إضافة تعليقك بنجاح';
    error_log("تم إضافة تعليق جديد - الرحلة: $trip_id, المستخدم: {$_SESSION['user_id']}");
    
} catch (PDOException $e) {
    $pdo->rollBack();
    
    // معالجة أنواع الأخطاء المختلفة
    $errorMsg = 'حدث خطأ تقني: ' . $e->getMessage();
    
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $errorMsg = 'خطأ في ارتباط البيانات (الرحلة أو المستخدم غير موجود)';
    } elseif (strpos($e->getMessage(), 'duplicate entry') !== false) {
        $errorMsg = 'هذا التعليق موجود مسبقاً';
    }
    
    $_SESSION['error'] = $errorMsg;
    error_log("خطأ في إضافة التعليق: " . $e->getMessage());
    
    // حفظ البيانات لإعادة العرض
    $_SESSION['comment_form'] = [
        'trip_id' => $trip_id,
        'content' => $content
    ];
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;