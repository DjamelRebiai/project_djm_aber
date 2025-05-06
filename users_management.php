<?php
session_start();
require 'config.php';

// Vérification des permissions admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Récupération des utilisateurs
try {
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
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
          
        }

        /* Contenu principal */
        .main-content {
            margin-right :300px;
            
            padding: 20px;
            transition: all 0.3s;
           
    width: 1200px;
          
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            
        }

        .action-btns .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
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
                    <a class="nav-link active" href="users_management.php">
                        <i class="fas fa-users"></i>
                        <span>Gestion des Utilisateurs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="trips_management.php">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Gestion des Voyages</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings_management.php">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Gestion des Réservations</span>
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
            
            
            <h1 class="h2 mb-4"><i class="fas fa-users me-2"></i>Gestion des Utilisateurs</h1>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>
            
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Liste des Utilisateurs</h5>
                    <a href="user_add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Nouvel Utilisateur
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Date d'inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <img src="<?= htmlspecialchars($user['profile_pic'] ?? 'uploads/trip_5_1746209965.png') ?>" 
                                             class="user-avatar" alt="Photo de profil">
                                    </td>
                                    <td><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $user['user_type'] == 'admin' ? 'danger' : 
                                            ($user['user_type'] == 'organizer' ? 'warning text-dark' : 'info') 
                                        ?>">
                                            <?= ucfirst($user['user_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                    <td class="action-btns">
                                        <a href="user_edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" action="user_delete.php" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    title="Supprimer" onclick="return confirm('Confirmer la suppression?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
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