/**
 * Public shareable page entry — mount target #share-root on sharedfiles.blade.
 * Real consumer of GET /api/share/links/{hash} (W3.9) ports next slice.
 */
import { createApp } from 'vue';

import ShareApp from '@common/share/ShareApp.vue';
import { createDriveVuetify } from '@common/vuetify';

const app = createApp(ShareApp).use(createDriveVuetify());

void app.mount('#share-root');
