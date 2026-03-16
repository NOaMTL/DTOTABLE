import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';

// PrimeVue Components
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Card from 'primevue/card';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Paginator from 'primevue/paginator';
import Badge from 'primevue/badge';
import Chip from 'primevue/chip';
import Panel from 'primevue/panel';
import Divider from 'primevue/divider';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import ToastService from 'primevue/toastservice';

// PrimeIcons
import 'primeicons/primeicons.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        
        app.use(plugin);
        app.use(PrimeVue, {
            theme: {
                preset: Aura
            }
        });
        app.use(ToastService);
        
        // Register PrimeVue components globally
        app.component('Button', Button);
        app.component('InputText', InputText);
        app.component('Select', Select);
        app.component('Card', Card);
        app.component('Checkbox', Checkbox);
        app.component('Dialog', Dialog);
        app.component('DataTable', DataTable);
        app.component('Column', Column);
        app.component('Paginator', Paginator);
        app.component('Badge', Badge);
        app.component('Chip', Chip);
        app.component('Panel', Panel);
        app.component('Divider', Divider);
        app.component('Tag', Tag);
        app.component('Toast', Toast);
        
        return app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

