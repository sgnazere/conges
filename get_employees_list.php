<?php
require_once 'check_auth.php';
require_once 'config.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();

    // Récupérer la liste des employés avec leur service
    $query = $db->query("
        SELECT e.*, s.nom as nom_service 
        FROM employees e 
        LEFT JOIN services s ON e.service_id = s.id 
        WHERE (e.status = 'actif' OR e.status IS NULL)
        ORDER BY e.nom, e.prenoms
    ");
    $employes = $query->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($employes as $employe) {
        // Formater les dates seulement si elles ne sont pas nulles
        $date_naissance = !empty($employe['date_naissance']) ? 
            date('d/m/Y', strtotime($employe['date_naissance'])) : '-';
        $date_embauche = !empty($employe['date_embauche']) ? 
            date('d/m/Y', strtotime($employe['date_embauche'])) : '-';

        // Préparer les données pour DataTables
        $data[] = [
            htmlspecialchars($employe['nom']),
            htmlspecialchars($employe['prenoms']),
            htmlspecialchars($employe['telephone'] ?: '-'),
            htmlspecialchars($employe['email'] ?: '-'),
            htmlspecialchars($employe['projet'] ?: '-'),
            htmlspecialchars($employe['type_contrat'] ?: '-'),
            htmlspecialchars($employe['poste'] ?: '-'),
            htmlspecialchars($employe['nom_service'] ?: '-'),
            $date_naissance,
            $date_embauche,
            // Boutons d'action pour modifier/supprimer
            sprintf(
                '<div class="action-buttons">
                    <button type="button" class="btn btn-edit" data-id="%d" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-delete" data-id="%d" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>',
                (int)$employe['id'],
                (int)$employe['id']
            )
        ];
    }

    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => count($employes),
        'recordsFiltered' => count($employes),
        'data' => $data
    ]);
} catch(PDOException $e) {
    error_log("Erreur get_employee_list.php : " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Erreur lors de la récupération des employés : ' . $e->getMessage()
    ]);
}
?>
