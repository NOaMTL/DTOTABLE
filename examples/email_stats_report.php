<?php

/**
 * Exemple : Génération de statistiques et rapports détaillés
 * Utilisation STANDALONE (sans Laravel)
 */

require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

$imap = new ImapClient('imap.gmail.com', 993, true);

try {
    $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe');
    
    echo "=== RAPPORT DE STATISTIQUES EMAILS ===\n";
    echo "Généré le: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Récupérer les 200 derniers emails pour analyse
    echo "📊 Analyse en cours (200 derniers emails)...\n\n";
    $emails = $imap->getEmails(200);
    
    if (empty($emails)) {
        echo "Aucun email à analyser\n";
        exit;
    }
    
    // 1. Statistiques générales
    echo "=" . str_repeat('=', 79) . "\n";
    echo "1. STATISTIQUES GÉNÉRALES\n";
    echo "=" . str_repeat('=', 79) . "\n";
    
    $totalCount = $imap->getEmailCount();
    $unreadCount = $imap->getUnreadCount();
    $analyzedCount = count($emails);
    
    echo "Total emails dans la boîte: $totalCount\n";
    echo "Emails non lus: $unreadCount (" . round($unreadCount / $totalCount * 100, 1) . "%)\n";
    echo "Emails analysés: $analyzedCount\n\n";
    
    // 2. Analyse par expéditeur
    echo "=" . str_repeat('=', 79) . "\n";
    echo "2. TOP 10 EXPÉDITEURS\n";
    echo "=" . str_repeat('=', 79) . "\n";
    
    $senders = [];
    foreach ($emails as $email) {
        $from = $email['from'];
        $senders[$from] = ($senders[$from] ?? 0) + 1;
    }
    arsort($senders);
    
    $topSenders = array_slice($senders, 0, 10, true);
    foreach ($topSenders as $sender => $count) {
        $percent = round($count / $analyzedCount * 100, 1);
        echo sprintf("%-50s %3d emails (%5.1f%%)\n", substr($sender, 0, 50), $count, $percent);
    }
    echo "\n";
    
    // 3. Analyse par période
    echo "=" . str_repeat('=', 79) . "\n";
    echo "3. RÉPARTITION TEMPORELLE\n";
    echo "=" . str_repeat('=', 79) . "\n";
    
    $byDay = [];
    $byHour = array_fill(0, 24, 0);
    $byDayOfWeek = ['Lundi' => 0, 'Mardi' => 0, 'Mercredi' => 0, 'Jeudi' => 0, 
                    'Vendredi' => 0, 'Samedi' => 0, 'Dimanche' => 0];
    
    $dayNames = ['Sunday' => 'Dimanche', 'Monday' => 'Lundi', 'Tuesday' => 'Mardi',
                 'Wednesday' => 'Mercredi', 'Thursday' => 'Jeudi', 'Friday' => 'Vendredi',
                 'Saturday' => 'Samedi'];
    
    foreach ($emails as $email) {
        $timestamp = strtotime($email['date']);
        $day = date('Y-m-d', $timestamp);
        $hour = (int)date('H', $timestamp);
        $dayOfWeek = $dayNames[date('l', $timestamp)];
        
        $byDay[$day] = ($byDay[$day] ?? 0) + 1;
        $byHour[$hour]++;
        $byDayOfWeek[$dayOfWeek]++;
    }
    
    echo "\nPar jour de la semaine:\n";
    foreach ($byDayOfWeek as $day => $count) {
        $bar = str_repeat('█', (int)($count / $analyzedCount * 50));
        echo sprintf("%-10s %3d emails %s\n", $day, $count, $bar);
    }
    
    echo "\nPar heure de la journée (top 5):\n";
    arsort($byHour);
    $topHours = array_slice($byHour, 0, 5, true);
    foreach ($topHours as $hour => $count) {
        $bar = str_repeat('█', (int)($count / $analyzedCount * 50));
        echo sprintf("%02d:00 - %02d:59  %3d emails %s\n", $hour, $hour, $count, $bar);
    }
    echo "\n";
    
    // 4. Analyse des pièces jointes
    echo "=" . str_repeat('=', 79) . "\n";
    echo "4. PIÈCES JOINTES\n";
    echo "=" . str_repeat('=', 79) . "\n";
    
    $withAttachments = 0;
    $totalAttachments = 0;
    $attachmentTypes = [];
    $totalAttachmentSize = 0;
    
    foreach ($emails as $email) {
        if (!empty($email['attachments'])) {
            $withAttachments++;
            $totalAttachments += count($email['attachments']);
            
            foreach ($email['attachments'] as $att) {
                $type = strtoupper($att['type']);
                $attachmentTypes[$type] = ($attachmentTypes[$type] ?? 0) + 1;
                $totalAttachmentSize += $att['size'] ?? 0;
            }
        }
    }
    
    echo "Emails avec pièces jointes: $withAttachments (" . 
         round($withAttachments / $analyzedCount * 100, 1) . "%)\n";
    echo "Total pièces jointes: $totalAttachments\n";
    echo "Taille totale: " . formatSize($totalAttachmentSize) . "\n";
    
    if ($totalAttachments > 0) {
        echo "Taille moyenne: " . formatSize($totalAttachmentSize / $totalAttachments) . "\n\n";
        
        echo "Types de fichiers:\n";
        arsort($attachmentTypes);
        foreach (array_slice($attachmentTypes, 0, 10, true) as $type => $count) {
            $percent = round($count / $totalAttachments * 100, 1);
            echo sprintf("  %-15s %3d (%5.1f%%)\n", $type, $count, $percent);
        }
    }
    echo "\n";
    
    // 5. Analyse de taille des emails
    echo "=" . str_repeat('=', 79) . "\n";
    echo "5. TAILLE DES EMAILS\n";
    echo "=" . str_repeat('=', 79) . "\n";
    
    $sizes = array_column($emails, 'size');
    $totalSize = array_sum($sizes);
    $avgSize = $totalSize / count($sizes);
    $maxSize = max($sizes);
    $minSize = min($sizes);
    
    echo "Taille totale: " . formatSize($totalSize) . "\n";
    echo "Taille moyenne: " . formatSize($avgSize) . "\n";
    echo "Plus grand: " . formatSize($maxSize) . "\n";
    echo "Plus petit: " . formatSize($minSize) . "\n\n";
    
    // Répartition par tranches
    $ranges = [
        '< 10 KB' => 0,
        '10-50 KB' => 0,
        '50-100 KB' => 0,
        '100-500 KB' => 0,
        '500 KB - 1 MB' => 0,
        '> 1 MB' => 0,
    ];
    
    foreach ($sizes as $size) {
        $kb = $size / 1024;
        if ($kb < 10) $ranges['< 10 KB']++;
        elseif ($kb < 50) $ranges['10-50 KB']++;
        elseif ($kb < 100) $ranges['50-100 KB']++;
        elseif ($kb < 500) $ranges['100-500 KB']++;
        elseif ($kb < 1024) $ranges['500 KB - 1 MB']++;
        else $ranges['> 1 MB']++;
    }
    
    echo "Répartition:\n";
    foreach ($ranges as $range => $count) {
        $percent = round($count / $analyzedCount * 100, 1);
        $bar = str_repeat('█', (int)($percent / 2));
        echo sprintf("  %-15s %3d emails (%5.1f%%) %s\n", $range, $count, $percent, $bar);
    }
    echo "\n";
    
    // 6. Emails les plus récents non lus
    echo "=" . str_repeat('=', 79) . "\n";
    echo "6. DERNIERS EMAILS NON LUS\n";
    echo "=" . str_repeat('=', 79) . "\n";
    
    $unread = array_filter($emails, fn($e) => !$e['seen']);
    usort($unread, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
    
    if (empty($unread)) {
        echo "Aucun email non lu\n\n";
    } else {
        foreach (array_slice($unread, 0, 10) as $email) {
            $date = date('Y-m-d H:i', strtotime($email['date']));
            $subject = substr($email['subject'], 0, 50);
            $from = substr($email['from'], 0, 30);
            echo "[$date] $from\n";
            echo "  → $subject\n";
        }
        echo "\n";
    }
    
    // Sauvegarder le rapport
    $reportFile = __DIR__ . '/email_report_' . date('Y-m-d_H-i-s') . '.txt';
    ob_start();
    echo "=== RAPPORT DE STATISTIQUES EMAILS ===\n";
    echo "Généré le: " . date('Y-m-d H:i:s') . "\n\n";
    echo "Total analysé: $analyzedCount emails\n";
    echo "Avec pièces jointes: $withAttachments\n";
    echo "Taille totale: " . formatSize($totalSize) . "\n";
    $reportContent = ob_get_clean();
    file_put_contents($reportFile, $reportContent);
    
    echo "✅ Rapport terminé\n";
    
    $imap->disconnect();
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

/**
 * Formater une taille en bytes
 */
function formatSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, 2) . ' ' . $units[$i];
}
