<script setup lang="ts">
/**
 * Full-screen file preview port (legacy Preview.vue): object (pdf/txt),
 * image with the panzoom mouse tool, and audio/video via plyr — the
 * player is destroyed on unmount now (the legacy leaked one instance
 * per view). W5.5 plyr carryover.
 */
import Plyr from 'plyr';

import 'plyr/dist/plyr.css';

import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import type { MediaItem } from '@ac/stores/media';

import { useMediaStore } from '@ac/stores/media';

import { initPanZoom } from '@common/panzoom';

import { storeToRefs } from 'pinia';

import ContextMenu from './ContextMenu.vue';

const media = useMediaStore();
const { previewModal, selectedMedia, selectedFilesId } = storeToRefs(media);

const showMenu = ref(false);
const menuPos = ref({ x: 0, y: 0 });

const current = computed(() => selectedMedia.value as MediaItem);

const fileUrl = computed(() => `${window.location.origin}/${current.value.url ?? ''}`);

const isImage = computed(() => ['gif', 'ico', 'jpeg', 'jpg', 'png', 'svg', 'bmp', 'dib'].includes(current.value.extension));

const isPdf = computed(() => ['pdf', 'txt'].includes(current.value.extension));

const isVideo = computed(() => ['mp4', 'webm', '3gp', 'flv', 'ogg', 'ogv', 'mov', 'wmv', 'mpeg'].includes(current.value.extension));

const isAudio = computed(() => ['mp3', 'ogg'].includes(current.value.extension));

let player: Plyr | null = null;

onMounted(() => {
    if (isImage.value) {
        void initPanZoom('.image-preview');
    }

    if (isVideo.value || isAudio.value) {
        player = new Plyr('#player');
    }
});

onBeforeUnmount(() => {
    // the legacy never destroyed the player — instances piled up
    player?.destroy();
    player = null;
});

function closePreview(): void {
    media.previewModal = false;
}

function clickCard(event: MouseEvent): void {
    if ((event.target as HTMLElement).closest('.v-toolbar__content')) {
        return;
    }

    if ((event.target as HTMLElement).closest('.v-responsive__content')) {
        return;
    }

    if ((event.target as HTMLElement).closest('.video-preview')) {
        return;
    }

    closePreview();
}

function downloadFile(): void {
    void media.downloadFile({ ids: [...selectedFilesId.value] });
}

function showContextMenu(event: MouseEvent): void {
    showMenu.value = ! showMenu.value;
    menuPos.value = { x: event.clientX, y: event.clientY };
}
</script>

<template>
    <v-dialog
        :model-value="previewModal"
        fullscreen
        scrollable
        transition="dialog-bottom-transition"
        @update:model-value="closePreview()"
    >
        <v-card
            class="preview-card file-deselect"
            @click="clickCard"
        >
            <v-toolbar
                color="primary"
            >
                <v-btn
                    icon
                    dark
                    @click="closePreview()"
                >
                    <v-icon>mdi-close</v-icon>
                </v-btn>
                <v-spacer />
                <v-btn
                    dark
                    @click="downloadFile()"
                >
                    <v-icon>mdi-cloud-download</v-icon>
                </v-btn>
                <div style="position: relative;">
                    <v-btn
                        dark
                        @click="showContextMenu"
                    >
                        <v-icon>mdi-dots-vertical</v-icon>
                    </v-btn>

                    <ContextMenu
                        v-model="showMenu"
                        :x="menuPos.x"
                        :y="menuPos.y"
                        :file="selectedMedia"
                    />
                </div>
            </v-toolbar>

            <v-card
                v-if="isPdf"
                class="pdf-preview"
            >
                <object
                    :type="current.mime"
                    :data="fileUrl"
                >
                    <a :href="fileUrl">Download the file.</a>
                </object>
            </v-card>

            <v-card
                v-else-if="isImage"
                class="image-preview"
            >
                <v-img
                    :src="current.public_path"
                    :lazy-src="current.public_path"
                    aspect-ratio="2.75"
                    contain
                    width="70%"
                />
            </v-card>

            <v-card
                v-else-if="isVideo"
                class="video-preview"
            >
                <video
                    id="player"
                    playsinline
                    controls
                >
                    <source
                        :src="fileUrl"
                        :type="current.mime"
                    >
                </video>
            </v-card>

            <v-card
                v-else-if="isAudio"
                class="video-preview"
            >
                <audio
                    id="player"
                    controls
                >
                    <source
                        :src="fileUrl"
                        :type="current.mime"
                    >
                </audio>
            </v-card>

            <v-card
                v-else
                class="no-preview"
            >
                <h4>No file preview available.</h4>
                <v-btn
                    color="primary"
                    @click="downloadFile()"
                >
                    Download
                </v-btn>
            </v-card>
        </v-card>
    </v-dialog>
</template>

<style>
.v-card.preview-card {
    background-color: rgba(0, 0, 0, 0.8) !important;
}

.v-card.preview-card .image-preview {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 80vh;
    box-shadow: none;
}

.v-card.preview-card .v-toolbar {
    z-index: 9999;
}

.v-card.preview-card .no-preview {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    background: #fff !important;
}

.v-card.preview-card .no-preview h4 {
    width: 300px;
    background: #fff;
    text-align: center;
    display: flex;
    justify-content: center;
}

.pdf-preview object {
    width: 100%;
    height: 100vh;
}

.video-preview {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    text-align: center;
}

.v-card.preview-card .video-preview {
    background: transparent !important;
}

.v-card.preview-card .pdf-preview {
    background: #fff !important;
}
</style>
