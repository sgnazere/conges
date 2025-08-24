<?php
session_start();
require_once 'check_license.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['is_logged']) || $_SESSION['is_logged'] !== true) {
    // Rediriger vers la page de connexion
    header('Location: index.php');
    exit();
}

// Vérifier la validité de la licence
$license_check = checkLicense();
if (!$license_check['valid']) {
    // Stocker le message d'erreur dans la session
    $_SESSION['license_error'] = $license_check['message'];

    // Déconnecter l'utilisateur en gardant le message d'erreur
    unset($_SESSION['is_logged']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_nom']);
    unset($_SESSION['admin_prenoms']);
    unset($_SESSION['admin_role']);

    // Rediriger vers la page de connexion pour afficher le message
    header('Location: index.php');
    exit();
}

// Récupérer les informations de l'utilisateur s'ils ne sont pas déjà en session
if (!isset($_SESSION['admin_nom']) || !isset($_SESSION['admin_prenoms']) || !isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare('SELECT id, nom, prenoms, role FROM admin WHERE username = ?');
        $stmt->execute([$_SESSION['admin_username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['admin_nom'] = $user['nom'];
            $_SESSION['admin_prenoms'] = $user['prenoms'];
            $_SESSION['admin_role'] = strtolower($user['role']);
            $_SESSION['admin_id'] = $user['id'];
        }
    } catch(Exception $e) {
        error_log("Erreur lors de la récupération des informations de l'utilisateur : " . $e->getMessage());
        // En cas d'erreur de base de données, déconnecter l'utilisateur et rediriger avec un message
        session_unset(); // Vider la session
        session_destroy(); // Détruire la session
        header('Location: index.php?error=database_error&message=' . urlencode("Une erreur de base de données est survenue."));
        exit();
    }
}
