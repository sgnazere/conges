<?php
// URL de téléchargement de TCPDF
$tcpdf_url = 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.2.zip';
$zip_file = 'tcpdf.zip';
$extract_path = './';

// Télécharger TCPDF
if (file_put_contents($zip_file, file_get_contents($tcpdf_url))) {
    echo "TCPDF téléchargé avec succès.\n";
    
    // Créer l'objet ZipArchive
    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE) {
        // Extraire le contenu
        $zip->extractTo($extract_path);
        $zip->close();
        echo "TCPDF extrait avec succès.\n";
        
        // Renommer le dossier
        rename('TCPDF-6.6.2', 'tcpdf');
        
        // Supprimer le fichier zip
        unlink($zip_file);
        
        echo "Installation terminée avec succès.\n";
    } else {
        echo "Erreur lors de l'extraction de TCPDF.\n";
    }
} else {
    echo "Erreur lors du téléchargement de TCPDF.\n";
} 