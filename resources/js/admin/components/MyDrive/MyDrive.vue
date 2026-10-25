<script setup lang="ts">
/**
 * Drive page port (legacy MyDrive.vue). Vuex mapState → Pinia, $bus →
 * mitt, VFlex rows → plain grid cells (same CSS), and the legacy
 * infinite-scroll guard `page > pagination.total` is fixed to compare
 * against `last_page` (W4.20: total is the ITEM count, so the legacy
 * loader never stopped asking the API for past-the-end pages).
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { MediaItem } from '@ac/stores/media';

import { useMediaStore } from '@ac/stores/media';

import { storeToRefs } from 'pinia';

import ContextMenu from './ContextMenu.vue';
import FileUploader from './FileUploader.vue';
import MediaInfo from './MediaInfo.vue';
import MediaItemCard from './mediaItem.vue';
import MediaToolbar from './mediaToolbar.vue';
import MoveTo from './MoveTo.vue';
import NewFolderForm from './NewFolderForm.vue';
import Preview from './Preview.vue';
import RenameFile from './RenameFile.vue';
import ShareFile from './ShareFile.vue';
import ShareLink from './ShareLink.vue';

import { useMyDriveMixin } from './mixin';

const route = useRoute();
const router = useRouter();
const media = useMediaStore();
const {
    mediaItems,
    pagination,
    fileInfoSideBar,
    newFolderModal,
    shareFileModal,
    shareLinkModal,
    renamefilemodal,
    moveToemodal,
    previewModal,
} = storeToRefs(media);

const { isfilesLoaded, isfolderLoaded, loadFolders } = useMyDriveMixin();

const cm = reactive<{ show: boolean, x: number, y: number, file: Partial<MediaItem> }>({
    show: false, x: 0, y: 0, file: {},
});
const fileUploader = ref(false);
const scrollLoading = ref(false);
const fileCm = ref(false);
const clickedOnItem = ref(false);

const isLoaded = computed(() => isfilesLoaded.value && isfolderLoaded.value);

// registered synchronously so cleanup can remove it; the legacy bound a
// bare anonymous listener and never removed it
const clickAway = (event: MouseEvent) => {
    const element = (event.target as HTMLElement).closest('.file-deselect');

    if (element) {
        return;
    }

    deselect();
};

function selfMediaItems(r: ReturnType<typeof useRoute>): void {
    const params: Record<string, unknown> = {};

    if (r.params.folderId !== undefined) {
        params.parent_id = r.params.folderId;
    }

    if (r.query.page) {
        params.page = r.query.page;
    }

    media.getMediaItems(params)
        .then(() => {
            isfilesLoaded.value = true;
            scrollLoading.value = false;
        });
}

watch(() => route.fullPath, (path, old) => {
    if (path !== old) {
        selfMediaItems(route);
    }
});

onMounted(() => {
    isfilesLoaded.value = false;

    selfMediaItems(route);

    void loadFolders();

    document.addEventListener('click', clickAway);

    scroll();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', clickAway);

    media.emptyMediaItems();
});

function activeDropzone(event: Event): void {
    event.stopPropagation();
    event.preventDefault();

    fileUploader.value = true;
}

function showContextMenu(e: MouseEvent): void {
    e.preventDefault();

    if (fileCm.value) {
        fileCm.value = false;

        return;
    }

    cm.show = true;
    cm.x = e.clientX;
    cm.y = e.clientY;
}

function showContextMenuForFile(e: MouseEvent, item: MediaItem): void {
    e.preventDefault();
    fileCm.value = true;
    cm.show = true;
    cm.x = e.clientX;
    cm.y = e.clientY;
    cm.file = item;

    onClickItem(e, item);
}

function onClickItem(event: MouseEvent | TouchEvent, item: MediaItem): void {
    clickedOnItem.value = true;
    const isMultiSelect = Boolean('ctrlKey' in event && (event.ctrlKey || event.metaKey));

    if (! isMultiSelect && item.type === 'folder' && ! fileCm.value) {
        pushChild(item);
    }

    media.selectFiles({ isMultiSelect, id: item.id });
    media.selectMediaItem(item);
}

function pushChild(item: MediaItem): void {
    let name = 'singleFolder';

    if (route.name === 'trash' || route.name === 'trashFolder') {
        name = 'trashFolder';
    }

    void router.push({ name, params: { folderId: item.hash } });
}

function deselect(): void {
    if (clickedOnItem.value) {
        clickedOnItem.value = false;

        // a click through the item context menu must not undo the whole
        // selection on the very next document click

        return;
    }

    media.deselectFile();
}

function scroll(): void {
    window.onscroll = () => {
        if (scrollLoading.value) {
            return;
        }

        const bottomOfWindow = document.documentElement.scrollTop + window.innerHeight + 50
            > document.documentElement.offsetHeight;

        if (bottomOfWindow) {
            const page = (Number(route.query.page) || 1) + 1;

            // W4.20: the legacy compared against `pagination.total` (the
            // ITEM count) so scrolling kept requesting past-the-end pages
            if (page > (pagination.value.last_page ?? 1)) {
                return;
            }

            scrollLoading.value = true;

            void router.replace({
                name: route.name as string,
                params: route.params,
                query: { page },
            });
        }
    };
}
</script>

<template>
    <div class="d-block">
        <div class="d-block my-drive-page">
            <div class="pa-0">
                <MediaToolbar />
            </div>
        </div>

        <div
            v-if="isLoaded"
            class="my-file"
            :class="{ 'sidebar-open': fileInfoSideBar }"
            @dragenter="activeDropzone($event)"
            @contextmenu="showContextMenu"
        >
            <div
                id="filecontainer"
                class="file-grid"
            >
                <div
                    v-for="img in mediaItems"
                    :key="img.id"
                    class="file-cell"
                    @contextmenu="showContextMenuForFile($event, img)"
                    @click="onClickItem($event, img)"
                    @touchstart="onClickItem($event, img)"
                >
                    <MediaItemCard :media="img" />
                </div>
            </div>

            <MediaInfo v-if="fileInfoSideBar" />
            <FileUploader v-model="fileUploader" />
            <ContextMenu
                v-model="cm.show"
                :x="cm.x"
                :y="cm.y"
                :file="cm.file"
            />
        </div>

        <template v-if="newFolderModal">
            <NewFolderForm />
        </template>

        <template v-if="shareFileModal">
            <ShareFile />
        </template>

        <template v-if="shareLinkModal">
            <ShareLink />
        </template>

        <template v-if="renamefilemodal">
            <RenameFile />
        </template>

        <template v-if="moveToemodal">
            <MoveTo />
        </template>

        <template v-if="previewModal">
            <Preview />
        </template>
    </div>
</template>

<style lang="scss">
.my-file {
    position: relative;
    display: block;
    overflow: hidden;
    height: calc(100vh - 10rem);
}

#filecontainer {
    margin: 0;
    transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);

    .file-cell {
        width: calc(20% - 20px);
        margin-top: 16px;
        margin-right: 20px;
        display: inline-block;
        position: relative;
        vertical-align: top;
        max-width: 210px;
        min-width: 120px;
    }
}

.sidebar-open #filecontainer {
    margin-right: 300px;

    .file-cell {
        width: calc(25% - 20px);
    }
}
</style>
