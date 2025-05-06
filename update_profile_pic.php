<?php
// Vérification du fichier uploadé
if (isset($_FILES['profile_pic'])) {
    $errors = [];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    // Vérification des erreurs d'upload
    if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
        $errors['profile_pic'] = "Erreur lors du téléchargement du fichier";
    }
    
    // Vérification du type MIME
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['profile_pic']['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes)) {
        $errors['profile_pic'] = "Seuls les formats JPG, PNG et GIF sont autorisés";
    }
    
    // Vérification de la taille
    if ($_FILES['profile_pic']['size'] > $maxSize) {
        $errors['profile_pic'] = "La taille maximale autorisée est de 2MB";
    }
    
    // Si aucune erreur, procéder à l'upload
    if (empty($errors)) {
        // Créer le répertoire s'il n'existe pas
        $uploadDir = __DIR__ . '/uploads/user/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Générer un nom de fichier unique
        $extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . uniqid() . '.' . strtolower($extension);
        $destination = $uploadDir . $filename;
        
        // Déplacer le fichier
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
            // Mettre à jour la base de données
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$filename, $_SESSION['user_id']]);
            
            // Message de succès
            $_SESSION['success'] = "Photo de profil mise à jour avec succès";
        } else {
            $errors['profile_pic'] = "Erreur lors de l'enregistrement duvv fichier";
        }
    }
    
    // Si des erreurs, les stocker pour affichage
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
    }
    
    // Rediriger vers la page de profil
    header("Location: profile.php");
    exit;
}