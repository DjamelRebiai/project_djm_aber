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

    // Get statistics for reports
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'new_users_this_month' => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn(),
        'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn(),
        
        'total_trips' => $pdo->query("SELECT COUNT(*) FROM trips")->fetchColumn(),
        'approved_trips' => $pdo->query("SELECT COUNT(*) FROM trips WHERE approved = TRUE")->fetchColumn(),
        'pending_trips' => $pdo->query("SELECT COUNT(*) FROM trips WHERE approved = FALSE")->fetchColumn(),
        'active_trips' => $pdo->query("SELECT COUNT(*) FROM trips WHERE status = 'actif'")->fetchColumn(),
        'completed_trips' => $pdo->query("SELECT COUNT(*) FROM trips WHERE status = 'complet'")->fetchColumn(),
        
        'total_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
        'confirmed_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn(),
        'pending_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn(),
        'cancelled_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn(),
        'revenue' => $pdo->query("SELECT SUM(price) FROM bookings WHERE status = 'confirmed'")->fetchColumn() ?? 0,
    ];

    // Get monthly user registration data for chart
    $monthly_users = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS count
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly trip creation data for chart
    $monthly_trips = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS count
        FROM trips
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly booking data for chart
    $monthly_bookings = $pdo->query("
        SELECT 
            DATE_FORMAT(booking_date, '%Y-%m') AS month,
            COUNT(*) AS count,
            SUM(price) AS revenue
        FROM bookings
        WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get popular trips
    $popular_trips = $pdo->query("
        SELECT 
            t.id,
            t.title,
            t.location,
            COUNT(b.id) AS bookings_count,
            SUM(b.price) AS total_revenue
        FROM trips t
        LEFT JOIN bookings b ON t.id = b.trip_id
        GROUP BY t.id
        ORDER BY bookings_count DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get active organizers
    $active_organizers = $pdo->query("
        SELECT 
            u.id,
            u.username,
            u.full_name,
            COUNT(t.id) AS trips_count,
            COUNT(b.id) AS bookings_count
        FROM users u
        JOIN trips t ON u.id = t.organizer_id
        LEFT JOIN bookings b ON t.id = b.trip_id
        WHERE u.user_type = 'organizer'
        GROUP BY u.id
        ORDER BY trips_count DESC
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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير والإحصائيات - نظام السياحة الغابية</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css" rel="stylesheet">
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

        /* الرسوم البيانية */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
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
                    <a class="nav-link" href="admin.php">
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
                    <a class="nav-link active" href="reports.php">
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
                <h4 class="mb-0"> Rapports et statistiques</h4>
                
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
                                <h3 class="mb-0"><?= $stats['total_users'] ?></h3>
                                <small class="text-muted"> +<?= round($stats['new_users_this_month'] / max(1, $stats['total_users'] - $stats['new_users_this_month']) * 100) ?>% ce mois</small>
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
                                <h6 class="text-muted mb-2"> Total des vols</h6>
                                <h3 class="mb-0"><?= $stats['total_trips'] ?></h3>
                                <small class="text-muted"> <?= $stats['approved_trips'] ?> approuvés</small>
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
                                <h6 class="text-muted mb-2"> Total des réservations</h6>
                                <h3 class="mb-0"><?= $stats['total_bookings'] ?></h3>
                                <small class="text-muted"> <?= $stats['confirmed_bookings'] ?> confirmées</small>
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
                                <h6 class="text-muted mb-2"> Revenu total</h6>
                                <h3 class="mb-0"><?= number_format($stats['revenue'], 2) ?> MAD</h3>
                                <small class="text-muted"> Revenu des réservations</small>
                            </div>
                            <div class="icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- الرسوم البيانية -->
            <div class="row mb-4">
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"> Inscription des utilisateurs (6 derniers mois)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="usersChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"> Réservations et revenus (6 derniers mois)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="bookingsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- الرحلات الشائعة والمنظمون النشطون -->
            <div class="row mb-4">
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"> Vols populaires</h5>
                            <small> Par nombre de réservations</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th> Le titre du voyage</th>
                                            <th>Localisation</th>
                                            <th> Réservations</th>
                                            <th> Revenu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($popular_trips as $trip): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($trip['title']) ?></td>
                                            <td><?= htmlspecialchars($trip['location']) ?></td>
                                            <td><?= $trip['bookings_count'] ?></td>
                                            <td><?= number_format($trip['total_revenue'] ?? 0, 2) ?> MAD</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"> Organisateurs actifs</h5>
                            <small> Par nombre de vols</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th> Nom</th>
                                            <th> Nom d'utilisateur</th>
                                            <th> Vols</th>
                                            <th> Réservations</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_organizers as $organizer): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($organizer['full_name']) ?></td>
                                            <td><?= htmlspecialchars($organizer['username']) ?></td>
                                            <td><?= $organizer['trips_count'] ?></td>
                                            <td><?= $organizer['bookings_count'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- تفاصيل إضافية -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"> Statut des vols</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="tripsStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"> Statut des réservations</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="bookingsStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
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

        // تحضير بيانات الرسوم البيانية
        const monthlyUsersData = {
            labels: <?= json_encode(array_column($monthly_users, 'month')) ?>,
            datasets: [{
                label: 'Nouveaux utilisateurs',
                data: <?= json_encode(array_column($monthly_users, 'count')) ?>,
                backgroundColor: 'rgba(45, 94, 58, 0.2)',
                borderColor: 'rgba(45, 94, 58, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        };

        const monthlyBookingsData = {
            labels: <?= json_encode(array_column($monthly_bookings, 'month')) ?>,
            datasets: [
                {
                    label: 'Nombre de réservations',
                    data: <?= json_encode(array_column($monthly_bookings, 'count')) ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    yAxisID: 'y'
                },
                {
                    label: 'Revenu (MAD)',
                    data: <?= json_encode(array_column($monthly_bookings, 'revenue')) ?>,
                    backgroundColor: 'rgba(23, 162, 184, 0.2)',
                    borderColor: 'rgba(23, 162, 184, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    yAxisID: 'y1'
                }
            ]
        };

        const tripsStatusData = {
            labels: ['Approuvés', 'En attente', 'Actifs', 'Terminés'],
            datasets: [{
                data: [
                    <?= $stats['approved_trips'] ?>,
                    <?= $stats['pending_trips'] ?>,
                    <?= $stats['active_trips'] ?>,
                    <?= $stats['completed_trips'] ?>
                ],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(255, 193, 7, 0.7)',
                    'rgba(0, 123, 255, 0.7)',
                    'rgba(108, 117, 125, 0.7)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(0, 123, 255, 1)',
                    'rgba(108, 117, 125, 1)'
                ],
                borderWidth: 1
            }]
        };

        const bookingsStatusData = {
            labels: ['Confirmées', 'En attente', 'Annulées'],
            datasets: [{
                data: [
                    <?= $stats['confirmed_bookings'] ?>,
                    <?= $stats['pending_bookings'] ?>,
                    <?= $stats['cancelled_bookings'] ?>
                ],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(255, 193, 7, 0.7)',
                    'rgba(220, 53, 69, 0.7)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        };

        // إنشاء الرسوم البيانية
        new Chart(document.getElementById('usersChart'), {
            type: 'line',
            data: monthlyUsersData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('bookingsChart'), {
            type: 'bar',
            data: monthlyBookingsData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Nombre de réservations'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenu (MAD)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('tripsStatusChart'), {
            type: 'doughnut',
            data: tripsStatusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        new Chart(document.getElementById('bookingsStatusChart'), {
            type: 'pie',
            data: bookingsStatusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    });
    </script>
</body>
</html>