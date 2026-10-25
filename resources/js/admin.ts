/**
 * Admin (drive) SPA entry — mounts on the /drive blade shell (#root).
 * Legacy `main.js` semantics: router base '/drive', the guard feeds the
 * root store's auth flag from the blade's window.LD.user.
 */
import { createPinia } from 'pinia';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

import AdminApp from '@ac/App.vue';
import routes from '@ac/router';
import { ensureApiToken } from '@common/bootstrap';
import { createDriveVuetify } from '@common/vuetify';

const router = createRouter({
    history: createWebHistory('/drive'),
    routes,
    scrollBehavior: () => ({ left: 0, top: 0 }),
});

// legacy guard semantics: without the session user the SPA blocks
// navigation and the layout shows its login dialog instead
router.beforeEach(() => {
    const user = (window as unknown as { LD?: { user?: unknown } }).LD?.user;

    return Boolean(user);
});

// W4.21: arm /api/auth before anything talks to the API (existing
// localStorage bearer or a session-minted one)
void (async () => {
    await ensureApiToken();

    const app = createApp(AdminApp).use(createPinia()).use(createDriveVuetify());

    void app.use(router).mount('#root');
})();
