<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// التحقق من طريقة الطلب والمصادقة
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// التحقق من توكن CSRF
if (empty($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Erreur de sécurité']);
    exit;
}

// الحصول على بيانات الإدخال
$input = json_decode(file_get_contents('php://input'), true);
$bookingId = $input['booking_id'] ?? null;

if (!$bookingId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de réservation manquant']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // التحقق من أن الحجز يعود للمستخدم الحالي (بغض النظر عن الحالة)
    $checkStmt = $pdo->prepare("
        SELECT b.*, t.start_date, t.title 
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $checkStmt->execute([$bookingId, $_SESSION['user_id']]);
    $booking = $checkStmt->fetch();
    
    if (!$booking) {
        throw new Exception('Réservation non trouvée ou non autorisée');
    }
    
    // حذف الحجز تماماً بدلاً من تغيير حالته
    $deleteStmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $deleteStmt->execute([$bookingId]);
    
    // إذا كانت الرحلة مكتملة وأصبحت هناك أماكن متاحة
    if ($booking['status'] === 'confirmed') {
        $tripStmt = $pdo->prepare("
            SELECT t.status, t.max_participants,
                   (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id AND status = 'confirmed') as confirmed_bookings
            FROM trips t
            WHERE t.id = ?
            FOR UPDATE
        ");
        $tripStmt->execute([$booking['trip_id']]);
        $trip = $tripStmt->fetch();
        
        if ($trip['status'] === 'complet' && $trip['confirmed_bookings'] < $trip['max_participants']) {
            $pdo->prepare("UPDATE trips SET status = 'actif' WHERE id = ?")
                ->execute([$booking['trip_id']]);
        }
    }
    
    // إرسال إشعار
    $pdo->prepare("
        INSERT INTO notifications (user_id, message)
        VALUES (?, ?)
    ")->execute([
        $_SESSION['user_id'],
        "Vous avez supprimé votre réservation pour le voyage '{$booking['title']}'"
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Réservation supprimée avec succès'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}