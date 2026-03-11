<?php

namespace App\Mail;

use Exception;

/**
 * Client IMAP pur PHP - Sans dépendances externes
 * 
 * Compatible PHP 8.2 / 8.3
 * Utilise uniquement l'extension PHP imap (php-imap)
 * 
 * @example
 * $imap = new ImapClient('imap.gmail.com', 993, true);
 * $imap->connect('user@gmail.com', 'password');
 * $emails = $imap->getEmails();
 * $imap->disconnect();
 */
class ImapClient
{
    private $connection = null;
    private string $host;
    private int $port;
    private bool $ssl;
    private ?string $mailbox = null;

    /**
     * @param string $host Serveur IMAP (ex: imap.gmail.com)
     * @param int $port Port (993 pour SSL, 143 pour non-SSL)
     * @param bool $ssl Utiliser SSL/TLS
     */
    public function __construct(string $host, int $port = 993, bool $ssl = true)
    {
        if (!function_exists('imap_open')) {
            throw new Exception('Extension PHP imap non installée');
        }

        $this->host = $host;
        $this->port = $port;
        $this->ssl = $ssl;
    }

    /**
     * Se connecter à la boîte mail
     */
    public function connect(string $username, string $password, string $folder = 'INBOX'): bool
    {
        $flags = $this->ssl ? '/imap/ssl' : '/imap';
        $this->mailbox = "{{$this->host}:{$this->port}{$flags}}{$folder}";

        $this->connection = @imap_open($this->mailbox, $username, $password);

        if (!$this->connection) {
            $error = imap_last_error();
            throw new Exception("Connexion IMAP échouée: {$error}");
        }

        return true;
    }

    /**
     * Se déconnecter
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Obtenir le nombre d'emails
     */
    public function getEmailCount(): int
    {
        $this->ensureConnected();
        return imap_num_msg($this->connection);
    }

    /**
     * Obtenir le nombre d'emails non lus
     */
    public function getUnreadCount(): int
    {
        $this->ensureConnected();
        $status = imap_status($this->connection, $this->mailbox, SA_UNSEEN);
        return $status->unseen ?? 0;
    }

    /**
     * Obtenir les emails
     * 
     * @param int $limit Nombre max d'emails à récupérer (0 = tous)
     * @param bool $unreadOnly Récupérer uniquement les non lus
     * @return array
     */
    public function getEmails(int $limit = 10, bool $unreadOnly = false): array
    {
        $this->ensureConnected();

        if ($unreadOnly) {
            $emails = imap_search($this->connection, 'UNSEEN');
            if (!$emails) {
                return [];
            }
        } else {
            $count = $this->getEmailCount();
            if ($count === 0) {
                return [];
            }
            $start = max(1, $count - $limit + 1);
            $emails = range($start, $count);
        }

        $result = [];
        $emails = array_reverse($emails); // Plus récents en premier

        foreach (array_slice($emails, 0, $limit > 0 ? $limit : null) as $emailNumber) {
            $result[] = $this->getEmail($emailNumber);
        }

        return $result;
    }

    /**
     * Obtenir un email spécifique
     */
    public function getEmail(int $emailNumber): array
    {
        $this->ensureConnected();

        $header = imap_headerinfo($this->connection, $emailNumber);
        $structure = imap_fetchstructure($this->connection, $emailNumber);
        
        $email = [
            'id' => $emailNumber,
            'uid' => imap_uid($this->connection, $emailNumber),
            'subject' => $this->decodeHeader($header->subject ?? '(Sans sujet)'),
            'from' => $this->parseAddress($header->from ?? []),
            'to' => $this->parseAddress($header->to ?? []),
            'cc' => $this->parseAddress($header->cc ?? []),
            'date' => date('Y-m-d H:i:s', strtotime($header->date ?? 'now')),
            'size' => $header->Size ?? 0,
            'seen' => $header->Unseen === 'U' ? false : true,
            'flagged' => $header->Flagged === 'F',
            'body_text' => $this->getBody($emailNumber, 'text'),
            'body_html' => $this->getBody($emailNumber, 'html'),
            'attachments' => $this->getAttachments($emailNumber, $structure),
        ];

        return $email;
    }

    /**
     * Récupérer le corps de l'email
     */
    private function getBody(int $emailNumber, string $type = 'text'): string
    {
        $structure = imap_fetchstructure($this->connection, $emailNumber);

        if (!isset($structure->parts)) {
            // Email simple (pas multipart)
            $body = imap_body($this->connection, $emailNumber);
            return $this->decodeBody($body, $structure);
        }

        // Email multipart
        $subtype = strtolower($type);
        foreach ($structure->parts as $partNum => $part) {
            $partNumber = $partNum + 1;
            
            if ($part->subtype === strtoupper($subtype)) {
                $body = imap_fetchbody($this->connection, $emailNumber, $partNumber);
                return $this->decodeBody($body, $part);
            }
        }

        // Fallback sur la première partie
        if (isset($structure->parts[0])) {
            $body = imap_fetchbody($this->connection, $emailNumber, 1);
            return $this->decodeBody($body, $structure->parts[0]);
        }

        return '';
    }

    /**
     * Décoder le corps selon l'encodage
     */
    private function decodeBody(string $body, object $part): string
    {
        $encoding = $part->encoding ?? 0;

        switch ($encoding) {
            case 0: // 7BIT
            case 1: // 8BIT
                return $body;
            case 2: // BINARY
                return $body;
            case 3: // BASE64
                return base64_decode($body);
            case 4: // QUOTED-PRINTABLE
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }

    /**
     * Récupérer les pièces jointes
     */
    private function getAttachments(int $emailNumber, object $structure): array
    {
        $attachments = [];

        if (!isset($structure->parts)) {
            return [];
        }

        foreach ($structure->parts as $partNum => $part) {
            $partNumber = $partNum + 1;

            // Vérifier si c'est une pièce jointe
            if (isset($part->disposition) && strtoupper($part->disposition) === 'ATTACHMENT') {
                $filename = '';
                
                if (isset($part->dparameters)) {
                    foreach ($part->dparameters as $param) {
                        if (strtoupper($param->attribute) === 'FILENAME') {
                            $filename = $this->decodeHeader($param->value);
                            break;
                        }
                    }
                }

                if (empty($filename) && isset($part->parameters)) {
                    foreach ($part->parameters as $param) {
                        if (strtoupper($param->attribute) === 'NAME') {
                            $filename = $this->decodeHeader($param->value);
                            break;
                        }
                    }
                }

                $attachments[] = [
                    'filename' => $filename,
                    'size' => $part->bytes ?? 0,
                    'type' => $part->subtype ?? 'UNKNOWN',
                    'part_number' => $partNumber,
                ];
            }
        }

        return $attachments;
    }

    /**
     * Télécharger une pièce jointe
     */
    public function downloadAttachment(int $emailNumber, int $partNumber): string
    {
        $this->ensureConnected();
        
        $data = imap_fetchbody($this->connection, $emailNumber, $partNumber);
        $structure = imap_bodystruct($this->connection, $emailNumber, $partNumber);
        
        return $this->decodeBody($data, $structure);
    }

    /**
     * Télécharger toutes les pièces jointes d'un email
     * 
     * @param int $emailNumber
     * @param string $destinationPath Dossier de destination (ex: storage/app/attachments)
     * @param bool $keepOriginalName Garder le nom original (true) ou générer un nom unique (false)
     * @return array Liste des fichiers téléchargés avec métadonnées
     */
    public function downloadAllAttachments(int $emailNumber, string $destinationPath, bool $keepOriginalName = true): array
    {
        $this->ensureConnected();
        
        $email = $this->getEmail($emailNumber);
        $attachments = $email['attachments'] ?? [];
        
        if (empty($attachments)) {
            return [];
        }
        
        // Créer le dossier si nécessaire
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }
        
        $downloaded = [];
        
        foreach ($attachments as $attachment) {
            try {
                $data = $this->downloadAttachment($emailNumber, $attachment['part_number']);
                
                // Déterminer le nom du fichier
                $originalName = $attachment['filename'];
                $extension = $this->getFileExtension($originalName, $attachment['type']);
                
                if ($keepOriginalName) {
                    $filename = $this->sanitizeFilename($originalName);
                } else {
                    $filename = uniqid('attachment_') . '.' . $extension;
                }
                
                // Éviter écrasement si fichier existe
                $fullPath = $destinationPath . '/' . $filename;
                $counter = 1;
                while (file_exists($fullPath)) {
                    $info = pathinfo($filename);
                    $base = $info['filename'];
                    $ext = $info['extension'] ?? '';
                    $filename = $base . '_' . $counter . ($ext ? '.' . $ext : '');
                    $fullPath = $destinationPath . '/' . $filename;
                    $counter++;
                }
                
                // Écrire le fichier
                file_put_contents($fullPath, $data);
                
                $downloaded[] = [
                    'original_name' => $originalName,
                    'saved_name' => $filename,
                    'path' => $fullPath,
                    'size' => filesize($fullPath),
                    'type' => $attachment['type'],
                    'mime' => $this->getMimeType($fullPath, $attachment['type']),
                    'extension' => $extension,
                ];
                
            } catch (Exception $e) {
                $downloaded[] = [
                    'original_name' => $attachment['filename'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $downloaded;
    }

    /**
     * Extraire le contenu propre d'un email (sans signature, réponses, etc.)
     * 
     * @param int $emailNumber
     * @param bool $html Extraire le HTML (true) ou texte brut (false)
     * @return array ['content' => contenu principal, 'signature' => signature détectée, 'quoted' => contenu cité]
     */
    public function extractCleanBody(int $emailNumber, bool $html = false): array
    {
        $email = $this->getEmail($emailNumber);
        $body = $html ? ($email['body_html'] ?? $email['body_text']) : $email['body_text'];
        
        if (empty($body)) {
            return ['content' => '', 'signature' => '', 'quoted' => ''];
        }
        
        $content = $body;
        $signature = '';
        $quoted = '';
        
        // Supprimer le HTML si en mode texte
        if (!$html && strpos($content, '<html') !== false) {
            $content = strip_tags($content);
        }
        
        // Détecter et extraire les réponses citées (quoted replies)
        $quotedPatterns = [
            '/^>.*$/m',                                    // Ligne commençant par >
            '/^On .* wrote:.*$/ms',                        // "On ... wrote:"
            '/^Le .* a écrit.*$/ms',                       // "Le ... a écrit"
            '/^From:.*?Subject:.*$/ms',                    // Headers de forward
            '/^De :.*?Objet :.*$/ms',                      // Headers français
            '/_{3,}.*?From:.*$/ms',                        // Ligne de séparation + headers
        ];
        
        foreach ($quotedPatterns as $pattern) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];
                $quoted = substr($content, $position);
                $content = trim(substr($content, 0, $position));
                break;
            }
        }
        
        // Détecter et extraire la signature
        $signaturePatterns = [
            '/\n-- ?\n.*/s',                               // Standard "-- " (RFC)
            '/\n_{2,}\n.*/s',                             // Ligne de underscores
            '/\n-{2,}\n.*/s',                             // Ligne de tirets
            '/\nCordialement[,\s].*$/si',                // "Cordialement"
            '/\nBest regards[,\s].*$/si',                // "Best regards"
            '/\nSent from my .*/si',                      // "Sent from my iPhone"
            '/\nEnvoyé depuis .*/si',                     // "Envoyé depuis mon iPhone"
        ];
        
        foreach ($signaturePatterns as $pattern) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];
                $signature = trim(substr($content, $position));
                $content = trim(substr($content, 0, $position));
                break;
            }
        }
        
        // Nettoyer les espaces multiples et lignes vides excessives
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        
        return [
            'content' => trim($content),
            'signature' => trim($signature),
            'quoted' => trim($quoted),
        ];
    }

    /**
     * Obtenir l'extension d'un fichier avec détection automatique
     */
    private function getFileExtension(string $filename, string $mimeSubtype): string
    {
        // Essayer depuis le nom de fichier
        if (preg_match('/\.([a-z0-9]{2,5})$/i', $filename, $matches)) {
            return strtolower($matches[1]);
        }
        
        // Mapper depuis le MIME type
        $mimeMap = [
            'JPEG' => 'jpg',
            'JPG' => 'jpg',
            'PNG' => 'png',
            'GIF' => 'gif',
            'PDF' => 'pdf',
            'ZIP' => 'zip',
            'RAR' => 'rar',
            'DOC' => 'doc',
            'DOCX' => 'docx',
            'XLS' => 'xls',
            'XLSX' => 'xlsx',
            'PPT' => 'ppt',
            'PPTX' => 'pptx',
            'TXT' => 'txt',
            'CSV' => 'csv',
            'XML' => 'xml',
            'HTML' => 'html',
            'MP4' => 'mp4',
            'AVI' => 'avi',
            'MP3' => 'mp3',
            'WAV' => 'wav',
        ];
        
        $subtype = strtoupper($mimeSubtype);
        return $mimeMap[$subtype] ?? strtolower($mimeSubtype);
    }

    /**
     * Nettoyer un nom de fichier
     */
    private function sanitizeFilename(string $filename): string
    {
        // Supprimer caractères dangereux
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        return trim($filename, '_');
    }

    /**
     * Obtenir le MIME type d'un fichier
     */
    private function getMimeType(string $filePath, string $fallbackType = 'application/octet-stream'): string
    {
        if (!file_exists($filePath)) {
            return $fallbackType;
        }
        
        // Utiliser finfo si disponible
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mime ?: $fallbackType;
        }
        
        // Fallback sur mime_content_type
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath) ?: $fallbackType;
        }
        
        return $fallbackType;
    }

    /**
     * Marquer comme lu
     */
    public function markAsRead(int $emailNumber): bool
    {
        $this->ensureConnected();
        return imap_setflag_full($this->connection, (string) $emailNumber, '\\Seen');
    }

    /**
     * Marquer comme non lu
     */
    public function markAsUnread(int $emailNumber): bool
    {
        $this->ensureConnected();
        return imap_clearflag_full($this->connection, (string) $emailNumber, '\\Seen');
    }

    /**
     * Supprimer un email
     */
    public function deleteEmail(int $emailNumber): bool
    {
        $this->ensureConnected();
        return imap_delete($this->connection, (string) $emailNumber);
    }

    /**
     * Vider la corbeille (expunge)
     */
    public function expunge(): bool
    {
        $this->ensureConnected();
        return imap_expunge($this->connection);
    }

    /**
     * Rechercher des emails
     */
    public function search(string $criteria): array
    {
        $this->ensureConnected();
        
        $results = imap_search($this->connection, $criteria);
        
        if (!$results) {
            return [];
        }

        $emails = [];
        foreach ($results as $emailNumber) {
            $emails[] = $this->getEmail($emailNumber);
        }

        return $emails;
    }

    /**
     * Lister les dossiers
     */
    public function listFolders(): array
    {
        $this->ensureConnected();
        
        $folders = imap_list($this->connection, "{{$this->host}:{$this->port}}", '*');
        
        if (!$folders) {
            return [];
        }

        return array_map(function ($folder) {
            return str_replace("{{$this->host}:{$this->port}}", '', $folder);
        }, $folders);
    }

    /**
     * Décoder un header encodé
     */
    private function decodeHeader(string $header): string
    {
        $decoded = imap_mime_header_decode($header);
        $result = '';

        foreach ($decoded as $part) {
            $charset = $part->charset ?? 'UTF-8';
            $text = $part->text;

            if (strtoupper($charset) !== 'UTF-8' && strtoupper($charset) !== 'DEFAULT') {
                $text = mb_convert_encoding($text, 'UTF-8', $charset);
            }

            $result .= $text;
        }

        return $result;
    }

    /**
     * Parser les adresses
     */
    private function parseAddress(array $addresses): array
    {
        $result = [];

        foreach ($addresses as $addr) {
            $email = $addr->mailbox . '@' . $addr->host;
            $name = $this->decodeHeader($addr->personal ?? '');
            
            $result[] = [
                'email' => $email,
                'name' => $name,
                'full' => empty($name) ? $email : "{$name} <{$email}>",
            ];
        }

        return $result;
    }

    /**
     * Vérifier la connexion
     */
    private function ensureConnected(): void
    {
        if (!$this->connection) {
            throw new Exception('Non connecté au serveur IMAP');
        }
    }

    /**
     * Obtenir les erreurs IMAP
     */
    public function getErrors(): array
    {
        return imap_errors() ?: [];
    }

    /**
     * Obtenir les alertes IMAP
     */
    public function getAlerts(): array
    {
        return imap_alerts() ?: [];
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
