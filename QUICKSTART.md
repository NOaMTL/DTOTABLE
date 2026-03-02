# Guide de Démarrage Rapide

## Prérequis
- PHP 8.2+
- Composer
- Node.js 18+
- SQLite ou MySQL

## Installation

### 1. Dépendances
```bash
# Dépendances PHP
composer install

# Dépendances JavaScript
npm install
```

### 2. Configuration
```bash
# Copier .env
cp .env.example .env

# Générer clé application
php artisan key:generate

# Configurer base de données dans .env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

### 3. Base de données
```bash
# Créer fichier SQLite
touch database/database.sqlite

# Exécuter migrations
php artisan migrate

# (Optionnel) Données de test
php artisan db:seed
```

### 4. Storage
```bash
# Créer lien symbolique
php artisan storage:link

# Créer dossier exports
mkdir -p storage/app/public/exports
```

### 5. Générer routes JS
```bash
php artisan ziggy:generate
```

## Démarrage

### Serveur de développement
```bash
# Terminal 1: Laravel
php artisan serve

# Terminal 2: Vite (compilation assets)
npm run dev
```

Accéder à: http://localhost:8000

## Structure des Fichiers Vue à Créer

### 1. Page Principale Tableau
**Fichier:** `resources/js/Pages/Tableau/Index.vue`

```vue
<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';

const props = defineProps({
    filters: Object,
});

const gridOptions = ref({
    rowModelType: 'serverSide',
    pagination: true,
    paginationPageSize: 100,
    cacheBlockSize: 100,
    columnDefs: [
        { field: 'reference', headerName: 'Référence', filter: 'agTextColumnFilter' },
        { 
            field: 'date_operation_formatted', 
            headerName: 'Date', 
            filter: 'agDateColumnFilter',
            width: 120 
        },
        { field: 'libelle', headerName: 'Libellé', filter: 'agTextColumnFilter', flex: 1 },
        { 
            field: 'montant_formatted', 
            headerName: 'Montant', 
            filter: 'agNumberColumnFilter',
            type: 'rightAligned',
            width: 130
        },
        { field: 'devise', headerName: 'Devise', filter: 'agSetColumnFilter', width: 90 },
        { field: 'compte', headerName: 'Compte', filter: 'agTextColumnFilter', width: 150 },
        { field: 'agence', headerName: 'Agence', filter: 'agTextColumnFilter', width: 120 },
        { field: 'type_operation', headerName: 'Type', filter: 'agSetColumnFilter', width: 120 },
        { field: 'statut', headerName: 'Statut', filter: 'agSetColumnFilter', width: 110 },
    ],
    defaultColDef: {
        sortable: true,
        filter: true,
        resizable: true,
    },
    onFilterChanged: (params) => {
        console.log('Filter changed:', params.api.getFilterModel());
    },
});

const onGridReady = (params) => {
    const datasource = {
        getRows: async (params) => {
            const filterModel = params.request.filterModel;
            const startRow = params.request.startRow;
            const endRow = params.request.endRow;
            const page = Math.floor(startRow / 100) + 1;

            try {
                const response = await axios.post('/api/tableau/data', {
                    filterModel,
                    page,
                    perPage: 100,
                });

                params.success({
                    rowData: response.data.data,
                    rowCount: response.data.total,
                });
            } catch (error) {
                console.error('Error loading data:', error);
                params.fail();
            }
        },
    };

    params.api.setGridOption('serverSideDatasource', datasource);
};

const exportPdf = async () => {
    const filterModel = gridOptions.value.api?.getFilterModel() || {};
    
    try {
        const response = await axios.post('/api/export/pdf', {
            filterModel,
        });
        
        if (response.data.success) {
            window.location.href = response.data.download_url;
        }
    } catch (error) {
        console.error('Export error:', error);
        alert('Erreur lors de l\'export');
    }
};
</script>

<template>
    <div class="container mx-auto p-4">
        <div class="mb-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">Tableau des Opérations Bancaires</h1>
            <div class="space-x-2">
                <button 
                    @click="exportPdf" 
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                >
                    Export PDF
                </button>
                <a 
                    href="/import" 
                    class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 inline-block"
                >
                    Importer
                </a>
            </div>
        </div>

        <div class="ag-theme-alpine" style="height: 600px;">
            <AgGridVue
                :gridOptions="gridOptions"
                @grid-ready="onGridReady"
            />
        </div>
    </div>
</template>
```

### 2. Page Import
**Fichier:** `resources/js/Pages/Import/Create.vue`

```vue
<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const form = ref({
    file: null,
});

const errors = ref({});
const uploading = ref(false);

const handleFileChange = (event) => {
    form.value.file = event.target.files[0];
};

const submitForm = () => {
    if (!form.value.file) {
        errors.value.file = 'Veuillez sélectionner un fichier';
        return;
    }

    uploading.value = true;
    errors.value = {};

    const formData = new FormData();
    formData.append('file', form.value.file);

    router.post('/import', formData, {
        onSuccess: () => {
            uploading.value = false;
            alert('Import réussi!');
        },
        onError: (err) => {
            uploading.value = false;
            errors.value = err;
        },
    });
};
</script>

<template>
    <div class="container mx-auto p-4 max-w-2xl">
        <h1 class="text-2xl font-bold mb-6">Importer des Données</h1>

        <div class="bg-white shadow rounded-lg p-6">
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">
                    Fichier (TXT, CSV, TSV)
                </label>
                <input 
                    type="file" 
                    @change="handleFileChange"
                    accept=".txt,.csv,.tsv"
                    class="w-full px-3 py-2 border rounded"
                />
                <p v-if="errors.file" class="text-red-500 text-sm mt-1">
                    {{ errors.file }}
                </p>
            </div>

            <div class="bg-blue-50 p-4 rounded mb-4">
                <h3 class="font-medium mb-2">Format attendu:</h3>
                <pre class="text-sm">référence;date;libellé;montant;devise;compte;agence;type;statut</pre>
                <p class="text-sm text-gray-600 mt-2">
                    Délimiteurs supportés: ; | , tab
                </p>
            </div>

            <div class="flex justify-between">
                <a 
                    href="/tableau" 
                    class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
                >
                    Annuler
                </a>
                <button 
                    @click="submitForm"
                    :disabled="uploading"
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50"
                >
                    {{ uploading ? 'Import en cours...' : 'Importer' }}
                </button>
            </div>
        </div>
    </div>
</template>
```

### 3. Configuration Inertia
**Fichier:** `resources/js/app.js`

```js
import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue')
    ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
```

## Format Fichier d'Import

### Exemple CSV
```csv
REF001;2024-01-15;Virement salaire;3500.00;EUR;FR7612345678901234567890123;AGENCE01;CREDIT;completed
REF002;2024-01-16;Prélèvement loyer;-850.50;EUR;FR7612345678901234567890123;AGENCE01;DEBIT;completed
```

### Exemple TSV (tab-separated)
```
REF001	2024-01-15	Virement salaire	3500.00	EUR	FR7612345678901234567890123	AGENCE01	CREDIT	completed
REF002	2024-01-16	Prélèvement loyer	-850.50	EUR	FR7612345678901234567890123	AGENCE01	DEBIT	completed
```

## Formats Date Supportés
- YYYY-MM-DD (2024-01-15)
- DD/MM/YYYY (15/01/2024)
- DD-MM-YYYY (15-01-2024)
- YYYY/MM/DD (2024/01/15)
- DD.MM.YYYY (15.01.2024)

## API Testing

### Tester les Routes

```bash
# Récupérer les données (avec auth Sanctum)
curl -X POST http://localhost:8000/api/tableau/data \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"filterModel":{},"page":1,"perPage":100}'

# Compter les résultats
curl -X POST http://localhost:8000/api/tableau/count \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"compte":"FR7612345678901234567890123"}'

# Export PDF
curl -X POST http://localhost:8000/api/export/pdf \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"date_debut":"2024-01-01","date_fin":"2024-12-31"}'
```

## Problèmes Courants

### 1. Erreur "Class not found"
```bash
composer dump-autoload
```

### 2. Erreur permissions storage
```bash
chmod -R 775 storage bootstrap/cache
```

### 3. AGGrid ne charge pas
Vérifier que `npm run dev` est en cours d'exécution

### 4. Routes API 404
Vérifier que `api.php` est bien chargé dans `bootstrap/app.php`

## Support

Pour plus d'informations, consulter:
- README_ARCHITECTURE.md (architecture détaillée)
- Documentation Laravel: https://laravel.com/docs/12.x
- Documentation AGGrid: https://www.ag-grid.com/vue-data-grid/
- Documentation Inertia: https://inertiajs.com/
