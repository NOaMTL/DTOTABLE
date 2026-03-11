<?php

namespace App\Mail;

use Exception;

/**
 * Client POP3 pur PHP - Sans dépendances externes
 * 
 * Compatible PHP 8.2 / 8.3
 * Utilise les sockets PHP natifs (aucune extension requise)
 * 
 * @example
 * $pop = new Pop3Client('pop.gmail.com', 995, true);
 * $pop->connect('user@gmail.com', 'password');
 * $emails = $pop->getEmails();
 * $pop->disconnect();
 */
class Pop3Client
{
    private $socket = null;
    private string $host;
    private int $port;
    private bool $ssl;
    private int $timeout = 30;

    /**
     * @param string $host Serveur POP3 (ex: pop.gmail.com)
     * @param int $port Port (995 pour SSL, 110 pour non-SSL)
     * @param bool $ssl Utiliser SSL/TLS
     */
    public function __construct(string $host, int $port = 995, bool $ssl = true)
    {
        $this->host = $host;
        $this->port = $port;
        $this->ssl = $ssl;
    }

    /**
     * Se connecter au serveur POP3
     */
    public function connect(string $username, string $password): bool
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $protocol = $this->ssl ? 'ssl' : 'tcp';
        $address = "{$protocol}://{$this->host}:{$this->port}";

        $this->socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new Exception("Connexion POP3 échouée: {$errstr} ({$errno})");
        }

        // Lire le banner
        $response = $this->readResponse();
        if (!$this->isOk($response)) {
            throw new Exception("Réponse serveur invalide: {$response}");
        }

        // Authentification USER
        $this->sendCommand("USER {$username}");
        $response = $this->readResponse();
        if (!$this->isOk($response)) {
            throw new Exception("Authentification USER échouée: {$response}");
        }

        // Authentification PASS
        $this->sendCommand("PASS {$password}");
        $response = $this->readResponse();
        if (!$this->isOk($response)) {
            throw new Exception("Authentification PASS échouée: {$response}");
        }

        return true;
    }

    /**
     * Se déconnecter
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            $this->sendCommand('QUIT');
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Obtenir le nombre d'emails
     */
    public function getEmailCount(): int
    {
        $this->ensureConnected();
        
        $this->sendCommand('STAT');
        $response = $this->readResponse();
        
        if (!$this->isOk($response)) {
            throw new Exception("Commande STAT échouée: {$response}");
        }

        // Format: +OK count size
        preg_match('/\+OK\s+(\d+)\s+(\d+)/', $response, $matches);
        return (int) ($matches[1] ?? 0);
    }

    /**
     * Obtenir la liste des emails
     */
    public function getEmailList(): array
    {
        $this->ensureConnected();
        
        $this->sendCommand('LIST');
        $response = $this->readMultilineResponse();
        
        $emails = [];
        foreach (explode("\r\n", $response) as $line) {
            if (preg_match('/^(\d+)\s+(\d+)$/', trim($line), $matches)) {
                $emails[] = [
                    'id' => (int) $matches[1],
                    'size' => (int) $matches[2],
                ];
            }
        }

        return $emails;
    }

    /**
     * Récupérer un email complet
     */
    public function getEmail(int $emailNumber): array
    {
        $this->ensureConnected();
        
        $this->sendCommand("RETR {$emailNumber}");
        $response = $this->readMultilineResponse();
        
        return $this->parseEmail($emailNumber, $response);
    }

    /**
     * Récupérer les headers d'un email
     */
    public function getEmailHeaders(int $emailNumber, int $lines = 0): string
    {
        $this->ensureConnected();
        
        $this->sendCommand("TOP {$emailNumber} {$lines}");
        return $this->readMultilineResponse();
    }

    /**
     * Récupérer plusieurs emails
     */
    public function getEmails(int $limit = 10): array
    {
        $count = $this->getEmailCount();
        $start = max(1, $count - $limit + 1);
        
        $emails = [];
        for ($i = $count; $i >= $start && count($emails) < $limit; $i--) {
            try {
                $emails[] = $this->getEmail($i);
            } catch (Exception $e) {
                // Skip les emails problématiques
            }
        }

        return $emails;
    }

    /**
     * Marquer un email pour suppression
     */
    public function deleteEmail(int $emailNumber): bool
    {
        $this->ensureConnected();
        
        $this->sendCommand("DELE {$emailNumber}");
        $response = $this->readResponse();
        
        return $this->isOk($response);
    }

    /**
     * Annuler les suppressions
     */
    public function reset(): bool
    {
        $this->ensureConnected();
        
        $this->sendCommand('RSET');
        $response = $this->readResponse();
        
        return $this->isOk($response);
    }

    /**
     * Parser un email brut
     */
    private function parseEmail(int $emailNumber, string $raw): array
    {
        // Séparer headers et body
        $parts = explode("\r\n\r\n", $raw, 2);
        $headers = $this->parseHeaders($parts[0]);
        $body = $parts[1] ?? '';

        return [
            'id' => $emailNumber,
            'subject' => $this->decodeHeader($headers['subject'] ?? '(Sans sujet)'),
            'from' => $headers['from'] ?? '',
            'to' => $headers['to'] ?? '',
            'date' => $headers['date'] ?? '',
            'body' => $this->decodeBody($body, $headers),
            'headers' => $headers,
            'raw' => $raw,
        ];
    }

    /**
     * Parser les headers
     */
    private function parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerText);
        $currentHeader = null;

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Continuation d'un header précédent
            if (preg_match('/^\s+/', $line) && $currentHeader) {
                $headers[$currentHeader] .= ' ' . trim($line);
            } 
            // Nouveau header
            elseif (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $currentHeader = strtolower($matches[1]);
                $headers[$currentHeader] = trim($matches[2]);
            }
        }

        return $headers;
    }

    /**
     * Décoder le body
     */
    private function decodeBody(string $body, array $headers): string
    {
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '');

        switch ($encoding) {
            case 'base64':
                return base64_decode($body);
            case 'quoted-printable':
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }

    /**
     * Décoder un header
     */
    private function decodeHeader(string $header): string
    {
        if (preg_match('/=\?([^?]+)\?([BQ])\?([^?]+)\?=/i', $header, $matches)) {
            $charset = $matches[1];
            $encoding = strtoupper($matches[2]);
            $text = $matches[3];

            if ($encoding === 'B') {
                $decoded = base64_decode($text);
            } else {
                $decoded = quoted_printable_decode(str_replace('_', ' ', $text));
            }

            if (strtoupper($charset) !== 'UTF-8') {
                $decoded = mb_convert_encoding($decoded, 'UTF-8', $charset);
            }

            return $decoded;
        }

        return $header;
    }

    /**
     * Envoyer une commande
     */
    private function sendCommand(string $command): void
    {
        fwrite($this->socket, $command . "\r\n");
    }

    /**
     * Lire une réponse simple
     */
    private function readResponse(): string
    {
        return trim(fgets($this->socket, 1024));
    }

    /**
     * Lire une réponse multiligne
     */
    private function readMultilineResponse(): string
    {
        $response = '';
        
        // Lire la première ligne (+OK ou -ERR)
        $firstLine = $this->readResponse();
        if (!$this->isOk($firstLine)) {
            throw new Exception("Erreur serveur: {$firstLine}");
        }

        // Lire jusqu'au point seul
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 1024);
            
            if (trim($line) === '.') {
                break;
            }

            // Dé-byte-stuffing (enlever le point en début de ligne)
            if (substr($line, 0, 2) === '..') {
                $line = substr($line, 1);
            }

            $response .= $line;
        }

        return rtrim($response, "\r\n");
    }

    /**
     * Vérifier si la réponse est OK
     */
    private function isOk(string $response): bool
    {
        return substr($response, 0, 3) === '+OK';
    }

    /**
     * Vérifier la connexion
     */
    private function ensureConnected(): void
    {
        if (!$this->socket) {
            throw new Exception('Non connecté au serveur POP3');
        }
    }

    /**
     * Définir le timeout
     */
    public function setTimeout(int $seconds): void
    {
        $this->timeout = $seconds;
        
        if ($this->socket) {
            stream_set_timeout($this->socket, $seconds);
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
