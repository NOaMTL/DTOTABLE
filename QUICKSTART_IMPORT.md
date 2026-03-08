# 🚀 Guide Rapide - Import Optimisé

## ⚡ Quick Start

### 1. Placer vos fichiers
```bash
# Copier vos 5 fichiers dans:
storage/imports/
```

### 2. Lancer l'import
```bash
# Import simple
php artisan tableau:import

# OU import depuis un autre dossier
php artisan tableau:import C:\chemin\vers\fichiers

# OU import ultra-rapide (vide la table + supprime indexes)
php artisan tableau:import --truncate --drop-indexes
```

## 📊 Résultats attendus

**AVANT (PHP procédural):**
- 350 000 lignes = **12-15 heures** ⏳😭
- 1 INSERT par ligne
- Pas de transactions
- Fichiers chargés en mémoire

**APRÈS (Laravel optimisé):**
- 350 000 lignes = **3-5 minutes** ⚡🎉
- Bulk inserts (1000 lignes à la fois)
- Streaming des fichiers
- Indexes temporairement désactivés

### Gain de performance : **180x plus rapide** 🚀

## 📝 Exemples

### Test avec le fichier exemple
```bash
php artisan tableau:import
# Importe les 10 lignes d'exemple en < 1 seconde
```

### Production - Import initial
```bash
php artisan tableau:import C:\data\import --truncate --drop-indexes --chunk-size=2000
```

### Production - Import quotidien
```bash
php artisan tableau:import C:\data\import --truncate
```

## 🎯 Options

| Option | Description | Usage |
|--------|-------------|-------|
| `path` | Dossier des fichiers | `php artisan tableau:import /path` |
| `--truncate` | Vide la table avant | Pour réimport complet |
| `--drop-indexes` | Supprime les indexes | **Max performance** |
| `--chunk-size=N` | Taille des batchs | Par défaut: 1000 |

## 💡 Formats acceptés

**Délimiteurs auto-détectés:**
- `;` point-virgule
- `,` virgule (CSV)
- `\t` tabulation (TSV)
- `|` pipe

**Extensions acceptées:**
- `.txt`
- `.csv`
- `.dat`
- `.tsv`

## ⚠️ Important

1. **--drop-indexes** : À utiliser en dehors des heures d'utilisation (verrouille la table)
2. **--truncate** : Supprime TOUTES les données existantes
3. Les fichiers peuvent rester sur le disque (pas chargés en mémoire)

## 🆘 Support

Voir documentation complète : [IMPORT_OPTIMISE.md](IMPORT_OPTIMISE.md)
