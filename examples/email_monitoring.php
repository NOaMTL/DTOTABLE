<?php

/**
 * Exemple : Monitoring et alertes emails
 * Script à exécuter en cron pour surveillance continue
 * Utilisation STANDALONE (sans Laravel)
 */

require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

// Configuration
$config = [
    'check_interval' => 300, // 5 minutes
    'alert_keywords' => ['urgent', 'important', 'critique', 'erreur'],
    'alert_senders' => ['boss@company.com', 'client@vip.com'],
    'log_file' => __DIR__ . '/monitoring.log',
    'alert_file' => __DIR__ . '/alerts.json',
];

$imap = new ImapClient('imap.gmail.com', 993, true);

try {
    $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe');
    
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] === MONITORING EMAILS ===\n\n";
    
    // 1. Vérifier les emails non lus
    $unreadCount = $imap->getUnreadCount();
    echo "📧 Emails non lus: $unreadCount\n\n";
    
    if ($unreadCount > 50) {
        echo "⚠️ ALERTE: Plus de 50 emails non lus!\n";
        logAlert($config['alert_file'], [
            'type' => 'high_unread_count',
            'count' => $unreadCount,
            'timestamp' => $timestamp,
        ]);
    }
    
    // 2. Chercher les emails urgents non lus
    echo "🔍 Recherche d'emails urgents...\n";
    $urgentEmails = [];
    
    foreach ($config['alert_keywords'] as $keyword) {
        $ids = $imap->search("UNSEEN SUBJECT \"$keyword\"");
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $urgentEmails[$id] = $imap->getEmail($id);
            }
        }
    }
    
    if (!empty($urgentEmails)) {
        echo "🚨 EMAILS URGENTS DÉTECTÉS: " . count($urgentEmails) . "\n\n";
        
        foreach ($urgentEmails as $email) {
            echo "   Email #{$email['id']}\n";
            echo "   De: {$email['from']}\n";
            echo "   Sujet: {$email['subject']}\n";
            echo "   Date: {$email['date']}\n";
            
            // Extraire contenu
            $clean = $imap->extractCleanBody($email['id'], false);
            $preview = substr($clean['content'], 0, 150);
            echo "   Aperçu: $preview...\n";
            echo "   " . str_repeat('-', 76) . "\n";
            
            // Logger l'alerte
            logAlert($config['alert_file'], [
                'type' => 'urgent_email',
                'email_id' => $email['id'],
                'from' => $email['from'],
                'subject' => $email['subject'],
                'timestamp' => $timestamp,
            ]);
        }
        echo "\n";
    } else {
        echo "   ✓ Aucun email urgent\n\n";
    }
    
    // 3. Vérifier les emails des expéditeurs VIP
    echo "👑 Vérification des expéditeurs VIP...\n";
    $vipEmails = [];
    
    foreach ($config['alert_senders'] as $sender) {
        $ids = $imap->search("UNSEEN FROM \"$sender\"");
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $vipEmails[$id] = $imap->getEmail($id);
            }
        }
    }
    
    if (!empty($vipEmails)) {
        echo "⭐ EMAILS VIP NON LUS: " . count($vipEmails) . "\n\n";
        
        foreach ($vipEmails as $email) {
            echo "   De: {$email['from']}\n";
            echo "   Sujet: {$email['subject']}\n";
            echo "   Date: {$email['date']}\n";
            
            if (!empty($email['attachments'])) {
                echo "   📎 " . count($email['attachments']) . " pièce(s) jointe(s)\n";
            }
            echo "\n";
            
            logAlert($config['alert_file'], [
                'type' => 'vip_email',
                'email_id' => $email['id'],
                'from' => $email['from'],
                'subject' => $email['subject'],
                'timestamp' => $timestamp,
            ]);
        }
    } else {
        echo "   ✓ Aucun email VIP non lu\n\n";
    }
    
    // 4. Statistiques horaires
    echo "📊 Statistiques des dernières 24h...\n";
    $yesterday = date('j F Y', strtotime('-1 day'));
    $last24h = $imap->search("SINCE \"$yesterday\"");
    
    echo "   Emails reçus: " . count($last24h) . "\n";
    
    if (!empty($last24h)) {
        $withAttachments = 0;
        $totalSize = 0;
        
        foreach ($last24h as $id) {
            $email = $imap->getEmail($id);
            $totalSize += $email['size'];
            if (!empty($email['attachments'])) {
                $withAttachments++;
            }
        }
        
        echo "   Avec pièces jointes: $withAttachments\n";
        echo "   Volume total: " . number_format($totalSize / 1024 / 1024, 2) . " MB\n";
        echo "   Moyenne: " . number_format($totalSize / count($last24h) / 1024, 2) . " KB/email\n";
    }
    echo "\n";
    
    // 5. Vérifier les emails de grande taille
    echo "💾 Vérification des emails volumineux...\n";
    $largeEmails = $imap->search('LARGER 5242880'); // > 5MB
    
    if (!empty($largeEmails)) {
        echo "   ⚠️ " . count($largeEmails) . " email(s) > 5MB détecté(s)\n";
        
        foreach (array_slice($largeEmails, 0, 5) as $id) {
            $email = $imap->getEmail($id);
            $sizeMB = number_format($email['size'] / 1024 / 1024, 2);
            echo "   - [{$sizeMB} MB] {$email['subject']}\n";
        }
        echo "\n";
    } else {
        echo "   ✓ Pas d'email volumineux\n\n";
    }
    
    // 6. Rapport de santé
    echo str_repeat('=', 80) . "\n";
    echo "RAPPORT DE SANTÉ\n";
    echo str_repeat('=', 80) . "\n";
    
    $totalEmails = $imap->getEmailCount();
    $unreadPercent = $totalEmails > 0 ? round($unreadCount / $totalEmails * 100, 1) : 0;
    
    $health = 'OK';
    if ($unreadCount > 100) {
        $health = 'CRITIQUE';
    } elseif ($unreadCount > 50) {
        $health = 'ATTENTION';
    }
    
    echo "État: $health\n";
    echo "Total emails: $totalEmails\n";
    echo "Non lus: $unreadCount ($unreadPercent%)\n";
    echo "Emails urgents: " . count($urgentEmails) . "\n";
    echo "Emails VIP: " . count($vipEmails) . "\n";
    echo "Timestamp: $timestamp\n";
    
    // Logger le monitoring
    $logEntry = "[$timestamp] Health=$health | Total=$totalEmails | Unread=$unreadCount | " .
                "Urgent=" . count($urgentEmails) . " | VIP=" . count($vipEmails) . "\n";
    file_put_contents($config['log_file'], $logEntry, FILE_APPEND);
    
    echo "\n📄 Log sauvegardé: {$config['log_file']}\n";
    
    $imap->disconnect();
    
    echo "\n✅ Monitoring terminé\n";
    
} catch (Exception $e) {
    $error = "[$timestamp] ERREUR: " . $e->getMessage() . "\n";
    file_put_contents($config['log_file'], $error, FILE_APPEND);
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

/**
 * Logger une alerte
 */
function logAlert(string $file, array $alert): void
{
    $alerts = [];
    if (file_exists($file)) {
        $alerts = json_decode(file_get_contents($file), true) ?: [];
    }
    
    $alerts[] = $alert;
    
    // Garder uniquement les 100 dernières alertes
    if (count($alerts) > 100) {
        $alerts = array_slice($alerts, -100);
    }
    
    file_put_contents($file, json_encode($alerts, JSON_PRETTY_PRINT));
}
