<?php
require_once 'config.php';

try {
    $db = getDBConnection();
    
    // Supprimer l'employé avec l'email spécifique
    $email = 'goas@gmail.com';
    $query = $db->prepare("DELETE FROM employees WHERE email = ?");
    $result = $query->execute([$email]);
    
    if ($result) {
        echo "L'employé avec l'email " . $email . " a été supprimé avec succès.";
    } else {
        echo "Aucun employé n'a été supprimé.";
    }
    
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}