<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once 'config.php';

// Fonction de vérification de connexion
function verifierConnexion($username, $password) {
    global $db;
    
    try {
        error_log("Début de la vérification de connexion pour l'utilisateur: " . $username);
        
        $query = $db->prepare("SELECT * FROM admin WHERE username = :username");
        $query->execute(['username' => $username]);
        $admin = $query->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            error_log("Utilisateur trouvé dans la base de données");
            
            if (password_verify($password, $admin['password'])) {
                error_log("Mot de passe vérifié avec succès");
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['is_logged'] = true;
                $_SESSION['last_activity'] = time();
                error_log("Session initialisée avec succès");
                return true;
            } else {
                error_log("Échec de la vérification du mot de passe");
            }
        } else {
            error_log("Aucun utilisateur trouvé avec ce nom d'utilisateur");
        }
        return false;
    } catch(PDOException $e) {
        error_log("Erreur lors de la vérification de connexion : " . $e->getMessage());
        return false;
    }
}

// Traitement de la connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'redirect' => ''];
    
    try {
        $db = getDBConnection();
        error_log("Connexion à la base de données réussie");
    } catch(PDOException $e) {
        error_log("Erreur de connexion à la base de données : " . $e->getMessage());
        $response['message'] = "Erreur de connexion à la base de données";
        echo json_encode($response);
        exit();
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    error_log("Tentative de connexion pour: " . $username);
    
    if (empty($username) || empty($password)) {
        $response['message'] = "Veuillez remplir tous les champs";
    } else {
        if (verifierConnexion($username, $password)) {
            $response['success'] = true;
            $response['message'] = "Connexion réussie";
            $response['redirect'] = "gestion_conges.php";
            error_log("Connexion réussie pour: " . $username);
        } else {
            $response['message'] = "Identifiants incorrects";
            error_log("Échec de connexion pour: " . $username);
        }
    }
    
    // Retourner la réponse en JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Si accès direct, rediriger vers la page de connexion
header('Location: index.php');
exit();
?> 