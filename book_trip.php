<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Validate CSRF token


// Check authentication
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['trip_id']) || empty($input['participants'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$tripId = (int)$input['trip_id'];
$participants = (int)$input['participants'];

try {
    $pdo->beginTransaction();
    
    // Check trip availability with row locking to prevent race conditions
    $trip_stmt = $pdo->prepare("
        SELECT t.max_participants, 
               (SELECT COALESCE(SUM(b.participants), 0) 
                FROM bookings b 
                WHERE b.trip_id = t.id AND b.status IN ('confirmed', 'pending')) as booked
        FROM trips t
        WHERE t.id = ? AND t.status = 'actif' AND t.start_date > NOW()
        FOR UPDATE
    ");
    $trip_stmt->execute([$tripId]);
    $trip = $trip_stmt->fetch();

    if (!$trip) {
        throw new Exception('Ce voyage n\'est plus disponible');
    }

    $available = $trip['max_participants'] - $trip['booked'];
    
    if ($participants > $available) {
        throw new Exception('Seulement ' . $available . ' places disponibles');
    }

    // Create booking
    $booking_stmt = $pdo->prepare("
        INSERT INTO bookings (trip_id, user_id, participants, status, booking_date)
        VALUES (?, ?, ?, 'confirmed', NOW())
    ");
    $booking_stmt->execute([$tripId, $_SESSION['user_id'], $participants]);
    
    // Update trip status if fully booked
    if ($available - $participants <= 0) {
        $update_stmt = $pdo->prepare("UPDATE trips SET status = 'complet' WHERE id = ?");
        $update_stmt->execute([$tripId]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Réservation confirmée!',
        'booking_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}