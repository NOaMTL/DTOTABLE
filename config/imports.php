<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tables de destination selon le type d'import
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'client_commercial' => env('IMPORT_TABLE_CLIENT_COMMERCIAL', 'client_commercial_data'),
        'partenaire' => env('IMPORT_TABLE_PARTENAIRE', 'partenaire_data'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fichiers attendus pour l'import
    |--------------------------------------------------------------------------
    |
    | Définir ici la liste des fichiers à télécharger et importer depuis QDD.
    | Format: [["nom_fichier.txt", "Type"], ...]
    |
    */
    'files' => [
        'client_commercial' => [
            // Exemples pour ClientCommercial:
            ['CLIENT_OPERATIONS_2024.txt', 'OPERATIONS'],
            ['CLIENT_COMPTES_2024.txt', 'COMPTES'],
            ['CLIENT_TRANSACTIONS_2024.txt', 'TRANSACTIONS'],
        ],
        'partenaire' => [
            // Exemples pour Partenaire:
            ['PARTENAIRE_FACTURES_2024.txt', 'FACTURES'],
            ['PARTENAIRE_PAIEMENTS_2024.txt', 'PAIEMENTS'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapping des colonnes
    |--------------------------------------------------------------------------
    |
    | Définir le mapping entre les colonnes de la table et les données.
    | Format:
    |   'nom_colonne' => ['value' => 'valeur_fixe']           // Valeur fixe
    |   'nom_colonne' => ['file_index' => 0]                   // Colonne 0 du fichier
    |   'nom_colonne' => ['file_type' => true]                 // Type du fichier
    |   'nom_colonne' => ['file_name' => true]                 // Nom du fichier
    |   'nom_colonne' => ['special' => 'today']                // Valeur dynamique
    |
    | Mots-clés spéciaux disponibles:
    |   'now', 'today', 'year', 'month', 'day', 'date', 'datetime', 'time',
    |   'timestamp', 'user', 'hostname', 'php_version'
    |
    */
    'column_mapping' => [
        'client_commercial' => [
            // Exemple : 85 colonnes dont 82 du fichier
            'Type' => ['file_type' => true],                    // Type du fichier (ex: 'OPERATIONS')
            'col_1' => ['file_index' => 0],                     // 1ère colonne du fichier
            'col_2' => ['file_index' => 1],                     // 2ème colonne du fichier
            'col_3' => ['file_index' => 2],
            // ... 79 autres colonnes du fichier (index 3-81)
            'col_83' => ['value' => 0],                         // Valeur fixe 0
            'col_84' => ['value' => 0],                         // Valeur fixe 0
            'col_85' => ['value' => 'EUR'],                     // Valeur fixe 'EUR'
        ],
        'partenaire' => [
            'Type' => ['file_type' => true],
            'col_1' => ['file_index' => 0],
            'col_2' => ['file_index' => 1],
            // ... autres colonnes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paramètres de performance
    |--------------------------------------------------------------------------
    */
    'chunk_size' => env('IMPORT_CHUNK_SIZE', 1000),
    'memory_limit' => env('IMPORT_MEMORY_LIMIT', '512M'),

    /*
    |--------------------------------------------------------------------------
    | QDD Configuration
    |--------------------------------------------------------------------------
    */
    'qdd' => [
        'remote_base_path' => env('QDD_REMOTE_BASE_PATH', ''),
        'timeout' => env('QDD_TIMEOUT', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Dossier temporaire
    |--------------------------------------------------------------------------
    */
    'temp_directory' => storage_path('app/temp_imports'),

    /*
    |--------------------------------------------------------------------------
    | Nettoyage automatique
    |--------------------------------------------------------------------------
    |
    | Supprimer automatiquement les fichiers après traitement
    |
    */
    'auto_cleanup' => env('IMPORT_AUTO_CLEANUP', true),

    /*
    |--------------------------------------------------------------------------
    | SQL Server - Configuration BULK INSERT
    |--------------------------------------------------------------------------
    |
    | Si votre serveur SQL Server est distant (pas sur la même machine que
    | l'application Laravel), vous devez configurer un chemin réseau partagé
    | accessible par SQL Server pour utiliser BULK INSERT.
    |
    | Exemples:
    |   - Chemin UNC: '\\\\SERVEUR\\partage\\imports'
    |   - Chemin local SQL Server: 'C:\\temp\\imports'
    |
    | Si null ou non configuré:
    |   - SQL Server local: utilisera le chemin local
    |   - SQL Server distant: utilisera INSERT + TABLOCK (plus lent mais fonctionne)
    |
    */
    'sql_server' => [
        'bulk_insert_path' => env('SQL_SERVER_BULK_PATH', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Types d'import - Configuration avancée
    |--------------------------------------------------------------------------
    |
    | Configuration spécifique pour chaque type d'import
    |
    */
    'types' => [
        'ClientCommercial' => [
            'table' => 'client_commercial_data',
            
            // Colonnes calculées/forcées (complément au parsing)
            'computed_columns' => [
                // Exemples de colonnes calculées
                // 'channel' => 'web',
                // 'campaign' => fn($row) => 'IMPORT_2026',
                // 'priority' => fn($row) => $row['type_client'] === 'VIP' ? 1 : 5,
                // 'full_name' => fn($row) => trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')),
            ],
        ],
        
        'Partenaire' => [
            'table' => 'partenaire_data',
            'computed_columns' => [
                // Colonnes spécifiques aux partenaires
            ],
        ],
    ],
];
