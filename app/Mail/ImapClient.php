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
