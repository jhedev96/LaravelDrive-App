/**
 * Media composable — port of the legacy MyDrive `mixin.js`. Used by the
 * toolbar, items, dialogs and the drive page itself. The switch/case
 * with inline `find()` defaults became a lookup map; icons stay the
 * flaticon spans served from public/css/font.
 */
import { computed, ref, type Ref } from 'vue';
import { useRoute } from 'vue-router';

import { useMediaStore, type MediaItem } from '@ac/stores/media';

export interface MediaIcon {
    type: string;
    icon: string;
    color?: string;
    avatar: boolean;
}

const FOLDER_ICON: MediaIcon = { type: 'folder', icon: '<span class="lafi material-icons">folder</span>', color: '#fbc02d', avatar: true };

const IMAGE_EXTENSIONS = ['gif', 'ico', 'jpeg', 'jpg', 'png', 'svg', 'bmp', 'dib'];

function mediaIconMap(): Record<string, MediaIcon> {
    const flaticon = (cls: string) => `<span class="lafi flaticon-${cls}"></span>`;

    return {
        txt: { type: 'text', icon: flaticon('txt'), color: '#2196f3', avatar: true },
        audio: { type: 'audio', icon: flaticon('mp3'), color: '#ff9800', avatar: true },
        pdf: { type: 'pdf', icon: flaticon('pdf'), color: '#f44336', avatar: true },
        archive: { type: 'archive', icon: flaticon('zip'), color: '#FBC02D', avatar: true },
        mp4: { type: 'video', icon: flaticon('mp4'), color: '#f44336', avatar: true },
        mov: { type: 'video', icon: flaticon('mp4'), color: '#f44336', avatar: true },
        ai: { type: 'ai', icon: flaticon('ai'), color: '#D9D7CA', avatar: true },
        avi: { type: 'avi', icon: flaticon('avi'), color: '#C8BDB8', avatar: true },
        css: { type: 'css', icon: flaticon('css'), color: '#0096E6', avatar: true },
        csv: { type: 'csv', icon: flaticon('csv'), color: '#C8BDB8', avatar: true },
        dbf: { type: 'dbf', icon: flaticon('dbf'), color: '#C8BDB8', avatar: true },
        doc: { type: 'doc', icon: flaticon('doc'), color: '#8697CB', avatar: true },
        html: { type: 'html', icon: flaticon('html'), color: '#EC6630', avatar: true },
        flash: { type: 'flash', icon: flaticon('flash'), color: '#7A2F32', avatar: true },
        iso: { type: 'iso', icon: flaticon('iso'), color: '#E9E9E0', avatar: true },
        javascript: { type: 'javascript', icon: flaticon('javascript'), color: '#EEAF4B', avatar: true },
        mp3: { type: 'mp3', icon: flaticon('mp3'), color: '#7D6599', avatar: true },
        ppt: { type: 'ppt', icon: flaticon('ppt'), color: '#C8BDB8', avatar: true },
        psd: { type: 'psd', icon: flaticon('psd'), color: '#C8BDB8', avatar: true },
        xls: { type: 'xls', icon: flaticon('xls'), color: '#C8BDB8', avatar: true },
        xml: { type: 'xml', icon: flaticon('xml'), color: '#F29C1F', avatar: true },
        image: { type: 'image', icon: flaticon('jpg'), avatar: false },
    };
}

export function getMediaIcon(type: string | undefined): MediaIcon {
    if (! type) {
        return FOLDER_ICON;
    }

    if (IMAGE_EXTENSIONS.includes(type)) {
        return mediaIconMap().image;
    }

    return mediaIconMap()[type] ?? FOLDER_ICON;
}

export function useMyDriveMixin(): {
    isfilesLoaded: Ref<boolean>,
    isfolderLoaded: Ref<boolean>,
    currentFolder: Ref<MediaItem | null>,
    currentFolderId: Ref<number>,
    isStarredPage: Ref<boolean>,
    isTrashPage: Ref<boolean>,
    loadFolders: () => void,
    openNewFolderModal: () => void,
    toggleSidebar: () => void,
} {
    const route = useRoute();
    const media = useMediaStore();

    const isfilesLoaded = ref(false);
    const isfolderLoaded = ref(false);

    const currentFolder = computed(() => {
        const hash = route.params.folderId as string | undefined;
        const folder = media.folders.find((m) => m.hash === hash);

        return folder ?? null;
    });

    const currentFolderId = computed(() => currentFolder.value?.id ?? 0);

    const isStarredPage = computed(() => route.name === 'starred');
    const isTrashPage = computed(() => route.name === 'trash' || route.name === 'trashFolder');

    function loadFolders() {
        media.getFolders()
            .then(() => {
                isfolderLoaded.value = true;
            });
    }

    function openNewFolderModal() {
        media.newFolderModal = true;
    }

    function toggleSidebar() {
        media.toggleSidebar();
    }

    return {
        isfilesLoaded,
        isfolderLoaded,
        currentFolder,
        currentFolderId,
        isStarredPage,
        isTrashPage,
        loadFolders,
        openNewFolderModal,
        toggleSidebar,
    };
}
