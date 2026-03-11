<?php

/**
 * Exemple : Gestion de plusieurs dossiers IMAP
 * Utilisation STANDALONE (sans Laravel)
 */

require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

$imap = new ImapClient('imap.gmail.com', 993, true);

try {
    // Connexion initiale à INBOX
    $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe', 'INBOX');
    
    echo "=== GESTION DES DOSSIERS IMAP ===\n\n";
    
    // 1. Lister tous les dossiers
    echo "📁 Dossiers disponibles:\n";
    $folders = $imap->listFolders();
    foreach ($folders as $folder) {
        echo "   - $folder\n";
    }
    echo "\n";
    
    // 2. Analyser plusieurs dossiers
    $foldersToCheck = ['INBOX', 'Sent', 'Drafts', 'Spam'];
    $stats = [];
    
    echo "=== ANALYSE DES DOSSIERS ===\n\n";
    
    foreach ($foldersToCheck as $folderName) {
        try {
            // Reconnecter au dossier spécifique
            $imap->disconnect();
            $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe', $folderName);
            
            $total = $imap->getEmailCount();
            $unread = $imap->getUnreadCount();
            
            $stats[$folderName] = [
                'total' => $total,
                'unread' => $unread,
                'read' => $total - $unread,
            ];
            
            echo "📂 $folderName:\n";
            echo "   Total: $total email(s)\n";
            echo "   Non lus: $unread email(s)\n";
            echo "   Lus: " . ($total - $unread) . " email(s)\n";
            
            // Afficher les 3 derniers emails du dossier
            if ($total > 0) {
                echo "   Derniers emails:\n";
                $recent = $imap->getEmails(3);
                foreach ($recent as $email) {
                    $status = $email['seen'] ? '✓' : '✗';
                    echo "   $status [{$email['date']}] {$email['subject']}\n";
                }
            }
            echo "\n";
            
        } catch (Exception $e) {
            echo "   ⚠️ Impossible d'accéder à $folderName: {$e->getMessage()}\n\n";
        }
    }
    
    // 3. Recherche dans tous les dossiers
    echo "=== RECHERCHE GLOBALE ===\n";
    echo "Recherche d'emails avec 'facture' dans tous les dossiers...\n\n";
    
    $globalResults = [];
    
    foreach ($foldersToCheck as $folderName) {
        try {
            $imap->disconnect();
            $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe', $folderName);
            
            $results = $imap->search('SUBJECT "facture"');
            
            if (!empty($results)) {
                $globalResults[$folderName] = $results;
                echo "📂 $folderName: " . count($results) . " résultat(s)\n";
                
                // Afficher les sujets trouvés
                foreach (array_slice($results, 0, 2) as $id) {
                    $email = $imap->getEmail($id);
                    echo "   - {$email['subject']}\n";
                }
            }
            
        } catch (Exception $e) {
            // Ignorer les dossiers inaccessibles
        }
    }
    
    $totalFound = array_sum(array_map('count', $globalResults));
    echo "\n✅ Total trouvé: $totalFound email(s) dans " . count($globalResults) . " dossier(s)\n\n";
    
    // 4. Statistiques globales
    echo "=== STATISTIQUES GLOBALES ===\n";
    $totalEmails = array_sum(array_column($stats, 'total'));
    $totalUnread = array_sum(array_column($stats, 'unread'));
    $totalRead = array_sum(array_column($stats, 'read'));
    
    echo "Total emails analysés: $totalEmails\n";
    echo "Total non lus: $totalUnread (" . round($totalUnread / $totalEmails * 100, 1) . "%)\n";
    echo "Total lus: $totalRead (" . round($totalRead / $totalEmails * 100, 1) . "%)\n\n";
    
    // Répartition par dossier
    echo "Répartition:\n";
    foreach ($stats as $folder => $stat) {
        $percent = $totalEmails > 0 ? round($stat['total'] / $totalEmails * 100, 1) : 0;
        echo "   $folder: {$stat['total']} emails ($percent%)\n";
    }
    
    $imap->disconnect();
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
