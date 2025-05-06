<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_type = $stmt->fetchColumn();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $max_participants = trim($_POST['max_participants'] ?? '');
    $difficulty_level = trim($_POST['difficulty_level'] ?? '');
    $forest_type = trim($_POST['forest_type'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? 'bus');
    $activities = $_POST['activities'] ?? [];
    $safety_tips = trim($_POST['safety_tips'] ?? '');

    // Validation des champs
    if (empty($title)) $errors['title'] = "Le titre est requis";
    if (empty($description)) $errors['description'] = "La description est requise";
    if (empty($location)) $errors['location'] = "La localisation est requise";
    if (empty($start_date)) $errors['start_date'] = "La date de début est requise";
    if (empty($end_date)) $errors['end_date'] = "La date de fin est requise";
    if (!is_numeric($price) || $price <= 0) $errors['price'] = "Prix invalide";
    if (!is_numeric($max_participants) || $max_participants <= 0) $errors['max_participants'] = "Nombre de participants invalide";
    
    // Validation du type de véhicule
    $allowed_vehicles = ['bus', 'bus_climatise', 'voiture', 'voiture_4x4', 'minibus', 'autre'];
    if (!in_array($vehicle_type, $allowed_vehicles)) {
        $errors['vehicle_type'] = "Type de véhicule invalide";
    }

    // Validation des dates
    if (empty($errors['start_date']) && empty($errors['end_date'])) {
        if (strtotime($start_date) > strtotime($end_date)) {
            $errors['end_date'] = "La date de fin doit être après la date de début";
        }
        if (strtotime($start_date) < strtotime('today')) {
            $errors['start_date'] = "La date de début doit être dans le futur";
        }
    }

    // Traitement de l'image
    $featured_image = null;
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['featured_image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors['featured_image'] = "Seuls les fichiers JPG, PNG et GIF sont autorisés";
        } else {
            $upload_dir = 'uploads/trips/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
            $file_name = 'trip_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $file_path)) {
                $featured_image = $file_path;
            } else {
                $errors['featured_image'] = "Erreur lors du téléchargement de l'image";
            }
        }
    } else {
        $errors['featured_image'] = "Une image principale est requise";
    }

    // Si pas d'erreurs, insertion en base
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insertion du voyage
            $stmt = $pdo->prepare("
                INSERT INTO trips (
                    organizer_id, title, description, location, start_date, end_date, 
                    price, max_participants, featured_image, difficulty_level, forest_type,
                    vehicle_type, safety_tips
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $title,
                $description,
                $location,
                $start_date,
                $end_date,
                $price,
                $max_participants,
                $featured_image,
                $difficulty_level,
                $forest_type,
                $vehicle_type,
                $safety_tips
            ]);
            
            $trip_id = $pdo->lastInsertId();
            
            // Insertion des activités
            if (!empty($activities)) {
                $stmt = $pdo->prepare("
                    INSERT INTO trip_activities (trip_id, activity) 
                    VALUES (?, ?)
                ");
                
                foreach ($activities as $activity) {
                    $stmt->execute([$trip_id, $activity]);
                }
            }
            
            $pdo->commit();
            $success = true;
            
            // Réinitialisation du formulaire après succès
            $_POST = [];
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
            
            // Suppression de l'image en cas d'erreur
            if ($featured_image && file_exists($featured_image)) {
                unlink($featured_image);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un voyage forestier - Forest Tourism</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2E7D32;
            --secondary-color: #689F38;
            --dark-color: #1B5E20;
            --light-color: #C8E6C9;
            --wood-color: #5D4037;
            --text-dark: #2C3E2D;
            --text-muted: #6C757D;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
        }
        
        .forest-bg {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), 
                        url('https://images.unsplash.com/photo-1448375240586-882707db888b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--dark-color);
            border-color: var(--dark-color);
            transform: translateY(-2px);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .activity-checkbox {
            position: relative;
            padding-left: 2.5rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
        }
        
        .activity-checkbox input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 25px;
            width: 25px;
            background-color: #eee;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .activity-checkbox:hover input ~ .checkmark {
            background-color: #ddd;
        }
        
        .activity-checkbox input:checked ~ .checkmark {
            background-color: var(--primary-color);
        }
        
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }
        
        .activity-checkbox input:checked ~ .checkmark:after {
            display: block;
        }
        
        .activity-checkbox .checkmark:after {
            left: 9px;
            top: 5px;
            width: 7px;
            height: 12px;
            border: solid white;
            border-width: 0 3px 3px 0;
            transform: rotate(45deg);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background-color: var(--light-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--primary-color);
        }
        
        .image-preview {
            width: 100%;
            height: 200px;
            background-color: #f5f5f5;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            border: 2px dashed #ddd;
            transition: all 0.3s ease;
        }
        
        .image-preview:hover {
            border-color: var(--primary-color);
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .image-preview-text {
            text-align: center;
            color: var(--text-muted);
        }
        
        .image-preview-text i {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
        }
        
        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875rem;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 768px) {
            .forest-bg {
                padding: 2rem 0;
                border-radius: 0;
            }
            
            .card {
                border-radius: 0;
            }
        }
        /* أنماط النصائح الأمنية */
.tip-btn {
    text-align: right;
    direction: rtl;
    padding: 0.5rem;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.tip-btn:hover {
    background-color: #f8f9fa;
}

.tip-btn i {
    margin-left: 0.5rem;
    margin-right: 0;
}

#safety_tips {
    direction: rtl;
    text-align: right;
    line-height: 1.8;
}


.trip-vehicle {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.trip-vehicle i {
    color: var(--primary-color);
    font-size: 1.1rem;
}
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="forest-bg">
        <div class="container">
            <div class="row">
                <div class="col-md-8 mx-auto text-center">
                    <h1><i class="fas fa-tree me-2"></i>Ajouter un voyage forestier</h1>
                    <p class="lead">Partagez votre passion pour la nature en créant une nouvelle expérience forestière</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenu principal -->
    <div class="container">
        <?php if ($success): ?>
            <div class="success-message">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-3" style="font-size: 1.5rem;"></i>
                    <div>
                        <h5 class="mb-1">Voyage créé avec succès !</h5>
                        <p class="mb-0">Votre nouvelle expérience forestière est maintenant en ligne.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors['database'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errors['database']) ?>
            </div>
        <?php endif; ?>
        
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Détails du voyage</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <!-- Titre -->
                                    <div class="mb-4">
                                        <label for="title" class="form-label fw-bold">Titre du voyage *</label>
                                        <input type="text" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" 
                                               id="title" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                               placeholder="Randonnée dans la forêt de Brocéliande">
                                        <?php if (isset($errors['title'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['title']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Localisation -->
                                    <div class="mb-4">
                                        <label for="location" class="form-label fw-bold">Localisation *</label>
                                        <input type="text" class="form-control <?= isset($errors['location']) ? 'is-invalid' : '' ?>" 
                                               id="location" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                                               placeholder="Forêt de Fontainebleau, France">
                                        <?php if (isset($errors['location'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['location']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Dates -->
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="start_date" class="form-label fw-bold">Date de début *</label>
                                            <input type="date" class="form-control <?= isset($errors['start_date']) ? 'is-invalid' : '' ?>" 
                                                   id="start_date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                                            <?php if (isset($errors['start_date'])): ?>
                                                <div class="invalid-feedback"><?= htmlspecialchars($errors['start_date']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="end_date" class="form-label fw-bold">Date de fin *</label>
                                            <input type="date" class="form-control <?= isset($errors['end_date']) ? 'is-invalid' : '' ?>" 
                                                   id="end_date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                                            <?php if (isset($errors['end_date'])): ?>
                                                <div class="invalid-feedback"><?= htmlspecialchars($errors['end_date']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Prix et participants -->
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="price" class="form-label fw-bold">Prix (€) *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">€</span>
                                                <input type="number" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>" 
                                                       id="price" name="price" min="0" step="0.01" 
                                                       value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                                            </div>
                                            <?php if (isset($errors['price'])): ?>
                                                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['price']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="max_participants" class="form-label fw-bold">Participants max *</label>
                                            <input type="number" class="form-control <?= isset($errors['max_participants']) ? 'is-invalid' : '' ?>" 
                                                   id="max_participants" name="max_participants" min="1" 
                                                   value="<?= htmlspecialchars($_POST['max_participants'] ?? '') ?>">
                                            <?php if (isset($errors['max_participants'])): ?>
                                                <div class="invalid-feedback"><?= htmlspecialchars($errors['max_participants']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <!-- Image principale -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Image principale *</label>
                                        <div class="image-preview" id="imagePreview">
                                            <?php if (isset($_FILES['featured_image']) && !isset($errors['featured_image'])): ?>
                                                <img src="<?= htmlspecialchars($featured_image) ?>" alt="Aperçu de l'image">
                                            <?php else: ?>
                                                <div class="image-preview-text">
                                                    <i class="fas fa-image"></i>
                                                    <p>Aperçu de l'image</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" class="form-control <?= isset($errors['featured_image']) ? 'is-invalid' : '' ?>" 
                                               id="featured_image" name="featured_image" accept="image/*">
                                        <?php if (isset($errors['featured_image'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['featured_image']) ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted">Format recommandé : 1200x800 pixels</small>
                                    </div>
                                    
                                    <!-- Type de forêt -->
                                    <div class="mb-4">
                                        <label for="forest_type" class="form-label fw-bold">Type de forêt *</label>
                                        <select class="form-select" id="forest_type" name="forest_type">
                                            <option value="deciduous" <?= ($_POST['forest_type'] ?? '') === 'deciduous' ? 'selected' : '' ?>>Feuillus</option>
                                            <option value="coniferous" <?= ($_POST['forest_type'] ?? '') === 'coniferous' ? 'selected' : '' ?>>Conifères</option>
                                            <option value="mixed" <?= ($_POST['forest_type'] ?? '') === 'mixed' ? 'selected' : '' ?>>Mixte</option>
                                            <option value="rainforest" <?= ($_POST['forest_type'] ?? '') === 'rainforest' ? 'selected' : '' ?>>Forêt tropicale</option>
                                            <option value="mangrove" <?= ($_POST['forest_type'] ?? '') === 'mangrove' ? 'selected' : '' ?>>Mangrove</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Niveau de difficulté -->
                                    <div class="mb-4">
                                        <label for="difficulty_level" class="form-label fw-bold">Niveau de difficulté *</label>
                                        <select class="form-select" id="difficulty_level" name="difficulty_level">
                                            <option value="easy" <?= ($_POST['difficulty_level'] ?? '') === 'easy' ? 'selected' : '' ?>>Facile</option>
                                            <option value="moderate" <?= ($_POST['difficulty_level'] ?? '') === 'moderate' ? 'selected' : '' ?>>Modéré</option>
                                            <option value="difficult" <?= ($_POST['difficulty_level'] ?? '') === 'difficult' ? 'selected' : '' ?>>Difficile</option>
                                            <option value="expert" <?= ($_POST['difficulty_level'] ?? '') === 'expert' ? 'selected' : '' ?>>Expert</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- Ajoutez cette section après le champ forest_type -->
<div class="mb-4">
    <label for="vehicle_type" class="form-label fw-bold">Type de véhicule *</label>
    <select class="form-select" id="vehicle_type" name="vehicle_type" required>
        <option value="bus" <?= ($_POST['vehicle_type'] ?? '') === 'bus' ? 'selected' : '' ?>>Bus standard</option>
        <option value="bus_climatise" <?= ($_POST['vehicle_type'] ?? '') === 'bus_climatise' ? 'selected' : '' ?>>Bus climatisé</option>
        <option value="voiture" <?= ($_POST['vehicle_type'] ?? '') === 'voiture' ? 'selected' : '' ?>>Voiture</option>
        <option value="voiture_4x4" <?= ($_POST['vehicle_type'] ?? '') === 'voiture_4x4' ? 'selected' : '' ?>>Voiture 4x4</option>
        <option value="minibus" <?= ($_POST['vehicle_type'] ?? '') === 'minibus' ? 'selected' : '' ?>>Minibus</option>
        <option value="autre" <?= ($_POST['vehicle_type'] ?? '') === 'autre' ? 'selected' : '' ?>>Autre</option>
    </select>
</div>
                            <!-- Description -->
                            <div class="mb-4">
                                <label for="description" class="form-label fw-bold">Description détaillée *</label>
                                <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" 
                                          id="description" name="description" rows="5"
                                          placeholder="Décrivez votre voyage, les points d'intérêt, l'itinéraire..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <?php if (isset($errors['description'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($errors['description']) ?></div>
                                <?php endif; ?>
                            </div>
                       <!-- النصائح الأمنية -->
<div class="mb-4">
    <label class="form-label fw-bold"> Conseils de sécurité pour la région</label>
    
    <!-- خيارات مسبقة -->
    <div class="mb-3">
        <label class="d-block mb-2"> Choisissez parmi les conseils les plus courants :</label>
        <div class="row g-2">
            <div class="col-md-4">
                <button type="button" class="btn btn-outline-secondary w-100 tip-btn" data-tip="1. Forêt hautement inflammable . Se méfier des incendies">
                    <i class="fas fa-fire me-2"></i>Forêt hautement inflammable
                </button>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-outline-secondary w-100 tip-btn" data-tip="1. Des animaux sauvages dangereux . Ne vous éloignez pas du groupe">
                    <i class="fas fa-paw me-2"></i> Zones de faune sauvage
                </button>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-outline-secondary w-100 tip-btn" data-tip="1. La pêche est interdite . Ne pas toucher aux nids et aux œufs">
                    <i class="fas fa-ban me-2"></i> Zones protégées
                </button>
            </div>
        </div>
    </div>
    
    <!-- حقل النصائح -->
    <textarea class="form-control" id="safety_tips" name="safety_tips" rows="4"
              placeholder="Saisissez des conseils de sécurité pour la région, un par ligne..."><?= htmlspecialchars($_POST['safety_tips'] ?? '') ?></textarea>
    <small class="text-muted"> Vous pouvez rédiger des conseils personnalisés ou choisir parmi les menus ci-dessus</small>
</div>
                            <!-- Activités -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Activités proposées</label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="activity-checkbox">
                                            <input type="checkbox" name="activities[]" value="hiking" <?= isset($_POST['activities']) && in_array('hiking', $_POST['activities']) ? 'checked' : '' ?>>
                                            <span class="checkmark"></span>
                                            <span class="d-flex align-items-center">
                                                <span class="activity-icon me-2">
                                                    <i class="fas fa-hiking"></i>
                                                </span>
                                                Randonnée
                                            </span>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="activity-checkbox">
                                            <input type="checkbox" name="activities[]" value="bird_watching" <?= isset($_POST['activities']) && in_array('bird_watching', $_POST['activities']) ? 'checked' : '' ?>>
                                            <span class="checkmark"></span>
                                            <span class="d-flex align-items-center">
                                                <span class="activity-icon me-2">
                                                    <i class="fas fa-dove"></i>
                                                </span>
                                                Observation d'oiseaux
                                            </span>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="activity-checkbox">
                                            <input type="checkbox" name="activities[]" value="camping" <?= isset($_POST['activities']) && in_array('camping', $_POST['activities']) ? 'checked' : '' ?>>
                                            <span class="checkmark"></span>
                                            <span class="d-flex align-items-center">
                                                <span class="activity-icon me-2">
                                                    <i class="fas fa-campground"></i>
                                                </span>
                                                Camping
                                            </span>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="activity-checkbox">
                                            <input type="checkbox" name="activities[]" value="photography" <?= isset($_POST['activities']) && in_array('photography', $_POST['activities']) ? 'checked' : '' ?>>
                                            <span class="checkmark"></span>
                                            <span class="d-flex align-items-center">
                                                <span class="activity-icon me-2">
                                                    <i class="fas fa-camera"></i>
                                                </span>
                                                Photographie
                                            </span>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="activity-checkbox">
                                            <input type="checkbox" name="activities[]" value="wildlife" <?= isset($_POST['activities']) && in_array('wildlife', $_POST['activities']) ? 'checked' : '' ?>>
                                            <span class="checkmark"></span>
                                            <span class="d-flex align-items-center">
                                                <span class="activity-icon me-2">
                                                    <i class="fas fa-paw"></i>
                                                </span>
                                                Observation d'animaux
                                            </span>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="activity-checkbox">
                                            <input type="checkbox" name="activities[]" value="education" <?= isset($_POST['activities']) && in_array('education', $_POST['activities']) ? 'checked' : '' ?>>
                                            <span class="checkmark"></span>
                                            <span class="d-flex align-items-center">
                                                <span class="activity-icon me-2">
                                                    <i class="fas fa-book"></i>
                                                </span>
                                                Éducation environnementale
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Boutons -->
                            <div class="d-flex justify-content-between mt-5">
                                <a href="organizer.php" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Retour
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Enregistrer le voyage
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Aperçu de l'image
    document.getElementById('featured_image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('imagePreview');
        
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="Aperçu de l'image">`;
            }
            
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = `
                <div class="image-preview-text">
                    <i class="fas fa-image"></i>
                    <p>Aperçu de l'image</p>
                </div>
            `;
        }
    });
    
    // Validation des dates
    document.getElementById('start_date').addEventListener('change', function() {
        const startDate = new Date(this.value);
        const endDateInput = document.getElementById('end_date');
        
        if (this.value && endDateInput.value) {
            const endDate = new Date(endDateInput.value);
            
            if (startDate > endDate) {
                endDateInput.value = this.value;
            }
        }
        
        // Définir la date minimale pour end_date
        endDateInput.min = this.value;
    });
    
    // Définir la date minimale pour start_date (aujourd'hui)
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;
        
        // Si start_date est déjà rempli, définir le min pour end_date
        const startDate = document.getElementById('start_date').value;
        if (startDate) {
            document.getElementById('end_date').min = startDate;
        }
    });
    // إضافة النصائح المسبقة عند النقر
document.querySelectorAll('.tip-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const currentTips = document.getElementById('safety_tips').value;
        const newTips = this.getAttribute('data-tip');
        
        if (currentTips.includes(newTips)) {
            alert('هذه النصائح مضافه بالفعل!');
            return;
        }
        
        document.getElementById('safety_tips').value = 
            currentTips ? `${currentTips}\n${newTips}` : newTips;
    });
});
    </script>
</body>
</html>