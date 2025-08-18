<?php
require_once 'check_auth.php';
require_once 'config.php';

$db = getDBConnection();

// Fonction pour vérifier le chevauchement
function verifierChevauchement($employee_id, $date_debut, $date_fin) {
    global $db;
    $stmt = $db->prepare("CALL check_conges_overlap(?, ?, ?, @overlap)");
    $stmt->execute([$employee_id, $date_debut, $date_fin]);
    $result = $db->query("SELECT @overlap as overlap")->fetch(PDO::FETCH_ASSOC);
    return $result['overlap'];
}

// Fonction pour calculer les jours ouvrés
function calculerJoursOuvres($date_debut, $date_fin) {
    global $db;
    $stmt = $db->prepare("SELECT calculate_working_days(?, ?) as jours");
    $stmt->execute([$date_debut, $date_fin]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['jours'];
}

// Fonction pour mettre à jour le solde des congés
function mettreAJourSoldeConges($employee_id, $nb_jours, $type_conge_id, $operation = 'add') {
    global $db;
    
    // Ne mettre à jour le solde que pour les congés payés (id = 1)
    if ($type_conge_id != 1) {
        return null;
    }
    
    $annee = date('Y');
    
    // Vérifier si l'entrée existe pour l'année en cours
    $stmt = $db->prepare("SELECT * FROM conges_annuels WHERE employee_id = ? AND annee = ?");
    $stmt->execute([$employee_id, $annee]);
    $conges = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conges) {
        // Créer une nouvelle entrée
        $stmt = $db->prepare("INSERT INTO conges_annuels (employee_id, annee, jours_total, jours_pris, jours_restants) VALUES (?, ?, 22.5, 0, 22.5)");
        $stmt->execute([$employee_id, $annee]);
    }

    // Calculer les nouveaux soldes
    if ($operation === 'add') {
        $stmt = $db->prepare("
            UPDATE conges_annuels 
            SET jours_pris = jours_pris + ?,
                jours_restants = jours_total - (jours_pris + ?)
            WHERE employee_id = ? AND annee = ?
        ");
    } else {
        $stmt = $db->prepare("
            UPDATE conges_annuels 
            SET jours_pris = jours_pris - ?,
                jours_restants = jours_total - (jours_pris - ?)
            WHERE employee_id = ? AND annee = ?
        ");
    }
    $stmt->execute([$nb_jours, $nb_jours, $employee_id, $annee]);

    // Récupérer et retourner le nouveau solde
    $stmt = $db->prepare("SELECT jours_restants FROM conges_annuels WHERE employee_id = ? AND annee = ?");
    $stmt->execute([$employee_id, $annee]);
    $nouveau_solde = $stmt->fetch(PDO::FETCH_ASSOC);
    return $nouveau_solde['jours_restants'];
}

// Récupération des types de congés
try {
    $query = $db->query("SELECT * FROM types_conges ORDER BY nom");
    $types_conges = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Erreur lors de la récupération des types de congés : " . $e->getMessage();
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                try {
                    $employee_id = $_POST['employee_id'];
                    $type_conge_id = $_POST['type_conge_id'];
                    $date_debut = $_POST['date_debut'];
                    $date_fin = $_POST['date_fin'];
                    
                    // Vérifier que la date de fin est après la date de début
                    if ($date_fin < $date_debut) {
                        throw new Exception("La date de fin doit être après la date de début");
                    }
                    
                    // Vérifier le chevauchement
                    if (verifierChevauchement($employee_id, $date_debut, $date_fin)) {
                        throw new Exception("Cette période chevauche une autre demande de congés");
                    }
                    
                    // Calculer le nombre de jours ouvrés
                    $nb_jours = calculerJoursOuvres($date_debut, $date_fin);
                    
                    // Vérifier le solde disponible uniquement pour les congés payés
                    if ($type_conge_id == 1) {
                        $stmt = $db->prepare("SELECT jours_restants FROM conges_annuels WHERE employee_id = ? AND annee = ?");
                        $stmt->execute([$employee_id, date('Y')]);
                        $solde = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$solde) {
                            // Initialiser le solde pour un nouvel employé
                            $stmt = $db->prepare("INSERT INTO conges_annuels (employee_id, annee, jours_total, jours_pris, jours_restants) VALUES (?, ?, 22.5, 0, 22.5)");
                            $stmt->execute([$employee_id, date('Y')]);
                            $solde = ['jours_restants' => 22.5];
                        }
                        
                        if ($solde['jours_restants'] < $nb_jours) {
                            throw new Exception("Solde de congés insuffisant (disponible : " . number_format($solde['jours_restants'], 1) . " jours, demandé : " . number_format($nb_jours, 1) . " jours)");
                        }
                    }
                    
                    // Ajouter la demande
                    $query = $db->prepare("INSERT INTO demandes_conges (employee_id, type_conge_id, date_debut, date_fin, nb_jours, commentaire) VALUES (?, ?, ?, ?, ?, ?)");
                    $query->execute([$employee_id, $type_conge_id, $date_debut, $date_fin, $nb_jours, $_POST['commentaire'] ?? null]);
                    
                    // Mettre à jour le solde
                    $nouveau_solde = mettreAJourSoldeConges($employee_id, $nb_jours, $type_conge_id, 'add');
                    
                    $success_message = "Demande de congés ajoutée avec succès";
                    if ($nouveau_solde !== null) {
                        $success_message .= ". Nouveau solde de congés : " . $nouveau_solde . " jours";
                    }
                } catch(Exception $e) {
                    $error_message = $e->getMessage();
                }
                break;

            case 'statut':
                try {
                    $demande_id = $_POST['demande_id'];
                    $nouveau_statut = $_POST['nouveau_statut'];
                    $ancien_statut = $_POST['ancien_statut'];
                    
                    // Récupérer les informations de la demande
                    $stmt = $db->prepare("
                        SELECT dc.*, tc.id as type_conge_id 
                        FROM demandes_conges dc 
                        JOIN types_conges tc ON dc.type_conge_id = tc.id 
                        WHERE dc.id = ?
                    ");
                    $stmt->execute([$demande_id]);
                    $demande = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$demande) {
                        throw new Exception("Demande introuvable");
                    }

                    // Vérifier si le statut peut être modifié
                    if ($ancien_statut === 'annule') {
                        throw new Exception("Une demande annulée ne peut pas être modifiée");
                    }

                    // Mettre à jour le statut
                    $query = $db->prepare("UPDATE demandes_conges SET statut = ? WHERE id = ?");
                    
                    if ($query->execute([$nouveau_statut, $demande_id])) {
                        // Si le congé est approuvé, créer une entrée de retour
                        if ($nouveau_statut === 'approuve') {
                            // Calculer la date de retour prévue (jour ouvrable suivant la fin du congé)
                            $date_fin = new DateTime($demande['date_fin']);
                            $expected_return = clone $date_fin;
                            $expected_return->modify('+1 weekday');
                            
                            // Créer l'entrée de retour
                            createCongeReturn($demande_id, $demande['employee_id'], $expected_return->format('Y-m-d'));
                        }
                        
                        // Ajuster le solde des congés si nécessaire
                        $nouveau_solde = null;
                        
                        // Si c'est un congé payé (type_conge_id = 1)
                        if ($demande['type_conge_id'] == 1) {
                            // Cas où on approuve une demande
                            if ($nouveau_statut === 'approuve' && $ancien_statut !== 'approuve') {
                                $nouveau_solde = mettreAJourSoldeConges($demande['employee_id'], $demande['nb_jours'], $demande['type_conge_id'], 'add');
                            }
                            // Cas où on annule une approbation (refus, standby, annulation)
                            elseif ($ancien_statut === 'approuve' && $nouveau_statut !== 'approuve') {
                                $nouveau_solde = mettreAJourSoldeConges($demande['employee_id'], $demande['nb_jours'], $demande['type_conge_id'], 'subtract');
                            }
                        }
                        
                        // Préparer le message de succès
                        $status_messages = [
                            'approuve' => 'approuvée',
                            'refuse' => 'refusée',
                            'standby' => 'mise en attente',
                            'annule' => 'annulée'
                        ];
                        
                        $success_message = "La demande a été " . $status_messages[$nouveau_statut];
                        if ($nouveau_solde !== null) {
                            $success_message .= ". Nouveau solde de congés : " . number_format($nouveau_solde, 1) . " jours";
                        }
                    }
                } catch(Exception $e) {
                    $error_message = $e->getMessage();
                }
                break;
        }
    }
}

// Récupération des employés
try {
    $query = $db->query("SELECT * FROM employees ORDER BY nom, prenoms");
    $employes = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Erreur lors de la récupération des employés : " . $e->getMessage();
}

// Récupération des demandes de congés
try {
    $query = $db->prepare("
        SELECT 
            dc.*,
            e.nom,
            e.prenoms,
            tc.nom as type_conge_nom,
            ca.jours_restants as solde_actuel
        FROM demandes_conges dc
        JOIN employees e ON dc.employee_id = e.id
        JOIN types_conges tc ON dc.type_conge_id = tc.id
        LEFT JOIN conges_annuels ca ON e.id = ca.employee_id AND YEAR(dc.date_debut) = ca.annee
        ORDER BY dc.created_at DESC
    ");
    $query->execute();
    $demandes = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Erreur lors de la récupération des demandes : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Congés</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
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
            --transition: all 0.3s ease;
        }

        body {
            background-color: var(--background-light);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
        }

        .dashboard {
            padding: 2rem;
            width: 90%;
            margin: 0 auto;
            max-width: none;
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
            font-size: 1.8rem;
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
            color: var(--white);
        }

        .conges-form-container {
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

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
            transform: scale(1.02);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
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
            border: none;
            cursor: pointer;
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

        .table-responsive {
            margin-top: 2rem;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
        }

        .employee-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            margin-bottom: 1rem;
        }

        .employee-table thead th {
            background: var(--primary-color);
            color: var(--white);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        .employee-table tbody tr {
            border-bottom: 1px solid var(--divider-color);
            transition: background-color 0.3s ease;
        }

        .employee-table tbody tr:hover {
            background-color: var(--primary-light);
        }

        .employee-table td {
            padding: 1rem;
            color: var(--text-primary);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
        }

        .btn-edit, .btn-delete, .btn-approve, .btn-reject {
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
            color: var(--white);
        }

        .btn-edit {
            background-color: var(--primary-color);
        }

        .btn-delete {
            background-color: var(--danger);
        }

        .btn-approve {
            background-color: var(--success);
        }

        .btn-reject {
            background-color: var(--warning);
        }

        .btn-edit:hover, .btn-delete:hover, .btn-approve:hover, .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
        }

        .status-en-attente {
            background-color: var(--warning);
            color: var(--text-primary);
        }

        .status-approuve {
            background-color: var(--success);
            color: var(--white);
        }

        .status-refuse {
            background-color: var(--danger);
            color: var(--white);
        }

        .solde-info {
            padding: 0.5rem 1rem;
            background-color: var(--primary-light);
            border-radius: 5px;
            color: var(--primary-dark);
            font-weight: 500;
            text-align: center;
            margin-top: 0.2rem;
        }

        .solde-warning {
            background-color: var(--warning);
            color: var(--text-primary);
        }

        .solde-danger {
            background-color: var(--danger);
            color: var(--white);
        }

        /* DataTables customization */
        .dataTables_wrapper .dataTables_filter input {
            padding: 0.5rem 1rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            margin-left: 0.5rem;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 0.5rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            margin: 0 0.5rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 1rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            margin: 0 2px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary-color);
            color: var(--white) !important;
            border-color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard {
                width: 95%;
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 0.8rem;
        }

        .form-actions {
            margin-top: 1rem;
        }

        .card-header {
            padding: 1rem 1.5rem;
        }

        /* Ajout des styles pour les boutons d'action et les badges de statut */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: var(--white);
        }

        .status-standby {
            background-color: var(--warning);
            color: var(--text-primary);
        }

        .status-annule {
            background-color: #6c757d;
            color: var(--white);
        }

        /* Style des tooltips pour les boutons */
        [title] {
            position: relative;
        }

        [title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.25rem 0.5rem;
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 1000;
        }

        .nav-btn {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 120px;
            justify-content: center;
            font-size: 0.9rem;
        }

        .nav-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .nav-btn-danger {
            background: var(--danger);
        }

        .nav-btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container" style="width: 100%;">
        <div class="dashboard" style="width: 90%; max-width: 1800px; margin: 0 auto;">
            <div class="page-header">
                <h2><i class="fas fa-calendar-alt"></i> Gestion des Congés</h2>
                <div class="header-actions">
                    <a href="gestion_conges.php" class="nav-btn">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                    <a href="calendrier_conges.php" class="nav-btn">
                        <i class="fas fa-calendar-alt"></i> Calendrier
                    </a>
                    <a href="confirmer_retour.php" class="nav-btn">
                        <i class="fas fa-check-circle"></i> Retours
                    </a>
                    <a href="gerer_employes.php" class="nav-btn">
                        <i class="fas fa-users"></i> Employés
                    </a>
                    <a href="rapports.php" class="nav-btn">
                        <i class="fas fa-chart-bar"></i> Rapports
                    </a>
                    <a href="logout.php" class="nav-btn nav-btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="grid-container" style="width: 100%;">
                <!-- Conteneur pour le formulaire de nouvelle demande -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Nouvelle demande de congés</h3>
                    </div>
                    <div class="card-body">
                        <div class="conges-form-container">
                            <h3 class="form-title">
                                <i class="fas fa-plus-circle"></i>
                                Nouvelle demande de congés
                            </h3>
                            <form method="POST" action="gerer_conges.php">
                                <input type="hidden" name="action" value="ajouter">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="employee_autocomplete">Employé</label>
                                        <input list="employe-list" id="employee_autocomplete" class="form-control" placeholder="Commencez à taper le nom..." autocomplete="off" required>
                                        <input type="hidden" name="employee_id" id="employee_id" required>
                                        <datalist id="employe-list">
                                            <?php foreach ($employes as $employe): ?>
                                                <option data-id="<?php echo $employe['id']; ?>" value="<?php echo htmlspecialchars($employe['nom'] . ' ' . $employe['prenoms']); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>

                                    <div class="form-group">
                                        <label for="type_conge_id">Type de congé</label>
                                        <select name="type_conge_id" id="type_conge_id" class="form-control" required>
                                            <option value="">Sélectionnez un type</option>
                                            <?php foreach ($types_conges as $type): ?>
                                                <option value="<?php echo $type['id']; ?>">
                                                    <?php echo htmlspecialchars($type['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="date_debut">Date de début</label>
                                        <input type="date" name="date_debut" id="date_debut" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="date_fin">Date de fin</label>
                                        <input type="date" name="date_fin" id="date_fin" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="commentaire">Commentaire</label>
                                        <textarea name="commentaire" id="commentaire" class="form-control" rows="3"></textarea>
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
                    </div>
                </div>

                <!-- Conteneur pour la liste des demandes -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Liste des demandes de congés</h3>
                    </div>
                    <div class="card-body" style="width: 100%;">
                        <table id="congesTable" class="display responsive nowrap" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Employé</th>
                                    <th>Type</th>
                                    <th>Début</th>
                                    <th>Fin</th>
                                    <th>Jours</th>
                                    <th>Statut</th>
                                    <th>Solde</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demandes as $demande): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($demande['nom'] . ' ' . $demande['prenoms']); ?></td>
                                        <td><?php echo htmlspecialchars($demande['type_conge_nom']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($demande['date_debut'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($demande['date_fin'])); ?></td>
                                        <td><?php echo number_format($demande['nb_jours'], 1); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $demande['statut']; ?>">
                                                <?php
                                                switch($demande['statut']) {
                                                    case 'approuve':
                                                        echo 'Approuvé';
                                                        break;
                                                    case 'refuse':
                                                        echo 'Refusé';
                                                        break;
                                                    case 'standby':
                                                        echo 'En attente';
                                                        break;
                                                    case 'annule':
                                                        echo 'Annulé';
                                                        break;
                                                    default:
                                                        echo ucfirst($demande['statut']);
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($demande['solde_actuel'], 1); ?></td>
                                        <td>
                                            <?php if ($demande['statut'] !== 'annule'): ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="action" value="statut">
                                                    <input type="hidden" name="demande_id" value="<?php echo $demande['id']; ?>">
                                                    <input type="hidden" name="ancien_statut" value="<?php echo $demande['statut']; ?>">
                                                    
                                                    <div class="action-buttons">
                                                        <a href="details_demande.php?id=<?php echo $demande['id']; ?>" class="btn btn-info btn-sm" title="Voir les détails">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($demande['statut'] !== 'approuve'): ?>
                                                            <button type="submit" name="nouveau_statut" value="approuve" class="btn btn-success btn-sm" title="Approuver">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($demande['statut'] !== 'refuse'): ?>
                                                            <button type="submit" name="nouveau_statut" value="refuse" class="btn btn-danger btn-sm" title="Refuser">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#congesTable').DataTable({
                responsive: true,
                order: [[2, 'desc']], // Tri par date de début décroissante
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                },
                columnDefs: [
                    {
                        targets: -1,
                        orderable: false,
                        width: '150px'
                    }
                ],
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Tous"]],
                dom: '<"top"lf>rt<"bottom"ip><"clear">'
            });

            // Initialiser le solde au chargement si un employé est déjà sélectionné
            var selectedEmployee = $('#employee_id').val();
            if (selectedEmployee) {
                updateSolde(selectedEmployee);
            }
        });

        function updateSolde(employeeId) {
            if (!employeeId) {
                $('#solde_info').text('Sélectionnez un employé pour voir son solde');
                $('#solde_info').removeClass('solde-warning solde-danger');
                return;
            }

            // Récupérer le solde depuis les données PHP
            var soldes = <?php 
                $soldes = [];
                foreach ($demandes as $demande) {
                    if (!isset($soldes[$demande['employee_id']])) {
                        $soldes[$demande['employee_id']] = $demande['solde_actuel'];
                    }
                }
                echo json_encode($soldes);
            ?>;

            var solde = soldes[employeeId];
            var soldeInfo = $('#solde_info');
            
            if (solde === undefined || solde === null) {
                // Créer une nouvelle entrée de solde si elle n'existe pas
                solde = 22.5; // Solde initial pour un nouvel employé
            }

            soldeInfo.text('Solde disponible : ' + solde + ' jours');
            
            // Ajouter des classes visuelles selon le solde
            soldeInfo.removeClass('solde-warning solde-danger');
            if (solde < 5) {
                soldeInfo.addClass('solde-danger');
            } else if (solde < 10) {
                soldeInfo.addClass('solde-warning');
            }
        }

        // Ajout de l'autocomplétion Employé
        const employeList = document.getElementById('employe-list');
        const inputAuto = document.getElementById('employee_autocomplete');
        const inputHidden = document.getElementById('employee_id');

        inputAuto.addEventListener('input', function() {
            // Trouver l'option correspondante
            const val = this.value;
            let found = false;
            for (const option of employeList.options) {
                if (option.value === val) {
                    inputHidden.value = option.getAttribute('data-id');
                    found = true;
                    break;
                }
            }
            if (!found) {
                inputHidden.value = '';
            }
        });
        // Empêcher la soumission si l'id n'est pas trouvé
        inputAuto.form.addEventListener('submit', function(e) {
            if (!inputHidden.value) {
                e.preventDefault();
                inputAuto.setCustomValidity('Veuillez sélectionner un employé dans la liste.');
                inputAuto.reportValidity();
            } else {
                inputAuto.setCustomValidity('');
            }
        });
    </script>
</body>
</html> 