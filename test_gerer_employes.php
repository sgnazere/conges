<?php
session_start();

echo "<h2>Test d'accès à gerer_employes.php</h2>";

// Simuler une session d'administrateur pour le test
$_SESSION['is_logged'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';
$_SESSION['admin_nom'] = 'Administrateur';
$_SESSION['admin_prenoms'] = 'Principal';
$_SESSION['admin_role'] = 'superadmin';

echo "<p style='color: green;'>✅ Session simulée créée</p>";

// Inclure les fichiers nécessaires
try {
    require_once 'check_auth.php';
    echo "<p style='color: green;'>✅ check_auth.php chargé avec succès</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur dans check_auth.php : " . $e->getMessage() . "</p>";
}

try {
    require_once 'config.php';
    echo "<p style='color: green;'>✅ config.php chargé avec succès</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur dans config.php : " . $e->getMessage() . "</p>";
}

// Test de connexion à la base de données
try {
    $db = getDBConnection();
    echo "<p style='color: green;'>✅ Connexion à la base de données réussie</p>";
    
    // Test de récupération des projets
    try {
        $query = $db->query("SELECT DISTINCT projet FROM employees WHERE projet IS NOT NULL AND projet != '' ORDER BY projet");
        $projets = $query->fetchAll(PDO::FETCH_COLUMN);
        echo "<p style='color: green;'>✅ Récupération des projets réussie (" . count($projets) . " projets trouvés)</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur lors de la récupération des projets : " . $e->getMessage() . "</p>";
    }
    
    // Test de récupération des employés
    try {
        $params = [];
        $where = "WHERE status = 'actif' OR status IS NULL";
        $query = $db->prepare("SELECT * FROM employees " . $where . " ORDER BY nom, prenoms");
        $query->execute($params);
        $employes = $query->fetchAll(PDO::FETCH_ASSOC);
        echo "<p style='color: green;'>✅ Récupération des employés réussie (" . count($employes) . " employés trouvés)</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur lors de la récupération des employés : " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur de connexion à la base de données : " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Test de la page complète :</h3>";
echo "<p><a href='gerer_employes.php' target='_blank'>Cliquer ici pour ouvrir gerer_employes.php dans un nouvel onglet</a></p>";

echo "<hr>";
echo "<h3>Actions recommandées :</h3>";
echo "<ol>";
echo "<li>Si les tables n'existent pas, exécutez : <code>php setup_database.php</code></li>";
echo "<li>Vérifiez que XAMPP est démarré (Apache + MySQL)</li>";
echo "<li>Vérifiez que la base de données 'goas' existe</li>";
echo "<li>Vérifiez les permissions de l'utilisateur MySQL</li>";
echo "</ol>";
?>

