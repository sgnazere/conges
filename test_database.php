<?php
require_once 'config.php';

echo "<h2>Test de la base de données</h2>";

try {
    $db = getDBConnection();
    echo "<p style='color: green;'>✅ Connexion à la base de données réussie</p>";
    
    // Vérifier si la table employees existe
    $query = $db->query("SHOW TABLES LIKE 'employees'");
    if ($query->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Table 'employees' existe</p>";
        
        // Vérifier la structure de la table
        $query = $db->query("DESCRIBE employees");
        $columns = $query->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Structure de la table employees :</h3>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li><strong>{$column['Field']}</strong> - {$column['Type']} - {$column['Null']} - {$column['Key']}</li>";
        }
        echo "</ul>";
        
        // Compter les employés
        $query = $db->query("SELECT COUNT(*) as count FROM employees");
        $count = $query->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Nombre d'employés dans la base : <strong>{$count}</strong></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Table 'employees' n'existe pas</p>";
        echo "<p>Exécutez le script setup_database.php pour créer les tables</p>";
    }
    
    // Vérifier si la table admin existe
    $query = $db->query("SHOW TABLES LIKE 'admin'");
    if ($query->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Table 'admin' existe</p>";
        
        // Compter les administrateurs
        $query = $db->query("SELECT COUNT(*) as count FROM admin");
        $count = $query->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Nombre d'administrateurs dans la base : <strong>{$count}</strong></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Table 'admin' n'existe pas</p>";
    }
    
    // Vérifier si la table conges existe
    $query = $db->query("SHOW TABLES LIKE 'conges'");
    if ($query->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Table 'conges' existe</p>";
    } else {
        echo "<p style='color: red;'>❌ Table 'conges' n'existe pas</p>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur de connexion : " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Actions recommandées :</h3>";
echo "<ol>";
echo "<li>Si les tables n'existent pas, exécutez : <code>php setup_database.php</code></li>";
echo "<li>Vérifiez que votre serveur MySQL/MariaDB est démarré</li>";
echo "<li>Vérifiez les paramètres de connexion dans config.php</li>";
echo "</ol>";
?>

