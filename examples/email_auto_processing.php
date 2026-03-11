<?php

/**
 * Exemple : Traitement automatique des emails avec règles
 * Utilisation STANDALONE (sans Laravel)
 */

require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

// Configuration des règles de traitement
$rules = [
    'factures' => [
        'criteria' => 'UNSEEN SUBJECT "facture"',
        'action' => 'download_attachments',
        'folder' => __DIR__ . '/factures',
    ],
    'urgent' => [
        'criteria' => 'UNSEEN SUBJECT "urgent"',
        'action' => 'mark_flagged',
    ],
    'newsletters' => [
        'criteria' => 'FROM "newsletter@" SUBJECT "newsletter"',
        'action' => 'mark_read',
    ],
    'spam_suspects' => [
        'criteria' => 'SUBJECT "viagra" SUBJECT "casino"',
        'action' => 'report',
    ],
];

$imap = new ImapClient('imap.gmail.com', 993, true);

try {
    $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe');
    
    echo "=== TRAITEMENT AUTOMATIQUE DES EMAILS ===\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    $stats = [
        'processed' => 0,
        'attachments_downloaded' => 0,
        'marked_read' => 0,
        'marked_flagged' => 0,
        'reported' => 0,
    ];
    
    // Traiter chaque règle
    foreach ($rules as $ruleName => $rule) {
        echo "📋 Règle: $ruleName\n";
        echo "   Critères: {$rule['criteria']}\n";
        
        try {
            $emailIds = $imap->search($rule['criteria']);
            $count = count($emailIds);
            
            echo "   Emails trouvés: $count\n";
            
            if ($count === 0) {
                echo "   ✓ Aucun email à traiter\n\n";
                continue;
            }
            
            // Appliquer l'action selon la règle
            foreach ($emailIds as $emailId) {
                $email = $imap->getEmail($emailId);
                $stats['processed']++;
                
                switch ($rule['action']) {
                    case 'download_attachments':
                        if (!empty($email['attachments'])) {
                            $folder = $rule['folder'] ?? __DIR__ . '/downloads';
                            if (!is_dir($folder)) {
                                mkdir($folder, 0755, true);
                            }
                            
                            $files = $imap->downloadAllAttachments($emailId, $folder, true);
                            $stats['attachments_downloaded'] += count($files);
                            
                            echo "   📎 Email #{$emailId}: " . count($files) . " pièce(s) téléchargée(s)\n";
                            echo "      Sujet: {$email['subject']}\n";
                        }
                        $imap->markAsRead($emailId);
                        break;
                        
                    case 'mark_read':
                        $imap->markAsRead($emailId);
                        $stats['marked_read']++;
                        echo "   ✓ Email #{$emailId} marqué comme lu\n";
                        break;
                        
                    case 'mark_flagged':
                        $imap->markAsRead($emailId);
                        $stats['marked_flagged']++;
                        echo "   🚩 Email #{$emailId} marqué comme important\n";
                        echo "      De: {$email['from']}\n";
                        echo "      Sujet: {$email['subject']}\n";
                        break;
                        
                    case 'report':
                        $stats['reported']++;
                        echo "   ⚠️ Email suspect #{$emailId}\n";
                        echo "      De: {$email['from']}\n";
                        echo "      Sujet: {$email['subject']}\n";
                        // Ici, vous pourriez logger dans un fichier ou envoyer une alerte
                        break;
                }
            }
            
            echo "   ✅ Règle appliquée à $count email(s)\n\n";
            
        } catch (Exception $e) {
            echo "   ❌ Erreur lors du traitement: {$e->getMessage()}\n\n";
        }
    }
    
    // Rapport final
    echo str_repeat('=', 80) . "\n";
    echo "RAPPORT FINAL\n";
    echo str_repeat('=', 80) . "\n";
    echo "Emails traités: {$stats['processed']}\n";
    echo "Pièces jointes téléchargées: {$stats['attachments_downloaded']}\n";
    echo "Emails marqués comme lus: {$stats['marked_read']}\n";
    echo "Emails marqués importants: {$stats['marked_flagged']}\n";
    echo "Emails suspects signalés: {$stats['reported']}\n";
    
    // Sauvegarder le rapport
    $reportFile = __DIR__ . '/email_processing_' . date('Y-m-d_H-i-s') . '.log';
    file_put_contents($reportFile, json_encode([
        'date' => date('Y-m-d H:i:s'),
        'stats' => $stats,
        'rules' => array_keys($rules),
    ], JSON_PRETTY_PRINT));
    
    echo "\n📄 Rapport sauvegardé: $reportFile\n";
    
    $imap->disconnect();
    
} catch (Exception $e) {
    echo "❌ Erreur fatale: " . $e->getMessage() . "\n";
}
