# Mapping Flexible des Colonnes

## Vue d'ensemble

Le système de mapping permet de construire des lignes d'insertion en combinant :
- Des **valeurs fixes** (constantes)
- Des **colonnes du fichier texte** (par index)
- Des **métadonnées** (type de fichier, nom du fichier)

## Format du Mapping

Dans `config/imports.php`, définissez le mapping dans `column_mapping` :

```php
'column_mapping' => [
    'client_commercial' => [
        'nom_colonne_db' => ['value' => 'valeur_fixe'],      // Valeur statique
        'nom_colonne_db' => ['file_index' => 0],              // Colonne 0 du fichier (0-based)
        'nom_colonne_db' => ['file_type' => true],            // Type du fichier
        'nom_colonne_db' => ['file_name' => true],            // Nom du fichier
    ],
],
```

## Types de Mapping

### 1. Valeur Fixe (`value`)

Insère une valeur constante pour toutes les lignes.

```php
'Type' => ['value' => 'ClientCommercial'],
'devise' => ['value' => 'EUR'],
'flag_actif' => ['value' => 1],
'flag_inactif' => ['value' => 0],
```

**Cas d'usage** :
- Valeurs par défaut
- Flags/indicateurs
- Métadonnées communes

### 2. Index du Fichier (`file_index`)

Récupère la valeur depuis une colonne du fichier texte (index commence à 0).

```php
'reference' => ['file_index' => 0],        // 1ère colonne du fichier
'date_operation' => ['file_index' => 1],   // 2ème colonne du fichier
'montant' => ['file_index' => 2],          // 3ème colonne du fichier
```

**Cas d'usage** :
- Données principales depuis le fichier
- Mapping standard colonne → colonne

### 3. Type de Fichier (`file_type`)

Insère le type du fichier défini dans `config/imports.php > files`.

```php
// Dans config/imports.php
'files' => [
    'client_commercial' => [
        ['CLIENT_OPERATIONS_2024.txt', 'OPERATIONS'],  // ← Ce type
        ['CLIENT_COMPTES_2024.txt', 'COMPTES'],
    ],
],

// Dans column_mapping
'Type' => ['file_type' => true],  // Recevra 'OPERATIONS' ou 'COMPTES'
```

**Cas d'usage** :
- Identifier l'origine des données
- Traitement différencié par type

### 4. Nom de Fichier (`file_name`)

Insère le nom du fichier en cours de traitement.

```php
'source_file' => ['file_name' => true],  // 'CLIENT_OPERATIONS_2024.txt'
```

**Cas d'usage** :
- Audit et traçabilité
- Debugging

### 5. Valeur Dynamique (`special`)

Insère une valeur calculée dynamiquement au moment de l'import.

```php
'import_date' => ['special' => 'today'],        // Date du jour (sans heure)
'import_datetime' => ['special' => 'now'],      // Date et heure actuelles
'import_year' => ['special' => 'year'],         // Année actuelle (2026)
'processed_by' => ['special' => 'user'],        // Utilisateur système
'server_name' => ['special' => 'hostname'],     // Nom de la machine
```

**Mots-clés disponibles** :

| Mot-clé | Description | Exemple de valeur |
|---------|-------------|-------------------|
| `now` | Date et heure actuelles (Carbon) | `2026-03-09 14:30:45` |
| `today` | Date du jour (Carbon) | `2026-03-09 00:00:00` |
| `year` | Année actuelle | `2026` |
| `month` | Mois actuel | `03` |
| `day` | Jour actuel | `09` |
| `date` | Date formatée Y-m-d | `2026-03-09` |
| `datetime` | Date/heure formatée | `2026-03-09 14:30:45` |
| `time` | Heure actuelle H:i:s | `14:30:45` |
| `timestamp` | Unix timestamp | `1741529445` |
| `user` | Utilisateur système | `www-data` ou `root` |
| `hostname` | Nom de la machine | `server-prod-01` |
| `php_version` | Version de PHP | `8.3.0` |

**Cas d'usage** :
- Horodatage des imports
- Traçabilité (qui/quand/où)
- Versioning
- Partitionnement par date/année

## Exemple Complet : 85 Colonnes

Scénario : Table de 85 colonnes
- Colonne 1 : Type du fichier
- Colonnes 2-83 : Données du fichier (82 colonnes)
- Colonnes 84-85 : Valeurs fixes (0)

```php
'column_mapping' => [
    'client_commercial' => [
        // Colonne 1 : Type
        'Type' => ['file_type' => true],
        
        // Colonnes 2-83 : Depuis le fichier
        'reference' => ['file_index' => 0],
        'date_operation' => ['file_index' => 1],
        'libelle' => ['file_index' => 2],
        'montant' => ['file_index' => 3],
        'devise' => ['file_index' => 4],
        // ... 77 autres colonnes (file_index 5-81)
        'col_82' => ['file_index' => 81],
        
        // Colonnes 84-85 : Valeurs fixes
        'flag_1' => ['value' => 0],
        'flag_2' => ['value' => 0],
        
        // Colonnes bonus : Valeurs dynamiques (optionnel)
        'import_date' => ['special' => 'today'],
        'processed_by' => ['special' => 'user'],
    ],
],
```

## Traitement Automatique des Types

Le système applique des transformations selon le **nom de la colonne** :

### Dates (contient `date`)
```php
'date_operation' => ['file_index' => 1],  // Auto-converti en format Y-m-d
```

Formats supportés :
- `Y-m-d` (2024-01-15)
- `d/m/Y` (15/01/2024)
- `d-m-Y` (15-01-2024)
- `Y/m/d` (2024/01/15)
- `Ymd` (20240115)

### Montants et Taux (contient `montant` ou `taux`)
```php
'montant' => ['file_index' => 3],    // Auto-converti en float
'taux_tva' => ['file_index' => 5],   // Auto-converti en float
```

Nettoyage automatique :
- Suppression des espaces et apostrophes
- Remplacement virgule → point
- Conversion en float

### Autres Colonnes
```php
'libelle' => ['file_index' => 2],  // Trimé (espaces supprimés)
```

## Validation des Données

Le système valide que :
1. Les indices de fichier demandés existent
2. Les 3 premières colonnes issues du fichier ne sont pas vides
3. Les colonnes obligatoires selon le type sont présentes

## Compatibilité avec l'Ancien Format

L'ancien format est toujours supporté :

```php
// Ancien format (toujours fonctionnel)
'column_mapping' => [
    'client_commercial' => [
        0 => 'reference',        // Index → Nom de colonne
        1 => 'date_operation',
        2 => 'montant',
    ],
],
```

Si aucun mapping n'est trouvé dans la config, le système utilise le fallback défini dans `ImportConfigService`.

## Erreurs Courantes

### 1. Index hors limites
```
Format invalide (colonne index 81 requise, 80 colonnes trouvées)
```
**Solution** : Vérifiez que votre fichier texte contient bien toutes les colonnes nécessaires.

### 2. Valeurs fixes non reconnues
```php
// ❌ Incorrect
'flag' => 0,

// ✅ CoMot-clé spécial invalide
```
Mot-clé spécial 'invalide' non reconnu, null inséré
```
**Solution** : Utilisez uniquement les mots-clés documentés (now, today, year, etc.)

### 4. rrect
'flag' => ['value' => 0],
```

### 3. Oubli du type de fichier dans config
```php
// ❌ Incorrect
'files' => [
    'client_commercial' => [
        ['CLIENT_OPERATIONS.txt'],  // Type manquant
    ],
],

// ✅ Correct
'files' => [
    'client_commercial' => [
        ['CLIENT_OPERATIONS.txt', 'OPERATIONS'],  // Type présent
    ],
],
```

## Exemple Pratique : Fichier avec 80 Colonnes

Fichier texte (`\t` séparé) :
```
REF001	2024-01-15	Achat	1250.50	EUR	...	(80 colonnes)
REF002	2024-01-16	Vente	-500.00	EUR	...	(80 colonnes)
```

Configuration :
```php
'column_mapping' => [
    'client_commercial' => [
        'Type' => ['file_type' => true],          // 'OPERATIONS'
        'reference' => ['file_index' => 0],       // 'REF001'
        'date_operation' => ['file_index' => 1],  // '2024-01-15'
        'libell_date' => ['special' => 'today'],
        'import_year' => ['special' => 'year'],
        'processed_by' => ['special' => 'user'],
    ],
],
```

Résultat en base :
```
| Type       | reference | date_operation | libelle | montant | ... | flag_imported | import_date | import_year | processed_by |
|------------|-----------|----------------|---------|---------|-----|---------------|-------------|-------------|--------------|
| OPERATIONS | REF001    | 2024-01-15     | Achat   | 1250.50 | ... | 1             | 2026-03-09  | 2026        | www-data 

Résultat en base :
```
| Type       | reference | date_operation | libelle | montant | devise | ... | flag_imported | imported_at |
|------------|-----------|----------------|---------|---------|--------|-----|---------------|-------------|
| OPERATIONS | REF001    | 2024-01-15     | Achat   | 1250.50 | EUR    | ... | 1             | 2024-...    |
```

## Performance

- Les valeurs fixes n'ajoutent **aucun coût** de traitement
- Les indices fichier sont accédés en **O(1)**
- Le mapping est résolu **une seule fois** au démarrage

## Commandes

```bash
# Import avec mapping depuis config
php artisan import:tableau-data-refactored ClientCommercial

# Voir le mapping chargé (debug)
php artisan import:tableau-data-refactored ClientCommercial --dry-run
```

## Voir Aussi

- [config/imports_example_85_columns.php](../config/imports_example_85_columns.php) - Exemple complet 85 colonnes
- [IMPORT_OPTIMISE.md](IMPORT_OPTIMISE.md) - Guide d'optimisation
- [QUICKSTART_IMPORT.md](QUICKSTART_IMPORT.md) - Démarrage rapide
