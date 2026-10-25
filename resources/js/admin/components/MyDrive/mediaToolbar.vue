<script setup lang="ts">
/**
 * Media toolbar port (legacy mediaToolbar.vue). `Bus.$emit` became the
 * typed mitt bus; view/filter buttons keep the legacy appearance and
 * gain nothing yet (their behaviors were never wired in the legacy app).
 */
import { storeToRefs } from 'pinia';

import { bus } from '@common/bootstrap';

import { useMediaStore } from '@ac/stores/media';

import { useMyDriveMixin } from './mixin';

const media = useMediaStore();
const { fileInfoSideBar } = storeToRefs(media);

const { openNewFolderModal, toggleSidebar } = useMyDriveMixin();

function openDropZone() {
    bus.emit('openDropZone');
}

function uploadFolder() {
    bus.emit('uploadFolder');
}
</script>

<template>
    <v-toolbar
        id="mediaToolbar"
        flat
        class="la-pt"
        color="secondary"
    >
        <v-toolbar-title>
            <v-icon
                icon="mdi-folder-multiple-image"
                class="mr-1"
            />
            My Files

            <v-menu location="bottom">
                <template #activator="{ props: activator }">
                    <v-btn
                        icon
                        v-bind="activator"
                    >
                        <v-icon>mdi-arrow-down-drop-circle-outline</v-icon>
                    </v-btn>
                </template>

                <v-list>
                    <v-list-item @click="openNewFolderModal()">
                        <v-list-item-title>
                            <v-icon
                                icon="mdi-folder-plus"
                                class="mr-2"
                            />New Folder
                        </v-list-item-title>
                    </v-list-item>

                    <v-list-item @click="uploadFolder">
                        <v-list-item-title>
                            <v-icon
                                icon="mdi-folder-plus"
                                class="mr-2"
                            />Upload Folder
                        </v-list-item-title>
                    </v-list-item>

                    <v-list-item @click="openDropZone">
                        <v-list-item-title>
                            <v-icon
                                icon="mdi-image-plus"
                                class="mr-2"
                            />Upload Files
                        </v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-menu>
        </v-toolbar-title>

        <v-spacer />

        <v-btn
            icon
            @click="openDropZone()"
        >
            <v-icon>mdi-image-plus</v-icon>
        </v-btn>
        <v-btn
            icon
            @click="openNewFolderModal()"
        >
            <v-icon>mdi-folder-plus</v-icon>
        </v-btn>
        <v-btn icon>
            <v-icon>mdi-view-module</v-icon>
        </v-btn>
        <v-btn icon>
            <v-icon>mdi-view-list</v-icon>
        </v-btn>
        <v-btn icon>
            <v-icon>mdi-filter</v-icon>
        </v-btn>
        <v-btn
            icon
            :class="{ active: fileInfoSideBar, 'file-deselect': true }"
            @click="toggleSidebar()"
        >
            <v-icon>mdi-information</v-icon>
        </v-btn>
    </v-toolbar>
</template>

<style>
#mediaToolbar .v-btn.active {
    background: #0e5796;
    color: #fff;
}
</style>
