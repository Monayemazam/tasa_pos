<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import FormField from '@/components/tasa/FormField.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';
import { formatMoney } from '@/lib/formatters';
import { api } from '@/lib/api';

interface CourierOrderItem {
    id: number;
    merchant_order_id: string;
    sale_id: number;
    consignment_id: string | null;
    recipient_name: string;
    recipient_phone: string;
    recipient_address: string;
    recipient_city: number;
    recipient_zone: number;
    recipient_area: number;
    store_id: number;
    item_description: string | null;
    special_instruction: string | null;
    delivery_type: number;
    delivery_type_label: string;
    item_weight: number;
    item_quantity: number;
    cod_amount: number;
    courier_charge: number;
    status: string;
    raw_status: string | null;
    attempts: number;
    last_error: string | null;
    created_at: string;
    delivered_at: string | null;
    last_synced_at: string | null;
    is_settled: boolean;
    cod_settled_at: string | null;
    cod_settled_by: string | null;
    tracking_url: string | null;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedOrders {
    data: CourierOrderItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLinks[];
}

interface ConnectionStatus {
    is_configured: boolean;
    status: string;
    token_days_left: number;
    warn_expiry: boolean;
    masked_client_id: string;
}

interface City {
    city_id: number;
    city_name: string;
}

interface Store {
    store_id: number;
    store_name: string;
}

interface Zone {
    zone_id: number;
    zone_name: string;
}

interface Area {
    area_id: number;
    area_name: string;
}

const props = defineProps<{
    orders: PaginatedOrders;
    connection: ConnectionStatus;
    cities?: City[];
    stores?: Store[];
    filters: {
        search: string;
        status: string;
        settlement: string;
        date_from: string;
        date_to: string;
    };
    can: {
        retry: boolean;
        settle_cod: boolean;
    };
}>();

// ==========================================
// FILTERS
// ==========================================
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const settlement = ref(props.filters.settlement || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
const isMobileFilterOpen = ref(false);

interface CourierFilterChip {
    key: string;
    label: string;
    value: string;
}

const activeFilterChips = computed<CourierFilterChip[]>(() => {
    const chips: CourierFilterChip[] = [];
    if (search.value.trim()) {
        chips.push({ key: 'search', label: 'Search', value: search.value.trim() });
    }
    if (status.value && status.value !== 'all') {
        chips.push({ key: 'status', label: 'Status', value: status.value.replace('_', ' ').toUpperCase() });
    }
    if (settlement.value && settlement.value !== 'all') {
        chips.push({ key: 'settlement', label: 'Settlement', value: settlement.value === 'settled' ? 'Settled' : 'Unsettled' });
    }
    if (dateFrom.value || dateTo.value) {
        chips.push({ key: 'date', label: 'Date', value: `${dateFrom.value || 'Start'} to ${dateTo.value || 'Today'}` });
    }
    return chips;
});

const removeFilter = (key: string) => {
    if (key === 'search') search.value = '';
    if (key === 'status') status.value = 'all';
    if (key === 'settlement') settlement.value = 'all';
    if (key === 'date') {
        dateFrom.value = '';
        dateTo.value = '';
    }
    applyFilters();
};

const applyFilters = () => {
    router.get(
        '/sales/courier',
        {
            search: search.value || undefined,
            status: status.value !== 'all' ? status.value : undefined,
            settlement: settlement.value !== 'all' ? settlement.value : undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const resetFilters = () => {
    search.value = '';
    status.value = 'all';
    settlement.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    router.get('/sales/courier', {}, { preserveState: true, replace: true });
};

// ==========================================
// ACTIONS (RETRY, SYNC, SETTLE COD)
// ==========================================
const isRetryDialogOpen = ref(false);
const orderToRetry = ref<CourierOrderItem | null>(null);
const isRetrying = ref(false);

const handleRetry = (order: CourierOrderItem) => {
    orderToRetry.value = order;
    isRetryDialogOpen.value = true;
};

const confirmRetryOrder = () => {
    if (!orderToRetry.value) return;
    isRetrying.value = true;
    router.post(`/sales/courier/${orderToRetry.value.id}/retry`, {}, {
        preserveScroll: true,
        onFinish: () => {
            isRetrying.value = false;
            isRetryDialogOpen.value = false;
            orderToRetry.value = null;
        },
    });
};

const handleSync = (order: CourierOrderItem) => {
    router.post(`/sales/courier/${order.id}/sync`, {}, { preserveScroll: true });
};

// Settle COD Modal
const isSettleModalOpen = ref(false);
const selectedOrderToSettle = ref<CourierOrderItem | null>(null);
const settleForm = useForm({
    settled_amount: 0,
});

const openSettleModal = (order: CourierOrderItem) => {
    selectedOrderToSettle.value = order;
    settleForm.settled_amount = order.cod_amount;
    isSettleModalOpen.value = true;
};

const closeSettleModal = () => {
    isSettleModalOpen.value = false;
    selectedOrderToSettle.value = null;
};

const submitSettleCod = () => {
    if (!selectedOrderToSettle.value) return;
    settleForm.post(`/sales/courier/${selectedOrderToSettle.value.id}/settle-cod`, {
        preserveScroll: true,
        onSuccess: () => closeSettleModal(),
    });
};

// Edit Modal (PRD Safeguards & Location Resolution)
const isEditModalOpen = ref(false);
const editingOrder = ref<CourierOrderItem | null>(null);
const editZones = ref<Zone[]>([]);
const editAreas = ref<Area[]>([]);
const isLoadingEditZones = ref(false);
const isLoadingEditAreas = ref(false);

const editForm = useForm({
    recipient_name: '',
    recipient_phone: '',
    recipient_address: '',
    store_id: 0,
    recipient_city: 0,
    recipient_zone: 0,
    recipient_area: 0,
    cod_amount: 0,
    item_weight: 0.5,
    delivery_type: 48,
    special_instruction: '',
});

const loadEditZones = async (cityId: number) => {
    if (!cityId) {
        editZones.value = [];
        return;
    }
    isLoadingEditZones.value = true;
    try {
        const data = await api.get(`/sales/courier/zones?city_id=${cityId}`);
        editZones.value = Array.isArray(data) ? data : [];
    } catch {
        editZones.value = [];
    } finally {
        isLoadingEditZones.value = false;
    }
};

const loadEditAreas = async (zoneId: number) => {
    if (!zoneId) {
        editAreas.value = [];
        return;
    }
    isLoadingEditAreas.value = true;
    try {
        const data = await api.get(`/sales/courier/areas?zone_id=${zoneId}`);
        editAreas.value = Array.isArray(data) ? data : [];
    } catch {
        editAreas.value = [];
    } finally {
        isLoadingEditAreas.value = false;
    }
};

const onCityChange = async () => {
    editForm.recipient_zone = 0;
    editForm.recipient_area = 0;
    editAreas.value = [];
    if (editForm.recipient_city) {
        await loadEditZones(editForm.recipient_city);
    } else {
        editZones.value = [];
    }
};

const onZoneChange = async () => {
    editForm.recipient_area = 0;
    if (editForm.recipient_zone) {
        await loadEditAreas(editForm.recipient_zone);
    } else {
        editAreas.value = [];
    }
};

const openEditModal = async (order: CourierOrderItem) => {
    editingOrder.value = order;
    editForm.recipient_name = order.recipient_name;
    editForm.recipient_phone = order.recipient_phone;
    editForm.recipient_address = order.recipient_address;
    editForm.store_id = order.store_id || (props.stores?.[0]?.store_id ?? 0);
    editForm.recipient_city = order.recipient_city || 0;
    editForm.recipient_zone = order.recipient_zone || 0;
    editForm.recipient_area = order.recipient_area || 0;
    editForm.cod_amount = order.cod_amount;
    editForm.item_weight = order.item_weight;
    editForm.delivery_type = order.delivery_type;
    editForm.special_instruction = order.special_instruction || '';

    isEditModalOpen.value = true;

    if (order.recipient_city) {
        await loadEditZones(order.recipient_city);
    }
    if (order.recipient_zone) {
        await loadEditAreas(order.recipient_zone);
    }
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    editingOrder.value = null;
    editZones.value = [];
    editAreas.value = [];
};

const submitEditOrder = () => {
    if (!editingOrder.value) return;
    editForm.put(`/sales/courier/${editingOrder.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

// ==========================================
// STATUS HELPERS
// ==========================================
const getStatusTone = (s: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' => {
    switch (s) {
        case 'delivered':
            return 'success';
        case 'in_transit':
        case 'picked_up':
            return 'info';
        case 'created':
            return 'success';
        case 'processing':
            return 'warning';
        case 'queued':
            return 'neutral';
        case 'awaiting_retry':
            return 'warning';
        case 'failed':
            return 'danger';
        case 'returned':
        case 'cancelled':
            return 'warning';
        default:
            return 'neutral';
    }
};

const getStatusLabel = (s: string): string => {
    switch (s) {
        case 'delivered':
            return 'Delivered';
        case 'in_transit':
            return 'In Transit';
        case 'picked_up':
            return 'Picked Up';
        case 'created':
            return 'Consignment Created';
        case 'processing':
            return 'Processing...';
        case 'queued':
            return 'Pending Dispatch';
        case 'awaiting_retry':
            return 'Awaiting Retry';
        case 'failed':
            return 'Dispatch Failed';
        case 'returned':
            return 'Returned';
        case 'cancelled':
            return 'Cancelled';
        default:
            return s.replace('_', ' ').toUpperCase();
    }
};
</script>

<template>
    <Head title="Courier Orders (Pathao) — Tasa POS" />

        <div class="max-w-[1560px] mx-auto space-y-5">
            <!-- Header -->
            <PageHeader
                title="Pathao Courier Orders"
                subtitle="Complete logistics dispatch registry with automated status polling, retry backoff, and Cash on Delivery settlement"
            >
                <template #actions>
                    <Link href="/settings/pathao">
                        <AppButton variant="secondary" icon="settings">
                            Courier Settings
                        </AppButton>
                    </Link>
                    <Link href="/sales/create">
                        <AppButton variant="primary" icon="plus">
                            New Sale
                        </AppButton>
                    </Link>
                </template>
            </PageHeader>

            <!-- Gateway Health Banner -->
            <div class="bg-surface border border-line-200 p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-page border border-line-300">
                        <AppIcon name="courier" :size="20" class="text-teal-700" />
                    </div>
                    <div>
                        <div class="font-bold text-ink-900">
                            Pathao API Gateway Connection:
                            <span class="font-normal font-mono ml-1">{{ connection.masked_client_id }}</span>
                        </div>
                        <div class="text-[11px] text-ink-500">
                            Bearer OAuth Token lifetime: {{ connection.token_days_left }} days remaining.
                            <span class="text-ink-400 ml-1">(API Gateway active; individual parcel dispatch states shown per row below)</span>
                            <span v-if="connection.warn_expiry" class="text-[#8E1B12] font-bold ml-1">
                                (Warning: expires in less than 7 days)
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <StatusBadge
                        :label="connection.status === 'connected' ? 'API Connected' : (connection.warn_expiry ? 'Expiring Soon' : 'Connection Problem')"
                        :tone="connection.status === 'connected' ? 'success' : 'warning'"
                    />
                    <Link href="/settings/pathao">
                        <span class="text-xs font-bold text-teal-700 hover:underline">Configure</span>
                    </Link>
                </div>
            </div>

            <!-- Mobile Filter Toggle Bar (< sm) -->
            <div class="sm:hidden flex items-center justify-between gap-2">
                <button
                    type="button"
                    class="flex-1 h-10 px-4 bg-white border border-[#D5DCDB] text-xs font-semibold text-[#111827] flex items-center justify-between"
                    @click="isMobileFilterOpen = true"
                >
                    <span class="flex items-center gap-2">
                        <AppIcon name="filter" :size="14" class="text-[#4A5957]" />
                        <span>Filter Orders</span>
                    </span>
                    <span
                        v-if="activeFilterChips.length > 0"
                        class="px-1.5 py-0.5 bg-[#0B6E5E] text-white text-[10px] font-bold"
                    >
                        {{ activeFilterChips.length }}
                    </span>
                </button>
                <button
                    v-if="activeFilterChips.length > 0"
                    type="button"
                    class="h-10 px-3 bg-white border border-[#D5DCDB] text-xs text-[#8E1B12] font-semibold"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>

            <!-- Desktop Filters Bar (hidden on mobile, visible on sm and up) -->
            <div class="hidden sm:block bg-surface border border-line-200 p-4 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3">
                    <!-- Search -->
                    <div class="sm:col-span-2 md:col-span-2">
                        <label class="block text-[11px] font-bold uppercase text-ink-500 mb-1">
                            Search Order / Consignment
                        </label>
                        <div class="relative">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Invoice #, Consignment ID, Customer..."
                                class="w-full h-9 pl-8 pr-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                                @keyup.enter="applyFilters"
                            />
                            <AppIcon
                                name="search"
                                :size="14"
                                class="absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-400"
                            />
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-ink-500 mb-1">
                            Courier Status
                        </label>
                        <select
                            v-model="status"
                            class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                            @change="applyFilters"
                        >
                            <option value="all">All Statuses</option>
                            <option value="queued">Pending Dispatch</option>
                            <option value="processing">Processing</option>
                            <option value="created">Consignment Created</option>
                            <option value="awaiting_retry">Awaiting Retry</option>
                            <option value="picked_up">Picked Up</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="returned">Returned</option>
                            <option value="failed">Dispatch Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- COD Settlement -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-ink-500 mb-1">
                            COD Settlement
                        </label>
                        <select
                            v-model="settlement"
                            class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                            @change="applyFilters"
                        >
                            <option value="all">All COD States</option>
                            <option value="unsettled">COD To Receive (Unsettled)</option>
                            <option value="settled">Settled Collections</option>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-ink-500 mb-1">
                            From Date
                        </label>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                            @change="applyFilters"
                        />
                    </div>

                    <!-- Date To & Clear -->
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-[11px] font-bold uppercase text-ink-500 mb-1">
                                To Date
                            </label>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                                @change="applyFilters"
                            />
                        </div>
                        <button
                            type="button"
                            class="h-9 px-3 text-xs font-bold text-ink-600 bg-page border border-line-300 hover:bg-line-200"
                            title="Reset filters"
                            @click="resetFilters"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active Filter Chips Row -->
            <div
                v-if="activeFilterChips.length > 0"
                class="flex flex-wrap items-center gap-2 pt-1 text-xs"
            >
                <span class="text-[#4A5957] font-semibold">Active filters:</span>
                <span
                    v-for="chip in activeFilterChips"
                    :key="chip.key"
                    class="inline-flex items-center px-2 py-1 bg-[#E6F4F1] text-[#0B6E5E] font-medium border border-[#BCE1DA]"
                >
                    <span class="mr-1.5">{{ chip.label }}: <strong>{{ chip.value }}</strong></span>
                    <button
                        type="button"
                        class="hover:text-[#063B32] font-bold p-0.5"
                        :aria-label="`Remove filter ${chip.label}`"
                        @click="removeFilter(chip.key)"
                    >
                        <AppIcon name="close" :size="12" />
                    </button>
                </span>
                <button
                    type="button"
                    class="text-xs text-[#4A5957] hover:text-[#8E1B12] font-semibold underline ml-1"
                    @click="resetFilters"
                >
                    Clear all
                </button>
            </div>

            <!-- FULL-SCREEN MOBILE FILTER SHEET (< sm) -->
            <div
                v-if="isMobileFilterOpen"
                class="sm:hidden fixed inset-0 z-50 bg-white flex flex-col"
                role="dialog"
                aria-modal="true"
                aria-label="Filter Courier Orders"
            >
                <!-- Sticky Header -->
                <div class="h-[56px] px-4 border-b border-[#D5DCDB] flex items-center justify-between bg-white sticky top-0 z-10">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-[#111827]">Filter Courier Orders</h2>
                        <span
                            v-if="activeFilterChips.length > 0"
                            class="px-2 py-0.5 bg-[#E6F4F1] text-[#0B6E5E] text-xs font-bold"
                        >
                            {{ activeFilterChips.length }} active
                        </span>
                    </div>
                    <button
                        type="button"
                        class="p-2 text-[#4A5957] hover:bg-[#FAFBFB]"
                        aria-label="Close filter sheet"
                        @click="isMobileFilterOpen = false"
                    >
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <!-- Scrollable Body with Filter Controls -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-500 mb-1">
                            Search Order / Consignment
                        </label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Invoice #, Consignment ID, Customer..."
                            class="w-full h-11 px-3 text-sm bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                        />
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-500 mb-1">
                            Courier Status
                        </label>
                        <select
                            v-model="status"
                            class="w-full h-11 px-3 text-sm bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                        >
                            <option value="all">All Statuses</option>
                            <option value="queued">Pending Dispatch</option>
                            <option value="processing">Processing</option>
                            <option value="created">Consignment Created</option>
                            <option value="awaiting_retry">Awaiting Retry</option>
                            <option value="picked_up">Picked Up</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="returned">Returned</option>
                            <option value="failed">Dispatch Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- COD Settlement -->
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-500 mb-1">
                            COD Settlement
                        </label>
                        <select
                            v-model="settlement"
                            class="w-full h-11 px-3 text-sm bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                        >
                            <option value="all">All COD States</option>
                            <option value="unsettled">COD To Receive (Unsettled)</option>
                            <option value="settled">Settled Collections</option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-ink-500 mb-1">From Date</label>
                            <input
                                v-model="dateFrom"
                                type="date"
                                class="w-full h-11 px-2 text-sm bg-page border border-line-300 rounded-none"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-ink-500 mb-1">To Date</label>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="w-full h-11 px-2 text-sm bg-page border border-line-300 rounded-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Sticky Action Bar at Bottom -->
                <div class="p-4 border-t border-[#D5DCDB] bg-[#FAFBFB] flex items-center justify-between gap-3 sticky bottom-0 z-10">
                    <button
                        type="button"
                        class="text-xs text-[#4A5957] hover:text-[#8E1B12] underline font-semibold px-3 py-2"
                        @click="resetFilters(); isMobileFilterOpen = false;"
                    >
                        Reset all
                    </button>
                    <AppButton
                        variant="primary"
                        size="md"
                        class="px-6"
                        @click="applyFilters(); isMobileFilterOpen = false;"
                    >
                        Apply filters
                    </AppButton>
                </div>
            </div>

            <!-- Courier Orders Table -->
            <div class="bg-surface border border-line-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="sticky top-0 bg-page z-10">
                            <tr class="bg-page border-b border-line-200 text-[11px] uppercase font-bold text-ink-500">
                                <th scope="col" class="p-3">Invoice #</th>
                                <th scope="col" class="p-3">Customer / Destination</th>
                                <th scope="col" class="p-3">Consignment ID</th>
                                <th scope="col" class="p-3">Speed & Weight</th>
                                <th scope="col" class="p-3 text-center">Courier Status</th>
                                <th scope="col" class="p-3 text-right">COD Amount</th>
                                <th scope="col" class="p-3 text-right">Courier Fee</th>
                                <th scope="col" class="p-3 text-center">COD Settlement</th>
                                <th scope="col" class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-200">
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                class="hover:bg-page transition-colors"
                                :class="order.status === 'failed' ? 'bg-[#FCF6F6]' : ''"
                            >
                                <!-- Invoice # -->
                                <td class="p-3 font-mono font-bold whitespace-nowrap">
                                    <Link
                                        :href="`/sales/invoices/${order.sale_id}`"
                                        class="text-teal-700 hover:underline"
                                    >
                                        {{ order.merchant_order_id }}
                                    </Link>
                                    <div class="text-[10px] text-ink-400 font-sans mt-0.5">
                                        {{ order.created_at }}
                                    </div>
                                </td>

                                <!-- Customer / Destination -->
                                <td class="p-3">
                                    <div class="font-bold text-ink-900">{{ order.recipient_name }}</div>
                                    <div class="font-mono text-[11px] text-ink-500">{{ order.recipient_phone }}</div>
                                    <div class="text-[11px] text-ink-400 truncate max-w-[220px]" :title="order.recipient_address">
                                        {{ order.recipient_address }}
                                    </div>
                                </td>

                                <!-- Consignment ID -->
                                <td class="p-3 whitespace-nowrap">
                                    <div v-if="order.consignment_id">
                                        <span class="font-mono font-bold px-2 py-0.5 bg-[#E6F2EF] text-[#0B6E5E] border border-[#B8DCD5]">
                                            {{ order.consignment_id }}
                                        </span>
                                        <a
                                            v-if="order.tracking_url"
                                            :href="order.tracking_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="block text-[10px] text-teal-700 hover:underline mt-1"
                                        >
                                            Track in Pathao ↗
                                        </a>
                                    </div>
                                    <div v-else>
                                        <span class="text-[11px] text-ink-400 italic">Not issued</span>
                                        <div v-if="order.attempts > 0" class="text-[10px] text-ink-500 mt-0.5">
                                            Attempts: {{ order.attempts }}/3
                                        </div>
                                    </div>
                                </td>

                                <!-- Speed & Weight -->
                                <td class="p-3 whitespace-nowrap">
                                    <div class="text-ink-900">{{ order.delivery_type_label }}</div>
                                    <div class="font-mono text-[11px] text-ink-500">
                                        {{ order.item_weight }} kg (Qty: {{ order.item_quantity }})
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="p-3 text-center">
                                    <StatusBadge
                                        :label="getStatusLabel(order.status)"
                                        :tone="getStatusTone(order.status)"
                                    />
                                    <div
                                        v-if="order.status === 'failed' && order.last_error"
                                        class="text-[10px] text-[#8E1B12] mt-1 max-w-[200px] mx-auto p-1.5 bg-[#FCF0F0] border border-[#F6D0D0] rounded-none text-left font-mono break-words leading-tight"
                                        :title="order.last_error"
                                    >
                                        {{ order.last_error }}
                                    </div>
                                    <div v-else-if="order.last_synced_at" class="text-[10px] text-ink-400 mt-0.5">
                                        Synced: {{ order.last_synced_at }}
                                    </div>
                                </td>

                                <!-- COD Amount -->
                                <td class="p-3 text-right font-mono font-bold text-ink-900 whitespace-nowrap">
                                    {{ formatMoney(order.cod_amount) }}
                                    <div v-if="order.cod_amount === 0" class="text-[10px] text-ink-400 font-sans">
                                        Pre-paid
                                    </div>
                                </td>

                                <!-- Courier Charge -->
                                <td class="p-3 text-right font-mono text-ink-600 whitespace-nowrap">
                                    {{ formatMoney(order.courier_charge) }}
                                    <div class="text-[10px] text-ink-400 font-sans">
                                        Linked Expense
                                    </div>
                                </td>

                                <!-- COD Settlement -->
                                <td class="p-3 text-center whitespace-nowrap">
                                    <div v-if="order.is_settled">
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-[#E6F2EF] text-[#0A5E2E] border border-[#B8DCD5]">
                                             SETTLED
                                        </span>
                                        <div class="text-[10px] text-ink-400 mt-0.5">
                                            {{ order.cod_settled_at }}
                                        </div>
                                    </div>
                                    <div v-else-if="order.status === 'delivered' && order.cod_amount > 0">
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-[#FEF3C7] text-[#92400E] border border-[#FDE68A]">
                                            COD TO RECEIVE
                                        </span>
                                        <div v-if="can.settle_cod" class="mt-1">
                                            <button
                                                type="button"
                                                class="text-[11px] font-bold text-teal-700 hover:underline"
                                                @click="openSettleModal(order)"
                                            >
                                                Mark Settled
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else>
                                        <span class="text-[11px] text-ink-400">—</span>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="p-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1">
                                        <!-- Retry (for failed / queued / awaiting_retry) -->
                                        <button
                                            v-if="can.retry && (order.status === 'failed' || order.status === 'queued' || order.status === 'awaiting_retry')"
                                            type="button"
                                            class="p-1.5 text-[#A37A00] hover:bg-[#FEF3C7]"
                                            title="Retry Dispatch to Pathao"
                                            @click="handleRetry(order)"
                                        >
                                            <AppIcon name="refresh" :size="15" />
                                        </button>

                                        <!-- Refresh Status (Sync with Pathao) -->
                                        <button
                                            v-if="order.consignment_id"
                                            type="button"
                                            class="p-1.5 text-teal-700 hover:bg-[#E6F2EF]"
                                            title="Poll Latest Status from Pathao API"
                                            @click="handleSync(order)"
                                        >
                                            <AppIcon name="refresh" :size="15" />
                                        </button>

                                        <!-- Edit Order Details -->
                                        <button
                                            type="button"
                                            class="p-1.5 text-ink-600 hover:bg-page hover:text-ink-900"
                                            title="View / Edit Order Details"
                                            @click="openEditModal(order)"
                                        >
                                            <AppIcon name="edit" :size="15" />
                                        </button>

                                        <!-- Open in Pathao Tracking -->
                                        <a
                                            v-if="order.tracking_url"
                                            :href="order.tracking_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="p-1.5 text-ink-600 hover:bg-page hover:text-ink-900"
                                            title="Open in Pathao Merchant Portal"
                                        >
                                            <AppIcon name="external-link" :size="15" />
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="orders.data.length === 0">
                                <td colspan="9" class="p-8 text-center text-ink-400">
                                    No courier orders found matching the filter criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="orders.links && orders.links.length > 3"
                    class="p-3 border-t border-line-200 flex items-center justify-between text-xs"
                >
                    <div class="text-ink-500">
                        Showing {{ orders.data.length }} of {{ orders.total }} orders
                    </div>
                    <div class="flex items-center space-x-1">
                        <Link
                            v-for="(link, idx) in orders.links"
                            :key="idx"
                            :href="link.url || '#'"
                            class="px-2.5 py-1 border text-xs"
                            :class="link.active ? 'bg-teal-700 text-white border-teal-700' : 'bg-surface text-ink-600 border-line-300 hover:bg-page'"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <!-- Settle COD Modal (Requirement 10) -->
            <div
                v-if="isSettleModalOpen && selectedOrderToSettle"
                class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F1B1A]/40 p-4"
            >
                <div class="bg-surface border border-line-300 max-w-md w-full p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-line-200 pb-3">
                        <h3 class="text-sm font-bold text-ink-900">Mark COD Collection as Settled</h3>
                        <button
                            type="button"
                            class="text-ink-400 hover:text-ink-900"
                            @click="closeSettleModal"
                        >
                            <AppIcon name="x" :size="18" />
                        </button>
                    </div>

                    <div class="text-xs text-ink-600 space-y-2">
                        <p>
                            You are recording the Cash on Delivery remittance received from Pathao for:
                        </p>
                        <div class="p-3 bg-page border border-line-200 space-y-1">
                            <div><strong>Invoice:</strong> {{ selectedOrderToSettle.merchant_order_id }}</div>
                            <div><strong>Consignment:</strong> {{ selectedOrderToSettle.consignment_id }}</div>
                            <div><strong>Recipient:</strong> {{ selectedOrderToSettle.recipient_name }}</div>
                            <div><strong>COD Expected:</strong> {{ formatMoney(selectedOrderToSettle.cod_amount) }}</div>
                        </div>
                        <p class="text-[11px] text-ink-500">
                            Settlement records a payment row with method 'cod' against the sale. It does not alter sales revenue.
                        </p>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitSettleCod">
                        <FormField label="Settled Amount (৳)" required :error="settleForm.errors.settled_amount">
                            <input
                                v-model="settleForm.settled_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="w-full h-9 px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none font-mono"
                            />
                        </FormField>

                        <div class="flex items-center justify-end space-x-2 pt-2 border-t border-line-200">
                            <AppButton variant="secondary" type="button" @click="closeSettleModal">
                                Cancel
                            </AppButton>
                            <AppButton variant="primary" type="submit" :loading="settleForm.processing">
                                Confirm Settlement
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Details Modal (Requirement 12 PRD Safeguard) -->
            <div
                v-if="isEditModalOpen && editingOrder"
                class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F1B1A]/40 p-4"
            >
                <div class="bg-surface border border-line-300 max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-line-200 pb-3">
                        <h3 class="text-sm font-bold text-ink-900">
                            Courier Order Details: #{{ editingOrder.merchant_order_id }}
                        </h3>
                        <button
                            type="button"
                            class="text-ink-400 hover:text-ink-900"
                            @click="closeEditModal"
                        >
                            <AppIcon name="x" :size="18" />
                        </button>
                    </div>

                    <!-- Last Error Diagnostic Alert -->
                    <div
                        v-if="editingOrder.last_error"
                        class="p-3 bg-[#FCF0F0] border border-[#F6D0D0] text-[#8E1B12] text-xs space-y-1.5"
                    >
                        <div class="font-bold flex items-center gap-1.5 text-[#8E1B12]">
                            <AppIcon name="alert-triangle" :size="15" class="flex-shrink-0" />
                            <span>Pathao Dispatch Error Diagnostic:</span>
                        </div>
                        <div class="font-mono text-[11px] bg-white p-2 border border-[#F6D0D0] rounded-none break-words leading-relaxed text-[#8E1B12]">
                            {{ editingOrder.last_error }}
                        </div>
                        <div class="text-[10px] text-ink-500">
                            Please correct the store or destination city/zone/area below before retrying dispatch.
                        </div>
                    </div>

                    <!-- PRD Requirement 12 Alert Banner when Consignment already exists -->
                    <div
                        v-if="editingOrder.consignment_id && editingOrder.status !== 'failed' && editingOrder.status !== 'queued' && editingOrder.status !== 'awaiting_retry'"
                        class="p-3 bg-[#FEFCE8] border border-[#FEF08A] text-[#854D0E] text-xs flex items-start space-x-2"
                    >
                        <AppIcon name="alert-triangle" :size="18" class="text-[#A37A00] flex-shrink-0 mt-0.5" />
                        <div>
                            <strong>Consignment already generated in Pathao (#{{ editingOrder.consignment_id }}):</strong>
                            <div class="mt-0.5">
                                Recipient details, delivery address, and collection amount cannot be modified here.
                                Please update them directly in the Pathao Merchant Panel.
                            </div>
                        </div>
                    </div>

                    <form class="space-y-3 text-xs" @submit.prevent="submitEditOrder">
                        <!-- Store Selector -->
                        <FormField v-if="stores && stores.length > 0" label="Dispatching Store" :error="editForm.errors.store_id">
                            <select
                                v-model="editForm.store_id"
                                :disabled="Boolean(editingOrder.consignment_id && editingOrder.status !== 'failed' && editingOrder.status !== 'queued' && editingOrder.status !== 'awaiting_retry')"
                                class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none disabled:bg-line-200 disabled:cursor-not-allowed"
                            >
                                <option v-for="store in stores" :key="store.store_id" :value="store.store_id">
                                    {{ store.store_name }} (ID: {{ store.store_id }})
                                </option>
                            </select>
                        </FormField>

                        <FormField label="Recipient Name" :error="editForm.errors.recipient_name">
                            <input
                                v-model="editForm.recipient_name"
                                type="text"
                                :disabled="Boolean(editingOrder.consignment_id && editingOrder.status !== 'failed' && editingOrder.status !== 'queued' && editingOrder.status !== 'awaiting_retry')"
                                class="w-full h-9 px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none disabled:bg-line-200 disabled:cursor-not-allowed"
                            />
                        </FormField>

                        <div class="grid grid-cols-2 gap-3">
                            <FormField label="Recipient Phone" :error="editForm.errors.recipient_phone">
                                <input
                                    v-model="editForm.recipient_phone"
                                    type="text"
                                    :disabled="Boolean(editingOrder.consignment_id && editingOrder.status !== 'failed' && editingOrder.status !== 'queued' && editingOrder.status !== 'awaiting_retry')"
                                    class="w-full h-9 px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none disabled:bg-line-200 disabled:cursor-not-allowed font-mono"
                                />
                            </FormField>

                            <FormField label="COD Collection Amount (৳)" :error="editForm.errors.cod_amount">
                                <input
                                    v-model="editForm.cod_amount"
                                    type="number"
                                    step="0.01"
                                    :disabled="Boolean(editingOrder.consignment_id && editingOrder.status !== 'failed' && editingOrder.status !== 'queued' && editingOrder.status !== 'awaiting_retry')"
                                    class="w-full h-9 px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none disabled:bg-line-200 disabled:cursor-not-allowed font-mono"
                                />
                            </FormField>
                        </div>

                        <!-- City, Zone, Area Cascading Selectors -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <FormField label="City" :error="editForm.errors.recipient_city">
                                <select
                                    v-model="editForm.recipient_city"
                                    :disabled="Boolean(editingOrder.consignment_id && editingOrder.status !== 'failed' && editingOrder.status !== 'queued' && editingOrder.status !== 'awaiting_retry')"
                                    class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none disabled:bg-line-200 disabled:cursor-not-allowed"
                                    @change="onCityChange"
                                >
                                    <option :value="0">Select City...</option>
                                    <option v-for="c in cities" :key="c.city_id" :value="c.city_id">
                                        {{ c.city_name }}
                                    </option>
                                </select>
                            </FormField>

                            <FormField label="Zone" :error="editForm.errors.recipient_zone">
                                <select
                                    v-model="editForm.recipient_zone"
                                    :disabled="Boolean(editingOrder.consignment_id && editingOrder.status !== 'failed' && editingOrder.status !== 'queued' && editingOrder.status !== 'awaiting_retry') || isLoadingEditZones"
                                    class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none disabled:bg-line-200 disabled:cursor-not-allowed"
                                    @change="onZoneChange"
                                >
                                    <option :value="0">{{ isLoadingEditZones ? 'Loading zones...' : 'Select Zone...' }}</option>
                                    <option v-for="z in editZones" :key="z.zone_id" :value="z.zone_id">
                                        {{ z.zone_name }}
                                    </option>
                                </select>
                            </FormField>

                            <FormField label="Area" :error="editForm.errors.recipient_area">
                                <select
                                    v-model="editForm.recipient_area"
                                    :disabled="Boolean(editingOrder.consignment_id && editingOrder.status !== 'failed' && editingOrder.status !== 'queued' && editingOrder.status !== 'awaiting_retry') || isLoadingEditAreas"
                                    class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none disabled:bg-line-200 disabled:cursor-not-allowed"
                                >
                                    <option :value="0">{{ isLoadingEditAreas ? 'Loading areas...' : (editZones.length === 0 ? 'Select zone first' : 'Select Area (Optional)...') }}</option>
                                    <option v-for="a in editAreas" :key="a.area_id" :value="a.area_id">
                                        {{ a.area_name }}
                                    </option>
                                </select>
                            </FormField>
                        </div>

                        <FormField label="Recipient Address" :error="editForm.errors.recipient_address">
                            <textarea
                                v-model="editForm.recipient_address"
                                rows="2"
                                :disabled="Boolean(editingOrder.consignment_id && editingOrder.status !== 'failed' && editingOrder.status !== 'queued' && editingOrder.status !== 'awaiting_retry')"
                                class="w-full p-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none disabled:bg-line-200 disabled:cursor-not-allowed"
                            />
                        </FormField>

                        <div class="grid grid-cols-2 gap-3">
                            <FormField label="Package Weight (kg)" :error="editForm.errors.item_weight">
                                <input
                                    v-model="editForm.item_weight"
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    class="w-full h-9 px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none font-mono"
                                />
                            </FormField>

                            <FormField label="Delivery Speed" :error="editForm.errors.delivery_type">
                                <select
                                    v-model="editForm.delivery_type"
                                    class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                                >
                                    <option :value="48">Normal (48h)</option>
                                    <option :value="12">Express (12h)</option>
                                </select>
                            </FormField>
                        </div>

                        <FormField label="Special Instructions" :error="editForm.errors.special_instruction">
                            <input
                                v-model="editForm.special_instruction"
                                type="text"
                                placeholder="e.g. Call before delivery, handle with care"
                                class="w-full h-9 px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                            />
                        </FormField>

                        <div class="flex items-center justify-end space-x-2 pt-2 border-t border-line-200">
                            <AppButton variant="secondary" type="button" @click="closeEditModal">
                                Close
                            </AppButton>
                            <AppButton
                                v-if="!editingOrder.consignment_id || editingOrder.status === 'failed' || editingOrder.status === 'queued' || editingOrder.status === 'awaiting_retry'"
                                variant="primary"
                                type="submit"
                                :loading="editForm.processing"
                            >
                                Save Changes
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Retry Order Confirmation Dialog -->
            <ConfirmDialog
                :is-open="isRetryDialogOpen"
                title="Retry Courier Dispatch"
                :message="orderToRetry ? `Are you sure you want to retry dispatching order #${orderToRetry.merchant_order_id} to Pathao? The order will be queued for immediate transmission.` : 'Are you sure you want to retry courier dispatch?'"
                confirm-label="Retry order"
                cancel-label="Cancel"
                tone="neutral"
                :loading="isRetrying"
                @confirm="confirmRetryOrder"
                @cancel="isRetryDialogOpen = false"
            />
    </div>
</template>
