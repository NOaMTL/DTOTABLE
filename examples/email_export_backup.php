<?php

/**
 * Exemple : Export et sauvegarde d'emails
 * Utilisation STANDALONE (sans Laravel)
 */

require __DIR__ . '/../app/Mail/ImapClient.php';

use App\Mail\ImapClient;

$imap = new ImapClient('imap.gmail.com', 993, true);

try {
    $imap->connect('votre.email@gmail.com', 'votre_mot_de_passe');
    
    echo "=== EXPORT ET SAUVEGARDE D'EMAILS ===\n\n";
    
    // Configuration
    $exportDir = __DIR__ . '/email_backups';
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }
    
    $backupDate = date('Y-m-d_H-i-s');
    
    // 1. Exporter les emails importants (flagged)
    echo "1️⃣ Export des emails importants...\n";
    $flaggedIds = $imap->search('FLAGGED');
    echo "   Trouvés: " . count($flaggedIds) . " email(s)\n";
    
    if (!empty($flaggedIds)) {
        $flaggedDir = "$exportDir/important_$backupDate";
        mkdir($flaggedDir, 0755, true);
        
        foreach ($flaggedIds as $id) {
            $email = $imap->getEmail($id);
            
            // Sauvegarder le contenu
            $clean = $imap->extractCleanBody($id, false);
            $filename = sanitizeFileName($email['subject']) . '_' . $id . '.txt';
            
            $content = "De: {$email['from']}\n";
            $content .= "À: {$email['to']}\n";
            $content .= "Sujet: {$email['subject']}\n";
            $content .= "Date: {$email['date']}\n";
            $content .= str_repeat('-', 80) . "\n\n";
            $content .= $clean['content'] . "\n\n";
            
            if (!empty($clean['signature'])) {
                $content .= str_repeat('-', 80) . "\n";
                $content .= "SIGNATURE:\n";
                $content .= $clean['signature'] . "\n";
            }
            
            file_put_contents("$flaggedDir/$filename", $content);
            
            // Télécharger les pièces jointes
            if (!empty($email['attachments'])) {
                $attDir = "$flaggedDir/attachments_" . $id;
                mkdir($attDir, 0755, true);
                $imap->downloadAllAttachments($id, $attDir, true);
            }
        }
        
        echo "   ✅ Exportés dans: $flaggedDir\n\n";
    }
    
    // 2. Exporter les emails d'un expéditeur spécifique
    echo "2️⃣ Export des emails de 'client@important.com'...\n";
    $clientEmails = $imap->search('FROM "client@important.com"');
    echo "   Trouvés: " . count($clientEmails) . " email(s)\n";
    
    if (!empty($clientEmails)) {
        $clientDir = "$exportDir/client_important_$backupDate";
        mkdir($clientDir, 0755, true);
        
        $index = [];
        
        foreach ($clientEmails as $id) {
            $email = $imap->getEmail($id);
            $clean = $imap->extractCleanBody($id, false);
            
            $filename = date('Y-m-d', strtotime($email['date'])) . '_' . 
                        sanitizeFileName($email['subject']) . '_' . $id . '.txt';
            
            $content = "="  . str_repeat('=', 79) . "\n";
            $content .= "Email #{$id}\n";
            $content .= "=" . str_repeat('=', 79) . "\n";
            $content .= "De: {$email['from']}\n";
            $content .= "À: {$email['to']}\n";
            $content .= "Sujet: {$email['subject']}\n";
            $content .= "Date: {$email['date']}\n";
            $content .= str_repeat('-', 80) . "\n\n";
            $content .= $clean['content'] . "\n";
            
            file_put_contents("$clientDir/$filename", $content);
            
            // Index pour recherche rapide
            $index[] = [
                'id' => $id,
                'date' => $email['date'],
                'subject' => $email['subject'],
                'file' => $filename,
                'has_attachments' => !empty($email['attachments']),
            ];
        }
        
        // Sauvegarder l'index JSON
        file_put_contents("$clientDir/index.json", json_encode($index, JSON_PRETTY_PRINT));
        
        echo "   ✅ Exportés dans: $clientDir\n";
        echo "   📄 Index créé: index.json\n\n";
    }
    
    // 3. Export CSV pour analyse
    echo "3️⃣ Export CSV des 100 derniers emails...\n";
    $recentEmails = $imap->getEmails(100);
    
    $csvFile = "$exportDir/emails_export_$backupDate.csv";
    $csv = fopen($csvFile, 'w');
    
    // Headers
    fputcsv($csv, ['ID', 'Date', 'De', 'Sujet', 'Lu', 'Taille (KB)', 'Nb Pièces Jointes']);
    
    foreach ($recentEmails as $email) {
        fputcsv($csv, [
            $email['id'],
            $email['date'],
            $email['from'],
            $email['subject'],
            $email['seen'] ? 'Oui' : 'Non',
            number_format($email['size'] / 1024, 2),
            count($email['attachments']),
        ]);
    }
    
    fclose($csv);
    echo "   ✅ CSV créé: $csvFile\n\n";
    
    // 4. Export JSON pour archivage complet
    echo "4️⃣ Export JSON des emails avec pièces jointes...\n";
    $withAttachments = array_filter($recentEmails, fn($e) => !empty($e['attachments']));
    
    $jsonFile = "$exportDir/emails_with_attachments_$backupDate.json";
    file_put_contents($jsonFile, json_encode($withAttachments, JSON_PRETTY_PRINT));
    
    echo "   ✅ JSON créé: $jsonFile\n";
    echo "   Emails avec pièces jointes: " . count($withAttachments) . "\n\n";
    
    // 5. Statistiques du backup
    echo str_repeat('=', 80) . "\n";
    echo "STATISTIQUES DU BACKUP\n";
    echo str_repeat('=', 80) . "\n";
    
    $totalSize = 0;
    $totalFiles = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($exportDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $totalSize += $file->getSize();
            $totalFiles++;
        }
    }
    
    echo "Dossier: $exportDir\n";
    echo "Fichiers créés: $totalFiles\n";
    echo "Taille totale: " . number_format($totalSize / 1024 / 1024, 2) . " MB\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    
    $imap->disconnect();
    
    echo "\n✅ Backup terminé avec succès\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

/**
 * Nettoyer un nom de fichier
 */
function sanitizeFileName(string $filename, int $maxLength = 100): string
{
    // Supprimer caractères spéciaux
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    $filename = preg_replace('/_+/', '_', $filename);
    $filename = trim($filename, '_');
    
    // Limiter la longueur
    if (strlen($filename) > $maxLength) {
        $filename = substr($filename, 0, $maxLength);
    }
    
    return $filename ?: 'email';
}
