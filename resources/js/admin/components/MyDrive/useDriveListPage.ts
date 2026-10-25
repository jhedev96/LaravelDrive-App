/**
 * Shared behavior for the drive LIST pages (trash, starred,
 * shared-with-me) — logic the legacy triplicated across
 * Trash.vue/Stared.vue/SharedWithMe.vue: item loading per route, the
 * click-away deselect, context menus, folder navigation, and the
 * infinite scroll loader.
 *
 * Pagination guards compare `last_page`; the legacy compared against
 * `total_pages`, a meta key the backend never produced (W4.26 — guard
 * dead on every list page, so paged loaders ran forever).
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch, type Ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { MediaItem } from '@ac/stores/media';

import { useMediaStore } from '@ac/stores/media';

import { storeToRefs } from 'pinia';

import { useMyDriveMixin } from './mixin';

export interface DriveListPageOptions {
    /** the store list to render, e.g. media.trashItems */
    items: () => MediaItem[];
    /** store action that loads the items, e.g. media.getTrashItems */
    fetch: (params: Record<string, unknown>) => Promise<unknown>;
    /** last page of the list's pagination meta */
    lastPage: () => number | undefined;
    /** store action clearing the list on unmount */
    empty: () => void;
    /** folder child nav route for non trash pages (else trashFolder) */
    folderRouteName?: string;
}

export interface DriveListPage {
    route: ReturnType<typeof useRoute>;
    media: ReturnType<typeof useMediaStore>;
    selectedFilesId: Ref<number[]>;
    isfilesLoaded: Ref<boolean>;
    isLoaded: Ref<boolean>;
    fileList: Ref<MediaItem[]>;
    cm: { show: boolean, x: number, y: number, file: Partial<MediaItem> };
    fileInfoSideBar: Ref<boolean>;
    fileUploader: Ref<boolean>;
    activeDropzone: (event: Event) => void;
    showContextMenu: (e: MouseEvent, item?: MediaItem) => void;
    showContextMenuForFile: (e: MouseEvent, item: MediaItem) => void;
    onClickItem: (event: MouseEvent | TouchEvent, item: MediaItem) => void;
    scrollToLoad: () => void;
}

export function useDriveListPage(options: DriveListPageOptions): DriveListPage {
    const route = useRoute();
    const router = useRouter();
    const media = useMediaStore();
    const { selectedFilesId, fileInfoSideBar } = storeToRefs(media);

    const { isfilesLoaded, isTrashPage } = useMyDriveMixin();

    const cm = reactive<{ show: boolean, x: number, y: number, file: Partial<MediaItem> }>({
        show: false, x: 0, y: 0, file: {},
    });
    const fileCm = ref(false);
    const clickedOnItem = ref(false);
    const scrollLoading = ref(false);
    const fileUploader = ref(false);

    const fileList = computed<MediaItem[]>(() => options.items());

    const isLoaded = isfilesLoaded;

    // registered synchronously so unmount can remove it (the legacy
    // bound an anonymous listener and never removed it)
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

        void options.fetch(params)
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

        document.addEventListener('click', clickAway);

        scrollToLoad();
    });

    onBeforeUnmount(() => {
        document.removeEventListener('click', clickAway);

        options.empty();
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
        cm.file = {};
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
        let name = options.folderRouteName ?? 'singleFolder';

        if (isTrashPage.value) {
            name = 'trashFolder';
        }

        void router.push({ name, params: { folderId: item.hash } });
    }

    function deselect(): void {
        if (clickedOnItem.value) {
            clickedOnItem.value = false;

            return;
        }

        media.deselectFile();
    }

    function scrollToLoad(): void {
        window.onscroll = () => {
            if (scrollLoading.value) {
                return;
            }

            const bottomOfWindow = document.documentElement.scrollTop + window.innerHeight + 50
                > document.documentElement.offsetHeight;

            if (bottomOfWindow) {
                const page = (Number(route.query.page) || 1) + 1;

                // W4.26: legacy compared pagination.total_pages — a meta
                // key that never existed — so loaders never stopped
                if (page > (options.lastPage() ?? 1)) {
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

    return {
        route,
        media,
        selectedFilesId,
        isfilesLoaded,
        isLoaded,
        fileList,
        cm,
        fileInfoSideBar,
        fileUploader,
        activeDropzone,
        showContextMenu,
        showContextMenuForFile,
        onClickItem,
        scrollToLoad,
    };
}
