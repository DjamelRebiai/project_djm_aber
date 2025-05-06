<?php
require_once 'config.php';
session_start();

// Vérification de l'ID du voyage
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_trips.php");
    exit();
}

$trip_id = (int)$_GET['id'];
$errors = [];
$success = false;

// Récupération des informations du voyage
try {
    $stmt = $pdo->prepare("SELECT * FROM trips WHERE id = ?");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        header("Location: manage_trips.php");
        exit();
    }

} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Définition des valeurs autorisées
$allowed_difficulties = ['easy', 'moderate', 'difficult', 'expert'];
$allowed_forest_types = ['deciduous', 'coniferous', 'mixed', 'rainforest', 'mangrove'];
$allowed_statuses = ['actif', 'complet', 'annulé'];
$allowed_vehicles = ['bus', 'bus_climatise', 'voiture', 'voiture_4x4', 'minibus', 'autre'];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyage et validation des données
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $price = (float)$_POST['price'];
    $max_participants = (int)$_POST['max_participants'];
    $difficulty = $_POST['difficulty_level'];
    $forest_type = $_POST['forest_type'];
    $vehicle_type = $_POST['vehicle_type'];
    $status = $_POST['status'];

    // Validation
    if (empty($title)) {
        $errors['title'] = "Le titre est obligatoire";
    } elseif (strlen($title) > 100) {
        $errors['title'] = "Le titre ne doit pas dépasser 100 caractères";
    }

    if (empty($description)) {
        $errors['description'] = "La description est obligatoire";
    }

    if (empty($location)) {
        $errors['location'] = "La localisation est obligatoire";
    }

    if (empty($start_date)) {
        $errors['start_date'] = "La date de début est obligatoire";
    } elseif ($start_date < date('Y-m-d')) {
        $errors['start_date'] = "La date de début ne peut pas être dans le passé";
    }

    if (empty($end_date)) {
        $errors['end_date'] = "La date de fin est obligatoire";
    } elseif ($end_date < $start_date) {
        $errors['end_date'] = "La date de fin doit être après la date de début";
    }

    if ($price <= 0) {
        $errors['price'] = "Le prix doit être un nombre positif";
    }

    if ($max_participants <= 0) {
        $errors['max_participants'] = "Le nombre maximum de participants doit être un nombre positif";
    }

    if (!in_array($difficulty, $allowed_difficulties)) {
        $errors['difficulty_level'] = "Niveau de difficulté invalide";
    }

    if (!in_array($forest_type, $allowed_forest_types)) {
        $errors['forest_type'] = "Type de forêt invalide";
    }

    if (!in_array($vehicle_type, $allowed_vehicles)) {
        $errors['vehicle_type'] = "Type de véhicule invalide";
    }

    if (!in_array($status, $allowed_statuses)) {
        $errors['status'] = "Statut invalide";
    }

    // Traitement de l'image si fournie
    $featured_image = $trip['featured_image'];
    if (!empty($_FILES['featured_image']['name'])) {
        $upload_dir = 'uploads/trips/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if ($_FILES['featured_image']['size'] > $max_size) {
            $errors['featured_image'] = "L'image ne doit pas dépasser 5MB";
        } elseif (!in_array($_FILES['featured_image']['type'], $allowed_types)) {
            $errors['featured_image'] = "Type de fichier non autorisé (seuls JPEG, PNG et GIF sont acceptés)";
        } else {
            $extension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
            $filename = 'trip_' . $trip_id . '_' . time() . '.' . $extension;
            $destination = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $destination)) {
                // Supprimer l'ancienne image si elle existe
                if (!empty($featured_image) && file_exists($featured_image)) {
                    unlink($featured_image);
                }
                $featured_image = $destination;
            } else {
                $errors['featured_image'] = "Erreur lors du téléchargement de l'image";
            }
        }
    }

    // Mise à jour si aucune erreur
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE trips SET 
                title = ?, 
                description = ?, 
                location = ?,
                start_date = ?, 
                end_date = ?, 
                price = ?, 
                max_participants = ?, 
                difficulty_level = ?,
                forest_type = ?,
                vehicle_type = ?,
                status = ?,
                featured_image = ?,
                updated_at = NOW()
                WHERE id = ?");

            $stmt->execute([
                $title,
                $description,
                $location,
                $start_date,
                $end_date,
                $price,
                $max_participants,
                $difficulty,
                $forest_type,
                $vehicle_type,
                $status,
                $featured_image,
                $trip_id
            ]);

            $success = true;
            $_SESSION['success_message'] = "Le voyage a été mis à jour avec succès!";
            header("Refresh: 2; url=organizer.php");
        } catch (PDOException $e) {
            $errors['general'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un voyage - Tourisme Forestier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
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

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Tajawal', sans-serif;
        }

        body {
            background-color: var(--light-color);
            color: var(--dark-color);
            line-height: 1.6;
            background-image: url('images/forest-bg.jpg');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
        }

        .edit-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .edit-box {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 700px;
            transition: all 0.3s ease;
        }

        .edit-box:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 32px;
            margin-right: 12px;
            color: var(--primary-color);
        }

        .logo span {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-color);
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--dark-color);
            position: relative;
            padding-bottom: 15px;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }

        .error-message {
            color: var(--error-color);
            font-size: 14px;
            margin-top: 5px;
            display: block;
        }

        .update-btn {
            width: 100%;
            padding: 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .update-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 139, 87, 0.3);
        }

        .success-message {
            background-color: #e8f5e9;
            color: var(--primary-dark);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            border-left: 4px solid var(--primary-color);
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
            color: var(--gray-color);
        }

        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        @media (max-width: 576px) {
            .edit-box {
                padding: 30px 20px;
            }

            .logo i {
                font-size: 28px;
            }

            .logo span {
                font-size: 20px;
            }

            h2 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <div class="edit-box">
            <div class="logo">
                <i class="fas fa-tree"></i>
                <span>Tourisme Forestier</span>
            </div>

            <h2>Modifier le voyage</h2>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php elseif ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> Le voyage a été mis à jour avec succès!
                </div>
            <?php elseif (isset($errors['general'])): ?>
                <div class="error-message" style="margin-bottom: 20px; text-align: center;">
                    <i class="fas fa-exclamation-circle"></i> <?= $errors['general'] ?>
                </div>
            <?php endif; ?>

            <form action="edit_trip.php?id=<?= $trip_id ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Titre du voyage</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($trip['title'] ?? '') ?>" required>
                    <?php if (isset($errors['title'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['title'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required><?= htmlspecialchars($trip['description'] ?? '') ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['description'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="location">Localisation</label>
                    <input type="text" id="location" name="location" value="<?= htmlspecialchars($trip['location'] ?? '') ?>" required>
                    <?php if (isset($errors['location'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['location'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Date de début</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($trip['start_date'] ?? '') ?>" required>
                        <?php if (isset($errors['start_date'])): ?>
                            <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['start_date'] ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="end_date">Date de fin</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($trip['end_date'] ?? '') ?>" required>
                        <?php if (isset($errors['end_date'])): ?>
                            <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['end_date'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Prix (€)</label>
                        <input type="number" id="price" name="price" min="0" step="0.01" value="<?= htmlspecialchars($trip['price'] ?? '') ?>" required>
                        <?php if (isset($errors['price'])): ?>
                            <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['price'] ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="max_participants">Nombre maximum de participants</label>
                        <input type="number" id="max_participants" name="max_participants" min="1" value="<?= htmlspecialchars($trip['max_participants'] ?? '') ?>" required>
                        <?php if (isset($errors['max_participants'])): ?>
                            <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['max_participants'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="difficulty_level">Niveau de difficulté</label>
                        <select id="difficulty_level" name="difficulty_level" required>
                            <option value="easy" <?= ($trip['difficulty_level'] ?? '') === 'easy' ? 'selected' : '' ?>>Facile</option>
                            <option value="moderate" <?= ($trip['difficulty_level'] ?? '') === 'moderate' ? 'selected' : '' ?>>Moyen</option>
                            <option value="difficult" <?= ($trip['difficulty_level'] ?? '') === 'difficult' ? 'selected' : '' ?>>Difficile</option>
                            <option value="expert" <?= ($trip['difficulty_level'] ?? '') === 'expert' ? 'selected' : '' ?>>Expert</option>
                        </select>
                        <?php if (isset($errors['difficulty_level'])): ?>
                            <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['difficulty_level'] ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="forest_type">Type de forêt</label>
                        <select id="forest_type" name="forest_type" required>
                            <option value="deciduous" <?= ($trip['forest_type'] ?? '') === 'deciduous' ? 'selected' : '' ?>>Feuillus</option>
                            <option value="coniferous" <?= ($trip['forest_type'] ?? '') === 'coniferous' ? 'selected' : '' ?>>Conifères</option>
                            <option value="mixed" <?= ($trip['forest_type'] ?? '') === 'mixed' ? 'selected' : '' ?>>Mixte</option>
                            <option value="rainforest" <?= ($trip['forest_type'] ?? '') === 'rainforest' ? 'selected' : '' ?>>Forêt tropicale</option>
                            <option value="mangrove" <?= ($trip['forest_type'] ?? '') === 'mangrove' ? 'selected' : '' ?>>Mangrove</option>
                        </select>
                        <?php if (isset($errors['forest_type'])): ?>
                            <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['forest_type'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="vehicle_type">Type de véhicule</label>
                        <select id="vehicle_type" name="vehicle_type" required>
                            <option value="bus" <?= ($trip['vehicle_type'] ?? '') === 'bus' ? 'selected' : '' ?>>Bus standard</option>
                            <option value="bus_climatise" <?= ($trip['vehicle_type'] ?? '') === 'bus_climatise' ? 'selected' : '' ?>>Bus climatisé</option>
                            <option value="voiture" <?= ($trip['vehicle_type'] ?? '') === 'voiture' ? 'selected' : '' ?>>Voiture</option>
                            <option value="voiture_4x4" <?= ($trip['vehicle_type'] ?? '') === 'voiture_4x4' ? 'selected' : '' ?>>Voiture 4x4</option>
                            <option value="minibus" <?= ($trip['vehicle_type'] ?? '') === 'minibus' ? 'selected' : '' ?>>Minibus</option>
                            <option value="autre" <?= ($trip['vehicle_type'] ?? '') === 'autre' ? 'selected' : '' ?>>Autre</option>
                        </select>
                        <?php if (isset($errors['vehicle_type'])): ?>
                            <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['vehicle_type'] ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select id="status" name="status" required>
                            <option value="actif" <?= ($trip['status'] ?? '') === 'actif' ? 'selected' : '' ?>>Actif</option>
                            <option value="complet" <?= ($trip['status'] ?? '') === 'complet' ? 'selected' : '' ?>>Complet</option>
                            <option value="annulé" <?= ($trip['status'] ?? '') === 'annulé' ? 'selected' : '' ?>>Annulé</option>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['status'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="featured_image">Image principale</label>
                    <?php if (!empty($trip['featured_image'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?= htmlspecialchars($trip['featured_image']) ?>" alt="Image actuelle" style="max-width: 200px; max-height: 150px; display: block;">
                            <small>Image actuelle</small>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="featured_image" name="featured_image" accept="image/jpeg,image/png,image/gif">
                    <?php if (isset($errors['featured_image'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?= $errors['featured_image'] ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="update-btn">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>

            <div class="back-link">
                <a href="organizer.php"><i class="fas fa-arrow-left"></i> Retour à la gestion des voyages</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editBox = document.querySelector('.edit-box');
            editBox.style.opacity = '0';
            editBox.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                editBox.style.opacity = '1';
                editBox.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>