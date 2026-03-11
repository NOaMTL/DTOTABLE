<?php

/**
 * Exemple : Recherche avancée d'emails
 * Utilisation STANDALONE (sans Laravel)
 */

require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

$imap = new ImapClient('imap.gmail.com', 993, true);

try {
    $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe');
    
    echo "=== RECHERCHES AVANCÉES D'EMAILS ===\n\n";
    
    // 1. Emails non lus depuis une date
    echo "1️⃣ Emails non lus depuis le 1er mars 2026:\n";
    $unreadRecent = $imap->search('UNSEEN SINCE "1 March 2026"');
    echo "   Trouvés: " . count($unreadRecent) . " email(s)\n\n";
    
    // 2. Emails d'un expéditeur spécifique
    echo "2️⃣ Emails de 'boss@company.com':\n";
    $fromBoss = $imap->search('FROM "boss@company.com"');
    echo "   Trouvés: " . count($fromBoss) . " email(s)\n\n";
    
    // 3. Emails avec mot-clé dans le sujet
    echo "3️⃣ Emails avec 'facture' dans le sujet:\n";
    $invoices = $imap->search('SUBJECT "facture"');
    echo "   Trouvés: " . count($invoices) . " email(s)\n";
    if (!empty($invoices)) {
        foreach (array_slice($invoices, 0, 3) as $id) {
            $email = $imap->getEmail($id);
            echo "   - [{$email['date']}] {$email['subject']}\n";
        }
    }
    echo "\n";
    
    // 4. Emails avec pièces jointes (recherche indirecte)
    echo "4️⃣ Emails avec pièces jointes:\n";
    $recent = $imap->getEmails(50);
    $withAttachments = array_filter($recent, fn($e) => !empty($e['attachments']));
    echo "   Trouvés: " . count($withAttachments) . " email(s) sur 50 récents\n";
    foreach (array_slice($withAttachments, 0, 3) as $email) {
        $attCount = count($email['attachments']);
        echo "   - {$email['subject']} ($attCount pièce(s) jointe(s))\n";
    }
    echo "\n";
    
    // 5. Emails entre deux dates
    echo "5️⃣ Emails entre le 1er et 10 mars 2026:\n";
    $dateRange = $imap->search('SINCE "1 March 2026" BEFORE "10 March 2026"');
    echo "   Trouvés: " . count($dateRange) . " email(s)\n\n";
    
    // 6. Emails importants (flagged)
    echo "6️⃣ Emails marqués comme importants:\n";
    $flagged = $imap->search('FLAGGED');
    echo "   Trouvés: " . count($flagged) . " email(s)\n\n";
    
    // 7. Emails > 100KB
    echo "7️⃣ Emails volumineux (> 100KB):\n";
    $large = $imap->search('LARGER 102400');
    echo "   Trouvés: " . count($large) . " email(s)\n";
    if (!empty($large)) {
        foreach (array_slice($large, 0, 3) as $id) {
            $email = $imap->getEmail($id);
            $sizeMB = number_format($email['size'] / 1024 / 1024, 2);
            echo "   - {$email['subject']} ({$sizeMB} MB)\n";
        }
    }
    echo "\n";
    
    // 8. Combinaison de critères
    echo "8️⃣ Emails non lus de 'boss@company.com' avec 'urgent' dans le sujet:\n";
    $urgentFromBoss = $imap->search('UNSEEN FROM "boss@company.com" SUBJECT "urgent"');
    echo "   Trouvés: " . count($urgentFromBoss) . " email(s)\n";
    if (!empty($urgentFromBoss)) {
        foreach ($urgentFromBoss as $id) {
            $email = $imap->getEmail($id);
            echo "   🚨 [{$email['date']}] {$email['subject']}\n";
        }
    }
    echo "\n";
    
    // 9. Emails à un destinataire spécifique
    echo "9️⃣ Emails envoyés à 'projet@company.com':\n";
    $toProject = $imap->search('TO "projet@company.com"');
    echo "   Trouvés: " . count($toProject) . " email(s)\n\n";
    
    // 10. Tous les emails sauf ceux lus
    echo "🔟 Statistiques globales:\n";
    $total = $imap->getEmailCount();
    $unread = $imap->getUnreadCount();
    $read = $total - $unread;
    echo "   Total: $total emails\n";
    echo "   Lus: $read emails (" . round($read / $total * 100, 1) . "%)\n";
    echo "   Non lus: $unread emails (" . round($unread / $total * 100, 1) . "%)\n";
    
    $imap->disconnect();
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
