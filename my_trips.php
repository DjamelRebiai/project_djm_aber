<?php
session_start();
require_once 'config.php';

// Vérification de la connexion et du type d'utilisateur
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_type = $stmt->fetchColumn();

if ($user_type !== 'organizer') {
    header("Location: organizer.php");
    exit();
}

// Récupération des données de l'organisateur
$organizer = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$organizer->execute([$_SESSION['user_id']]);
$organizer_data = $organizer->fetch(PDO::FETCH_ASSOC);

// Récupération des voyages avec statistiques
$trips = $pdo->prepare("
    SELECT t.*, 
           COUNT(b.id) as bookings_count,
           SUM(CASE WHEN b.status = 'confirmed' THEN b.participants ELSE 0 END) as total_participants,
           SUM(CASE WHEN b.status = 'confirmed' THEN t.price * b.participants ELSE 0 END) as total_revenue
    FROM trips t
    LEFT JOIN bookings b ON t.id = b.trip_id
    WHERE t.organizer_id = ?
    GROUP BY t.id
    ORDER BY t.start_date DESC
");
$trips->execute([$_SESSION['user_id']]);
$organizer_trips = $trips->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Voyages - <?= htmlspecialchars($organizer_data['full_name']) ?></title>
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
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            margin-bottom: 1.5rem;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 0.75rem 0.75rem 0 0 !important;
        }
        
        .trip-card-img {
            height: 200px;
            object-fit: cover;
            border-radius: 0.5rem;
        }
        
        .badge-participants {
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
        }
        
        .upcoming {
            background-color: #E3F2FD;
            color: #0D47A1;
        }
        
        .ongoing {
            background-color: #E8F5E9;
            color: #1B5E20;
        }
        
        .completed {
            background-color: #F5F5F5;
            color: #424242;
        }
        
        .action-btn {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(100%);
                z-index: 1000;
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
            <img src="<?= htmlspecialchars($organizer_data['profile_pic'] ?: 'default_profile.jpg') ?>" 
                 class="profile-img mb-3">
            <h5 class="mb-1"><?= htmlspecialchars($organizer_data['full_name']) ?></h5>
            <small class="text-white-50">Organisateur de voyages</small>
        </div>
        <ul class="nav flex-column px-2">
            <li class="nav-item">
                <a class="nav-link" href="organizer.php">
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
                <a class="nav-link active" href="my_trips.php">
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
                <h3>Mes Voyages</h3>
                <p class="text-muted mb-0">Gérez vos randonnées et excursions</p>
            </div>
            <a href="add_trip.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Nouveau voyage
            </a>
        </div>
        
        <!-- Filter Options -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Statut</label>
                        <select class="form-select">
                            <option value="all">Tous les voyages</option>
                            <option value="upcoming">À venir</option>
                            <option value="ongoing">En cours</option>
                            <option value="completed">Terminés</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date de début</label>
                        <input type="date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Recherche</label>
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Rechercher...">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Trips List -->
        <?php if(!empty($organizer_trips)): ?>
            <div class="row">
                <?php foreach($organizer_trips as $trip): 
                    // Déterminer le statut du voyage
                    $today = date('Y-m-d');
                    $status = '';
                    if ($today < $trip['start_date']) {
                        $status = 'upcoming';
                    } elseif ($today >= $trip['start_date'] && $today <= $trip['end_date']) {
                        $status = 'ongoing';
                    } else {
                        $status = 'completed';
                    }
                ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?= htmlspecialchars($trip['title']) ?></h5>
                            <span class="status-badge <?= $status ?>">
                                <?= $status == 'upcoming' ? 'À venir' : 
                                   ($status == 'ongoing' ? 'En cours' : 'Terminé') ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-5">
                                    <img src="<?= htmlspecialchars($trip['featured_image'] ?: 'default_trip.jpg') ?>" 
                                         class="trip-card-img w-100 mb-3 mb-md-0">
                                </div>
                                <div class="col-md-7">
                                    <p class="text-muted mb-3">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?= htmlspecialchars($trip['location']) ?>
                                    </p>
                                    <p class="mb-3"><?= htmlspecialchars(substr($trip['description'], 0, 150)) ?>...</p>
                                    
                                    <div class="d-flex justify-content-between mb-3">
                                        <div>
                                            <small class="text-muted d-block">Date de début</small>
                                            <strong><?= date('d/m/Y', strtotime($trip['start_date'])) ?></strong>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Date de fin</small>
                                            <strong><?= date('d/m/Y', strtotime($trip['end_date'])) ?></strong>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Prix</small>
                                            <strong><?= number_format($trip['price'], 2) ?> €</strong>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-primary me-2">
                                                <i class="fas fa-users me-1"></i>
                                                <?= $trip['bookings_count'] ?> réservations
                                            </span>
                                            <span class="badge badge-participants">
                                                <i class="fas fa-user me-1"></i>
                                                <?= $trip['total_participants'] ?> participants
                                            </span>
                                        </div>
                                        <div>
                                            <span class="badge bg-success">
                                                <i class="fas fa-euro-sign me-1"></i>
                                                <?= number_format($trip['total_revenue'], 2) ?> €
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 d-flex justify-content-between">
                            <a href="trip_details.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> Détails
                            </a>
                            <div class="d-flex gap-2">
                                <a href="edit_trip.php?id=<?= $trip['id'] ?>" 
                                   class="action-btn btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="organizer_bookings.php?id=<?= $trip['id'] ?>" 
                                   class="action-btn btn btn-sm btn-outline-success">
                                    <i class="fas fa-ticket-alt"></i>
                                </a>
                                <a href="delete_trip.php?id=<?= $trip['id'] ?>" 
                                   class="action-btn btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce voyage ?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-map-marked-alt fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">Aucun voyage créé</h4>
                    <p class="text-muted mb-4">Commencez par créer votre premier voyage</p>
                    <a href="add_trip.php" class="btn btn-primary px-4">
                        <i class="fas fa-plus me-1"></i> Créer un voyage
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
        
        // Filter trips by status
        document.querySelector('select').addEventListener('change', function() {
            const status = this.value;
            const trips = document.querySelectorAll('.col-lg-6');
            
            trips.forEach(trip => {
                const tripStatus = trip.querySelector('.status-badge').className.includes('upcoming') ? 'upcoming' : 
                                  trip.querySelector('.status-badge').className.includes('ongoing') ? 'ongoing' : 'completed';
                
                if (status === 'all' || tripStatus === status) {
                    trip.style.display = 'block';
                } else {
                    trip.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>