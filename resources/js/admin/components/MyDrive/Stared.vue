<script setup lang="ts">
/**
 * Starred page port (legacy Stared.vue) — grid over the starred
 * listing with the upload/info/dialog cluster. Behaviors ride on
 * useDriveListPage.
 */
import MediaItemCard from './mediaItem.vue';
import ContextMenu from './ContextMenu.vue';
import FileUploader from './FileUploader.vue';
import MediaInfo from './MediaInfo.vue';
import NewFolderForm from './NewFolderForm.vue';
import RenameFile from './RenameFile.vue';
import ShareFile from './ShareFile.vue';
import MoveTo from './MoveTo.vue';

import { useDriveListPage } from './useDriveListPage';

import { useMediaStore } from '@ac/stores/media';

const media = useMediaStore();

const page = useDriveListPage({
    items: () => media.staredItems,
    fetch: (params) => media.getStaredItems(params),
    lastPage: () => media.staredPagination.last_page,
    empty: () => media.emptyStaredItems(),
});

const { fileList, cm, isLoaded, fileInfoSideBar, fileUploader } = page;
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

            <MediaInfo v-if="fileInfoSideBar" />
            <FileUploader v-model="fileUploader" />

            <ContextMenu
                v-model="cm.show"
                :x="cm.x"
                :y="cm.y"
                :file="cm.file"
            />
        </div>

        <template v-if="media.newFolderModal">
            <NewFolderForm />
        </template>

        <template v-if="media.shareFileModal">
            <ShareFile />
        </template>

        <template v-if="media.renamefilemodal">
            <RenameFile />
        </template>

        <MoveTo />
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
