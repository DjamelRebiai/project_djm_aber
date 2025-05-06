<?php
session_start();
require 'config.php';

// Vérification des permissions admin/organizer
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] != 'admin' && $_SESSION['user_type'] != 'organizer')) {
    header('Location: login.php');
    exit();
}

// Récupération des voyages
try {
    $query = "SELECT t.*, u.username as organizer_name 
              FROM trips t
              JOIN users u ON t.organizer_id = u.id";
    
    // Si c'est un organisateur, ne montrer que ses propres voyages
    if ($_SESSION['user_type'] == 'organizer') {
        $query .= " WHERE t.organizer_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    }
    
    $trips = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr"  dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Voyages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .trip-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
        .action-btns .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            margin-left: 5px;
        }
        .trip-img {
            height: 180px;
            object-fit: cover;
            width: 100%;
        }

        :root {
            --primary-dark: #1A3C27;
            --primary: #2D5E3A;
            --primary-light: #4A8C5E;
            --secondary: #3A7D44;
            --accent: #A78A6D;
            --light: #F8F9F8;
            --dark: #2C3E2D;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }

        /* Barre latérale */
        .sidebar {
    background: linear-gradient(180deg, var(--primary-dark), var(--primary));
    color: white;
    min-height: 100vh;
    width: 280px;
    position: fixed;
    right: 0;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1); 
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
            margin-right: 10px;
        }

        /* Contenu principal */
        .main-content {
            margin-right :290px;
            margin-left: 0px;
            padding: 20px;
            transition: all 0.3s;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar .nav-link span {
                display: none;
            }
            
            .sidebar .nav-link i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .sidebar-header h4 {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Barre latérale -->
        <div class="sidebar">
            <div class="sidebar-header d-flex align-items-center">
                <i class="fas fa-tree fa-2x me-2"></i>
                <h4 class="m-0">Tourisme Forestier</h4>
            </div>
            <ul class="nav flex-column p-3">
                <li class="nav-item">
                    <a class="nav-link" href="admin.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users_management.php">
                        <i class="fas fa-users"></i>
                        <span>Gestion Utilisateurs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="trips_management.php">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Gestion Voyages</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings_management.php">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Gestion Réservations</span>
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
        
        <!-- Contenu principal -->
        <div class="main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-map-marked-alt me-2"></i>Gestion des Voyages</h1>
                <a href="trip_add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Nouveau Voyage
                </a>
            </div>
            
            <!-- Messages d'alerte -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>
            
            <!-- Filtres -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Statut</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">Tous</option>
                                <option value="pending" <?= isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : '' ?>>En attente</option>
                                <option value="approved" <?= isset($_GET['status']) && $_GET['status'] == 'approved' ? 'selected' : '' ?>>Approuvé</option>
                                <option value="rejected" <?= isset($_GET['status']) && $_GET['status'] == 'rejected' ? 'selected' : '' ?>>Rejeté</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Date de début</label>
                            <input type="date" id="date_from" name="date_from" class="form-control" value="<?= $_GET['date_from'] ?? '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Date de fin</label>
                            <input type="date" id="date_to" name="date_to" class="form-control" value="<?= $_GET['date_to'] ?? '' ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrer
                            </button>
                            <a href="trips_management.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Liste des voyages -->
            <div class="row">
                <?php foreach ($trips as $trip): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 trip-card">
                        <?php if ($trip['featured_image']): ?>
                        <img src="<?= htmlspecialchars($trip['featured_image']) ?>" class="card-img-top trip-img" alt="<?= htmlspecialchars($trip['title']) ?>">
                        <?php else: ?>
                        <div class="card-img-top trip-img bg-secondary d-flex align-items-center justify-content-center">
                            <i class="fas fa-mountain fa-3x text-white"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="card-title mb-2"><?= htmlspecialchars($trip['title']) ?></h5>
                                <span class="badge bg-<?= 
                                    $trip['status'] == 'approved' ? 'success' : 
                                    ($trip['status'] == 'rejected' ? 'danger' : 'warning text-dark') 
                                ?>">
                                    <?= ucfirst($trip['status']) ?>
                                </span>
                            </div>
                            
                            <p class="card-text text-muted small mb-2">
                                <i class="fas fa-user me-1"></i> Organisateur: <?= htmlspecialchars($trip['organizer_name']) ?>
                            </p>
                            
                            <p class="card-text text-muted small mb-2">
                                <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($trip['location']) ?>
                            </p>
                            
                            <p class="card-text text-muted small mb-3">
                                <i class="fas fa-calendar-alt me-1"></i> 
                                <?= date('d/m/Y', strtotime($trip['start_date'])) ?> - <?= date('d/m/Y', strtotime($trip['end_date'])) ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary"><?= number_format($trip['price'], 2) ?> €</span>
                                <div class="action-btns">
                                    <a href="trip_details.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="trip_edit.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($_SESSION['user_type'] == 'admin'): ?>
                                    <form method="post" action="trip_change_status.php" class="d-inline">
                                        <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        
                                        <?php if ($trip['status'] != 'approved'): ?>
                                        <button type="submit" name="status" value="approved" class="btn btn-sm btn-outline-success" title="Approuver">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($trip['status'] != 'rejected'): ?>
                                        <button type="submit" name="status" value="rejected" class="btn btn-sm btn-outline-danger" title="Rejeter">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Précédent</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialisation des tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>