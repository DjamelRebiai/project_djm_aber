<?php
session_start();
require 'config.php';

// التحقق من أن المستخدم مسجل الدخول
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'يجب تسجيل الدخول أولاً';
    header('Location: login.php');
    exit();
}

// التحقق من أن المستخدم لديه صلاحيات الموافقة (admin أو organizer)
if ($_SESSION['user_type'] != 'admin' && $_SESSION['user_type'] != 'organizer') {
    $_SESSION['error_message'] = 'ليس لديك صلاحية لهذا الإجراء';
    header('Location: index.php');
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

// التحقق أن الإجراء هو الموافقة فقط
if ($action != 'approve') {
    $_SESSION['error_message'] = 'إجراء غير مسموح';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

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

    // التحقق من أن المستخدم هو المدير أو المنظم نفسه
    if ($_SESSION['user_type'] == 'organizer' && $trip['organizer_id'] != $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'لا يمكنك الموافقة على رحلات الآخرين';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // تحديث حالة الرحلة إلى "تمت الموافقة"
    $stmt = $pdo->prepare("UPDATE trips SET approved = '1' WHERE id = ?");
    $stmt->execute([$trip_id]);

    // إرسال إشعار للمنظم إذا كان الموافق هو المدير
    if ($_SESSION['user_type'] == 'admin') {
        $message = "تمت الموافقة على رحلتك '" . $trip['title'] . "' بواسطة الإدارة";
        $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt_notify->execute([$trip['organizer_id'], $message]);
    }

    // رسالة نجاح
    $_SESSION['success_message'] = 'تمت الموافقة على الرحلة بنجاح';

} catch (PDOException $e) {
    // تسجيل الخطأ وعرض رسالة مناسبة
    $_SESSION['error_message'] = 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
    error_log('Database error in approve_trip.php: ' . $e->getMessage());
}

// العودة إلى الصفحة السابقة
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?>