<?php
require_once 'config.php';

header('Content-Type: application/json');

// Vérifier si les paramètres nécessaires sont fournis
if (!isset($_GET['nom']) || !isset($_GET['prenoms'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants (nom et prenoms requis)',
        'exists' => false
    ]);
    exit;
}

$nom = trim($_GET['nom']);
$prenoms = trim($_GET['prenoms']);
$premier_prenom = explode(' ', $prenoms)[0]; // Récupérer le premier prénom

if (empty($nom) || empty($premier_prenom)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Le nom et le prénom sont requis',
        'exists' => false
    ]);
    exit;
}

try {
    $db = getDBConnection();
    
    // Rechercher l'employé avec le même nom et premier prénom
    $query = $db->prepare("SELECT id, nom, prenoms, email, poste FROM employees WHERE LOWER(nom) = LOWER(?) AND LOWER(SUBSTRING_INDEX(prenoms, ' ', 1)) = LOWER(?)");
    $query->execute([$nom, $premier_prenom]);
    $employee = $query->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        echo json_encode([
            'success' => true,
            'message' => "Un employé existe déjà avec ce nom et prénom",
            'exists' => true,
            'employee' => [
                'id' => $employee['id'],
                'nom' => $employee['nom'],
                'prenoms' => $employee['prenoms'],
                'email' => $employee['email'],
                'poste' => $employee['poste']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Employé non trouvé dans la base',
            'exists' => false
        ]);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la vérification : ' . $e->getMessage(),
        'exists' => false
    ]);
}