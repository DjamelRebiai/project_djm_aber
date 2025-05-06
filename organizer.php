<?php
session_start();
require_once 'config.php';

// Vérification de la connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Vérification du type d'utilisateur
$stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_type = $stmt->fetchColumn();

if ($user_type !== 'organizer') {
    header("Location: organizer.php");
    exit();
}

try {
    // Données de l'organisateur
    $organizer = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $organizer->execute([$_SESSION['user_id']]);
    $organizer_data = $organizer->fetch(PDO::FETCH_ASSOC);

    // Voyages de l'organisateur
    $trips = $pdo->prepare("
        SELECT t.*, COUNT(b.id) as bookings_count 
        FROM trips t
        LEFT JOIN bookings b ON t.id = b.trip_id AND b.status = 'confirmed'
        WHERE t.organizer_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $trips->execute([$_SESSION['user_id']]);
    $organizer_trips = $trips->fetchAll(PDO::FETCH_ASSOC);

    // Réservations pour les voyages
    $bookings = $pdo->prepare("
        SELECT b.*, u.username, u.full_name, t.title as trip_title
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN trips t ON b.trip_id = t.id
        WHERE t.organizer_id = ?
        ORDER BY b.booking_date DESC
        LIMIT 5
    ");
    $bookings->execute([$_SESSION['user_id']]);
    $trip_bookings = $bookings->fetchAll(PDO::FETCH_ASSOC);

    // Notifications
    $notifications = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? OR user_id = 0
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $notifications->execute([$_SESSION['user_id']]);
    $user_notifications = $notifications->fetchAll(PDO::FETCH_ASSOC);

    // Marquer les notifications comme lues
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?")->execute([$_SESSION['user_id']]);

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Organisateur - <?= htmlspecialchars($organizer_data['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        body {
            background-color: #f8f9fa;
            font-family: 'Tajawal', sans-serif;
            color: var(--text-dark);
            direction: rtl;
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(to bottom, var(--dark-color), var(--primary-color));
            color: white;
            position: fixed;
            height: 100vh;
            top: 0;
            right: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .profile-img {
            width: 80px;
            height: 80px;
            border: 3px solid rgba(255,255,255,0.2);
            border-radius: 50%;
        }
        
        .nav-link {
            padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.8);
            border-radius: 0.375rem;
            margin: 0.25rem 0.5rem;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(-5px);
        }
        
        .main-content {
            margin-right: 280px;
            padding: 2rem;
            transition: all 0.3s;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .stat-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-icon {
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .notification-badge {
            top: -5px;
            left: -5px;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Toggle -->
    <button class="btn btn-primary d-lg-none position-fixed" style="top:20px; right:20px; z-index:1000;" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand text-center py-4">
            <img src="<?= htmlspecialchars($organizer_data['profile_pic'] ?? 'uploads/trip_5_1746209965.png') ?>" 
                 class="profile-img mb-3">
            <h5 class="mb-1"><?= htmlspecialchars($organizer_data['full_name']) ?></h5>
            <small class="text-white-50">Organisateur de voyages</small>
        </div>
        <ul class="nav flex-column px-2">
            <li class="nav-item">
                <a class="nav-link active" href="organizer.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="add_trip.php">
                    <i class="fas fa-plus-circle"></i>
                    <span>Créer un voyage</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my_trips.php">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Mes voyages</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="organizer_bookings.php">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Réservations</span>
                </a>
            </li>
           
            <li class="nav-item mt-3">
                <a class="nav-link" href="profile.php">
                    <i class="fas 	fas fa-user"></i>
                    <span>Profil</span>
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link" href="login.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3>Bonjour, <?= htmlspecialchars($organizer_data['full_name']) ?></h3>
                <p class="text-muted mb-0"><?= date('d/m/Y') ?> | Dernière connexion: <?= date('H:i') ?></p>
            </div>
            
            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn btn-light position-relative" data-bs-toggle="dropdown">
                    <i class="fas fa-bell"></i>
                    <?php if(count(array_filter($user_notifications, fn($n) => !$n['is_read'])) > 0): ?>
                        <span class="badge bg-danger notification-badge position-absolute">
                            <?= count(array_filter($user_notifications, fn($n) => !$n['is_read'])) ?>
                        </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 350px;">
                    <h6 class="dropdown-header bg-light py-2">Notifications</h6>
                    <?php if(!empty($user_notifications)): ?>
                        <?php foreach($user_notifications as $notification): ?>
                            <a class="dropdown-item <?= $notification['is_read'] ? '' : 'bg-light' ?> py-3 border-bottom" href="#">
                                <small class="text-muted d-block mb-1">
                                    <i class="far fa-clock me-1"></i>
                                    <?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?>
                                </small>
                                <p class="mb-0"><?= htmlspecialchars($notification['message']) ?></p>
                            </a>
                        <?php endforeach; ?>
                        <div class="text-center py-2">
                            <a href="#" class="text-primary">Voir toutes</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="far fa-bell-slash fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">Aucune notification</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Voyages créés</h6>
                                <h2 class="mb-0"><?= count($organizer_trips) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Réservations</h6>
                                <h2 class="mb-0"><?= count($trip_bookings) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Participants</h6>
                                <h2 class="mb-0"><?= array_sum(array_column($trip_bookings, 'participants')) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Trips -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Derniers voyages</h5>
                <a href="my_trips.php" class="btn btn-sm btn-outline-primary">
                    Voir tous <i class="fas fa-arrow-left ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if(!empty($organizer_trips)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Titre</th>
                                    <th>Lieu</th>
                                    <th>Date</th>
                                    <th>Réservations</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach(array_slice($organizer_trips, 0, 5) as $trip): ?>
                                    <tr>
                                        <td><?= $trip['id'] ?></td>
                                        <td>
                                            <a href="trip_details.php?id=<?= $trip['id'] ?>" class="text-dark">
                                                <?= htmlspecialchars($trip['title']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($trip['location']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($trip['start_date'])) ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= $trip['bookings_count'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="trip_details.php?id=<?= $trip['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_trip.php?id=<?= $trip['id'] ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-map-marked-alt fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun voyage créé</h5>
                        <p class="text-muted mb-4">Commencez par créer votre premier voyage</p>
                        <a href="add_trip.php" class="btn btn-primary px-4">
                            <i class="fas fa-plus me-1"></i> Créer un voyage
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Dernières réservations</h5>
                <a href="organizer_bookings.php" class="btn btn-sm btn-outline-success">
                    Voir toutes <i class="fas fa-arrow-left ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if(!empty($trip_bookings)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Voyage</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Participants</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($trip_bookings as $booking): ?>
                                    <tr>
                                        <td><?= $booking['id'] ?></td>
                                        <td><?= htmlspecialchars($booking['trip_title']) ?></td>
                                        <td><?= htmlspecialchars($booking['full_name'] ?: $booking['username']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($booking['booking_date'])) ?></td>
                                        <td><?= $booking['participants'] ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $booking['status'] == 'confirmed' ? 'success' : 
                                                ($booking['status'] == 'pending' ? 'warning' : 'danger') 
                                            ?>">
                                                <?= $booking['status'] == 'confirmed' ? 'Confirmé' : 
                                                   ($booking['status'] == 'pending' ? 'En attente' : 'Annulé') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($booking['status'] == 'pending'): ?>
                                                <div class="d-flex gap-2">
                                                    <a href="process_booking.php?booking_id=<?= $booking['id'] ?>&action=confirm" 
                                                       class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="process_booking.php?booking_id=<?= $booking['id'] ?>&action=cancel" 
                                                       class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-ticket-alt fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune réservation</h5>
                        <p class="text-muted">Les nouvelles réservations apparaîtront ici</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>