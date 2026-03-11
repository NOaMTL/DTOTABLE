# Optimisations Performance Import

## 📊 Résultats Actuels
- **380 000 lignes en 38 minutes** = ~167 lignes/sec
- **Chunk size : 1000**

## 🎯 Objectif
- Passer à **1000+ lignes/sec** (6x plus rapide)
- Target : **380k lignes en 6-8 minutes**

## 🚀 Optimisations à Appliquer

### 1. Désactiver les Logs Laravel (CRITIQUE)
```bash
# .env
LOG_LEVEL=emergency
APP_DEBUG=false
```
**Gain estimé : 30-50%**

### 2. Utiliser la Version TURBO
```bash
php artisan tableau:import-turbo ClientCommercial --drop-indexes --chunk-size=2000
```
**Gain estimé : 30-50%**

### 3. Désactiver Query Log
```php
// Dans ImportTableauDataRefactoredCommand::handle(), après ligne 95
DB::connection()->disableQueryLog();
```
**Gain estimé : 10-20%**

### 4. Augmenter Chunk Size pour SQL Server
```bash
--chunk-size=2000  # Au lieu de 1000
```
**Gain estimé : 20-30%**

### 5. Désactiver Eloquent Events
```php
// Dans handle() après optimizePerformance()
\Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
```
**Gain estimé : 10%**

### 6. Paramètres SQL Server
```sql
-- Avant import
ALTER DATABASE votre_db SET RECOVERY SIMPLE;
ALTER INDEX ALL ON votre_table DISABLE;

-- Après import
ALTER INDEX ALL ON votre_table REBUILD;
ALTER DATABASE votre_db SET RECOVERY FULL;
```
**Gain estimé : 40-60%**

### 7. Fichier Encodé en UTF-8
Si vos fichiers sont en Windows-1252/ANSI :
```bash
# Convertir avant import
iconv -f WINDOWS-1252 -t UTF-8 input.txt > output.txt
```
**Gain : Évite conversion à la volée**

### 8. Désactiver Validation (MODE TURBO)
La version TURBO désactive déjà la validation. Si vous utilisez v2 :
```php
// Commenter dans processLocalFile()
// if ($this->parserService->validateData($parsed, $columnMapping, $importType)) {
    $batch[] = $parsed;
// }
```
**Gain estimé : 10-15%**

## 🔥 Configuration Optimale

### Commande
```bash
php artisan tableau:import-turbo ClientCommercial \
  --drop-indexes \
  --truncate \
  --chunk-size=2000 \
  --keep-files
```

### .env
```env
APP_DEBUG=false
LOG_LEVEL=emergency
DB_QUERY_LOG=false
IMPORT_CHUNK_SIZE=2000
```

### php.ini
```ini
memory_limit = 1024M
max_execution_time = 3600
```

## 📈 Gains Cumulés Estimés

| Optimisation | Gain | Temps estimé |
|--------------|------|--------------|
| Base actuelle | - | 38 min |
| + Logs désactivés | 40% | 23 min |
| + Version TURBO | 40% | 14 min |
| + Chunk 2000 | 25% | 10 min |
| + SQL Server optimisé | 50% | **5 min** |

## ⚡ Commande Ultra-Optimisée

Créer un script bash/PowerShell :

```powershell
# optimize-import.ps1

# 1. Désactiver logs
$env:APP_DEBUG = "false"
$env:LOG_LEVEL = "emergency"

# 2. Optimiser SQL Server
php artisan db:query "ALTER DATABASE tableau SET RECOVERY SIMPLE"
php artisan db:query "ALTER INDEX ALL ON client_commercial_data DISABLE"

# 3. Import TURBO
php artisan tableau:import-turbo ClientCommercial `
  --drop-indexes `
  --truncate `
  --chunk-size=2000

# 4. Restaurer SQL Server
php artisan db:query "ALTER INDEX ALL ON client_commercial_data REBUILD"
php artisan db:query "ALTER DATABASE tableau SET RECOVERY FULL"
```

## 🐛 Problème Encodage

### Symptôme
"Civilité" devient "Civilit?"

### Solution Appliquée
```php
// Utilise iconv au lieu de mb_convert_encoding
$cleaned = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
```

### Alternative : Pré-conversion
```bash
# Avant import, convertir le fichier
iconv -f CP1252 -t UTF-8 fichier_source.txt > fichier_utf8.txt
```

## 📅 Format Date

Tous les formats de date retournent maintenant **Y-m-d** (string) :
- `parseDate()` → 'Y-m-d'
- `'special' => 'today'` → 'Y-m-d'
- `'special' => 'now'` → 'Y-m-d H:i:s'

## 🎯 Checklist Avant Import

- [ ] `APP_DEBUG=false`
- [ ] `LOG_LEVEL=emergency`
- [ ] Utiliser `tableau:import-turbo`
- [ ] `--drop-indexes`
- [ ] `--chunk-size=2000`
- [ ] SQL Server RECOVERY SIMPLE
- [ ] Indexes désactivés
- [ ] Fichier en UTF-8

**Objectif réaliste : 380k lignes en 5-7 minutes au lieu de 38 minutes**
