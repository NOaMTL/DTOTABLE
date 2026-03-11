# 🔥 Optimisations Avancées SQL Server

## 🎯 Déjà Implémenté

✅ Auto-optimize structure (indexes, contraintes, FK)
✅ Auto-tuning RECOVERY SIMPLE
✅ Chunk size adaptatif
✅ Téléchargement parallèle (préparé)
✅ Encodage intelligent
✅ Reconnexion QDD automatique
✅ Suppression immédiate fichiers

## 🚀 Optimisations Avancées Supplémentaires

### 1. 🔓 Désactivation Temporaire des Triggers ⭐⭐⭐⭐⭐

**Problème :** Les triggers se déclenchent à chaque INSERT (audit, validation, cascade)

**Solution :**
```sql
-- Avant import
ALTER TABLE client_commercial_data DISABLE TRIGGER ALL;

-- Après import
ALTER TABLE client_commercial_data ENABLE TRIGGER ALL;
```

**Implémentation PHP :**
```php
private function disableTableTriggers(string $tableName): array
{
    // Récupérer tous les triggers
    $triggers = DB::select("
        SELECT name 
        FROM sys.triggers 
        WHERE parent_id = OBJECT_ID(?)
    ", [$tableName]);
    
    foreach ($triggers as $trigger) {
        DB::statement("ALTER TABLE {$tableName} DISABLE TRIGGER [{$trigger->name}]");
    }
    
    return array_column($triggers, 'name');
}
```

**Gain estimé : 30-50%** (si la table a beaucoup de triggers)

---

### 2. 💾 TABLOCK Hint pour Bulk Insert ⭐⭐⭐⭐⭐

**Problème :** Locks au niveau ligne = overhead massif

**Solution :** Lock toute la table (mode bulk)

```php
// Dans BulkInsertService
DB::statement("INSERT INTO {$table} WITH (TABLOCK) VALUES (...), (...), (...)");
```

**Ou mieux encore :**
```sql
-- Mode minimal logging (combiné avec RECOVERY SIMPLE)
ALTER TABLE client_commercial_data SET (LOCK_ESCALATION = TABLE)
```

**Gain estimé : 40-60%** (locks plus efficaces)

---

### 3. 📊 Désactivation Update Statistics ⭐⭐⭐⭐

**Problème :** SQL Server recalcule les statistiques après chaque gros batch

**Solution :**
```sql
-- Avant import
ALTER DATABASE CURRENT SET AUTO_UPDATE_STATISTICS OFF;

-- Après import
ALTER DATABASE CURRENT SET AUTO_UPDATE_STATISTICS ON;
UPDATE STATISTICS client_commercial_data WITH FULLSCAN;
```

**Gain estimé : 15-25%**

---

### 4. 🔄 Batch Transactions Explicites ⭐⭐⭐⭐

**Problème :** Chaque batch = transaction séparée

**Solution :** Grouper plusieurs chunks dans une transaction

```php
DB::beginTransaction();
try {
    // Insérer 10 chunks (10 000 lignes)
    for ($i = 0; $i < 10; $i++) {
        $this->insertBatch($batches[$i], $tableName);
    }
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
}
```

**Gain estimé : 20-30%** (moins de commits)

---

### 5. 🗜️ Désactivation Compression Temporaire ⭐⭐⭐

**Problème :** Si la table est compressée (ROW/PAGE), chaque INSERT doit compresser

**Solution :**
```sql
-- Avant import
ALTER TABLE client_commercial_data REBUILD WITH (DATA_COMPRESSION = NONE);

-- Après import
ALTER TABLE client_commercial_data REBUILD WITH (DATA_COMPRESSION = PAGE);
```

**Gain estimé : 10-20%** (selon taille des données)

---

### 6. 📡 Network Packet Size ⭐⭐⭐

**Problème :** Packet size par défaut = 4096 bytes (trop petit pour gros inserts)

**Solution :**
```php
// Dans config/database.php
'sqlsrv' => [
    'options' => [
        'PDO::SQLSRV_ATTR_PACKET_SIZE' => 32768, // 32KB au lieu de 4KB
    ],
],
```

**Gain estimé : 10-15%** (moins de round-trips réseau)

---

### 7. 🎯 Disable Change Tracking ⭐⭐⭐

**Problème :** Si change tracking activé, chaque changement est tracké

**Solution :**
```sql
-- Avant import
ALTER TABLE client_commercial_data DISABLE CHANGE_TRACKING;

-- Après import
ALTER TABLE client_commercial_data ENABLE CHANGE_TRACKING;
```

**Gain estimé : 15-20%**

---

### 8. 💿 Pre-Allocate Table Space ⭐⭐

**Problème :** Table grandit au fur et à mesure (allocation disque coûteuse)

**Solution :**
```sql
-- Estimer la taille finale et pré-allouer
ALTER DATABASE tableau 
MODIFY FILE (
    NAME = tableau_data, 
    SIZE = 10GB  -- Taille estimée finale
);
```

**Gain estimé : 5-10%** (évite extensions multiples)

---

### 9. 🔥 Utiliser BULK INSERT API ⭐⭐⭐⭐⭐

**Problème :** INSERT VALUES est moins efficace que BULK INSERT

**Solution :** Utiliser `BULK INSERT` de SQL Server

```php
// Sauvegarder le batch dans un fichier temporaire
$tempFile = tempnam(sys_get_temp_dir(), 'bulk');
file_put_contents($tempFile, implode("\n", $csvLines));

// Bulk insert ultra-rapide
DB::statement("
    BULK INSERT {$tableName}
    FROM '{$tempFile}'
    WITH (
        DATAFILETYPE = 'char',
        FIELDTERMINATOR = '\t',
        ROWTERMINATOR = '\n',
        TABLOCK,
        BATCHSIZE = 5000
    )
");

unlink($tempFile);
```

**Gain estimé : 60-80%** (API native SQL Server)

---

### 10. ⚡ Connection Pooling Optimisé ⭐⭐

**Problème :** Nouvelle connexion = overhead

**Solution :**
```php
// config/database.php
'sqlsrv' => [
    'options' => [
        'ConnectionPooling' => true,
        'MinPoolSize' => 5,
        'MaxPoolSize' => 20,
    ],
],
```

**Gain estimé : 5-10%**

---

## 📊 Gains Cumulatifs Estimés

### Scénario 1 : Table Simple (Sans Triggers/Compression)

| Optimisation | Gain Additionnel | Temps (380k lignes) |
|--------------|------------------|---------------------|
| ULTRA actuel | - | 2-3 min |
| + TABLOCK | 40% | 1m 30s |
| + Update Stats OFF | 20% | 1m 12s |
| + Batch Transactions | 15% | 1m 02s |
| + Network Packet | 10% | **56s** |

**Total : 95% plus rapide que v1**

### Scénario 2 : Avec BULK INSERT API

| Optimisation | Gain | Temps (380k lignes) |
|--------------|------|---------------------|
| ULTRA actuel | - | 2-3 min |
| + BULK INSERT | 70% | **45-50s** |

**Total : 97% plus rapide que v1**

---

## 🎯 Matrice de Priorité

| Optimisation | Impact | Complexité | Risque | Priorité |
|--------------|--------|------------|--------|----------|
| BULK INSERT API | 🔥🔥🔥🔥🔥 | Moyenne | Faible | 🥇 |
| TABLOCK | 🔥🔥🔥🔥🔥 | Faible | Faible | 🥇 |
| Disable Triggers | 🔥🔥🔥🔥🔥 | Faible | Moyen | 🥇 |
| Update Stats OFF | 🔥🔥🔥🔥 | Faible | Faible | 🥈 |
| Batch Transactions | 🔥🔥🔥🔥 | Moyenne | Faible | 🥈 |
| Network Packet | 🔥🔥🔥 | Faible | Faible | 🥈 |
| Disable Change Tracking | 🔥🔥🔥 | Faible | Faible | 🥉 |
| Disable Compression | 🔥🔥🔥 | Faible | Moyen | 🥉 |
| Pre-allocate Space | 🔥🔥 | Moyenne | Faible | 🥉 |
| Connection Pooling | 🔥🔥 | Faible | Faible | 🥉 |

---

## 💡 Recommandations d'Implémentation

### Phase 1 : Quick Wins Critiques (2-4h)
```php
1. TABLOCK hint dans les inserts
2. Désactivation Update Statistics
3. Désactivation Triggers (si présents)
4. Network packet size
```

**Gain estimé : 70-90%** (en plus des optimisations actuelles)
**Temps : 380k lignes en 45s-1min**

### Phase 2 : BULK INSERT (1 jour)
```php
5. Implémenter BULK INSERT API native
```

**Gain estimé : 80-95%** (en plus)
**Temps : 380k lignes en 30-45s**

### Phase 3 : Fine-Tuning (optionnel)
```php
6. Batch transactions explicites
7. Change tracking désactivé
8. Compression désactivée temporairement
```

**Gain marginal : 5-10%** supplémentaire

---

## 🚀 Objectif Final Réaliste

**Avec toutes les optimisations SQL Server avancées :**

| Métrique | Valeur |
|----------|--------|
| **380 000 lignes** | **30-45 secondes** |
| **Vitesse** | **8 000-12 000 lignes/sec** |
| **Gain vs v1** | **98%** |

---

## ⚠️ Précautions

### Triggers
- ⚠️ Vérifier qu'ils ne sont pas critiques (audit, validation)
- ✅ Option : Re-exécuter les triggers après import

### Compression
- ⚠️ Rebuild de table = temps + espace disque
- ✅ Uniquement si table déjà compressée

### BULK INSERT
- ⚠️ Format fichier doit être exact (tab-delimited)
- ⚠️ Chemin fichier doit être accessible par SQL Server
- ✅ Solution : Utiliser UNC path ou monter volume

### Change Tracking
- ⚠️ Si utilisé pour synchronisation
- ✅ Alternative : Forcer sync complète après import

---

## 🔬 Commande EXPERIMENTAL

Pour tester toutes les optimisations :

```bash
php artisan tableau:import-experimental ClientCommercial \
  --use-bulk-insert \
  --disable-triggers \
  --tablock \
  --truncate
```

**Attendu : 380k lignes en < 1 minute** 🚀🚀🚀

---

## 📝 Notes Importantes

1. **BULK INSERT** est LA grosse optimisation restante (+70%)
2. **TABLOCK** est gratuit et très efficace (+40%)
3. Les autres sont des gains marginaux mais cumulatifs
4. Toujours tester en DEV avant PROD
5. Documenter les optimisations appliquées

Prêt à implémenter le mode EXPERIMENTAL ? 🎯
