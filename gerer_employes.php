<?php
require_once 'check_auth.php';
require_once 'config.php';

$db = getDBConnection();

// Récupérer la liste unique des projets
try {
    $query = $db->query("SELECT DISTINCT projet FROM employees WHERE projet IS NOT NULL AND projet != '' ORDER BY projet");
    $projets = $query->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $error_message = "Erreur lors de la récupération des projets : " . $e->getMessage();
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    // Validation du numéro CNPS
    if (isset($_POST['numero_cnps'])) {
        if (!preg_match('/^[0-9]{12}$/', $_POST['numero_cnps'])) {
            $response = [
                'success' => false,
                'message' => "Le numéro CNPS doit contenir exactement 12 chiffres"
            ];
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                try {
                    $query = $db->prepare("INSERT INTO employees (nom, prenoms, email, date_naissance, poste, projet, date_embauche, telephone, numero_cnps, type_contrat, numero_urgence) VALUES (:nom, :prenoms, :email, :date_naissance, :poste, :projet, :date_embauche, :telephone, :numero_cnps, :type_contrat, :numero_urgence)");
                    $query->execute([
                        'nom' => $_POST['nom'],
                        'prenoms' => $_POST['prenoms'],
                        'email' => $_POST['email'],
                        'date_naissance' => $_POST['date_naissance'],
                        'poste' => $_POST['poste'],
                        'projet' => $_POST['projet'],
                        'date_embauche' => $_POST['date_embauche'],
                        'telephone' => $_POST['telephone'],
                        'numero_cnps' => $_POST['numero_cnps'],
                        'type_contrat' => $_POST['type_contrat'],
                        'numero_urgence' => $_POST['numero_urgence']
                    ]);
                    $response = [
                        'success' => true,
                        'message' => "Employé ajouté avec succès"
                    ];
                } catch(PDOException $e) {
                    $response = [
                        'success' => false,
                        'message' => "Erreur lors de l'ajout : " . $e->getMessage()
                    ];
                }
                break;

            case 'modifier':
                try {
                    $query = $db->prepare("UPDATE employees SET nom = :nom, prenoms = :prenoms, email = :email, date_naissance = :date_naissance, poste = :poste, projet = :projet, date_embauche = :date_embauche, telephone = :telephone, numero_cnps = :numero_cnps, type_contrat = :type_contrat, numero_urgence = :numero_urgence WHERE id = :id");
                    $query->execute([
                        'id' => $_POST['id'],
                        'nom' => $_POST['nom'],
                        'prenoms' => $_POST['prenoms'],
                        'email' => $_POST['email'],
                        'date_naissance' => $_POST['date_naissance'],
                        'poste' => $_POST['poste'],
                        'projet' => $_POST['projet'],
                        'date_embauche' => $_POST['date_embauche'],
                        'telephone' => $_POST['telephone'],
                        'numero_cnps' => $_POST['numero_cnps'],
                        'type_contrat' => $_POST['type_contrat'],
                        'numero_urgence' => $_POST['numero_urgence']
                    ]);
                    $response = [
                        'success' => true,
                        'message' => "Employé modifié avec succès"
                    ];
                } catch(PDOException $e) {
                    $response = [
                        'success' => false,
                        'message' => "Erreur lors de la modification : " . $e->getMessage()
                    ];
                }
                break;

            case 'supprimer':
                try {
                    // Au lieu de supprimer, on met à jour le statut
                    $query = $db->prepare("UPDATE employees SET status = 'inactif' WHERE id = :id");
                    $query->execute(['id' => $_POST['id']]);
                    $response = [
                        'success' => true,
                        'message' => "Employé archivé avec succès"
                    ];
                } catch(PDOException $e) {
                    $response = [
                        'success' => false,
                        'message' => "Erreur lors de l'archivage : " . $e->getMessage()
                    ];
                }
                break;
        }
    }
    
    // Si c'est une requête AJAX, renvoyer la réponse JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Récupération des employés avec filtre pour l'affichage initial
try {
    $where = "";
    $params = [];
    
    if (isset($_GET['projet']) && !empty($_GET['projet'])) {
        $where = "WHERE projet = :projet AND (status = 'actif' OR status IS NULL)";
        $params['projet'] = $_GET['projet'];
    } else {
        $where = "WHERE status = 'actif' OR status IS NULL";
    }
    
    $query = $db->prepare("SELECT * FROM employees " . $where . " ORDER BY nom, prenoms");
    $query->execute($params);
    $employes = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Erreur lors de la récupération des employés : " . $e->getMessage();
}

// Fonction pour calculer le nombre d'années
function calculerAnnees($date_embauche) {
    $date1 = new DateTime($date_embauche);
    $date2 = new DateTime();
    $interval = $date1->diff($date2);
    return $interval->y;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Employés</title>
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
            gap: 10mm;
            padding: 10mm;
        }

        .form-grid button[type="submit"] {
            grid-column: 1;
            justify-self: start;
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

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
            transform: scale(1.02);
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
            margin-top: 5mm;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .submit-btn:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .submit-btn i {
            font-size: 0.9rem;
        }

        .submit-btn:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .employee-list-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-light);
        }

        .list-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .employee-count {
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .filter-section {
            background: var(--background-light);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .filter-section label {
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-section select {
            padding: 0.5rem;
            border: 1px solid var(--divider-color);
            border-radius: 5px;
            font-size: 1rem;
        }

        .reset-filter {
            color: var(--danger);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .reset-filter:hover {
            text-decoration: underline;
        }

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

        /* Responsive Design */
        @media (max-width: 1600px) {
            .dashboard {
                width: 98%;
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .dashboard {
                width: 100%;
                padding: 0.5rem;
            }

            .employee-list-container {
                padding: 1rem;
            }
        }

        /* Styles pour le tableau DataTable */
        .table-responsive {
            margin-top: 2rem;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .employee-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            margin-bottom: 1rem;
        }

        .employee-table th {
            padding: 1rem;
            background: var(--primary-color);
            color: var(--white);
            font-weight: 600;
            text-align: left;
        }

        .employee-table td {
            padding: 1rem;
            vertical-align: middle;
        }

        /* Largeurs spécifiques pour les colonnes */
        .employee-table th:nth-child(1), /* Nom */
        .employee-table td:nth-child(1) {
            width: 12%;
        }

        .employee-table th:nth-child(2), /* Prénoms */
        .employee-table td:nth-child(2) {
            width: 12%;
        }

        .employee-table th:nth-child(3), /* Téléphone */
        .employee-table td:nth-child(3) {
            width: 10%;
        }

        .employee-table th:nth-child(4), /* Email */
        .employee-table td:nth-child(4) {
            width: 15%;
        }

        .employee-table th:nth-child(5), /* Projet */
        .employee-table td:nth-child(5) {
            width: 15%;
        }

        .employee-table th:nth-child(6), /* Type contrat */
        .employee-table td:nth-child(6) {
            width: 10%;
        }

        .employee-table th:nth-child(7), /* Poste */
        .employee-table td:nth-child(7) {
            width: 12%;
        }

        .employee-table th:nth-child(8), /* Actions */
        .employee-table td:nth-child(8) {
            width: 10%;
        }

        /* Style pour le contenu qui dépasse */
        .employee-table td {
            max-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Style pour le hover sur les lignes */
        .employee-table tbody tr:hover {
            background-color: var(--background-light);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
        }

        .btn-edit, .btn-delete {
            padding: 0.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
        }

        .btn-edit {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .btn-delete {
            background-color: var(--danger);
            color: var(--white);
        }

        .btn-edit:hover, .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* Styles pour la pagination DataTable */
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 1rem;
            padding: 1rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 1rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            cursor: pointer;
            background: var(--white);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        /* Styles pour la recherche DataTable */
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
            display: flex;
            justify-content: flex-end;
        }

        .dataTables_wrapper .dataTables_filter input {
            padding: 0.5rem 1rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            margin-left: 0.5rem;
            transition: all 0.3s ease;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        /* Styles pour le nombre d'entrées par page */
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 0.5rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            margin: 0 0.5rem;
        }

        /* Ajout des styles spécifiques pour les colonnes */
        .employee-table th[data-column="projet"] {
            min-width: 200px;
            width: 20%;
        }

        .employee-table th[data-column="prenoms"] {
            min-width: 180px;
            width: 18%;
        }

        .employee-table th[data-column="nom"] {
            width: 12%;
        }

        .employee-table th[data-column="anciennete"] {
            width: 100px;
        }

        .employee-table td[data-column="projet"] {
            font-weight: 500;
            color: var(--primary-dark);
        }

        .employee-table td[data-column="prenoms"] {
            font-weight: 500;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--white);
        }

        .btn-primary {
            background-color: var(--primary-color);
        }

        .btn-secondary {
            background-color: var(--text-secondary);
        }

        .btn-edit {
            background-color: var(--primary-color);
        }

        .btn-delete {
            background-color: var(--danger);
        }

        .btn-refresh {
            background-color: var(--warning);
            color: var(--text-primary);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .form-actions .btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            min-width: 100px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .form-actions .btn i {
            font-size: 0.8rem;
        }

        .form-actions .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .form-actions .btn-secondary {
            background-color: var(--text-secondary);
            color: var(--white);
        }

        .form-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Styles pour les boutons de navigation */
        .header-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--white);
            color: var(--primary-color);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 2px solid var(--primary-color);
        }

        .nav-btn i {
            font-size: 1.1rem;
        }

        .nav-btn:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .nav-btn-danger {
            background: var(--white);
            color: var(--danger);
            border-color: var(--danger);
        }

        .nav-btn-danger:hover {
            background: var(--danger);
            color: var(--white);
        }

        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .nav-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Styles pour la modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: var(--white);
            margin: 15% auto;
            padding: 0;
            border-radius: 8px;
            width: 400px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
        }

        .modal-header {
            padding: 1rem;
            background-color: var(--primary-color);
            color: var(--white);
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h4 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem;
            border-top: 1px solid var(--divider-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .close {
            color: var(--white);
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--primary-light);
        }

        /* Style pour le formulaire en mode modification */
        .form-title.edit-mode {
            color: var(--warning);
        }

        .btn-edit-mode {
            background-color: var(--warning);
            color: var(--text-primary);
        }

        /* Styles pour l'infobulle */
        .employee-tooltip {
            display: none;
            position: absolute;
            background-color: #333;
            color: #fff;
            padding: 10px;
            border-radius: 5px;
            z-index: 1000;
            max-width: 300px;
            pointer-events: none; /* Empêche l'infobulle de capturer les événements de la souris */
            font-size: 0.9rem;
            line-height: 1.4;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .employee-tooltip h5 {
            margin: 0 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #555;
            font-size: 1rem;
            color: var(--primary-light);
        }

        .employee-tooltip p {
            margin: 5px 0;
        }

        .employee-tooltip strong {
            color: var(--primary-light);
        }
    </style>
    <!-- Ajout des liens CSS et JS pour DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
</head>
<body>
   <div class="container">
        <div class="dashboard">
            <div class="page-header">
                <h2><i class="fas fa-users"></i> Gestion des Employés</h2>
                <div class="header-actions">
                    <a href="gestion_conges.php" class="nav-btn">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                    <a href="gerer_conges.php" class="nav-btn">
                        <i class="fas fa-calendar-check"></i> Gérer les congés
                    </a>
                    <a href="calendrier_conges.php" class="nav-btn">
                        <i class="fas fa-calendar-alt"></i> Calendrier
                    </a>
                    <a href="rapports.php" class="nav-btn">
                        <i class="fas fa-chart-bar"></i> Rapports
                    </a>
                    <a href="export_employes_excel.php" class="nav-btn">
                        <i class="fas fa-chart-bar"></i> Exports
                    </a>
                    <a href="logout.php" class="nav-btn nav-btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Formulaire d'ajout dans un container -->
            <div class="employee-form-container">
                <h3 class="form-title"><i class="fas fa-plus-circle"></i> Ajouter un employé</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" name="nom" id="nom" required>
                        </div>
                        <div class="form-group">
                            <label for="prenoms">Prénoms</label>
                            <input type="text" name="prenoms" id="prenoms" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email">
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" name="telephone" id="telephone" pattern="[0-9]{10}" title="Numéro de téléphone à 10 chiffres" required>
                        </div>
                        <div class="form-group">
                            <label for="numero_urgence">Numéro d'urgence</label>
                            <input type="tel" name="numero_urgence" id="numero_urgence" pattern="[0-9]{10}" title="Numéro de téléphone à 10 chiffres">
                        </div>
                        <div class="form-group">
                            <label for="numero_cnps">Numéro CNPS</label>
                            <input type="text" name="numero_cnps" id="numero_cnps" pattern="[0-9]{12}" maxlength="12" title="Numéro CNPS à 12 chiffres" required>
                        </div>
                        <div class="form-group">
                            <label for="type_contrat">Type de contrat</label>
                            <select name="type_contrat" id="type_contrat" required>
                                <option value="">Sélectionnez un type de contrat</option>
                                <option value="CDI">CDI</option>
                                <option value="CDD">CDD</option>
                                <option value="Stage">Stage</option>
                                <option value="Prestation">Prestation</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_naissance">Date de naissance</label>
                            <input type="date" name="date_naissance" id="date_naissance" required>
                        </div>
                        <div class="form-group">
                            <label for="poste">Poste</label>
                            <input type="text" name="poste" id="poste" required>
                        </div>
                        <div class="form-group">
                            <label for="projet">Projet</label>
                            <select name="projet" id="projet" class="form-control" required>
                                <option value="">Sélectionnez un projet</option>
                                <option value="Agir">Agir</option>
                                <option value="Epic">Epic</option>
                                <option value="Fon">Fon</option>
                                <option value="Hiv Free">Hiv Free</option>
                                <option value="Inili">Inili</option>
                                <option value="Perle et Lag">Perle et Lag</option>
                                <option value="Plaidoyer">Plaidoyer</option>
                                <option value="Pouvoir">Pouvoir</option>
                                <option value="Rise">Rise</option>
                                <option value="Risk'Zero">Risk'Zero</option>
                                <option value="Soutra">Soutra</option>
                                <option value="Ya Fohi">Ya Fohi</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_embauche">Date d'embauche</label>
                            <input type="date" name="date_embauche" id="date_embauche" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Ajouter
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        
        <!-- Liste des employés avec filtre -->
        <div class="employee-list-container">
        <div class="table-responsive">
                <div class="list-header">
                    <h3 class="list-title"><i class="fas fa-list"></i> Liste des employés</h3>
                    <span class="employee-count"><?php echo count($employes); ?> employé(s)</span>
                </div>
                    <div>      
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Prénoms</th>
                                <th>Téléphone</th>
                                <th>Email</th>
                                <th>Projet</th>
                                <th>Type contrat</th>
                                <th>Poste</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employes as $employe): ?>
                                <tr data-date-naissance="<?php echo htmlspecialchars($employe['date_naissance']); ?>"
                                    data-date-embauche="<?php echo htmlspecialchars($employe['date_embauche']); ?>"
                                    data-numero-cnps="<?php echo htmlspecialchars($employe['numero_cnps']); ?>"
                                    data-numero-urgence="<?php echo htmlspecialchars($employe['numero_urgence']); ?>">
                                    <td><?php echo htmlspecialchars($employe['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($employe['prenoms']); ?></td>
                                    <td><?php echo htmlspecialchars($employe['telephone']); ?></td>
                                    <td><?php echo htmlspecialchars($employe['email']); ?></td>
                                    <td><?php echo htmlspecialchars($employe['projet']); ?></td>
                                    <td><?php echo htmlspecialchars($employe['type_contrat']); ?></td>
                                    <td><?php echo htmlspecialchars($employe['poste']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-edit" data-id="<?php echo $employe['id']; ?>" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-delete" data-id="<?php echo $employe['id']; ?>" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal" id="deleteModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-exclamation-triangle"></i> Confirmation d'archivage</h4>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir archiver cet employé ?</p>
                <p>L'employé sera marqué comme inactif et n'apparaîtra plus dans la liste, mais ses données historiques seront conservées.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelDelete">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="button" class="btn btn-warning" id="confirmDelete">
                    <i class="fas fa-archive"></i> Archiver
                </button>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Configuration de DataTables
        var table = $('.employee-table').DataTable({
            responsive: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
            },
            pageLength: 10,
            order: [[0, 'asc']],
            scrollX: false,
            columnDefs: [
                {
                    targets: -1,
                    orderable: false,
                    searchable: false
                }
            ],
            drawCallback: function() {
                initializeActionButtons();
            }
        });

        // Intercepter la soumission du formulaire
        $('form').on('submit', function(e) {
            e.preventDefault();
            
            const action = $('form').find('input[name="action"]').val();
            
            // Si c'est une modification, on soumet directement le formulaire
            if (action === 'modifier') {
                submitForm();
                return;
            }
            
            // Pour un nouvel employé, on vérifie les doublons
            const nom = $('#nom').val();
            const prenoms = $('#prenoms').val();
            
            // Vérifier d'abord s'il y a un doublon
            $.ajax({
                url: 'check_duplicate_employee.php',
                method: 'GET',
                data: {
                    nom: nom,
                    prenoms: prenoms
                },
                success: function(response) {
                    if (response.exists) {
                        // Afficher un message d'erreur avec les détails de l'employé existant
                        const message = response.message + "\n\n" +
                            "Détails de l'employé existant :\n" +
                            "Nom : " + response.employee.nom + "\n" +
                            "Prénoms : " + response.employee.prenoms + "\n" +
                            "Poste : " + response.employee.poste;
                        
                        alert(message);
                    } else {
                        // Pas de doublon, on peut soumettre le formulaire
                        submitForm();
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Erreur lors de la vérification des doublons: ' + error, 'error');
                }
            });
        });

        function submitForm() {
            // Récupérer les données du formulaire
            var formData = $('form').serialize();
            
            // Envoyer la requête AJAX
            $.ajax({
                url: 'gerer_employes.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Afficher le message de succès
                        showMessage(response.message, 'success');
                        
                        // Réinitialiser le formulaire
                        resetForm();
                        
                        // Recharger la liste des employés
                        reloadEmployeeList();
                    } else {
                        // Afficher le message d'erreur
                        showMessage(response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Une erreur est survenue: ' + error, 'error');
                }
            });
        }

        function showMessage(message, type) {
            // Supprimer les anciens messages
            $('.success-message, .error-message').remove();
            
            // Créer le nouvel élément de message
            var messageDiv = $('<div>')
                .addClass(type === 'success' ? 'success-message' : 'error-message')
                .text(message);
            
            // Insérer le message au début du dashboard
            $('.dashboard').prepend(messageDiv);
            
            // Faire défiler jusqu'au message
            messageDiv[0].scrollIntoView({ behavior: 'smooth' });
            
            // Faire disparaître le message après 5 secondes
            setTimeout(function() {
                messageDiv.fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        }

        function reloadEmployeeList() {
            $.ajax({
                url: 'get_employees_list.php',
                method: 'GET',
                data: { 
                    draw: 1,
                    length: $('.employee-table').DataTable().page.len(),
                    start: 0
                },
                success: function(response) {
                    if (response.error) {
                        showMessage(response.error, 'error');
                        return;
                    }
                    
                    // Détruire et réinitialiser la table
                    var table = $('.employee-table').DataTable();
                    table.destroy();
                    
                    // Vider et remplir le tbody avec les nouvelles données
                    var tbody = $('.employee-table tbody');
                    tbody.empty();
                    
                    response.data.forEach(function(row) {
                        tbody.append(
                            '<tr>' +
                            '<td>' + row[0] + '</td>' + // nom
                            '<td>' + row[1] + '</td>' + // prenoms
                            '<td>' + row[2] + '</td>' + // telephone
                            '<td>' + row[3] + '</td>' + // email
                            '<td>' + row[4] + '</td>' + // projet
                            '<td>' + row[5] + '</td>' + // type_contrat
                            '<td>' + row[6] + '</td>' + // poste
                            '<td>' + row[10] + '</td>' + // actions
                            '</tr>'
                        );
                    });
                    
                    // Réinitialiser DataTables
                    $('.employee-table').DataTable({
                        responsive: true,
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                        },
                        pageLength: 10,
                        order: [[0, 'asc']],
                        scrollX: false,
                        columnDefs: [
                            {
                                targets: -1,
                                orderable: false,
                                searchable: false
                            }
                        ],
                        drawCallback: function() {
                            initializeActionButtons();
                        }
                    });
                    
                    // Mettre à jour le compteur d'employés
                    $('.employee-count').text(response.recordsTotal + ' employé(s)');
                },
                error: function(xhr, status, error) {
                    showMessage('Erreur lors du rechargement de la liste: ' + error, 'error');
                }
            });
        }

        function updateProjectFilter(employees) {
            // Extraire la liste unique des projets
            var projects = [...new Set(employees.map(emp => emp.projet).filter(Boolean))];
            
            // Récupérer le filtre actuel
            var currentProject = $('#filter-projet').val();
            
            // Mettre à jour les options du filtre
            var filterSelect = $('#filter-projet');
            filterSelect.find('option:not(:first)').remove();
            
            projects.sort().forEach(function(project) {
                filterSelect.append($('<option>', {
                    value: project,
                    text: project,
                    selected: project === currentProject
                }));
            });
        }

        function initializeActionButtons() {
            // Supprimer les gestionnaires d'événements existants
            $('.btn-edit, .btn-delete').off('click');

            // Gestionnaire pour le bouton de modification
            $('.btn-edit').on('click', function(e) {
                e.preventDefault();
                const employeeId = $(this).data('id');
                editEmployee(employeeId);
            });

            // Gestionnaire pour le bouton de suppression
            $('.btn-delete').on('click', function(e) {
                e.preventDefault();
                const employeeId = $(this).data('id');
                deleteEmployee(employeeId);
            });
        }

        let currentEmployeeId = null;

        function editEmployee(employeeId) {
            // Récupérer les données de l'employé via AJAX
            $.ajax({
                url: 'get_employee.php',
                method: 'GET',
                data: { id: employeeId },
                dataType: 'json',
                success: function(response) {
                    if (!response.success) {
                        showMessage(response.message, 'error');
                        return;
                    }

                    const employee = response.data;
                    
                    // Remplir le formulaire avec les données
                    $('#nom').val(employee.nom);
                    $('#prenoms').val(employee.prenoms);
                    $('#email').val(employee.email);
                    $('#telephone').val(employee.telephone);
                    $('#numero_urgence').val(employee.numero_urgence);
                    $('#numero_cnps').val(employee.numero_cnps);
                    $('#type_contrat').val(employee.type_contrat);
                    $('#date_naissance').val(employee.date_naissance);
                    $('#poste').val(employee.poste);
                    $('#projet').val(employee.projet);
                    $('#date_embauche').val(employee.date_embauche);

                    // Mettre à jour le formulaire pour la modification
                    const form = $('form');
                    form.find('input[name="action"]').val('modifier');
                    
                    // Ajouter ou mettre à jour l'ID de l'employé
                    let idInput = form.find('input[name="id"]');
                    if (idInput.length === 0) {
                        idInput = $('<input>').attr({
                            type: 'hidden',
                            name: 'id'
                        });
                        form.append(idInput);
                    }
                    idInput.val(employeeId);
                    currentEmployeeId = employeeId;

                    // Mettre à jour l'apparence du formulaire
                    $('.form-title').addClass('edit-mode')
                        .html('<i class="fas fa-edit"></i> Modifier l\'employé');
                    
                    // Mettre à jour les boutons
                    const submitBtn = form.find('button[type="submit"]')
                        .removeClass('btn-primary')
                        .addClass('btn-edit-mode')
                        .html('<i class="fas fa-save"></i> Enregistrer les modifications');

                    // Ajouter un bouton d'annulation
                    if (!form.find('.btn-cancel').length) {
                        const cancelBtn = $('<button>')
                            .attr('type', 'button')
                            .addClass('btn btn-secondary btn-cancel')
                            .html('<i class="fas fa-times"></i> Annuler')
                            .on('click', resetForm);
                        submitBtn.after(cancelBtn);
                    }

                    // Faire défiler jusqu'au formulaire
                    $('.employee-form-container')[0].scrollIntoView({ behavior: 'smooth' });
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Erreur lors de la récupération des données';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = response.message;
                        }
                    } catch(e) {
                        console.error('Erreur de parsing JSON:', error);
                    }
                    showMessage(errorMessage, 'error');
                }
            });
        }

        function deleteEmployee(employeeId) {
            currentEmployeeId = employeeId;
            $('#deleteModal').show();
        }

        // Gestionnaires d'événements pour la modal
        $('.close, #cancelDelete').on('click', function() {
            $('#deleteModal').hide();
            currentEmployeeId = null;
        });

        $('#confirmDelete').on('click', function() {
            if (currentEmployeeId) {
                $.ajax({
                    url: 'gerer_employes.php',
                    method: 'POST',
                    data: {
                        action: 'supprimer',
                        id: currentEmployeeId
                    },
                    dataType: 'json',
                    success: function(response) {
                        $('#deleteModal').hide();
                        if (response.success) {
                            showMessage(response.message, 'success');
                            reloadEmployeeList();
                        } else {
                            showMessage(response.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#deleteModal').hide();
                        showMessage('Erreur lors de la suppression: ' + error, 'error');
                    }
                });
            }
        });

        function resetForm() {
            const form = $('form');
            form[0].reset();
            form.find('input[name="action"]').val('ajouter');
            form.find('input[name="id"]').remove();
            currentEmployeeId = null;

            // Réinitialiser l'apparence du formulaire
            $('.form-title').removeClass('edit-mode')
                .html('<i class="fas fa-plus-circle"></i> Ajouter un employé');

            // Réinitialiser le bouton de soumission
            const submitBtn = form.find('button[type="submit"]')
                .removeClass('btn-edit-mode')
                .addClass('btn-primary')
                .html('<i class="fas fa-plus"></i> Ajouter');

            // Supprimer le bouton d'annulation
            form.find('.btn-cancel').remove();
        }

        // Fermer la modal si on clique en dehors
        $(window).on('click', function(event) {
            if ($(event.target).hasClass('modal')) {
                $('#deleteModal').hide();
                currentEmployeeId = null;
            }
        });

        function filterByProject(projet) {
            window.location.href = '?projet=' + encodeURIComponent(projet);
        }

        // Gestion de l'infobulle au survol
        var tooltip;
        $('.employee-table tbody').on('mouseenter', 'tr', function(e) {
            var $row = $(this);
            var dateNaissance = $row.data('date-naissance');
            var dateEmbauche = $row.data('date-embauche');
            var numeroCnps = $row.data('numero-cnps');
            var numeroUrgence = $row.data('numero-urgence');

            // Formatter les dates si elles existent
            var formattedDateNaissance = dateNaissance ? new Date(dateNaissance).toLocaleDateString('fr-FR') : 'N/A';
            var formattedDateEmbauche = dateEmbauche ? new Date(dateEmbauche).toLocaleDateString('fr-FR') : 'N/A';

            var tooltipContent = '<h5>Informations supplémentaires</h5>' +
                '<p><strong>Date de naissance:</strong> ' + formattedDateNaissance + '</p>' +
                '<p><strong>Date d\'embauche:</strong> ' + formattedDateEmbauche + '</p>' +
                '<p><strong>Numéro CNPS:</strong> ' + (numeroCnps || 'N/A') + '</p>' +
                '<p><strong>Numéro d\'urgence:</strong> ' + (numeroUrgence || 'N/A') + '</p>';

            tooltip = $('<div class="employee-tooltip"></div>').html(tooltipContent).appendTo('body');

            // Positionner l'infobulle
            var top = e.pageY + 15;
            var left = e.pageX + 15;

            // Ajuster si l'infobulle dépasse de l'écran
            if (left + tooltip.outerWidth() > $(window).width()) {
                left = e.pageX - tooltip.outerWidth() - 15;
            }
            if (top + tooltip.outerHeight() > $(window).height()) {
                top = e.pageY - tooltip.outerHeight() - 15;
            }

            tooltip.css({ top: top, left: left }).fadeIn(200);

        }).on('mouseleave', 'tr', function() {
            if (tooltip) {
                tooltip.remove();
            }
        }).on('mousemove', 'tr', function(e) {
            if (tooltip) {
                // Positionner l'infobulle
                var top = e.pageY + 15;
                var left = e.pageX + 15;

                // Ajuster si l'infobulle dépasse de l'écran
                if (left + tooltip.outerWidth() > $(window).width()) {
                    left = e.pageX - tooltip.outerWidth() - 15;
                }
                if (top + tooltip.outerHeight() > $(window).height()) {
                    top = e.pageY - tooltip.outerHeight() - 15;
                }

                tooltip.css({ top: top, left: left });
            }
        });
    });
    </script>
</body>
</html> 