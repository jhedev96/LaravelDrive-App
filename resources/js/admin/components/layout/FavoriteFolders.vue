<script setup lang="ts">
/**
 * Favorite folders in the side menu (legacy layout/FavoriteFolders.vue);
 * feeds from the Media store getter like the Vuex mapGetters did.
 */
import { useMediaStore } from '@ac/stores/media';

const media = useMediaStore();
</script>

<template>
    <v-list-group value="favorite">
        <template #activator="{ props: activator }">
            <v-list-item
                v-bind="activator"
                prepend-icon="mdi-star"
            >
                <v-list-item-title>
                    Favorite Folder
                </v-list-item-title>
            </v-list-item>
        </template>

        <v-list-item
            v-for="folder in media.favoriteFolders"
            :key="folder.id"
            :to="{ name: 'singleFolder', params: { folderId: folder.hash } }"
        >
            <template #prepend>
                <v-icon>mdi-folder</v-icon>
            </template>

            <v-list-item-title>
                {{ folder.name }}
            </v-list-item-title>
        </v-list-item>
    </v-list-group>
</template>

<style scoped>
.favorite-folder :deep(.v-list-group__items .v-list-item) {
    padding-left: 15px;
}
</style>
