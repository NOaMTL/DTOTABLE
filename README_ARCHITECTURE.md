# Architecture de l'Application Tableau Bancaire

## Vue d'ensemble

Application Laravel 12 pour la gestion et l'analyse de données bancaires avec:
- Interface AGGrid Vue3 pour l'affichage et le filtrage des données
- Export PDF avec MPDF
- Import de fichiers texte/CSV
- Logging des exports
- Architecture en couches (Repository, Service, Action patterns)

## Structure du Projet

```
app/
├── Actions/
│   ├── Exports/
│   │   └── ExportTableauToPdfAction.php          # Orchestration de l'export PDF
│   └── Imports/
│       └── ImportDataFromTextFileAction.php      # Orchestration de l'import
├── DataTransferObjects/
│   ├── ExportLogDTO.php                          # DTO pour les logs d'export
│   └── TableauFilterDTO.php                      # DTO pour les filtres AGGrid
├── Enums/
│   └── ExportTypeEnum.php                        # Types d'export (PDF, CSV, Excel)
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── ExportController.php              # API export PDF
│   │   ├── ImportController.php                  # Import de fichiers
│   │   └── TableauController.php                 # Affichage et données AGGrid
│   ├── Middleware/
│   │   └── HandleInertiaRequests.php             # Middleware Inertia
│   ├── Requests/
│   │   ├── ExportTableauRequest.php              # Validation export
│   │   └── ImportDataRequest.php                 # Validation import
│   └── Resources/
│       └── TableauResource.php                   # Transformation données API
├── Models/
│   ├── ExportLog.php                             # Logs des exports
│   └── TableauData.php                           # Données bancaires
├── Providers/
│   └── AppServiceProvider.php                    # Singletons et DI
├── QueryBuilders/
│   └── TableauQueryBuilder.php                   # Construction requêtes AGGrid
├── Repositories/
│   ├── ExportLogRepository.php                   # Accès données logs
│   └── TableauRepository.php                     # Accès données tableau
└── Services/
    ├── DataImportService.php                     # Parsing fichiers import
    ├── ExportLogService.php                      # Gestion logs export
    └── PdfExportService.php                      # Génération PDF avec MPDF

database/
└── migrations/
    ├── 2024_01_01_000001_create_tableau_data_table.php
    └── 2024_01_01_000002_create_export_logs_table.php

routes/
├── api.php                                       # Routes API (tableau data, export)
└── web.php                                       # Routes web (Inertia pages)
```

## Couches de l'Application

### 1. Models (Eloquent ORM)
- **TableauData**: Données bancaires (transactions)
- **ExportLog**: Historique des exports avec filtres utilisés

### 2. DTOs (Data Transfer Objects)
- **TableauFilterDTO**: Encapsule tous les filtres AGGrid et personnalisés
- **ExportLogDTO**: Données structurées pour logging

### 3. Repositories (Data Access Layer)
- **TableauRepository**: CRUD et requêtes filtrées pour les données bancaires
- **ExportLogRepository**: Gestion des logs d'export

### 4. QueryBuilders
- **TableauQueryBuilder**: Convertit les filtres AGGrid en requêtes Eloquent
  - Supporte: text, number, date, set filters
  - Gère les opérateurs: equals, contains, lessThan, greaterThan, inRange, etc.

### 5. Services (Business Logic)
- **PdfExportService**: Génération PDF avec MPDF (format A4 paysage)
- **DataImportService**: Parsing de fichiers texte/CSV avec détection automatique du délimiteur
- **ExportLogService**: Enregistrement des exports pour audit

### 6. Actions (Use Cases)
- **ExportTableauToPdfAction**: Workflow complet d'export (data → PDF → log)
- **ImportDataFromTextFileAction**: Workflow complet d'import (parse → validate → insert)

### 7. Controllers
- **TableauController**: Affichage page Inertia + API data pour AGGrid
- **ImportController**: Upload et traitement fichiers
- **Api\ExportController**: API export PDF + téléchargement

### 8. Requests (Validation)
- **ExportTableauRequest**: Validation des filtres d'export
- **ImportDataRequest**: Validation des fichiers (txt, csv, tsv max 10MB)

### 9. Resources (API Transformation)
- **TableauResource**: Formatage JSON pour AGGrid

## Base de Données

### Table: tableau_data
```sql
- id (PK)
- reference (indexed)
- date_operation (indexed)
- libelle (text)
- montant (decimal 15,2, indexed)
- devise (default: EUR)
- compte (indexed)
- agence
- type_operation (indexed)
- statut (indexed)
- created_at, updated_at

Indexes composites:
- (date_operation, compte)
- (compte, date_operation)
- (devise, date_operation)
```

### Table: export_logs
```sql
- id (PK)
- user_id (FK → users, indexed)
- export_type (indexed)
- filters (JSON)
- results_count
- file_path
- file_size (bytes)
- execution_time (decimal 8,3 seconds)
- ip_address
- user_agent
- created_at, updated_at
```

## Routes

### Routes Web (Inertia)
```php
GET  /               → Redirect vers tableau.index
GET  /tableau        → TableauController@index (page AGGrid)
GET  /import         → ImportController@create
POST /import         → ImportController@store
```

### Routes API (Sanctum Auth)
```php
POST /api/tableau/data           → getData (pagination AGGrid)
POST /api/tableau/count          → count (prévisualisation)
POST /api/export/pdf             → exportPdf (génération)
GET  /api/export/download/{file} → download (téléchargement)
```

## Filtres AGGrid Supportés

### Text Filter
- equals, notEqual
- contains, notContains
- startsWith, endsWith
- blank, notBlank

### Number Filter
- equals, notEqual
- lessThan, lessThanOrEqual
- greaterThan, greaterThanOrEqual
- inRange
- blank, notBlank

### Date Filter
- equals, notEqual
- lessThan, greaterThan
- inRange
- blank, notBlank

### Set Filter
- Sélection multiple de valeurs

## Flux de Données

### 1. Affichage Tableau
```
User → GET /tableau
     → TableauController@index
     → Inertia::render('Tableau/Index')
     → Vue AGGrid Component

AGGrid → POST /api/tableau/data + filterModel
      → TableauController@getData
      → TableauRepository@getFilteredData
      → TableauQueryBuilder@buildQuery
      → Eloquent Query
      → TableauResource::collection
      → JSON Response → AGGrid
```

### 2. Export PDF
```
User → POST /api/export/pdf + filters
     → ExportController@exportPdf
     → ExportTableauToPdfAction@execute
       → TableauRepository@getFilteredDataForExport
       → PdfExportService@generatePdf (MPDF)
       → ExportLogService@logExport
     → JSON response {filename, download_url}
     → User clicks download
     → GET /api/export/download/{file}
     → BinaryFileResponse (PDF download)
```

### 3. Import Données
```
User → Upload file → POST /import
     → ImportController@store
     → ImportDataFromTextFileAction@execute
       → DataImportService@parseTextFile
         → Detect delimiter (;, tab, |, ,)
         → Parse each line
         → Validate data
       → TableauRepository@bulkInsert
     → Redirect with success/errors report
```

## Dépendances Principales

### PHP
- laravel/framework: ^12.0
- mpdf/mpdf: ^8.2 (génération PDF)
- inertiajs/inertia-laravel: ^2.0 (SSR Vue3)
- tightenco/ziggy: ^2.6 (routes Laravel → JS)

### JavaScript
- ag-grid-community (AGGrid core)
- ag-grid-vue3 (AGGrid Vue3 wrapper)
- @inertiajs/vue3 (Inertia client)

## Configuration

### AppServiceProvider
Tous les services sont enregistrés en Singleton:
- TableauQueryBuilder
- TableauRepository
- ExportLogRepository
- PdfExportService
- DataImportService
- ExportLogService

### Middleware Inertia
Configuré dans `bootstrap/app.php` pour les routes web

### Storage
Exports PDF sauvegardés dans: `storage/app/public/exports/`
Format: `export_tableau_YYYY-MM-DD_HHiiss.pdf`

## Sécurité

- Routes API protégées par Sanctum Auth
- Routes web protégées par Auth middleware
- Validation des requests (ExportTableauRequest, ImportDataRequest)
- Logging des exports (user_id, IP, user_agent, filters)
- Suppression automatique du fichier après téléchargement

## Performance

### Optimisations Base de Données
- Indexes sur colonnes fréquemment filtrées
- Indexes composites pour requêtes multi-colonnes
- Pagination AGGrid (100 records/page par défaut)

### Optimisations Export
- Streaming des données pour grands volumes
- Génération PDF avec MPDF optimisé
- Exécution time tracking

### Optimisations Import
- Bulk insert pour imports massifs
- Validation ligne par ligne avec rapport d'erreurs
- Support multi-formats (CSV, TSV, pipe, semicolon)

## Prochaines Étapes

1. **Frontend Vue3**:
   - Créer `resources/js/Pages/Tableau/Index.vue` (AGGrid component)
   - Créer `resources/js/Pages/Import/Create.vue` (upload form)

2. **Authentification**:
   - Configurer Laravel Breeze/Fortify
   - Ajouter middleware auth

3. **Tests**:
   - Tests unitaires pour Services
   - Tests d'intégration pour Actions
   - Tests de features pour Controllers

4. **Documentation API**:
   - OpenAPI/Swagger specs
   - Postman collection

## Commandes Utiles

```bash
# Migrations
php artisan migrate

# Routes
php artisan route:list

# Générer routes Ziggy pour JS
php artisan ziggy:generate

# Créer lien symbolique storage
php artisan storage:link

# Serveur dev
php artisan serve

# Compiler assets
npm run dev
```
