<?php
require_once 'config.php';
require_once 'expire_license.php';

function checkLicense() {
    return checkLicenseExpiration();
}

// Fonction pour générer une nouvelle clé de licence
function generateLicenseKey($company_name) {
    $prefix = 'CONGES';
    $year = date('Y');
    $random = strtoupper(substr(md5(uniqid()), 0, 8));
    return sprintf("%s-%s-%s-%s", $prefix, $year, clean($company_name), $random);
}

// Nettoyer le nom de l'entreprise pour la clé de licence
function clean($string) {
    // Remplacer les caractères spéciaux et les espaces par des tirets
    $string = preg_replace('/[^A-Za-z0-9]/', '-', $string);
    // Supprimer les tirets multiples
    $string = preg_replace('/-+/', '-', $string);
    // Limiter à 10 caractères
    return strtoupper(substr($string, 0, 10));
} 