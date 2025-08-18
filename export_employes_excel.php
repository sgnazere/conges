<?php
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Connexion à la base de données
$db = getDBConnection();

// Récupérer les employés
$query = $db->query("SELECT nom, prenoms, projet, poste, telephone, date_embauche, numero_cnps FROM employees ORDER BY nom, prenoms");
$employes = $query->fetchAll(PDO::FETCH_ASSOC);

// Création du fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Entêtes
$headers = ['Nom', 'Prénoms', 'Projet', 'Poste', 'Numéro téléphone', "Date d'embauche", 'Numéro CNPS'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col.'1', $header);
    $col++;
}

// Données
$row = 2;
foreach ($employes as $employe) {
    $sheet->setCellValue('A'.$row, $employe['nom']);
    $sheet->setCellValue('B'.$row, $employe['prenoms']);
    $sheet->setCellValue('C'.$row, $employe['projet']);
    $sheet->setCellValue('D'.$row, $employe['poste']);
    $sheet->setCellValue('E'.$row, $employe['telephone']);
    $sheet->setCellValue('F'.$row, $employe['date_embauche']);
    $sheet->setCellValue('G'.$row, $employe['numero_cnps']);
    $row++;
}

// Style basique (optionnel)
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $sheet->getStyle($col.'1')->getFont()->setBold(true);
}

// Téléchargement du fichier
$filename = 'employes_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 