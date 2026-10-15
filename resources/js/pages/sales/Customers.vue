<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import SideDrawer from '@/components/tasa/SideDrawer.vue';
import FormField from '@/components/tasa/FormField.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';
import { formatMoney, formatBDPhone } from '@/lib/formatters';
import { api } from '@/lib/api';

interface CustomerRow {
    id: number;
    name: string;
    phone: string;
    alt_phone: string | null;
    address: string;
    pathao_city_id: number | null;
    pathao_zone_id: number | null;
    pathao_area_id: number | null;
    city_name: string | null;
    zone_name: string | null;
    area_name: string | null;
    notes: string | null;
    total_orders: number;
    total_spent: number;
    outstanding_due: number;
    created_at: string;
}

interface CityItem {
    city_id: number;
    city_name: string;
}

const props = defineProps<{
    customers: {
        data: CustomerRow[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    filters: {
        search?: string;
        has_due?: string;
    };
    cities: CityItem[];
}>();

const searchQuery = ref(props.filters.search || '');
const hasDueFilter = ref(props.filters.has_due === '1');

// Drawer State
const isProfileDrawerOpen = ref(false);
const activeCustomerProfile = ref<any | null>(null);
const isLoadingProfile = ref(false);

// Create / Edit Modal State
const isEditModalOpen = ref(false);
const editingCustomerId = ref<number | null>(null);
const modalForm = ref({
    name: '',
    phone: '',
    alt_phone: '',
    address: '',
    pathao_city_id: null as number | null,
    pathao_zone_id: null as number | null,
    pathao_area_id: null as number | null,
    city_name: '',
    zone_name: '',
    area_name: '',
    notes: '',
});
const modalErrors = ref<Record<string, string>>({});
const isSaving = ref(false);

// Archive Confirm Dialog
const isArchiveDialogOpen = ref(false);
const customerToArchive = ref<CustomerRow | null>(null);

// Summary metrics from current list
const totalDuesInList = props.customers.data.reduce((sum, c) => sum + c.outstanding_due, 0);
const totalSpentInList = props.customers.data.reduce((sum, c) => sum + c.total_spent, 0);

function handleSearch() {
    router.get(
        '/sales/customers',
        {
            search: searchQuery.value.trim() || undefined,
            has_due: hasDueFilter.value ? '1' : undefined,
        },
        { preserveState: true, replace: true }
    );
}

function toggleDueFilter() {
    hasDueFilter.value = !hasDueFilter.value;
    handleSearch();
}

async function viewCustomerProfile(customer: CustomerRow) {
    isLoadingProfile.value = true;
    isProfileDrawerOpen.value = true;
    activeCustomerProfile.value = null;

    try {
        const data = await api.get(`/sales/customers/${customer.id}`);
        activeCustomerProfile.value = data;
    } catch (e) {
        // Fallback to row data
        activeCustomerProfile.value = {
            ...customer,
            order_history: [],
        };
    } finally {
        isLoadingProfile.value = false;
    }
}

function openCreateModal() {
    editingCustomerId.value = null;
    modalForm.value = {
        name: '',
        phone: '',
        alt_phone: '',
        address: '',
        pathao_city_id: props.cities[0]?.city_id ?? null,
        pathao_zone_id: null,
        pathao_area_id: null,
        city_name: props.cities[0]?.city_name ?? '',
        zone_name: '',
        area_name: '',
        notes: '',
    };
    modalErrors.value = {};
    isEditModalOpen.value = true;
}

function openEditModal(customer: CustomerRow) {
    editingCustomerId.value = customer.id;
    modalForm.value = {
        name: customer.name,
        phone: customer.phone,
        alt_phone: customer.alt_phone || '',
        address: customer.address,
        pathao_city_id: customer.pathao_city_id,
        pathao_zone_id: customer.pathao_zone_id,
        pathao_area_id: customer.pathao_area_id,
        city_name: customer.city_name || '',
        zone_name: customer.zone_name || '',
        area_name: customer.area_name || '',
        notes: customer.notes || '',
    };
    modalErrors.value = {};
    isEditModalOpen.value = true;
}

function saveCustomer() {
    isSaving.value = true;
    modalErrors.value = {};

    const url = editingCustomerId.value
        ? `/sales/customers/${editingCustomerId.value}`
        : '/sales/customers';

    const method = editingCustomerId.value ? 'put' : 'post';

    router[method](url, modalForm.value, {
        onSuccess: () => {
            isEditModalOpen.value = false;
        },
        onError: (err: any) => {
            modalErrors.value = err;
        },
        onFinish: () => {
            isSaving.value = false;
        },
    });
}

function confirmArchive(customer: CustomerRow) {
    customerToArchive.value = customer;
    isArchiveDialogOpen.value = true;
}

function executeArchive(reason?: string) {
    if (!customerToArchive.value) return;

    router.delete(`/sales/customers/${customerToArchive.value.id}`, {
        data: { reason },
        onSuccess: () => {
            isArchiveDialogOpen.value = false;
            customerToArchive.value = null;
        },
    });
}
</script>

<template>
    <Head title="Customers Directory" />

    <div>
        <div class="max-w-[1560px] mx-auto space-y-5">
            <!-- Header -->
            <PageHeader
                title="Customer Directory"
                subtitle="Customer profiles with dynamic lifetime order history, calculated totals, and delivery addresses"
            >
                <template #actions>
                    <div class="flex items-center gap-2">
                        <AppButton
                            variant="secondary"
                            @click="router.visit('/sales/create')"
                        >
                            <AppIcon name="sales" :size="16" class="mr-1.5" />
                            Open POS Terminal
                        </AppButton>
                        <AppButton
                            variant="primary"
                            @click="openCreateModal"
                        >
                            <AppIcon name="plus" :size="16" class="mr-1.5" />
                            New Customer
                        </AppButton>
                    </div>
                </template>
            </PageHeader>

            <!-- Lifetime Metrics KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <StatCard
                    title="Total Registered Buyers"
                    :value="props.customers.total.toString()"
                    subtext="Unique phone accounts"
                    :is-primary="true"
                />
                <StatCard
                    title="Outstanding Customer Dues"
                    :value="formatMoney(totalDuesInList)"
                    subtext="Unpaid balances on active invoices"
                    :is-negative="totalDuesInList > 0"
                />
                <StatCard
                    title="Total Spent (Current View)"
                    :value="formatMoney(totalSpentInList)"
                    subtext="Gross buyer purchases"
                />
            </div>

            <!-- Search & Filters -->
            <div class="bg-white border border-[#D5DCDB] p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex-1 w-full sm:w-auto relative">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by name, phone (017…), or address…"
                        class="w-full h-10 px-3 pl-9 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none"
                        @keydown.enter="handleSearch"
                    />
                    <div class="absolute left-2.5 top-2.5 text-[#5F6F6D]">
                        <AppIcon name="search" :size="16" />
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button
                        type="button"
                        class="px-3 py-2 text-xs border font-medium transition-colors flex items-center gap-1.5"
                        :class="hasDueFilter ? 'bg-[#FFF4DB] border-[#B26A00] text-[#8A5A00] font-bold' : 'bg-white border-[#D5DCDB] text-[#4A5957] hover:border-[#7F8E8C]'"
                        @click="toggleDueFilter"
                    >
                        <AppIcon name="warning" :size="16" />
                        <span>Has Dues Only</span>
                    </button>

                    <AppButton
                        variant="secondary"
                        size="md"
                        @click="handleSearch"
                    >
                        Search
                    </AppButton>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="bg-white border border-[#D5DCDB] overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-[#EBEFEE] border-b border-[#D5DCDB] text-[#4A5957]">
                        <tr>
                            <th class="py-3 px-3 font-semibold">Customer Details</th>
                            <th class="py-3 px-3 font-semibold">Delivery Address & Area</th>
                            <th class="py-3 px-3 font-semibold text-center">Orders</th>
                            <th class="py-3 px-3 font-semibold text-right">Total Spent</th>
                            <th class="py-3 px-3 font-semibold text-right">Outstanding Due</th>
                            <th class="py-3 px-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#D5DCDB]">
                        <tr
                            v-for="c in props.customers.data"
                            :key="c.id"
                            class="hover:bg-[#F0F7F5] transition-colors"
                        >
                            <td class="py-3 px-3">
                                <div class="font-bold text-sm text-[#0F1B1A]">{{ c.name }}</div>
                                <div class="font-mono text-xs text-[#0B6E5E] flex items-center gap-1 mt-0.5">
                                    <AppIcon name="phone" :size="16" />
                                    <span>{{ formatBDPhone(c.phone) }}</span>
                                    <span v-if="c.alt_phone" class="text-[#5F6F6D]">· {{ formatBDPhone(c.alt_phone) }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-3 text-[#4A5957] max-w-[280px]">
                                <div class="truncate" :title="c.address">{{ c.address }}</div>
                                <div v-if="c.city_name || c.zone_name" class="text-[11px] text-[#5F6F6D] mt-0.5">
                                    {{ [c.area_name, c.zone_name, c.city_name].filter(Boolean).join(', ') }}
                                </div>
                            </td>
                            <td class="py-3 px-3 text-center tabular-nums">
                                <span class="px-2 py-0.5 font-medium bg-[#EBEFEE] text-[#0F1B1A] border border-[#D5DCDB]">
                                    {{ c.total_orders }} orders
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right font-medium tabular-nums text-[#0F1B1A]">
                                {{ formatMoney(c.total_spent) }}
                            </td>
                            <td class="py-3 px-3 text-right tabular-nums">
                                <span
                                    v-if="c.outstanding_due > 0"
                                    class="px-2 py-0.5 font-bold bg-[#FDECEA] text-[#B42318] border border-[#B42318]"
                                >
                                    {{ formatMoney(c.outstanding_due) }}
                                </span>
                                <span v-else class="text-[#14602C] font-medium">
                                    ৳ 0.00
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="px-2 py-1 bg-white hover:bg-[#E6F2EF] text-[#0B6E5E] border border-[#0B6E5E] font-medium transition-colors"
                                        @click="viewCustomerProfile(c)"
                                    >
                                        History
                                    </button>
                                    <button
                                        type="button"
                                        class="px-2 py-1 bg-white hover:bg-[#EBEFEE] text-[#4A5957] border border-[#D5DCDB] font-medium transition-colors"
                                        @click="openEditModal(c)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="px-2 py-1 bg-white hover:bg-[#FDECEA] text-[#B42318] border border-[#D5DCDB] hover:border-[#B42318] font-medium transition-colors"
                                        title="Archive"
                                        @click="confirmArchive(c)"
                                    >
                                        Archive
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.customers.data.length === 0">
                            <td colspan="6" class="py-12 text-center text-[#5F6F6D]">
                                No customer records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Customer Profile & Order History Drawer -->
        <SideDrawer
            :open="isProfileDrawerOpen"
            :title="activeCustomerProfile?.name || 'Customer Profile'"
            @close="isProfileDrawerOpen = false"
        >
            <div v-if="activeCustomerProfile" class="space-y-5 text-xs">
                <!-- Customer Snapshot Banner -->
                <div class="p-3 bg-[#EBEFEE] border border-[#D5DCDB] space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-sm font-bold text-[#0B6E5E]">
                            {{ formatBDPhone(activeCustomerProfile.phone) }}
                        </span>
                        <StatusBadge
                            v-if="activeCustomerProfile.outstanding_due > 0"
                            tone="danger"
                            label="Has Dues"
                            icon="warning"
                        />
                        <StatusBadge
                            v-else
                            tone="success"
                            label="Clear Balance"
                            icon="check-circle"
                        />
                    </div>
                    <div class="text-[#4A5957]">
                        <span class="font-bold">Address:</span> {{ activeCustomerProfile.address }}
                    </div>
                    <div v-if="activeCustomerProfile.city_name" class="text-[#5F6F6D]">
                        <span class="font-bold">Location:</span> {{ [activeCustomerProfile.area_name, activeCustomerProfile.zone_name, activeCustomerProfile.city_name].filter(Boolean).join(', ') }}
                    </div>
                    <div v-if="activeCustomerProfile.success_rate_hint" class="pt-1 border-t border-[#D5DCDB] flex items-center justify-between text-[#174B85]">
                        <span>Pathao Score:</span>
                        <span class="font-bold">{{ activeCustomerProfile.success_rate_hint.success_rate || activeCustomerProfile.success_rate_hint.label }}</span>
                    </div>
                </div>

                <!-- Lifetime Summary Stats -->
                <div class="grid grid-cols-3 gap-2">
                    <div class="p-2.5 bg-white border border-[#D5DCDB] text-center">
                        <div class="text-[10px] text-[#5F6F6D] uppercase">Orders</div>
                        <div class="text-base font-bold text-[#0F1B1A] tabular-nums">{{ activeCustomerProfile.total_orders }}</div>
                    </div>
                    <div class="p-2.5 bg-white border border-[#D5DCDB] text-center">
                        <div class="text-[10px] text-[#5F6F6D] uppercase">Total Spent</div>
                        <div class="text-base font-bold text-[#0F1B1A] tabular-nums">{{ formatMoney(activeCustomerProfile.total_spent) }}</div>
                    </div>
                    <div class="p-2.5 bg-white border border-[#D5DCDB] text-center">
                        <div class="text-[10px] text-[#5F6F6D] uppercase">Due</div>
                        <div class="text-base font-bold text-[#B42318] tabular-nums">{{ formatMoney(activeCustomerProfile.outstanding_due) }}</div>
                    </div>
                </div>

                <!-- Order History Table -->
                <div class="space-y-2">
                    <h4 class="font-bold uppercase tracking-wider text-[#4A5957] border-b border-[#D5DCDB] pb-1">
                        Order History ({{ activeCustomerProfile.order_history?.length || 0 }})
                    </h4>

                    <div v-if="activeCustomerProfile.order_history?.length > 0" class="border border-[#D5DCDB] overflow-y-auto max-h-[380px]">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-[#EBEFEE] sticky top-0 border-b border-[#D5DCDB]">
                                <tr>
                                    <th class="py-2 px-2 font-semibold">Invoice & Date</th>
                                    <th class="py-2 px-2 font-semibold">Items</th>
                                    <th class="py-2 px-2 font-semibold text-right">Total</th>
                                    <th class="py-2 px-2 font-semibold text-right">Due</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#D5DCDB]">
                                <tr
                                    v-for="order in activeCustomerProfile.order_history"
                                    :key="order.id"
                                    class="hover:bg-[#F0F7F5]"
                                >
                                    <td class="py-2 px-2">
                                        <div class="font-mono font-bold text-[#0B6E5E]">{{ order.invoice_no }}</div>
                                        <div class="text-[10px] text-[#5F6F6D]">{{ order.sale_date }}</div>
                                    </td>
                                    <td class="py-2 px-2 text-[#4A5957] max-w-[140px] truncate" :title="order.items_summary">
                                        {{ order.items_summary }}
                                    </td>
                                    <td class="py-2 px-2 text-right font-medium tabular-nums">
                                        {{ formatMoney(order.grand_total) }}
                                    </td>
                                    <td class="py-2 px-2 text-right tabular-nums">
                                        <span v-if="order.due_amount > 0" class="text-[#B42318] font-bold">
                                            {{ formatMoney(order.due_amount) }}
                                        </span>
                                        <span v-else class="text-[#14602C]">
                                            Paid
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-6 text-center text-[#5F6F6D] border border-dashed border-[#D5DCDB]">
                        No orders recorded for this customer yet.
                    </div>
                </div>
            </div>
        </SideDrawer>

        <!-- New / Edit Customer Modal -->
        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A]/40"
        >
            <div class="w-full max-w-lg bg-white border border-[#D5DCDB] p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-[#D5DCDB] pb-3">
                    <h3 class="text-sm font-bold text-[#0F1B1A]">
                        {{ editingCustomerId ? 'Edit Customer Details' : 'Register New Customer' }}
                    </h3>
                    <button
                        type="button"
                        class="text-sm font-bold text-[#5F6F6D] hover:text-black"
                        @click="isEditModalOpen = false"
                    >
                        ×
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <FormField label="Full Name" required :error="modalErrors.name">
                        <input
                            v-model="modalForm.name"
                            type="text"
                            placeholder="Full name"
                            class="w-full h-9 px-3 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none"
                        />
                    </FormField>

                    <div class="grid grid-cols-2 gap-3">
                        <FormField label="Phone (11 digits BD)" required :error="modalErrors.phone">
                            <input
                                v-model="modalForm.phone"
                                type="tel"
                                placeholder="01XXXXXXXXX"
                                maxlength="11"
                                class="w-full h-9 px-3 font-mono border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none"
                            />
                        </FormField>
                        <FormField label="Alternate Phone" :error="modalErrors.alt_phone">
                            <input
                                v-model="modalForm.alt_phone"
                                type="tel"
                                placeholder="Optional alternate"
                                maxlength="11"
                                class="w-full h-9 px-3 font-mono border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none"
                            />
                        </FormField>
                    </div>

                    <FormField label="Address (min 10 characters)" required :error="modalErrors.address">
                        <textarea
                            v-model="modalForm.address"
                            rows="2"
                            placeholder="Detailed street and house address…"
                            class="w-full p-2 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none"
                        ></textarea>
                    </FormField>

                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[#4A5957] mb-1 font-medium">City</label>
                            <input
                                v-model="modalForm.city_name"
                                type="text"
                                placeholder="e.g. Dhaka"
                                class="w-full h-8 px-2 border border-[#7F8E8C] text-xs outline-none"
                            />
                        </div>
                        <div>
                            <label class="block text-[#4A5957] mb-1 font-medium">Zone</label>
                            <input
                                v-model="modalForm.zone_name"
                                type="text"
                                placeholder="e.g. Dhanmondi"
                                class="w-full h-8 px-2 border border-[#7F8E8C] text-xs outline-none"
                            />
                        </div>
                        <div>
                            <label class="block text-[#4A5957] mb-1 font-medium">Area</label>
                            <input
                                v-model="modalForm.area_name"
                                type="text"
                                placeholder="e.g. Road 27"
                                class="w-full h-8 px-2 border border-[#7F8E8C] text-xs outline-none"
                            />
                        </div>
                    </div>

                    <FormField label="Notes">
                        <input
                            v-model="modalForm.notes"
                            type="text"
                            placeholder="Customer preferences, courier tags, etc."
                            class="w-full h-8 px-2 border border-[#7F8E8C] text-xs outline-none"
                        />
                    </FormField>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D5DCDB]">
                    <AppButton
                        variant="secondary"
                        size="sm"
                        @click="isEditModalOpen = false"
                    >
                        Cancel
                    </AppButton>
                    <AppButton
                        variant="primary"
                        size="sm"
                        :loading="isSaving"
                        @click="saveCustomer"
                    >
                        Save Customer
                    </AppButton>
                </div>
            </div>
        </div>

        <!-- Archive Confirm Dialog (Owner only) -->
        <ConfirmDialog
            :open="isArchiveDialogOpen"
            title="Archive Customer Profile"
            description="Are you sure you want to archive this customer? Past invoices and financial records will remain completely intact."
            confirm-label="Archive Customer"
            :danger="true"
            :require-reason="true"
            @confirm="executeArchive"
            @cancel="isArchiveDialogOpen = false"
        />
    </div>
</template>
