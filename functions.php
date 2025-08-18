<?php
/**
 * Fichier contenant les fonctions utilitaires pour l'application GOAS
 */

/**
 * Vérifie si une adresse email est valide
 * @param string $email L'adresse email à vérifier
 * @return bool True si l'email est valide, false sinon
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Génère un token sécurisé
 * @param int $length Longueur du token (par défaut 32)
 * @return string Le token généré
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Envoie un email avec les paramètres SMTP configurés
 * @param string $to Adresse email du destinataire
 * @param string $subject Sujet de l'email
 * @param string $message Corps de l'email
 * @param array $headers En-têtes additionnels (optionnel)
 * @return bool True si l'email a été envoyé, false sinon
 */
function sendEmail($to, $subject, $message, $headers = []) {
    // Configuration des en-têtes par défaut
    $defaultHeaders = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: GOAS <no-reply@goas.com>',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Fusion avec les en-têtes additionnels
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    // Envoi de l'email
    return mail($to, $subject, $message, implode("\r\n", $allHeaders));
}

/**
 * Vérifie si un mot de passe respecte les critères de sécurité
 * @param string $password Le mot de passe à vérifier
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePassword($password) {
    $result = ['valid' => true, 'message' => ''];
    
    if (strlen($password) < 8) {
        $result['valid'] = false;
        $result['message'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        return $result;
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Le mot de passe doit contenir au moins une majuscule.';
        return $result;
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Le mot de passe doit contenir au moins une minuscule.';
        return $result;
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Le mot de passe doit contenir au moins un chiffre.';
        return $result;
    }
    
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Le mot de passe doit contenir au moins un caractère spécial.';
        return $result;
    }
    
    return $result;
}

/**
 * Nettoie une chaîne de caractères pour l'affichage HTML
 * @param string $str La chaîne à nettoyer
 * @return string La chaîne nettoyée
 */
function cleanOutput($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Génère une URL absolue
 * @param string $path Chemin relatif
 * @return string URL absolue
 */
function getAbsoluteUrl($path) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return sprintf(
        "%s://%s%s/%s",
        $protocol,
        $_SERVER['HTTP_HOST'],
        dirname($_SERVER['PHP_SELF']),
        ltrim($path, '/')
    );
}

/**
 * Génère un mot de passe sécurisé aléatoire
 * @param int $length Longueur du mot de passe (par défaut 12)
 * @return string Le mot de passe généré
 */
function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    
    // S'assurer d'avoir au moins un caractère de chaque type
    $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 25)]; // Majuscule
    $password .= 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)]; // Minuscule
    $password .= '0123456789'[random_int(0, 9)]; // Chiffre
    $password .= '!@#$%^&*()'[random_int(0, 9)]; // Caractère spécial
    
    // Remplir le reste avec des caractères aléatoires
    for ($i = 4; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    // Mélanger le mot de passe
    return str_shuffle($password);
} 