<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import TasaShell from '@/components/tasa/TasaShell.vue';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import FormField from '@/components/tasa/FormField.vue';
import PhoneInput from '@/components/tasa/PhoneInput.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';
import TablePagination from '@/components/tasa/TablePagination.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';

interface RoleOption {
    id: number;
    name: string;
    is_locked: boolean;
}

interface UserItem {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    role: string;
    is_owner: boolean;
    last_login_at: string | null;
    created_at: string;
}

interface PaginatedUsers {
    data: UserItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    users: PaginatedUsers;
    roles: RoleOption[];
    filters: {
        search?: string;
        role?: string;
        status?: string;
    };
}>();

// Search & Filters state
const searchQuery = ref(props.filters.search || '');
const roleFilter = ref(props.filters.role || '');
const statusFilter = ref(props.filters.status || '');

const handleFilter = () => {
    router.get(
        '/settings/users',
        {
            search: searchQuery.value || undefined,
            role: roleFilter.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const handleResetFilters = () => {
    searchQuery.value = '';
    roleFilter.value = '';
    statusFilter.value = '';
    router.get('/settings/users', {}, { preserveState: true, replace: true });
};

// Create User Modal State
const isCreateOpen = ref(false);
const createForm = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    role: props.roles[0]?.name || 'Staff',
});

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.role = props.roles.find(r => r.name === 'Staff')?.name || props.roles[0]?.name || 'Staff';
    isCreateOpen.value = true;
};

const submitCreate = () => {
    createForm.post('/settings/users', {
        onSuccess: () => {
            isCreateOpen.value = false;
            createForm.reset();
        },
    });
};

// Edit User Modal State
const isEditOpen = ref(false);
const editingUser = ref<UserItem | null>(null);
const editForm = useForm({
    name: '',
    email: '',
    phone: '',
    role: '',
    is_active: true,
});

const openEditModal = (user: UserItem) => {
    editingUser.value = user;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.phone = user.phone || '';
    editForm.role = user.role;
    editForm.is_active = user.is_active;
    isEditOpen.value = true;
};

const submitEdit = () => {
    if (!editingUser.value) return;
    editForm.put(`/settings/users/${editingUser.value.id}`, {
        onSuccess: () => {
            isEditOpen.value = false;
            editingUser.value = null;
        },
    });
};

// Force Password Reset Modal State
const isResetOpen = ref(false);
const resettingUser = ref<UserItem | null>(null);
const resetForm = useForm({
    password: '',
});

const openResetModal = (user: UserItem) => {
    resettingUser.value = user;
    resetForm.reset();
    resetForm.clearErrors();
    isResetOpen.value = true;
};

const submitReset = () => {
    if (!resettingUser.value) return;
    resetForm.post(`/settings/users/${resettingUser.value.id}/force-reset-password`, {
        onSuccess: () => {
            isResetOpen.value = false;
            resettingUser.value = null;
        },
    });
};

// Toggle Active State
const toggleUserActive = (user: UserItem) => {
    if (user.is_owner) return;
    router.post(`/settings/users/${user.id}/toggle-active`, {}, { preserveScroll: true });
};

// Delete User Confirm Dialog State
const isDeleteOpen = ref(false);
const deletingUser = ref<UserItem | null>(null);

const openDeleteConfirm = (user: UserItem) => {
    deletingUser.value = user;
    isDeleteOpen.value = true;
};

const confirmDelete = () => {
    if (!deletingUser.value) return;
    router.delete(`/settings/users/${deletingUser.value.id}`, {
        onSuccess: () => {
            isDeleteOpen.value = false;
            deletingUser.value = null;
        },
    });
};
</script>

<template>
    <Head title="User Accounts — Tasa POS" />

    <TasaShell>
        <div class="space-y-6">
            <!-- Header -->
            <PageHeader
                title="User Accounts"
                subtitle="Manage operators, assign single roles, and control active status."
            >
                <template #actions>
                    <AppButton variant="primary" icon="plus" @click="openCreateModal">
                        Add User
                    </AppButton>
                </template>
            </PageHeader>

            <!-- Filters Bar -->
            <div class="p-4 bg-surface border border-line-200 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
                <div class="flex flex-1 flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <div class="relative flex-1 max-w-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-500">
                            <AppIcon name="search" :size="16" />
                        </div>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search name, email, or phone..."
                            class="w-full h-[40px] pl-9 pr-3 text-xs text-ink-900 bg-page border border-line-300 focus:bg-surface focus:border-teal-700 rounded-none focus:outline-teal-700"
                            @keydown.enter="handleFilter"
                        />
                    </div>

                    <div class="w-full sm:w-44">
                        <select
                            v-model="roleFilter"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            @change="handleFilter"
                        >
                            <option value="">All Roles</option>
                            <option v-for="role in roles" :key="role.id" :value="role.name">
                                {{ role.name }}
                            </option>
                        </select>
                    </div>

                    <div class="w-full sm:w-36">
                        <select
                            v-model="statusFilter"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            @change="handleFilter"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive Only</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <AppButton variant="secondary" size="sm" @click="handleFilter">
                        Filter
                    </AppButton>
                    <AppButton
                        v-if="filters.search || filters.role || filters.status"
                        variant="ghost"
                        size="sm"
                        @click="handleResetFilters"
                    >
                        Reset
                    </AppButton>
                </div>
            </div>

            <!-- Users List: Desktop Table & Mobile Cards -->
            <div class="bg-surface border border-line-200">
                <!-- Desktop Table (>= 768px) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-page border-b border-line-200 text-ink-600 uppercase font-semibold">
                                <th class="py-3 px-4">User Details</th>
                                <th class="py-3 px-4">Phone</th>
                                <th class="py-3 px-4">Assigned Role</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Last Login</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-200">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-teal-50 transition-colors"
                            >
                                <td class="py-3 px-4">
                                    <div class="font-bold text-ink-900 text-sm flex items-center space-x-1.5">
                                        <span>{{ user.name }}</span>
                                        <span
                                            v-if="user.is_owner"
                                            class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold uppercase bg-teal-100 text-teal-800 border border-teal-300"
                                            title="Protected Owner Account"
                                        >
                                            Owner
                                        </span>
                                    </div>
                                    <div class="text-ink-500 text-xs">{{ user.email }}</div>
                                </td>
                                <td class="py-3 px-4 font-mono">
                                    {{ user.phone || '—' }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-block px-2 py-0.5 font-medium border border-line-300 bg-page text-ink-800">
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <StatusBadge
                                        :label="user.is_active ? 'Active' : 'Inactive'"
                                        :tone="user.is_active ? 'success' : 'neutral'"
                                    />
                                </td>
                                <td class="py-3 px-4 text-ink-600 font-mono">
                                    {{ user.last_login_at || 'Never' }}
                                </td>
                                <td class="py-3 px-4 text-right space-x-1">
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditModal(user)"
                                    >
                                        Edit
                                    </AppButton>
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        @click="openResetModal(user)"
                                    >
                                        Reset PW
                                    </AppButton>
                                    <AppButton
                                        v-if="!user.is_owner"
                                        :variant="user.is_active ? 'secondary' : 'primary'"
                                        size="sm"
                                        @click="toggleUserActive(user)"
                                    >
                                        {{ user.is_active ? 'Deactivate' : 'Activate' }}
                                    </AppButton>
                                    <AppButton
                                        v-if="!user.is_owner"
                                        variant="danger"
                                        size="sm"
                                        @click="openDeleteConfirm(user)"
                                    >
                                        Delete
                                    </AppButton>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="6" class="p-8 text-center">
                                    <EmptyState
                                        title="No users found"
                                        description="Try adjusting your search criteria or add a new user account."
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards (< 768px) -->
                <div class="md:hidden divide-y divide-line-200">
                    <div
                        v-for="user in users.data"
                        :key="user.id"
                        class="p-4 space-y-3"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-bold text-ink-900 text-sm flex items-center space-x-1">
                                    <span>{{ user.name }}</span>
                                    <span
                                        v-if="user.is_owner"
                                        class="text-[9px] px-1 py-0.2 bg-teal-100 text-teal-800 font-bold uppercase"
                                    >
                                        Owner
                                    </span>
                                </div>
                                <div class="text-xs text-ink-500">{{ user.email }}</div>
                                <div v-if="user.phone" class="text-xs text-ink-600 font-mono mt-0.5">{{ user.phone }}</div>
                            </div>
                            <StatusBadge
                                :label="user.is_active ? 'Active' : 'Inactive'"
                                :tone="user.is_active ? 'success' : 'neutral'"
                            />
                        </div>

                        <div class="flex items-center justify-between text-xs text-ink-600 pt-1 border-t border-line-200">
                            <div>Role: <span class="font-semibold text-ink-800">{{ user.role }}</span></div>
                            <div>Last Login: {{ user.last_login_at || 'Never' }}</div>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-2">
                            <AppButton variant="secondary" size="sm" @click="openEditModal(user)">
                                Edit
                            </AppButton>
                            <AppButton variant="ghost" size="sm" @click="openResetModal(user)">
                                Reset PW
                            </AppButton>
                            <AppButton
                                v-if="!user.is_owner"
                                :variant="user.is_active ? 'secondary' : 'primary'"
                                size="sm"
                                @click="toggleUserActive(user)"
                            >
                                {{ user.is_active ? 'Deactivate' : 'Activate' }}
                            </AppButton>
                            <AppButton
                                v-if="!user.is_owner"
                                variant="danger"
                                size="sm"
                                @click="openDeleteConfirm(user)"
                            >
                                Delete
                            </AppButton>
                        </div>
                    </div>

                    <div v-if="users.data.length === 0" class="p-6 text-center">
                        <EmptyState title="No users found" description="Adjust your filters or add a user." />
                    </div>
                </div>

                <!-- Pagination -->
                <TablePagination
                    v-if="users.total > 0"
                    :current-page="users.current_page"
                    :total-items="users.total"
                    :per-page="15"
                    @update:current-page="(p: number) => router.get('/settings/users', { ...filters, page: p }, { preserveState: true })"
                />
            </div>
        </div>

        <!-- CREATE USER MODAL -->
        <div v-if="isCreateOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
            <div class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40" @click="isCreateOpen = false" />
            <div class="relative bg-surface border border-line-300 w-full max-w-lg p-6 space-y-4 z-10 shadow-lg">
                <div class="flex justify-between items-center pb-3 border-b border-line-200">
                    <h2 class="text-base font-bold text-ink-900">Add New User</h2>
                    <button type="button" class="text-ink-500 hover:text-ink-900" @click="isCreateOpen = false">
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submitCreate">
                    <FormField label="Full Name" required :error="createForm.errors.name">
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            placeholder="e.g. Shakil Ahmed"
                        />
                    </FormField>

                    <FormField label="Email Address" required :error="createForm.errors.email">
                        <input
                            v-model="createForm.email"
                            type="email"
                            required
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            placeholder="e.g. staff@tasa.com"
                        />
                    </FormField>

                    <FormField label="Mobile Phone" :error="createForm.errors.phone">
                        <PhoneInput v-model="createForm.phone" placeholder="01XXXXXXXXX" />
                    </FormField>

                    <FormField label="Role Assignment" required :error="createForm.errors.role">
                        <select
                            v-model="createForm.role"
                            required
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                        >
                            <option v-for="role in roles" :key="role.id" :value="role.name">
                                {{ role.name }} {{ role.is_locked ? '(Owner - Locked)' : '' }}
                            </option>
                        </select>
                    </FormField>

                    <FormField label="Password" required :error="createForm.errors.password">
                        <input
                            v-model="createForm.password"
                            type="password"
                            required
                            minlength="8"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 font-mono"
                            placeholder="Minimum 8 characters"
                        />
                    </FormField>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-line-200">
                        <AppButton variant="secondary" type="button" @click="isCreateOpen = false">
                            Cancel
                        </AppButton>
                        <AppButton variant="primary" type="submit" :loading="createForm.processing">
                            Create User
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- EDIT USER MODAL -->
        <div v-if="isEditOpen && editingUser" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
            <div class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40" @click="isEditOpen = false" />
            <div class="relative bg-surface border border-line-300 w-full max-w-lg p-6 space-y-4 z-10 shadow-lg">
                <div class="flex justify-between items-center pb-3 border-b border-line-200">
                    <h2 class="text-base font-bold text-ink-900">Edit User: {{ editingUser.name }}</h2>
                    <button type="button" class="text-ink-500 hover:text-ink-900" @click="isEditOpen = false">
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submitEdit">
                    <FormField label="Full Name" required :error="editForm.errors.name">
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                        />
                    </FormField>

                    <FormField label="Email Address" required :error="editForm.errors.email">
                        <input
                            v-model="editForm.email"
                            type="email"
                            required
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                        />
                    </FormField>

                    <FormField label="Mobile Phone" :error="editForm.errors.phone">
                        <PhoneInput v-model="editForm.phone" />
                    </FormField>

                    <FormField label="Role Assignment" required :error="editForm.errors.role">
                        <select
                            v-model="editForm.role"
                            :disabled="editingUser.is_owner"
                            required
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 disabled:opacity-60"
                        >
                            <option v-for="role in roles" :key="role.id" :value="role.name">
                                {{ role.name }} {{ role.is_locked ? '(Owner - Locked)' : '' }}
                            </option>
                        </select>
                        <p v-if="editingUser.is_owner" class="text-[11px] text-teal-800 mt-1 font-semibold">
                            Owner role is locked and cannot be changed.
                        </p>
                    </FormField>

                    <div class="pt-2">
                        <label class="flex items-center space-x-2 text-xs font-semibold text-ink-900">
                            <input
                                v-model="editForm.is_active"
                                type="checkbox"
                                :disabled="editingUser.is_owner"
                                class="w-4 h-4 text-teal-700 rounded-none border-line-300 focus:ring-teal-700 disabled:opacity-50"
                            />
                            <span>Account Active</span>
                        </label>
                        <p v-if="editingUser.is_owner" class="text-[11px] text-ink-500 mt-0.5">
                            Owner cannot be deactivated.
                        </p>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-line-200">
                        <AppButton variant="secondary" type="button" @click="isEditOpen = false">
                            Cancel
                        </AppButton>
                        <AppButton variant="primary" type="submit" :loading="editForm.processing">
                            Save Changes
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- FORCE PASSWORD RESET MODAL -->
        <div v-if="isResetOpen && resettingUser" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
            <div class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40" @click="isResetOpen = false" />
            <div class="relative bg-surface border border-line-300 w-full max-w-md p-6 space-y-4 z-10 shadow-lg">
                <div class="flex justify-between items-center pb-3 border-b border-line-200">
                    <h2 class="text-base font-bold text-ink-900">Reset Password</h2>
                    <button type="button" class="text-ink-500 hover:text-ink-900" @click="isResetOpen = false">
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <p class="text-xs text-ink-600">
                    Force a new password for <span class="font-bold text-ink-900">{{ resettingUser.name }}</span> ({{ resettingUser.email }}).
                </p>

                <form class="space-y-4" @submit.prevent="submitReset">
                    <FormField label="New Password" required :error="resetForm.errors.password">
                        <input
                            v-model="resetForm.password"
                            type="password"
                            required
                            minlength="8"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 font-mono"
                            placeholder="Enter new password"
                        />
                    </FormField>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-line-200">
                        <AppButton variant="secondary" type="button" @click="isResetOpen = false">
                            Cancel
                        </AppButton>
                        <AppButton variant="primary" type="submit" :loading="resetForm.processing">
                            Confirm Reset
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- DELETE CONFIRM DIALOG -->
        <ConfirmDialog
            :open="isDeleteOpen"
            title="Delete User Account"
            :description="`Are you sure you want to delete user '${deletingUser?.name}'? Their historical sales and activity logs will be preserved.`"
            confirm-label="Delete User"
            cancel-label="Cancel"
            :danger="true"
            @confirm="confirmDelete"
            @cancel="isDeleteOpen = false"
        />
    </TasaShell>
</template>
