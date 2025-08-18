<?php
require_once 'check_auth.php';
require_once 'config.php';

$db = getDBConnection();

// Récupération des statistiques générales
try {
    // 1. Nombre total de congés par type
    $query = $db->query("
        SELECT tc.nom as type_conge, COUNT(*) as nombre, SUM(dc.nb_jours) as total_jours
        FROM demandes_conges dc
        JOIN types_conges tc ON dc.type_conge_id = tc.id
        WHERE dc.statut = 'approuve'
        GROUP BY tc.id, tc.nom
    ");
    $conges_par_type = $query->fetchAll(PDO::FETCH_ASSOC);

    // 2. Congés par mois pour l'année en cours
    $query = $db->query("
        SELECT MONTH(date_debut) as mois, COUNT(*) as nombre, SUM(nb_jours) as total_jours
        FROM demandes_conges
        WHERE YEAR(date_debut) = YEAR(CURRENT_DATE) AND statut = 'approuve'
        GROUP BY MONTH(date_debut)
    ");
    $conges_par_mois = $query->fetchAll(PDO::FETCH_ASSOC);

    // 3. Taux d'approbation
    $query = $db->query("
        SELECT 
            statut,
            COUNT(*) as nombre,
            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM demandes_conges)) as pourcentage
        FROM demandes_conges
        GROUP BY statut
    ");
    $taux_approbation = $query->fetchAll(PDO::FETCH_ASSOC);

    // 4. Top 5 des employés avec le plus de congés
    $query = $db->query("
        SELECT 
            e.nom,
            e.prenoms,
            COUNT(dc.id) as nombre_demandes,
            SUM(dc.nb_jours) as total_jours
        FROM employees e
        LEFT JOIN demandes_conges dc ON e.id = dc.employee_id AND dc.statut = 'approuve'
        GROUP BY e.id, e.nom, e.prenoms
        ORDER BY total_jours DESC
        LIMIT 5
    ");
    $top_employes = $query->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $error_message = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
}

// Préparation des données pour les graphiques
$labels_types = [];
$data_types = [];
foreach ($conges_par_type as $type) {
    $labels_types[] = $type['type_conge'];
    $data_types[] = $type['total_jours'];
}

$labels_mois = [];
$data_mois = array_fill(0, 12, 0); // Initialiser tous les mois à 0
foreach ($conges_par_mois as $mois) {
    $data_mois[$mois['mois']-1] = floatval($mois['total_jours']);
}
for ($i = 0; $i < 12; $i++) {
    $labels_mois[] = date('F', mktime(0, 0, 0, $i + 1, 1));
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Congés</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .dashboard {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
            width: 95%;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .stats-card {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stats-card h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .top-employes {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .top-employes th,
        .top-employes td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--divider-color);
        }

        .top-employes th {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            font-weight: 600;
        }

        .top-employes tr:hover {
            background-color: var(--background-light);
        }

        .stat-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-item .number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-item .label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .dashboard {
                padding: 1rem;
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="page-header">
            <h2><i class="fas fa-chart-line"></i> Statistiques des Congés</h2>
            <div class="header-actions">
                <a href="gestion_conges.php" class="nav-btn">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a href="gerer_conges.php" class="nav-btn">
                    <i class="fas fa-calendar-check"></i> Gérer les congés
                </a>
                <a href="gerer_employes.php" class="nav-btn">
                    <i class="fas fa-users"></i> Employés
                </a>
            </div>
        </div>

        <!-- Résumé des statistiques -->
        <div class="stat-summary">
            <div class="stat-item">
                <div class="number">
                    <?php 
                        $total_jours = array_sum(array_column($conges_par_type, 'total_jours'));
                        echo number_format($total_jours, 1);
                    ?>
                </div>
                <div class="label">Jours de congés pris</div>
            </div>
            <div class="stat-item">
                <div class="number">
                    <?php 
                        $taux_approuve = 0;
                        foreach ($taux_approbation as $taux) {
                            if ($taux['statut'] === 'approuve') {
                                $taux_approuve = round($taux['pourcentage']);
                                break;
                            }
                        }
                        echo $taux_approuve . '%';
                    ?>
                </div>
                <div class="label">Taux d'approbation</div>
            </div>
            <div class="stat-item">
                <div class="number">
                    <?php echo count($conges_par_type); ?>
                </div>
                <div class="label">Types de congés utilisés</div>
            </div>
        </div>

        <div class="stats-grid">
            <!-- Graphique des congés par type -->
            <div class="stats-card">
                <h3><i class="fas fa-chart-pie"></i> Répartition par type de congés</h3>
                <div class="chart-container">
                    <canvas id="congesParType"></canvas>
                </div>
            </div>

            <!-- Graphique des congés par mois -->
            <div class="stats-card">
                <h3><i class="fas fa-chart-bar"></i> Congés par mois</h3>
                <div class="chart-container">
                    <canvas id="congesParMois"></canvas>
                </div>
            </div>

            <!-- Top 5 des employés -->
            <div class="stats-card">
                <h3><i class="fas fa-trophy"></i> Top 5 des employés</h3>
                <table class="top-employes">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Nombre de congés</th>
                            <th>Total jours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_employes as $employe): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employe['prenoms'] . ' ' . $employe['nom']); ?></td>
                                <td><?php echo $employe['nombre_demandes']; ?></td>
                                <td><?php echo number_format($employe['total_jours'], 1); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Taux d'approbation -->
            <div class="stats-card">
                <h3><i class="fas fa-check-circle"></i> Statuts des demandes</h3>
                <div class="chart-container">
                    <canvas id="tauxApprobation"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration des graphiques
        const typeColors = [
            '#4CAF50', '#2196F3', '#FFC107', '#FF5722', '#9C27B0',
            '#795548', '#607D8B', '#E91E63', '#9E9E9E', '#CDDC39'
        ];

        // Graphique des congés par type
        new Chart(document.getElementById('congesParType'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($labels_types); ?>,
                datasets: [{
                    data: <?php echo json_encode($data_types); ?>,
                    backgroundColor: typeColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Graphique des congés par mois
        new Chart(document.getElementById('congesParMois'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels_mois); ?>,
                datasets: [{
                    label: 'Jours de congés',
                    data: <?php echo json_encode($data_mois); ?>,
                    backgroundColor: '#4CAF50',
                    borderColor: '#388E3C',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Graphique des taux d'approbation
        new Chart(document.getElementById('tauxApprobation'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($taux_approbation, 'statut')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($taux_approbation, 'nombre')); ?>,
                    backgroundColor: {
                        'approuve': '#28a745',
                        'refuse': '#dc3545',
                        'standby': '#ffc107',
                        'annule': '#6c757d'
                    }
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>
</body>
</html> 