<?php
require_once 'config.php';

try {
    $db = getDBConnection();
    
    // Vérifier si la colonne role existe déjà
    $stmt = $db->query("SHOW COLUMNS FROM admin LIKE 'role'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Ajouter la colonne role
        $db->exec("ALTER TABLE admin ADD COLUMN role ENUM('superadmin', 'admin', 'user') DEFAULT 'admin'");
        echo "Colonne 'role' ajoutée avec succès à la table admin.<br>";
        
        // Mettre à jour les utilisateurs existants
        // Le premier utilisateur devient superadmin, les autres restent admin
        $db->exec("UPDATE admin SET role = 'superadmin' WHERE id = (SELECT MIN(id) FROM admin)");
        echo "Premier utilisateur défini comme superadmin.<br>";
        
        echo "Migration terminée avec succès !";
    } else {
        echo "La colonne 'role' existe déjà dans la table admin.";
    }
    
} catch(PDOException $e) {
    echo "Erreur lors de la modification de la table : " . $e->getMessage();
}
?> 