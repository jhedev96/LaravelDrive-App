<script setup lang="ts">
/**
 * User create/edit dialog port (legacy UserForm.vue): vee-validate 4
 * + yup; the grouped vue-multiselect permissions picker becomes a
 * multiple v-select with group headers (W5.5 mapping).
 */
import { computed } from 'vue';

import { useUsersStore } from '@ac/stores/users';

import { Field, Form } from 'vee-validate';

import { object, string, ref as yupRef } from 'yup';

import { storeToRefs } from 'pinia';

const usersStore = useUsersStore();
const { permissions } = storeToRefs(usersStore);

const props = defineProps<{
    user?: Partial<{
        id: number;
        firstname: string;
        lastname: string;
        name: string;
        email: string;
        password?: string;
        password_confirmation?: string;
        permissions: unknown[];
        role?: number;
        avatar?: string;
    }>;
}>();

const emit = defineEmits(['close']);

const current = computed(() => props.user ?? {});

const isEdit = computed(() => Boolean(current.value.id));

const schema = object({
    name: string().required('User name is required').min(6, 'Name must be at least 6 characters'),
    email: string().required('E-mail is required').email('E-mail is invalid'),
    // password is required only on creation (legacy conditional rule)
    password: isEdit.value
        ? string().optional()
        : string().required('Password is required').min(6, 'Password must be at least 6 characters'),
    password_confirmation: isEdit.value
        ? string().optional()
        : string().required('Confirmation is required').oneOf([yupRef('password')], 'Passwords must match'),
});

if (! usersStore.permissions.length) {
    void usersStore.getPermissions();
}

interface GroupedPermission {
    model: string;
    permissions: string[];
}

const permissionItems = computed(() => usersStore.permissions.flatMap((group: unknown) => {
    const g = group as GroupedPermission;

    return [
        { header: g.model, props: { header: g.model } },
        ... (g.permissions ?? []).map((p: string) => ({ title: p, value: p })),
    ];
}));

async function onSubmit(values: Record<string, unknown>): Promise<void> {
    const payload = {
        id: current.value.id,
        firstname: current.value.firstname,
        lastname: current.value.lastname,
        name: String(values.name ?? ''),
        email: String(values.email ?? ''),
        password: values.password,
        password_confirmation: values.password_confirmation,
        permissions: values.permissions ?? [],
        role: current.value.role,
        avatar: current.value.avatar,
    };

    if (! isEdit.value) {
        await usersStore.addUser(payload);
        close();

        return;
    }

    await usersStore.updateUser(payload as { id: number } & Record<string, unknown>);
    close();
}

function close(): void {
    emit('close', false);
}
</script>

<template>
    <v-card>
        <v-card-title>
            <span
                v-if="isEdit"
                class="text-h6"
            >Update User</span>
            <span
                v-else
                class="text-h6"
            >New User</span>
        </v-card-title>

        <Form
            :validation-schema="schema"
            :initial-values="{
                name: current.name,
                email: current.email,
                password: current.password ?? '',
                password_confirmation: current.password_confirmation ?? '',
                permissions: current.permissions ?? [],
            }"
            @submit="onSubmit"
        >
            <v-card-text>
                <v-container grid-list-md>
                    <v-row wrap>
                        <v-col
                            cols="12"
                            sm="6"
                            md="6"
                        >
                            <Field
                                name="firstname"
                                v-slot="{ field }"
                            >
                                <v-text-field
                                    :model-value="current.firstname ?? ''"
                                    v-bind="field"
                                    label="First Name"
                                />
                            </Field>
                        </v-col>

                        <v-col
                            cols="12"
                            sm="6"
                            md="6"
                        >
                            <Field
                                name="lastname"
                                v-slot="{ field }"
                            >
                                <v-text-field
                                    :model-value="current.lastname ?? ''"
                                    v-bind="field"
                                    label="Last Name"
                                />
                            </Field>
                        </v-col>

                        <v-col cols="12">
                            <Field
                                name="name"
                                v-slot="{ field, errorMessage }"
                            >
                                <v-text-field
                                    v-bind="field"
                                    label="User Name*"
                                    :disabled="isEdit"
                                    counter="6"
                                    :error="!!errorMessage"
                                    :error-messages="errorMessage ? [errorMessage] : []"
                                    required
                                />
                            </Field>
                        </v-col>

                        <v-col cols="12">
                            <Field
                                name="email"
                                v-slot="{ field, errorMessage }"
                            >
                                <v-text-field
                                    v-bind="field"
                                    label="E-mail*"
                                    :disabled="isEdit"
                                    :error="!!errorMessage"
                                    :error-messages="errorMessage ? [errorMessage] : []"
                                    required
                                />
                            </Field>
                        </v-col>

                        <template v-if="! isEdit">
                            <v-col cols="12">
                                <Field
                                    name="password"
                                    v-slot="{ field, errorMessage }"
                                >
                                    <v-text-field
                                        v-bind="field"
                                        label="Password"
                                        type="password"
                                        :error="!!errorMessage"
                                        :error-messages="errorMessage ? [errorMessage] : []"
                                    />
                                </Field>
                            </v-col>

                            <v-col cols="12">
                                <Field
                                    name="password_confirmation"
                                    v-slot="{ field, errorMessage }"
                                >
                                    <v-text-field
                                        v-bind="field"
                                        name="password_confirmation"
                                        label="Confirmed Password"
                                        type="password"
                                        :error="!!errorMessage"
                                        :error-messages="errorMessage ? [errorMessage] : []"
                                    />
                                </Field>
                            </v-col>
                        </template>

                        <v-col cols="12">
                            <v-label>Permissions</v-label>

                            <Field
                                name="permissions"
                                v-slot="{ field, value }"
                            >
                                <v-select
                                    :model-value="value"
                                    :items="permissionItems"
                                    label="Add Permissions"
                                    multiple
                                    chips
                                    closable-chips
                                    hide-details
                                    no-data-text="Oops! No Permissions found."
                                    @update:model-value="field.onChange"
                                />
                            </Field>
                        </v-col>
                    </v-row>
                </v-container>

                <small>*indicates required field</small>
            </v-card-text>

            <v-divider />

            <v-card-actions>
                <v-spacer />
                <v-btn
                    color="primary"
                    variant="text"
                    @click="close()"
                >
                    Close
                </v-btn>
                <v-btn
                    color="primary"
                    variant="text"
                    type="submit"
                >
                    Save
                </v-btn>
            </v-card-actions>
        </Form>
    </v-card>
</template>
