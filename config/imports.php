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
];
