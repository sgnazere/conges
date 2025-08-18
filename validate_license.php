<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'expire_license.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['license'])) {
        $license = json_decode($_POST['license'], true);
        
        if ($license && isset($license['data']) && isset($license['signature'])) {
            // Créer le répertoire config s'il n'existe pas
            if (!file_exists(__DIR__ . '/config')) {
                mkdir(__DIR__ . '/config', 0777, true);
            }
            
            // Sauvegarder la licence
            file_put_contents(__DIR__ . '/config/license.txt', json_encode($license));
            
            // Vérifier immédiatement la licence
            $check = checkLicenseExpiration();
            
            if ($check['valid']) {
                $_SESSION['success_message'] = "Licence installée avec succès !";
                header('Location: index.php');
                exit;
            } else {
                $error_message = $check['message'];
            }
        } else {
            $error_message = "Format de licence invalide";
        }
    } else {
        $error_message = "Veuillez entrer une licence";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Validation de Licence - Gestion des Congés</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <form method="POST" class="form_main">
            <p class="heading">Validation de Licence</p>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="inputContainer">
                <i class="fas fa-key inputIcon"></i>
                <textarea name="license" 
                          class="inputField" 
                          style="height: 100px; font-family: monospace;"
                          placeholder="Collez ici le contenu de la licence"
                          required><?php echo isset($_POST['license']) ? htmlspecialchars($_POST['license']) : ''; ?></textarea>
            </div>
            
            <button type="submit" id="button">
                <i class="fas fa-check"></i> Valider la licence
            </button>
            
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #666; font-size: 0.9em;">
                    Pour obtenir une licence, exécutez generate_license.php
                </p>
            </div>
            
            <a href="index.php" class="forgotLink">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </form>
    </div>
</body>
</html>