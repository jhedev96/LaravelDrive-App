<script setup lang="ts">
/**
 * Role create/edit form port (legacy RoleForm.vue). The name field
 * collapsed/expanded on focus (halfForm), permissions via the grouped
 * multi-select (vue-multiselect → v-select per W5.5). The legacy
 * "primery" button color typo is fixed.
 */
import { computed, ref } from 'vue';

import { useUsersStore, type AppRole } from '@ac/stores/users';

import { storeToRefs } from 'pinia';

const usersStore = useUsersStore();
const { permissions } = storeToRefs(usersStore);

const props = withDefaults(defineProps<{
    role?: Partial<AppRole>;
    halfForm?: boolean;
}>(), {
    role: () => ({
        id: 0,
        name: '',
        description: '',
        permissions: [],
        status: true,
    }),
    halfForm: true,
});

const emit = defineEmits(['close']);

const loading = ref(false);
const showForm = ref(Boolean(props.halfForm));
const name = ref(props.role?.name ?? '');
const description = ref(props.role?.description ?? '');

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

const selected = ref<unknown[]>((props.role?.permissions as unknown[] | undefined) ?? []);

async function submitRole(): Promise<void> {
    loading.value = true;

    const payload = {
        id: props.role?.id,
        name: name.value,
        description: description.value,
        permissions: selected.value,
        status: props.role?.status ?? true,
    };

    try {
        if (! props.role?.id) {
            await usersStore.addRole(payload as never);
        } else {
            await usersStore.updateRole(payload as never);
        }

        loading.value = false;
        emit('close', 'true');
        showForm.value = false;
    } catch {
        loading.value = false;
    }
}

function showFullForm(): void {
    showForm.value = true;
}

function hideFullForm(): void {
    emit('close', 'true');
    showForm.value = false;
}
</script>

<template>
    <form
        class="role-form"
        @submit.prevent="submitRole()"
    >
        <v-col cols="12">
            <v-text-field
                v-model="name"
                placeholder="Add Role"
                hide-details
                light
                flat
                @focus="showFullForm()"
            />
        </v-col>

        <template v-if="showForm">
            <v-col cols="12">
                <textarea
                    v-model="description"
                    class="role-description"
                    placeholder="Description"
                />
            </v-col>

            <v-col cols="12">
                <v-select
                    v-model="selected"
                    :items="permissionItems"
                    multiple
                    chips
                    closable-chips
                    hide-details
                    placeholder="Add Permissions"
                    no-data-text="Oops! No Permissions found."
                />
            </v-col>

            <v-col cols="12">
                <v-btn
                    :loading="loading"
                    color="primary"
                    type="submit"
                    small
                >
                    Submit
                </v-btn>
                <v-btn
                    color="error"
                    small
                    @click="hideFullForm()"
                >
                    close
                </v-btn>
            </v-col>
        </template>
    </form>
</template>

<style>
form.role-form {
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
}

.role-form textarea.role-description {
    border: 1px solid #ddd;
    margin: 10px 0px;
    width: 100%;
    padding: 5px;
}

.role-form button {
    margin-top: 13px;
}
</style>
