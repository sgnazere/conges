<?php
require_once 'check_auth.php';
require_once 'config.php';

$db = getDBConnection();

// Récupérer l'ID de la demande
$id_demande = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id_demande) {
    die("ID de demande non spécifié");
}

try {
    // Récupérer les informations détaillées de la demande
    $query = "SELECT 
        d.*,
        e.nom as emp_nom,
        e.prenoms as emp_prenoms,
        e.telephone as emp_telephone,
        e.projet as emp_projet,
        e.poste as emp_poste,
        tc.nom as type_conge,
        DATEDIFF(d.date_fin, d.date_debut) + 1 as nombre_jours,
        ca.jours_restants as solde_actuel
    FROM demandes_conges d
    JOIN employees e ON d.employee_id = e.id
    JOIN types_conges tc ON d.type_conge_id = tc.id
    LEFT JOIN conges_annuels ca ON e.id = ca.employee_id AND YEAR(d.date_debut) = ca.annee
    WHERE d.id = ?";

    $stmt = $db->prepare($query);
    $stmt->execute([$id_demande]);
    $demande = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$demande) {
        die("Demande non trouvée");
    }

} catch(PDOException $e) {
    die("Erreur lors de la récupération des données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la demande de congé</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4CAF50;
            --text-primary: #212121;
            --text-secondary: #757575;
            --divider-color: #BDBDBD;
            --background-light: #f5f5f5;
            --white: #ffffff;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--background-light);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--divider-color);
        }

        .logo {
            width: 150px;
            height: auto;
            margin-right: 20px;
        }

        .header-content {
            flex: 1;
            text-align: center;
        }

        .header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 24px;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-item {
            margin-bottom: 10px;
        }

        .info-item strong {
            color: var(--text-secondary);
            display: inline-block;
            width: 150px;
        }

        .signature-section {
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .signature-box {
            border: 1px solid var(--divider-color);
            padding: 15px;
            text-align: center;
            height: 150px;
        }

        .signature-box h4 {
            margin: 0 0 15px 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .signature-line {
            border-bottom: 1px solid var(--divider-color);
            width: 80%;
            margin: 50px auto 10px;
        }

        .signature-date {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 10px;
        }

        .btn-print {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        @media print {
            .btn-print {
                display: none;
            }

            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .container {
                box-shadow: none;
                padding: 20px;
                max-width: none;
            }

            @page {
                size: A4;
                margin: 2cm;
            }
        }
    </style>
</head>
<body>
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <button onclick="window.print()" class="btn btn-primary btn-print">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <a href="gerer_conges.php" class="btn btn-secondary">
            <i class="fas fa-back"></i> Retour
        </a>
    </div>

    <div class="container">
        <div class="header">
            <img src="./images/Logo2.png" alt="Logo" class="logo">
            <div class="header-content">
                <h1>DEMANDE DE CONGÉ</h1>
                <p>N° <?php echo str_pad($demande['id'], 5, '0', STR_PAD_LEFT); ?></p>
            </div>
            <div style="width: 150px;"></div>
        </div>

        <h2><i class="fas fa-info-circle"></i> Détail de la demande</h2>

        <div class="info-section">
            <h3>Informations de l'employé</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Nom et Prénoms:</strong>
                    <?php echo htmlspecialchars($demande['emp_nom'] . ' ' . $demande['emp_prenoms']); ?>
                </div>
                <div class="info-item">
                    <strong>Poste:</strong>
                    <?php echo htmlspecialchars($demande['emp_poste']); ?>
                </div>
                <div class="info-item">
                    <strong>Projet:</strong>
                    <?php echo htmlspecialchars($demande['emp_projet']); ?>
                </div>
                <div class="info-item">
                    <strong>Téléphone:</strong>
                    <?php echo htmlspecialchars($demande['emp_telephone']); ?>
                </div>
            </div>
        </div>

        <div class="info-section">
            <h3>Détails du congé</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Type de congé:</strong>
                    <?php echo htmlspecialchars($demande['type_conge']); ?>
                </div>
                <div class="info-item">
                    <strong>Date de demande:</strong>
                    <?php echo date('d/m/Y', strtotime($demande['date_demande'])); ?>
                </div>
                <div class="info-item">
                    <strong>Date de début:</strong>
                    <?php echo date('d/m/Y', strtotime($demande['date_debut'])); ?>
                </div>
                <div class="info-item">
                    <strong>Date de fin:</strong>
                    <?php echo date('d/m/Y', strtotime($demande['date_fin'])); ?>
                </div>
                <div class="info-item">
                    <strong>Nombre de jours:</strong>
                    <?php echo number_format($demande['nombre_jours'], 1); ?> jours
                </div>
                <?php if ($demande['type_conge_id'] == 1): ?>
                <div class="info-item">
                    <strong>Solde restant:</strong>
                    <?php echo number_format($demande['solde_actuel'], 1); ?> jours
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($demande['commentaire'])): ?>
            <div class="info-item" style="margin-top: 20px;">
                <strong>Commentaire:</strong><br>
                <?php echo nl2br(htmlspecialchars($demande['commentaire'])); ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="signature-section">
            <div class="signature-grid">
                <div class="signature-box">
                    <h4>Signature de l'employé</h4>
                    <div class="signature-line"></div>
                    <div class="signature-date">Date: <?php echo date('d/m/Y', strtotime($demande['date_demande'])); ?></div>
                </div>
                <div class="signature-box">
                    <h4>Signature du superviseur</h4>
                    <div class="signature-line"></div>
                    <div class="signature-date">Date: ___/___/_____</div>
                </div>
                <div class="signature-box">
                    <h4>Signature du Directeur Exécutif</h4>
                    <div class="signature-line"></div>
                    <div class="signature-date">Date: ___/___/_____</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 