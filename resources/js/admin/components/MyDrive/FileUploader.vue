<script setup lang="ts">
/**
 * Chunked uploader port (legacy FileUploader.vue): a Dropzone instance
 * tuned for the /api/file chunk contract (forceChunking, 1 MB chunks,
 * parallel chunks, retries). `sending` appends `path` (which keeps
 * nested folders uploading under their relative path) and the current
 * parent_id. Success pushes the created resource into the media store.
 * The upload progress card below is a port; "expandLess" stays.
 */
import { computed, onBeforeUnmount, onMounted, ref, type PropType } from 'vue';
import { useTemplateRef } from 'vue';

import Dropzone from '@ac/components/dropzone';

import { useMediaStore } from '@ac/stores/media';

import { getMediaIcon, useMyDriveMixin } from './mixin';

import { bus, readLdConfig, savedBearer } from '@common/bootstrap';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

interface UploadedFile {
    name: string;
    size: number;
    type: string;
    ldporgress: number;
    [key: string]: unknown;
}

const dropzoneRef = useTemplateRef<InstanceType<typeof Dropzone>>('myVueDropzone');

const expandLess = ref(true);
const filesAddedPopup = ref(false);
const fileList = ref<UploadedFile[]>([]);

const media = useMediaStore();

const { currentFolderId } = useMyDriveMixin();

const visible = computed(() => props.modelValue);

const dropzoneOptions = computed<Record<string, unknown>>(() => ({
    url: '/api/file',
    method: 'POST',
    thumbnailWidth: 200,
    parallelUploads: 1,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': readLdConfig().csrfToken,
        // auth:api needs the Passport bearer (W4.21 arm-up did the setup)
        ...(savedBearer() ? { Authorization: `Bearer ${savedBearer()}` } : {}),
    },
    chunking: true,
    forceChunking: true,
    maxFilesize: 400000000,
    chunkSize: 1000000,
    parallelChunkUploads: true,
    retryChunks: true,
    retryChunksLimit: 3,
}));

const dropzoneStyle = computed(() => (visible.value
    ? { display: 'block', opacity: 1 }
    : { display: 'none', opacity: 0 }));

function iconFor(file: UploadedFile) {
    const subtype = String(file.type ?? '').split('/')[1] ?? '';

    return getMediaIcon(subtype);
}

function openUploader(): void {
    dropzoneRef.value?.openPicker();
}

function uploadFolder(): void {
    dropzoneRef.value?.openFolderPicker();
}

function onBusOpen(): void {
    openUploader();
}

function onBusFolder(): void {
    uploadFolder();
}

onMounted(() => {
    bus.on('openDropZone', onBusOpen);
    bus.on('uploadFolder', onBusFolder);
});

onBeforeUnmount(() => {
    bus.off('openDropZone', onBusOpen);
    bus.off('uploadFolder', onBusFolder);
});

function deactive(event: Event): void {
    event.stopPropagation();
    event.preventDefault();
    emit('update:modelValue', false);
}

function onUploadProgress(file: UploadedFile, progress: number): void {
    const i = fileList.value.findIndex((f) => f.size === file.size);

    if (i !== -1) {
        fileList.value[i].ldporgress = progress;
    }
}

function onProcessing(file: UploadedFile): void {
    const i = fileList.value.findIndex((f) => f.size === file.size);

    if (i !== -1) {
        fileList.value[i] = Object.assign(file, fileList.value[i]);
    }
}

function onFilesAdded(files: UploadedFile[]): void {
    filesAddedPopup.value = true;

    files.forEach((file) => {
        file.ldporgress = 0;
        fileList.value.push(file);
    });
}

function onSuccess(file: { xhr?: { response?: string } }): void {
    try {
        const response = JSON.parse(file.xhr?.response ?? '');

        media.setMediaItem(response.data);
    } catch {
        // the final chunk always answers JSON; a parse miss means the
        // request errored — the dropzone error event reports it
    }
}

function onChunksUploaded(_file: unknown, done: () => void): void {
    done();
}

function onSending(file: Record<string, unknown | string | undefined>, _xhr: unknown, formData: FormData): void {
    let path = (file.fullPath ?? file.webkitRelativePath ?? file.mozRelativePath) as string | undefined;

    if (typeof path === 'undefined') {
        path = file.name as string;
    }

    formData.append('path', `/${path}`);
    formData.append('parent_id', String(currentFolderId.value));
}
</script>

<template>
    <div>
        <Dropzone
            id="laraveladmin"
            ref="myVueDropzone"
            :options="dropzoneOptions"
            :style="dropzoneStyle"

            @vdropzone-files-added="onFilesAdded"
            @vdropzone-success="onSuccess"
            @vdropzone-processing="onProcessing"
            @vdropzone-chunks-uploaded="onChunksUploaded"
            @vdropzone-upload-progress="onUploadProgress"
            @vdropzone-sending="onSending"
            @vdropzone-drop="deactive"
            @vdropzone-drag-leave="deactive"
        />

        <v-card
            v-if="filesAddedPopup"
            class="upload-file-list elevation-8"
        >
            <v-toolbar
                color="error"
            >
                <v-toolbar-title class="font-weight-medium">
                    Uploading files
                </v-toolbar-title>

                <v-spacer />

                <v-btn icon @click="expandLess = !expandLess">
                    <v-icon>{{ expandLess ? 'mdi-chevron-down' : 'mdi-chevron-up' }}</v-icon>
                </v-btn>

                <v-btn icon @click="filesAddedPopup = false">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-toolbar>

            <v-card-text v-show="expandLess">
                <ul>
                    <li
                        v-for="file in fileList"
                        :key="`${file.size}-${file.name}`"
                    >
                        <div class="file-name">
                            <span
                                class="fn-i-i"
                                :style="{ color: iconFor(file).color }"
                                v-html="iconFor(file).icon"
                            />
                            <span>{{ file.name }}</span>
                        </div>

                        <v-progress-circular
                            size="28"
                            width="3"
                            :model-value="file.ldporgress"
                        />
                    </li>
                </ul>
            </v-card-text>
        </v-card>
    </div>
</template>

<style>
.v-card.upload-file-list {
    position: absolute;
    bottom: 20px;
    width: 500px;
    right: 0;
    z-index: 4;
}

.v-card.upload-file-list ul {
    padding: 0;
    margin: 0;
    list-style: none;
}

.v-card.upload-file-list ul li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.v-card.upload-file-list ul li .file-name {
    max-width: 80%;
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
}
</style>
