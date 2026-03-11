# 📧 ImapClient - Utilisation Standalone

## ⚡ Quick Start

Cette classe fonctionne **sans framework**, en PHP pur.

### 1. Vérifier l'extension PHP IMAP

```bash
php -m | grep imap
```

Si absent :
```bash
# Ubuntu/Debian
sudo apt-get install php8.3-imap
sudo systemctl restart php8.3-fpm

# Windows : décommenter dans php.ini
extension=imap
```

### 2. Copier la Classe

```bash
# Structure minimale
projet/
├── app/
│   └── Mail/
│       └── ImapClient.php  # Copier depuis app/Mail/ImapClient.php
└── index.php
```

### 3. Utiliser la Classe

```php
<?php

require __DIR__ . '/app/Mail/ImapClient.php';

use App\Mail\ImapClient;

$imap = new ImapClient('imap.gmail.com', 993, true);
$imap->connect('email@gmail.com', 'password');

// Récupérer emails
$emails = $imap->getEmails(10);

foreach ($emails as $email) {
    echo $email['subject'] . "\n";
}

$imap->disconnect();
```

## 🎯 Exemples Complets

### Extraction de Contenu Propre

```php
$clean = $imap->extractCleanBody($emailId, false);

echo "Contenu: " . $clean['content'] . "\n";        // Message principal
echo "Signature: " . $clean['signature'] . "\n";     // Signature détectée
echo "Citations: " . $clean['quoted'] . "\n";        // Anciennes réponses
```

### Téléchargement des Pièces Jointes

```php
$files = $imap->downloadAllAttachments(
    emailNumber: 123,
    destinationPath: __DIR__ . '/downloads',
    keepOriginalName: true
);

foreach ($files as $file) {
    echo "{$file['original_name']} → {$file['path']}\n";
    echo "Type: {$file['mime']} | Taille: {$file['size']} bytes\n";
}
```

### Recherche d'Emails

```php
// Emails non lus
$unreadIds = $imap->search('UNSEEN');

// Emails avec pièces jointes
$withAttachmentsIds = $imap->search('UNSEEN');

// Emails d'un expéditeur
$fromIds = $imap->search('FROM "boss@company.com"');

// Emails récents avec mot-clé
$recentIds = $imap->search('SINCE "1 March 2026" SUBJECT "urgent"');
```

## 📋 API Complète

### Connexion

```php
$imap = new ImapClient($host, $port, $ssl);
$imap->connect($username, $password, $folder = 'INBOX');
$imap->disconnect();
```

### Récupération

```php
$count = $imap->getEmailCount();                // Total emails
$unread = $imap->getUnreadCount();              // Non lus
$emails = $imap->getEmails($limit, $unreadOnly); // Liste emails
$email = $imap->getEmail($emailNumber);         // Email unique
```

### Recherche

```php
$ids = $imap->search($criteria);                // Recherche IMAP
$folders = $imap->listFolders();                // Liste dossiers
```

### Actions

```php
$imap->markAsRead($emailNumber);
$imap->markAsUnread($emailNumber);
$imap->deleteEmail($emailNumber);
$imap->expunge();                               // Vider corbeille
```

### Pièces Jointes

```php
$data = $imap->downloadAttachment($emailNumber, $partNumber);
$files = $imap->downloadAllAttachments($emailNumber, $path, $keepOriginalName);
```

### Extraction

```php
$clean = $imap->extractCleanBody($emailNumber, $html = false);
// Retourne: ['content' => '...', 'signature' => '...', 'quoted' => '...']
```

## ✅ Avantages Standalone

- ✅ Aucune dépendance externe (Composer, framework, etc.)
- ✅ Un seul fichier PHP de ~700 lignes
- ✅ Compatible PHP 8.2 / 8.3
- ✅ Fonctionne partout (serveur partagé, script CLI, cron, etc.)
- ✅ Performance optimale (pas de surcharge framework)
- ✅ Auto-détection des types de fichiers
- ✅ Extraction intelligente du contenu (signature, citations)

## 🔧 Configuration Gmail

### Mot de passe d'application

Gmail nécessite un "mot de passe d'application" (pas votre mot de passe principal) :

1. Aller sur https://myaccount.google.com/security
2. Activer "Validation en 2 étapes"
3. Créer un "Mot de passe d'application"
4. Utiliser ce mot de passe dans votre code

### Code Gmail

```php
$imap = new ImapClient('imap.gmail.com', 993, true);
$imap->connect('votre@gmail.com', 'mot-de-passe-application');
```

## 📁 Structure Recommandée

```
projet/
├── app/
│   └── Mail/
│       └── ImapClient.php
├── config/
│   └── mail.php           # Configuration (credentials)
├── downloads/             # Pièces jointes téléchargées
├── logs/                  # Logs optionnels
└── index.php              # Script principal
```

### Exemple config/mail.php

```php
<?php

return [
    'imap' => [
        'host' => 'imap.gmail.com',
        'port' => 993,
        'ssl' => true,
        'username' => 'votre@gmail.com',
        'password' => 'mot-de-passe-application',
    ],
    
    'download_path' => __DIR__ . '/../downloads',
];
```

### Utilisation avec config

```php
<?php

$config = require __DIR__ . '/config/mail.php';
require __DIR__ . '/app/Mail/ImapClient.php';

use App\Mail\ImapClient;

$imap = new ImapClient(
    $config['imap']['host'],
    $config['imap']['port'],
    $config['imap']['ssl']
);

$imap->connect(
    $config['imap']['username'],
    $config['imap']['password']
);
```

## 🚀 Exemples Fournis

Voir le dossier `examples/` :

### Basiques
- `standalone_usage.php` - Exemple complet standalone
- `email_clean_content.php` - Extraction de contenu propre
- `email_download_attachments.php` - Téléchargement pièces jointes

### Avancés
- `email_search_advanced.php` - Recherches complexes (10 exemples)
- `email_folder_management.php` - Gestion multi-dossiers
- `email_auto_processing.php` - Traitement automatique avec règles
- `email_export_backup.php` - Export et sauvegarde (TXT, CSV, JSON)
- `email_monitoring.php` - Monitoring et alertes (pour cron)
- `email_stats_report.php` - Statistiques détaillées et rapports

## 📖 Documentation Complète

Voir [docs/MAIL_CLIENTS.md](docs/MAIL_CLIENTS.md) pour :
- Critères de recherche IMAP
- Gestion des erreurs
- Patterns de signature détectés
- Types MIME supportés
- Troubleshooting

## ⚠️ Notes Importantes

1. **Extension IMAP** : Obligatoire pour ImapClient
2. **Gmail** : Utiliser mot de passe d'application (pas le mot de passe principal)
3. **Outlook/Office365** : `outlook.office365.com`, port 993
4. **Timeout** : Par défaut 30s, configurable dans `imap_open()`
5. **Mémoire** : ~2MB par email avec pièces jointes

## 🆘 Troubleshooting

```php
// Vérifier extension
if (!function_exists('imap_open')) {
    die('Extension php-imap non installée');
}

// Obtenir erreurs
$errors = $imap->getErrors();
$alerts = $imap->getAlerts();

// Debug connexion
try {
    $imap->connect($user, $pass);
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    print_r($imap->getErrors());
}
```
