<?php
require_once 'config.php';

try {
    $db = getDBConnection();
    
    $username = 'ahizi.g';
    $password = password_hash('AGN@2024Ec', PASSWORD_DEFAULT);
    $email = 'ahizi.g@ec-ci.org';
    $nom = 'AHIZI';
    $prenoms = 'GNAZERE';
    
    $stmt = $db->prepare('INSERT INTO admin (username, password, email, nom, prenoms) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$username, $password, $email, $nom, $prenoms]);
    
    echo "Utilisateur créé avec succès !\n";
    echo "Nom d'utilisateur : " . $username . "\n";
    echo "Email : " . $email . "\n";
    echo "Nom : " . $nom . "\n";
    echo "Prénoms : " . $prenoms . "\n";
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
} 