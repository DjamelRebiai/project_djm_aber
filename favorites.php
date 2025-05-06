<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get favorite trips with additional information
$stmt = $pdo->prepare("
    SELECT t.*, u.username AS organizer_name, u.profile_pic AS organizer_pic,
           (SELECT COUNT(*) FROM favorites WHERE trip_id = t.id) AS favorite_count,
           (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id) AS booked_count
    FROM trips t
    JOIN favorites f ON t.id = f.trip_id
    JOIN users u ON t.organizer_id = u.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$favorite_trips = $stmt->fetchAll();

// Get user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr" dir="rtl" >
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= bin2hex(random_bytes(32)) ?>">
    <title>Favoris - Tourisme Forestier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2e8b57;
            --primary-dark: #1e5631;
            --primary-light: #d4edda;
            --secondary-color: #3cb371;
            --light-color: #f8f9fa;
            --dark-color: #1a3e2a;
            --text-color: #333;
            --border-radius: 12px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
            color: var(--text-color);
            line-height: 1.6;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

     

        /* Main content - Adjusted for right sidebar */
        .main-content {
            margin-right: 280px; /* Changed from margin-left */
            padding: 25px;
            transition: var(--transition);
            width: calc(100% - 280px);
        }

        /* User bar */
        .user-bar {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 15px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid var(--primary-light);
            margin-right: 15px;
        }

        .user-name {
            font-weight: 700;
        }

        .user-join-date {
            font-size: 0.85rem;
            color: #666;
        }

        /* Page header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .page-title {
            color: var(--primary-dark);
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        .page-title i {
            margin-right: 10px;
            color: #dc3545;
        }

        /* Trip cards */
        .trips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .trip-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            overflow: hidden;
            border: none;
        }

        .trip-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .trip-img-container {
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .trip-img {
            height: 100%;
            width: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .trip-card:hover .trip-img {
            transform: scale(1.05);
        }

        .favorite-btn {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc3545;
            font-size: 1.3rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .favorite-btn:hover {
            transform: scale(1.15);
            color: #c82333;
        }

        .favorite-btn.active i {
            color: #dc3545;
        }

        .trip-badge {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: var(--primary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .trip-badge i {
            margin-right: 5px;
        }

        .trip-details {
            padding: 20px;
        }

        .trip-title {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .organizer-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .organizer-info img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid var(--primary-light);
            margin-right: 10px;
        }

        .organizer-name {
            font-size: 0.9rem;
            color: #555;
        }

        .trip-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .trip-price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.2rem;
        }

        .trip-date {
            color: #666;
            font-size: 0.9rem;
        }

        .trip-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            gap: 10px;
        }

        .btn {
            transition: var(--transition);
            font-weight: 500;
        }

        .btn-details {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            padding: 8px 15px;
            border-radius: 5px;
            flex: 1;
        }

        .btn-details:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-book {
            background: var(--primary-color);
            border: 1px solid var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            flex: 1;
        }

        .btn-book:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
            flex: 1;
        }

        .btn-secondary:hover {
            background: #5a6268;
            border-color: #545b62;
        }

        .btn-disabled {
            background: #e9ecef;
            border-color: #dee2e6;
            color: #6c757d;
            cursor: not-allowed;
            flex: 1;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            max-width: 600px;
            margin: 40px auto;
        }

        .empty-state i {
            font-size: 5rem;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #9ca3af;
            margin-bottom: 25px;
        }

        /* Alert messages */
        .alert-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 24px;
            border-radius: 5px;
            font-weight: bold;
            z-index: 1100;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.3s ease-in-out;
            display: flex;
            align-items: center;
            min-width: 300px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-message i {
            margin-right: 10px;
        }

        @keyframes fadeIn {
            from { opacity: 0; top: 0; }
            to { opacity: 1; top: 20px; }
        }

     
        @media (max-width: 768px) {
            .trips-grid {
                grid-template-columns: 1fr;
            }
            
            .user-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-actions {
                margin-top: 15px;
                width: 100%;
            }
            
            .user-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .trip-actions {
                flex-direction: column;
            }
            
            .trip-actions .btn {
                width: 100%;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
        

/* الشريط الجانبي مع ألوان الغابة */
.sidebar {
    width: 254.5px;
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
    </style>
</head>
<body>
    <div id="alertMessage" class="alert-message" style="display: none;"></div>
    
    <div class="dashboard">
        <!-- Sidebar - Right-aligned -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-tree"></i>
                <span>Tourisme Forestier</span>
            </div>
            <ul class="menu">
                <li ><a href="user.php"><i class="fas fa-search"></i>Explorer les voyages</a></li>
                <li><a href="mes_voyages.php"><i class="fas fa-map-marked-alt"></i> Mes voyages</a></li>
                <li class="active"><a href="#"><i class="fas fa-heart"></i> Favoris</a></li>
                <li ><a href="profile.php"><i class="fas fa-user"></i> Profil</a></li>
                <li><a href="login.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </div>

        <!-- Main content -->
        <div class="main-content">
            <!-- User bar -->
            <div class="user-bar">
                <div class="user-info">
                    <img src="<?= htmlspecialchars($user['profile_pic'] ?? 'uploads/trip_5_1746209965.png ') ?>" 
                         alt=" ">
                    <div>
                        <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                        <div class="user-join-date">Membre depuis <?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
                    </div>
                </div>
                <div class="user-actions">
                    <a href="index.php" class="btn btn-book">
                        <i class="fas fa-plus"></i> Nouveau voyage
                    </a>
                </div>
            </div>

            <!-- Page header -->
            <div class="page-header">
                <h2 class="page-title">
                    <i class="fas fa-heart"></i>
                    Mes voyages préférés
                </h2>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-sort"></i> Trier par
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?sort=recent"><i class="fas fa-clock me-2"></i>Récent</a></li>
                        <li><a class="dropdown-item" href="?sort=popular"><i class="fas fa-fire me-2"></i>Populaire</a></li>
                        <li><a class="dropdown-item" href="?sort=date"><i class="fas fa-calendar me-2"></i>Date de voyage</a></li>
                    </ul>
                </div>
            </div>

            <!-- Favorite trips list -->
           <!-- Favorite trips list -->
<?php if (empty($favorite_trips)): ?>
    <div class="empty-state">
        <i class="fas fa-heart-broken"></i>
        <h3>Pas de favoris</h3>
        <p>Vous n'avez pas encore ajouté de voyages à vos favoris. Commencez à explorer et ajoutez les voyages que vous aimez !</p>
        <a href="index.php" class="btn btn-book">
            <i class="fas fa-search me-2"></i> Explorer les voyages
        </a>
    </div>
<?php else: ?>
    <div class="trips-grid">
        <?php foreach ($favorite_trips as $trip): 
            $available_spots = $trip['max_participants'] - $trip['booked_count'];
        ?>
            <div class="trip-card">
                <div class="trip-img-container">
                    <img src="<?= htmlspecialchars($trip['featured_image'] ?? 'uploads/images_df.png ') ?>" class="trip-img" alt="Image de <?= htmlspecialchars($trip['title']) ?>">
                    
                    <!-- Formulaire de suppression des favoris -->
                    <form method="post" action="update_favorite.php" class="favorite-form">
                        <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                        
                        <button type="submit" class="favorite-btn active" aria-label="Retirer des favoris">
                            <i class="fas fa-heart"></i>
                        </button>
                    </form>
                    
                    <div class="trip-badge">
                        <i class="fas fa-heart"></i> <?= $trip['favorite_count'] ?> Favoris
                    </div>
                </div>
                <div class="trip-details">
                    <h3 class="trip-title"><?= htmlspecialchars($trip['title']) ?></h3>
                    <div class="organizer-info">
                        <img src="<?= htmlspecialchars($user['profile_pic'] ?? 'uploads/trip_5_1746209965.png ') ?>" 
                             alt="Organisateur: <?= htmlspecialchars($trip['organizer_name']) ?>">
                        <span class="organizer-name"><?= htmlspecialchars($trip['organizer_name']) ?></span>
                    </div>
                    <div class="trip-meta">
                        <span class="trip-price"><?= number_format($trip['price'], 0, ',', ' ') ?> DA</span>
                        <span class="trip-date">
                            <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($trip['start_date'])) ?>
                        </span>
                    </div>
                    <div class="trip-actions">
                        <a href="trip_details.php?id=<?= $trip['id'] ?>" class="btn btn-details">
                            <i class="fas fa-eye"></i> Détails
                        </a>
                        <?php if ($trip['status'] === 'actif'): ?>
                            <?php if ($available_spots > 0): ?>
                                <a href="book_trip.php?id=<?= $trip['id'] ?>" class="btn btn-book" aria-label="Réserver ce voyage">
                                    <i class="fas fa-calendar-check"></i> Réserver (<?= $available_spots ?> places)
                                </a>
                            <?php else: ?>
                                <button class="btn btn-disabled" disabled aria-label="Complet - plus de places disponibles">
                                    <i class="fas fa-times-circle"></i> Complet
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-disabled" disabled aria-label="Voyage inactif">
                                <i class="fas fa-ban"></i> Inactif
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        

        // Book a trip
        function bookTrip(tripId) {
            fetch('book_trip.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ 
                    trip_id: tripId,
                    participants: 1 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Réservation confirmée!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message || 'Erreur lors de la réservation', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion au serveur1', 'error');
            });
        }

        // Show alert messages
        function showAlert(message, type) {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                ${message}
            `;
            alertDiv.className = `alert-message alert-${type}`;
            alertDiv.style.display = 'flex';
            
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        // Handle sidebar on small screens
        function handleSidebar() {
            if (window.innerWidth <= 992) {
                document.querySelectorAll('.sidebar span').forEach(el => {
                    el.style.display = 'none';
                });
            } else {
                document.querySelectorAll('.sidebar span').forEach(el => {
                    el.style.display = 'inline';
                });
            }
        }

        window.addEventListener('resize', handleSidebar);
        document.addEventListener('DOMContentLoaded', handleSidebar);
    </script>
</body>
</html>