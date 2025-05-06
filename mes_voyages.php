<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Récupérer les voyages organisés par l'utilisateur
$organized_stmt = $pdo->prepare("
    SELECT t.*, COUNT(b.id) as bookings_count
    FROM trips t
    LEFT JOIN bookings b ON t.id = b.trip_id AND b.status = 'confirmed'
    WHERE t.organizer_id = ?
    GROUP BY t.id
    ORDER BY t.start_date DESC
");
$organized_stmt->execute([$user_id]);
$organized_trips = $organized_stmt->fetchAll();

// Récupérer les voyages auxquels l'utilisateur a participé
$participated_stmt = $pdo->prepare("
    SELECT t.*, b.id as booking_id, b.booking_date, b.participants, b.status as booking_status
    FROM bookings b
    JOIN trips t ON b.trip_id = t.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC
");
$participated_stmt->execute([$user_id]);
$participated_trips = $participated_stmt->fetchAll();

// Récupérer les notifications
$notifications_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notifications_stmt->execute([$user_id]);
$notifications = $notifications_stmt->fetchAll();

// Marquer les notifications comme lues
$update_stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
$update_stmt->execute([$user_id]);
?>
<!DOCTYPE html>
<html lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes voyages - Tourisme Forestier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* CSS Reset et styles généraux */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        /* Styles du tableau de bord */
        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Barre latérale */
        .sidebar {
            width: 250px;
            background-color: #1e5631;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            right: 0;
            transition: all 0.3s;
            z-index: 10;
            background-image: linear-gradient(to bottom, #1e5631, #2d7a46);
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
        }

        .logo {
            display: flex;
            align-items: center;
            padding: 0 20px 30px;
            font-size: 1.2rem;
            font-weight: bold;
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        .logo i {
            margin-left: 10px;
            font-size: 1.5rem;
            color: #a4de9a;
        }

        .menu {
            list-style: none;
            padding: 0 10px;
        }

        .menu li {
            margin-bottom: 5px;
            border-radius: 5px;
            overflow: hidden;
        }

        .menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s;
            border-right: 3px solid transparent;
            background-color: rgba(255, 255, 255, 0.05);
        }

        .menu li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-right-color: #a4de9a;
            color: #fff;
        }

        .menu li a i {
            margin-left: 10px;
            width: 20px;
            text-align: center;
            color: #a4de9a;
        }

        .menu li.active a {
            background-color: rgba(164, 222, 154, 0.2);
            border-right-color: #a4de9a;
            color: #fff;
            font-weight: 500;
        }

        /* Contenu principal */
        .main-content {
            flex: 1;
            margin-right: 250px;
            padding: 20px;
            transition: all 0.3s;
        }

        /* Barre d'information utilisateur */
        .user-info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .user-greeting {
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .user-greeting i {
            margin-left: 10px;
            color: var(--primary-color);
        }

        /* Cartes de voyage */
        .trip-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-right: 4px solid var(--primary-color);
            transition: all 0.3s;
        }

        .trip-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(46, 139, 87, 0.1);
        }

        .trip-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .trip-title {
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .trip-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-completed {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .trip-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
        }

        .meta-item i {
            margin-left: 5px;
            color: var(--primary-color);
        }

        .trip-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-outline-primary {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }

        /* Onglets */
        .nav-tabs {
            border-bottom: 2px solid var(--primary-color);
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            color: var(--dark-color);
            border: none;
            padding: 10px 20px;
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            border-bottom: 3px solid var(--primary-color);
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--primary-color);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar .logo span,
            .menu li a span {
                display: none;
            }
            
            .menu li a {
                justify-content: center;
                padding: 15px 0;
            }
            
            .menu li a i {
                margin-left: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-right: 70px;
            }
        }

        @media (max-width: 768px) {
            .user-info-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .trip-actions {
                flex-direction: column;
            }
            
            .trip-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .trip-meta {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* Styles pour les conseils de sécurité */
        .trip-safety-tips {
            position: relative;
            margin: 10px 0;
        }

        .safety-tips-toggle {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            color: #dc3545;
            padding: 5px;
        }

        .tips-content {
            background-color: #fff8e1;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            border-right: 4px solid #ffc107;
            display: none;
        }

        .tips-content h4 {
            color: #ff9800;
            margin-bottom: 10px;
        }

        .tips-text {
            line-height: 1.8;
            color: #5a3e00;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Barre latérale -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-tree"></i>
                <span>Tourisme Forestier</span>
            </div>
            <ul class="menu">
                <li><a href="user.php"><i class="fas fa-search"></i> Explorer les voyages</a></li>
                <li class="active"><a href="my_trips.php"><i class="fas fa-map-marked-alt"></i> Mes voyages</a></li>
                <li><a href="favorites.php"><i class="fas fa-heart"></i> Favoris</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profil</a></li>
                <li><a href="login.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </div>

        <!-- Contenu principal -->
        <div class="main-content">
            <!-- Barre d'information utilisateur -->
            <div class="user-info-bar">
                <div class="user-greeting">
                    <i class="fas fa-user"></i>
                    Bonjour, <a href="profile.php"><span><?= htmlspecialchars($user['username']) ?></span></a>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <!-- Icône de notifications -->
                    <div class="notification-dropdown">
                        <button class="btn position-relative" type="button" id="notificationDropdown" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell fs-5"></i>
                            <?php 
                            $unread_count = count(array_filter($notifications, function($n) { 
                                return !$n['is_read']; 
                            }));
                            if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $unread_count ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        
                        <ul class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header bg-light py-2">Notifications</h6></li>
                            
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item <?= $notification['is_read'] ? '' : 'bg-light' ?> py-3" href="#">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <small class="text-muted">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?>
                                                </small>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-primary">Nouveau</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-0"><?= htmlspecialchars($notification['message']) ?></p>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>
                                    <div class="dropdown-item text-center py-4">
                                        <i class="far fa-bell-slash fs-4 text-muted mb-2"></i>
                                        <p class="text-muted mb-0">Aucune notification</p>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                                
                    <!-- Menu utilisateur -->
                    <div class="dropdown">
                        <button class="btn dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <span><?= htmlspecialchars($user['username']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                           <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="login.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Onglets -->
            <ul class="nav nav-tabs" id="myTripsTabs" role="tablist">
                
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="participated-tab" data-bs-toggle="tab" data-bs-target="#participated" type="button" role="tab">
                        <i class="fas fa-calendar-check me-2"></i>Ma participation
                    </button>
                </li>
            </ul>

            <!-- Contenu des onglets -->
           
                <!-- Voyages organisés -->
                <div class="tab-pane fade show " id="organized" role="tabpanel">
                <?php if (empty($participated_trips)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Vous n'avez participé à aucun voyage pour le moment. 
                            <a href="index.php" class="alert-link">Explorer les voyages disponibles</a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($participated_trips as $trip): ?>
                                <div class="col-lg-6">
                                    <div class="trip-card">
                                        <div class="trip-header">
                                            <div>
                                                <h3 class="trip-title"><?= htmlspecialchars($trip['title']) ?></h3>
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if ($trip['booking_status'] == 'confirmed'): ?>
                                                        <span class="trip-status status-active">
                                                            <i class="fas fa-check-circle me-1"></i> Confirmé
                                                        </span>
                                                    <?php elseif ($trip['booking_status'] == 'pending'): ?>
                                                        <span class="trip-status" style="background-color: #fff3cd; color: #856404;">
                                                            <i class="fas fa-clock me-1"></i> En attente
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="trip-status status-cancelled">
                                                            <i class="fas fa-times-circle me-1"></i> Annulé
                                                        </span>
                                                    <?php endif; ?>
                                                    <small class="text-muted">
                                                        <?= $trip['participants'] ?> place<?= $trip['participants'] > 1 ? 's' : '' ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="trip-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?= htmlspecialchars($trip['location']) ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span><?= date('d/m/Y', strtotime($trip['start_date'])) ?> - <?= date('d/m/Y', strtotime($trip['end_date'])) ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-euro-sign"></i>
                                                <span><?= $trip['price'] ?> € / personne</span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($trip['safety_tips'])): ?>
    <div class="trip-safety-tips">
        <button class="safety-tips-toggle" type="button">
            <i class="fas fa-exclamation-triangle"></i> Conseils de sécurité
        </button>
        <div class="tips-content">
            <div class="tips-text">
                <?= nl2br(htmlspecialchars($trip['safety_tips'])) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="trip-actions">
    <a href="trip_details.php?id=<?= $trip['id'] ?>" class="btn btn-outline-primary">
        <i class="fas fa-eye me-2"></i> Voir détails
    </a>
    <button class="btn btn-danger" onclick="deleteBooking(<?= $trip['booking_id'] ?>, '<?= htmlspecialchars($trip['title'], ENT_QUOTES) ?>')">
        <i class="fas fa-trash-alt me-2"></i> Supprimer
    </button>
</div>
                                      
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
               
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Afficher/masquer les conseils de sécurité
        document.querySelectorAll('.safety-tips-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const tipsContent = this.nextElementSibling;
                if (tipsContent.style.display === 'none' || !tipsContent.style.display) {
                    tipsContent.style.display = 'block';
                    this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Masquer les conseils';
                } else {
                    tipsContent.style.display = 'none';
                    this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Conseils de sécurité';
                }
            });
        });

        // Fonction pour annuler une réservation
        function cancelBooking(bookingId) {
            if (confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')) {
                fetch('cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `booking_id=${bookingId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Une erreur est survenue');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur de connexion');
                });
            }
        }

        function deleteBooking(bookingId, tripTitle) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer définitivement votre réservation pour "${tripTitle}" ? Cette action est irréversible.`)) {
        fetch('cancel_booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>'
            },
            body: JSON.stringify({ booking_id: bookingId })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw err; });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                throw data;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message || 'Une erreur est survenue lors de la suppression');
        });
    }
}
    </script>
</body>
</html>