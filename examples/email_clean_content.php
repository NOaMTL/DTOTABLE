<?php

/**
 * Exemple : Extraire le contenu propre d'un email
 * (sans signature, réponses citées, etc.)
 * 
 * Utilisation STANDALONE (sans Laravel)
 */

require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

$imap = new ImapClient('imap.gmail.com', 993, true);

try {
    // Connexion
    $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe');
    
    // Récupérer les 5 derniers emails
    $emails = $imap->getEmails(5);
    
    echo "=== EXTRACTION DE CONTENU PROPRE ===\n\n";
    
    foreach ($emails as $email) {
        echo "Email #{$email['id']}: {$email['subject']}\n";
        echo "De: {$email['from']}\n";
        echo "Date: {$email['date']}\n";
        echo str_repeat('-', 80) . "\n";
        
        // Extraire le contenu propre (texte)
        $clean = $imap->extractCleanBody($email['id'], false);
        
        echo "CONTENU PRINCIPAL:\n";
        echo $clean['content'] . "\n\n";
        
        if (!empty($clean['signature'])) {
            echo "SIGNATURE DÉTECTÉE:\n";
            echo $clean['signature'] . "\n\n";
        }
        
        if (!empty($clean['quoted'])) {
            echo "CONTENU CITÉ (réponse):\n";
            echo substr($clean['quoted'], 0, 200) . "...\n\n";
        }
        
        echo str_repeat('=', 80) . "\n\n";
    }
    
    // Exemple avec HTML
    echo "\n=== EXTRACTION HTML ===\n\n";
    $firstEmail = $emails[0];
    $cleanHtml = $imap->extractCleanBody($firstEmail['id'], true);
    
    echo "Contenu HTML propre:\n";
    echo substr($cleanHtml['content'], 0, 500) . "...\n";
    
    $imap->disconnect();
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
