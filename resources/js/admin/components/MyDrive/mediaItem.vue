<script setup lang="ts">
/**
 * Media item card port (legacy mediaItem.vue). Public file preview
 * still uses `media.public_path` (the resource serializes it); the
 * icon row keeps the flaticon spans via v-html.
 */
import { computed, type PropType } from 'vue';

import { getMediaIcon } from './mixin';
import type { MediaItem } from '@ac/stores/media';

import { useMediaStore } from '@ac/stores/media';

import { storeToRefs } from 'pinia';

const props = defineProps({
    media: {
        type: Object as PropType<MediaItem>,
        required: true,
    },
    editable: Boolean,
});

const mediaStore = useMediaStore();
const { selectedFilesId } = storeToRefs(mediaStore);

const mediaIcon = computed(() => getMediaIcon(props.media.extension));

const isImage = computed(() => (['gif', 'ico', 'jpeg', 'jpg', 'png', 'svg', 'bmp', 'dib'] as string[]).includes(props.media.extension));
</script>

<template>
    <div
        class="media-item"
        :class="{ selected: selectedFilesId.includes(media.id) }"
    >
        <div class="card-inner">
            <div class="la-file-name">
                <div
                    class="fi"
                    :class="mediaIcon.type"
                    :style="{ color: mediaIcon.color }"
                >
                    <div
                        v-if="isImage"
                        class="la-fii"
                    >
                        <v-img
                            :src="media.public_path as string"
                            height="166"
                            :lazy-src="media.public_path as string"
                        />
                    </div>
                    <div
                        v-else
                        class="la-fia"
                    >
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <v-avatar
                            size="166"
                            tile
                            v-html="mediaIcon.icon"
                        />
                    </div>
                </div>

                <div class="fn">
                    <div class="fn-i">
                        <span
                            class="fn-i-i"
                            :style="{ color: mediaIcon.color }"
                            v-html="mediaIcon.icon"
                        />
                        <div class="fn-i-t">
                            <span
                                class="filename"
                                v-text="media.name"
                            />
                        </div>
                    </div>
                </div>

                <span
                    v-if="media.stared"
                    class="fstared material-icons"
                >
                    star
                </span>
            </div>
        </div>
    </div>
</template>

<style lang="scss">
#filecontainer .media-item {
    .card-inner {
        border: 1px solid #e8eaed;
        border-radius: 6px;
        box-shadow: none;
        position: relative;
        width: 100%;
    }

    &.selected .card-inner {
        border: 1px solid #226cdb;
    }

    .la-fia {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .fn {
        height: 48px;
        width: 100%;
        border-radius: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;

        .fn-i {
            display: flex;
            padding: 5px;
            justify-content: flex-start;
            align-items: center;
            height: 100%;

            .fn-i-i {
                padding: 0 10px;

                span {
                    font-size: 16px;
                    vertical-align: middle;
                }
            }

            .fn-i-t {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                padding-right: 10px;
            }
        }
    }

    .fi .lafi {
        font-size: 90px;
    }
}

span.fstared {
    position: absolute;
    top: -10px;
    right: -10px;
    color: #d4a002;
}
</style>
