<?php
require_once 'check_auth.php';
require_once 'functions.php';
require_once 'check_permissions.php';

// Vérifier si l'utilisateur est superadmin
requireRole('superadmin');

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $db = getDBConnection();
            
            switch ($_POST['action']) {
                case 'create':
                    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
                    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                    $password = $_POST['password'];
                    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
                    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
                    $prenoms = filter_input(INPUT_POST, 'prenoms', FILTER_SANITIZE_STRING);
                    
                    // Vérifier si l'utilisateur existe déjà
                    $stmt = $db->prepare('SELECT id FROM admin WHERE username = ? OR email = ?');
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        $_SESSION['error_message'] = "Un utilisateur avec ce nom d'utilisateur ou cet email existe déjà.";
                        break;
                    }
                    
                    // Créer l'utilisateur
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('INSERT INTO admin (username, email, password, role, nom, prenoms, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                    $stmt->execute([$username, $email, $hashedPassword, $role, $nom, $prenoms]);
                    
                    $_SESSION['success_message'] = "Utilisateur créé avec succès.";
                    break;
                    
                case 'update':
                    $id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
                    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
                    $prenoms = filter_input(INPUT_POST, 'prenoms', FILTER_SANITIZE_STRING);
                    
                    // Vérifier que l'utilisateur ne modifie pas son propre rôle
                    if ($id == $_SESSION['admin_id']) {
                        $_SESSION['error_message'] = "Vous ne pouvez pas modifier votre propre rôle.";
                        break;
                    }
                    
                    $stmt = $db->prepare('UPDATE admin SET email = ?, role = ?, nom = ?, prenoms = ? WHERE id = ?');
                    $stmt->execute([$email, $role, $nom, $prenoms, $id]);
                    
                    $_SESSION['success_message'] = "Utilisateur mis à jour avec succès.";
                    break;
                    
                case 'delete':
                    $id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                    
                    // Vérifier que l'utilisateur ne se supprime pas lui-même
                    if ($id == $_SESSION['admin_id']) {
                        $_SESSION['error_message'] = "Vous ne pouvez pas supprimer votre propre compte.";
                        break;
                    }
                    
                    $stmt = $db->prepare('DELETE FROM admin WHERE id = ?');
                    $stmt->execute([$id]);
                    
                    $_SESSION['success_message'] = "Utilisateur supprimé avec succès.";
                    break;
                    
                case 'reset_password':
                    $id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                    $newPassword = generateSecurePassword();
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare('UPDATE admin SET password = ? WHERE id = ?');
                    $stmt->execute([$hashedPassword, $id]);
                    
                    $_SESSION['success_message'] = "Mot de passe réinitialisé. Nouveau mot de passe : " . $newPassword;
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erreur lors de l'opération : " . $e->getMessage();
        }
    }
}

// Récupérer la liste des utilisateurs
try {
    $db = getDBConnection();
    $stmt = $db->query('SELECT id, username, email, role, nom, prenoms, created_at FROM admin ORDER BY created_at DESC');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur lors de la récupération des utilisateurs : " . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="session_timeout.js" defer></script>
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #388E3C;
            --primary-light: #C8E6C9;
            --accent-color: #FF5722;
            --text-primary: #212121;
            --text-secondary: #757575;
            --divider-color: #BDBDBD;
            --background-light: #f5f5f5;
            --white: #ffffff;
            --danger: #dc3545;
            --warning: #ffc107;
            --success: #28a745;
        }
        body {
            background-color: var(--background-light);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
            width: 95%;
        }
        .page-header {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h2 {
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .page-header h2 i {
            color: var(--primary-color);
        }
        .logout-btn {
            background: var(--accent-color);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logout-btn:hover {
            background: #f4511e;
        }
        .user-form-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 3rem;
            border: 1px solid var(--primary-light);
        }
        .form-title {
            color: var(--primary-color);
            font-size: 1.75rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .form-title i {
            font-size: 1.5rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 10px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--divider-color);
            border-radius: 5px;
            font-size: 1rem;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }
        .btn-danger:hover {
            background: #a71d2a;
        }
        .btn-warning {
            background: var(--warning);
            color: var(--text-primary);
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-secondary {
            background: var(--divider-color);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: #888;
        }
        .users-table-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid var(--primary-light);
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        .users-table th, .users-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--divider-color);
            text-align: left;
        }
        .users-table th {
            background: var(--primary-light);
            color: var(--primary-color);
            font-weight: bold;
        }
        .users-table tr:last-child td {
            border-bottom: none;
        }
        .role-badge {
            padding: 0.3em 0.8em;
            border-radius: 12px;
            font-size: 0.95em;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .role-superadmin {
            background: var(--danger);
            color: var(--white);
        }
        .role-admin {
            background: var(--success);
            color: var(--white);
        }
        .role-user {
            background: var(--warning);
            color: var(--text-primary);
        }
        @media (max-width: 900px) {
            .dashboard {
                padding: 0.5rem;
            }
            .users-table-container, .user-form-container {
                padding: 1rem;
            }
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(44, 62, 80, 0.35);
            justify-content: center;
            align-items: center;
            overflow-y: auto;
            transition: background 0.3s;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(44,62,80,0.18);
            padding: 2.5rem 2rem 2rem 2rem;
            max-width: 480px;
            width: 95vw;
            margin: 2rem auto;
            position: relative;
            animation: modalPop 0.25s cubic-bezier(.4,2,.6,1) 1;
        }
        @keyframes modalPop {
            0% { transform: scale(0.95) translateY(40px); opacity: 0; }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }
        .modal-close {
            position: absolute;
            top: 18px;
            right: 18px;
            font-size: 1.5rem;
            color: #aaa;
            background: none;
            border: none;
            cursor: pointer;
            transition: color 0.2s;
            z-index: 10;
        }
        .modal-close:hover {
            color: #dc3545;
        }
        .form-title {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            font-size: 1.4rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.2rem;
        }
        .form-group label {
            font-weight: 500;
            color: #555;
            margin-bottom: 0.3rem;
            display: block;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 7px;
            font-size: 1rem;
            background: #f8f9fa;
            transition: border 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-light);
            background: #fff;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }
        .btn {
            padding: 0.7rem 1.5rem;
            border-radius: 7px;
            font-size: 1rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 8px rgba(44,62,80,0.04);
        }
        .btn-primary {
            background: var(--primary-color);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #bdbdbd;
        }
        @media (max-width: 600px) {
            .modal-content {
                padding: 1.2rem 0.5rem 1.5rem 0.5rem;
            }
            .form-title {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="page-header">
            <h2><i class="fas fa-users-cog"></i> Gestion des Utilisateurs</h2>
            <div>
                <a href="gestion_conges.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
                <button class="btn btn-primary" onclick="openModal('create')"><i class="fas fa-plus"></i> Nouvel Utilisateur</button>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo cleanOutput($_SESSION['success_message']);
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo cleanOutput($_SESSION['error_message']);
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Prénoms</th>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['nom']); ?></td>
                            <td><?php echo htmlspecialchars($user['prenoms']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-primary" title="Modifier" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-warning" title="Réinitialiser le mot de passe" onclick="resetPassword(<?php echo $user['id']; ?>)"><i class="fas fa-key"></i></button>
                                <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                    <button class="btn btn-danger" title="Supprimer" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal pour créer un utilisateur -->
    <div id="createModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2 class="form-title"><i class="fas fa-user-plus"></i> Nouvel Utilisateur</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="nom">Nom:</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="prenoms">Prénoms:</label>
                        <input type="text" id="prenoms" name="prenoms" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Rôle:</label>
                        <select id="role" name="role" required>
                            <option value="user">Utilisateur</option>
                            <option value="admin">Administrateur</option>
                            <option value="superadmin">Super Administrateur</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour modifier un utilisateur -->
    <div id="editModal" class="modal" style="display:none;">
        <div class="modal-content">
            <button type="button" class="modal-close" onclick="closeModal('editModal')" aria-label="Fermer"><i class="fas fa-times"></i></button>
            <h2 class="form-title"><i class="fas fa-user-edit"></i> Modifier l'Utilisateur</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_username">Nom d'utilisateur:</label>
                        <input type="text" id="edit_username" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email:</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_nom">Nom:</label>
                        <input type="text" id="edit_nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_prenoms">Prénoms:</label>
                        <input type="text" id="edit_prenoms" name="prenoms" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Rôle:</label>
                        <select id="edit_role" name="role" required>
                            <option value="user">Utilisateur</option>
                            <option value="admin">Administrateur</option>
                            <option value="superadmin">Super Administrateur</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Modifier</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalType) {
            document.getElementById(modalType + 'Modal').style.display = 'block';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_nom').value = user.nom;
            document.getElementById('edit_prenoms').value = user.prenoms;
            document.getElementById('edit_role').value = user.role;
            openModal('edit');
        }
        function deleteUser(userId, username) {
            if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + username + '" ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        function resetPassword(userId) {
            if (confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html> 