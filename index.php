<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Définir les en-têtes de sécurité
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

require_once 'config.php';

// Gestion des erreurs de timeout
$timeout_username = '';
if (isset($_GET['error']) && $_GET['error'] === 'timeout') {
    $timeout_username = $_GET['username'] ?? '';
    $error_message = "Votre session a expiré en raison d'une inactivité de 5 minutes. Veuillez vous reconnecter.";
}

// Vérifier si des données ont déjà été envoyées au navigateur
if (headers_sent($filename, $linenum)) {
    error_log("Headers déjà envoyés. Fichier: $filename, ligne: $linenum");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Login - Gestion des Congés</title>
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styles pour la modale */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: block;
            opacity: 1;
        }

        .modal-content {
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0.95) 0%,
                rgba(255, 255, 255, 0.95) 100%
            );
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            margin: 15% auto;
            padding: 30px;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 400px;
            position: relative;
            transform: translateY(-50px);
            transition: transform 0.3s ease;
            box-shadow: var(--shadow-md);
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--text-primary);
        }

        #forgotPasswordForm {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        #forgotPasswordForm .form-group {
            margin-bottom: 15px;
        }

        #forgotPasswordForm input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--divider-color);
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        #forgotPasswordForm input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
        }

        #forgotPasswordForm button {
            background-color: var(--primary-color);
            color: white;
            padding: 12px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        #forgotPasswordForm button:hover {
            background-color: var(--primary-dark);
        }

        .modal h2 {
            color: var(--text-primary);
            margin-bottom: 20px;
            font-size: 24px;
            text-align: center;
        }

        .alert {
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            font-size: 14px;
        }

        .alert-danger {
            background-color: var(--danger);
            color: white;
        }

        .alert-success {
            background-color: var(--success);
            color: white;
        }

        #modalMessage {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <form method="POST" action="login_handler.php" class="form_main" id="loginForm" autocomplete="off" novalidate>
            <p class="heading">Connexion</p>
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['license_error'])): ?>
                <div class="error-message license-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php 
                        echo htmlspecialchars($_SESSION['license_error']);
                        unset($_SESSION['license_error']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="inputContainer">
                <svg class="inputIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#2e2e2e" viewBox="0 0 16 16">
                    <path d="M13.106 7.222c0-2.967-2.249-5.032-5.482-5.032-3.35 0-5.646 2.318-5.646 5.702 0 3.493 2.235 5.708 5.762 5.708.862 0 1.689-.123 2.304-.335v-.862c-.43.199-1.354.328-2.29.328-2.926 0-4.813-1.88-4.813-4.798 0-2.844 1.921-4.881 4.594-4.881 2.735 0 4.608 1.688 4.608 4.156 0 1.682-.554 2.769-1.416 2.769-.492 0-.772-.28-.772-.76V5.206H8.923v.834h-.11c-.266-.595-.881-.964-1.6-.964-1.4 0-2.378 1.162-2.378 2.823 0 1.737.957 2.906 2.379 2.906.8 0 1.415-.39 1.709-1.087h.11c.081.67.703 1.148 1.503 1.148 1.572 0 2.57-1.415 2.57-3.643zm-7.177.704c0-1.197.54-1.907 1.456-1.907.93 0 1.524.738 1.524 1.907S8.308 9.84 7.371 9.84c-.895 0-1.442-.725-1.442-1.914z"></path>
                </svg>
                <input type="text" class="inputField" id="username" name="username" placeholder="Nom d'utilisateur" value="<?php echo htmlspecialchars($timeout_username); ?>" autocomplete="username" required>
            </div>
    
            <div class="inputContainer">
                <svg class="inputIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#2e2e2e" viewBox="0 0 16 16">
                    <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"></path>
                </svg>
                <input type="password" class="inputField" id="password" name="password" placeholder="Mot de passe" autocomplete="current-password" required>
            </div>
              
            <button type="submit" id="button">Se connecter</button>
            <a href="#" class="forgotLink" onclick="openForgotPasswordModal(); return false;">Mot de passe oublié ?</a>
        </form>
    </div>

    <!-- Modal Mot de passe oublié -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeForgotPasswordModal()">&times;</span>
            <h2>Mot de passe oublié</h2>
            <div id="modalMessage"></div>
            <form id="forgotPasswordForm" onsubmit="handleForgotPassword(event)">
                <div class="form-group">
                    <label for="email">Email :</label>
                    <input type="email" id="email" name="email" placeholder="Entrez votre adresse email" required>
                </div>
                <div class="form-group">
                    <button type="submit">Réinitialiser le mot de passe</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Variables globales pour éviter les soumissions multiples
    let isSubmitting = false;
    let submitTimeout = null;

    // Fonction pour nettoyer les champs sensibles
    function clearSensitiveFields() {
        const passwordField = document.getElementById('password');
        if (passwordField) {
            passwordField.value = '';
        }
    }

    // Fonction pour désactiver l'autocomplétion du navigateur
    function disableAutocomplete() {
        const form = document.getElementById('loginForm');
        const inputs = form.querySelectorAll('input');
        
        inputs.forEach(input => {
            // Ajouter un attribut data-autocomplete pour éviter les conflits
            input.setAttribute('data-autocomplete', 'off');
            
            // Écouter les événements d'autocomplétion
            input.addEventListener('change', function() {
                // Délai pour laisser l'autocomplétion se terminer
                setTimeout(() => {
                    if (this.type === 'password') {
                        // Vérifier si le champ a été rempli par l'autocomplétion
                        if (this.value && !this.dataset.userTyped) {
                            this.dataset.autoFilled = 'true';
                        }
                    }
                }, 100);
            });
            
            // Marquer quand l'utilisateur tape manuellement
            input.addEventListener('input', function() {
                this.dataset.userTyped = 'true';
                delete this.dataset.autoFilled;
            });
        });
    }

    // Fonction pour valider le formulaire
    function validateForm() {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();
        
        if (!username) {
            alert('Veuillez saisir votre nom d\'utilisateur');
            document.getElementById('username').focus();
            return false;
        }
        
        if (!password) {
            alert('Veuillez saisir votre mot de passe');
            document.getElementById('password').focus();
            return false;
        }
        
        return true;
    }

    // Gestionnaire principal du formulaire
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Éviter les soumissions multiples
        if (isSubmitting) {
            console.log('Soumission déjà en cours...');
            return false;
        }
        
        // Valider le formulaire
        if (!validateForm()) {
            return false;
        }
        
        const formData = new FormData(this);
        const submitButton = document.getElementById('button');
        const originalText = submitButton.textContent;
        
        // Marquer comme en cours de soumission
        isSubmitting = true;
        
        // Désactiver le bouton et changer le texte
        submitButton.disabled = true;
        submitButton.textContent = 'Connexion en cours...';
        
        // Timeout de sécurité (30 secondes)
        submitTimeout = setTimeout(() => {
            isSubmitting = false;
            submitButton.disabled = false;
            submitButton.textContent = originalText;
            alert('Délai d\'attente dépassé. Veuillez réessayer.');
        }, 30000);
        
        fetch('login_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            clearTimeout(submitTimeout);
            
            if (data.success) {
                // Nettoyer les champs sensibles avant la redirection
                clearSensitiveFields();
                
                // Rediriger directement vers la page de gestion
                window.location.href = data.redirect;
            } else {
                // Afficher l'erreur
                alert(data.message || 'Identifiants incorrects');
                
                // Réactiver le bouton
                isSubmitting = false;
                submitButton.disabled = false;
                submitButton.textContent = originalText;
                
                // Focus sur le champ de mot de passe en cas d'erreur
                document.getElementById('password').focus();
            }
        })
        .catch(error => {
            clearTimeout(submitTimeout);
            console.error('Erreur:', error);
            alert('Erreur de connexion. Veuillez réessayer.');
            
            // Réactiver le bouton
            isSubmitting = false;
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        });
    });

    // Initialisation au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        disableAutocomplete();
        
        // Focus sur le champ username au chargement
        const usernameField = document.getElementById('username');
        if (usernameField && !usernameField.value) {
            usernameField.focus();
        }
        
        // Écouter les événements de clavier pour Enter
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !isSubmitting) {
                const activeElement = document.activeElement;
                if (activeElement && activeElement.form === document.getElementById('loginForm')) {
                    // Laisser le formulaire se soumettre normalement
                    return true;
                }
            }
        });
    });

    function openForgotPasswordModal() {
        document.getElementById('forgotPasswordModal').classList.add('show');
        document.getElementById('email').focus();
    }

    function closeForgotPasswordModal() {
        document.getElementById('forgotPasswordModal').classList.remove('show');
        document.getElementById('modalMessage').textContent = '';
        document.getElementById('forgotPasswordForm').reset();
    }

    window.onclick = function(event) {
        const modal = document.getElementById('forgotPasswordModal');
        if (event.target == modal) {
            closeForgotPasswordModal();
        }
    }

    async function handleForgotPassword(event) {
        event.preventDefault();
        const form = event.target;
        const email = form.email.value;
        const messageDiv = document.getElementById('modalMessage');
        const submitButton = form.querySelector('button[type="submit"]');
        
        // Désactiver le bouton pendant la requête
        submitButton.disabled = true;
        submitButton.textContent = 'Envoi en cours...';

        try {
            const response = await fetch('forgot_password_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `email=${encodeURIComponent(email)}`
            });

            const result = await response.json();
            
            if (result.success) {
                messageDiv.className = 'alert alert-success';
                messageDiv.textContent = result.message;
                form.reset();
                setTimeout(() => {
                    closeForgotPasswordModal();
                    location.reload();
                }, 3000);
            } else {
                messageDiv.className = 'alert alert-danger';
                messageDiv.textContent = result.message;
            }
        } catch (error) {
            messageDiv.className = 'alert alert-danger';
            messageDiv.textContent = "Une erreur est survenue. Veuillez réessayer plus tard.";
        } finally {
            // Réactiver le bouton
            submitButton.disabled = false;
            submitButton.textContent = 'Réinitialiser le mot de passe';
        }
    }
    </script>
</body>
</html> 