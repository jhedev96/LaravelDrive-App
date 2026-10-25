/**
 * Admin (drive) SPA entry — mounts on the /drive blade shell (#root).
 * Router base '/drive', legacy routes land module-by-module in later
 * slices; slice 1 proves the Vite + Vuetify 4 + Pinia pipeline.
 */
import { createPinia } from 'pinia';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

import AdminApp from '@ac/App.vue';
import { createDriveVuetify } from '@common/vuetify';

const router = createRouter({
    history: createWebHistory('/drive'),
    routes: [
        // placeholder until MyDashboard ports over (W5.3)
        {
            path: '',
            name: 'dashboard',
            component: () => import('@ac/components/Dashboard/Dashboard.vue'),
        },
        { path: '/:pathMatch(.*)*', redirect: { name: 'dashboard' } },
    ],
    scrollBehavior: () => ({ left: 0, top: 0 }),
});

// legacy guard semantic: without a session user the SPA refuses to navigate
router.beforeEach(() => {
    const user = (window as unknown as { LD?: { user?: unknown } }).LD?.user;

    return user ? true : false;
});

const app = createApp(AdminApp).use(createPinia()).use(createDriveVuetify());

void app.use(router).mount('#root');
