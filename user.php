<?php

session_start();
// session_start();Afficher les données de session pour debug

// Connexion à la base de données
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
$user = null;
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Récupération des voyages disponibles (public pour tous)
$search_query = $_GET['search'] ?? '';
$location_filter = $_GET['location'] ?? '';
$date_filter = $_GET['trip_date'] ?? '';
$difficulty_filter = $_GET['difficulty'] ?? '';
$forest_filter = $_GET['forest_type'] ?? '';

$sql = "SELECT t.*, u.username as organizer_name, u.profile_pic as organizer_pic 
        FROM trips t
        JOIN users u ON t.organizer_id = u.id
        WHERE t.start_date >= CURDATE() AND t.status = 'actif'";

$params = [];

if (!empty($search_query)) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
if (!empty($location_filter)) {
    $sql .= " AND t.location = ?";
    $params[] = $location_filter;
}
if (!empty($date_filter)) {
    $sql .= " AND t.start_date = ?";
    $params[] = $date_filter;
}
if (!empty($difficulty_filter)) {
    $sql .= " AND t.difficulty_level = ?";
    $params[] = $difficulty_filter;
}
if (!empty($forest_filter)) {
    $sql .= " AND t.forest_type = ?";
    $params[] = $forest_filter;
}

// Tri
$order_by = $_GET['order_by'] ?? 'recent';
switch ($order_by) {
    case 'price-low': $sql .= " ORDER BY t.price ASC"; break;
    case 'price-high': $sql .= " ORDER BY t.price DESC"; break;
    case 'date-asc': $sql .= " ORDER BY t.start_date ASC"; break;
    default: $sql .= " ORDER BY t.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trips = $stmt->fetchAll();

// Récupération des filtres
$locations = $pdo->query("SELECT DISTINCT location FROM trips ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);

$difficulties = $pdo->query("SELECT DISTINCT difficulty_level FROM trips ORDER BY difficulty_level")->fetchAll(PDO::FETCH_COLUMN);
$forest_types = $pdo->query("SELECT DISTINCT forest_type FROM trips ORDER BY forest_type")->fetchAll(PDO::FETCH_COLUMN);

// ✅ Si l'utilisateur est connecté, récupérer les favoris, notifications, réservations
$favorites = [];
$notifications = [];
$bookings = [];

if ($user_id) {
    // Favoris
    $favorites_stmt = $pdo->prepare("SELECT trip_id FROM favorites WHERE user_id = ?");
    $favorites_stmt->execute([$user_id]);
    $favorites = $favorites_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Notifications
    $notifications_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $notifications_stmt->execute([$user_id]);
    $notifications = $notifications_stmt->fetchAll();

    // Marquer comme lues
    $update_stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
    $update_stmt->execute([$user_id]);

    // Réservations
    $booking_stmt = $pdo->prepare("
        SELECT b.*, t.title, u.username as organizer_name 
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN users u ON t.organizer_id = u.id
        WHERE b.user_id = ?
    ");



    // In your trips query section, modify the SQL to:
$sql = "SELECT t.*, u.username as organizer_name, u.profile_pic as organizer_pic,
        (SELECT COUNT(*) FROM comments c WHERE c.trip_id = t.id) as comment_count,
        (SELECT c.content FROM comments c WHERE c.trip_id = t.id ORDER BY c.created_at DESC LIMIT 1) as last_comment
        FROM trips t
        JOIN users u ON t.organizer_id = u.id
        WHERE t.start_date >= CURDATE() AND t.status = 'actif'";
    $booking_stmt->execute([$user_id]);
    $bookings = $booking_stmt->fetchAll(PDO::FETCH_ASSOC);
}
$favoritesJson = json_encode($favorites);
?>
<!DOCTYPE html>
<html  lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Tourisme Forestier</title>
   
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
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

            /* CSS Reset وتنسيقات عامة */
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
    direction: rtl;
}

/* أنماط التنبيهات */
.alert-message {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    padding: 12px 24px;
    border-radius: 5px;
    font-weight: bold;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: fadeIn 0.3s ease-in-out;
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

@keyframes fadeIn {
    from { opacity: 0; top: 0; }
    to { opacity: 1; top: 20px; }
}

/* تخطيط لوحة التحكم */
.dashboard {
    display: flex;
    min-height: 100vh;
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

/* المحتوى الرئيسي */
.main-content {
    flex: 1;
    margin-right: 250px;
    padding: 20px;
    transition: all 0.3s;
}

/* شريط معلومات المستخدم */
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
    color: var(--main-color);
}

.notification-dropdown .dropdown-menu {
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
}

.notification-dropdown .dropdown-item {
    border-bottom: 1px solid var(--border-color);
    transition: all 0.2s;
}

.notification-dropdown .dropdown-item:hover {
    background-color: #f8f9fa;
}

/* قسم البحث */
.search-section {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.search-section h2 {
    margin-bottom: 15px;
    color: var(--dark-color);
    display: flex;
    align-items: center;
}

.search-section h2 i {
    margin-left: 10px;
}

.search-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-form input,
.search-form select {
    flex: 1;
    min-width: 200px;
    padding: 10px 15px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    font-size: 0.9rem;
}

.search-form button {
    background-color: var(--main-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
}

.search-form button:hover {
    background-color: var(--secondary-color);
}

.search-form button i {
    margin-left: 8px;
}

/* أقسام المحتوى */
.section {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    color: var(--dark-color);
}

.filter select {
    padding: 8px 15px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    font-size: 0.9rem;
}

/* بطاقات الرحلات */
.trip-card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s;
    border-right: 4px solid var(--main-color);
}

.trip-card:hover {
    box-shadow: 0 5px 15px rgba(46, 139, 87, 0.1);
    transform: translateY(-3px);
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

.trip-organizer {
    display: flex;
    align-items: center;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.trip-organizer img {
    border-radius: 50%;
    margin-left: 10px;
}

.favorite-btn {
    background-color: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    padding: 5px 15px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    background-color:rgb(255, 255, 255);
}

.favorite-btn:hover {
    background-color:rgb(248, 108, 108);
    color:rgb(255, 255, 255);
}

.favorite-btn i {
    margin-left: 5px;
}

.favorite-btn .fas {
    color: #dc3545;
}

/* معلومات الرحلة */
.trip-basic-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.info-row {
    display: flex;
    align-items: center;
}

.info-label {
    font-weight: bold;
    margin-left: 10px;
    color: var(--text-muted);
    min-width: 120px;
}

.info-label i {
    margin-left: 5px;
    color: var(--main-color);
}

.info-value {
    color: #333;
}

.trip-location {
    color: var(--main-color);
    font-weight: bold;
}

.trip-date {
    color: var(--secondary-color);
}

/* وصف الرحلة */
.trip-description {
    margin: 15px 0;
    padding: 15px 0;
    border-top: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
}

.trip-description h4 {
    color: var(--dark-color);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.trip-description h4 i {
    margin-left: 10px;
    color: var(--main-color);
}

.trip-description p {
    color: #555;
    line-height: 1.8;
}

/* معرض الوسائط */
.trip-media {
    margin: 15px 0;
}

.trip-media h4 {
    color: var(--dark-color);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.trip-media h4 i {
    margin-left: 10px;
    color: var(--main-color);
}

.media-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.media-item {
    border-radius: 8px;
    overflow: hidden;
    height: 120px;
    position: relative;
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
}

.media-item:hover {
    transform: scale(1.03);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.media-item img, 
.media-item video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* أزرار الرحلة */
.trip-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 15px;
}

.trip-actions .btn {
    padding: 8px 20px;
    border-radius: 5px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    transition: all 0.3s;
}

.trip-actions .btn i {
    margin-left: 8px;
}

.btn-view {
    background-color: transparent;
    border: 1px solid var(--main-color);
    color: var(--main-color);
}

.btn-view:hover {
    background-color: rgba(46, 139, 87, 0.1);
}

.btn-book {
    background-color: rgba(0, 255, 110, 0.1);
    border: 1px solid var(--main-color);
    color:rgb(0, 124, 0);
}

.btn-book:hover {
    background-color: var(--secondary-color);
    border-color: var(--secondary-color);
}

.btn-disabled {
    background-color: #e9ecef;
    border: 1px solid #dee2e6;
    color: #6c757d;
    cursor: not-allowed;
}

/* حالة عدم وجود نتائج */
.no-results {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.no-results h3 {
    margin-bottom: 10px;
    color: var(--dark-color);
}

.no-results i {
    color: var(--secondary-color);
}

/* القوائم المنسدلة */
.dropdown-menu {
    text-align: right;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 8px 15px;
}

.dropdown-item i {
    margin-left: 10px;
}

/* تصميم متجاوب */
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
    
    .search-form {
        flex-direction: column;
    }
    
    .trip-basic-info {
        grid-template-columns: 1fr;
    }
    
    .trip-actions {
        flex-direction: column;
    }
    
    .trip-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 15px 10px;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .media-gallery {
        grid-template-columns: repeat(2, 1fr);
    }
}


/* تأثيرات إضافية للطبيعة */
.sidebar::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(to right, #a4de9a, #2d7a46, #a4de9a);
}

/* عند التصغير */
@media (max-width: 992px) {
    .sidebar {
        width: 70px;
    }
    
    .sidebar .logo {
        justify-content: center;
        padding: 0 0 30px 0;
    }
    
    .sidebar .logo span {
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
    
    .menu li a span {
        display: none;
    }
}






.trip-safety-tips {
    position: relative;
    margin: 15px 0;
}

.safety-tips-toggle {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.5rem;
    padding: 5px;
    transition: transform 0.3s;
}

.safety-tips-toggle:hover {
    transform: scale(1.2);
}

.tips-content {
    background-color: #fff8e1;
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
    border-right: 4px solid #ffc107;
}

.tips-content h4 {
    color: #ff9800;
    margin-bottom: 10px;
}

.tips-text {
    line-height: 1.8;
    color: #5a3e00;
}







.toggle-comments-btn {
    transition: all 0.3s ease;
    border-radius: 20px;
    padding: 5px 15px;
    margin-top: 15px;
    display: inline-flex;
    align-items: center;
}

.toggle-comments-btn:hover {
    background-color: #f0f0f0;
}

.trip-comments-section {
    transition: all 0.3s ease;
    overflow: hidden;
}

.comment-form {
    margin-top: 15px;
}

</style>
</head>
<body>
    <div id="alertMessage" class="alert-message" style="display: none;"></div>
    
    <div class="dashboard">
        <!-- Barre latérale -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-tree"></i>
                <span>Tourisme Forestier</span>
            </div>
            <ul class="menu">
                <li class="active"><a href="#"><i class="fas fa-search"></i> Explorer les voyages</a></li>
                <li><a href="mes_voyages.php"><i class="fas fa-map-marked-alt"></i> Mes voyages</a></li>
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



                  
                <?php if ($user_id): ?>
                    <span><?= htmlspecialchars($user['username']) ?></span>
<?php else: ?>
   
<?php endif; ?>





                           
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="login.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                        </ul>
                    </div>
                </div>
            </div>  

            <!-- Section de recherche -->
            <div class="search-section">
                <h2><i class="fas fa-search me-2"></i>Rechercher des voyages</h2>
                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Rechercher un voyage..." value="<?= htmlspecialchars($search_query) ?>">
                    
                    <select name="location">
                        <option value="">Tous les lieux</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>" <?= $location_filter == $loc ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="difficulty">
                        <option value="">Tous niveaux</option>
                        <?php foreach ($difficulties as $diff): ?>
                            <option value="<?= htmlspecialchars($diff) ?>" <?= $difficulty_filter == $diff ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($diff)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="forest_type">
                        <option value="">Tous types</option>
                        <?php foreach ($forest_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $forest_filter == $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($type)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" name="trip_date" value="<?= htmlspecialchars($date_filter) ?>">
                    
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search me-1"></i> Rechercher
                    </button>
                </form>
            </div>

            <!-- Résultats de recherche -->
            <div class="section">
                <div class="section-header">
                    
                    <h2>Voyages disponibles</h2>
                    <div class="filter">
                        <form method="GET" id="order-form">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                            <input type="hidden" name="location" value="<?= htmlspecialchars($location_filter) ?>">
                            <input type="hidden" name="trip_date" value="<?= htmlspecialchars($date_filter) ?>">
                            <input type="hidden" name="difficulty" value="<?= htmlspecialchars($difficulty_filter) ?>">
                            <input type="hidden" name="forest_type" value="<?= htmlspecialchars($forest_filter) ?>">
                            <select name="order_by" onchange="document.getElementById('order-form').submit()">
                                <option value="recent" <?= $order_by == 'recent' ? 'selected' : '' ?>>Plus récents</option>
                                <option value="price-low" <?= $order_by == 'price-low' ? 'selected' : '' ?>>Prix croissant</option>
                                <option value="price-high" <?= $order_by == 'price-high' ? 'selected' : '' ?>>Prix décroissant</option>
                                <option value="date-asc" <?= $order_by == 'date-asc' ? 'selected' : '' ?>>Prochainement</option>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if (empty($trips)): ?>
    <div class="no-results">
        <i class="fas fa-campground" style="font-size: 40px; margin-bottom: 15px;"></i>
        <h3>Aucun voyage disponible</h3>
        <p>Aucun voyage ne correspond à votre recherche. Essayez de modifier vos critères.</p>
    </div>
<?php else: ?>
    <?php foreach ($trips as $trip): ?>
        <div class="trip-card">
            <div class="trip-header">
                <div>
                    <h3 class="trip-title">
                        <?= htmlspecialchars($trip['title']) ?>
                    </h3>
                    <div class="trip-organizer">
                        <img src="<?= htmlspecialchars($trip['organizer_pic'] ?? 'uploads/trip_5_1746209965.png ') ?>" 
                             alt="" width="30">
                        <span>Organisé par <?= htmlspecialchars($trip['organizer_name']) ?></span>
                    </div>
                </div>
                <?php if (!empty($trip['safety_tips'])): ?>
<div class="trip-safety-tips">
    <button class="safety-tips-toggle" type="button">
        <i class="fas fa-exclamation-triangle text-danger"></i>
    </button>
    <div class="tips-content" style="display: none;">
        <h4> Conseils de sécurité pour la région :</h4>
        <div class="tips-text">
            <?= nl2br(htmlspecialchars($trip['safety_tips'])) ?>
        </div>
    </div>
</div>
<?php endif; ?>

    <!-- Bouton Favoris (version sans JavaScript) -->
<?php if ($user_id): ?>
    <form method="post" action="update_favorite.php" class="favorite-form">
        <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
        <input type="hidden" name="action" value="<?= in_array($trip['id'], $favorites) ? 'remove' : 'add' ?>">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <!-- Ajout d'un champ pour la redirection -->
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
        
        <button type="submit" class="favorite-btn">
            <i class="<?= in_array($trip['id'], $favorites) ? 'fas' : 'far' ?> fa-heart"></i>
            <span class="favorite-text">
                <?= in_array($trip['id'], $favorites) ? 'Favori' : 'Ajouter aux favoris' ?>
            </span>
        </button>
    </form>
<?php else: ?>
    <div class="favorite-form">
        <a href="login.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="favorite-btn btn-view">
            <i class="far fa-heart"></i> Connectez-vous pour ajouter aux favoris
        </a>
    </div>
<?php endif; ?>





            </div>

            <div class="trip-basic-info">
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-map-marker-alt"></i> Lieu:</span>
                    <span class="info-value trip-location"><?= htmlspecialchars($trip['location']) ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label"><i class="fas fa-calendar-alt"></i> Dates:</span>
                    <span class="info-value trip-date">
                        Du <?= date('d/m/Y', strtotime($trip['start_date'])) ?>
                        au <?= date('d/m/Y', strtotime($trip['end_date'])) ?>
                    </span>
                </div>
                                
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-users"></i> Places disponibles:</span>
                                    <span class="info-value">
                                        <?php 
                                        $booked = $pdo->prepare("SELECT SUM(participants) FROM bookings WHERE trip_id = ? AND status = 'confirmed'");
                                        $booked->execute([$trip['id']]);
                                        $booked_count = $booked->fetchColumn();
                                        echo ($trip['max_participants'] - $booked_count) . ' / ' . $trip['max_participants'];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-tag"></i> Prix:</span>
                                    <span class="info-value"><?= $trip['price'] ?>  par personne DA</span>
                                </div>
                                <div class="info-row d-flex align-items-center mb-2">
    <span class="info-label me-2 text-muted">
        <i class="fas fa-car me-1"></i> Type de véhicule:
    </span>
    <span class="info-value fw-bold">
        <?= isset($trip['vehicle_type']) ? htmlspecialchars($trip['vehicle_type']) : 'Non spécifié' ?>
    </span>
</div>

                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-hiking"></i> Difficulté:</span>
                                    <span class="info-value"><?= ucfirst(htmlspecialchars($trip['difficulty_level'])) ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-tree"></i> Type de forêt:</span>
                                    <span class="info-value"><?= ucfirst(htmlspecialchars($trip['forest_type'])) ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($trip['description'])): ?>
                            <div class="trip-description">
                                <h4><i class="fas fa-info-circle"></i> Description:</h4>
                                <p><?= nl2br(htmlspecialchars($trip['description'])) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Galerie média du voyage -->
                            <?php 
                            $media_stmt = $pdo->prepare("SELECT * FROM media WHERE trip_id = ? ORDER BY upload_date DESC LIMIT 4");
                            $media_stmt->execute([$trip['id']]);
                            $trip_media = $media_stmt->fetchAll();
                            
                            if (!empty($trip_media)): ?>
                            <div class="trip-media">
                                <h4><i class="fas fa-images"></i> Galerie:</h4>
                                <div class="media-gallery">
                                    <?php foreach ($trip_media as $media): ?>
                                        <div class="media-item">
                                            <?php if ($media['media_type'] == 'image'): ?>
                                                <img src="<?= htmlspecialchars($media['file_path']) ?>" 
                                                     alt="<?= htmlspecialchars($media['title'] ?? 'Image du voyage') ?>">
                                            <?php else: ?>
                                                <video controls>
                                                    <source src="<?= htmlspecialchars($media['file_path']) ?>" type="video/mp4">
                                                </video>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="trip-actions">
                                <a href="trip_details.php?id=<?= $trip['id'] ?>" class="btn btn-view">
                                    <i class="fas fa-eye"></i> Détails
                                </a>
                               
<?php if ($user_id && $trip['status'] === 'actif'): ?>
    <?php $available_spots = $trip['max_participants'] - $booked_count; ?>
    <?php if ($available_spots > 0): ?>
        <button class="btn btn-book" onclick="bookTrip(<?= $trip['id'] ?>)">
            <i class="fas fa-calendar-check"></i> Réserver
            
          
            (<?= $available_spots ?> places)
        </button>
    <?php else: ?>
        <button class="btn btn-disabled" disabled>
            <i class="fas fa-times-circle"></i> Complet
        </button>
    <?php endif; ?>
<?php elseif (!$user_id): ?>
    <button class="btn btn-secondary" onclick="showLoginModal()">
        <i class="fas fa-sign-in-alt"></i> Connectez-vous pour réserver
    </button>

                <?php else: ?>
                    <button class="favorite-btn" disabled>
                    
                        <a href="login.php?" class="btn btn-view">
                                    <i class="far fa-fa-calendar-check"></i> Connectez-vous pour Réserver  
                                </a>
                    </button>
                <?php endif; ?>







                            </div>
                 <!-- قسم التعليقات والإعجابات -->
<div class="trip-comments-section mt-4">
<h4 class="mb-0">
            <i class="fas fa-comments me-2"></i>Commentaires
        </h4>
  
<!-- زر إظهار/إخفاء التعليقات -->
<button class="btn btn-sm btn-outline-secondary toggle-comments-btn mb-3">
    <i class="fas fa-comments me-2"></i>
    <span class="show-text"> Afficher les commentaires</span>
    <span class="hide-text" style="display:none"> Cacher les commentaires</span>
</button>

<!-- قسم التعليقات (مخفي بشكل افتراضي) -->
<div class="trip-comments-section mt-4" style="display:none">
    <!-- نموذج إضافة تعليق -->
         <!-- نموذج إضافة تعليق -->
    <div class="comment-form mb-4">
        <?php if(!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(50));
        } ?>
        
        <form method="post" action="add_comment.php" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
            
            <div class="form-group">
                <textarea name="content" class="form-control" rows="3" 
                          placeholder="Ecrivez votre commentaire ici..." required><?= 
                          htmlspecialchars($_SESSION['comment_form']['content'] ?? '') ?></textarea>
                <div class="invalid-feedback">
                Veuillez saisir votre commentaire
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary mt-2">
                <i class="fas fa-paper-plane me-1"></i> Poster un commentaire
            </button>
        </form>
    </div>
    <div class="comment-form mb-4">
     
   
        <!-- محتوى قائمة التعليقات الحالي -->
       
        <?php
        // استعلام لاسترجاع التعليقات
        $stmt_comments = $pdo->prepare("
            SELECT c.*, u.username, u.profile_pic 
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.trip_id = ?
            ORDER BY c.created_at DESC
            LIMIT 5
        ");
        $stmt_comments->execute([$trip['id']]);
        $comments = $stmt_comments->fetchAll();
        
        if (empty($comments)): ?>
            <div class="alert alert-info">
            Il n'y a pas encore de commentaires. Soyez le premier à commenter !
            </div>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                
                <div class="comment-item mb-3 p-3 bg-light rounded">
                    
                    <div class="d-flex">
                        <img src="<?= htmlspecialchars($user['profile_pic'] ?? 'uploads/trip_5_1746209965.png ') ?>" 
                             alt=" " 
                             class="rounded-circle me-3" width="50" height="50">
                             
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong><?= htmlspecialchars($comment['username']) ?></strong>
                                <small class="text-muted">
                                    <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>
                                </small>
                            </div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
              <!-- زر الإعجاب وعداد الإعجابات -->
      <?php
// التحقق إذا كان المستخدم قد أعجب بهذه الرحلة
$liked = false;
if ($user_id) {
    $stmt_user_like = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE user_id = ? AND trips_id = ?");
    $stmt_user_like->execute([$user_id, $trip['id']]);
    $liked = $stmt_user_like->fetchColumn() > 0;
}

// عدد الإعجابات على هذه الرحلة
$stmt_likes = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE trips_id = ?");
$stmt_likes->execute([$trip['id']]);
$like_count = $stmt_likes->fetchColumn();
?>

<!-- زر الإعجاب -->
<div class="likes-counter mt-2">
    <button class="btn <?= $liked ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm like-trip-btn"
            data-trip-id="<?= $trip['id'] ?>"
        <?= !$user_id ? 'disabled title="Se connecter pour aimer"' : '' ?>>
        <i class="fas fa-thumbs-up"></i>
        <span class="like-count"><?= $like_count ?></span>
    </button>
</div>
        <button  class="btn btn-sm btn-outline-secondary toggle-comments-btn mb-3">
        <i class="fas fa-comments me-2"></i>

 <span class="show-text">(<?= count($comments) ?>)</span>

</button>
    </div>
</div>









</div>
</div>

                    <?php endforeach; ?>
                <?php endif; ?>

        </div>
    </div> 


 

    <script src="http://localhost:3000/assets/modules/channel-web/inject.js"></script>
<script>
    window.botpressWebChat.init({
        host: 'http://localhost:3000',
        botId: 'tourisme-forestier'
    });

    
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // عرض/إخفاء النصائح الأمنية عند النقر على الأيقونة
document.querySelectorAll('.safety-tips-toggle').forEach(button => {
    button.addEventListener('click', function() {
        const tipsContent = this.nextElementSibling;
        if (tipsContent.style.display === 'none') {
            tipsContent.style.display = 'block';
            this.innerHTML = '<i class="fas fa-times text-danger"></i>';
        } else {
            tipsContent.style.display = 'none';
            this.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i>';
        }
    });
});






// Fonction pour basculer l'affichage des commentaires
document.querySelectorAll('.toggle-comments-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const commentsList = this.closest('.trip-comments-section').querySelector('.comments-list');
        const showText = this.querySelector('.show-text');
        const hideText = this.querySelector('.hide-text');
        
        if (commentsList.style.display === 'none') {
            commentsList.style.display = 'block';
            showText.style.display = 'none';
            hideText.style.display = 'inline';
        } else {
            commentsList.style.display = 'none';
            showText.style.display = 'inline';
            hideText.style.display = 'none';
        }
    });
});

document.querySelectorAll('.like-trip-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        const tripId = this.getAttribute('data-trip-id');
        const likeCountEl = this.querySelector('.like-count');
        const isLiked = this.classList.contains('btn-primary');

        try {
            const response = await fetch('handle_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `trip_id=${tripId}&action=${isLiked ? 'unlike' : 'like'}`
            });

            const result = await response.json();

            if (result.success) {
                // تحديث العدد
                likeCountEl.textContent = result.like_count;

                // تبديل الألوان
                if (isLiked) {
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-outline-primary');
                } else {
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-primary');
                }
            } else {
                alert(result.message || 'حدث خطأ أثناء العملية');
            }

        } catch (error) {
            console.error('Error:', error);
            alert('فشل الاتصال، يرجى المحاولة لاحقاً');
        }
    });
});


// Fonction pour formater la date (similaire à votre time_ago PHP)
function timeAgo(date) {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    
    if (seconds < 60) return 'à l\'instant';
    if (seconds < 3600) return `il y a ${Math.floor(seconds / 60)} min`;
    if (seconds < 86400) return `il y a ${Math.floor(seconds / 3600)} h`;
    return new Date(date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

// Mise à jour des dates en temps réel
document.querySelectorAll('.comment-item small').forEach(el => {
    const date = el.getAttribute('data-time');
    if (date) {
        el.textContent = timeAgo(date);
        // Actualiser toutes les minutes
        setInterval(() => {
            el.textContent = timeAgo(date);
        }, 60000);
    }
});

function bookTrip(tripId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const participants = 1;
    
    fetch('book_trip.php?id=<?= $trip['id'] ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ trip_id: tripId, participants: participants })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showAlert('Réservation confirmée!', 'success');
            location.reload();
        } else {
            showAlert(data.message || 'Erreur lors de la réservation', 'error');
        }
    })
    .catch(error => {
        console.error("Erreur réseau ou serveur:", error);
        showAlert('Échec de la connexion au serveur. Vérifiez les logs.', 'error');
    });
}
// Fonction pour afficher les alertes (à adapter selon votre système)
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} fixed-top mx-auto mt-3`;
    alertDiv.style.maxWidth = '500px';
    alertDiv.style.zIndex = '1100';
    alertDiv.textContent = message;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => alertDiv.remove(), 5000);
}
    </script>
    <!-- تحسينات JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // إرسال التعليق باستخدام AJAX
    document.querySelectorAll('.comment-form-ajax').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            const feedbackEl = this.querySelector('.feedback-message');
            
            // عرض حالة التحميل
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
            submitBtn.disabled = true;
            feedbackEl.textContent = '';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // إضافة التعليق الجديد
                    addNewComment(data);
                    
                    // مسح حقل النص
                    this.querySelector('textarea').value = '';
                    
                    // تحديث عدد التعليقات
                    updateCommentsCount(this.closest('.trip-comments-section'), 1);
                } else {
                    feedbackEl.textContent = data.message || 'حدث خطأ أثناء إضافة التعليق';
                    feedbackEl.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedbackEl.textContent = 'حدث خطأ في الاتصال بالخادم. يرجى المحاولة لاحقاً.';
                feedbackEl.style.display = 'block';
            })
            .finally(() => {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            });
        });
    });
    
    // دالة لإضافة تعليق جديد إلى القائمة
    function addNewComment(commentData) {
        const commentsContainer = document.querySelector('.comments-list');
        const noCommentsAlert = commentsContainer.querySelector('.alert');
        
        const newComment = document.createElement('div');
        newComment.className = 'comment-item mb-3 p-3 bg-light rounded';
        newComment.innerHTML = `
            <div class="d-flex">
                <img src="${commentData.profile_pic}" 
                     alt="${commentData.username}" 
                     class="rounded-circle me-3" width="50" height="50">
                     
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>${commentData.username}</strong>
                        <small class="text-muted">الآن</small>
                    </div>
                    <p class="mb-0">${commentData.content}</p>
                </div>
            </div>
        `;
        
        if (noCommentsAlert) {
            commentsContainer.innerHTML = '';
        }
        
        commentsContainer.prepend(newComment);
    }
    
    // دالة لتحديث عدد التعليقات
    function updateCommentsCount(sectionElement, increment) {
        const countElement = sectionElement.querySelector('.comments-count');
        if (countElement) {
            const currentCount = parseInt(countElement.textContent) || 0;
            countElement.textContent = currentCount + increment;
        }
    }
});
</script>


<script>// إدارة عرض/إخفاء التعليقات
document.querySelectorAll('.toggle-comments-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const commentsSection = this.nextElementSibling;
        const showText = this.querySelector('.show-text');
        const hideText = this.querySelector('.hide-text');
        
        if (commentsSection.style.display === 'none') {
            commentsSection.style.display = 'block';
            showText.style.display = 'none';
            hideText.style.display = 'inline';
        } else {
            commentsSection.style.display = 'none';
            showText.style.display = 'inline';
            hideText.style.display = 'none';
        }
    });
});
</script>


<script>


/**
 * Gets total booked participants count for a trip
 * @param PDO $pdo Database connection
 * @param int $trip_id Trip ID
 * @return int Total booked participants count
 */
function get_booked_count(PDO $pdo, int $trip_id): int {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(participants), 0) as total FROM bookings WHERE trip_id = ? AND status IN ('confirmed', 'pending')  ");
        $stmt->execute([$trip_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    } catch (PDOException $e) {
        error_log("Booking count error: " . $e->getMessage());
        return 0; // Fail safe
    }
}
</script>



<script>
// Passer les données PHP au JS
const userFavorites = <?= $favoritesJson ?>;
const initialFavorites = new Set(userFavorites);

document.addEventListener('DOMContentLoaded', () => {
    // Marquer visuellement les favoris
    document.querySelectorAll('.favorite-btn').forEach(btn => {
        const tripId = btn.dataset.tripId;
        if (initialFavorites.has(parseInt(tripId))) {
            btn.classList.add('active');
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.replace('far', 'fas');
            }
        }
    });
});
</script>

<script src="http://localhost:3000/assets/modules/channel-web/inject.js"></script>
<script>
    window.botpressWebChat.init({
        host: 'http://localhost:3000',
        botId: 'tourisme-forestier'
    });
</script>
</body>
</html>