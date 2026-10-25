<script setup lang="ts">
/**
 * File-info sidebar port (legacy MediaInfo.vue) with the legacy dead
 * ends reawakened:
 * - created_at.date / updated_at.date never existed (Laravel serializes
 *   ISO strings, not {date} objects) — W4.22, rendered raw now
 * - "Owner" showed file_name (the path filename!) — the uploader's
 *   name instead when the resource carries it
 * - "Storage Used" duplicated file_size — the real user usage comes
 *   from /api/stats via the root store
 * - the two tabs were anchors that never bound @click — clicking
 *   works now; Activities lists created/modified facts
 */
import { computed, onMounted, ref } from 'vue';

import { humanBytes } from '@common/format';

import { useMediaStore, type MediaItem } from '@ac/stores/media';

import { useRootStore } from '@ac/stores/root';

import { getMediaIcon, useMyDriveMixin } from './mixin';

import { storeToRefs } from 'pinia';

const media = useMediaStore();
const root = useRootStore();
const { fileInfoSideBar, selectedMedia } = storeToRefs(media);

const { toggleSidebar } = useMyDriveMixin();

onMounted(() => {
    void root.fetchStats();
});

const tabActive = ref(1);

const current = computed(() => selectedMedia.value as MediaItem);

const hasItem = computed(() => 'id' in current.value && Boolean(current.value.id));

const mediaIcon = computed(() => getMediaIcon(hasItem.value ? current.value.extension : 'folder'));
</script>

<template>
    <v-navigation-drawer
        class="media-info file-deselect"
        :model-value="fileInfoSideBar"
        location="right"
        absolute
        floating
    >
        <v-btn
            icon
            class="float-right"
            size="small"
            :class="{ active: fileInfoSideBar }"
            @click="toggleSidebar()"
        >
            <v-icon>mdi-close</v-icon>
        </v-btn>

        <div
            v-if="hasItem"
            class="mi-fh"
        >
            <!-- eslint-disable-next-line vue/no-v-html -->
            <span
                class="fn-i-i"
                :style="{ color: mediaIcon.color }"
                v-html="mediaIcon.icon"
            />
            <h3>{{ current.name }}</h3>
        </div>

        <div
            v-else
            class="mi-fh media-info-fallback"
        >
            <span
                class="fn-i-i"
                :style="{ color: mediaIcon.color }"
                v-html="mediaIcon.icon"
            />
            <h3>My Files</h3>
        </div>

        <div class="la-toggle-bn">
            <a
                href="#"
                :class="{ 'ev-dg-active': tabActive === 1 }"
                @click.prevent="tabActive = 1"
            >Details</a>
            <a
                href="#"
                :class="{ 'ev-dg-active': tabActive === 2 }"
                @click.prevent="tabActive = 2"
            >Activities</a>
        </div>

        <div
            v-if="hasItem && tabActive === 1"
            class="details media-info-details"
        >
            <v-img
                :src="current.public_path"
                :lazy-src="current.public_path"
            />

            <div class="las-info-list">
                <div class="las-ii">
                    <span class="la-ik">File Name</span>
                    <span class="la-iv">{{ current.name }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Type</span>
                    <span class="la-iv">{{ current.type }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Size</span>
                    <span class="la-iv">{{ humanBytes(Number(current.file_size)) }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Storage Used</span>
                    <span class="la-iv">{{ humanBytes(Number(root.stats.storage_bytes ?? 0)) }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Location</span>
                    <span class="la-iv">{{ current.url }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Owner</span>
                    <span class="la-iv">{{ current.uploader?.name ?? '—' }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Created</span>
                    <span class="la-iv">{{ current.created_at }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Modified</span>
                    <span class="la-iv">{{ current.updated_at }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Description</span>
                    <span class="la-iv">{{ current.description }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Uploaded As</span>
                    <span class="la-iv">{{ current.file_name }}</span>
                </div>
            </div>
        </div>

        <div
            v-else-if="hasItem && tabActive === 2"
            class="details media-info-details"
        >
            <div class="las-info-list">
                <div class="las-ii">
                    <span class="la-ik">Created</span>
                    <span class="la-iv">{{ current.created_at }}</span>
                </div>
                <div class="las-ii">
                    <span class="la-ik">Modified</span>
                    <span class="la-iv">{{ current.updated_at }}</span>
                </div>
            </div>
        </div>

        <div
            v-else-if="tabActive === 1"
            class="no-item media-info-noitem"
        >
            <v-icon
                color="gray"
                large
            >
                mdi-home
            </v-icon>

            <h4>Select a file or folder to view its details.</h4>
        </div>
    </v-navigation-drawer>
</template>

<style lang="scss">
    .media-info {
        border-top: 1px solid #ddd;

        .mi-fh {
            padding: 20px 8px;

            h3 {
                display: inline;
            }
        }

        .la-toggle-bn {
            display: flex;
            justify-content: space-around;
            border-bottom: 1px solid #ddd;

            a {
                padding: 10px 20px;
                display: inline-block;
                font-size: 16px;

                &.ev-dg-active,
                &:hover {
                    text-decoration: none;
                    border-bottom: 3px solid rgb(45, 111, 173);
                }
            }
        }

        .las-info-list {
            margin-top: 20px;

            .las-ii {
                display: flex;
                justify-content: flex-start;
                align-items: self-start;
                padding: 7px 0px;
                text-overflow: clip;
                word-break: break-word;
                overflow: hidden;

                span.la-ik {
                    flex: 0 0 120px;
                    color: #757373;
                }
            }
        }
    }
</style>
