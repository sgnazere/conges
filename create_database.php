<?php
// Configuration de la base de données (sans spécifier la base de données)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

echo "<h2>Création de la base de données</h2>";

try {
    // Connexion sans spécifier la base de données
    $db = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Connexion au serveur MySQL réussie</p>";
    
    // Vérifier si la base de données existe
    $query = $db->query("SHOW DATABASES LIKE 'goas'");
    if ($query->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Base de données 'goas' existe déjà</p>";
    } else {
        // Créer la base de données
        $db->exec("CREATE DATABASE goas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p style='color: green;'>✅ Base de données 'goas' créée avec succès</p>";
    }
    
    // Sélectionner la base de données
    $db->exec("USE goas");
    echo "<p style='color: green;'>✅ Base de données 'goas' sélectionnée</p>";
    
    // Vérifier si les tables existent
    $query = $db->query("SHOW TABLES");
    $tables = $query->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p style='color: orange;'>⚠️ Aucune table trouvée. Création des tables...</p>";
        
        // Lire et exécuter le fichier SQL
        if (file_exists('create_tables.sql')) {
            $sql = file_get_contents('create_tables.sql');
            
            // Split the SQL file into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            // Execute each statement
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    try {
                        $db->exec($statement);
                        echo "<p style='color: green;'>✅ Requête exécutée : " . substr($statement, 0, 50) . "...</p>";
                    } catch (Exception $e) {
                        echo "<p style='color: red;'>❌ Erreur lors de l'exécution : " . $e->getMessage() . "</p>";
                    }
                }
            }
            
            echo "<p style='color: green;'>✅ Tables créées avec succès</p>";
        } else {
            echo "<p style='color: red;'>❌ Fichier create_tables.sql non trouvé</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Tables existantes trouvées : " . implode(', ', $tables) . "</p>";
    }
    
    // Vérifier le contenu des tables
    $query = $db->query("SELECT COUNT(*) as count FROM admin");
    $adminCount = $query->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Nombre d'administrateurs : <strong>{$adminCount}</strong></p>";
    
    $query = $db->query("SELECT COUNT(*) as count FROM employees");
    $employeeCount = $query->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Nombre d'employés : <strong>{$employeeCount}</strong></p>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Actions suivantes :</h3>";
echo "<ol>";
echo "<li><a href='test_database.php'>Tester la base de données</a></li>";
echo "<li><a href='test_gerer_employes.php'>Tester gerer_employes.php</a></li>";
echo "<li><a href='index.php'>Retour à la page de connexion</a></li>";
echo "</ol>";
?>

