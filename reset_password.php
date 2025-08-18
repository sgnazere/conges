<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Vérifier si un token est fourni
if (!isset($_GET['token'])) {
    header('Location: index.php');
    exit();
}

$token = $_GET['token'];

try {
    $db = getDBConnection();
    
    // Vérifier si le token est valide et non expiré
    $stmt = $db->prepare('SELECT id FROM admin WHERE reset_token = ? AND reset_token_expiry > NOW()');
    $stmt->execute([$token]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error_message'] = "Le lien de réinitialisation est invalide ou a expiré.";
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Une erreur est survenue. Veuillez réessayer plus tard.";
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $_SESSION['error_message'] = "Les mots de passe ne correspondent pas.";
    } else {
        // Valider le mot de passe
        $validation = validatePassword($password);
        if (!$validation['valid']) {
            $_SESSION['error_message'] = $validation['message'];
        } else {
            try {
                // Mettre à jour le mot de passe et supprimer le token
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE admin SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?');
                $stmt->execute([$hashed_password, $token]);
                
                $_SESSION['success_message'] = "Votre mot de passe a été réinitialisé avec succès.";
                header('Location: index.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Une erreur est survenue lors de la réinitialisation du mot de passe.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe - GOAS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>Réinitialisation du mot de passe</h2>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo cleanOutput($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">Nouveau mot de passe :</label>
                    <input type="password" id="password" name="password" required>
                    <small class="form-text text-muted">
                        Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.
                    </small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe :</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 