<?php

/**
 * Exemple : Utilisation STANDALONE (sans Laravel ni framework)
 * 
 * Cette classe fonctionne en PHP pur, aucune dépendance externe requise.
 * Seule l'extension php-imap est nécessaire pour ImapClient.
 */

// Charger la classe directement
require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

// Configuration
$config = [
    'host' => 'imap.gmail.com',
    'port' => 993,
    'ssl' => true,
    'email' => 'votre.email@gmail.com',
    'password' => 'votre_mot_de_passe',
];

try {
    // Connexion
    $imap = new ImapClient($config['host'], $config['port'], $config['ssl']);
    $imap->connect($config['email'], $config['password']);
    
    echo "✅ Connecté à {$config['email']}\n\n";
    
    // Statistiques
    $total = $imap->getEmailCount();
    $unread = $imap->getUnreadCount();
    echo "Emails: $total total, $unread non lus\n\n";
    
    // Récupérer les 3 derniers emails
    $emails = $imap->getEmails(3);
    
    foreach ($emails as $email) {
        echo "=" . str_repeat("=", 79) . "\n";
        echo "Email #{$email['id']}\n";
        echo "De: {$email['from']}\n";
        echo "Sujet: {$email['subject']}\n";
        echo "Date: {$email['date']}\n";
        echo "-" . str_repeat("-", 79) . "\n";
        
        // Extraction contenu propre
        $clean = $imap->extractCleanBody($email['id'], false);
        $content = substr($clean['content'], 0, 200);
        echo "Contenu: $content...\n";
        
        // Pièces jointes
        if (!empty($email['attachments'])) {
            echo "\n📎 Pièces jointes:\n";
            foreach ($email['attachments'] as $att) {
                echo "  - {$att['filename']} ({$att['type']}, " . 
                     number_format($att['size'] / 1024, 2) . " KB)\n";
            }
            
            // Télécharger dans dossier local
            $attachmentDir = __DIR__ . '/downloads';
            if (!is_dir($attachmentDir)) {
                mkdir($attachmentDir, 0755, true);
            }
            
            $downloaded = $imap->downloadAllAttachments(
                $email['id'],
                $attachmentDir,
                keepOriginalName: true
            );
            
            echo "\n✅ Téléchargé " . count($downloaded) . " fichier(s) dans $attachmentDir\n";
        }
        
        echo "\n";
    }
    
    $imap->disconnect();
    echo "\n✅ Déconnecté\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
