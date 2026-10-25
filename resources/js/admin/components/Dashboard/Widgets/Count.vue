<script setup lang="ts">
/**
 * Stat widget port (legacy Dashboard/Widgets/Count.vue) — mdi icon and
 * the label/value slot.
 */
defineProps<{
    icon?: string;
}>();
</script>

<template>
    <v-card>
        <v-row no-gutters>
            <v-col
                cols="3"
                class="counter-icon"
            >
                <v-icon size="30">
                    {{ icon }}
                </v-icon>
            </v-col>

            <v-col cols="9">
                <v-card-title class="d-block text-body-1">
                    <slot />
                </v-card-title>
            </v-col>
        </v-row>
    </v-card>
</template>

<style>
.counter-icon {
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>
