<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté et est un organisateur ou admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'organizer' && $_SESSION['user_type'] !== 'admin')) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fonction pour récupérer les réservations des voyages organisés par l'utilisateur
function getOrganizerBookings($pdo, $organizer_id) {
    $stmt = $pdo->prepare("
        SELECT b.*, t.title as trip_title, u.full_name as user_name, 
               u.email as user_email, u.profile_pic as user_pic
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN users u ON b.user_id = u.id
        WHERE t.organizer_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([$organizer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les voyages organisés par l'utilisateur
function getOrganizerTrips($pdo, $organizer_id) {
    $stmt = $pdo->prepare("
        SELECT id, title, start_date, end_date 
        FROM trips 
        WHERE organizer_id = ? 
        AND end_date >= CURDATE()
        ORDER BY start_date ASC
    ");
    $stmt->execute([$organizer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour mettre à jour le statut d'une réservation
function updateBookingStatus($pdo, $booking_id, $status) {
    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $booking_id]);
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['booking_id'])) {
        $booking_id = $_POST['booking_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'confirm':
                    if (updateBookingStatus($pdo, $booking_id, 'confirmed')) {
                        $_SESSION['success'] = "Réservation confirmée avec succès";
                    }
                    break;
                case 'cancel':
                    if (updateBookingStatus($pdo, $booking_id, 'cancelled')) {
                        $_SESSION['success'] = "Réservation annulée avec succès";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur: " . $e->getMessage();
        }
        
        header("Location: organizer_bookings.php");
        exit();
    }
}

$bookings = getOrganizerBookings($pdo, $user_id);
$trips = getOrganizerTrips($pdo, $user_id);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Réservations - Tourisme Forestier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
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
            --warning-color: #f39c12;
            --success-color: #27ae60;
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
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo i {
            font-size: 32px;
            color: var(--primary-color);
            margin-left: 10px;
        }

        .logo span {
            font-size: 24px;
            font-weight: 700;
        }

        nav a {
            margin-left: 20px;
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 500;
            transition: color 0.3s;
        }

        nav a:hover {
            color: var(--primary-color);
        }

        .page-title {
            margin-bottom: 30px;
            color: var(--dark-color);
            position: relative;
            padding-bottom: 15px;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .filter-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 16px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: var(--gray-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }

        .bookings-table th,
        .bookings-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .bookings-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }

        .bookings-table tr:hover {
            background-color: rgba(46, 139, 87, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 5px;
        }

        .btn-confirm {
            background-color: var(--success-color);
            color: white;
        }

        .btn-cancel {
            background-color: var(--error-color);
            color: white;
        }

        .btn-view {
            background-color: var(--secondary-color);
            color: white;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--error-color);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--gray-color);
        }

        .empty-state i {
            font-size: 50px;
            margin-bottom: 20px;
            color: var(--border-color);
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                align-items: flex-start;
            }

            nav {
                margin-top: 20px;
            }

            nav a {
                margin-left: 0;
                margin-right: 15px;
            }

            .bookings-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
          
            <nav>
            <a href="organizer.php" class="btn btn-outline-primary">
             <i class="fas fa-arrow-left me-2"></i>Retour
            </a>
                               
                             
            </nav>
            <div class="logo">
                <i class="fas fa-tree"></i>
                <span>Tourisme Forestier</span>
            </div>
        </header>

        <h1 class="page-title">Gestion des Réservations</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="filter-section">
            <h2><i class="fas fa-filter"></i> Filtres</h2>
            <form class="filter-form" method="GET">
                <div class="form-group">
                    <label for="trip">Voyage</label>
                    <select id="trip" name="trip">
                        <option value="">Tous les voyages</option>
                        <?php foreach ($trips as $trip): ?>
                            <option value="<?= $trip['id'] ?>" <?= isset($_GET['trip']) && $_GET['trip'] == $trip['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($trip['title']) ?> (<?= date('d/m/Y', strtotime($trip['start_date'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <option value="">Tous les statuts</option>
                        <option value="pending" <?= isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="confirmed" <?= isset($_GET['status']) && $_GET['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmé</option>
                        <option value="cancelled" <?= isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_from">Date de début</label>
                    <input type="date" id="date_from" name="date_from" value="<?= $_GET['date_from'] ?? '' ?>">
                </div>

                <div class="form-group">
                    <label for="date_to">Date de fin</label>
                    <input type="date" id="date_to" name="date_to" value="<?= $_GET['date_to'] ?? '' ?>">
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
                <a href="organizer_bookings.php" class="btn btn-secondary"><i class="fas fa-times"></i> Réinitialiser</a>
            </form>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>Aucune réservation trouvée</h3>
                <p>Vous n'avez aucune réservation pour vos voyages pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Voyage</th>
                            <th>Date Réservation</th>
                            <th>Participants</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?= $booking['id'] ?></td>
                            <td>
                                <div class="user-info">
                                    <?php if ($booking['user_pic']): ?>
                                        <img src="<?= htmlspecialchars($booking['user_pic']) ?>" alt="Photo de profil" class="user-avatar">
                                    <?php else: ?>
                                        <div class="user-avatar" style="background-color: #<?= substr(md5($booking['user_name']), 0, 6) ?>; color: white; display: flex; align-items: center; justify-content: center;">
                                            <?= strtoupper(substr($booking['user_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($booking['user_name']) ?></strong><br>
                                        <small><?= htmlspecialchars($booking['user_email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($booking['trip_title']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($booking['booking_date'])) ?></td>
                            <td><?= $booking['participants'] ?></td>
                            <td>
                                <span class="status-badge status-<?= $booking['status'] ?>">
                                    <?= $booking['status'] === 'pending' ? 'En attente' : ($booking['status'] === 'confirmed' ? 'Confirmé' : 'Annulé') ?>
                                </span>
                            </td>
                            <td>
                                <button class="action-btn btn-view" onclick="viewBooking(<?= $booking['id'] ?>)">
                                    <i class="fas fa-eye"></i> Voir
                                </button>
                                
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="action-btn btn-confirm">
                                            <i class="fas fa-check"></i> Confirmer
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="action-btn btn-cancel">
                                            <i class="fas fa-times"></i> Annuler
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function viewBooking(bookingId) {
            // Ici vous pouvez implémenter une modal ou une redirection vers une page de détails
            alert('Affichage des détails de la réservation #' + bookingId);
            // window.location.href = 'booking_details.php?id=' + bookingId;
        }
    </script>
</body>
</html>