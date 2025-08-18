<?php
require_once 'check_auth.php';
require_once 'config.php';

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
   header('Location: gestion_conges.php?error=acces_interdit');
    exit();
}
// Initialisation des variables
$error = '';
$success = '';
$username = '';
$email = '';
$edit_id = null;
$edit_username = '';
$edit_email = '';
$nom = '';
$prenoms = '';
$role = '';

$db = getDBConnection();

// Suppression d'un utilisateur
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    if ($delete_id > 0) {
        try {
            $stmt = $db->prepare('DELETE FROM admin WHERE id = ?');
            $stmt->execute([$delete_id]);
            $success = "Utilisateur supprimé avec succès.";
        } catch(PDOException $e) {
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// Préparation de l'édition
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $db->prepare('SELECT * FROM admin WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_user) {
        $edit_username = $edit_user['username'];
        $edit_email = $edit_user['email'];
        $nom = $edit_user['nom'] ?? '';
        $prenoms = $edit_user['prenoms'] ?? '';
        $role = $edit_user['role'] ?? '';
        $username = $edit_username;
        $email = $edit_email;
    }
}

// Modification d'un utilisateur
if (isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $edit_username = trim($_POST['username'] ?? '');
    $edit_email = trim($_POST['email'] ?? '');
    $edit_nom = trim($_POST['nom'] ?? '');
    $edit_prenoms = trim($_POST['prenoms'] ?? '');
    $edit_role = trim($_POST['role'] ?? '');
    $edit_password = $_POST['password'] ?? '';
    $edit_password_confirm = $_POST['password_confirm'] ?? '';
    
    if ($edit_username === '' || $edit_email === '' || $edit_nom === '' || $edit_prenoms === '' || $edit_role === '') {
        $error = "Tous les champs sont obligatoires pour la modification.";
    } elseif (!filter_var($edit_email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } elseif ($edit_password !== '' && $edit_password !== $edit_password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $stmt = $db->prepare('SELECT COUNT(*) FROM admin WHERE (username = ? OR email = ?) AND id != ?');
        $stmt->execute([$edit_username, $edit_email, $edit_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Nom d'utilisateur ou email déjà utilisé.";
        } else {
            try {
                if ($edit_password !== '') {
                    // Si un nouveau mot de passe est fourni, l'inclure dans la mise à jour
                    $password_hash = password_hash($edit_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('UPDATE admin SET username = ?, email = ?, nom = ?, prenoms = ?, role = ?, password = ? WHERE id = ?');
                    $stmt->execute([$edit_username, $edit_email, $edit_nom, $edit_prenoms, $edit_role, $password_hash, $edit_id]);
                    $success = "Utilisateur modifié avec succès (mot de passe inclus).";
                } else {
                    // Si aucun nouveau mot de passe n'est fourni, ne pas modifier le mot de passe
                    $stmt = $db->prepare('UPDATE admin SET username = ?, email = ?, nom = ?, prenoms = ?, role = ? WHERE id = ?');
                    $stmt->execute([$edit_username, $edit_email, $edit_nom, $edit_prenoms, $edit_role, $edit_id]);
                    $success = "Utilisateur modifié avec succès.";
                }
                $edit_id = null;
                $username = $email = $nom = $prenoms = '';
                $role = '';
            } catch(PDOException $e) {
                $error = "Erreur lors de la modification : " . $e->getMessage();
            }
        }
    }
}

// Création d'un utilisateur
if (isset($_POST['create_user'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $nom = trim($_POST['nom'] ?? '');
    $prenoms = trim($_POST['prenoms'] ?? '');
    $role = trim($_POST['role'] ?? '');
    if ($username === '' || $email === '' || $password === '' || $password_confirm === '' || $nom === '' || $prenoms === '' || $role === '') {
        $error = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } elseif ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $stmt = $db->prepare('SELECT COUNT(*) FROM admin WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Nom d'utilisateur ou email déjà utilisé.";
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO admin (username, password, email, nom, prenoms, role) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$username, $password_hash, $email, $nom, $prenoms, $role]);
                $success = "Utilisateur créé avec succès !";
                $username = $email = $nom = $prenoms = '';
                $role = 'admin';
            } catch(PDOException $e) {
                $error = "Erreur lors de la création : " . $e->getMessage();
            }
        }
    }
}

// Récupérer la liste des utilisateurs
$stmt = $db->query('SELECT * FROM admin ORDER BY created_at DESC');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Voir un utilisateur (affichage détails)
$view_user = null;
if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);
    $stmt = $db->prepare('SELECT * FROM admin WHERE id = ?');
    $stmt->execute([$view_id]);
    $view_user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            max-width: 900px;
            margin: 0 auto;
            width: 98%;
        }
        .employee-form-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 3rem;
            border: 1px solid var(--primary-light);
        }
        .form-title {
            color: var(--primary-color);
            font-size: 1.5rem;
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
            gap: 10mm;
            padding: 10mm 0 0 0;
        }
        .form-group {
            margin-bottom: 10mm;
            background: var(--background-light);
            padding: 1rem;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }
        .form-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }
        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
            transform: scale(1.02);
        }
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
            transform: scale(1.02);
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .submit-btn {
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 0.6rem 1.2rem;
            border: 1px solid var(--primary-color);
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            justify-content: center;
            width: auto;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        .submit-btn:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .submit-btn i { font-size: 0.9rem; }
        .success-message {
            background: var(--success);
            color: var(--white);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .error-message {
            background: var(--danger);
            color: var(--white);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .user-list-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .list-header {
            color: var(--primary-color);
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: #fafafa;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 0.7rem 0.5rem;
            text-align: left;
        }
        th {
            background: var(--primary-color);
            color: #fff;
            font-weight: 600;
        }
        tr:nth-child(even) { background: #f2f2f2; }
        td.actions { text-align: center; }
        .icon-btn { background: none; border: none; color: #388E3C; font-size: 1.1rem; cursor: pointer; margin: 0 0.3rem; transition: color 0.2s; }
        .icon-btn.delete { color: #d32f2f; }
        .icon-btn:hover { color: #222; }
        .icon-btn.delete:hover { color: #b71c1c; }
        .edit-form { display: flex; gap: 0.5rem; align-items: center; }
        .edit-form input[type="text"], .edit-form input[type="email"] { padding: 0.4rem 0.5rem; font-size: 1rem; }
        .edit-form .icon-btn { font-size: 1.2rem; }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover { text-decoration: underline; }
        .modal-view {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.3);
            align-items: center;
            justify-content: center;
        }
        .modal-view.active {
            display: flex;
        }
        .modal-content-view {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            padding: 2rem 2.5rem;
            min-width: 320px;
            max-width: 90vw;
            position: relative;
        }
        .modal-content-view h4 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 1.2rem;
        }
        .modal-content-view .close-view {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.3rem;
            color: var(--danger);
            cursor: pointer;
        }
        .modal-content-view .detail-row {
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
        }
        .modal-content-view .detail-label {
            color: var(--text-secondary);
            font-weight: 500;
            min-width: 120px;
        }
        .modal-content-view .detail-value {
            color: var(--text-primary);
            font-weight: 400;
        }
    </style>
    <script>
        function confirmDelete(username) {
            return confirm('Voulez-vous vraiment supprimer l\'utilisateur "' + username + '" ?');
        }
        function showViewModal() {
            document.getElementById('modalView').classList.add('active');
        }
        function closeViewModal() {
            document.getElementById('modalView').classList.remove('active');
            window.location.href = 'create_user.php';
        }
        
        // Validation des mots de passe lors de l'édition
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('password_confirm');
            
            if (passwordField && confirmField) {
                function validatePasswords() {
                    const password = passwordField.value;
                    const confirm = confirmField.value;
                    
                    if (password !== '' || confirm !== '') {
                        if (password !== confirm) {
                            confirmField.setCustomValidity('Les mots de passe ne correspondent pas');
                        } else {
                            confirmField.setCustomValidity('');
                        }
                    } else {
                        confirmField.setCustomValidity('');
                    }
                }
                
                passwordField.addEventListener('input', validatePasswords);
                confirmField.addEventListener('input', validatePasswords);
            }
        });
    </script>
</head>
<body>
    <div class="dashboard">
        <div class="employee-form-container">
            <h3 class="form-title">
                <i class="fas <?php echo isset($edit_id) && $edit_id ? 'fa-edit' : 'fa-user-plus'; ?>"></i>
                <?php echo isset($edit_id) && $edit_id ? 'Modifier un utilisateur' : 'Ajouter un utilisateur'; ?>
            </h3>
            <?php if ($error): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($success): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <?php if (isset($edit_id) && $edit_id): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <?php else: ?>
                    <input type="hidden" name="create_user" value="1">
                <?php endif; ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="email">Adresse email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($nom); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="prenoms">Prénoms</label>
                        <input type="text" id="prenoms" name="prenoms" value="<?php echo htmlspecialchars($prenoms); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Rôle</label>
                        <select id="role" name="role" required>
                            <option value="admin" <?php if($role==='admin') echo 'selected'; ?>>Administrateur</option>
                            <option value="superadmin" <?php if($role==='superadmin') echo 'selected'; ?>>Super Administrateur</option>
                            <option value="user" <?php if($role==='user') echo 'selected'; ?>>Utilisateur</option>
                        </select>
                    </div>
                    <?php if (!isset($edit_id) || !$edit_id): ?>
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirmer le mot de passe</label>
                        <input type="password" id="password_confirm" name="password_confirm" required>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label for="password">Nouveau mot de passe (optionnel)</label>
                        <input type="password" id="password" name="password" placeholder="Laissez vide pour conserver l'actuel">
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="password_confirm" name="password_confirm" placeholder="Laissez vide pour conserver l'actuel">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-btn">
                        <i class="fas <?php echo isset($edit_id) && $edit_id ? 'fa-save' : 'fa-user-plus'; ?>"></i>
                        <?php echo isset($edit_id) && $edit_id ? 'Modifier l\'utilisateur' : 'Créer l\'utilisateur'; ?>
                    </button>
                    <?php if (isset($edit_id) && $edit_id): ?>
                        <a href="create_user.php" class="submit-btn" style="background:var(--danger);color:#fff;border-color:var(--danger);"><i class="fas fa-times"></i> Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="user-list-container">
            <div class="list-header"><i class="fas fa-users-cog"></i> Liste des utilisateurs</div>
            <table>
                <thead>
                    <tr>
                        <th>Nom d'utilisateur</th>
                        <th>Nom</th>
                        <th>Prénoms</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['nom']); ?></td>
                        <td><?php echo htmlspecialchars($user['prenoms']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                        <td class="actions">
                            <a href="create_user.php?view=<?php echo $user['id']; ?>" class="icon-btn" title="Voir"><i class="fas fa-eye"></i></a>
                            <a href="create_user.php?edit=<?php echo $user['id']; ?>" class="icon-btn" title="Modifier"><i class="fas fa-edit"></i></a>
                            <a href="create_user.php?delete=<?php echo $user['id']; ?>" class="icon-btn delete" title="Supprimer" onclick="return confirmDelete('<?php echo htmlspecialchars($user['username']); ?>');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a href="gestion_conges.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour au tableau de bord</a>
    </div>

    <?php if ($view_user): ?>
    <div class="modal-view active" id="modalView" onclick="closeViewModal()">
        <div class="modal-content-view" onclick="event.stopPropagation();">
            <span class="close-view" onclick="closeViewModal()"><i class="fas fa-times"></i></span>
            <h4><i class="fas fa-eye"></i> Détail de l'utilisateur</h4>
            <div class="detail-row"><span class="detail-label">Nom d'utilisateur :</span> <span class="detail-value"><?php echo htmlspecialchars($view_user['username']); ?></span></div>
            <div class="detail-row"><span class="detail-label">Nom :</span> <span class="detail-value"><?php echo htmlspecialchars($view_user['nom']); ?></span></div>
            <div class="detail-row"><span class="detail-label">Prénoms :</span> <span class="detail-value"><?php echo htmlspecialchars($view_user['prenoms']); ?></span></div>
            <div class="detail-row"><span class="detail-label">Email :</span> <span class="detail-value"><?php echo htmlspecialchars($view_user['email']); ?></span></div>
            <div class="detail-row"><span class="detail-label">Rôle :</span> <span class="detail-value"><?php echo htmlspecialchars($view_user['role']); ?></span></div>
            <div class="detail-row"><span class="detail-label">Créé le :</span> <span class="detail-value"><?php echo htmlspecialchars($view_user['created_at']); ?></span></div>
            <div class="detail-row"><span class="detail-label">ID :</span> <span class="detail-value"><?php echo htmlspecialchars($view_user['id']); ?></span></div>
        </div>
    </div>
    <script>showViewModal();</script>
    <?php endif; ?>
    
    <!-- Script pour la gestion du timeout de session -->
    <script src="session_timeout.js"></script>
</body>
</html> 