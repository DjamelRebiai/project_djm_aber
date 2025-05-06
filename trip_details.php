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

// Récupération de l'ID du voyage depuis l'URL
$trip_id = $_GET['id'] ?? 0;

try {
    // Récupération des détails du voyage
    $trip_stmt = $pdo->prepare("
        SELECT t.*, u.username as organizer_name, u.profile_pic as organizer_pic
        FROM trips t
        JOIN users u ON t.organizer_id = u.id
        WHERE t.id = ?
    ");
    $trip_stmt->execute([$trip_id]);
    $trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);

    // Vérification que le voyage existe
    if (!$trip) {
        header("Location: login.php");
        exit();
    }

    // Vérification des permissions (organisateur ou admin)
    $is_owner = ($trip['organizer_id'] == $_SESSION['user_id']);
    $is_admin = ($user_type == 'admin');
    $user = ($user_type == 'user');
    
    if (!$is_owner && !$is_admin && !$user) {
        header("Location: login.php");
        exit();
    }

    // Récupération des activités du voyage
    $activities_stmt = $pdo->prepare("SELECT activity FROM trip_activities WHERE trip_id = ?");
    $activities_stmt->execute([$trip_id]);
    $activities = $activities_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Récupération des médias du voyage
    $media_stmt = $pdo->prepare("
        SELECT * FROM media 
        WHERE trip_id = ? 
        ORDER BY upload_date DESC
    ");
    $media_stmt->execute([$trip_id]);
    $trip_media = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupération des réservations
    $bookings_stmt = $pdo->prepare("
        SELECT b.*, u.username, u.full_name, u.email
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.trip_id = ?
        ORDER BY b.booking_date DESC
    ");
    $bookings_stmt->execute([$trip_id]);
    $bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques des réservations
    $confirmed_bookings = array_filter($bookings, fn($b) => $b['status'] == 'confirmed');
    $pending_bookings = array_filter($bookings, fn($b) => $b['status'] == 'pending');
    $total_participants = array_sum(array_column($confirmed_bookings, 'participants'));

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
$user_type = [
    'organizer' => ($is_owner || $is_admin) // true إذا كان منظم أو مسؤول
];
?>

<!DOCTYPE html>
<html lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($trip['title']) ?> - Détails du voyage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2E7D32;
            --secondary-color: #689F38;
            --dark-color: #1B5E20;
            --light-color: #C8E6C9;
            --text-dark: #2C3E2D;
            --text-muted: #6C757D;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Tajawal', sans-serif;
            color: var(--text-dark);
            direction: rtl;
        }
        
        .trip-header {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), 
                        url('<?= htmlspecialchars($trip['featured_image']) ?>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 5rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            position: relative;
        }
        
        .trip-header-content {
            position: relative;
            z-index: 2;
        }
        
        .trip-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.5);
        }
        
        .trip-meta {
            display: flex;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .trip-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }
        
        .trip-meta-item i {
            color: var(--light-color);
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 0.75rem 0.75rem 0 0 !important;
        }
        
        .organizer-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
        }
        
        .organizer-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--light-color);
        }
        
        .gallery-item {
            height: 200px;
            overflow: hidden;
            border-radius: 0.5rem;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s;
        }
        
        .gallery-item:hover img {
            transform: scale(1.05);
        }
        
        .gallery-item .media-type {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: rgba(0,0,0,0.7);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
        }
        
        .activity-badge {
            background-color: var(--light-color);
            color: var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.25rem;
        }
        
        .booking-status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
        }
        
        .status-confirmed {
            background-color: #e8f5e9;
            color: #2E7D32;
        }
        
        .status-pending {
            background-color: #fff8e1;
            color: #FF8F00;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #C62828;
        }
        
        @media (max-width: 768px) {
            .trip-header {
                padding: 3rem 0;
                border-radius: 0;
            }
            
            .trip-title {
                font-size: 2rem;
            }
            
            .trip-meta {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="organizer.php">
                <i class="fas fa-tree me-2"></i>Forest Tourism
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="organizer.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_trips.php">
                            <i class="fas fa-map-marked-alt me-1"></i> Mes voyages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Déconnexion
                        </a>
                    </li>

                    
                </ul>
            </div>
        </div>
    </nav>

    <!-- Trip Header -->
    <div class="trip-header">
        <div class="container trip-header-content">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="trip-title"><?= htmlspecialchars($trip['title']) ?></h1>
                    <p class="lead"><?= htmlspecialchars($trip['location']) ?></p>
                    
                    <div class="trip-meta">
                        <div class="trip-meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?= date('d/m/Y', strtotime($trip['start_date'])) ?> - <?= date('d/m/Y', strtotime($trip['end_date'])) ?></span>
                        </div>
                        <div class="trip-meta-item">
                            <i class="fas fa-euro-sign"></i>
                            <span><?= number_format($trip['price'], 2) ?> €</span>
                        </div>
                        <div class="trip-meta-item">
                            <i class="fas fa-users"></i>
                            <span><?= $total_participants ?> / <?= $trip['max_participants'] ?> participants</span>
                        </div>
                    </div>
                </div>
                <?php if ($is_owner): ?>
    <div class="d-flex gap-2">
        <a href="edit_trip.php?id=<?= $trip['id'] ?>" class="btn btn-light">
            <i class="fas fa-edit me-1"></i> Modifier
        </a>
        <a href="#" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteTripModal">
            <i class="fas fa-trash me-1"></i> Supprimer
        </a>
    </div>
    <?php endif; ?>
            </div>
            
        </div>
    </div>

    <div class="container">
    <div class="container-fluid">
    <div class="container-fluid">
    <div class="d-flex justify-content-between mt-5">
    <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php') ?>" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i>Retour
    </a>
</div>
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                
                <!-- Trip Description -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Description du voyage</h5>
                    </div>
                    <div class="card-body">
                        <p><?= nl2br(htmlspecialchars($trip['description'])) ?></p>
                        
                        <div class="mt-4">
                            <h6 class="mb-3"><i class="fas fa-tasks me-2"></i>Activités proposées</h6>
                            <div>
                                <?php foreach($activities as $activity): ?>
                                    <span class="activity-badge">
                                        <?php 
                                            $icons = [
                                                'hiking' => 'fa-hiking',
                                                'bird_watching' => 'fa-dove',
                                                'camping' => 'fa-campground',
                                                'photography' => 'fa-camera',
                                                'wildlife' => 'fa-paw',
                                                'education' => 'fa-book'
                                            ];
                                            $icon = $icons[$activity] ?? 'fa-check';
                                        ?>
                                        <i class="fas <?= $icon ?>"></i>
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $activity))) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Trip Gallery -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-images me-2"></i>Galerie</h5>
                        <?php if ($is_owner ): ?>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMediaModal">
        <i class="fas fa-plus me-1"></i> Ajouter
    </button>
    <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($trip_media)): ?>
                            <div class="row g-3">
                                <?php foreach($trip_media as $media): ?>
                                    <div class="col-md-4">
                                        <div class="gallery-item">
                                            <?php if($media['media_type'] == 'image'): ?>
                                                <img src="<?= htmlspecialchars($media['file_path']) ?>" alt="<?= htmlspecialchars($media['title']) ?>">
                                            <?php else: ?>
                                                <video>
                                                    <source src="<?= htmlspecialchars($media['file_path']) ?>" type="video/mp4">
                                                </video>
                                            <?php endif; ?>
                                            <span class="media-type">
                                                <i class="fas fa-<?= $media['media_type'] == 'image' ? 'image' : 'video' ?>"></i>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-images fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucun média pour ce voyage</h5>
                                <p class="text-muted">Ajoutez des photos ou vidéos pour illustrer votre voyage</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Organizer Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Organisateur</h5>
                    </div>
                    <div class="organizer-card">
                        <img src="<?= htmlspecialchars($trip['organizer_pic'] ?: 'default_profile.jpg') ?>" 
                             class="organizer-img" alt="Organisateur">
                        <div>
                            <h6><?= htmlspecialchars($trip['organizer_name']) ?></h6>
                            <p class="text-muted mb-0">Organisateur de voyage</p>
                            <a href="#" class="btn btn-sm btn-outline-primary mt-2">Contacter</a>
                        </div>
                    </div>
                </div>
                
                <!-- Trip Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-map-marked-alt me-2"></i>Détails du voyage</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-route me-2 text-muted"></i> Type de forêt</span>
                                <span class="fw-bold"><?= htmlspecialchars(ucfirst($trip['forest_type'])) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-mountain me-2 text-muted"></i> Difficulté</span>
                                <span class="fw-bold"><?= htmlspecialchars(ucfirst($trip['difficulty_level'])) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-friends me-2 text-muted"></i> Places restantes</span>
                                <span class="fw-bold"><?= $trip['max_participants'] - $total_participants ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-clock me-2 text-muted"></i> Date de création</span>
                                <span class="fw-bold"><?= date('d/m/Y', strtotime($trip['created_at'])) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Bookings Summary -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>Réservations</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <h6 class="text-muted mb-1">Confirmées</h6>
                                <h4 class="mb-0"><?= count($confirmed_bookings) ?></h4>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">En attente</h6>
                                <h4 class="mb-0"><?= count($pending_bookings) ?></h4>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Participants</h6>
                                <h4 class="mb-0"><?= $total_participants ?></h4>
                            </div>
                        </div>
                        <a href="organizer_bookings.php?trip_id=<?= $trip['id'] ?>" class="btn btn-primary w-100">
                            <i class="fas fa-list me-1"></i> Voir toutes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Trip Modal -->
    <div class="modal fade" id="deleteTripModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer ce voyage? Cette action est irréversible.</p>
                    <p class="text-danger">Toutes les réservations associées seront également supprimées.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <a href="delete_trip.php?id=<?= $trip['id'] ?>" class="btn btn-danger">Supprimer</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Media Modal -->
    <div class="modal fade" id="addMediaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="upload_media.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajouter un média</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Type de média</label>
                            <select class="form-select" name="media_type" required>
                                <option value="image">Image</option>
                                <option value="video">Vidéo</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Titre (optionnel)</label>
                            <input type="text" class="form-control" name="title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fichier</label>
                            <input type="file" class="form-control" name="media_file" required>
                            <small class="text-muted">Formats acceptés: JPG, PNG, GIF, MP4 (max 10MB)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Télécharger</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialiser les tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>