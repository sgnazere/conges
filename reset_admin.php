<?php
require_once 'config.php';

try {
    $db = getDBConnection();

    // Créer un hash sécurisé du mot de passe
    $password = "AJN@2024"; // Mot de passe par défaut
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Mettre à jour le mot de passe de l'admin
    $query = $db->prepare("UPDATE admin SET password = :password WHERE username = 'affiba.j'");
    $query->execute(['password' => $hash]);

    echo "Le mot de passe admin a été réinitialisé avec succès.<br>";
    echo "Nom d'utilisateur: affiba.j<br>";
    echo "Mot de passe: AJN@2024";

} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?> 