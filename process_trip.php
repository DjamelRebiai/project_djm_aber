<?php
session_start();
require 'config.php';

// التحقق من أن المستخدم مسجل الدخول وله صلاحيات إدارية
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    $_SESSION['error_message'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة';
    header('Location: login.php');
    exit();
}

// التحقق من وجود CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'رمز التحقق غير صالح';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// التحقق من وجود بيانات الرحلة
if (!isset($_POST['trip_id']) || !isset($_POST['action'])) {
    $_SESSION['error_message'] = 'بيانات الطلب غير مكتملة';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

$trip_id = (int)$_POST['trip_id'];
$action = $_POST['action'];

try {
    // التحقق من وجود الرحلة
    $stmt = $pdo->prepare("SELECT * FROM trips WHERE id = ?");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch();

    if (!$trip) {
        $_SESSION['error_message'] = 'الرحلة غير موجودة';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // معالجة الإجراء المطلوب
    switch ($action) {
        case 'approve':
            // الموافقة على الرحلة
            $stmt = $pdo->prepare("UPDATE trips SET status = 'actif' WHERE id = ?");
            $stmt->execute([$trip_id]);
            
            // إرسال إشعار للمنظم
            $message = "تمت الموافقة على رحلتك '" . $trip['title'] . "'";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->execute([$trip['organizer_id'], $message]);
            
            $_SESSION['success_message'] = 'تمت الموافقة على الرحلة بنجاح';
            break;
            
        case 'reject':
            // رفض الرحلة (تغيير الحالة فقط)
            $stmt = $pdo->prepare("UPDATE trips SET status = 'annulé' WHERE id = ?");
            $stmt->execute([$trip_id]);
            
            // إرسال إشعار للمنظم
            $message = "تم رفض رحلتك '" . $trip['title'] . "'";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->execute([$trip['organizer_id'], $message]);
            
            $_SESSION['success_message'] = 'تم رفض الرحلة بنجاح';
            break;
            
        case 'delete':
            // حذف الرحلة بشكل كامل من قاعدة البيانات
            // نبدأ بحذف السجلات المرتبطة بسبب القيود FOREIGN KEY
            
            // 1. حذف تعليقات الرحلة
            $stmt = $pdo->prepare("DELETE FROM comments WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
            
            // 2. حذف الإعجابات
            $stmt = $pdo->prepare("DELETE FROM likes WHERE trips_id = ?");
            $stmt->execute([$trip_id]);
            
            // 3. حذف المفضلات
            $stmt = $pdo->prepare("DELETE FROM favorites WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
            
            // 4. حذف الحجوزات
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
            
            // 5. حذف الوسائط
            $stmt = $pdo->prepare("DELETE FROM media WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
            
            // 6. حذف الأنشطة
            $stmt = $pdo->prepare("DELETE FROM trip_activities WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
            
            // 7. حذف نصائح السلامة
            $stmt = $pdo->prepare("DELETE FROM trip_safety_tips WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
            
            // 8. أخيراً حذف الرحلة نفسها
            $stmt = $pdo->prepare("DELETE FROM trips WHERE id = ?");
            $stmt->execute([$trip_id]);
            
            // إرسال إشعار للمنظم
            $message = "تم حذف رحلتك '" . $trip['title'] . "' بواسطة الإدارة";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->execute([$trip['organizer_id'], $message]);
            
            $_SESSION['success_message'] = 'تم حذف الرحلة بنجاح';
            break;
            
        default:
            $_SESSION['error_message'] = 'إجراء غير معروف';
            break;
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
    error_log('Database error in process_trip.php: ' . $e->getMessage());
}

// العودة إلى الصفحة السابقة
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?>