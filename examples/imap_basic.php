<?php

/**
 * Exemple basique d'utilisation du client IMAP
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Mail\ImapClient;

// Configuration
$host = 'imap.gmail.com';
$port = 993;
$ssl = true;
$username = 'votre-email@gmail.com';
$password = 'votre-app-password'; // App Password Gmail

try {
    // Connexion
    echo "🔌 Connexion à {$host}...\n";
    $imap = new ImapClient($host, $port, $ssl);
    $imap->connect($username, $password);
    echo "✅ Connecté\n\n";

    // Statistiques
    $total = $imap->getEmailCount();
    $unread = $imap->getUnreadCount();
    
    echo "📊 Statistiques:\n";
    echo "  Total: {$total} emails\n";
    echo "  Non lus: {$unread} emails\n\n";

    // Récupérer les 5 derniers emails
    echo "📧 5 derniers emails:\n";
    echo str_repeat('=', 80) . "\n";
    
    $emails = $imap->getEmails(5);
    
    foreach ($emails as $email) {
        echo "\nID: {$email['id']}\n";
        echo "De: {$email['from'][0]['full']}\n";
        echo "À: {$email['to'][0]['full']}\n";
        echo "Sujet: {$email['subject']}\n";
        echo "Date: {$email['date']}\n";
        echo "Taille: " . round($email['size'] / 1024, 2) . " KB\n";
        echo "Lu: " . ($email['seen'] ? 'Oui' : 'Non') . "\n";
        echo "Pièces jointes: " . count($email['attachments']) . "\n";
        echo "Preview: " . substr(strip_tags($email['body_text']), 0, 100) . "...\n";
        echo str_repeat('-', 80) . "\n";
    }

    // Déconnexion
    echo "\n🔌 Déconnexion...\n";
    $imap->disconnect();
    echo "✅ Terminé\n";

} catch (Exception $e) {
    echo "❌ Erreur: {$e->getMessage()}\n";
    exit(1);
}
