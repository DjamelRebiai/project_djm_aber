<?php
session_start();
require 'config.php';

// Vérification des permissions admin/organizer
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] != 'admin' && $_SESSION['user_type'] != 'organizer')) {
    header('Location: login.php');
    exit();
}

// Récupération des réservations avec le prix
try {
    $query = "SELECT b.*, u.username, u.email, t.title as trip_title, t.price as trip_price 
              FROM bookings b
              JOIN users u ON b.user_id = u.id
              JOIN trips t ON b.trip_id = t.id";
    
    // Si c'est un organisateur, ne montrer que ses propres voyages
    if ($_SESSION['user_type'] == 'organizer') {
        $query .= " WHERE t.organizer_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    }
    
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Réservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
        .action-btns .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
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

    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header d-flex align-items-center">
                <i class="fas fa-tree fa-2x me-2"></i>
                <h4 class="m-0">Tourisme forestier</h4>
            </div>
            <ul class="nav flex-column p-3">
                <li class="nav-item">
                    <a class="nav-link" href="admin.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Panneau de contrôle</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users_management.php">
                        <i class="fas fa-users"></i>
                        <span>Gérer les utilisateurs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="trips_management.php">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Gestion des voyages</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="bookings_management.php">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Gérer les réservations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Rapports</span>
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="login.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">

            
            <h1 class="h2 mb-4"><i class="fas fa-ticket-alt me-2"></i>Gestion des Réservations</h1>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>
            
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Liste des Réservations</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Voyage</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Participants</th>
                                    <th>Prix</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>#<?= $booking['id'] ?></td>
                                    <td><?= htmlspecialchars($booking['trip_title']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($booking['username']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($booking['email']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($booking['booking_date'])) ?></td>
                                    <td><?= $booking['participants'] ?></td>
                                    <td><?= number_format($booking['trip_price'], 2) ?> MAD</td>
                                    <td>
                                        <span class="badge status-badge bg-<?= 
                                            $booking['status'] == 'confirmed' ? 'success' : 
                                            ($booking['status'] == 'cancelled' ? 'danger' : 'warning text-dark') 
                                        ?>">
                                            <?= ucfirst($booking['status']) ?>
                                        </span>
                                    </td>
                                    <td class="action-btns">
                                        <a href="booking_view.php?id=<?= $booking['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($booking['status'] == 'pending'): ?>
                                            <form method="post" action="booking_update.php" class="d-inline">
                                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Confirmer">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="post" action="booking_update.php" class="d-inline">
                                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Annuler">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>