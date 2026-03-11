<?php

/**
 * Exemple : Traitement avancé des emails
 * - Extraction de contenu propre
 * - Téléchargement des pièces jointes
 * - Filtrage par type de fichier
 * 
 * Utilisation STANDALONE (sans Laravel)
 */

require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

$imap = new ImapClient('imap.gmail.com', 993, true);

try {
    $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe');
    
    // Rechercher les emails non lus avec pièces jointes
    $emails = $imap->search('UNSEEN');
    
    echo "=== TRAITEMENT DES EMAILS NON LUS ===\n";
    echo "Trouvés: " . count($emails) . " email(s)\n\n";
    
    $storagePath = __DIR__; // Dossier local
    $stats = [
        'processed' => 0,
        'with_attachments' => 0,
        'pdf_downloaded' => 0,
        'images_downloaded' => 0,
        'other_downloaded' => 0,
    ];
    
    foreach ($emails as $emailId) {
        $email = $imap->getEmail($emailId);
        
        echo "Email #{$emailId}: {$email['subject']}\n";
        echo "De: {$email['from']}\n";
        echo "Date: {$email['date']}\n";
        echo str_repeat('-', 80) . "\n";
        
        // Extraire le contenu propre
        $clean = $imap->extractCleanBody($emailId, false);
        $content = $clean['content'];
        
        // Limiter à 200 caractères pour affichage
        if (strlen($content) > 200) {
            $content = substr($content, 0, 200) . '...';
        }
        
        echo "Contenu: $content\n";
        
        // Traiter les pièces jointes
        $attachments = $email['attachments'] ?? [];
        if (!empty($attachments)) {
            $stats['with_attachments']++;
            
            echo "\nPièces jointes:\n";
            
            // Télécharger selon le type
            $downloaded = $imap->downloadAllAttachments(
                $emailId,
                $storagePath . '/attachments',
                keepOriginalName: false  // Noms uniques
            );
            
            foreach ($downloaded as $file) {
                if (isset($file['error'])) {
                    echo "  ❌ {$file['original_name']}: {$file['error']}\n";
                    continue;
                }
                
                echo "  ✅ {$file['original_name']} → {$file['saved_name']}\n";
                echo "     Type: {$file['mime']} | Taille: " . 
                     number_format($file['size'] / 1024, 2) . " KB\n";
                
                // Catégoriser
                $ext = strtolower($file['extension']);
                if ($ext === 'pdf') {
                    $stats['pdf_downloaded']++;
                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $stats['images_downloaded']++;
                } else {
                    $stats['other_downloaded']++;
                }
            }
        }
        
        // Marquer comme lu après traitement
        $imap->markAsRead($emailId);
        
        $stats['processed']++;
        echo "\n" . str_repeat('=', 80) . "\n\n";
    }
    
    // Statistiques finales
    echo "\n=== STATISTIQUES ===\n";
    echo "Emails traités: {$stats['processed']}\n";
    echo "Avec pièces jointes: {$stats['with_attachments']}\n";
    echo "PDF téléchargés: {$stats['pdf_downloaded']}\n";
    echo "Images téléchargées: {$stats['images_downloaded']}\n";
    echo "Autres fichiers: {$stats['other_downloaded']}\n";
    echo "Total fichiers: " . 
         ($stats['pdf_downloaded'] + $stats['images_downloaded'] + $stats['other_downloaded']) . "\n";
    
    $imap->disconnect();
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
