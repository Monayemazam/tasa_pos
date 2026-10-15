<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import FormField from '@/components/tasa/FormField.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';
import { showToast } from '@/lib/toast';

interface RoleItem {
    id: number;
    name: string;
    description: string | null;
    is_locked: boolean;
    users_count: number;
    permissions: string[];
}

interface SpecialPermission {
    key: string;
    label: string;
}

interface SectionDefinition {
    description: string;
    permissions: {
        view?: string;
        create?: string;
        update?: string;
        delete?: string;
        special?: SpecialPermission[];
    };
}

const props = defineProps<{
    roles: RoleItem[];
    sections: Record<string, SectionDefinition>;
    allPermissions: string[];
}>();

// Selected Role State
const activeRoleId = ref<number>(props.roles[0]?.id || 1);
const activeRole = computed(() => props.roles.find(r => r.id === activeRoleId.value) || props.roles[0]);

// Working Copy of Permissions for currently active role
const selectedPermissions = ref<string[]>([...(activeRole.value?.permissions || [])]);

// Switch Active Role
const selectRole = (role: RoleItem) => {
    if (hasUnsavedChanges.value) {
        if (!confirm('You have unsaved permission changes. Discard and switch role?')) {
            return;
        }
    }
    activeRoleId.value = role.id;
    selectedPermissions.value = [...role.permissions];
};

// Check if current permissions match saved permissions
const hasUnsavedChanges = computed(() => {
    if (!activeRole.value || activeRole.value.is_locked) return false;
    const original = [...activeRole.value.permissions].sort();
    const current = [...selectedPermissions.value].sort();
    if (original.length !== current.length) return true;
    return original.some((val, idx) => val !== current[idx]);
});

// Toggle Permission Helper
const togglePermission = (perm: string) => {
    if (activeRole.value?.is_locked) return;
    const idx = selectedPermissions.value.indexOf(perm);
    if (idx === -1) {
        selectedPermissions.value.push(perm);
    } else {
        selectedPermissions.value.splice(idx, 1);
    }
};

const hasPerm = (perm: string) => {
    if (activeRole.value?.is_locked) return true;
    return selectedPermissions.value.includes(perm);
};

// Toggle all in section
const toggleAllInSection = (sec: SectionDefinition) => {
    if (activeRole.value?.is_locked) return;
    const sectionPerms: string[] = [];
    if (sec.permissions.view) sectionPerms.push(sec.permissions.view);
    if (sec.permissions.create) sectionPerms.push(sec.permissions.create);
    if (sec.permissions.update) sectionPerms.push(sec.permissions.update);
    if (sec.permissions.delete) sectionPerms.push(sec.permissions.delete);
    if (sec.permissions.special) {
        sec.permissions.special.forEach(sp => sectionPerms.push(sp.key));
    }

    const allChecked = sectionPerms.every(p => selectedPermissions.value.includes(p));
    if (allChecked) {
        // Uncheck all
        selectedPermissions.value = selectedPermissions.value.filter(p => !sectionPerms.includes(p));
    } else {
        // Check all
        sectionPerms.forEach(p => {
            if (!selectedPermissions.value.includes(p)) {
                selectedPermissions.value.push(p);
            }
        });
    }
};

// Discard Changes
const discardChanges = () => {
    if (!activeRole.value) return;
    selectedPermissions.value = [...activeRole.value.permissions];
};

// Watch for server updates to props.roles and synchronize working state
watch(
    () => props.roles,
    (newRoles) => {
        const found = newRoles.find(r => r.id === activeRoleId.value);
        if (found) {
            selectedPermissions.value = [...found.permissions];
        }
    },
    { deep: true }
);

// Save Permissions Form
const isSaving = ref(false);
const savePermissions = () => {
    if (!activeRole.value || activeRole.value.is_locked) return;
    isSaving.value = true;
    router.put(`/settings/roles/${activeRole.value.id}`, {
        name: activeRole.value.name,
        description: activeRole.value.description,
        permissions: selectedPermissions.value,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const found = (page.props.roles as RoleItem[])?.find(r => r.id === activeRoleId.value);
            if (found) {
                selectedPermissions.value = [...found.permissions];
            }
            showToast(`Permissions for '${activeRole.value.name}' updated successfully.`, 'success');
        },
        onError: (errors) => {
            const firstErr = (Object.values(errors)[0] as string) || 'Failed to update role permissions.';
            showToast(firstErr, 'danger');
        },
        onFinish: () => {
            isSaving.value = false;
        },
    });
};

// Mobile Accordions State
const openAccordions = ref<Record<string, boolean>>({});
const toggleAccordion = (sectionName: string) => {
    openAccordions.value[sectionName] = !openAccordions.value[sectionName];
};

// Create Role Modal
const isCreateOpen = ref(false);
const createRoleForm = useForm({
    name: '',
    description: '',
    permissions: [] as string[],
});

const openCreateModal = () => {
    createRoleForm.reset();
    createRoleForm.clearErrors();
    createRoleForm.permissions = ['dashboard.view', 'sales.view', 'sales.create'];
    isCreateOpen.value = true;
};

const submitCreateRole = () => {
    createRoleForm.post('/settings/roles', {
        onSuccess: () => {
            isCreateOpen.value = false;
            createRoleForm.reset();
            showToast('Role created successfully.', 'success');
        },
        onError: (errors) => {
            const firstErr = (Object.values(errors)[0] as string) || 'Failed to create role. Please check validation.';
            showToast(firstErr, 'danger');
        },
    });
};

// Delete Role Confirm Dialog
const isDeleteOpen = ref(false);
const deletingRole = ref<RoleItem | null>(null);

const openDeleteConfirm = (role: RoleItem) => {
    deletingRole.value = role;
    isDeleteOpen.value = true;
};

const confirmDeleteRole = () => {
    if (!deletingRole.value) return;
    const deletedId = deletingRole.value.id;
    router.delete(`/settings/roles/${deletedId}`, {
        onSuccess: () => {
            isDeleteOpen.value = false;
            deletingRole.value = null;
            if (activeRoleId.value === deletedId) {
                activeRoleId.value = props.roles[0]?.id || 1;
                selectedPermissions.value = [...(props.roles[0]?.permissions || [])];
            }
            showToast('Role deleted successfully.', 'success');
        },
        onError: (errs) => {
            const msg = (Object.values(errs)[0] as string) || 'Cannot delete role.';
            showToast(msg, 'danger');
        },
    });
};
</script>

<template>
    <Head title="Roles & Permissions — Tasa POS" />

    <div>
        <div class="space-y-6 pb-24">
            <!-- Header -->
            <PageHeader
                title="Roles & Permissions Matrix"
                subtitle="Configure module actions, financial visibility, and administrative access per role."
            >
                <template #actions>
                    <AppButton variant="primary" icon="plus" @click="openCreateModal">
                        Create Custom Role
                    </AppButton>
                </template>
            </PageHeader>

            <!-- Role Selector Tabs Strip -->
            <div class="bg-surface border border-line-200 p-2 flex flex-wrap items-center gap-2">
                <button
                    v-for="role in roles"
                    :key="role.id"
                    type="button"
                    :class="[
                        'px-4 py-2 text-xs font-bold border transition-colors flex items-center space-x-2',
                        activeRoleId === role.id
                            ? 'bg-teal-700 text-white border-teal-700 shadow-sm'
                            : 'bg-page text-ink-700 border-line-300 hover:bg-teal-50',
                    ]"
                    @click="selectRole(role)"
                >
                    <span>{{ role.name }}</span>
                    <span
                        v-if="role.is_locked"
                        :class="[
                            'text-[9px] px-1 py-0.2 uppercase font-extrabold',
                            activeRoleId === role.id ? 'bg-teal-900 text-white' : 'bg-teal-100 text-teal-800'
                        ]"
                    >
                        Locked
                    </span>
                    <span
                        v-else
                        :class="[
                            'text-[10px] px-1.5 py-0.2 rounded-none font-mono',
                            activeRoleId === role.id ? 'bg-teal-800 text-white' : 'bg-line-200 text-ink-600'
                        ]"
                    >
                        {{ role.users_count }}
                    </span>
                </button>
            </div>

            <!-- Role Details Card & Owner Protection Banner -->
            <div class="bg-surface border border-line-200 p-4 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center space-x-2">
                            <h2 class="text-base font-bold text-ink-900">{{ activeRole.name }} Role</h2>
                            <span
                                v-if="activeRole.is_locked"
                                class="px-2 py-0.5 text-xs font-bold bg-teal-100 text-teal-800 border border-teal-300 uppercase"
                            >
                                System Locked
                            </span>
                        </div>
                        <p class="text-xs text-ink-500 mt-0.5">
                            {{ activeRole.description || 'No description provided.' }}
                        </p>
                    </div>

                    <div v-if="!activeRole.is_locked" class="flex items-center space-x-2">
                        <AppButton
                            v-if="activeRole.users_count === 0"
                            variant="danger"
                            size="sm"
                            @click="openDeleteConfirm(activeRole)"
                        >
                            Delete Role
                        </AppButton>
                    </div>
                </div>

                <!-- Owner Always Has Full Access Banner -->
                <div
                    v-if="activeRole.is_locked"
                    class="p-3 bg-teal-50 border border-teal-300 text-teal-800 text-xs flex items-center space-x-2"
                >
                    <AppIcon name="shield" :size="18" class="flex-shrink-0 text-teal-700" />
                    <div>
                        <span class="font-bold">Owner always has full access.</span>
                        This system role cannot be renamed, edited, deleted, deactivated, or deprived of permissions.
                    </div>
                </div>
            </div>

            <!-- PERMISSION MATRIX: DESKTOP TABLE (>= 768px) -->
            <div class="hidden md:block bg-surface border border-line-200 overflow-hidden">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-page border-b border-line-200 text-ink-600 uppercase font-semibold">
                            <th class="py-3 px-4 w-1/4">System Section</th>
                            <th class="py-3 px-3 text-center w-16">View</th>
                            <th class="py-3 px-3 text-center w-16">Add</th>
                            <th class="py-3 px-3 text-center w-16">Edit</th>
                            <th class="py-3 px-3 text-center w-16">Delete</th>
                            <th class="py-3 px-4">Special Capabilities</th>
                            <th class="py-3 px-3 text-right w-20">All</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-200">
                        <tr
                            v-for="(sec, secName) in sections"
                            :key="secName"
                            class="hover:bg-teal-50 transition-colors"
                        >
                            <!-- Section Name & Description -->
                            <td class="py-3 px-4">
                                <div class="font-bold text-ink-900 text-sm">{{ secName }}</div>
                                <div class="text-[11px] text-ink-500">{{ sec.description }}</div>
                            </td>

                            <!-- View -->
                            <td class="py-3 px-3 text-center">
                                <input
                                    v-if="sec.permissions.view"
                                    type="checkbox"
                                    :checked="hasPerm(sec.permissions.view)"
                                    :disabled="activeRole.is_locked"
                                    class="w-4 h-4 text-teal-700 rounded-none border-line-300 focus:ring-teal-700 disabled:opacity-75 cursor-pointer"
                                    @change="togglePermission(sec.permissions.view)"
                                />
                                <span v-else class="text-ink-300">—</span>
                            </td>

                            <!-- Add / Create -->
                            <td class="py-3 px-3 text-center">
                                <input
                                    v-if="sec.permissions.create"
                                    type="checkbox"
                                    :checked="hasPerm(sec.permissions.create)"
                                    :disabled="activeRole.is_locked"
                                    class="w-4 h-4 text-teal-700 rounded-none border-line-300 focus:ring-teal-700 disabled:opacity-75 cursor-pointer"
                                    @change="togglePermission(sec.permissions.create)"
                                />
                                <span v-else class="text-ink-300">—</span>
                            </td>

                            <!-- Edit / Update -->
                            <td class="py-3 px-3 text-center">
                                <input
                                    v-if="sec.permissions.update"
                                    type="checkbox"
                                    :checked="hasPerm(sec.permissions.update)"
                                    :disabled="activeRole.is_locked"
                                    class="w-4 h-4 text-teal-700 rounded-none border-line-300 focus:ring-teal-700 disabled:opacity-75 cursor-pointer"
                                    @change="togglePermission(sec.permissions.update)"
                                />
                                <span v-else class="text-ink-300">—</span>
                            </td>

                            <!-- Delete -->
                            <td class="py-3 px-3 text-center">
                                <input
                                    v-if="sec.permissions.delete"
                                    type="checkbox"
                                    :checked="hasPerm(sec.permissions.delete)"
                                    :disabled="activeRole.is_locked"
                                    class="w-4 h-4 text-teal-700 rounded-none border-line-300 focus:ring-teal-700 disabled:opacity-75 cursor-pointer"
                                    @change="togglePermission(sec.permissions.delete)"
                                />
                                <span v-else class="text-ink-300">—</span>
                            </td>

                            <!-- Special Permissions -->
                            <td class="py-3 px-4">
                                <div v-if="sec.permissions.special && sec.permissions.special.length > 0" class="flex flex-wrap gap-2">
                                    <label
                                        v-for="sp in sec.permissions.special"
                                        :key="sp.key"
                                        class="inline-flex items-center space-x-1.5 px-2 py-1 bg-page border border-line-200 text-[11px] cursor-pointer hover:border-teal-700 transition-colors"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="hasPerm(sp.key)"
                                            :disabled="activeRole.is_locked"
                                            class="w-3.5 h-3.5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700 disabled:opacity-75"
                                            @change="togglePermission(sp.key)"
                                        />
                                        <span class="text-ink-800">{{ sp.label }}</span>
                                    </label>
                                </div>
                                <span v-else class="text-ink-400 text-[11px]">Standard CRUD only</span>
                            </td>

                            <!-- Section Toggle All -->
                            <td class="py-3 px-3 text-right">
                                <button
                                    v-if="!activeRole.is_locked"
                                    type="button"
                                    class="text-[11px] text-teal-700 hover:text-teal-900 font-semibold underline"
                                    @click="toggleAllInSection(sec)"
                                >
                                    Toggle
                                </button>
                                <span v-else class="text-[11px] text-teal-800 font-bold">ALL</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PERMISSION MATRIX: MOBILE ACCORDIONS (< 768px) WITH LARGE TOGGLES -->
            <div class="md:hidden space-y-3">
                <div
                    v-for="(sec, secName) in sections"
                    :key="secName"
                    class="bg-surface border border-line-200 overflow-hidden"
                >
                    <!-- Accordion Header (Min 44px touch target) -->
                    <button
                        type="button"
                        class="w-full min-h-[48px] px-4 py-3 flex items-center justify-between text-left bg-page hover:bg-teal-50 border-b border-line-200 transition-colors"
                        @click="toggleAccordion(secName as string)"
                    >
                        <div>
                            <div class="font-bold text-sm text-ink-900">{{ secName }}</div>
                            <div class="text-[11px] text-ink-500">{{ sec.description }}</div>
                        </div>
                        <AppIcon
                            :name="openAccordions[secName as string] ? 'chevron-up' : 'chevron-down'"
                            :size="18"
                            class="text-ink-600 flex-shrink-0 ml-2"
                        />
                    </button>

                    <!-- Accordion Content -->
                    <div
                        v-show="openAccordions[secName as string] ?? true"
                        class="p-4 space-y-3 bg-surface"
                    >
                        <!-- Standard CRUD Toggles (Large Touch Targets >= 44px) -->
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-if="sec.permissions.view"
                                class="flex items-center min-h-[44px] px-3 bg-page border border-line-200 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="hasPerm(sec.permissions.view)"
                                    :disabled="activeRole.is_locked"
                                    class="w-5 h-5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                                    @change="togglePermission(sec.permissions.view)"
                                />
                                <span class="ml-2 text-xs font-semibold text-ink-800">View</span>
                            </label>

                            <label
                                v-if="sec.permissions.create"
                                class="flex items-center min-h-[44px] px-3 bg-page border border-line-200 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="hasPerm(sec.permissions.create)"
                                    :disabled="activeRole.is_locked"
                                    class="w-5 h-5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                                    @change="togglePermission(sec.permissions.create)"
                                />
                                <span class="ml-2 text-xs font-semibold text-ink-800">Add</span>
                            </label>

                            <label
                                v-if="sec.permissions.update"
                                class="flex items-center min-h-[44px] px-3 bg-page border border-line-200 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="hasPerm(sec.permissions.update)"
                                    :disabled="activeRole.is_locked"
                                    class="w-5 h-5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                                    @change="togglePermission(sec.permissions.update)"
                                />
                                <span class="ml-2 text-xs font-semibold text-ink-800">Edit</span>
                            </label>

                            <label
                                v-if="sec.permissions.delete"
                                class="flex items-center min-h-[44px] px-3 bg-page border border-line-200 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="hasPerm(sec.permissions.delete)"
                                    :disabled="activeRole.is_locked"
                                    class="w-5 h-5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                                    @change="togglePermission(sec.permissions.delete)"
                                />
                                <span class="ml-2 text-xs font-semibold text-ink-800">Delete</span>
                            </label>
                        </div>

                        <!-- Special Permissions (Large Toggles >= 44px) -->
                        <div v-if="sec.permissions.special && sec.permissions.special.length > 0" class="space-y-2 pt-2 border-t border-line-200">
                            <div class="text-[11px] font-bold uppercase text-ink-500">Special Rights:</div>
                            <label
                                v-for="sp in sec.permissions.special"
                                :key="sp.key"
                                class="flex items-center min-h-[44px] px-3 bg-page border border-line-200 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="hasPerm(sp.key)"
                                    :disabled="activeRole.is_locked"
                                    class="w-5 h-5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                                    @change="togglePermission(sp.key)"
                                />
                                <span class="ml-2 text-xs font-medium text-ink-900">{{ sp.label }}</span>
                            </label>
                        </div>

                        <div v-if="!activeRole.is_locked" class="pt-2 text-right">
                            <button
                                type="button"
                                class="text-xs text-teal-700 font-bold underline p-2"
                                @click="toggleAllInSection(sec)"
                            >
                                Toggle All in Section
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STICKY SAVE / DISCARD BAR (Only visible when permissions are edited and unsaved) -->
        <div
            v-if="hasUnsavedChanges"
            class="fixed bottom-0 inset-x-0 bg-surface border-t-2 border-teal-700 p-4 shadow-xl z-50 flex items-center justify-between max-w-[1440px] mx-auto"
        >
            <div class="flex items-center space-x-2 text-ink-900">
                <AppIcon name="alert" :size="20" class="text-teal-700" />
                <span class="text-xs sm:text-sm font-bold">
                    You have unsaved permission changes for <span class="text-teal-700">{{ activeRole.name }}</span>.
                </span>
            </div>

            <div class="flex items-center space-x-3">
                <AppButton variant="secondary" size="sm" @click="discardChanges">
                    Discard
                </AppButton>
                <AppButton variant="primary" size="sm" :loading="isSaving" @click="savePermissions">
                    Save Changes
                </AppButton>
            </div>
        </div>

        <!-- CREATE ROLE MODAL -->
        <div v-if="isCreateOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
            <div class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40" @click="isCreateOpen = false" />
            <div class="relative bg-surface border border-line-300 w-full max-w-lg p-6 space-y-4 z-10 shadow-lg">
                <div class="flex justify-between items-center pb-3 border-b border-line-200">
                    <h2 class="text-base font-bold text-ink-900">Create Custom Role</h2>
                    <button type="button" class="text-ink-500 hover:text-ink-900" @click="isCreateOpen = false">
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submitCreateRole">
                    <FormField label="Role Name" required :error="createRoleForm.errors.name">
                        <input
                            v-model="createRoleForm.name"
                            type="text"
                            required
                            maxlength="50"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            placeholder="e.g. Inventory Manager, Cashier"
                        />
                    </FormField>

                    <FormField label="Description" :error="createRoleForm.errors.description">
                        <input
                            v-model="createRoleForm.description"
                            type="text"
                            maxlength="255"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            placeholder="Operational duties and access scope"
                        />
                    </FormField>

                    <div class="text-xs text-ink-600 bg-page p-3 border border-line-200">
                        Default basic permissions will be assigned. You can adjust the full matrix on the screen immediately after creation.
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-line-200">
                        <AppButton variant="secondary" type="button" @click="isCreateOpen = false">
                            Cancel
                        </AppButton>
                        <AppButton variant="primary" type="submit" :loading="createRoleForm.processing">
                            Create Role
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- DELETE ROLE CONFIRM DIALOG -->
        <ConfirmDialog
            :open="isDeleteOpen"
            title="Delete Custom Role"
            :description="`Are you sure you want to delete role '${deletingRole?.name}'? This action cannot be undone.`"
            confirm-label="Delete Role"
            cancel-label="Cancel"
            :danger="true"
            @confirm="confirmDeleteRole"
            @cancel="isDeleteOpen = false"
        />
    </div>
</template>
