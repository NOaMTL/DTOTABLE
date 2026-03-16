# 🚀 POC Query Builder - Guide de lancement

## Installation et Configuration

### 1. Migration de la base de données

Créer la table `clients` :

```bash
php artisan migrate
```

### 2. Génération des données de test

Créer 500 clients fictifs :

```bash
php artisan db:seed --class=ClientSeeder
```

### 3. Démarrer l'application

```bash
php artisan serve
```

### 4. Accéder à la POC

Ouvrez votre navigateur et accédez à :

```
http://localhost:8000/query-builder
```

(Si vous avez une authentification, connectez-vous d'abord)

---

## 🎯 Fonctionnalités implémentées

### Interface principale (3 colonnes)

1. **Colonne gauche** : Palette des champs disponibles
   - Champs groupés par catégorie
   - Drag & Drop vers la zone centrale

2. **Colonne centrale** : Constructeur de requête
   - Ajout/suppression de filtres
   - Opérateurs dynamiques selon le type de champ
   - Logique AND/OR entre les filtres
   - Recherche rapide en langage naturel

3. **Colonne droite** : Aperçu et actions
   - Compteur en temps réel
   - Aperçu des 10 premiers résultats
   - Boutons d'export et sauvegarde

### Fonctionnalités avancées

✅ **Recherche rapide** : Tapez "âge > 30" ou "ville = Paris" pour créer automatiquement un filtre

✅ **Drag & Drop** : Glissez un champ depuis la palette vers la zone de filtres

✅ **Comptage temps réel** : Voir le nombre de clients trouvés pendant la création des filtres

✅ **Export CSV** : Télécharger les résultats en CSV

✅ **Debug SQL** : Voir la requête SQL générée

✅ **Opérateurs intelligents** : Selon le type de champ (text, number, date, boolean, select)

✅ **Table de résultats** : Affichage complet avec pagination

---

## 📝 Exemples d'utilisation

### Exemple 1 : Clients avec bon solde
- Champ : `solde_compte`
- Opérateur : `>`
- Valeur : `50000`

### Exemple 2 : Combinaison AND
- Filtre 1 : `type_compte = premium` [ET]
- Filtre 2 : `ville = Paris`

### Exemple 3 : Combinaison OR
- Filtre 1 : `region = Île-de-France` [OU]
- Filtre 2 : `region = Provence-Alpes-Côte d'Azur`

### Exemple 4 : Recherche rapide
Dans le champ de recherche rapide, tapez :
- `âge > 30`
- `nom contient Dupont`
- `solde_compte >= 10000`

---

## 🛠️ Structure technique

### Backend (Laravel)
- **Model** : `App\Models\Client`
- **Controller** : `App\Http\Controllers\QueryBuilderController`
- **Service** : `App\Services\DynamicQueryBuilderService`
- **Migration** : `database/migrations/2026_03_15_000001_create_clients_table.php`
- **Factory** : `database/factories/ClientFactory.php`
- **Seeder** : `database/seeders/ClientSeeder.php`

### Frontend (Vue.js + Inertia)
- **Page principale** : `resources/js/Pages/QueryBuilder/Index.vue`

### Routes
```php
GET  /query-builder          - Interface principale
POST /query-builder/execute  - Exécute la requête
POST /query-builder/count    - Compte les résultats
POST /query-builder/sql      - Génère le SQL
POST /query-builder/parse    - Parse recherche naturelle
POST /query-builder/export   - Export CSV
```

---

## 🎨 Améliorations futures possibles

1. **Groupes de conditions** : Parenthèses logiques (A AND B) OR (C AND D)
2. **Favoris** : Sauvegarder des combinaisons de filtres
3. **Templates prédéfinis** : "Clients VIP", "Clients inactifs", etc.
4. **Export Excel** : En plus du CSV
5. **Graphiques** : Visualisation des données
6. **Partage** : Partager une recherche avec un collègue
7. **Historique** : Voir les dernières recherches effectuées
8. **Auto-complétion** : Suggestions de valeurs pour les champs

---

## 🐛 Dépannage

### Erreur "Table 'clients' doesn't exist"
→ Lancez `php artisan migrate`

### Aucun client ne s'affiche
→ Lancez `php artisan db:seed --class=ClientSeeder`

### Erreur 404 sur /query-builder
→ Vérifiez que les routes sont bien dans `routes/web.php`

### Erreur d'authentification
→ La route est protégée par le middleware `auth`, connectez-vous d'abord

---

## 📧 Support

Pour toute question ou amélioration, n'hésitez pas à demander !
