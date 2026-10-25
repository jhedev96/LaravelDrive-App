<script setup lang="ts">
/**
 * Move-to dialog port (legacy MoveTo.vue). The folder tree comes from
 * the store's nestedFolders getter; moved items disappear locally via
 * the ported moveFile mutation now named moveItemLocally. Fix-up: the
 * legacy typed the active node off `@update:active` (never a Vuetify
 * 2 event either way); Vuetify 4 treeview offers update:activated.
 */
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';

import type { MediaItem } from '@ac/stores/media';

import { useMediaStore } from '@ac/stores/media';

import { storeToRefs } from 'pinia';

const media = useMediaStore();
const { moveToemodal, selectedFilesId } = storeToRefs(media);

const route = useRoute();

const active = ref<unknown[]>([]);

interface TreeNode {
    id: number;
    name: string;
    children?: TreeNode[];
}

const folderLists = computed<TreeNode[]>(() => {
    const lists = (media.nestedFolders as unknown as TreeNode[]).slice();

    if (route.params.folderId !== undefined) {
        lists.unshift({ id: 0, name: 'Root' });
    }

    return lists;
});

async function moveTo(): Promise<void> {
    const destination = (active.value[0] as number) ?? null;

    const res = await media.moveFiles({
        files: selectedFilesId.value,
        destination,
    });

    const parentId = (route.params.folderId as unknown as number) ?? 0;

    const items = (Array.isArray(res) ? res : []) as MediaItem[];

    // only items that actually left the current listing get pulled out
    // of the grid (legacy moveFile mutation semantics)
    media.moveItemLocally(
        items.filter((i) => i.parent_id !== parentId).map((i) => i.id),
    );

    close();
}

function close(): void {
    media.moveToemodal = false;
}
</script>

<template>
    <v-dialog
        :model-value="moveToemodal"
        class="mpu"
        persistent
        width="500"
        @update:model-value="close()"
    >
        <v-card>
            <div class="d-flex align-center bg-grey-lighten-2">
                <v-card-title class="flex-grow-1">Move To</v-card-title>

                <v-card-actions>
                    <v-btn
                        icon
                        color="error"
                        @click="close()"
                    >
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-actions>
            </div>

            <v-card-text class="recursive-folder">
                <v-treeview
                    v-model:activated="active"
                    :items="folderLists"
                    activatable
                    density="comfortable"
                    item-title="name"
                    item-value="id"
                    rounded
                    color="success"
                    open-on-click
                />
            </v-card-text>

            <v-card-actions class="bt-1">
                <v-btn
                    color="secondary"
                    @click="moveTo"
                >
                    Move To
                </v-btn>
                <v-btn
                    color="error"
                    @click="close()"
                >
                    Cancel
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style>
.mpu .v-card-actions {
    border-top: 1px solid #ddd;
}
</style>
