<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

// Récupérer et valider l'email
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

if (!isValidEmail($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez entrer une adresse email valide.'
    ]);
    exit;
}

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
            echo json_encode([
                'success' => true,
                'message' => "Un email de réinitialisation a été envoyé à votre adresse email."
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "Erreur lors de l'envoi de l'email. Veuillez réessayer plus tard."
            ]);
        }
    } else {
        // Pour des raisons de sécurité, ne pas indiquer si l'email existe ou non
        echo json_encode([
            'success' => true,
            'message' => "Si cette adresse email est associée à un compte, vous recevrez un email de réinitialisation."
        ]);
    }
} catch (PDOException $e) {
    error_log("Erreur de réinitialisation de mot de passe : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Une erreur est survenue. Veuillez réessayer plus tard."
    ]);
} 