<?php
// Enable error display for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    // Verify admin privileges
    $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_type = $stmt->fetchColumn();

    if ($user_type !== 'admin') {
        header("Location: login.php");
        exit();
    }

    // Get statistics
    $stats = [
        'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'trips' => $pdo->query("SELECT COUNT(*) FROM trips")->fetchColumn(),
        'bookings' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
        'pending_trips' => $pdo->query("SELECT COUNT(*) FROM trips WHERE approved = FALSE")->fetchColumn(),
        'active_trips' => $pdo->query("SELECT COUNT(*) FROM trips WHERE status = 'actif'")->fetchColumn(),
        'completed_trips' => $pdo->query("SELECT COUNT(*) FROM trips WHERE status = 'complet'")->fetchColumn()
    ];

    // Get pending trips with organizer info
    $pending_trips_list = $pdo->query("
        SELECT t.*, u.username as organizer_name 
        FROM trips t
        JOIN users u ON t.organizer_id = u.id
        WHERE t.approved = FALSE
        ORDER BY t.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get all trips with organizer info
    $trips = $pdo->query("
        SELECT t.*, u.username as organizer_name 
        FROM trips t
        JOIN users u ON t.organizer_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get recent bookings
    $recent_bookings = $pdo->query("
        SELECT b.*, u.username as user_name, t.title as trip_title
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN trips t ON b.trip_id = t.id
        ORDER BY b.booking_date DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get current user data
    $currentUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $currentUser->execute([$_SESSION['user_id']]);
    $user_data = $currentUser->fetch(PDO::FETCH_ASSOC);

    // Get notifications
    $notifications_stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? OR user_id = 0
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $notifications_stmt->execute([$_SESSION['user_id']]);
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark notifications as read
    $update_stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE user_id = ? AND is_read = FALSE
    ");
    $update_stmt->execute([$_SESSION['user_id']]);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - نظام السياحة الغابية</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1A3C27;
            --primary: #2D5E3A;
            --primary-light: #4A8C5E;
            --secondary: #3A7D44;
            --accent: #A78A6D;
            --light: #F8F9F8;
            --dark: #2C3E2D;
            --text-dark: #2C3E2D;
            --text-medium: #556B5A;
            --text-light: #7F8C8D;
            --border-color: #DFE6E0;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
        }

        /* الشريط الجانبي */
        .sidebar {
            background: linear-gradient(180deg, var(--primary-dark), var(--primary));
            color: white;
            min-height: 100vh;
            width: 280px;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 5px;
            margin-bottom: 5px;
            padding: 12px 15px;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link i {
            width: 24px;
            text-align: center;
            margin-left: 10px;
        }

        /* المحتوى الرئيسي */
        .main-content {
            margin-right: 280px;
            padding: 20px;
            transition: all 0.3s;
        }

        /* شريط العناوين العلوي */
        .top-bar {
            background-color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        /* بطاقات الإحصائيات */
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-right: 4px solid var(--primary);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card .icon {
            font-size: 2rem;
            color: var(--primary);
        }

        /* بطاقات الرحلات */
        .trip-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            height: 100%;
        }

        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .trip-card .card-img-top {
            height: 160px;
            object-fit: cover;
        }

        .trip-card .badge-status {
            position: absolute;
            top: 10px;
            left: 10px;
        }

        /* الجداول */
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: var(--primary);
            color: white;
            border-bottom: none;
        }

        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* الأزرار */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        /* القوائم */
        .list-group-item {
            border-radius: 8px !important;
            margin-bottom: 10px;
            border: 1px solid var(--border-color);
        }

        /* التنبيهات */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
        }

        /* التكيف مع الشاشات الصغيرة */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                overflow: hidden;
            }
            
            .sidebar .nav-link span {
                display: none;
            }
            
            .sidebar .nav-link i {
                margin-left: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-right: 80px;
            }
            
            .sidebar-header h4 {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- الشريط الجانبي -->
        <div class="sidebar">
            <div class="sidebar-header d-flex align-items-center">
                <i class="fas fa-tree fa-2x me-2"></i>
                <h4 class="m-0"> Tourisme forestier</h4>
            </div>
            <ul class="nav flex-column p-3">
                <li class="nav-item">
                    <a class="nav-link active" href="admin.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span> Panneau de contrôle</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users_management.php">
                        <i class="fas fa-users"></i>
                        <span> Gérer les utilisateurs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="trips_management.php">
                        <i class="fas fa-map-marked-alt"></i>
                        <span> Gestion des voyages</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings_management.php">
                        <i class="fas fa-ticket-alt"></i>
                        <span> Gérer les réservations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span> Rapports</span>
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="login.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>logout </span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <!-- شريط العناوين العلوي -->
            <div class="top-bar d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Panneau de contrôle</h4>
                
                <div class="d-flex align-items-center gap-3">
                    <!-- إشعارات -->
                    <div class="dropdown position-relative">
                        <button class="btn position-relative" id="notificationDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-bell fs-5"></i>
                            <?php if(count(array_filter($notifications, fn($n) => !$n['is_read'])) > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                    <?= count(array_filter($notifications, fn($n) => !$n['is_read'])) ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-0" style="width: 350px;">
                            <li><h6 class="dropdown-header bg-light py-2"> Notifications</h6></li>
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item <?= $notification['is_read'] ? '' : 'bg-light' ?> py-3" href="#">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="text-muted">
                                                    <?= date('Y-m-d H:i', strtotime($notification['created_at'])) ?>
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
                                        <p class="text-muted mb-0"> Aucune notification</p>
                                    </div>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider m-0"></li>
                            <li>
                                <a class="dropdown-item text-center py-2" href="notifications.php">
                                    عرض الكل
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- معلومات المستخدم -->
                    <?php if($user_data): ?>
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i>
                                <span><?= htmlspecialchars($user_data['full_name'] ?? $user_data['username']) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header"> Informations sur l'utilisateur</h6></li>
                                <li>
                                    <div class="dropdown-item">
                                        <small class="text-muted"> Nom d'utilisateur :</small>
                                        <div><?= htmlspecialchars($user_data['username']) ?></div>
                                    </div>
                                </li>
                                <li>
                                    <div class="dropdown-item">
                                        <small class="text-muted"> Email :</small>
                                        <div><?= htmlspecialchars($user_data['email']) ?></div>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="login.php">
                                        <i class="fas fa-sign-out-alt me-1"></i> logout 
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- بطاقات الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2"> Total des utilisateurs</h6>
                                <h3 class="mb-0"><?= $stats['users'] ?></h3>
                                <small class="text-muted"> +5% par rapport au mois dernier</small>
                            </div>
                            <div class="icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2"> Total des vols </h6>
                                <h3 class="mb-0"><?= $stats['trips'] ?></h3>
                                <small class="text-muted">  +12% par rapport au mois dernier</small>
                            </div>
                            <div class="icon">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total des réservations</h6>
                                <h3 class="mb-0"><?= $stats['bookings'] ?></h3>
                                <small class="text-muted"> +8% par rapport au mois dernier</small>
                            </div>
                            <div class="icon">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2"> Vols en attente</h6>
                                <h3 class="mb-0"><?= $stats['pending_trips'] ?></h3>
                                <small class="text-muted"> Nécessite un examen</small>
                            </div>
                            <div class="icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- قسم الرحلات قيد الانتظار -->
            <?php if (!empty($pending_trips_list)): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"> Vols en attente</h5>
                    <small> Nécessite une approbation</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="60">#</th>
                                    <th> Le titre du voyage</th>
                                    <th>Localisation</th>
                                    <th>Organisateur</th>
                                    <th width="120"> Date de début</th>
                                    <th width="150"> Procédures</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_trips_list as $trip): ?>
                                <tr>
                                    <td><?= $trip['id'] ?></td>
                                    <td><?= htmlspecialchars($trip['title']) ?></td>
                                    <td><?= htmlspecialchars($trip['location']) ?></td>
                                    <td><?= htmlspecialchars($trip['organizer_name']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($trip['start_date'])) ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="trip_details.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir les détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="post" action="accepte_trip.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-outline-success" title="Accepter">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="post" action="process_trip.php" class="d-inline">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
    <input type="hidden" name="action" value="delete">
    <button type="submit" class="btn btn-sm btn-outline-danger" 
            title="Supprimer" 
            onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement ce vol ? Cette action ne peut pas être annulée !')">
        <i class="fas fa-trash-alt"></i> 
    </button>
</form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- أحدث الرحلات والحجوزات -->
            <div class="row">
                <!-- أحدث الرحلات -->
                <div class="col-lg-8 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"> Derniers vols</h5>
                            <a href="trips_management.php" class="btn btn-sm btn-light"> Voir tout</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($trips)): ?>
                                <div class="row">
                                    <?php foreach ($trips as $trip): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="trip-card card h-100">
                                            <div class="position-relative">
                                                <?php if (!empty($trip['featured_image'])): ?>
                                                    <img src="<?= htmlspecialchars($trip['featured_image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($trip['title']) ?>">
                                                <?php else: ?>
                                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 160px;">
                                                        <i class="fas fa-tree fa-3x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="badge <?= $trip['approved'] ? 'bg-success' : 'bg-warning' ?> position-absolute top-10 start-10">
                                                    <?= $trip['approved'] ? 'Certifié' : 'En attente ' ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($trip['title']) ?></h5>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                                    <small class="text-muted"><?= htmlspecialchars($trip['location']) ?></small>
                                                </div>
                                                <div class="d-flex align-items-center mb-3">
                                                    <i class="fas fa-user text-muted me-2"></i>
                                                    <small class="text-muted"><?= htmlspecialchars($trip['organizer_name']) ?></small>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-light text-dark">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?= date('Y-m-d', strtotime($trip['start_date'])) ?>
                                                    </span>
                                                    <a href="trip_details.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-primary">
                                                    Détails <i class="fas fa-arrow-left ms-1"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-map-marked-alt fa-3x text-muted mb-3"></i>
                                    <p class="text-muted"> Pas de vols enregistrés</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- أحدث الحجوزات -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"> Dernières réservations</h5>
                            <a href="bookings_management.php" class="btn btn-sm btn-light"> Voir tout</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_bookings)): ?>
                                <div class="list-group">
                                    <?php foreach ($recent_bookings as $booking): ?>
                                    <div class="list-group-item border-0 mb-2 rounded shadow-sm">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <strong class="flex-grow-1"><?= htmlspecialchars($booking['trip_title']) ?></strong>
                                            <span class="badge <?= $booking['status'] == 'confirmed' ? 'bg-success' : ($booking['status'] == 'pending' ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= $booking['status'] == 'confirmed' ? 'Confirmé' : ($booking['status'] == 'pending' ? 'En attente ' : 'Annulé') ?>
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-user text-muted me-2"></i>
                                            <small><?= htmlspecialchars($booking['user_name']) ?></small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="far fa-clock text-muted me-2"></i>
                                            <small class="text-muted"><?= date('Y-m-d H:i', strtotime($booking['booking_date'])) ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                    <p class="text-muted"> Pas de réservations</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // تفعيل عناصر Bootstrap
    document.addEventListener('DOMContentLoaded', function() {
        // تفعيل الأدوات المساعدة
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // تفعيل القوائم المنسدلة
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
    });
    </script>
</body>
</html>