<?php
require_once 'check_auth.php';
require_once 'config.php';

$db = getDBConnection();

// Période par défaut et filtres
$annee = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');
$mois = isset($_GET['mois']) ? intval($_GET['mois']) : null;
$projet = isset($_GET['projet']) ? $_GET['projet'] : null;
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;

// Fonctions pour récupérer les données des statistiques
function getCongesParMois($db, $annee, $employee_id = null) {
    $sql = "SELECT MONTH(date_debut) as mois, COUNT(*) as nombre, 
            SUM(DATEDIFF(date_fin, date_debut) + 1) as jours_total
            FROM demandes_conges 
            WHERE YEAR(date_debut) = :annee ";
    
    if ($employee_id) {
        $sql .= "AND employee_id = :employee_id ";
    }
    
    $sql .= "GROUP BY MONTH(date_debut) ORDER BY mois";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
    if ($employee_id) {
        $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEtatDemandes($db, $annee, $employee_id = null) {
    $sql = "SELECT statut, COUNT(*) as nombre 
            FROM demandes_conges 
            WHERE YEAR(date_debut) = :annee ";
    
    if ($employee_id) {
        $sql .= "AND employee_id = :employee_id ";
    }
    
    $sql .= "GROUP BY statut";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
    if ($employee_id) {
        $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAbsenteismeParService($db, $annee) {
    $sql = "SELECT s.nom as service, 
            COUNT(dc.id) as nombre_conges,
            SUM(DATEDIFF(dc.date_fin, dc.date_debut) + 1) as jours_total
            FROM services s
            LEFT JOIN employees e ON e.service_id = s.id
            LEFT JOIN demandes_conges dc ON dc.employee_id = e.id
            WHERE YEAR(dc.date_debut) = :annee
            GROUP BY s.id, s.nom";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRepartitionTypesConges($db, $annee, $employee_id = null) {
    $sql = "SELECT tc.nom as type_conge, COUNT(*) as nombre
            FROM demandes_conges dc
            JOIN types_conges tc ON dc.type_conge_id = tc.id
            WHERE YEAR(dc.date_debut) = :annee ";
    
    if ($employee_id) {
        $sql .= "AND dc.employee_id = :employee_id ";
    }
    
    $sql .= "GROUP BY tc.id, tc.nom";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
    if ($employee_id) {
        $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSoldeConges($db, $employee_id) {
    $sql = "SELECT 
            e.solde_conges as solde_total,
            (SELECT SUM(DATEDIFF(date_fin, date_debut) + 1)
             FROM demandes_conges 
             WHERE employee_id = e.id 
             AND statut = 'approuve'
             AND YEAR(date_debut) = YEAR(CURRENT_DATE)) as conges_pris
            FROM employees e
            WHERE e.id = :employee_id";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupération des données pour les graphiques
$congesParMois = getCongesParMois($db, $annee, $employee_id);
$etatDemandes = getEtatDemandes($db, $annee, $employee_id);
$absenteismeService = getAbsenteismeParService($db, $annee);
$repartitionConges = getRepartitionTypesConges($db, $annee, $employee_id);
if ($employee_id) {
    $soldeConges = getSoldeConges($db, $employee_id);
}

// Récupération des employés pour le filtre
try {
    $query = $db->query("SELECT id, nom, prenoms FROM employees ORDER BY nom, prenoms");
    $employes = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Erreur lors de la récupération des employés : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports et Statistiques</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            background-color: var(--background-light);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            white-space: nowrap;
        }

        .page-header h2 {
            color: var(--primary-color);
            margin: 0;
            font-size: 1.8rem;
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
        }

        .nav-btn i {
            font-size: 1.1rem;
        }

        .nav-btn:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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

        .filters-container {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--text-secondary);
        }

        .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            font-size: 0.9rem;
            background-color: var(--white);
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .btn-filter {
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .stats-container {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--background-light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--primary-dark);
            font-weight: 500;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }

        @media (max-width: 1024px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .header-actions {
                justify-content: center;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: stretch;
            }

            .btn-filter {
                width: 100%;
                justify-content: center;
            }
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
    </style>
</head>
<body>
    <div class="container">
        <!-- En-tête avec navigation -->
        <div class="page-header">
            <div class="header-title">
                <h2><i class="fas fa-chart-bar"></i> Rapports et Statistiques</h2>
            </div>
            <div class="header-actions">
                <a href="gestion_conges.php" class="nav-btn">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a href="gerer_conges.php" class="nav-btn">
                    <i class="fas fa-calendar-check"></i> Gérer les congés
                </a>
                <a href="calendrier_conges.php" class="nav-btn">
                    <i class="fas fa-calendar-alt"></i> Calendrier
                </a>
                <a href="gerer_employes.php" class="nav-btn">
                    <i class="fas fa-users"></i> Employés
                </a>
                <a href="logout.php" class="nav-btn nav-btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters-container">
            <div class="stats-title">
                <i class="fas fa-filter"></i> Filtres
            </div>
            <div class="filters">
                <div class="filter-group">
                    <label for="annee">Année:</label>
                    <select id="annee" name="annee">
                        <?php
                        $current_year = date('Y');
                        for($y = $current_year - 2; $y <= $current_year + 1; $y++) {
                            $selected = $y == $annee ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="mois">Mois:</label>
                    <select id="mois" name="mois">
                        <option value="">Tous les mois</option>
                        <?php
                        for($m = 1; $m <= 12; $m++) {
                            $selected = $m == $mois ? 'selected' : '';
                            echo "<option value='$m' $selected>" . strftime('%B', mktime(0, 0, 0, $m, 1)) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="employee">Employé:</label>
                    <select id="employee" name="employee_id">
                        <option value="">Tous les employés</option>
                        <?php foreach ($employes as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $emp['id'] == $employee_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['nom'] . ' ' . $emp['prenoms']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-filter" onclick="appliquerFiltres()">
                    <i class="fas fa-search"></i> Appliquer les filtres
                </button>
            </div>
        </div>

        <!-- Vue d'ensemble -->
        <div class="stats-container">
            <div class="stats-title">
                <i class="fas fa-tachometer-alt"></i> Vue d'ensemble
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-chart-bar"></i>
                        Vue mensuelle
                    </div>
                    <div class="chart-container">
                        <canvas id="vueMensuelle"></canvas>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-tasks"></i>
                        État des demandes
                    </div>
                    <div class="chart-container">
                        <canvas id="etatDemandes"></canvas>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-calendar-day"></i>
                        Jours de congés par mois
                    </div>
                    <div class="chart-container">
                        <canvas id="congesParMois"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques par Service -->
        <div class="stats-container">
            <div class="stats-title">
                <i class="fas fa-building"></i> Statistiques par Service
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-users"></i>
                        Taux d'absentéisme par service
                    </div>
                    <div class="chart-container">
                        <canvas id="absenteismeService"></canvas>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-chart-pie"></i>
                        Répartition des types de congés
                    </div>
                    <div class="chart-container">
                        <canvas id="repartitionConges"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapports par Employé -->
        <div class="stats-container">
            <div class="stats-title">
                <i class="fas fa-user-chart"></i> Rapports par Employé
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-history"></i>
                        Historique des congés
                    </div>
                    <div class="chart-container">
                        <canvas id="historiqueConges"></canvas>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-balance-scale"></i>
                        Solde de congés
                    </div>
                    <div class="chart-container">
                        <canvas id="soldeConges"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Planification -->
        <div class="stats-container">
            <div class="stats-title">
                <i class="fas fa-calendar"></i> Planification
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-calendar-alt"></i>
                        Calendrier des congés à venir
                    </div>
                    <div class="chart-container">
                        <canvas id="congesAVenir"></canvas>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-chart-line"></i>
                        Périodes de forte demande
                    </div>
                    <div class="chart-container">
                        <canvas id="forteDemande"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour appliquer les filtres
        function appliquerFiltres() {
            const annee = document.getElementById('annee').value;
            const mois = document.getElementById('mois').value;
            const employee = document.getElementById('employee').value;
            
            let url = 'rapports.php?annee=' + annee;
            if (mois) url += '&mois=' + mois;
            if (employee) url += '&employee_id=' + employee;
            
            window.location.href = url;
        }

        // Données PHP converties en JSON pour les graphiques
        const donneesCongesParMois = <?php echo json_encode($congesParMois); ?>;
        const donneesEtatDemandes = <?php echo json_encode($etatDemandes); ?>;
        const donneesAbsenteismeService = <?php echo json_encode($absenteismeService); ?>;
        const donneesRepartitionConges = <?php echo json_encode($repartitionConges); ?>;
        <?php if (isset($soldeConges)): ?>
        const donneesSoldeConges = <?php echo json_encode($soldeConges); ?>;
        <?php endif; ?>

        // Configuration commune pour les graphiques
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        };

        // Couleurs pour les graphiques
        const colors = {
            primary: 'rgba(76, 175, 80, 0.7)',      // Vert pour approuvé
            primaryBorder: 'rgba(76, 175, 80, 1)',
            secondary: 'rgba(33, 150, 243, 0.7)',    // Bleu
            secondaryBorder: 'rgba(33, 150, 243, 1)',
            warning: 'rgba(255, 152, 0, 0.7)',       // Orange pour en attente
            warningBorder: 'rgba(255, 152, 0, 1)',
            danger: 'rgba(244, 67, 54, 0.7)',        // Rouge pour refusé
            dangerBorder: 'rgba(244, 67, 54, 1)',
            standby: 'rgba(156, 39, 176, 0.7)',      // Violet pour standby
            standbyBorder: 'rgba(156, 39, 176, 1)',
            cancelled: 'rgba(158, 158, 158, 0.7)',   // Gris pour annulé
            cancelledBorder: 'rgba(158, 158, 158, 1)'
        };

        // Initialisation des graphiques
        function initializeCharts() {
            // Graphique des congés par mois
            const congesParMoisData = Array(12).fill(0);
            donneesCongesParMois.forEach(item => {
                congesParMoisData[item.mois - 1] = parseInt(item.jours_total);
            });

            new Chart(document.getElementById('congesParMois'), {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                    datasets: [{
                        label: 'Jours de congés',
                        data: congesParMoisData,
                        backgroundColor: colors.primary,
                        borderColor: colors.primaryBorder,
                        borderWidth: 1
                    }]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de jours'
                            }
                        }
                    }
                }
            });

            // Graphique de l'état des demandes
            const labels = [];
            const data = [];
            const backgroundColors = [];
            const borderColors = [];

            donneesEtatDemandes.forEach(item => {
                labels.push(item.statut);
                data.push(parseInt(item.nombre));
                switch(item.statut.toLowerCase()) {
                    case 'approuve':
                        backgroundColors.push(colors.primary);
                        borderColors.push(colors.primaryBorder);
                        break;
                    case 'en_attente':
                        backgroundColors.push(colors.warning);
                        borderColors.push(colors.warningBorder);
                        break;
                    case 'refuse':
                        backgroundColors.push(colors.danger);
                        borderColors.push(colors.dangerBorder);
                        break;
                    case 'standby':
                        backgroundColors.push(colors.standby);
                        borderColors.push(colors.standbyBorder);
                        break;
                    case 'annule':
                        backgroundColors.push(colors.cancelled);
                        borderColors.push(colors.cancelledBorder);
                        break;
                    default:
                        backgroundColors.push(colors.secondary);
                        borderColors.push(colors.secondaryBorder);
                }
            });

            new Chart(document.getElementById('etatDemandes'), {
                type: 'doughnut',
                data: {
                    labels: labels.map(label => {
                        switch(label.toLowerCase()) {
                            case 'approuve': return 'Approuvé';
                            case 'en_attente': return 'En attente';
                            case 'refuse': return 'Refusé';
                            case 'standby': return 'En pause';
                            case 'annule': return 'Annulé';
                            default: return label;
                        }
                    }),
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20
                            }
                        }
                    }
                }
            });

            // Graphique de l'absentéisme par service
            new Chart(document.getElementById('absenteismeService'), {
                type: 'bar',
                data: {
                    labels: donneesAbsenteismeService.map(item => item.service),
                    datasets: [{
                        label: 'Jours d\'absence',
                        data: donneesAbsenteismeService.map(item => parseInt(item.jours_total) || 0),
                        backgroundColor: colors.secondary,
                        borderColor: colors.secondaryBorder,
                        borderWidth: 1
                    }]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de jours'
                            }
                        }
                    }
                }
            });

            // Graphique de répartition des types de congés
            new Chart(document.getElementById('repartitionConges'), {
                type: 'pie',
                data: {
                    labels: donneesRepartitionConges.map(item => item.type_conge),
                    datasets: [{
                        data: donneesRepartitionConges.map(item => parseInt(item.nombre)),
                        backgroundColor: [
                            colors.primary,
                            colors.secondary,
                            colors.warning,
                            colors.danger
                        ],
                        borderColor: [
                            colors.primaryBorder,
                            colors.secondaryBorder,
                            colors.warningBorder,
                            colors.dangerBorder
                        ],
                        borderWidth: 1
                    }]
                },
                options: commonOptions
            });

            // Graphique du solde de congés (si un employé est sélectionné)
            if (typeof donneesSoldeConges !== 'undefined') {
                const soldeRestant = parseInt(donneesSoldeConges.solde_total) - (parseInt(donneesSoldeConges.conges_pris) || 0);
                
                new Chart(document.getElementById('soldeConges'), {
                    type: 'bar',
                    data: {
                        labels: ['Solde total', 'Congés pris', 'Solde restant'],
                        datasets: [{
                            label: 'Jours',
                            data: [
                                parseInt(donneesSoldeConges.solde_total),
                                parseInt(donneesSoldeConges.conges_pris) || 0,
                                soldeRestant
                            ],
                            backgroundColor: [
                                colors.primary,
                                colors.warning,
                                colors.secondary
                            ],
                            borderColor: [
                                colors.primaryBorder,
                                colors.warningBorder,
                                colors.secondaryBorder
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...commonOptions,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Nombre de jours'
                                }
                            }
                        }
                    }
                });
            }
        }

        // Initialiser les graphiques au chargement de la page
        document.addEventListener('DOMContentLoaded', initializeCharts);
    </script>
</body>
</html> 