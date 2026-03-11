<?php

/**
 * Exemple basique d'utilisation du client POP3
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Mail\Pop3Client;

$host = 'pop.gmail.com';
$username = 'votre-email@gmail.com';
$password = 'votre-app-password';

try {
    echo "🔌 Connexion à {$host}...\n";
    $pop = new Pop3Client($host, 995, true);
    $pop->connect($username, $password);
    echo "✅ Connecté\n\n";

    // Nombre d'emails
    $count = $pop->getEmailCount();
    echo "📊 Total: {$count} emails\n\n";

    // Liste des emails
    echo "📋 Liste des emails:\n";
    $list = $pop->getEmailList();
    
    foreach ($list as $item) {
        $sizeKB = round($item['size'] / 1024, 2);
        echo "  ID {$item['id']}: {$sizeKB} KB\n";
    }
    echo "\n";

    // Récupérer les 3 derniers emails
    echo "📧 3 derniers emails:\n";
    echo str_repeat('=', 80) . "\n";
    
    $emails = $pop->getEmails(3);
    
    foreach ($emails as $email) {
        echo "\nID: {$email['id']}\n";
        echo "De: {$email['from']}\n";
        echo "À: {$email['to']}\n";
        echo "Sujet: {$email['subject']}\n";
        echo "Date: {$email['date']}\n";
        echo "Preview: " . substr($email['body'], 0, 100) . "...\n";
        echo str_repeat('-', 80) . "\n";
    }

    // Déconnexion
    echo "\n🔌 Déconnexion...\n";
    $pop->disconnect();
    echo "✅ Terminé\n";

} catch (Exception $e) {
    echo "❌ Erreur: {$e->getMessage()}\n";
    exit(1);
}
