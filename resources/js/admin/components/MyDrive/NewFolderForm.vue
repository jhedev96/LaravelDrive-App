<script setup lang="ts">
/**
 * New-folder dialog (legacy NewFolderForm.vue) — vee-validate 4 + yup
 * replaces the v3 ValidationProvider; the legacy min:6 rule on folder
 * names is kept for behavioral parity (a legacy quirk, noted in TASKS).
 * The "Craete" typo button is now "Create".
 */
import { Field, Form } from 'vee-validate';
import { storeToRefs } from 'pinia';
import { object, string } from 'yup';

import { useMediaStore } from '@ac/stores/media';

import { useMyDriveMixin } from './mixin';

const media = useMediaStore();
const { newFolderModal } = storeToRefs(media);

const { currentFolderId } = useMyDriveMixin();

const schema = object({
    name: string().required('Name is required').min(6, 'Name must be at least 6 characters'),
});

async function onSubmit(values: Record<string, unknown>): Promise<void> {
    await media.addFolder({
        name: String(values.name ?? ''),
        parent_id: currentFolderId.value,
    });

    close();
}

function close(): void {
    media.newFolderModal = false;
}
</script>

<template>
    <v-dialog
        :model-value="newFolderModal"
        class="mpu"
        width="500"
        persistent
        @update:model-value="close()"
    >
        <Form
            :validation-schema="schema"
            @submit="onSubmit"
        >
            <v-card>
                <v-card-title class="bg-grey-lighten-2">
                    New Folder
                </v-card-title>

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
                        Create
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
