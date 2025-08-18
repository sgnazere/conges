<?php
/**
 * Fichier pour vérifier les permissions selon les rôles
 */

/**
 * Vérifie si l'utilisateur a le rôle requis
 * @param string $requiredRole Le rôle requis ('superadmin', 'admin', 'user')
 * @return bool True si l'utilisateur a le rôle requis ou supérieur
 */
function hasRole($requiredRole) {
    if (!isset($_SESSION['admin_role'])) {
        return false;
    }
    
    $roleHierarchy = [
        'user' => 1,
        'admin' => 2,
        'superadmin' => 3
    ];
    
    $userRole = strtolower($_SESSION['admin_role'] ?? 'user');
    $requiredRole = strtolower($requiredRole);
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

/**
 * Vérifie si l'utilisateur est superadmin
 * @return bool True si l'utilisateur est superadmin
 */
function isSuperAdmin() {
    return hasRole('superadmin');
}

/**
 * Vérifie si l'utilisateur est admin ou superadmin
 * @return bool True si l'utilisateur est admin ou superadmin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Redirige l'utilisateur s'il n'a pas les permissions requises
 * @param string $requiredRole Le rôle requis
 * @param string $redirectUrl URL de redirection (par défaut: gestion_conges.php)
 */
function requireRole($requiredRole, $redirectUrl = 'gestion_conges.php') {
    if (!hasRole($requiredRole)) {
        $_SESSION['error_message'] = "Accès refusé. Permissions insuffisantes.";
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Affiche un contenu conditionnel selon le rôle
 * @param string $requiredRole Le rôle requis
 * @param callable $callback Fonction à exécuter si l'utilisateur a le rôle requis
 */
function ifHasRole($requiredRole, $callback) {
    if (hasRole($requiredRole)) {
        $callback();
    }
}