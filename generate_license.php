<?php
require_once 'config.php';

function generateLicenseKey() {
    $prefix = 'CONGES';
    $timestamp = date('Ymd');
    $random = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    return "$prefix-$timestamp-$random";
}

// Générer la licence
$licenseKey = generateLicenseKey();
$expiryDate = date('Y-m-d', strtotime('+365 days')); // Licence valide 365 jours à partir d'aujourd'hui

// Créer le contenu de la licence
$licenseData = [
    'key' => $licenseKey,
    'expiry_date' => $expiryDate,
    'created_at' => date('Y-m-d'),
    'max_users' => 10
];

// Encoder la licence
$encodedData = base64_encode(json_encode($licenseData));

// Créer une signature
$signature = hash_hmac('sha256', $encodedData, LICENSE_SECRET_KEY);

// Créer le fichier de licence
$license = [
    'data' => $encodedData,
    'signature' => $signature
];

// Sauvegarder dans un fichier temporaire
$licenseFile = 'license.txt';
file_put_contents($licenseFile, json_encode($license));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Génération de Licence - Gestion des Congés</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="form_main">
            <p class="heading">Licence Générée</p>
            
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                Licence générée avec succès !
            </div>
            
            <div class="license-info">
                <p><strong>Clé de licence :</strong> <?php echo htmlspecialchars($licenseKey); ?></p>
                <p><strong>Date d'expiration :</strong> <?php echo htmlspecialchars($expiryDate); ?></p>
                <p><strong>Nombre maximum d'utilisateurs :</strong> <?php echo htmlspecialchars($licenseData['max_users']); ?></p>
            </div>
            
            <div class="inputContainer">
                <label>Contenu de la licence (à copier) :</label>
                <textarea id="licenseContent" 
                          class="inputField" 
                          style="height: 100px; font-family: monospace;"
                          readonly><?php echo htmlspecialchars(json_encode($license)); ?></textarea>
            </div>
            
            <button type="button" id="copyButton" onclick="copyLicense()">
                <i class="fas fa-copy"></i> Copier la licence
            </button>
            
            <a href="validate_license.php" class="button">
                <i class="fas fa-check"></i> Aller à la validation
            </a>
            
            <a href="index.php" class="forgotLink">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>
    
    <script>
    function copyLicense() {
        var textarea = document.getElementById('licenseContent');
        textarea.select();
        document.execCommand('copy');
        
        var button = document.getElementById('copyButton');
        button.innerHTML = '<i class="fas fa-check"></i> Copié !';
        setTimeout(function() {
            button.innerHTML = '<i class="fas fa-copy"></i> Copier la licence';
        }, 2000);
    }
    </script>
</body>
</html> 