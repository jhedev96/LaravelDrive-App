<script setup lang="ts">
/**
 * Dashboard port with the hard-coded digits made real: the legacy view
 * shipped fake numbers (3796 files, 23 users, 650.3 MB) that no endpoint
 * ever fed. /api/stats (W5.2) answers live counts; the users widget only
 * renders for administrators, matching the legacy permission semantics.
 */
import { computed, onMounted, ref } from 'vue';

import WidgetCount from './Widgets/Count.vue';

import { api } from '@common/bootstrap';
import { humanBytes } from '@common/format';
import { getUserPermissions } from '@common/auth';

import { useRootStore } from '@ac/stores/root';

interface Stats {
    files: number;
    folders: number;
    storage_bytes: number;
    users: number | null;
}

const root = useRootStore();

const stats = ref<Stats | null>(null);
const loading = ref(false);

const isAdmin = computed(() => getUserPermissions().includes('administrator'));

onMounted(async () => {
    loading.value = true;

    try {
        const res = await api.get('/api/stats');

        stats.value = res.data.data as Stats;
    } catch (error) {
        const response = (error as { response?: { data?: { message?: string } } }).response;

        root.setSnackbar({
            message: response?.data?.message ?? 'Could not load stats',
            color: 'error',
        });
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="d-block">
        <v-row dense>
            <v-col
                cols="12"
                sm="6"
                md="3"
            >
                <WidgetCount
                    class="ml-3"
                    icon="mdi-folder-multiple-image"
                >
                    <span class="d-block">Total Files</span>
                    <span class="d-block">{{ stats ? stats.files : '…' }}</span>
                </WidgetCount>
            </v-col>

            <v-col
                cols="12"
                sm="6"
                md="3"
            >
                <WidgetCount
                    class="ml-3"
                    icon="mdi-folder-open"
                >
                    <span class="d-block">Total Folders</span>
                    <span class="d-block">{{ stats ? stats.folders : '…' }}</span>
                </WidgetCount>
            </v-col>

            <v-col
                v-if="isAdmin"
                cols="12"
                sm="6"
                md="3"
            >
                <WidgetCount
                    class="ml-3"
                    icon="mdi-account-multiple"
                >
                    <span class="d-block">Total Users</span>
                    <span class="d-block">{{ stats ? (stats.users ?? '…') : '…' }}</span>
                </WidgetCount>
            </v-col>

            <v-col
                cols="12"
                sm="6"
                :md="isAdmin ? 3 : 6"
            >
                <WidgetCount
                    class="ml-3"
                    icon="mdi-harddisk"
                >
                    <span class="d-block">Space Used</span>
                    <span>{{ stats ? humanBytes(stats.storage_bytes) : '…' }}</span>
                </WidgetCount>
            </v-col>
        </v-row>

        <v-progress-linear
            v-if="loading"
            class="mt-4"
            indeterminate
        />
    </div>
</template>
