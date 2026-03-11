<?php

/**
 * Exemple : Télécharger les pièces jointes avec auto-détection
 * 
 * Utilisation STANDALONE (sans Laravel)
 */

require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

$imap = new ImapClient('imap.gmail.com', 993, true);

try {
    // Connexion
    $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe');
    
    // Récupérer les 10 derniers emails
    $emails = $imap->getEmails(10);
    
    echo "=== TÉLÉCHARGEMENT DES PIÈCES JOINTES ===\n\n";
    
    $totalDownloaded = 0;
    $destinationPath = __DIR__ . '/attachments'; // Dossier local
    
    foreach ($emails as $email) {
        $attachmentCount = count($email['attachments'] ?? []);
        
        if ($attachmentCount === 0) {
            continue;
        }
        
        echo "Email #{$email['id']}: {$email['subject']}\n";
        echo "Pièces jointes: $attachmentCount\n";
        echo str_repeat('-', 80) . "\n";
        
        // Télécharger toutes les pièces jointes
        $downloaded = $imap->downloadAllAttachments(
            $email['id'],
            $destinationPath,
            keepOriginalName: true  // Garder noms originaux
        );
        
        foreach ($downloaded as $file) {
            if (isset($file['error'])) {
                echo "❌ {$file['original_name']}: {$file['error']}\n";
            } else {
                echo "✅ {$file['original_name']}\n";
                echo "   → {$file['saved_name']}\n";
                echo "   → Type: {$file['mime']} (.{$file['extension']})\n";
                echo "   → Taille: " . number_format($file['size'] / 1024, 2) . " KB\n";
                echo "   → Chemin: {$file['path']}\n";
                $totalDownloaded++;
            }
        }
        
        echo "\n";
    }
    
    echo str_repeat('=', 80) . "\n";
    echo "Total téléchargé: $totalDownloaded fichier(s)\n";
    echo "Dossier: $destinationPath\n";
    
    $imap->disconnect();
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
