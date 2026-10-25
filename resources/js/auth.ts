/**
 * Auth SPA entry — light shell, real login/register port is a later slice.
 */
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

import AuthApp from '@auth/App.vue';
import { createDriveVuetify } from '@common/vuetify';

const router = createRouter({
    history: createWebHistory('/'),
    routes: [
        {
            path: '',
            name: 'login',
            component: () => import('@auth/components/Login.vue'),
        },
    ],
});

const app = createApp(AuthApp).use(createDriveVuetify());

void app.use(router).mount('#app');
