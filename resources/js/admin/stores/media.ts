/**
 * Media store — port of the legacy MyDrive Vuex module
 * (`components/MyDrive/store.js`).
 *
 * Bug fixes carried over while porting:
 * - getFolders previously passed `params` as the axios second argument,
 *   i.e. the request CONFIG — the query string never reached the API;
 * - trash/starred/shared actions read `res.data.meta.pagination`, a shape
 *   the backend never sent (every paginated endpoint answers
 *   `{data, links, meta}`) so those paginations were permanently
 *   `undefined` and the loaders broke — all pages read `meta` now;
 * - the rename flow committed the response wrapper (`res.data`) instead
 *   of the item (`res.data.data`) so the grid never updated after a PUT;
 * - the IE-only `msSaveBlob` branch in downloadFile is gone.
 */
import { defineStore } from 'pinia';

import { api } from '@common/bootstrap';

import { useRootStore } from './root';

export interface MediaItem {
    id: number;
    hash: string;
    name: string;
    type: string;
    mime: string;
    extension: string;
    file_size: number;
    parent_id: number;
    stared?: boolean;
    meta?: Record<string, unknown>;
    description?: string;
    public_path?: string;
    url?: string;
    file_name?: string;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
    uploader?: { name?: string, display_name?: string, email?: string, avatar?: string } | null;
    [key: string]: unknown;
}

export interface PaginationMeta {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    [key: string]: unknown;
}

export interface ContextMenu {
    show: boolean;
    x: number;
    y: number;
}

function getNestedFolders(arr: MediaItem[], parent = 0): MediaItem[] {
    const out: MediaItem[] = [];

    (arr ?? []).forEach((e, i) => {
        if (e.parent_id === parent) {
            const children = getNestedFolders(arr.slice(i + 1), e.id);

            e.children = children.length ? children : [];

            out.push(e);
        }
    });

    return out;
}

function reportRequestFailure(error: unknown) {
    const response = (error as { response?: { data?: { message?: string }, status?: number } }).response;

    useRootStore().setSnackbar({
        message: response?.data?.message ?? 'Something went wrong',
        status: response?.status,
        color: 'error',
        show: true,
    });
}

export const useMediaStore = defineStore('media', {
    state: () => ({
        mediaItems: [] as MediaItem[],
        folders: [] as MediaItem[],
        pagination: {} as PaginationMeta,
        trashItems: [] as MediaItem[],
        trashPagination: {} as PaginationMeta,
        staredItems: [] as MediaItem[],
        staredPagination: {} as PaginationMeta,
        sharedItems: [] as MediaItem[],
        sharedPagination: {} as PaginationMeta,
        fileInfoSideBar: false,
        newFolderModal: false,
        shareFileModal: false,
        shareLinkModal: false,
        renamefilemodal: false,
        moveToemodal: false,
        previewModal: false,
        selectedMedia: {} as MediaItem,
        selectedFilesId: [] as number[],
        contextMenu: {
            show: false, x: 0, y: 0,
        } as ContextMenu,
    }),

    getters: {
        nestedFolders: (state) => getNestedFolders(state.folders, 0),

        favoriteFolders: (state) => state.folders.filter((f) => f.stared),
    },

    actions: {
        // ---- items ------------------------------------------------------
        async getMediaItems(params: Record<string, unknown> = {}) {
            if (! params.page) {
                this.emptyMediaItems();
            }

            try {
                const res = await api.get('/api/file', { params });

                this.mediaItems = this.mediaItems.concat(res.data.data);
                this.pagination = res.data.meta;

                return res.data;
            } catch (error) {
                reportRequestFailure(error);

                throw error;
            }
        },

        async updateItem(params: Partial<MediaItem> & { id: number }) {
            const res = await api.put(`/api/file/${params.id}`, params);

            this.updateItemById(res.data.data);

            return res.data.data;
        },

        async deleteItem(params: { ids: number[] } & Record<string, unknown>) {
            const res = await api.delete('/api/file/delete', { params });

            this.deleteItems(params.ids);

            return res.data;
        },

        // ---- folders ----------------------------------------------------
        async getFolders() {
            const res = await api.get('/api/folder');

            this.folders = res.data.data;

            return res.data;
        },

        async addFolder(params: Record<string, unknown>) {
            const res = await api.post('/api/folder', params);

            const created = res.data.data as MediaItem;

            this.mediaItems.unshift(created);
            this.folders.unshift(created);

            return res.data.data;
        },

        // ---- stars ------------------------------------------------------
        async addStar(params: { ids: number[] }) {
            const res = await api.post('/api/tag/star', params);

            this.mediaItems.forEach((item) => {
                if (params.ids.includes(item.id)) {
                    item.stared = true;
                }
            });

            return res.data;
        },

        async removeStar(params: { ids: number[] }) {
            const res = await api.post('/api/tag/unstar', params);

            this.mediaItems.forEach((item) => {
                if (params.ids.includes(item.id)) {
                    item.stared = false;
                }
            });

            return res.data;
        },

        // ---- copy / move --------------------------------------------------
        async copyFile(params: Record<string, unknown>) {
            const res = await api.post('/api/file/copy', params);

            this.mediaItems = this.mediaItems.concat(res.data.data);

            return res.data;
        },

        async moveFiles(params: Record<string, unknown>) {
            const res = await api.post('/api/file/move', params);

            // the caller decides which items left the current listing
            // (depends on the route's parent); the legacy store action
            // also only returned the API items
            return res.data.data;
        },

        // ---- download ------------------------------------------------------
        async downloadFile(params: { ids: number[] }) {
            const res = await api.post('/api/download', params, {
                responseType: 'blob',
            });

            let filename = '';
            const disposition = res.headers['content-disposition'];

            if (disposition) {
                const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);

                if (matches && matches[1]) {
                    filename = matches[1].replace(/['"]/g, '');
                }
            }

            const url = URL.createObjectURL(res.data);
            const a = document.createElement('a');

            if (filename) {
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.target = '_blank';
                a.click();
            } else {
                window.location.href = url;
            }

            URL.revokeObjectURL(url);

            return res;
        },

        // ---- trash / starred / shared lists -------------------------------
        async getTrashItems(params: Record<string, unknown> = {}) {
            if (! params.page) {
                this.trashItems = [];
            }

            try {
                const res = await api.get('/api/file/trash', { params });

                this.trashItems = this.trashItems.concat(res.data.data);
                this.trashPagination = res.data.meta;

                return res.data;
            } catch (error) {
                reportRequestFailure(error);

                throw error;
            }
        },

        async getStaredItems(params: Record<string, unknown> = {}) {
            if (! params.page) {
                this.staredItems = [];
            }

            try {
                const res = await api.get('/api/file/star', { params });

                this.staredItems = this.staredItems.concat(res.data.data);
                this.staredPagination = res.data.meta;

                return res.data;
            } catch (error) {
                reportRequestFailure(error);

                throw error;
            }
        },

        async getSharedItems(params: Record<string, unknown> = {}) {
            if (! params.page) {
                this.sharedItems = [];
            }

            try {
                const res = await api.get('/api/shared/files', { params });

                this.sharedItems = this.sharedItems.concat(res.data.data);
                this.sharedPagination = res.data.meta;

                return res.data;
            } catch (error) {
                reportRequestFailure(error);

                throw error;
            }
        },

        // ---- local state helpers -------------------------------------------
        setMediaItem(item: MediaItem) {
            this.mediaItems.push(item);
        },

        emptyMediaItems() {
            this.mediaItems = [];
        },

        emptyTrashItems() {
            this.trashItems = [];
        },

        emptyStaredItems() {
            this.staredItems = [];
        },

        emptySharedItems() {
            this.sharedItems = [];
        },

        selectMediaItem(item: MediaItem) {
            this.selectedMedia = item;
        },

        selectFiles(payload: { id: number, isMultiSelect?: boolean }) {
            if (payload.isMultiSelect) {
                this.selectedFilesId.push(payload.id);
            } else {
                this.selectedFilesId = [payload.id];
            }
        },

        deselectFile() {
            this.selectedFilesId = [];
            this.selectedMedia = {} as MediaItem;
        },

        toggleSidebar(payload: boolean | null = null) {
            if (payload !== null) {
                this.fileInfoSideBar = payload;
            } else {
                this.fileInfoSideBar = ! this.fileInfoSideBar;
            }
        },

        openContextMenu(payload: Partial<ContextMenu>) {
            this.contextMenu = { show: true, x: 0, y: 0, ...payload };
        },

        closeContextMenu() {
            this.contextMenu.show = false;
        },

        updateItemById(item: MediaItem) {
            const i = this.mediaItems.findIndex((it) => it.id === item.id);

            if (i !== -1) {
                this.mediaItems[i] = item;
            }
        },

        deleteItems(ids: number[]) {
            ids.forEach((id) => {
                const i = this.mediaItems.findIndex((item) => item.id === id);

                if (i !== -1) {
                    this.mediaItems.splice(i, 1);
                }
            });
        },

        moveItemLocally(ids: number[]) {
            this.mediaItems = this.mediaItems.filter((i) => ! ids.includes(i.id));
        },
    },
});
