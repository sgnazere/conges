<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'goas');
define('DB_USER', 'root');
define('DB_PASS', '');

// Clé secrète pour la licence
define('LICENSE_SECRET_KEY', 'CleSecreteConges2024!');

// Fonction de connexion à la base de données
function getDBConnection() {
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch(PDOException $e) {
        error_log("Erreur de connexion à la base de données : " . $e->getMessage());
        throw new Exception("Erreur de connexion à la base de données");
    }
}
