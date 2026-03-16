<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    group: {
        type: Object,
        required: true
    },
    fields: {
        type: Object,
        required: true
    },
    level: {
        type: Number,
        default: 0
    },
    isFirst: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update', 'remove', 'add-filter', 'add-group']);

// Couleurs par niveau pour distinguer visuellement
const levelColors = [
    { border: 'border-blue-300', bg: 'bg-blue-50', tag: 'bg-blue-600' },
    { border: 'border-purple-300', bg: 'bg-purple-50', tag: 'bg-purple-600' },
    { border: 'border-green-300', bg: 'bg-green-50', tag: 'bg-green-600' },
    { border: 'border-orange-300', bg: 'bg-orange-50', tag: 'bg-orange-600' },
];

const colorScheme = computed(() => levelColors[props.level % levelColors.length]);

const addFilter = () => {
    emit('add-filter', props.group.id);
};

const addSubGroup = () => {
    emit('add-group', props.group.id);
};

const removeGroup = () => {
    emit('remove', props.group.id);
};

const updateFilter = (filterId, field, value) => {
    const filter = props.group.filters.find(f => f.id === filterId);
    if (filter) {
        filter[field] = value;
        emit('update');
    }
};

const removeFilter = (filterId) => {
    const index = props.group.filters.findIndex(f => f.id === filterId);
    if (index > -1) {
        props.group.filters.splice(index, 1);
        emit('update');
    }
};

const toggleGroupLogic = () => {
    props.group.groupLogic = props.group.groupLogic === 'and' ? 'or' : 'and';
    emit('update');
};

const toggleLogic = (filterId) => {
    const filter = props.group.filters.find(f => f.id === filterId);
    if (filter && filter.logic) {
        filter.logic = filter.logic === 'and' ? 'or' : 'and';
        emit('update');
    }
};

const getFieldConfig = (fieldName) => {
    for (const category in props.fields) {
        const field = props.fields[category].fields[fieldName];
        if (field) return field;
    }
    return null;
};

const getOperatorsForField = (fieldName) => {
    const config = getFieldConfig(fieldName);
    return config?.operators || ['=', '!=', '>', '<', '>=', '<='];
};

const getInputType = (fieldName) => {
    const config = getFieldConfig(fieldName);
    return config?.type || 'text';
};

const getSelectOptions = (fieldName) => {
    const config = getFieldConfig(fieldName);
    return config?.options || [];
};
</script>

<template>
    <div 
        class="relative p-4 rounded-lg border-2 transition-all"
        :class="[colorScheme.border, colorScheme.bg, level > 0 ? 'ml-6' : '']"
    >
        <!-- En-tête du groupe -->
        <div class="flex items-center gap-3 mb-3">
            <!-- Badge niveau -->
            <div 
                class="px-2 py-1 rounded text-xs font-bold text-white"
                :class="colorScheme.tag"
            >
                Groupe {{ level + 1 }}
            </div>

            <!-- Logique du groupe -->
            <button
                v-if="!isFirst"
                @click="toggleGroupLogic"
                class="px-3 py-1 text-sm font-bold rounded transition"
                :class="group.groupLogic === 'or' 
                    ? 'bg-orange-500 text-white hover:bg-orange-600' 
                    : 'bg-blue-500 text-white hover:bg-blue-600'"
            >
                {{ group.groupLogic === 'or' ? 'OU' : 'ET' }} toutes ces conditions :
            </button>
            <span v-else class="text-sm font-semibold text-gray-700">
                Conditions :
            </span>

            <div class="flex-1"></div>

            <!-- Actions du groupe -->
            <button
                @click="addFilter"
                class="px-2 py-1 bg-green-500 text-white text-xs rounded hover:bg-green-600 transition"
                title="Ajouter un filtre"
            >
                + Filtre
            </button>
            <button
                @click="addSubGroup"
                class="px-2 py-1 bg-purple-500 text-white text-xs rounded hover:bg-purple-600 transition"
                title="Ajouter un sous-groupe"
            >
                + Groupe
            </button>
            <button
                v-if="!isFirst"
                @click="removeGroup"
                class="px-2 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600 transition"
                title="Supprimer ce groupe"
            >
                ❌
            </button>
        </div>

        <!-- Filtres et sous-groupes -->
        <div class="space-y-2">
            <template v-for="(item, index) in group.filters" :key="item.id">
                <!-- Sous-groupe -->
                <FilterGroup
                    v-if="item.type === 'group'"
                    :group="item"
                    :fields="fields"
                    :level="level + 1"
                    @update="emit('update')"
                    @remove="removeFilter"
                    @add-filter="emit('add-filter', $event)"
                    @add-group="emit('add-group', $event)"
                />

                <!-- Filtre simple -->
                <div v-else class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
                    <!-- Logique AND/OR -->
                    <div v-if="index > 0" class="mb-2">
                        <button
                            @click="toggleLogic(item.id)"
                            class="px-3 py-1 text-xs font-bold rounded transition"
                            :class="item.logic === 'or' 
                                ? 'bg-orange-100 text-orange-700 hover:bg-orange-200' 
                                : 'bg-blue-100 text-blue-700 hover:bg-blue-200'"
                        >
                            {{ item.logic?.toUpperCase() || 'ET' }}
                        </button>
                    </div>

                    <!-- Champs du filtre -->
                    <div class="grid grid-cols-12 gap-2 items-center">
                        <!-- Sélection du champ -->
                        <select
                            :value="item.field"
                            @input="updateFilter(item.id, 'field', $event.target.value)"
                            class="col-span-4 px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm"
                        >
                            <option value="">Choisir un champ...</option>
                            <optgroup v-for="(category, catKey) in fields" :key="catKey" :label="category.label">
                                <option v-for="(field, fieldKey) in category.fields" :key="fieldKey" :value="fieldKey">
                                    {{ field.label }}
                                </option>
                            </optgroup>
                        </select>

                        <!-- Opérateur -->
                        <select
                            :value="item.operator"
                            @input="updateFilter(item.id, 'operator', $event.target.value)"
                            :disabled="!item.field"
                            class="col-span-3 px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm disabled:bg-gray-100"
                        >
                            <option v-for="op in getOperatorsForField(item.field)" :key="op" :value="op">
                                {{ op }}
                            </option>
                        </select>

                        <!-- Valeur (selon le type) -->
                        <!-- Boolean -->
                        <div v-if="getInputType(item.field) === 'boolean'" class="col-span-4 flex items-center gap-2">
                            <input
                                type="checkbox"
                                :checked="item.value"
                                @change="updateFilter(item.id, 'value', $event.target.checked)"
                                class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500"
                            />
                            <span class="text-sm text-gray-600">Oui</span>
                        </div>

                        <!-- Select -->
                        <select
                            v-else-if="getInputType(item.field) === 'select'"
                            :value="item.value"
                            @input="updateFilter(item.id, 'value', $event.target.value)"
                            :disabled="!item.field"
                            class="col-span-4 px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm disabled:bg-gray-100"
                        >
                            <option value="">Sélectionner...</option>
                            <option v-for="option in getSelectOptions(item.field)" :key="option" :value="option">
                                {{ option }}
                            </option>
                        </select>

                        <!-- Between (deux valeurs) -->
                        <div v-else-if="item.operator === 'between'" class="col-span-4 flex gap-1">
                            <input
                                type="text"
                                :value="Array.isArray(item.value) ? item.value[0] : ''"
                                @input="updateFilter(item.id, 'value', [$event.target.value, Array.isArray(item.value) ? item.value[1] : ''])"
                                placeholder="Min"
                                class="flex-1 px-2 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm"
                            />
                            <span class="text-gray-500">-</span>
                            <input
                                type="text"
                                :value="Array.isArray(item.value) ? item.value[1] : ''"
                                @input="updateFilter(item.id, 'value', [Array.isArray(item.value) ? item.value[0] : '', $event.target.value])"
                                placeholder="Max"
                                class="flex-1 px-2 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm"
                            />
                        </div>

                        <!-- Input standard -->
                        <input
                            v-else
                            :type="getInputType(item.field) === 'number' ? 'number' : getInputType(item.field) === 'date' ? 'date' : 'text'"
                            :value="item.value"
                            @input="updateFilter(item.id, 'value', $event.target.value)"
                            :disabled="!item.field"
                            placeholder="Valeur..."
                            class="col-span-4 px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm disabled:bg-gray-100"
                        />

                        <!-- Bouton supprimer -->
                        <button
                            @click="removeFilter(item.id)"
                            class="col-span-1 px-2 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition text-sm"
                            title="Supprimer ce filtre"
                        >
                            🗑️
                        </button>
                    </div>
                </div>
            </template>

            <!-- Message si vide -->
            <div v-if="group.filters.length === 0" class="text-center py-8 text-gray-400 text-sm">
                Aucun filtre. Cliquez sur "+ Filtre" ou glissez un champ ici.
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Animations pour les groupes */
.group-enter-active,
.group-leave-active {
    transition: all 0.3s ease;
}

.group-enter-from,
.group-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
