<script setup lang="ts">
/**
 * Trash page port (legacy Trash.vue) — the drive grid bound to the
 * trash listing with restore/delete-forever context items. Behaviors
 * (load, click-away, context, scroll) ride on useDriveListPage.
 */
import MediaItemCard from './mediaItem.vue';
import ContextMenu from './ContextMenu.vue';

import { useDriveListPage } from './useDriveListPage';

import { useMediaStore } from '@ac/stores/media';

const media = useMediaStore();

const page = useDriveListPage({
    items: () => media.trashItems,
    fetch: (params) => media.getTrashItems(params),
    lastPage: () => media.trashPagination.last_page,
    empty: () => media.emptyTrashItems(),
});

const { fileList, cm, isLoaded, fileInfoSideBar } = page;
const { showContextMenu, showContextMenuForFile, onClickItem, activeDropzone } = page;
</script>

<template>
    <div class="d-block">
        <div
            v-if="isLoaded"
            class="my-file"
            :class="{ 'sidebar-open': fileInfoSideBar }"
            @dragenter="activeDropzone($event)"
            @contextmenu="showContextMenu($event)"
        >
            <div
                id="filecontainer"
                class="file-grid"
            >
                <div
                    v-for="img in fileList"
                    :key="img.id"
                    class="file-cell"
                    @contextmenu="showContextMenuForFile($event, img)"
                    @click="onClickItem($event, img)"
                    @touchstart="onClickItem($event, img)"
                >
                    <MediaItemCard :media="img" />
                </div>
            </div>

            <ContextMenu
                v-model="cm.show"
                :x="cm.x"
                :y="cm.y"
                :file="cm.file"
            />
        </div>
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
</style>
