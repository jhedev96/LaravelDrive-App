<script setup lang="ts">
/**
 * Shareable-link dialog port (legacy ShareLink.vue): find/create a
 * link for the first selected file, expiry picker, password,
 * allow-edit/download flags, copy and delete.
 *
 * Fixes carried over:
 * - the legacy copy used document.execCommand('copy') on a focused
 *   input (dead in modern browsers) — clipboard API with a select
 *   fallback (manual Ctrl+C) now (W4.24)
 * - the legacy sent `expires_at` even with expiry off ('' + ' '
 *   builds "  " — mildly corrupting); expiry-off sends null now
 * - the date picker inputs bound v-model straight into the submit —
 *   kept via v-date-picker (Vuetify 4) and a native time field for
 *   the HH:MM part (cheaper than a full time picker)
 */
import { computed, ref, useTemplateRef } from 'vue';

import { useMediaStore } from '@ac/stores/media';

import { useRootStore } from '@ac/stores/root';

import { api } from '@common/bootstrap';

import { storeToRefs } from 'pinia';

interface FileLink {
    id?: number;
    link?: string;
    hash?: string;
    allow_edit?: boolean;
    allow_download?: boolean;
    expires_at?: string | null;
    [key: string]: unknown;
}

const media = useMediaStore();
const root = useRootStore();

const { shareLinkModal, selectedFilesId } = storeToRefs(media);

const sharelink = useTemplateRef<{ focus?: () => void, select?: () => void }>('sharelink');

const loading = ref(false);
const showSettings = ref(false);
const linkExpier = ref(false);
const linkExpierDate = ref('');
const linkExpierTime = ref('');
const dateMenu = ref(false);
const password = ref(false);
const linkPassword = ref('');
const showPassword = ref(false);
const editable = ref(false);
const downloadable = ref(true);
const linkdata = ref<Partial<FileLink>>({});

const hasLinkData = computed(() => Boolean(linkdata.value.id));

function getShareableLink(): void {
    void api.get(`/api/shareable-links/file/${String(selectedFilesId.value[0] ?? '')}`)
        .then((res: { data: { data?: Partial<FileLink> } }) => {
            linkdata.value = res.data.data ?? {};

            if (linkdata.value.id) {
                if (linkdata.value.allow_edit !== undefined) {
                    editable.value = Boolean(linkdata.value.allow_edit);
                }

                if (linkdata.value.allow_download !== undefined) {
                    downloadable.value = Boolean(linkdata.value.allow_download);
                }
            }

            const expires = linkdata.value.expires_at;

            if (expires) {
                linkExpier.value = true;
                linkExpierDate.value = String(expires).slice(0, 10);
                linkExpierTime.value = String(expires).slice(11, 16);
            }
        });
}

getShareableLink();

function storeShareableLink(): void {
    if (loading.value) {
        return;
    }

    loading.value = true;

    void api.post(`/api/shareable-links/file/${String(selectedFilesId.value[0] ?? '')}`, {
        file_id: selectedFilesId.value[0],
        allow_edit: editable.value,
        allow_download: downloadable.value,
        password: password.value ? linkPassword.value : null,
        expires_at: linkExpier.value ? `${linkExpierDate.value} ${linkExpierTime.value}` : null,
    })
        .then((res: { data: { data: Partial<FileLink> } }) => {
            linkdata.value = res.data.data;
            loading.value = false;
        });
}

async function deleteShareableLink(): Promise<void> {
    if (! hasLinkData.value) {
        return;
    }

    await api.delete(`/api/shareable-links/${linkdata.value.id}`);

    close();
}

function clearExpiry(): void {
    if (! linkExpier.value) {
        linkExpierDate.value = '';
        linkExpierTime.value = '';
    }
}

async function copyLink(): Promise<void> {
    const text = String(linkdata.value.link ?? '');

    try {
        await navigator.clipboard.writeText(text);

        root.setSnackbar({
            message: 'Link Copied successfully',
            color: 'success',
            show: true,
        });

        return;
    } catch {
        // fall through to manual selection fallback (legacy semantics)
    }

    sharelink.value?.focus?.();
    sharelink.value?.select?.();

    root.setSnackbar({
        message: 'Press Ctrl+C to copy the selected link',
        color: 'info',
        show: true,
    });
}

function close(): void {
    media.shareLinkModal = false;
}
</script>

<template>
    <v-dialog
        :model-value="shareLinkModal"
        class="mpu"
        max-width="500"
        persistent
    >
        <v-card class="dl-share-modal">
            <v-card-title class="bg-grey-lighten-2">
                Shareable link
            </v-card-title>

            <form
                class="file-deselect"
                @submit.prevent="storeShareableLink"
            >
                <v-card-text class="mt-4">
                    <v-row
                        justify="space-between"
                        class="mb-2"
                    >
                        <v-col><strong>Link sharing is on</strong></v-col>

                        <v-col class="text-right">
                            <v-btn
                                v-if="hasLinkData"
                                icon
                                size="x-small"
                                variant="outlined"
                                rounded
                                color="error"
                                @click="deleteShareableLink"
                            >
                                <v-icon>mdi-delete</v-icon>
                            </v-btn>

                            <v-btn
                                icon
                                size="x-small"
                                variant="outlined"
                                rounded
                                @click="showSettings = ! showSettings"
                            >
                                <v-icon>mdi-pencil</v-icon>
                            </v-btn>
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col
                            cols="12"
                            class="pb-0"
                        >
                            <v-text-field
                                ref="sharelink"
                                variant="outlined"
                                label="Link"
                                :model-value="linkdata.link"
                                type="text"
                                readonly
                                @focus="(e: FocusEvent) => (e.target as HTMLInputElement)?.select()"
                            >
                                <template #append-inner>
                                    <v-btn
                                        color="primary"
                                        size="x-small"
                                        @click="copyLink"
                                    >
                                        Copy
                                    </v-btn>
                                </template>
                            </v-text-field>
                        </v-col>
                    </v-row>

                    <div
                        v-if="showSettings"
                        class="pa-2"
                    >
                        <v-row class="boundary-b">
                            <v-col
                                cols="12"
                                align-self="center"
                            >
                                <p>Link expiration</p>
                                <v-checkbox
                                    v-model="linkExpier"
                                    hide-details
                                    label="Link is valid until:"
                                    @change="clearExpiry"
                                />
                            </v-col>

                            <v-col
                                v-if="linkExpier"
                                cols="6"
                            >
                                <v-menu
                                    v-model="dateMenu"
                                    :close-on-content-click="false"
                                    transition="scale-transition"
                                >
                                    <template #activator="{ props: menu }">
                                        <v-text-field
                                            v-model="linkExpierDate"
                                            v-bind="menu"
                                            label="YYYY-MM-DD"
                                            prepend-icon="mdi-calendar"
                                            readonly
                                        />
                                    </template>

                                    <v-date-picker
                                        v-model="linkExpierDate"
                                        @update:model-value="dateMenu = false"
                                    />
                                </v-menu>
                            </v-col>

                            <v-col
                                v-if="linkExpier"
                                cols="6"
                            >
                                <v-text-field
                                    v-model="linkExpierTime"
                                    label="HH:MM"
                                    type="time"
                                    hint="Leave empty for midnight"
                                    persistent-hint
                                />
                            </v-col>
                        </v-row>

                        <v-row class="boundary-b">
                            <v-col>
                                <p>Password Protect</p>
                                <v-checkbox
                                    v-model="password"
                                    hide-details
                                    label="Require password:"
                                />
                                <v-text-field
                                    v-if="password"
                                    v-model="linkPassword"
                                    :append-inner-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="LinkPassword"
                                    hint="At least 8 characters"
                                    persistent-hint
                                    counter
                                    @click:append-inner="showPassword = ! showPassword"
                                />
                            </v-col>
                        </v-row>

                        <v-row class="boundary-b">
                            <v-col>
                                <p>Allow editing</p>
                                <v-checkbox
                                    v-model="editable"
                                    hide-details
                                    label="Users with link can modify this item."
                                />
                            </v-col>
                        </v-row>

                        <v-row>
                            <v-col>
                                <p>Allow download</p>
                                <v-checkbox
                                    v-model="downloadable"
                                    hide-details
                                    label="Users with link can download this item."
                                />
                            </v-col>
                        </v-row>
                    </div>
                </v-card-text>
            </form>

            <v-card-actions>
                <v-btn
                    color="primary"
                    @click="storeShareableLink"
                >
                    {{ hasLinkData ? 'Update Link' : 'Create Link' }}
                </v-btn>
                <v-btn
                    color="error"
                    @click="close"
                >
                    Cancel
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style>
</style>
