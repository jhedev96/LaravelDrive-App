<script setup lang="ts">
/**
 * Rename dialog (legacy RenameFile.vue) — renames selectedMedia via
 * PUT /api/file/{id} with the store update fix (W4.19d: the legacy
 * committed the response wrapper so the grid never updated).
 */
import { Field, Form } from 'vee-validate';
import { storeToRefs } from 'pinia';
import { object, string } from 'yup';

import { useMediaStore } from '@ac/stores/media';

const media = useMediaStore();
const { renamefilemodal, selectedMedia } = storeToRefs(media);

const schema = object({
    name: string().required('Name is required').min(6, 'Name must be at least 6 characters'),
});

async function onSubmit(values: Record<string, unknown>): Promise<void> {
    const current = selectedMedia.value;

    if (! current?.id) {
        close();

        return;
    }

    await media.updateItem({ id: current.id, name: String(values.name ?? '') });

    close();
}

function close(): void {
    media.renamefilemodal = false;
}
</script>

<template>
    <v-dialog
        :model-value="renamefilemodal"
        class="mpu"
        width="500"
        persistent
        @update:model-value="close()"
    >
        <Form
            class="file-deselect"
            :validation-schema="schema"
            :initial-values="{ name: selectedMedia.name }"
            @submit="onSubmit"
        >
            <v-card>
                <v-card-title class="bg-grey-lighten-2">Rename File</v-card-title>

                <v-card-text class="mt-3">
                    <Field
                        name="name"
                        v-slot="{ field, errorMessage }"
                    >
                        <v-text-field
                            v-bind="field"
                            label="Name"
                            required
                            :error="!!errorMessage"
                            :error-messages="errorMessage"
                        />
                    </Field>
                </v-card-text>

                <v-divider />

                <v-card-actions>
                    <v-btn
                        color="primary"
                        type="submit"
                    >
                        Rename
                    </v-btn>
                    <v-btn
                        color="error"
                        @click="close"
                    >
                        Cancel
                    </v-btn>
                </v-card-actions>
            </v-card>
        </Form>
    </v-dialog>
</template>
