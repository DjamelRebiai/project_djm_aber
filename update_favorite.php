<?php
session_start();
require_once 'config.php';

try {
    // 1. Vérifier si l'utilisateur est connecté
    if (empty($_SESSION['user_id'])) {
        $_SESSION['flash_message'] = 'Vous devez être connecté pour gérer vos favoris';
        $_SESSION['flash_type'] = 'error';
        header('Location: login.php?return_to=' . urlencode($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER']));
        exit;
    }

    // 2. Vérifier le token CSRF
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_message'] = 'Jeton de sécurité invalide';
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . ($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER']));
        exit;
    }

    // 3. Valider les données reçues
    if (empty($_POST['trip_id']) || empty($_POST['action'])) {
        $_SESSION['flash_message'] = 'Paramètres manquants';
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . ($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER']));
        exit;
    }

    $tripId = (int)$_POST['trip_id'];
    $userId = (int)$_SESSION['user_id'];
    $action = $_POST['action'] === 'add' ? 'add' : 'remove';

    // 4. Vérifier que le voyage existe
    $stmt = $pdo->prepare("SELECT id FROM trips WHERE id = ?");
    $stmt->execute([$tripId]);
    if (!$stmt->fetch()) {
        $_SESSION['flash_message'] = 'Voyage introuvable';
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . ($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER']));
        exit;
    }

    // 5. Traitement des favoris
    if ($action === 'add') {
        try {
            // Ajouter aux favoris
            $stmt = $pdo->prepare("INSERT INTO favorites (user_id, trip_id) VALUES (?, ?)");
            $stmt->execute([$userId, $tripId]);

            // Ajouter une notification
            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)")
               ->execute([$userId, "Vous avez ajouté un voyage à vos favoris", 'favorite_add']);

            $_SESSION['flash_message'] = 'Voyage ajouté aux favoris';
            $_SESSION['flash_type'] = 'success';

        } catch (PDOException $e) {
            // Si déjà dans les favoris (erreur de duplication)
            if ($e->errorInfo[1] == 1062) {
                $_SESSION['flash_message'] = 'Ce voyage est déjà dans vos favoris';
                $_SESSION['flash_type'] = 'info';
            } else {
                throw $e;
            }
        }
    } else {
        // Retirer des favoris
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND trip_id = ?");
        $stmt->execute([$userId, $tripId]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['flash_message'] = 'Voyage retiré des favoris';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Ce voyage n\'était pas dans vos favoris';
            $_SESSION['flash_type'] = 'info';
        }
    }

    // Redirection vers la page précédente
    header('Location: ' . ($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER']));
    exit;

} catch (PDOException $e) {
    error_log("Erreur PDO: " . $e->getMessage());
    $_SESSION['flash_message'] = 'Une erreur de base de données est survenue';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . ($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER']));
    exit;
} catch (Exception $e) {
    $_SESSION['flash_message'] = $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . ($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER']));
    exit;
}