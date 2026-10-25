<script setup lang="ts">
/**
 * Minimal Dropzone 5 wrapper (W5.5) — replaces the legacy ~300-line
 * clone of vue2-dropzone. It bridges only the events the app actually
 * consumes and keeps the chunk-upload contract identical:
 * dzuuid/dzchunkindex/dzchunkbyteoffset params flow from Dropzone 5's
 * chunking, `sending` lets the caller append path/parent_id, and the
 * final chunk's response carries the created resource.
 *
 * Not ported: the legacy wrapper's AWS-S3 signing branch (services.js
 * getSignedURL) — the FE never configured it; cloud offload happens
 * server-side via UploadToCloud (W3.6).
 */
import { onBeforeUnmount, onMounted } from 'vue';
import { useTemplateRef } from 'vue';

import DropzoneLib from 'dropzone';

// the default import carries the merged `export = Dropzone` class — its
// type position IS the instance type
type DropzoneInstance = DropzoneLib;

const emit = defineEmits<{
    /* the wrapper bridges Dropzone 5's loose JS payloads; listeners
       narrow these on their own side */
    /* eslint-disable @typescript-eslint/no-explicit-any */
    'vdropzone-file-added': [any],
    'vdropzone-files-added': [any],
    'vdropzone-processing': [any],
    'vdropzone-chunks-uploaded': [any, any],
    'vdropzone-upload-progress': [any, any, any],
    'vdropzone-sending': [any, any, any],
    'vdropzone-success': [any, any],
    'vdropzone-error': [any, any, any],
    'vdropzone-complete': [any],
    'vdropzone-queue-complete': [],
    'vdropzone-drop': [any],
    'vdropzone-drag-enter': [any],
    'vdropzone-drag-leave': [any],
    'vdropzone-removed-file': [any],
    'vdropzone-mounted': [],
}>();

const props = defineProps<{
    id?: string;
    options: Record<string, unknown>;
    includeStyling?: boolean;
    destroyDropzone?: boolean;
}>();

const rootEl = useTemplateRef<HTMLDivElement>('rootEl');

let dropzone: DropzoneInstance | null = null;

const BRIDGE = {
    addedfile: 'vdropzone-file-added',
    addedfiles: 'vdropzone-files-added',
    processing: 'vdropzone-processing',
    chunksUploaded: 'vdropzone-chunks-uploaded',
    uploadprogress: 'vdropzone-upload-progress',
    sending: 'vdropzone-sending',
    success: 'vdropzone-success',
    error: 'vdropzone-error',
    complete: 'vdropzone-complete',
    queuecomplete: 'vdropzone-queue-complete',
    drop: 'vdropzone-drop',
    dragenter: 'vdropzone-drag-enter',
    dragleave: 'vdropzone-drag-leave',
    removedfile: 'vdropzone-removed-file',
} as const;

onMounted(() => {
    DropzoneLib.autoDiscover = false;

    dropzone = new DropzoneLib(rootEl.value as HTMLDivElement, props.options);

    Object.entries(BRIDGE).forEach(([dzEvent, vueEvent]) => {
        dropzone?.on(dzEvent, ((...args: unknown[]) => {
            (emit as (name: string, ...rest: unknown[]) => void)(vueEvent, ...args);
        }) as never);
    });

    emit('vdropzone-mounted');
});

onBeforeUnmount(() => {
    if (props.destroyDropzone !== false) {
        dropzone?.destroy();
    }

    dropzone = null;
});

defineExpose({
    get dropzone(): DropzoneInstance | null {
        return dropzone;
    },

    // legacy $refs parity: click the hidden file input
    openPicker(): void {
        rootEl.value?.querySelector<HTMLInputElement>('input[type="file"]')?.click();
    },

    openFolderPicker(): void {
        const input = rootEl.value?.querySelector<HTMLInputElement>('input[type="file"]');

        if (! input) {
            return;
        }

        input.setAttribute('webkitdirectory', '');
        input.setAttribute('mozdirectory', '');
        input.setAttribute('directory', '');

        input.click();
    },
});
</script>

<template>
    <div
        :id="id"
        ref="rootEl"
        :class="{ 'vue-dropzone dropzone': includeStyling !== false }"
    >
        <div class="dz-message">
            <slot>Drop files here to upload</slot>
        </div>
    </div>
</template>
