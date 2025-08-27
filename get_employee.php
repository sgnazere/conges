<?php
require_once 'check_auth.php';
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID non fourni']);
    exit;
}

try {
    $db = getDBConnection();
    $query = $db->prepare("SELECT * FROM employees WHERE id = ?");
    $query->execute([$_GET['id']]);
    $employee = $query->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Employé non trouvé'
        ]);
        exit;
    }

    // Formater les dates pour l'affichage dans les champs input type="date"
    if (!empty($employee['date_naissance'])) {
        $employee['date_naissance'] = date('Y-m-d', strtotime($employee['date_naissance']));
    }
    if (!empty($employee['date_embauche'])) {
        $employee['date_embauche'] = date('Y-m-d', strtotime($employee['date_embauche']));
    }

    echo json_encode([
        'success' => true,
        'data' => $employee
    ]);
} catch(PDOException $e) {
    error_log("Erreur get_employee.php : " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des données : ' . $e->getMessage()
    ]);
} 