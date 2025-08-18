<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

function checkLicenseExpiration() {
    $licenseFile = __DIR__ . '/config/license.txt';
    
    if (!file_exists($licenseFile)) {
        return [
            'valid' => false,
            'message' => 'Aucune licence trouvée. Veuillez installer une licence.'
        ];
    }
    
    $licenseContent = file_get_contents($licenseFile);
    $license = json_decode($licenseContent, true);
    
    if (!$license || !isset($license['data']) || !isset($license['signature'])) {
        return [
            'valid' => false,
            'message' => 'Format de licence invalide.'
        ];
    }
    
    // Vérifier la signature
    $computedSignature = hash_hmac('sha256', $license['data'], LICENSE_SECRET_KEY);
    
    if ($computedSignature !== $license['signature']) {
        return [
            'valid' => false,
            'message' => 'Signature de licence invalide.'
        ];
    }
    
    // Décoder les données de licence
    $licenseData = json_decode(base64_decode($license['data']), true);
    
    if (!$licenseData) {
        return [
            'valid' => false,
            'message' => 'Données de licence corrompues.'
        ];
    }
    
    // Vérifier la date d'expiration
    $expiryDate = strtotime($licenseData['expiry_date']);
    $today = strtotime('today');
    
    if ($today > $expiryDate) {
        return [
            'valid' => false,
            'message' => 'Licence expirée. Date d\'expiration : ' . $licenseData['expiry_date']
        ];
    }
    
    try {
        // Vérifier le nombre d'utilisateurs
        $db = getDBConnection();
        $query = $db->query("SELECT COUNT(*) as total FROM employees");
        $row = $query->fetch(PDO::FETCH_ASSOC);
        $totalUsers = $row['total'];
        
        if ($totalUsers > $licenseData['max_users']) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Nombre d\'utilisateurs (%d) supérieur à la limite de la licence (%d)',
                    $totalUsers,
                    $licenseData['max_users']
                )
            ];
        }
        
        return [
            'valid' => true,
            'license' => $licenseData,
            'users' => [
                'current' => $totalUsers,
                'max' => $licenseData['max_users']
            ]
        ];
    } catch(Exception $e) {
        error_log("Erreur lors de la vérification du nombre d'utilisateurs : " . $e->getMessage());
        return [
            'valid' => false,
            'message' => 'Erreur lors de la vérification du nombre d\'utilisateurs.'
        ];
    }
}

// Si appelé directement, retourner le statut en JSON
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Content-Type: application/json');
    echo json_encode(checkLicenseExpiration());
} 