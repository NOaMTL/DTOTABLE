# 📧 Clients IMAP/POP3 PHP Pur

Système d'accès aux boîtes mail via IMAP ou POP3, **sans dépendances externes**, en PHP natif.

**Compatible PHP 8.2 / 8.3**

## 🚀 Installation

### Activer l'extension PHP IMAP (pour ImapClient)

```bash
# Ubuntu/Debian
sudo apt-get install php8.3-imap
sudo systemctl restart php8.3-fpm

# Windows (dans php.ini)
extension=imap

# Vérifier
php -m | grep imap
```

**Note** : POP3Client n'a besoin d'aucune extension (sockets natifs).

## 📖 Utilisation

### IMAP Client (Recommandé)

```php
use App\Mail\ImapClient;

// Connexion
$imap = new ImapClient('imap.gmail.com', 993, true);
$imap->connect('votre-email@gmail.com', 'votre-mot-de-passe');

// Récupérer 10 derniers emails
$emails = $imap->getEmails(10);

foreach ($emails as $email) {
    echo "De: " . $email['from'][0]['full'] . "\n";
    echo "Sujet: " . $email['subject'] . "\n";
    echo "Date: " . $email['date'] . "\n";
    echo "Body: " . substr($email['body_text'], 0, 100) . "...\n";
    echo "---\n";
}

// Statistiques
echo "Total: " . $imap->getEmailCount() . " emails\n";
echo "Non lus: " . $imap->getUnreadCount() . " emails\n";

// Déconnexion
$imap->disconnect();
```

### POP3 Client

```php
use App\Mail\Pop3Client;

// Connexion
$pop = new Pop3Client('pop.gmail.com', 995, true);
$pop->connect('votre-email@gmail.com', 'votre-mot-de-passe');

// Récupérer les emails
$emails = $pop->getEmails(5);

foreach ($emails as $email) {
    echo "Sujet: " . $email['subject'] . "\n";
    echo "De: " . $email['from'] . "\n";
    echo "Body: " . substr($email['body'], 0, 100) . "...\n";
    echo "---\n";
}

// Déconnexion
$pop->disconnect();
```

## 🔧 Fonctionnalités

### ImapClient

#### Lecture d'emails
```php
// Tous les emails
$emails = $imap->getEmails(limit: 20);

// Emails non lus uniquement
$unread = $imap->getEmails(limit: 10, unreadOnly: true);

// Un email spécifique
$email = $imap->getEmail(emailNumber: 5);
```

#### Recherche
```php
// Emails d'un expéditeur
$emails = $imap->search('FROM "john@example.com"');

// Emails depuis une date
$emails = $imap->search('SINCE "1-Jan-2024"');

// Emails avec un sujet
$emails = $imap->search('SUBJECT "Facture"');

// Combinaison
$emails = $imap->search('FROM "john@example.com" UNSEEN');
```

#### Gestion des emails
```php
// Marquer comme lu
$imap->markAsRead(5);

// Marquer comme non lu
$imap->markAsUnread(5);

// Supprimer un email
$imap->deleteEmail(5);
$imap->expunge(); // Confirmer la suppression
```

#### Pièces jointes
```php
$email = $imap->getEmail(5);

foreach ($email['attachments'] as $attachment) {
    echo "Fichier: " . $attachment['filename'] . "\n";
    echo "Taille: " . $attachment['size'] . " octets\n";
    
    // Télécharger
    $data = $imap->downloadAttachment(5, $attachment['part_number']);
    file_put_contents('/tmp/' . $attachment['filename'], $data);
}
```

#### Dossiers
```php
// Lister les dossiers
$folders = $imap->listFolders();
print_r($folders);

// Se connecter à un dossier spécifique
$imap->connect('email@example.com', 'password', 'Sent');
```

### Pop3Client

#### Lecture d'emails
```php
// Nombre d'emails
$count = $pop->getEmailCount();

// Liste des emails (ID + taille)
$list = $pop->getEmailList();

// Récupérer un email
$email = $pop->getEmail(1);

// Récupérer plusieurs emails
$emails = $pop->getEmails(10);
```

#### Headers uniquement
```php
// Headers + 0 lignes de body
$headers = $pop->getEmailHeaders(1, 0);

// Headers + 10 premières lignes
$preview = $pop->getEmailHeaders(1, 10);
```

#### Suppression
```php
// Marquer pour suppression
$pop->deleteEmail(1);
$pop->deleteEmail(2);

// Annuler
$pop->reset();

// Confirmer (automatique à la déconnexion)
$pop->disconnect();
```

## 📋 Structure d'un email

### ImapClient
```php
[
    'id' => 5,
    'uid' => 1234567,
    'subject' => 'Sujet de l\'email',
    'from' => [
        ['email' => 'john@example.com', 'name' => 'John Doe', 'full' => 'John Doe <john@example.com>']
    ],
    'to' => [...],
    'cc' => [...],
    'date' => '2026-03-11 14:30:00',
    'size' => 12345,
    'seen' => false,
    'flagged' => false,
    'body_text' => 'Corps de l\'email en texte...',
    'body_html' => '<html>Corps de l\'email en HTML...</html>',
    'attachments' => [
        ['filename' => 'document.pdf', 'size' => 54321, 'type' => 'PDF', 'part_number' => 2]
    ]
]
```

### Pop3Client
```php
[
    'id' => 1,
    'subject' => 'Sujet de l\'email',
    'from' => 'john@example.com',
    'to' => 'me@example.com',
    'date' => 'Tue, 11 Mar 2026 14:30:00 +0100',
    'body' => 'Corps de l\'email...',
    'headers' => [...],
    'raw' => 'Email brut complet...'
]
```

## 🔐 Configuration Gmail

### App Password (obligatoire pour Gmail)

1. Activer la validation en 2 étapes
2. Aller sur https://myaccount.google.com/apppasswords
3. Générer un mot de passe d'application
4. Utiliser ce mot de passe au lieu de votre mot de passe Gmail

### Serveurs Gmail
- **IMAP** : `imap.gmail.com:993` (SSL)
- **POP3** : `pop.gmail.com:995` (SSL)

## 📧 Autres fournisseurs

### Outlook/Hotmail
```php
// IMAP
$imap = new ImapClient('outlook.office365.com', 993, true);

// POP3
$pop = new Pop3Client('outlook.office365.com', 995, true);
```

### Yahoo
```php
// IMAP
$imap = new ImapClient('imap.mail.yahoo.com', 993, true);

// POP3 (nécessite activation dans les paramètres Yahoo)
$pop = new Pop3Client('pop.mail.yahoo.com', 995, true);
```

### OVH
```php
$imap = new ImapClient('ssl0.ovh.net', 993, true);
$pop = new Pop3Client('ssl0.ovh.net', 995, true);
```

## ⚠️ Gestion des erreurs

```php
try {
    $imap = new ImapClient('imap.gmail.com', 993, true);
    $imap->connect('user@gmail.com', 'wrong-password');
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    
    // Erreurs IMAP détaillées
    $errors = $imap->getErrors();
    print_r($errors);
}
```

## 🎯 Critères de recherche IMAP

| Critère | Description | Exemple |
|---------|-------------|---------|
| `ALL` | Tous les emails | `ALL` |
| `UNSEEN` | Non lus | `UNSEEN` |
| `SEEN` | Lus | `SEEN` |
| `FLAGGED` | Marqués | `FLAGGED` |
| `FROM` | Expéditeur | `FROM "john@example.com"` |
| `TO` | Destinataire | `TO "me@example.com"` |
| `SUBJECT` | Sujet | `SUBJECT "Facture"` |
| `BODY` | Corps | `BODY "urgent"` |
| `SINCE` | Depuis date | `SINCE "1-Jan-2024"` |
| `BEFORE` | Avant date | `BEFORE "31-Dec-2023"` |
| `ON` | À la date | `ON "15-Mar-2024"` |

## 🚀 Performances

### ImapClient
- ✅ Support natif des dossiers
- ✅ Recherche côté serveur (rapide)
- ✅ Pièces jointes optimisées
- ✅ Gestion flags (lu/non lu/marqué)
- ⚠️ Nécessite extension PHP imap

### Pop3Client
- ✅ Aucune dépendance externe
- ✅ Plus léger que IMAP
- ✅ Compatible tous serveurs
- ⚠️ Pas de dossiers (INBOX seulement)
- ⚠️ Pas de recherche côté serveur

## 📝 Recommandations

1. **Utiliser IMAP** si disponible (plus de fonctionnalités)
2. **POP3** uniquement si IMAP indisponible
3. **Toujours utiliser SSL** (port 993/995)
4. **Déconnecter proprement** pour libérer les ressources
5. **Gérer les erreurs** avec try/catch

## 🔍 Debugging

```php
// Activer les erreurs PHP
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Voir les erreurs IMAP
$imap->connect('user', 'pass');
$errors = $imap->getErrors();
$alerts = $imap->getAlerts();

print_r($errors);
print_r($alerts);
```

## 📚 Exemples avancés

Voir les fichiers d'exemples :
- [examples/imap_basic.php](examples/imap_basic.php)
- [examples/imap_search.php](examples/imap_search.php)
- [examples/imap_attachments.php](examples/imap_attachments.php)
- [examples/pop3_basic.php](examples/pop3_basic.php)
