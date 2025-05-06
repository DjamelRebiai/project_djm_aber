<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

$user_id = $_SESSION['user_id'];
$trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? 'like'; // 'like' أو 'unlike'

if (!$trip_id) {
    echo json_encode(['success' => false, 'message' => 'معرف الرحلة غير صالح']);
    exit;
}

try {
    if ($action === 'like') {
        // محاولة الإعجاب
        $stmt = $pdo->prepare("INSERT IGNORE INTO likes (user_id, trips_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $trip_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('لقد أعجبت بهذا السفر من قبل');
        }

    } elseif ($action === 'unlike') {
        // إزالة الإعجاب
        $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND trips_id = ?");
        $stmt->execute([$user_id, $trip_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('لم تُعجب بهذا السفر بعد');
        }
    }

    // عد الإعجابات بعد التعديل
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE trips_id = ?");
    $countStmt->execute([$trip_id]);
    $like_count = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'like_count' => $like_count
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>