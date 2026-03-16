<template>
    <div class="min-h-screen bg-gray-50 p-6">
        <Toast />
        
        <div class="max-w-7xl mx-auto">
            <!-- Header avec PrimeVue -->
            <Card class="mb-6">
                <template #title>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="pi pi-filter text-4xl text-primary"></i>
                            <div>
                                <h1 class="text-3xl font-bold">Query Builder Avancé</h1>
                                <p class="text-gray-600 text-sm mt-1">Créez des requêtes complexes avec groupes imbriqués</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                label="Templates"
                                icon="pi pi-book"
                                severity="secondary"
                                @click="showTemplates = true"
                                outlined
                            />
                            <Button
                                label="Réinitialiser"
                                icon="pi pi-refresh"
                                severity="secondary"
                                @click="resetFilters"
                                outlined
                            />
                        </div>
                    </div>
                </template>
                <template #content>
                    <!-- Recherche rapide -->
                    <div class="flex gap-2">
                        <InputText
                            v-model="quickSearch"
                            @keyup.enter="parseQuickSearch"
                            placeholder='Recherche rapide : ex "âge > 30", "nom contient Dupont"...'
                            class="flex-1"
                        />
                        <Button
                            label="Ajouter"
                            icon="pi pi-bolt"
                            @click="parseQuickSearch"
                        />
                    </div>
                </template>
            </Card>

            <div class="grid grid-cols-12 gap-6">
                <!-- Colonne gauche - Palette des champs -->
                <div class="col-span-3">
                    <Card>
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-list"></i>
                                Champs disponibles
                            </div>
                        </template>
                        <template #content>
                            <!-- Recherche dans les champs -->
                            <div class="mb-4">
                                <InputText
                                    v-model="fieldSearch"
                                    placeholder="Filtrer les champs..."
                                    class="w-full"
                                >
                                    <template #prefix>
                                        <i class="pi pi-search"></i>
                                    </template>
                                </InputText>
                            </div>
                            
                            <div class="space-y-4 max-h-[calc(100vh-300px)] overflow-y-auto">
                                <div v-for="(category, categoryKey) in filteredFields" :key="categoryKey">
                                    <Divider align="left">
                                        <Chip :label="category.label" />
                                    </Divider>
                                    <div class="space-y-2">
                                        <Chip
                                            v-for="(field, fieldKey) in category.fields"
                                            :key="fieldKey"
                                            :label="field.label"
                                            draggable="true"
                                            @dragstart="onDragStart(categoryKey, fieldKey)"
                                            class="cursor-move w-full"
                                        >
                                            <template #icon>
                                                <i class="pi pi-bars mr-2"></i>
                                            </template>
                                        </Chip>
                                    </div>
                                </div>
                            </div>

                            <div v-if="Object.keys(filteredFields).length === 0" class="text-center py-8 text-gray-400">
                                <i class="pi pi-inbox text-4xl mb-2"></i>
                                <p class="text-sm">Aucun champ trouvé</p>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Colonne centrale - Constructeur -->
                <div class="col-span-6">
                    <Card>
                        <template #title>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-cog"></i>
                                    Constructeur de Requête
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        label="Filtre"
                                        icon="pi pi-plus"
                                        size="small"
                                        severity="success"
                                        @click="addFilterToGroup('root')"
                                        outlined
                                    />
                                    <Button
                                        label="Groupe"
                                        icon="pi pi-sitemap"
                                        size="small"
                                        severity="secondary"
                                        @click="addGroupToGroup('root')"
                                        outlined
                                    />
                                </div>
                            </div>
                        </template>
                        <template #content>
                            <!-- Zone de drop principale -->
                            <div
                                v-if="rootGroup.filters.length === 0"
                                @drop.prevent="onDrop('root')"
                                @dragover.prevent
                                class="border-4 border-dashed border-gray-300 rounded-xl p-16 text-center text-gray-400 hover:border-primary hover:bg-blue-50 transition-all"
                            >
                                <i class="pi pi-cloud-upload text-6xl mb-4"></i>
                                <p class="text-lg font-semibold mb-2">Glissez-déposez des champs ici</p>
                                <p class="text-sm">ou utilisez la recherche rapide / les templates</p>
                            </div>

                            <!-- Groupe racine avec filtres -->
                            <FilterGroup
                                v-else
                                :group="rootGroup"
                                :fields="props.fields"
                                :level="0"
                                :is-first="true"
                                @update="countResults"
                                @add-filter="addFilterToGroup"
                                @add-group="addGroupToGroup"
                            />

                            <!-- Actions -->
                            <div class="mt-6 flex gap-3">
                                <Button
                                    label="Exécuter la Requête"
                                    icon="pi pi-play"
                                    :loading="loading"
                                    :disabled="rootGroup.filters.length === 0"
                                    @click="executeQuery"
                                    class="flex-1"
                                    size="large"
                                />
                                <Button
                                    label="SQL"
                                    icon="pi pi-code"
                                    severity="secondary"
                                    @click="generateSql"
                                    outlined
                                />
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Colonne droite - Résultats -->
                <div class="col-span-3">
                    <Card>
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-chart-bar"></i>
                                Résultats
                            </div>
                        </template>
                        <template #content>
                            <div class="text-center mb-6 p-4 bg-primary-50 rounded-lg">
                                <div class="text-5xl font-bold text-primary">
                                    {{ count.toLocaleString() }}
                                </div>
                                <div class="text-sm text-gray-600 mt-1">clients trouvés</div>
                            </div>

                            <div class="space-y-2">
                                <Button
                                    label="Exporter CSV"
                                    icon="pi pi-download"
                                    severity="success"
                                    :disabled="count === 0"
                                    @click="exportResults"
                                    class="w-full"
                                    outlined
                                />
                                <Button
                                    label="Sauvegarder"
                                    icon="pi pi-save"
                                    severity="secondary"
                                    class="w-full"
                                    outlined
                                />
                            </div>

                            <!-- Aperçu -->
                            <div v-if="results.length > 0" class="mt-6">
                                <Divider align="left">
                                    <Chip label="Aperçu" />
                                </Divider>
                                <div class="space-y-2 max-h-96 overflow-y-auto">
                                    <Card
                                        v-for="client in results.slice(0, 10)"
                                        :key="client.id"
                                        class="p-2"
                                    >
                                        <template #content>
                                            <div class="text-sm">
                                                <div class="font-bold">{{ client.nom }} {{ client.prenom }}</div>
                                                <div class="text-gray-600 flex items-center gap-1 mt-1">
                                                    <i class="pi pi-map-marker text-xs"></i>
                                                    {{ client.ville }}
                                                </div>
                                                <div class="font-semibold mt-1" :class="parseFloat(client.solde_compte) > 0 ? 'text-green-600' : 'text-red-600'">
                                                    {{ parseFloat(client.solde_compte).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) }}€
                                                </div>
                                            </div>
                                        </template>
                                    </Card>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>

            <!-- Modal Templates -->
            <Dialog
                v-model:visible="showTemplates"
                modal
                header="Templates de Requêtes"
                :style="{ width: '50rem' }"
            >
                <div class="grid grid-cols-1 gap-4">
                    <Card
                        v-for="(template, index) in queryTemplates"
                        :key="index"
                        @click="applyTemplate(template)"
                        class="cursor-pointer hover:shadow-lg transition-all"
                    >
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i :class="'pi ' + template.icon"></i>
                                {{ template.name }}
                            </div>
                        </template>
                        <template #content>
                            <p class="text-gray-600">{{ template.description }}</p>
                        </template>
                    </Card>
                </div>
            </Dialog>

            <!-- Modal SQL Debug -->
            <Dialog
                v-model:visible="showSqlDebug"
                modal
                header="SQL Généré"
                :style="{ width: '60rem' }"
            >
                <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm font-mono">{{ sqlQuery }}</pre>
            </Dialog>

            <!-- Table complète des résultats -->
            <Card v-if="results.length > 0" class="mt-6">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-table"></i>
                        Résultats Complets
                    </div>
                </template>
                <template #content>
                    <DataTable
                        :value="results"
                        stripedRows
                        showGridlines
                        paginator
                        :rows="perPage"
                        :totalRecords="count"
                        @page="currentPage = $event.page + 1; executeQuery()"
                        :rowsPerPageOptions="[10, 25, 50, 100]"
                    >
                        <Column field="numero_client" header="N° Client" sortable></Column>
                        <Column field="nom" header="Nom" sortable></Column>
                        <Column field="prenom" header="Prénom" sortable></Column>
                        <Column field="email" header="Email" sortable></Column>
                        <Column field="ville" header="Ville" sortable></Column>
                        <Column field="solde_compte" header="Solde" sortable>
                            <template #body="slotProps">
                                <span :class="parseFloat(slotProps.data.solde_compte) > 0 ? 'text-green-600' : 'text-red-600'" class="font-bold">
                                    {{ parseFloat(slotProps.data.solde_compte).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) }}€
                                </span>
                            </template>
                        </Column>
                        <Column field="type_compte" header="Type">
                            <template #body="slotProps">
                                <Tag
                                    :value="slotProps.data.type_compte"
                                    :severity="slotProps.data.type_compte === 'premium' ? 'warn' : slotProps.data.type_compte === 'professionnel' ? 'info' : 'secondary'"
                                />
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>
    </div>
</template>

<style scoped>
.space-y-2 > * + * {
    margin-top: 0.5rem;
}

.space-y-4 > * + * {
    margin-top: 1rem;
}
</style>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash';
import FilterGroup from '../../Components/FilterGroup.vue';

const props = defineProps({
    fields: {
        type: Object,
        required: true
    },
    totalClients: {
        type: Number,
        default: 0
    }
});

// État principal
const rootGroup = ref({
    id: 'root',
    type: 'group',
    groupLogic: 'and',
    filters: []
});

const results = ref([]);
const count = ref(0);
const loading = ref(false);
const currentPage = ref(1);
const perPage = ref(50);
const totalPages = ref(1);
const showSqlDebug = ref(false);
const sqlQuery = ref('');
const quickSearch = ref('');
const fieldSearch = ref('');
const showTemplates = ref(false);

// Drag and drop
const draggedField = ref(null);

// Templates de requêtes prédéfinies
const queryTemplates = ref([
    {
        name: '🌟 Clients VIP (Premium + solde élevé)',
        description: 'Clients premium avec solde > 50 000€',
        filters: [
            { field: 'type_compte', operator: '=', value: 'premium' },
            { field: 'solde_compte', operator: '>', value: '50000', logic: 'and' }
        ]
    },
    {
        name: '💰 Clients avec crédit immobilier actif',
        description: 'Clients ayant un crédit immobilier en cours',
        filters: [
            { field: 'a_credit_immobilier', operator: '=', value: true },
            { field: 'credit_en_cours', operator: '>', value: '0', logic: 'and' }
        ]
    },
    {
        name: '😴 Clients inactifs',
        description: 'Aucune transaction depuis 30 jours',
        filters: [
            { field: 'derniere_transaction', operator: '<', value: new Date(Date.now() - 30*24*60*60*1000).toISOString().split('T')[0] }
        ]
    },
    {
        name: '🎯 Jeunes actifs Île-de-France',
        description: 'Clients 25-40 ans en IdF avec revenus > 3000€',
        filters: [
            { 
                type: 'group', 
                groupLogic: 'and',
                filters: [
                    { field: 'age', operator: '>=', value: '25' },
                    { field: 'age', operator: '<=', value: '40', logic: 'and' }
                ]
            },
            { field: 'region', operator: '=', value: 'Île-de-France', logic: 'and' },
            { field: 'revenus_mensuels', operator: '>', value: '3000', logic: 'and' }
        ]
    },
    {
        name: '🔍 Clients à potentiel (Bronze/Silver avec bon solde)',
        description: 'Clients non-premium mais avec bon solde',
        filters: [
            {
                type: 'group',
                groupLogic: 'or',
                filters: [
                    { field: 'categorie_client', operator: '=', value: 'bronze' },
                    { field: 'categorie_client', operator: '=', value: 'silver', logic: 'or' }
                ]
            },
            { field: 'solde_compte', operator: '>', value: '30000', logic: 'and' }
        ]
    }
]);

// Champs filtrés pour la recherche
const filteredFields = computed(() => {
    if (!fieldSearch.value) return props.fields;
    
    const search = fieldSearch.value.toLowerCase();
    const result = {};
    
    for (const [categoryKey, category] of Object.entries(props.fields)) {
        const filteredCategoryFields = {};
        
        for (const [fieldKey, field] of Object.entries(category.fields)) {
            if (field.label.toLowerCase().includes(search) || fieldKey.toLowerCase().includes(search)) {
                filteredCategoryFields[fieldKey] = field;
            }
        }
        
        if (Object.keys(filteredCategoryFields).length > 0) {
            result[categoryKey] = {
                ...category,
                fields: filteredCategoryFields
            };
        }
    }
    
    return result;
});

// Génère un ID unique
const generateId = () => {
    return `item_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
};

// Ajoute un filtre à un groupe
const addFilterToGroup = (groupId) => {
    const group = findGroupById(rootGroup.value, groupId);
    if (group) {
        const newFilter = {
            id: generateId(),
            type: 'filter',
            field: '',
            operator: '=',
            value: '',
            logic: group.filters.length > 0 ? 'and' : null
        };
        group.filters.push(newFilter);
    }
};

// Ajoute un sous-groupe
const addGroupToGroup = (parentGroupId) => {
    const parentGroup = findGroupById(rootGroup.value, parentGroupId);
    if (parentGroup) {
        const newGroup = {
            id: generateId(),
            type: 'group',
            groupLogic: 'and',
            logic: parentGroup.filters.length > 0 ? 'and' : null,
            filters: []
        };
        parentGroup.filters.push(newGroup);
    }
};

// Trouve un groupe par ID (récursif)
const findGroupById = (group, id) => {
    if (group.id === id) return group;
    
    for (const item of group.filters) {
        if (item.type === 'group') {
            const found = findGroupById(item, id);
            if (found) return found;
        }
    }
    
    return null;
};

// Compte les résultats (debounced)
const countResults = debounce(async () => {
    if (rootGroup.value.filters.length === 0) {
        count.value = props.totalClients;
        return;
    }

    try {
        const response = await axios.post('/query-builder/count', {
            filters: [rootGroup.value]
        });
        count.value = response.data.count;
    } catch (error) {
        console.error('Erreur lors du comptage:', error);
    }
}, 500);

// Exécute la requête
const executeQuery = async () => {
    loading.value = true;
    try {
        const response = await axios.post('/query-builder/execute', {
            filters: [rootGroup.value],
            page: currentPage.value,
            per_page: perPage.value
        });
        
        results.value = response.data.data;
        count.value = response.data.total;
        totalPages.value = response.data.last_page;
    } catch (error) {
        console.error('Erreur lors de l\'exécution:', error);
        alert('Erreur lors de l\'exécution de la requête');
    } finally {
        loading.value = false;
    }
};

// Génère le SQL
const generateSql = async () => {
    try {
        const response = await axios.post('/query-builder/sql', {
            filters: [rootGroup.value]
        });
        sqlQuery.value = response.data.sql;
        showSqlDebug.value = true;
    } catch (error) {
        console.error('Erreur lors de la génération du SQL:', error);
    }
};

// Parse la recherche rapide
const parseQuickSearch = async () => {
    if (!quickSearch.value.trim()) return;
    
    try {
        const response = await axios.post('/query-builder/parse', {
            search: quickSearch.value
        });
        
        if (response.data.success && response.data.filter) {
            const parsed = response.data.filter;
            const newFilter = {
                id: generateId(),
                type: 'filter',
                field: parsed.field,
                operator: parsed.operator,
                value: parsed.value,
                logic: rootGroup.value.filters.length > 0 ? 'and' : null
            };
            rootGroup.value.filters.push(newFilter);
            quickSearch.value = '';
        }
    } catch (error) {
        console.error('Erreur lors du parsing:', error);
    }
};

// Applique un template
const applyTemplate = (template) => {
    rootGroup.value.filters = template.filters.map(f => ({
        ...f,
        id: generateId()
    }));
    showTemplates.value = false;
    countResults();
};

// Réinitialise les filtres
const resetFilters = () => {
    rootGroup.value.filters = [];
    results.value = [];
    count.value = props.totalClients;
};

// Export CSV
const exportResults = async () => {
    try {
        const response = await axios.post('/query-builder/export', {
            filters: [rootGroup.value],
            format: 'csv'
        }, {
            responseType: 'blob'
        });
        
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `clients_export_${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        link.remove();
    } catch (error) {
        console.error('Erreur lors de l\'export:', error);
        alert('Erreur lors de l\'export');
    }
};

// Drag & Drop
const onDragStart = (categoryKey, fieldKey) => {
    draggedField.value = { categoryKey, fieldKey };
};

const onDrop = (groupId) => {
    if (draggedField.value) {
        const { fieldKey } = draggedField.value;
        const group = findGroupById(rootGroup.value, groupId);
        if (group) {
            const config = getFieldConfig(fieldKey);
            const newFilter = {
                id: generateId(),
                type: 'filter',
                field: fieldKey,
                operator: config?.operators[0] || '=',
                value: '',
                logic: group.filters.length > 0 ? 'and' : null
            };
            group.filters.push(newFilter);
        }
        draggedField.value = null;
    }
};

const getFieldConfig = (fieldName) => {
    for (const category in props.fields) {
        const field = props.fields[category].fields[fieldName];
        if (field) return field;
    }
    return null;
};

// Watchers
watch(() => rootGroup.value.filters, () => {
    countResults();
}, { deep: true });

// Initialisation
count.value = props.totalClients;
</script>
