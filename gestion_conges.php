<?php
require_once 'check_auth.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Congés - Tableau de Bord</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 100%;
        }
        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .welcome-header h2 {
            font-size: 24px;
            color: #333;
            margin: 0;
        }
        .welcome-header .user-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .welcome-header .user-name {
            font-size: 24px;
            color: #333;
            margin: 0;
        }
        .welcome-header .user-role {
            font-size: 16px;
            color: #666;
            margin: 0;
        }
        .logout-btn {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .logout-btn:hover {
            background-color: #c82333;
        }
        .dashboard-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .menu-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #007bff;
        }
        .menu-item h3 {
            margin: 15px 0;
            color: #2c3e50;
            font-size: 20px;
        }
        .menu-item p {
            color: #6c757d;
            margin: 10px 0;
            font-size: 15px;
        }
        .menu-item i {
            font-size: 24px;
            color: #007bff;
            margin-bottom: 15px;
        }
        @media (max-width: 768px) {
            .dashboard {
                padding: 20px;
            }
            .welcome-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .dashboard-menu {
                grid-template-columns: 1fr;
            }
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            position: relative;
            height: 80px;
        }
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .header-logo {
            width: 100px;
            height: auto;
            object-fit: contain;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            margin-top: -10px;
        }
        .user-name {
            font-weight: bold;
            color: #333;
            font-size: 18px;
        }
        .user-role {
            color: #666;
            font-size: 14px;
        }
        .logout-btn {
            padding: 8px 15px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .logout-btn:hover {
            background-color: #c82333;
        }
    </style>
    <!-- Ajout de Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="session_timeout.js" defer></script>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="header">
                <div class="user-info">
                    <span class="user-name">
                        <?php 
                        if (isset($_SESSION['admin_nom']) && isset($_SESSION['admin_prenoms'])) {
                            echo htmlspecialchars($_SESSION['admin_prenoms'] . ' ' . $_SESSION['admin_nom']);
                        } else {
                            echo htmlspecialchars($_SESSION['admin_username']);
                        }
                        ?>
                    </span>
                    <span class="user-role">
                        <?php 
                        $roleLabels = [
                            'superadmin' => 'Super Administrateur',
                            'admin' => 'Administrateur',
                            'user' => 'Utilisateur'
                        ];
                        echo isset($_SESSION['admin_role']) ? $roleLabels[strtolower($_SESSION['admin_role'])] : 'Administrateur';
                        ?>
                    </span>
                </div>
                <img src="images/Logo2.png" alt="" class="header-logo">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <div class="dashboard-menu">
                <div class="menu-item" onclick="window.location.href='gerer_employes.php';" style="cursor: pointer;">
                    <i class="fas fa-users"></i>
                    <h3>Gérer les Employés</h3>
                    <p>Ajouter, modifier ou supprimer des employés</p>
                </div>
                
                <div class="menu-item" onclick="window.location.href='gerer_conges.php';" style="cursor: pointer;">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Demandes de Congés</h3>
                    <p>Voir et gérer les demandes de congés</p>
                </div>
                
                <div class="menu-item" onclick="window.location.href='calendrier_conges.php';" style="cursor: pointer;">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Calendrier</h3>
                    <p>Vue d'ensemble des congés</p>
                </div>
                
                <div class="menu-item" onclick="window.location.href='rapports.php';" style="cursor: pointer;">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Rapports</h3>
                    <p>Statistiques et rapports</p>
                </div>
                
                <?php if (isset($_SESSION['admin_role']) && strtolower($_SESSION['admin_role']) === 'superadmin'): ?>
                <div class="menu-item" onclick="window.location.href='gestion_utilisateurs.php';" style="cursor: pointer;">
                    <i class="fas fa-users-cog"></i>
                    <h3>Gestion des Utilisateurs</h3>
                    <p>Gérer les comptes utilisateurs et les rôles</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 