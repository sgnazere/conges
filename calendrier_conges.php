<?php
require_once 'check_auth.php';
require_once 'config.php';

$db = getDBConnection();

// Récupération des demandes de congés
try {
    $query = $db->query("
        SELECT dc.*, e.nom, e.prenoms, tc.nom as type_conge
        FROM demandes_conges dc
        JOIN employees e ON dc.employee_id = e.id
        LEFT JOIN types_conges tc ON dc.type_conge_id = tc.id
        ORDER BY dc.date_debut
    ");
    $conges = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Erreur lors de la récupération des congés : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier des Congés</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
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
            min-height: 100vh;
        }

        .container {
            display: grid;
            grid-template-rows: auto 1fr;
            min-height: 100vh;
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            padding: 1rem;
            gap: 1rem;
            box-sizing: border-box;
        }

        .page-header {
            background: var(--white);
            padding: 1rem 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 1rem;
            z-index: 100;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-header h2 {
            color: var(--primary-color);
            margin: 0;
            font-size: 1.5rem;
            white-space: nowrap;
        }

        .nav-btn {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            color: var(--white);
            background: var(--primary-color);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .nav-btn-danger {
            background: var(--danger);
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .main-content {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1rem;
            height: calc(100vh - 120px);
        }

        .legend-container {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            height: fit-content;
            position: sticky;
            top: 90px;
        }

        .legend {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .legend-item:hover {
            background-color: var(--background-light);
        }

        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 6px;
        }

        .status-approuve {
            background-color: var(--success);
            color: var(--white);
        }

        .status-refuse {
            background-color: var(--danger);
            color: var(--white);
        }

        .status-standby {
            background-color: var(--warning);
            color: var(--text-primary);
        }

        .status-annule {
            background-color: var(--text-secondary);
            color: var(--white);
        }

        .calendar-container {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            height: 100%;
            overflow: auto;
        }

        /* FullCalendar personnalisation */
        .fc {
            height: 100% !important;
        }

        .fc-toolbar-title {
            color: var(--primary-dark);
            font-size: 1.25rem !important;
            font-weight: 600;
        }

        .fc-button-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            padding: 0.5rem 1rem !important;
            font-size: 0.9rem !important;
            font-weight: 500 !important;
        }

        .fc-button-primary:hover {
            background-color: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
            box-shadow: var(--shadow-sm);
        }

        .fc-event {
            border-radius: 4px;
            padding: 2px 4px;
            font-size: 0.85rem;
            border: none !important;
            margin: 1px 0 !important;
        }

        .fc-event-title {
            font-weight: 500;
            padding: 2px 4px;
        }

        .fc-day-today {
            background-color: var(--primary-light) !important;
            opacity: 0.8;
        }

        .fc-day-today .fc-daygrid-day-number {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        /* Tooltip personnalisé */
        .custom-tooltip {
            position: absolute;
            background: rgba(33, 33, 33, 0.95);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            z-index: 1000;
            pointer-events: none;
            box-shadow: var(--shadow-md);
            max-width: 300px;
            line-height: 1.5;
        }

        .custom-tooltip strong {
            color: var(--primary-light);
            display: block;
            margin-bottom: 0.25rem;
        }

        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .legend-container {
                position: static;
                margin-bottom: 1rem;
            }

            .legend {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0.5rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
                width: 100%;
            }

            .nav-btn {
                flex: 1;
                justify-content: center;
                min-width: 140px;
            }

            .calendar-container {
                padding: 1rem;
            }

            .fc-toolbar {
                flex-direction: column;
                gap: 0.5rem;
            }

            .fc-toolbar-chunk {
                display: flex;
                justify-content: center;
                width: 100%;
            }

            .fc-toolbar-title {
                font-size: 1.1rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="header-title">
                <h2><i class="fas fa-calendar-alt"></i> Calendrier des Congés</h2>
            </div>
            <div class="header-actions">
                <a href="gestion_conges.php" class="nav-btn">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a href="gerer_conges.php" class="nav-btn">
                    <i class="fas fa-calendar-check"></i> Gérer les congés
                </a>
                <a href="gerer_employes.php" class="nav-btn">
                    <i class="fas fa-users"></i> Gérer les employés
                </a>
                <a href="rapports.php" class="nav-btn">
                    <i class="fas fa-chart-bar"></i> Rapports
                </a>
                <a href="logout.php" class="nav-btn nav-btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </div>

        <div class="main-content">
            <div class="legend-container">
                <div class="legend">
                    <div class="legend-item status-approuve">
                        <div class="legend-color"></div>
                        <span>Approuvé</span>
                    </div>
                    <div class="legend-item status-refuse">
                        <div class="legend-color"></div>
                        <span>Refusé</span>
                    </div>
                    <div class="legend-item status-standby">
                        <div class="legend-color"></div>
                        <span>En attente</span>
                    </div>
                    <div class="legend-item status-annule">
                        <div class="legend-color"></div>
                        <span>Annulé</span>
                    </div>
                </div>
            </div>

            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const congés = <?php echo json_encode($conges); ?>;

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'fr',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                firstDay: 1, // Semaine commence le lundi
                buttonText: {
                    today: "Aujourd'hui",
                    month: 'Mois',
                    week: 'Semaine',
                    day: 'Jour'
                },
                events: congés.map(conge => ({
                    title: `${conge.prenoms} ${conge.nom} - ${conge.type_conge}`,
                    start: conge.date_debut,
                    end: conge.date_fin,
                    backgroundColor: getStatusColor(conge.statut),
                    borderColor: getStatusColor(conge.statut),
                    extendedProps: {
                        employee: `${conge.prenoms} ${conge.nom}`,
                        type: conge.type_conge,
                        statut: conge.statut,
                        jours: conge.nb_jours
                    }
                })),
                eventDidMount: function(info) {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'custom-tooltip';
                    tooltip.innerHTML = `
                        <strong>${info.event.extendedProps.employee}</strong><br>
                        Type: ${info.event.extendedProps.type}<br>
                        Du: ${formatDate(info.event.start)}<br>
                        Au: ${formatDate(info.event.end)}<br>
                        Durée: ${info.event.extendedProps.jours} jours<br>
                        Statut: ${formatStatus(info.event.extendedProps.statut)}
                    `;

                    info.el.addEventListener('mouseenter', function(e) {
                        document.body.appendChild(tooltip);
                        const rect = info.el.getBoundingClientRect();
                        tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
                        tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
                    });

                    info.el.addEventListener('mouseleave', function() {
                        document.body.removeChild(tooltip);
                    });
                }
            });

            calendar.render();
        });

        function getStatusColor(statut) {
            switch(statut) {
                case 'approuve': return '#28a745';
                case 'refuse': return '#dc3545';
                case 'standby': return '#ffc107';
                case 'annule': return '#6c757d';
                default: return '#6c757d';
            }
        }

        function formatDate(date) {
            return new Date(date).toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        function formatStatus(statut) {
            const statusMap = {
                'approuve': 'Approuvé',
                'refuse': 'Refusé',
                'standby': 'En attente',
                'annule': 'Annulé'
            };
            return statusMap[statut] || statut;
        }
    </script>
</body>
</html> 