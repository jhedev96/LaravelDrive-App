<script setup lang="ts">
/**
 * Context menu port (legacy ContextMenu.vue) — items gate by object
 * type (folder show-sans-preview) or trash state (restore/delete
 * forever), actions dispatch on the Media store. Vuetify 3 replaces
 * position-x/y with a `[x, y]` target.
 */
import { computed, nextTick, watch, type PropType } from 'vue';
import { useRoute } from 'vue-router';

import { bus } from '@common/bootstrap';

import type { MediaItem } from '@ac/stores/media';

import { useMediaStore } from '@ac/stores/media';

import { useMyDriveMixin } from './mixin';

import { storeToRefs } from 'pinia';

const props = defineProps({
    x: {
        type: Number,
        default: 0,
    },
    y: {
        type: Number,
        default: 0,
    },
    file: {
        type: Object as PropType<Partial<MediaItem>>,
        default: () => ({}),
    },
});

const show = defineModel<boolean>({ default: false });

const route = useRoute();
const media = useMediaStore();
const { selectedFilesId } = storeToRefs(media);

const { openNewFolderModal } = useMyDriveMixin();

// v-model passthrough; legacy reopened the menu on x/y movement with a
// nextTick dance — replicated here for Vuetify 4's target form
const showMenu = computed({
    get: () => show.value,
    set: (value: boolean) => {
        show.value = value;
    },
});

watch([() => props.x, () => props.y], () => {
    showMenu.value = false;

    void nextTick(() => {
        showMenu.value = true;
    });
});

function openDropZone() {
    bus.emit('openDropZone');
}

function uploadFolder() {
    bus.emit('uploadFolder');
}

function moveTo() {
    media.moveToemodal = true;
}

function shareFiles() {
    media.shareFileModal = true;
}

function shareLink() {
    media.shareLinkModal = true;
}

function manageStar() {
    if (props.file.id && ! props.file.stared) {
        void media.addStar({ ids: selectedFilesId.value });
    } else {
        void media.removeStar({ ids: selectedFilesId.value });
    }
}

function openRenameModal() {
    media.renamefilemodal = true;
}

function deleteItems() {
    void media.deleteItem({ ids: selectedFilesId.value });
}

function copyFiles() {
    void media.copyFile({ ids: selectedFilesId.value });
}

function downloadFile() {
    void media.downloadFile({ ids: selectedFilesId.value });
}

function restore() {
    void media.deleteItem({ ids: selectedFilesId.value, action: 'restore' });
}

function deleteForever() {
    void media.deleteItem({ ids: selectedFilesId.value, action: 'deleteforever' });
}

function preview() {
    media.previewModal = true;
}

interface ContextItem {
    title: string;
    icon: string;
    show: 'items' | 'back' | 'trash';
    action: () => void;
}

const menuitems = computed<ContextItem[]>(() => [
    { title: 'Preview', icon: 'mdi-eye', show: 'items', action: preview },
    { title: 'Share', icon: 'mdi-account-multiple', show: 'items', action: shareFiles },
    { title: 'Get shareable link', icon: 'mdi-link', show: 'items', action: shareLink },
    { title: (props.file as MediaItem).stared ? 'Removed from star' : 'Add a star', icon: 'mdi-star', show: 'items', action: manageStar },
    { title: 'Move to', icon: 'mdi-arrow-right-bottom', show: 'items', action: moveTo },
    { title: 'Rename', icon: 'mdi-pencil', show: 'items', action: openRenameModal },
    { title: 'Make a copy', icon: 'mdi-content-copy', show: 'items', action: copyFiles },
    { title: 'Download', icon: 'mdi-cloud-download', show: 'items', action: downloadFile },
    { title: 'Delete', icon: 'mdi-trash-can', show: 'items', action: deleteItems },
    { title: 'New Folder', icon: 'mdi-folder-plus', show: 'back', action: openNewFolderModal },
    { title: 'Upload files', icon: 'mdi-cloud-upload', show: 'back', action: openDropZone },
    { title: 'Upload Folder', icon: 'mdi-folder-open', show: 'back', action: uploadFolder },
    { title: 'Restore files', icon: 'mdi-restore', show: 'trash', action: restore },
    { title: 'Delete Forever', icon: 'mdi-delete-forever', show: 'trash', action: deleteForever },
]);

const items = computed<ContextItem[]>(() => {
    if ('id' in props.file && props.file.id && (props.file as MediaItem).deleted_at === null) {
        if (props.file.type === 'folder') {
            return menuitems.value.filter((i) => i.show === 'items' && i.title !== 'Preview');
        }

        return menuitems.value.filter((i) => i.show === 'items');
    } else if ('id' in props.file && props.file.id && (props.file as MediaItem).deleted_at !== null) {
        return menuitems.value.filter((i) => i.show === 'trash');
    } else if (route.name === 'media' || route.name === 'singleFolder') {
        return menuitems.value.filter((i) => i.show === 'back');
    }

    return [];
});
</script>

<template>
    <v-menu
        v-if="items.length"
        v-model="showMenu"
        :target="[x, y]"
        absolute
    >
        <v-list id="contextmenu">
            <v-list-item
                v-for="(item, index) in items"
                :key="index"
                ripple
                @click="item.action"
            >
                <template
                    v-if="item.icon"
                    #prepend
                >
                    <v-icon>{{ item.icon }}</v-icon>
                </template>

                <v-list-item-title>{{ item.title }}</v-list-item-title>
            </v-list-item>
        </v-list>
    </v-menu>
</template>

<style>
#contextmenu {
    width: 300px;
    padding: 10px;
}

#contextmenu .v-list-item {
    max-height: 42px;
    cursor: pointer;
}

#contextmenu .v-list-item:hover {
    background: #f3f3f3;
}
</style>
