<?php

/**
 * EXEMPLE DE CONFIGURATION AVEC 85 COLONNES
 * 
 * Cet exemple montre comment mapper 85 colonnes dans la table de destination,
 * où :
 * - La colonne 1 (Type) vient du type de fichier
 * - Les colonnes 2-83 viennent du fichier texte (82 colonnes)
 * - Les colonnes 84-85 sont des valeurs fixes (0)
 */

return [
    'tables' => [
        'client_commercial' => env('IMPORT_TABLE_CLIENT_COMMERCIAL', 'client_commercial_data'),
        'partenaire' => env('IMPORT_TABLE_PARTENAIRE', 'partenaire_data'),
    ],

    'files' => [
        'client_commercial' => [
            ['CLIENT_OPERATIONS_2024.txt', 'OPERATIONS'],
            ['CLIENT_COMPTES_2024.txt', 'COMPTES'],
            ['CLIENT_TRANSACTIONS_2024.txt', 'TRANSACTIONS'],
        ],
        'partenaire' => [
            ['PARTENAIRE_FACTURES_2024.txt', 'FACTURES'],
            ['PARTENAIRE_PAIEMENTS_2024.txt', 'PAIEMENTS'],
        ],
    ],

    /**
     * MAPPING DES COLONNES - EXEMPLE AVEC 85 COLONNES
     * 
     * Format pour chaque colonne :
     * 
     * 'nom_colonne' => ['value' => 'valeur_fixe']           // Valeur statique
     * 'nom_colonne' => ['file_index' => 0]                   // Index dans le fichier (0-based)
     * 'nom_colonne' => ['file_type' => true]                 // Type du fichier (ex: 'OPERATIONS')
     * 'nom_colonne' => ['file_name' => true]                 // Nom du fichier
     * 'nom_colonne' => ['special' => 'today']                // Valeur dynamique (date, user, etc.)
     */
    'column_mapping' => [
        'client_commercial' => [
            // Colonne 1 : Type du fichier
            'Type' => ['file_type' => true],
            
            // Colonnes 2-83 : Données depuis le fichier (82 colonnes)
            'reference' => ['file_index' => 0],                 // Index 0 du fichier
            'date_operation' => ['file_index' => 1],            // Index 1 du fichier
            'libelle' => ['file_index' => 2],
            'montant' => ['file_index' => 3],
            'devise' => ['file_index' => 4],
            'compte' => ['file_index' => 5],
            'agence' => ['file_index' => 6],
            'type_operation' => ['file_index' => 7],
            'statut' => ['file_index' => 8],
            'col_10' => ['file_index' => 9],
            'col_11' => ['file_index' => 10],
            'col_12' => ['file_index' => 11],
            'col_13' => ['file_index' => 12],
            'col_14' => ['file_index' => 13],
            'col_15' => ['file_index' => 14],
            'col_16' => ['file_index' => 15],
            'col_17' => ['file_index' => 16],
            'col_18' => ['file_index' => 17],
            'col_19' => ['file_index' => 18],
            'col_20' => ['file_index' => 19],
            'col_21' => ['file_index' => 20],
            'col_22' => ['file_index' => 21],
            'col_23' => ['file_index' => 22],
            'col_24' => ['file_index' => 23],
            'col_25' => ['file_index' => 24],
            'col_26' => ['file_index' => 25],
            'col_27' => ['file_index' => 26],
            'col_28' => ['file_index' => 27],
            'col_29' => ['file_index' => 28],
            'col_30' => ['file_index' => 29],
            'col_31' => ['file_index' => 30],
            'col_32' => ['file_index' => 31],
            'col_33' => ['file_index' => 32],
            'col_34' => ['file_index' => 33],
            'col_35' => ['file_index' => 34],
            'col_36' => ['file_index' => 35],
            'col_37' => ['file_index' => 36],
            'col_38' => ['file_index' => 37],
            'col_39' => ['file_index' => 38],
            'col_40' => ['file_index' => 39],
            'col_41' => ['file_index' => 40],
            'col_42' => ['file_index' => 41],
            'col_43' => ['file_index' => 42],
            'col_44' => ['file_index' => 43],
            'col_45' => ['file_index' => 44],
            'col_46' => ['file_index' => 45],
            'col_47' => ['file_index' => 46],
            'col_48' => ['file_index' => 47],
            'col_49' => ['file_index' => 48],
            'col_50' => ['file_index' => 49],
            'col_51' => ['file_index' => 50],
            'col_52' => ['file_index' => 51],
            'col_53' => ['file_index' => 52],
            'col_54' => ['file_index' => 53],
            'col_55' => ['file_index' => 54],
            'col_56' => ['file_index' => 55],
            'col_57' => ['file_index' => 56],
            'col_58' => ['file_index' => 57],
            'col_59' => ['file_index' => 58],
            'col_60' => ['file_index' => 59],
            'col_61' => ['file_index' => 60],
            'col_62' => ['file_index' => 61],
            'col_63' => ['file_index' => 62],
            'col_64' => ['file_index' => 63],
            'col_65' => ['file_index' => 64],
            'col_66' => ['file_index' => 65],
            'col_67' => ['file_index' => 66],
            'col_68' => ['file_index' => 67],
            'col_69' => ['file_index' => 68],
            'col_70' => ['file_index' => 69],
            'col_71' => ['file_index' => 70],
            'col_72' => ['file_index' => 71],
            'col_73' => ['file_index' => 72],
            'col_74' => ['file_index' => 73],
            'col_75' => ['file_index' => 74],
            'col_76' => ['file_index' => 75],
            'col_77' => ['file_index' => 76],
            'col_78' => ['file_index' => 77],
            'col_79' => ['file_index' => 78],
            'col_80' => ['file_index' => 79],
            'col_81' => ['file_index' => 80],
            'col_82' => ['file_index' => 81],
            
            // Colonnes 84-85 : Valeurs fixes
            'flag_1' => ['value' => 0],
            'flag_2' => ['value' => 0],
            
            // Exemples de valeurs dynamiques (optionnel)
            'import_date' => ['special' => 'today'],        // Date du jour
            'import_datetime' => ['special' => 'now'],      // Date/heure actuelle
            'import_year' => ['special' => 'year'],         // Année actuelle
            'processed_by' => ['special' => 'user'],        // Utilisateur système
            'server_name' => ['special' => 'hostname'],     // Nom du serveur
        ],

        'partenaire' => [
            // Exemple similaire pour Partenaire
            'Type' => ['file_type' => true],
            'code_partenaire' => ['file_index' => 0],
            'date_transaction' => ['file_index' => 1],
            'description' => ['file_index' => 2],
            'montant_ht' => ['file_index' => 3],
            'montant_ttc' => ['file_index' => 4],
            'taux_tva' => ['file_index' => 5],
            'devise' => ['file_index' => 6],
            'statut' => ['file_index' => 7],
            // ... autres colonnes selon besoin
        ],
    ],

    'chunk_size' => env('IMPORT_CHUNK_SIZE', 1000),
    'memory_limit' => env('IMPORT_MEMORY_LIMIT', '512M'),

    'qdd' => [
        'remote_base_path' => env('QDD_REMOTE_BASE_PATH', ''),
        'timeout' => env('QDD_TIMEOUT', 300),
    ],

    'temp_directory' => storage_path('app/temp_imports'),
    'auto_cleanup' => env('IMPORT_AUTO_CLEANUP', true),
];
