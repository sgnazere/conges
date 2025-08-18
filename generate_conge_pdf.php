<?php
require_once('config.php');
require_once('functions.php');

// Inclure TCPDF
require_once('tcpdf/tcpdf.php');

// Récupérer l'ID de la demande de congé
$id_demande = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id_demande) {
    die("ID de demande non spécifié");
}

try {
    $db = getDBConnection();
    
    // Récupérer les informations de la demande
    $query = "SELECT 
        d.*, 
        e.nom as emp_nom, 
        e.prenoms as emp_prenoms,
        e.service as emp_service,
        t.libelle as type_conge,
        DATEDIFF(d.date_fin, d.date_debut) + 1 as nombre_jours
    FROM demandes_conges d
    JOIN employees e ON d.employee_id = e.id
    JOIN types_conges t ON d.type_conge_id = t.id
    WHERE d.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$id_demande]);
    $demande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$demande) {
        die("Demande non trouvée");
    }
    
    // Créer le PDF
    class MYPDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 15, 'DEMANDE DE CONGÉ', 0, true, 'C');
            $this->SetFont('helvetica', '', 12);
            $this->Cell(0, 10, 'GOAS - Gestion des Congés', 0, true, 'C');
            $this->Ln(10);
        }
        
        public function Footer() {
            $this->SetY(-25);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');
        }
    }
    
    // Initialiser le PDF
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Définir les informations du document
    $pdf->SetCreator('GOAS');
    $pdf->SetAuthor('GOAS System');
    $pdf->SetTitle('Demande de congé - ' . $demande['emp_nom'] . ' ' . $demande['emp_prenoms']);
    
    // Définir les marges
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Informations de l'employé
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Informations de l\'employé', 0, true, 'L');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Nom et prénoms:', 0, 0);
    $pdf->Cell(0, 7, $demande['emp_nom'] . ' ' . $demande['emp_prenoms'], 0, 1);
    $pdf->Cell(50, 7, 'Service:', 0, 0);
    $pdf->Cell(0, 7, $demande['emp_service'], 0, 1);
    
    // Informations du congé
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Détails du congé', 0, true, 'L');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Type de congé:', 0, 0);
    $pdf->Cell(0, 7, $demande['type_conge'], 0, 1);
    $pdf->Cell(50, 7, 'Date de début:', 0, 0);
    $pdf->Cell(0, 7, date('d/m/Y', strtotime($demande['date_debut'])), 0, 1);
    $pdf->Cell(50, 7, 'Date de fin:', 0, 0);
    $pdf->Cell(0, 7, date('d/m/Y', strtotime($demande['date_fin'])), 0, 1);
    $pdf->Cell(50, 7, 'Nombre de jours:', 0, 0);
    $pdf->Cell(0, 7, $demande['nombre_jours'] . ' jour(s)', 0, 1);
    
    if (!empty($demande['motif'])) {
        $pdf->Cell(50, 7, 'Motif:', 0, 0);
        $pdf->MultiCell(0, 7, $demande['motif'], 0, 'L');
    }
    
    // Signatures
    $pdf->Ln(20);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'Signatures', 0, true, 'L');
    
    // Tableau des signatures
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 10, 'L\'employé', 1, 0, 'C');
    $pdf->Cell(60, 10, 'Le superviseur', 1, 0, 'C');
    $pdf->Cell(60, 10, 'Le directeur', 1, 1, 'C');
    
    $pdf->Cell(60, 20, '', 1, 0, 'C');
    $pdf->Cell(60, 20, '', 1, 0, 'C');
    $pdf->Cell(60, 20, '', 1, 1, 'C');
    
    // Date d'émission
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 10, 'Document généré le ' . date('d/m/Y'), 0, true, 'R');
    
    // Générer le PDF
    $pdf->Output('demande_conge_' . $id_demande . '.pdf', 'I');
    
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
} 