<?php
require_once 'check_auth.php';
require_once 'config.php';

// Fonction pour créer automatiquement une entrée de retour de congé
function createCongeReturn($conge_id, $employee_id, $expected_return_date) {
    $db = getDBConnection();
    $query = $db->prepare("INSERT INTO conge_returns (conge_id, employee_id, expected_return_date) VALUES (?, ?, ?)");
    return $query->execute([$conge_id, $employee_id, $expected_return_date]);
}

// Fonction pour mettre à jour le statut de retour
function updateCongeReturn($conge_id, $return_date, $confirmation_type, $supervisor_name, $notes = '') {
    $db = getDBConnection();
    
    try {
        $db->beginTransaction();
        
        // Mettre à jour le statut du retour
        $query = $db->prepare("
            UPDATE conge_returns 
            SET actual_return_date = ?,
                confirmation_type = ?,
                supervisor_name = ?,
                notes = ?,
                status = CASE 
                    WHEN ? > expected_return_date THEN 'late'
                    ELSE 'returned'
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE conge_id = ? AND status IN ('pending', 'missed')
        ");
        
        $result = $query->execute([
            $return_date,
            $confirmation_type,
            $supervisor_name,
            $notes,
            $return_date,
            $conge_id
        ]);
        
        if ($result) {
            $db->commit();
            return true;
        }
        
        $db->rollBack();
        return false;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Erreur lors de la mise à jour du retour : " . $e->getMessage());
        return false;
    }
}

// Fonction pour marquer les retours manqués
function checkMissedReturns() {
    $db = getDBConnection();
    $today = date('Y-m-d');
    
    // Mettre à jour tous les retours manqués
    $query = $db->prepare("
        UPDATE conge_returns 
        SET status = 'missed' 
        WHERE expected_return_date < ? 
        AND actual_return_date IS NULL 
        AND status = 'pending'
    ");
    return $query->execute([$today]);
}

// Fonction pour obtenir la liste des retours en attente ou manqués
function getPendingAndMissedReturns() {
    $db = getDBConnection();
    
    $query = $db->prepare("
        SELECT DISTINCT cr.*, e.nom, e.prenoms, c.date_debut, c.date_fin
        FROM conge_returns cr
        JOIN employees e ON cr.employee_id = e.id
        JOIN demandes_conges c ON cr.conge_id = c.id
        WHERE cr.status IN ('pending', 'missed')
        ORDER BY cr.expected_return_date ASC
    ");
    $query->execute();
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_return':
                if (isset($_POST['conge_id']) && isset($_POST['return_date'])) {
                    $confirmation_type = $_POST['confirmation_type'] ?? 'physical';
                    $supervisor_name = $_POST['supervisor_name'] ?? '';
                    $notes = $_POST['notes'] ?? '';
                    
                    if (updateCongeReturn(
                        $_POST['conge_id'],
                        $_POST['return_date'],
                        $confirmation_type,
                        $supervisor_name,
                        $notes
                    )) {
                        $response = ['success' => true, 'message' => 'Retour enregistré avec succès'];
                    } else {
                        $response = ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du retour'];
                    }
                }
                break;
        }
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Vérifier les retours manqués
checkMissedReturns();

// Récupérer la liste des retours en attente et manqués
$pending_returns = getPendingAndMissedReturns();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Retours de Congés</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .alert-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #ffc107;
        }
        
        .alert-card.missed {
            border-left-color: #dc3545;
        }
        
        .alert-card.pending {
            border-left-color: #ffc107;
        }
        
        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .alert-title {
            font-weight: bold;
            color: #333;
        }
        
        .alert-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .status-missed {
            background: #dc3545;
            color: white;
        }
        
        .status-pending {
            background: #ffc107;
            color: black;
        }
        
        .alert-content {
            margin: 0.5rem 0;
        }
        
        .alert-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-mark-return {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-mark-return:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="page-header">
                <h2><i class="fas fa-calendar-check"></i> Gestion des Retours de Congés</h2>
            </div>

            <div class="content-section">
                <h3>Retours en attente et manqués</h3>
                
                <?php if (empty($pending_returns)): ?>
                    <p>Aucun retour en attente ou manqué.</p>
                <?php else: ?>
                    <?php foreach ($pending_returns as $return): ?>
                        <div class="alert-card <?php echo $return['status']; ?>">
                            <div class="alert-header">
                                <span class="alert-title">
                                    <?php echo htmlspecialchars($return['nom'] . ' ' . $return['prenoms']); ?>
                                </span>
                                <span class="alert-status status-<?php echo $return['status']; ?>">
                                    <?php echo $return['status'] === 'missed' ? 'Manqué' : 'En attente'; ?>
                                </span>
                            </div>
                            <div class="alert-content">
                                <p>Période de congé : <?php echo date('d/m/Y', strtotime($return['date_debut'])); ?> au <?php echo date('d/m/Y', strtotime($return['date_fin'])); ?></p>
                                <p>Date de retour prévue : <?php echo date('d/m/Y', strtotime($return['expected_return_date'])); ?></p>
                            </div>
                            <div class="alert-actions">
                                <button type="button" class="btn-mark-return" onclick="markReturn(<?php echo $return['conge_id']; ?>)">
                                    <i class="fas fa-check"></i> Marquer le retour
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal pour marquer le retour -->
    <div id="markReturnModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Marquer le retour</h3>
            <form id="markReturnForm">
                <input type="hidden" name="action" value="mark_return">
                <input type="hidden" name="conge_id" id="modal_conge_id">
                
                <div class="form-group">
                    <label for="return_date">Date de retour effective</label>
                    <input type="date" name="return_date" id="return_date" required>
                </div>
                
                <div class="form-group">
                    <label for="confirmation_type">Type de confirmation</label>
                    <select name="confirmation_type" id="confirmation_type">
                        <option value="physical">Physique</option>
                        <option value="email">Email</option>
                        <option value="phone">Téléphone</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="supervisor_name">Nom du superviseur</label>
                    <input type="text" name="supervisor_name" id="supervisor_name">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (optionnel)</label>
                    <textarea name="notes" id="notes" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function markReturn(congeId) {
            document.getElementById('modal_conge_id').value = congeId;
            document.getElementById('markReturnModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('markReturnModal').style.display = 'none';
        }
        
        document.getElementById('markReturnForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('manage_conge_returns.php', {
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
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            });
        });
    </script>
</body>
</html> 