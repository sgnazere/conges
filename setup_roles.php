<?php
require_once 'config.php';
require_once 'functions.php';

echo "<h1>Configuration du Système de Rôles</h1>";

try {
    $db = getDBConnection();
    
    // 1. Vérifier si la colonne role existe
    echo "<h2>1. Vérification de la structure de la table</h2>";
    $stmt = $db->query("SHOW COLUMNS FROM admin LIKE 'role'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "<p style='color: red;'>❌ La colonne 'role' n'existe pas. Ajout en cours...</p>";
        
        // Ajouter la colonne role
        $db->exec("ALTER TABLE admin ADD COLUMN role ENUM('superadmin', 'admin', 'user') DEFAULT 'admin'");
        echo "<p style='color: green;'>✅ Colonne 'role' ajoutée avec succès.</p>";
        
        // Mettre à jour les utilisateurs existants
        $db->exec("UPDATE admin SET role = 'superadmin' WHERE id = (SELECT MIN(id) FROM admin)");
        echo "<p style='color: green;'>✅ Premier utilisateur défini comme superadmin.</p>";
    } else {
        echo "<p style='color: green;'>✅ La colonne 'role' existe déjà.</p>";
    }
    
    // 2. Vérifier les utilisateurs existants
    echo "<h2>2. Utilisateurs existants</h2>";
    $stmt = $db->query("SELECT id, username, email, role, nom, prenoms FROM admin ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p style='color: orange;'>⚠️ Aucun utilisateur trouvé dans la base de données.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nom d'utilisateur</th><th>Email</th><th>Nom</th><th>Prénoms</th><th>Rôle</th></tr>";
        
        foreach ($users as $user) {
            $roleColor = [
                'superadmin' => '#dc3545',
                'admin' => '#28a745',
                'user' => '#ffc107'
            ];
            $color = $roleColor[$user['role']] ?? '#6c757d';
            
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['nom']}</td>";
            echo "<td>{$user['prenoms']}</td>";
            echo "<td style='color: white; background-color: {$color}; padding: 5px; border-radius: 3px;'>{$user['role']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Créer un utilisateur de test si aucun n'existe
    if (empty($users)) {
        echo "<h2>3. Création d'un utilisateur superadmin de test</h2>";
        
        $testPassword = generateSecurePassword();
        $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO admin (username, email, password, role, nom, prenoms, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute(['admin', 'admin@goas.com', $hashedPassword, 'superadmin', 'Administrateur', 'Principal']);
        
        echo "<p style='color: green;'>✅ Utilisateur superadmin créé avec succès.</p>";
        echo "<p><strong>Nom d'utilisateur:</strong> admin</p>";
        echo "<p><strong>Mot de passe:</strong> {$testPassword}</p>";
        echo "<p><strong>Email:</strong> admin@goas.com</p>";
    }
    
    // 4. Vérifier les permissions
    echo "<h2>4. Test des permissions</h2>";
    
    // Simuler une session pour tester les permissions
    $_SESSION['admin_role'] = 'superadmin';
    require_once 'check_permissions.php';
    
    echo "<p>Test des permissions pour un superadmin :</p>";
    echo "<ul>";
    echo "<li>isSuperAdmin(): " . (isSuperAdmin() ? "✅ True" : "❌ False") . "</li>";
    echo "<li>isAdmin(): " . (isAdmin() ? "✅ True" : "❌ False") . "</li>";
    echo "<li>hasRole('user'): " . (hasRole('user') ? "✅ True" : "❌ False") . "</li>";
    echo "</ul>";
    
    $_SESSION['admin_role'] = 'admin';
    echo "<p>Test des permissions pour un admin :</p>";
    echo "<ul>";
    echo "<li>isSuperAdmin(): " . (isSuperAdmin() ? "✅ True" : "❌ False") . "</li>";
    echo "<li>isAdmin(): " . (isAdmin() ? "✅ True" : "❌ False") . "</li>";
    echo "<li>hasRole('user'): " . (hasRole('user') ? "✅ True" : "❌ False") . "</li>";
    echo "</ul>";
    
    $_SESSION['admin_role'] = 'user';
    echo "<p>Test des permissions pour un utilisateur :</p>";
    echo "<ul>";
    echo "<li>isSuperAdmin(): " . (isSuperAdmin() ? "✅ True" : "❌ False") . "</li>";
    echo "<li>isAdmin(): " . (isAdmin() ? "✅ True" : "❌ False") . "</li>";
    echo "<li>hasRole('user'): " . (hasRole('user') ? "✅ True" : "❌ False") . "</li>";
    echo "</ul>";
    
    echo "<h2>5. Configuration terminée</h2>";
    echo "<p style='color: green;'>✅ Le système de rôles est maintenant configuré et fonctionnel.</p>";
    echo "<p><a href='gestion_conges.php' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Accéder au tableau de bord</a></p>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur lors de la configuration : " . $e->getMessage() . "</p>";
}
?> 