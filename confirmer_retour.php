<?php
require_once 'check_auth.php';
require_once 'config.php';


$db = getDBConnection();

// Fonction pour obtenir les détails d'un retour de congé
function getReturnDetails($return_id) {
    $db = getDBConnection();
    $query = $db->prepare("
        SELECT cr.*, e.nom, e.prenoms, e.poste, dc.date_debut, dc.date_fin,
               dc.type_conge_id, tc.nom as type_conge
        FROM conge_returns cr
        JOIN employees e ON cr.employee_id = e.id
        JOIN demandes_conges dc ON cr.conge_id = dc.id
        JOIN types_conges tc ON dc.type_conge_id = tc.id
        WHERE cr.id = ?
    ");
    $query->execute([$return_id]);
    return $query->fetch(PDO::FETCH_ASSOC);
}

// Traitement de la confirmation de retour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['action']) && $_POST['action'] === 'confirm_return') {
        try {
            $return_id = $_POST['return_id'];
            $actual_return_date = $_POST['actual_return_date'];
            $confirmation_type = $_POST['confirmation_type'];
            $notes = $_POST['notes'];
            $supervisor_name = $_POST['supervisor_name'];
            
            // Mettre à jour le retour avec les détails de confirmation
            $query = $db->prepare("
                UPDATE conge_returns 
                SET actual_return_date = ?,
                    confirmation_type = ?,
                    notes = ?,
                    supervisor_name = ?,
                    confirmation_date = CURRENT_TIMESTAMP,
                    status = CASE 
                        WHEN actual_return_date > expected_return_date THEN 'late'
                        ELSE 'returned'
                    END
                WHERE id = ?
            ");
            
            if ($query->execute([$actual_return_date, $confirmation_type, $notes, $supervisor_name, $return_id])) {
                $response = [
                    'success' => true,
                    'message' => 'Retour confirmé avec succès'
                ];
            } else {
                throw new Exception("Erreur lors de la confirmation du retour");
            }
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
}

// Récupérer la liste des retours à confirmer
$query = $db->prepare("
    SELECT DISTINCT 
        cr.id,
        cr.conge_id,
        cr.employee_id,
        cr.expected_return_date,
        cr.status,
        e.nom,
        e.prenoms,
        dc.date_debut,
        dc.date_fin
    FROM conge_returns cr
    INNER JOIN employees e ON cr.employee_id = e.id
    INNER JOIN demandes_conges dc ON cr.conge_id = dc.id
    WHERE cr.status IN ('pending', 'missed')
        AND cr.actual_return_date IS NULL
    GROUP BY cr.id
    ORDER BY cr.expected_return_date ASC
");
$query->execute();
$pending_returns = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation des Retours de Congés</title>
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
            --transition: all 0.3s ease;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --border-radius: 10px;
        }

        body {
            background: linear-gradient(
                135deg,
                rgba(176, 224, 255, 0.8) 0%,
                rgba(200, 230, 255, 0.8) 25%,
                rgba(220, 237, 255, 0.8) 50%,
                rgba(230, 242, 255, 0.8) 75%,
                rgba(240, 248, 255, 0.8) 100%
            );
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .page-header {
            background: linear-gradient(
                to right,
                rgba(255, 255, 255, 0.95),
                rgba(255, 255, 255, 0.8)
            );
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(31, 38, 135, 0.1);
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.3);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(31, 38, 135, 0.15);
        }

        .table-responsive {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
            overflow: hidden;
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .employee-table thead th {
            background: linear-gradient(
                45deg,
                var(--primary-color),
                #2196F3
            );
            color: white;
            border: none;
        }

        .employee-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.8);
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .modal-header {
            background: linear-gradient(
                45deg,
                var(--primary-color),
                #2196F3
            );
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.9);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(31, 38, 135, 0.2);
        }

        .confirmation-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .confirmation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .employee-info {
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .dates-info {
            color: #666;
            margin: 1rem 0;
        }
        
        .confirmation-form {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .confirmation-type {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .confirmation-type label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .confirmation-type input[type="radio"] {
            margin: 0;
        }
        
        .confirmation-type label:hover {
            background: #f0f0f0;
        }
        
        .confirmation-type input[type="radio"]:checked + label {
            background: var(--primary-light);
            border-color: var(--primary-color);
            color: var(--primary-dark);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .status-pending {
            background: #ffc107;
            color: #000;
        }
        
        .status-missed {
            background: #dc3545;
            color: #fff;
        }
        
        .status-late {
            background: #fd7e14;
            color: #fff;
        }
        
        .status-returned {
            background: #28a745;
            color: #fff;
        }

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
            min-width: 120px;
            justify-content: center;
            font-size: 0.9rem;
        }

        .nav-btn i {
            font-size: 1.1rem;
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

        .employee-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
        }

        .employee-table tbody tr {
            border-bottom: 1px solid var(--divider-color);
        }

        .employee-table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .btn-confirm-return {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: var(--primary-color);
            color: var(--white);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-confirm-return:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 5vh auto;
            padding: 0;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--divider-color);
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
        }

        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .close:hover {
            color: var(--text-primary);
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-info {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .stats-title {
            color: var(--primary-color);
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        @media (max-width: 768px) {
            .employee-table {
                display: block;
                overflow-x: auto;
            }
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .stat-icon.missed {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .stat-details {
            flex: 1;
        }

        .stat-details h3 {
            margin: 0;
            font-size: 1rem;
            color: var(--text-secondary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 0.5rem;
        }

        .employee-table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .employee-table th i {
            margin-right: 0.5rem;
            opacity: 0.8;
        }

        .date-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .date-cell i {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .status-badge i {
            font-size: 0.9rem;
        }

        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .status-missed {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .status-pending-row {
            background-color: rgba(255, 193, 7, 0.02);
        }

        .status-missed-row {
            background-color: rgba(220, 53, 69, 0.02);
        }

        .btn-confirm-return {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-confirm-return:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .modal-landscape {
            max-width: 900px;
            width: 90%;
            margin: 2vh auto;
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            border-radius: 10px 10px 0 0;
        }

        .modal-header h3 {
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }

        .close-button {
            color: white;
        }

        .confirmation-form {
            padding: 1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }

        .form-column {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .radio-buttons {
            display: flex;
            gap: 1.5rem;
        }

        .radio-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.75rem 1.25rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .radio-button:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-color);
        }

        .radio-button input[type="radio"] {
            display: none;
        }

        .radio-button input[type="radio"]:checked + .radio-label {
            color: var(--primary-color);
        }

        .radio-button input[type="radio"]:checked + .radio-label i {
            color: var(--primary-color);
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            padding: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .radio-buttons {
                flex-direction: column;
                gap: 0.75rem;
            }

            .modal-landscape {
                margin: 0;
                width: 100%;
                height: 100%;
                max-width: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="page-header">
                <div class="header-title">
                    <h2><i class="fas fa-clipboard-check"></i> Confirmation des Retours de Congés</h2>
                </div>
                <div class="header-actions">
                    <a href="gestion_conges.php" class="nav-btn">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                    <a href="calendrier_conges.php" class="nav-btn">
                        <i class="fas fa-calendar-alt"></i> Calendrier
                    </a>
                    <a href="manage_conge_returns.php" class="nav-btn">
                        <i class="fas fa-history"></i> Historique des retours
                    </a>
                    <a href="rapports.php" class="nav-btn">
                        <i class="fas fa-chart-bar"></i> Rapports
                    </a>
                    <a href="logout.php" class="nav-btn nav-btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>

            <div class="content-section">
                <div class="stats-title">
                    <i class="fas fa-clock"></i> Gestion des retours de congé
                </div>

                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Retours en attente</h3>
                            <div class="stat-number">
                                <?php 
                                    $pending_count = count(array_filter($pending_returns, function($return) {
                                        return $return['status'] === 'pending';
                                    }));
                                    echo $pending_count;
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon missed">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Retours manqués</h3>
                            <div class="stat-number">
                                <?php 
                                    $missed_count = count(array_filter($pending_returns, function($return) {
                                        return $return['status'] === 'missed';
                                    }));
                                    echo $missed_count;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($pending_returns)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun retour en attente de confirmation.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="employee-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user"></i> Nom</th>
                                    <th><i class="fas fa-user"></i> Prénoms</th>
                                    <th><i class="fas fa-plane-departure"></i> Date départ</th>
                                    <th><i class="fas fa-plane-arrival"></i> Date retour prévue</th>
                                    <th><i class="fas fa-info-circle"></i> Statut</th>
                                    <th><i class="fas fa-tasks"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_returns as $return): ?>
                                    <tr class="status-<?php echo $return['status']; ?>-row">
                                        <td><?php echo htmlspecialchars($return['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($return['prenoms']); ?></td>
                                        <td>
                                            <div class="date-cell">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('d/m/Y', strtotime($return['date_debut'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-cell">
                                                <i class="fas fa-calendar-check"></i>
                                                <?php echo date('d/m/Y', strtotime($return['expected_return_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $return['status']; ?>">
                                                <i class="fas <?php echo $return['status'] === 'pending' ? 'fa-clock' : 'fa-exclamation-circle'; ?>"></i>
                                                <?php 
                                                    switch($return['status']) {
                                                        case 'pending':
                                                            echo 'En attente';
                                                            break;
                                                        case 'missed':
                                                            echo 'Retour manqué';
                                                            break;
                                                        default:
                                                            echo ucfirst($return['status']);
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-confirm-return" 
                                                    data-return-id="<?php echo $return['id']; ?>"
                                                    onclick="openConfirmationModal(<?php echo $return['id']; ?>)">
                                                <i class="fas fa-check-circle"></i> Confirmer
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Modal de confirmation -->
            <div id="confirmationModal" class="modal">
                <div class="modal-content modal-landscape">
                    <div class="modal-header">
                        <h3><i class="fas fa-clipboard-check"></i> Confirmation du retour de congé</h3>
                        <button type="button" class="close-button" onclick="closeConfirmationModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="modal-body">
                        <form class="confirmation-form" id="confirmationForm">
                            <input type="hidden" name="action" value="confirm_return">
                            <input type="hidden" name="return_id" id="returnId">

                            <div class="form-grid">
                                <!-- Colonne gauche -->
                                <div class="form-column">
                                    <div class="form-group">
                                        <label for="actual_return_date">
                                            <i class="fas fa-calendar"></i> Date effective de retour
                                        </label>
                                        <input type="date" 
                                               id="actual_return_date"
                                               name="actual_return_date"
                                               class="form-control"
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label>
                                            <i class="fas fa-check-circle"></i> Type de confirmation
                                        </label>
                                        <div class="radio-buttons">
                                            <label class="radio-button">
                                                <input type="radio" name="confirmation_type" value="physical" required>
                                                <span class="radio-label">
                                                    <i class="fas fa-user"></i> Présence physique
                                                </span>
                                            </label>
                                            <label class="radio-button">
                                                <input type="radio" name="confirmation_type" value="remote">
                                                <span class="radio-label">
                                                    <i class="fas fa-laptop"></i> Retour à distance
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Colonne droite -->
                                <div class="form-column">
                                    <div class="form-group">
                                        <label for="supervisor_name">
                                            <i class="fas fa-user-tie"></i> Superviseur
                                        </label>
                                        <input type="text"
                                               id="supervisor_name"
                                               name="supervisor_name"
                                               class="form-control"
                                               placeholder="Nom du superviseur"
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="notes">
                                            <i class="fas fa-comment-alt"></i> Notes / Observations
                                        </label>
                                        <textarea id="notes"
                                                  name="notes"
                                                  class="form-control"
                                                  rows="3"
                                                  placeholder="Commentaires (optionnel)"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Confirmer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fonction pour ouvrir le modal
            window.openConfirmationModal = function(returnId) {
                console.log('Opening modal for return ID:', returnId); // Debug
                const modal = document.getElementById('confirmationModal');
                if (modal) {
                    document.getElementById('returnId').value = returnId;
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                    
                    // Définir la date du jour par défaut
                    const today = new Date().toISOString().split('T')[0];
                    document.getElementById('actual_return_date').value = today;
                } else {
                    console.error('Modal element not found');
                }
            };

            // Fonction pour fermer le modal
            window.closeConfirmationModal = function() {
                const modal = document.getElementById('confirmationModal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            };

            // Fermer le modal en cliquant à l'extérieur
            window.onclick = function(event) {
                const modal = document.getElementById('confirmationModal');
                if (event.target === modal) {
                    closeConfirmationModal();
                }
            };

            // Fermer le modal avec la touche Echap
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeConfirmationModal();
                }
            });

            // Gestionnaire de soumission du formulaire
            const form = document.getElementById('confirmationForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('confirmer_retour.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('Erreur : ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Une erreur est survenue lors de la confirmation');
                        console.error('Erreur:', error);
                    });
                });
            }
        });
    </script>
</body>
</html> 