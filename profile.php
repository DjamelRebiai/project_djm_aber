<?php

session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable");
}

// Récupérer les voyages créés par l'organisateur (si c'est un organisateur ou admin)
$organized_trips = [];
if ($user['user_type'] !== 'user') {
    $stmt_trips = $pdo->prepare("SELECT * FROM trips WHERE organizer_id = ? ORDER BY start_date DESC");
    $stmt_trips->execute([$user_id]);
    $organized_trips = $stmt_trips->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les réservations de l'utilisateur
$stmt_bookings = $pdo->prepare("
    SELECT b.*, t.title, u.username AS organizer_name 
    FROM bookings b
    JOIN trips t ON b.trip_id = t.id
    JOIN users u ON t.organizer_id = u.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC
");
$stmt_bookings->execute([$user_id]);
$bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les favoris
$stmt_favorites = $pdo->prepare("
    SELECT f.trip_id, t.title, t.location, t.start_date, t.end_date, u.username AS organizer 
    FROM favorites f
    JOIN trips t ON f.trip_id = t.id
    JOIN users u ON t.organizer_id = u.id
    WHERE f.user_id = ?
");
$stmt_favorites->execute([$user_id]);
$favorites = $stmt_favorites->fetchAll(PDO::FETCH_ASSOC);

// Gestion de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $bio = trim($_POST['bio']);
    $phone = trim($_POST['phone']);

    // Mise à jour du profil utilisateur
    $pdo->prepare("UPDATE users SET full_name = ?, bio = ?, phone = ? WHERE id = ?")
       ->execute([$full_name, $bio, $phone, $user_id]);

    // Redirection pour rafraîchir les données
    header("Location: profile.php");
    exit;
}

// Gestion de la mise à jour du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!password_verify($current_password, $user['password'])) {
        $password_error = "Le mot de passe actuel est incorrect";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "Les deux mots de passe ne correspondent pas";
    } elseif (strlen($new_password) < 6) {
        $password_error = "Le nouveau mot de passe doit faire au moins 6 caractères";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
           ->execute([$hashed_password, $user_id]);
        $password_success = "Mot de passe mis à jour avec succès";
    }
}
// Gestion de l'upload de photo de profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $errors = [];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    // Vérification des erreurs d'upload
    if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
        $errors['profile_pic'] = "Erreur lors du téléchargement du fichier";
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['profile_pic']['tmp_name']);

        if (!in_array($mimeType, $allowedTypes)) {
            $errors['profile_pic'] = "Seuls les formats JPG, PNG et GIF sont autorisés";
        }

        if ($_FILES['profile_pic']['size'] > $maxSize) {
            $errors['profile_pic'] = "La taille maximale autorisée est de 2MB";
        }
    }

    if (empty($errors)) {
        // 📂 Chemin absolu sur le disque (pour move_uploaded_file)
        $uploadDir = __DIR__ . '/uploads/user/';

        // 🌐 Chemin relatif à la racine du site (à enregistrer dans la BDD)
        $webPath = 'uploads/user/';

        // Créer le dossier s'il n'existe pas
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Supprimer l'ancienne photo si elle existe
        if (!empty($user['profile_pic']) && file_exists($uploadDir . $user['profile_pic'])) {
            unlink($uploadDir . $user['profile_pic']);
        }

        $extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $user_id . '_' . uniqid() . '.' . strtolower($extension);
        $destination = $uploadDir.$filename;

        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
            // 📌 Enregistrer juste le nom de fichier ou chemin relatif
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute(["uploads/user/".$filename, $user_id]);

            // Rafraîchir les données utilisateur
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $_SESSION['success_message'] = "Photo de profil mise à jour avec succès";
        } else {
            $errors['profile_pic'] = "Erreur lors de l'enregistrement du fichier";
        }
    }
}
$stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_type = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil - Tourisme Forestier</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #1e5631;
            color: white;
            position: fixed;
            height: 100%;
            padding-top: 20px;
            transition: all 0.3s ease;
        }

        .main-content {
            margin-right: 250px;
            padding: 20px;
            width: 100%;
        }

        .profile-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #2e8b57;
        }

        .edit-profile-form textarea,
        .edit-profile-form input[type="text"],
        .edit-profile-form input[type="tel"] {
            border: 1px solid #ced4da;
        }

        .section-title {
            color: #1a3e2a;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }

        .trip-card {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-right: 4px solid #2e8b57;
        }

        .trip-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }

        .alert-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            padding: 12px 24px;
            border-radius: 5px;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; top: 0; }
            to { opacity: 1; top: 20px; }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }

            .main-content {
                margin-right: 70px;
            }
        }





        .image-preview {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background-color: #f8f9fa;
    border: 2px dashed #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    overflow: hidden;
    position: relative;
}

.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-preview-text {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #6c757d;
    text-align: center;
    padding: 20px;
}

.image-preview-text i {
    font-size: 3rem;
    margin-bottom: 10px;
}

/* الشريط الجانبي مع ألوان الغابة */
.sidebar {
    width: 250px;
    background-color: #1e5631; /* لون أخضر غابي داكن */
    color: white;
    padding: 20px 0;
    position: fixed;
    height: 100vh;
    transition: all 0.3s;
    z-index: 10;
    background-image: linear-gradient(to bottom, #1e5631, #2d7a46);
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
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
    color: #a4de9a; /* لون أخضر فاتح يشبه أوراق الشجر */
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

<div class="dashboard">
<?php if ($user_type === 'user'): ?>
    <!-- Sidebar pour l'utilisateur normal -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-tree"></i>
            <span>Tourisme Forestier</span>
        </div>
        <ul class="menu">
            <li><a href="user.php"><i class="fas fa-search"></i> Explorer les voyages</a></li>
            <li><a href="mes_voyages.php"><i class="fas fa-map-marked-alt"></i> Mes voyages</a></li>
            <li><a href="favorites.php"><i class="fas fa-heart"></i> Favoris</a></li>
            <li class="active"><a href="#"><i class="fas fa-user"></i> Profil</a></li>
            <li><a href="login.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>

<?php elseif ($user_type === 'organizer'): ?>
    <!-- Sidebar pour l'organisateur -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand text-center py-4">
           
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
                    <i class="fas fa-user"></i>
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
<?php else: ?>
    <!-- Si le type est inconnu ou admin -->
    <p>Accès non autorisé.</p>
<?php endif; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="user-info-bar">
               

        <!-- Profile Section -->
        <div class="profile-card">
        <div class="user-greeting">
                    <i class="fas fa-user"></i>
                    Bonjour, <?php if ($user_id): ?>
    <a href="profile.php"><span><?= htmlspecialchars($user['username']) ?></span></a>
<?php else: ?>
    <a href="login.php">Se connecter</a>
<?php endif; ?>
                </div>

                <?php if ($user_id): ?>
   
<?php else: ?>
    <div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    Vous êtes visiteur. <a href="login.php">Connectez-vous</a> pour profiter de toutes les fonctionnalités.
</div>
<?php endif; ?>
        
            <h4 class="section-title"><i class="fas fa-user-circle me-2"></i> Informations du Profil</h4>

            <?php if (!empty($password_error)): ?>
                <div class="alert alert-danger"><?= $password_error ?></div>
            <?php elseif (!empty($password_success)): ?>
                <div class="alert alert-success"><?= $password_success ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Informations utilisateur -->
               
            
<!-- Formulaire de téléchargement -->
<form action="profile.php" method="post" enctype="multipart/form-data" class="mb-4">
    <label class="form-label fw-bold">Photo de profil</label>
    <h5><?= htmlspecialchars($user['username']) ?></h5>
    <p class="text-muted"><?= ucfirst(htmlspecialchars($user['user_type'])) ?></p>
    <!-- Aperçu de l'image -->
    <div class="image-preview" id="imagePreview">
        <?php if (!empty($user['profile_pic'])): ?>
            <img src="<?= htmlspecialchars($user['profile_pic']?? '/uploads/trip_5_1746209965.png')?>" alt="Photo de profil actuelle">
        <?php else: ?>
            <div class="image-preview-text">
                <i class="fas fa-user"></i>
                <p>Aperçu de la photo</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Champ de fichier -->
    <input type="file" class="form-control <?= isset($_SESSION['errors']['profile_pic']) ? 'is-invalid' : '' ?>" 
           id="profile_pic" name="profile_pic" accept="image/*">
    
    <!-- Message d'erreur -->
    <?php if (isset($_SESSION['errors']['profile_pic'])): ?>
        <div class="invalid-feedback"><?= htmlspecialchars($_SESSION['errors']['profile_pic']) ?></div>
        <?php unset($_SESSION['errors']['profile_pic']); ?>
    <?php endif; ?>
    
    <!-- Message de succès -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success mt-2"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <button type="submit" class="btn btn-primary mt-2">Mettre à jour la photo</button>
    <small class="text-muted d-block">Formats acceptés : JPG, PNG, GIF (max 2MB)</small>
</form>




                <!-- Formulaire de mise à jour -->
                <div class="col-md-8">
                    <form method="post" class="edit-profile-form needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nom complet</label>
                            <input type="text" class="form-control" id="full_name" name="full_name"
                                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="text" class="form-control" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        </div>

                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio / Description</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-success mt-2">
                            <i class="fas fa-save me-2"></i>Mettre à jour le profil
                        </button>
                    </form>

                    <!-- Formulaire de changement de mot de passe -->
                    <hr class="my-4">
                    <h5 class="section-title"><i class="fas fa-lock me-2"></i> Changer mon mot de passe</h5>
                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <div class="invalid-feedback">Veuillez entrer votre mot de passe actuel</div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                            <div class="invalid-feedback">Le mot de passe doit faire au moins 6 caractères</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback">Veuillez confirmer le mot de passe</div>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary mt-2">
                            <i class="fas fa-key me-2"></i> Mettre à jour le mot de passe
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Mes réservations -->
        <div class="profile-card">
            <h4 class="section-title"><i class="fas fa-calendar-check me-2"></i> Mes réservations</h4>
            <?php if (empty($bookings)): ?>
                <div class="alert alert-info">Aucune réservation trouvée.</div>
            <?php else: ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Randonnée</th>
                            <th>Organisateur</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?= htmlspecialchars($booking['title']) ?></td>
                                <td><?= htmlspecialchars($booking['organizer_name']) ?></td>
                                <td><?= date('d/m/Y', strtotime($booking['booking_date'])) ?></td>
                                <td><span class="badge bg-<?= $booking['status'] == 'confirmed' ? 'success' : ($booking['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($booking['status']) ?>
                                </span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Mes voyages (si organisateur ou admin) -->
        <?php if (!empty($organized_trips)): ?>
            <div class="profile-card">
                <h4 class="section-title"><i class="fas fa-map me-2"></i> Mes voyages organisés</h4>
                <div class="row g-3">
                    <?php foreach ($organized_trips as $trip): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="trip-card">
                                <h5><?= htmlspecialchars($trip['title']) ?></h5>
                                <p class="text-muted"><?= htmlspecialchars($trip['location']) ?> | Du <?= date('d/m/Y', strtotime($trip['start_date'])) ?> au <?= date('d/m/Y', strtotime($trip['end_date'])) ?></p>
                                <a href="trip_details.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-eye me-1"></i>Voir détails
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Favoris -->
        <div class="profile-card">
            <h4 class="section-title"><i class="fas fa-heart me-2"></i> Mes favoris</h4>
            <?php if (empty($favorites)): ?>
                <div class="alert alert-info">Vous n'avez aucun voyage dans vos favoris.</div>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($favorites as $fav): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= htmlspecialchars($fav['title']) ?> - <?= htmlspecialchars($fav['location']) ?></span>
                            <small><?= htmlspecialchars($fav['organizer']) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

document.getElementById('profile_pic').addEventListener('change', function(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('imagePreview');
    const previewText = preview.querySelector('.image-preview-text');
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            let img = preview.querySelector('img');
            if (!img) {
                img = document.createElement('img');
                preview.appendChild(img);
            }
            
            img.src = e.target.result;
            img.alt = "Aperçu de l'image";
            
            if (previewText) {
                previewText.style.display = 'none';
            }
        };
        
        reader.readAsDataURL(file);
    } else {
        const img = preview.querySelector('img');
        if (img) {
            preview.removeChild(img);
        }
        if (previewText) {
            previewText.style.display = 'flex';
        }
    }
});



(function () {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>
</body>
</html>