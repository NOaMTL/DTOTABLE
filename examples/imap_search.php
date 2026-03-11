<?php

/**
 * Exemple de recherche d'emails avec IMAP
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Mail\ImapClient;

$host = 'imap.gmail.com';
$username = 'votre-email@gmail.com';
$password = 'votre-app-password';

try {
    $imap = new ImapClient($host, 993, true);
    $imap->connect($username, $password);
    echo "✅ Connecté\n\n";

    // 1. Rechercher les emails non lus
    echo "📧 Emails non lus:\n";
    $unread = $imap->search('UNSEEN');
    echo "Trouvés: " . count($unread) . " emails\n";
    
    foreach (array_slice($unread, 0, 3) as $email) {
        echo "  - {$email['subject']}\n";
    }
    echo "\n";

    // 2. Rechercher par expéditeur
    echo "📧 Emails de john@example.com:\n";
    $fromJohn = $imap->search('FROM "john@example.com"');
    echo "Trouvés: " . count($fromJohn) . " emails\n\n";

    // 3. Rechercher par sujet
    echo "📧 Emails avec 'Facture' dans le sujet:\n";
    $invoices = $imap->search('SUBJECT "Facture"');
    echo "Trouvés: " . count($invoices) . " emails\n\n";

    // 4. Rechercher depuis une date
    echo "📧 Emails depuis le 1er janvier 2024:\n";
    $recent = $imap->search('SINCE "1-Jan-2024"');
    echo "Trouvés: " . count($recent) . " emails\n\n";

    // 5. Recherche combinée
    echo "📧 Emails non lus de john@example.com:\n";
    $combined = $imap->search('FROM "john@example.com" UNSEEN');
    echo "Trouvés: " . count($combined) . " emails\n\n";

    // 6. Marquer comme lu
    if (!empty($unread)) {
        $firstUnread = $unread[0];
        echo "📝 Marquage de l'email #{$firstUnread['id']} comme lu...\n";
        $imap->markAsRead($firstUnread['id']);
        echo "✅ Marqué\n\n";
    }

    $imap->disconnect();
    echo "✅ Terminé\n";

} catch (Exception $e) {
    echo "❌ Erreur: {$e->getMessage()}\n";
    exit(1);
}
