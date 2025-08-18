<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if (!isValidEmail($email)) {
        $_SESSION['error_message'] = "Veuillez entrer une adresse email valide.";
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare('SELECT id, username FROM admin WHERE email = ?');
            $stmt->execute([$email]);
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Générer un token unique
                $token = generateSecureToken();
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Sauvegarder le token dans la base de données
                $stmt = $db->prepare('UPDATE admin SET reset_token = ?, reset_token_expiry = ? WHERE email = ?');
                $stmt->execute([$token, $expiry, $email]);
                
                // Préparer l'email
                $resetLink = getAbsoluteUrl("reset_password.php?token=" . $token);
                $subject = "Réinitialisation de votre mot de passe GOAS";
                $message = "Bonjour,\n\n";
                $message .= "Vous avez demandé la réinitialisation de votre mot de passe.\n";
                $message .= "Cliquez sur le lien suivant pour réinitialiser votre mot de passe :\n";
                $message .= $resetLink . "\n\n";
                $message .= "Ce lien expirera dans 1 heure.\n";
                $message .= "Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.\n\n";
                $message .= "Cordialement,\nL'équipe GOAS";
                
                // Envoyer l'email
                if (sendEmail($email, $subject, $message)) {
                    $_SESSION['success_message'] = "Un email de réinitialisation a été envoyé à votre adresse email.";
                    header('Location: index.php');
                    exit();
                } else {
                    $_SESSION['error_message'] = "Erreur lors de l'envoi de l'email. Veuillez réessayer plus tard.";
                }
            } else {
                $_SESSION['error_message'] = "Aucun compte n'est associé à cette adresse email.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Une erreur est survenue. Veuillez réessayer plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - GOAS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>Mot de passe oublié</h2>
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
                    <label for="email">Email :</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Réinitialiser le mot de passe</button>
                </div>
                <div class="form-group">
                    <a href="index.php" class="btn btn-link">Retour à la connexion</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 