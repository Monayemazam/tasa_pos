<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { api } from '@/lib/api';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import FormField from '@/components/tasa/FormField.vue';
import TablePagination from '@/components/tasa/TablePagination.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import SideDrawer from '@/components/tasa/SideDrawer.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';
import { formatMoney, formatQuantity, formatDate } from '@/lib/formatters';
import { showToast } from '@/lib/toast';

interface AccessoryItemOption {
    id: number;
    name: string;
    unit: string;
    is_active: boolean;
    min_stock: number;
}

interface StockReportItem {
    id: number;
    name: string;
    unit: string;
    min_stock: number;
    is_active: boolean;
    total_purchased_qty: number;
    total_consumed_qty: number;
    total_adjusted_qty: number;
    current_stock_qty: number;
    avg_cost: number | null;
    stock_value: number | null;
    is_low_stock: boolean;
    period_purchased_qty: number;
    period_purchased_cost: number | null;
    period_consumed_qty: number;
}

interface PurchaseItem {
    id: number;
    purchase_date: string;
    raw_date: string;
    item_id: number;
    item_name: string;
    unit: string;
    supplier: string | null;
    qty: number;
    rate: number | null;
    total_cost: number | null;
    paid_amount: number;
    payment_method: string | null;
    note: string | null;
    creator: string;
}

interface ConsumptionItem {
    id: number;
    consumption_date: string;
    raw_date: string;
    item_id: number;
    item_name: string;
    unit: string;
    qty: number;
    note: string | null;
    creator: string;
}

interface MovementLedgerItem {
    id: string;
    date: string;
    display_date: string;
    type: 'purchase' | 'consumption' | 'adjustment';
    signed_qty: number;
    balance_after: number;
    note: string | null;
    creator: string;
    created_at: string;
}

const props = defineProps<{
    activeTab: 'stock' | 'purchases' | 'consumption';
    stockItems: StockReportItem[];
    purchases: any | null;
    consumptions: any | null;
    allItems: AccessoryItemOption[];
    filters: {
        tab: string;
        from?: string;
        to?: string;
        search?: string;
        low_stock?: boolean;
        accessory_item_id?: string | number;
        supplier?: string;
    };
    canViewCost: boolean;
    canManage: boolean;
    canDelete: boolean;
    canAdjust: boolean;
    stats: {
        total_items: number;
        active_items: number;
        low_stock_count: number;
        total_stock_qty: number;
        total_stock_value: number | null;
        period_purchased_qty: number;
        period_consumed_qty: number;
        period_purchased_cost: number | null;
    };
    allowNegativeStock: boolean;
}>();

// --- Filter State ---
const currentTab = ref(props.activeTab);
const searchInput = ref(props.filters.search || '');
const lowStockOnly = ref(Boolean(props.filters.low_stock));
const fromDate = ref(props.filters.from || '');
const toDate = ref(props.filters.to || '');
const selectedItemId = ref(props.filters.accessory_item_id ? String(props.filters.accessory_item_id) : '');
const supplierFilter = ref(props.filters.supplier || '');

function applyFilters(overrideTab?: string) {
    router.get(
        '/inventory/accessories',
        {
            tab: overrideTab || currentTab.value,
            search: searchInput.value || undefined,
            low_stock: lowStockOnly.value ? '1' : undefined,
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
            accessory_item_id: selectedItemId.value || undefined,
            supplier: supplierFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function switchTab(tab: 'stock' | 'purchases' | 'consumption') {
    currentTab.value = tab;
    applyFilters(tab);
}

function resetFilters() {
    searchInput.value = '';
    lowStockOnly.value = false;
    fromDate.value = '';
    toDate.value = '';
    selectedItemId.value = '';
    supplierFilter.value = '';
    applyFilters();
}

// --- Modals State ---
const showItemModal = ref(false);
const editingItem = ref<AccessoryItemOption | null>(null);

const itemForm = useForm({
    name: '',
    unit: 'pcs',
    min_stock: '0',
    is_active: true,
});

function openCreateItemModal() {
    editingItem.value = null;
    itemForm.reset();
    itemForm.clearErrors();
    itemForm.unit = 'pcs';
    itemForm.min_stock = '0';
    itemForm.is_active = true;
    showItemModal.value = true;
}

function openEditItemModal(item: StockReportItem) {
    editingItem.value = {
        id: item.id,
        name: item.name,
        unit: item.unit,
        is_active: item.is_active,
        min_stock: item.min_stock,
    };
    itemForm.clearErrors();
    itemForm.name = item.name;
    itemForm.unit = item.unit;
    itemForm.min_stock = String(item.min_stock);
    itemForm.is_active = item.is_active;
    showItemModal.value = true;
}

function submitItemForm() {
    if (editingItem.value) {
        itemForm.put(`/inventory/accessories/items/${editingItem.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showItemModal.value = false;
                showToast('Accessory item updated successfully.', 'success');
            },
        });
    } else {
        itemForm.post('/inventory/accessories/items', {
            preserveScroll: true,
            onSuccess: () => {
                showItemModal.value = false;
                showToast('Accessory item created successfully.', 'success');
            },
        });
    }
}

// --- Delete Item Dialog ---
const showDeleteConfirm = ref(false);
const itemToDelete = ref<StockReportItem | null>(null);

function confirmDeleteItem(item: StockReportItem) {
    itemToDelete.value = item;
    showDeleteConfirm.value = true;
}

function deleteItem() {
    if (!itemToDelete.value) return;
    router.delete(`/inventory/accessories/items/${itemToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            itemToDelete.value = null;
            showToast('Accessory item archived successfully.', 'success');
        },
    });
}

// --- Purchase Modal ---
const showPurchaseModal = ref(false);
const purchaseForm = useForm({
    accessory_item_id: '',
    purchase_date: new Date().toISOString().slice(0, 10),
    supplier: '',
    qty: '',
    rate: '',
    paid_amount: '0',
    payment_method: 'cash',
    note: '',
});

const calculatedPurchaseTotal = computed(() => {
    const q = parseFloat(purchaseForm.qty) || 0;
    const r = parseFloat(purchaseForm.rate) || 0;
    return Math.round(q * r * 100) / 100;
});

function openPurchaseModal(presetItemId?: number) {
    purchaseForm.reset();
    purchaseForm.clearErrors();
    purchaseForm.purchase_date = new Date().toISOString().slice(0, 10);
    purchaseForm.payment_method = 'cash';
    purchaseForm.paid_amount = '0';
    if (presetItemId) {
        purchaseForm.accessory_item_id = String(presetItemId);
    } else if (props.allItems.length > 0) {
        purchaseForm.accessory_item_id = String(props.allItems[0].id);
    }
    showPurchaseModal.value = true;
}

function submitPurchaseForm() {
    purchaseForm.post('/inventory/accessories/purchases', {
        preserveScroll: true,
        onSuccess: () => {
            showPurchaseModal.value = false;
            showToast('Accessory purchase recorded.', 'success');
        },
    });
}

// --- Consumption Modal ---
const showConsumptionModal = ref(false);
const consumptionForm = useForm({
    accessory_item_id: '',
    consumption_date: new Date().toISOString().slice(0, 10),
    qty: '',
    note: '',
});

function openConsumptionModal(presetItemId?: number) {
    consumptionForm.reset();
    consumptionForm.clearErrors();
    consumptionForm.consumption_date = new Date().toISOString().slice(0, 10);
    if (presetItemId) {
        consumptionForm.accessory_item_id = String(presetItemId);
    } else if (props.allItems.length > 0) {
        consumptionForm.accessory_item_id = String(props.allItems[0].id);
    }
    showConsumptionModal.value = true;
}

function submitConsumptionForm() {
    consumptionForm.post('/inventory/accessories/consumptions', {
        preserveScroll: true,
        onSuccess: () => {
            showConsumptionModal.value = false;
            showToast('Accessory consumption logged.', 'success');
        },
    });
}

// --- Adjustment Modal ---
const showAdjustModal = ref(false);
const adjustForm = useForm({
    accessory_item_id: '',
    qty: '',
    adjusted_on: new Date().toISOString().slice(0, 10),
    reason: 'damaged',
    note: '',
});

function openAdjustModal(presetItemId?: number) {
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.adjusted_on = new Date().toISOString().slice(0, 10);
    adjustForm.reason = 'damaged';
    if (presetItemId) {
        adjustForm.accessory_item_id = String(presetItemId);
    } else if (props.allItems.length > 0) {
        adjustForm.accessory_item_id = String(props.allItems[0].id);
    }
    showAdjustModal.value = true;
}

function submitAdjustForm() {
    adjustForm.post('/inventory/accessories/adjust-stock', {
        preserveScroll: true,
        onSuccess: () => {
            showAdjustModal.value = false;
            showToast('Stock adjustment recorded.', 'success');
        },
    });
}

// --- Ledger Movements Drawer ---
const showMovementsDrawer = ref(false);
const activeDrawerItem = ref<StockReportItem | null>(null);
const movementsLoading = ref(false);
const movementLedger = ref<MovementLedgerItem[]>([]);

async function openMovementsDrawer(item: StockReportItem) {
    activeDrawerItem.value = item;
    showMovementsDrawer.value = true;
    movementsLoading.value = true;
    movementLedger.value = [];

    try {
        const res = await api.get<{ item: any; movements: MovementLedgerItem[] }>(
            `/inventory/accessories/items/${item.id}/movements`
        );
        movementLedger.value = res.movements || [];
    } catch {
        showToast('Failed to load stock movement ledger.', 'danger');
    } finally {
        movementsLoading.value = false;
    }
}
</script>

<template>
    <Head title="Accessories Inventory (Raw Materials) — Tasa POS" />

        <div class="space-y-6">
            <!-- Header -->
            <PageHeader
                title="Accessories Stock"
                subtitle="Track variable-unit raw materials (buckles, zippers, thread, studs, rivets, lining)"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <AppButton
                            v-if="canManage"
                            tone="primary"
                            icon="plus"
                            @click="openCreateItemModal"
                        >
                            New Item
                        </AppButton>
                        <AppButton
                            v-if="canManage"
                            tone="secondary"
                            icon="cart"
                            @click="openPurchaseModal()"
                        >
                            Log Purchase
                        </AppButton>
                        <AppButton
                            v-if="canManage"
                            tone="secondary"
                            icon="close"
                            @click="openConsumptionModal()"
                        >
                            Log Consumption
                        </AppButton>
                        <AppButton
                            v-if="canAdjust"
                            tone="secondary"
                            icon="adjustments"
                            @click="openAdjustModal()"
                        >
                            Adjust Stock
                        </AppButton>
                    </div>
                </template>
            </PageHeader>

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <StatCard
                    title="Active Items"
                    :value="String(stats.active_items)"
                    :hint="`Total: ${stats.total_items} items`"
                    icon="box"
                />
                <StatCard
                    title="In-Stock Units"
                    :value="formatQuantity(stats.total_stock_qty)"
                    hint="Sum across all units"
                    icon="leather"
                />
                <StatCard
                    title="Low Stock Alerts"
                    :value="String(stats.low_stock_count)"
                    :hint="stats.low_stock_count > 0 ? 'Below minimum threshold' : 'All items optimal'"
                    :tone="stats.low_stock_count > 0 ? 'warning' : 'default'"
                    icon="warning"
                />
                <StatCard
                    title="Total Stock Value"
                    :value="canViewCost && stats.total_stock_value !== null ? formatMoney(stats.total_stock_value) : '••••••'"
                    :hint="canViewCost ? 'Current valuation' : 'Restricted to Owner/Admin'"
                    icon="receipt"
                />
            </div>

            <!-- Tab Navigation Bar -->
            <div class="border-b border-[#E5E7EB] bg-white px-4 flex items-center justify-between">
                <div class="flex gap-1 -mb-px">
                    <button
                        type="button"
                        class="px-4 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors cursor-pointer"
                        :class="[
                            currentTab === 'stock'
                                ? 'border-[#0B6E5E] text-[#0B6E5E]'
                                : 'border-transparent text-[#6B7280] hover:text-[#111827] hover:border-[#D1D5DB]'
                        ]"
                        @click="switchTab('stock')"
                    >
                        <AppIcon name="inventory" :size="16" />
                        Stock Catalogue
                        <span class="text-xs bg-[#F3F4F6] text-[#4B5563] px-2 py-0.5 font-mono">
                            {{ stockItems.length }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="px-4 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors cursor-pointer"
                        :class="[
                            currentTab === 'purchases'
                                ? 'border-[#0B6E5E] text-[#0B6E5E]'
                                : 'border-transparent text-[#6B7280] hover:text-[#111827] hover:border-[#D1D5DB]'
                        ]"
                        @click="switchTab('purchases')"
                    >
                        <AppIcon name="cart" :size="16" />
                        Purchases History
                    </button>
                    <button
                        type="button"
                        class="px-4 py-3 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors cursor-pointer"
                        :class="[
                            currentTab === 'consumption'
                                ? 'border-[#0B6E5E] text-[#0B6E5E]'
                                : 'border-transparent text-[#6B7280] hover:text-[#111827] hover:border-[#D1D5DB]'
                        ]"
                        @click="switchTab('consumption')"
                    >
                        <AppIcon name="close" :size="16" />
                        Consumption Log
                    </button>
                </div>

                <!-- Low Stock Quick Filter (Stock Tab) -->
                <div v-if="currentTab === 'stock'" class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-[#4B5563] cursor-pointer">
                        <input
                            v-model="lowStockOnly"
                            type="checkbox"
                            class="checkbox-square border-[#D1D5DB] text-[#0B6E5E] focus:ring-[#0B6E5E]"
                            @change="applyFilters()"
                        />
                        <span class="flex items-center gap-1">
                            <AppIcon name="warning" :size="14" class="text-[#D97706]" />
                            Low stock only ({{ stats.low_stock_count }})
                        </span>
                    </label>
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <div class="bg-white border border-[#E5E7EB] p-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <AppIcon
                            name="search"
                            :size="16"
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9CA3AF]"
                        />
                        <input
                            v-model="searchInput"
                            type="text"
                            placeholder="Search by accessory name or unit..."
                            class="w-full pl-9 pr-3 py-1.5 text-sm bg-white border border-[#D1D5DB] focus:border-[#0B6E5E] focus:outline-none"
                            @keyup.enter="applyFilters()"
                        />
                    </div>
                </div>

                <!-- Specific Item Filter -->
                <div class="w-48">
                    <select
                        v-model="selectedItemId"
                        class="w-full py-1.5 px-2 text-sm bg-white border border-[#D1D5DB] focus:border-[#0B6E5E] focus:outline-none"
                        @change="applyFilters()"
                    >
                        <option value="">All Accessory Items</option>
                        <option v-for="item in allItems" :key="item.id" :value="String(item.id)">
                            {{ item.name }} ({{ item.unit }})
                        </option>
                    </select>
                </div>

                <!-- Date Range for Period Scoping -->
                <div class="flex items-center gap-1.5 text-xs text-[#6B7280]">
                    <span>From:</span>
                    <input
                        v-model="fromDate"
                        type="date"
                        class="py-1 px-2 text-xs border border-[#D1D5DB] bg-white focus:border-[#0B6E5E] focus:outline-none"
                        @change="applyFilters()"
                    />
                    <span>To:</span>
                    <input
                        v-model="toDate"
                        type="date"
                        class="py-1 px-2 text-xs border border-[#D1D5DB] bg-white focus:border-[#0B6E5E] focus:outline-none"
                        @change="applyFilters()"
                    />
                </div>

                <AppButton tone="secondary" size="sm" @click="applyFilters()">
                    Filter
                </AppButton>
                <AppButton tone="ghost" size="sm" @click="resetFilters">
                    Reset
                </AppButton>
            </div>

            <!-- TAB 1: STOCK CATALOGUE -->
            <div v-if="currentTab === 'stock'" class="bg-white border border-[#E5E7EB] overflow-hidden">
                <div v-if="stockItems.length === 0" class="p-8">
                    <EmptyState
                        title="No accessory items found"
                        description="Try clearing your search filters or add a new accessory item catalogue entry."
                        icon="box"
                    >
                        <template #action>
                            <AppButton
                                v-if="canManage"
                                tone="primary"
                                icon="plus"
                                @click="openCreateItemModal"
                            >
                                Add First Accessory
                            </AppButton>
                        </template>
                    </EmptyState>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-[#F9FAFB] border-b border-[#E5E7EB] text-xs font-semibold text-[#4B5563] uppercase tracking-wider">
                                <th class="py-3 px-4">Item Name</th>
                                <th class="py-3 px-3">Unit</th>
                                <th class="py-3 px-3 text-right">Min Stock</th>
                                <th class="py-3 px-3 text-right">Purchased</th>
                                <th class="py-3 px-3 text-right">Consumed</th>
                                <th class="py-3 px-3 text-right">Adjusted</th>
                                <th class="py-3 px-3 text-right font-bold text-[#111827]">Current Stock</th>
                                <th v-if="canViewCost" class="py-3 px-3 text-right">Avg Cost / Unit</th>
                                <th v-if="canViewCost" class="py-3 px-3 text-right">Stock Valuation</th>
                                <th class="py-3 px-3 text-center">Stock Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr
                                v-for="item in stockItems"
                                :key="item.id"
                                class="hover:bg-[#F9FAFB] transition-colors"
                                :class="{'bg-[#FFFBEB]/40': item.is_low_stock}"
                            >
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-[#111827] flex items-center gap-2">
                                        {{ item.name }}
                                        <span v-if="!item.is_active" class="text-[10px] bg-[#F3F4F6] text-[#6B7280] px-1.5 py-0.5 font-mono">
                                            Archived
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 font-mono text-xs text-[#4B5563]">
                                    <span class="px-2 py-0.5 bg-[#F3F4F6] border border-[#E5E7EB]">
                                        {{ item.unit }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums text-[#6B7280]">
                                    {{ formatQuantity(item.min_stock) }} {{ item.unit }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums text-[#374151]">
                                    {{ formatQuantity(item.total_purchased_qty) }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums text-[#374151]">
                                    {{ formatQuantity(item.total_consumed_qty) }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums">
                                    <span :class="item.total_adjusted_qty >= 0 ? 'text-[#059669]' : 'text-[#DC2626]'">
                                        {{ item.total_adjusted_qty > 0 ? '+' : '' }}{{ formatQuantity(item.total_adjusted_qty) }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums font-bold">
                                    <span
                                        :class="[
                                            item.current_stock_qty < 0
                                                ? 'text-[#DC2626]'
                                                : item.is_low_stock
                                                ? 'text-[#D97706]'
                                                : 'text-[#0B6E5E]'
                                        ]"
                                    >
                                        {{ formatQuantity(item.current_stock_qty) }} {{ item.unit }}
                                    </span>
                                </td>
                                <td v-if="canViewCost" class="py-3 px-3 text-right tabular-nums text-[#374151]">
                                    {{ item.avg_cost !== null ? formatMoney(item.avg_cost) : '—' }}
                                </td>
                                <td v-if="canViewCost" class="py-3 px-3 text-right tabular-nums font-medium text-[#111827]">
                                    {{ item.stock_value !== null ? formatMoney(item.stock_value) : '—' }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span
                                        v-if="item.current_stock_qty < 0"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold bg-[#FEE2E2] text-[#B91C1C] border border-[#FCA5A5]"
                                    >
                                        <AppIcon name="danger" :size="12" />
                                        Negative
                                    </span>
                                    <span
                                        v-else-if="item.is_low_stock"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold bg-[#FEF3C7] text-[#B45309] border border-[#FCD34D]"
                                    >
                                        <AppIcon name="warning" :size="12" />
                                        Low Stock
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold bg-[#D1FAE5] text-[#065F46] border border-[#6EE7B7]"
                                    >
                                        <AppIcon name="check-circle" :size="12" />
                                        In Stock
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            title="View Ledger Movements"
                                            class="p-1 text-[#6B7280] hover:text-[#0B6E5E] transition-colors"
                                            @click="openMovementsDrawer(item)"
                                        >
                                            <AppIcon name="invoices" :size="16" />
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            title="Edit Item"
                                            class="p-1 text-[#6B7280] hover:text-[#0B6E5E] transition-colors"
                                            @click="openEditItemModal(item)"
                                        >
                                            <AppIcon name="edit" :size="16" />
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            title="Quick Purchase"
                                            class="p-1 text-[#6B7280] hover:text-[#0B6E5E] transition-colors"
                                            @click="openPurchaseModal(item.id)"
                                        >
                                            <AppIcon name="cart" :size="16" />
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            title="Log Consumption"
                                            class="p-1 text-[#6B7280] hover:text-[#0B6E5E] transition-colors"
                                            @click="openConsumptionModal(item.id)"
                                        >
                                            <AppIcon name="close" :size="16" />
                                        </button>
                                        <button
                                            v-if="canAdjust"
                                            type="button"
                                            title="Stock Adjustment"
                                            class="p-1 text-[#6B7280] hover:text-[#0B6E5E] transition-colors"
                                            @click="openAdjustModal(item.id)"
                                        >
                                            <AppIcon name="adjustments" :size="16" />
                                        </button>
                                        <button
                                            v-if="canDelete"
                                            type="button"
                                            title="Archive Item"
                                            class="p-1 text-[#6B7280] hover:text-[#DC2626] transition-colors"
                                            @click="confirmDeleteItem(item)"
                                        >
                                            <AppIcon name="trash" :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: PURCHASES LOG -->
            <div v-if="currentTab === 'purchases'" class="bg-white border border-[#E5E7EB] overflow-hidden">
                <div v-if="!purchases || purchases.data.length === 0" class="p-8">
                    <EmptyState
                        title="No accessory purchases recorded"
                        description="Click 'Log Purchase' above to register raw accessory purchases from suppliers."
                        icon="cart"
                    >
                        <template #action>
                            <AppButton
                                v-if="canManage"
                                tone="primary"
                                icon="plus"
                                @click="openPurchaseModal()"
                            >
                                Log Accessory Purchase
                            </AppButton>
                        </template>
                    </EmptyState>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-[#F9FAFB] border-b border-[#E5E7EB] text-xs font-semibold text-[#4B5563] uppercase tracking-wider">
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Accessory Item</th>
                                <th class="py-3 px-3">Supplier</th>
                                <th class="py-3 px-3 text-right">Quantity</th>
                                <th v-if="canViewCost" class="py-3 px-3 text-right">Rate / Unit</th>
                                <th v-if="canViewCost" class="py-3 px-3 text-right">Total Cost</th>
                                <th class="py-3 px-3 text-right">Paid Amount</th>
                                <th class="py-3 px-3 text-center">Payment Method</th>
                                <th class="py-3 px-4">Note / Creator</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr v-for="p in purchases.data" :key="p.id" class="hover:bg-[#F9FAFB] transition-colors">
                                <td class="py-3 px-4 whitespace-nowrap text-xs font-medium text-[#111827]">
                                    {{ p.purchase_date }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-[#111827]">
                                    {{ p.item_name }}
                                </td>
                                <td class="py-3 px-3 text-xs text-[#4B5563]">
                                    {{ p.supplier || '—' }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums font-bold text-[#0B6E5E]">
                                    {{ formatQuantity(p.qty) }} {{ p.unit }}
                                </td>
                                <td v-if="canViewCost" class="py-3 px-3 text-right tabular-nums text-[#374151]">
                                    {{ p.rate !== null ? formatMoney(p.rate) : '—' }}
                                </td>
                                <td v-if="canViewCost" class="py-3 px-3 text-right tabular-nums font-semibold text-[#111827]">
                                    {{ p.total_cost !== null ? formatMoney(p.total_cost) : '—' }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums font-medium text-[#059669]">
                                    {{ formatMoney(p.paid_amount) }}
                                </td>
                                <td class="py-3 px-3 text-center uppercase text-xs font-mono">
                                    <span class="px-2 py-0.5 bg-[#F3F4F6] text-[#374151] border border-[#E5E7EB]">
                                        {{ p.payment_method || 'CASH' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs text-[#6B7280]">
                                    <div>{{ p.note || '—' }}</div>
                                    <div class="text-[10px] text-[#9CA3AF]">By: {{ p.creator }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-4 border-t border-[#E5E7EB]">
                        <TablePagination
                            :current-page="purchases.current_page"
                            :total-items="purchases.total"
                            :per-page="purchases.per_page"
                            @update:current-page="(page) => router.get('/inventory/accessories', { tab: 'purchases', page }, { preserveState: true })"
                        />
                    </div>
                </div>
            </div>

            <!-- TAB 3: CONSUMPTION LOG -->
            <div v-if="currentTab === 'consumption'" class="bg-white border border-[#E5E7EB] overflow-hidden">
                <div v-if="!consumptions || consumptions.data.length === 0" class="p-8">
                    <EmptyState
                        title="No accessory consumption logged"
                        description="Log workshop usage and consumption manually to keep real-time inventory balances."
                        icon="close"
                    >
                        <template #action>
                            <AppButton
                                v-if="canManage"
                                tone="primary"
                                icon="plus"
                                @click="openConsumptionModal()"
                            >
                                Log Usage / Consumption
                            </AppButton>
                        </template>
                    </EmptyState>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-[#F9FAFB] border-b border-[#E5E7EB] text-xs font-semibold text-[#4B5563] uppercase tracking-wider">
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Accessory Item</th>
                                <th class="py-3 px-3 text-right">Quantity Consumed</th>
                                <th class="py-3 px-4">Purpose / Note</th>
                                <th class="py-3 px-4 text-right">Recorded By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr v-for="c in consumptions.data" :key="c.id" class="hover:bg-[#F9FAFB] transition-colors">
                                <td class="py-3 px-4 whitespace-nowrap text-xs font-medium text-[#111827]">
                                    {{ c.consumption_date }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-[#111827]">
                                    {{ c.item_name }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums font-bold text-[#DC2626]">
                                    -{{ formatQuantity(c.qty) }} {{ c.unit }}
                                </td>
                                <td class="py-3 px-4 text-xs text-[#374151]">
                                    {{ c.note || 'Workshop production consumption' }}
                                </td>
                                <td class="py-3 px-4 text-right text-xs text-[#6B7280]">
                                    {{ c.creator }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-4 border-t border-[#E5E7EB]">
                        <TablePagination
                            :current-page="consumptions.current_page"
                            :total-items="consumptions.total"
                            :per-page="consumptions.per_page"
                            @update:current-page="(page) => router.get('/inventory/accessories', { tab: 'consumption', page }, { preserveState: true })"
                        />
                    </div>
                </div>
            </div>

            <!-- MODAL: ADD / EDIT ACCESSORY ITEM -->
            <div
                v-if="showItemModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-none p-4"
            >
                <div class="bg-white border border-[#E5E7EB] shadow-lg w-full max-w-md p-6 space-y-4">
                    <h2 class="text-lg font-bold text-[#111827]">
                        {{ editingItem ? 'Edit Accessory Item' : 'New Accessory Item' }}
                    </h2>

                    <form class="space-y-4" @submit.prevent="submitItemForm">
                        <FormField label="Item Name" :error="itemForm.errors.name" required>
                            <input
                                v-model="itemForm.name"
                                type="text"
                                placeholder="e.g. YKK Brass Zipper #5, Antique Rivets 9mm"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <FormField label="Unit of Measure" :error="itemForm.errors.unit" required>
                            <input
                                v-model="itemForm.unit"
                                type="text"
                                placeholder="e.g. pcs, set, meter, kg, roll"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <FormField label="Minimum Stock Alert Level" :error="itemForm.errors.min_stock">
                            <input
                                v-model="itemForm.min_stock"
                                type="number"
                                step="0.001"
                                min="0"
                                placeholder="0.000"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <div class="flex items-center gap-2 pt-2">
                            <input
                                id="is_active"
                                v-model="itemForm.is_active"
                                type="checkbox"
                                class="checkbox-square border-[#D1D5DB] text-[#0B6E5E] focus:ring-[#0B6E5E]"
                            />
                            <label for="is_active" class="text-sm text-[#374151] cursor-pointer">
                                Active item (visible in workshop stock lists)
                            </label>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-[#E5E7EB]">
                            <AppButton tone="ghost" type="button" @click="showItemModal = false">
                                Cancel
                            </AppButton>
                            <AppButton tone="primary" type="submit" :loading="itemForm.processing">
                                {{ editingItem ? 'Save Changes' : 'Create Item' }}
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: LOG PURCHASE -->
            <div
                v-if="showPurchaseModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-none p-4"
            >
                <div class="bg-white border border-[#E5E7EB] shadow-lg w-full max-w-lg p-6 space-y-4">
                    <h2 class="text-lg font-bold text-[#111827]">Log Accessory Purchase</h2>
                    <p class="text-xs text-[#6B7280]">
                        Record material acquisition from supplier. Note: Does not create an operating expense (PRD BR-5).
                    </p>

                    <form class="space-y-4" @submit.prevent="submitPurchaseForm">
                        <div class="grid grid-cols-2 gap-3">
                            <FormField label="Purchase Date" :error="purchaseForm.errors.purchase_date" required>
                                <input
                                    v-model="purchaseForm.purchase_date"
                                    type="date"
                                    class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                                />
                            </FormField>
                            <FormField label="Supplier (Optional)" :error="purchaseForm.errors.supplier">
                                <input
                                    v-model="purchaseForm.supplier"
                                    type="text"
                                    placeholder="Supplier name"
                                    class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                                />
                            </FormField>
                        </div>

                        <FormField label="Accessory Item" :error="purchaseForm.errors.accessory_item_id" required>
                            <select
                                v-model="purchaseForm.accessory_item_id"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            >
                                <option v-for="item in allItems" :key="item.id" :value="String(item.id)">
                                    {{ item.name }} ({{ item.unit }})
                                </option>
                            </select>
                        </FormField>

                        <div class="grid grid-cols-2 gap-3">
                            <FormField label="Quantity" :error="purchaseForm.errors.qty" required>
                                <input
                                    v-model="purchaseForm.qty"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    placeholder="Quantity"
                                    class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                                />
                            </FormField>
                            <FormField label="Rate per Unit (৳)" :error="purchaseForm.errors.rate" required>
                                <input
                                    v-model="purchaseForm.rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Rate"
                                    class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                                />
                            </FormField>
                        </div>

                        <!-- Computed Total Cost Banner -->
                        <div class="bg-[#F9FAFB] border border-[#E5E7EB] p-3 flex justify-between items-center text-sm">
                            <span class="text-[#6B7280]">Computed Total Cost:</span>
                            <span class="font-bold text-[#111827] text-base tabular-nums">
                                {{ formatMoney(calculatedPurchaseTotal) }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <FormField label="Amount Paid (৳)" :error="purchaseForm.errors.paid_amount">
                                <input
                                    v-model="purchaseForm.paid_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Paid amount"
                                    class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                                />
                            </FormField>
                            <FormField label="Payment Method" :error="purchaseForm.errors.payment_method">
                                <select
                                    v-model="purchaseForm.payment_method"
                                    class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                                >
                                    <option value="cash">Cash</option>
                                    <option value="bkash">bKash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </FormField>
                        </div>

                        <FormField label="Note" :error="purchaseForm.errors.note">
                            <input
                                v-model="purchaseForm.note"
                                type="text"
                                placeholder="Batch #, invoice note, remarks"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <div class="flex justify-end gap-2 pt-4 border-t border-[#E5E7EB]">
                            <AppButton tone="ghost" type="button" @click="showPurchaseModal = false">
                                Cancel
                            </AppButton>
                            <AppButton tone="primary" type="submit" :loading="purchaseForm.processing">
                                Save Purchase
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: LOG CONSUMPTION -->
            <div
                v-if="showConsumptionModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-none p-4"
            >
                <div class="bg-white border border-[#E5E7EB] shadow-lg w-full max-w-md p-6 space-y-4">
                    <h2 class="text-lg font-bold text-[#111827]">Log Accessory Consumption</h2>
                    <p class="text-xs text-[#6B7280]">
                        Record workshop usage. No automatic BOM deduction is applied (manual tracking only).
                    </p>

                    <form class="space-y-4" @submit.prevent="submitConsumptionForm">
                        <FormField label="Consumption Date" :error="consumptionForm.errors.consumption_date" required>
                            <input
                                v-model="consumptionForm.consumption_date"
                                type="date"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <FormField label="Accessory Item" :error="consumptionForm.errors.accessory_item_id" required>
                            <select
                                v-model="consumptionForm.accessory_item_id"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            >
                                <option v-for="item in allItems" :key="item.id" :value="String(item.id)">
                                    {{ item.name }} ({{ item.unit }})
                                </option>
                            </select>
                        </FormField>

                        <FormField label="Quantity Consumed" :error="consumptionForm.errors.qty" required>
                            <input
                                v-model="consumptionForm.qty"
                                type="number"
                                step="0.001"
                                min="0.001"
                                placeholder="Quantity consumed"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <FormField label="Note / Purpose" :error="consumptionForm.errors.note">
                            <input
                                v-model="consumptionForm.note"
                                type="text"
                                placeholder="e.g. 50 pcs for card holders batch"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <div class="flex justify-end gap-2 pt-4 border-t border-[#E5E7EB]">
                            <AppButton tone="ghost" type="button" @click="showConsumptionModal = false">
                                Cancel
                            </AppButton>
                            <AppButton tone="primary" type="submit" :loading="consumptionForm.processing">
                                Record Consumption
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: ADJUST STOCK -->
            <div
                v-if="showAdjustModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-none p-4"
            >
                <div class="bg-white border border-[#E5E7EB] shadow-lg w-full max-w-md p-6 space-y-4">
                    <h2 class="text-lg font-bold text-[#111827]">Adjust Accessory Stock</h2>
                    <p class="text-xs text-[#6B7280]">
                        Enter signed quantity (+5 to add found units, -3 for damaged or lost units). Every adjustment is audited.
                    </p>

                    <form class="space-y-4" @submit.prevent="submitAdjustForm">
                        <FormField label="Adjustment Date" :error="adjustForm.errors.adjusted_on" required>
                            <input
                                v-model="adjustForm.adjusted_on"
                                type="date"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <FormField label="Accessory Item" :error="adjustForm.errors.accessory_item_id" required>
                            <select
                                v-model="adjustForm.accessory_item_id"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            >
                                <option v-for="item in allItems" :key="item.id" :value="String(item.id)">
                                    {{ item.name }} ({{ item.unit }})
                                </option>
                            </select>
                        </FormField>

                        <FormField label="Signed Quantity (+/-)" :error="adjustForm.errors.qty" required>
                            <input
                                v-model="adjustForm.qty"
                                type="number"
                                step="0.001"
                                placeholder="e.g. -5.000 or +10.000"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <FormField label="Reason" :error="adjustForm.errors.reason" required>
                            <select
                                v-model="adjustForm.reason"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            >
                                <option value="damaged">Damaged Material</option>
                                <option value="lost">Lost / Missing</option>
                                <option value="count_correction">Count Correction (Stock Audit)</option>
                                <option value="other">Other (Requires Note)</option>
                            </select>
                        </FormField>

                        <FormField
                            label="Note"
                            :error="adjustForm.errors.note"
                            :required="adjustForm.reason === 'other'"
                        >
                            <input
                                v-model="adjustForm.note"
                                type="text"
                                placeholder="Reason details / recount audit note"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <div class="flex justify-end gap-2 pt-4 border-t border-[#E5E7EB]">
                            <AppButton tone="ghost" type="button" @click="showAdjustModal = false">
                                Cancel
                            </AppButton>
                            <AppButton tone="primary" type="submit" :loading="adjustForm.processing">
                                Save Adjustment
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <!-- DRAWER: LEDGER MOVEMENTS HISTORY -->
            <SideDrawer
                :open="showMovementsDrawer"
                title="Stock Movement Ledger"
                @close="showMovementsDrawer = false"
            >
                <div v-if="activeDrawerItem" class="space-y-4">
                    <div class="bg-[#F9FAFB] p-3 border border-[#E5E7EB]">
                        <div class="font-bold text-[#111827] text-base">{{ activeDrawerItem.name }}</div>
                        <div class="text-xs text-[#6B7280] flex items-center gap-3 mt-1">
                            <span>Unit: <strong class="text-[#111827]">{{ activeDrawerItem.unit }}</strong></span>
                            <span>Current Stock: <strong class="text-[#0B6E5E]">{{ formatQuantity(activeDrawerItem.current_stock_qty) }}</strong></span>
                            <span>Min Stock: <strong class="text-[#111827]">{{ formatQuantity(activeDrawerItem.min_stock) }}</strong></span>
                        </div>
                    </div>

                    <div v-if="movementsLoading" class="p-8 text-center text-sm text-[#6B7280]">
                        Loading movements history...
                    </div>

                    <div v-else-if="movementLedger.length === 0" class="p-6 text-center text-sm text-[#6B7280]">
                        No stock movements recorded for this item.
                    </div>

                    <div v-else class="divide-y divide-[#E5E7EB] border border-[#E5E7EB]">
                        <div
                            v-for="m in movementLedger"
                            :key="m.id"
                            class="p-3 text-xs flex justify-between items-start hover:bg-[#F9FAFB]"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="px-1.5 py-0.5 font-bold uppercase text-[10px]"
                                        :class="[
                                            m.type === 'purchase'
                                                ? 'bg-[#D1FAE5] text-[#065F46]'
                                                : m.type === 'consumption'
                                                ? 'bg-[#FEE2E2] text-[#991B1B]'
                                                : 'bg-[#EFF6FF] text-[#1E40AF]'
                                        ]"
                                    >
                                        {{ m.type }}
                                    </span>
                                    <span class="font-medium text-[#111827]">{{ m.display_date }}</span>
                                </div>
                                <div class="text-[#6B7280]">{{ m.note || 'No notes' }}</div>
                                <div class="text-[10px] text-[#9CA3AF]">By: {{ m.creator }} · {{ m.created_at }}</div>
                            </div>
                            <div class="text-right">
                                <div
                                    class="font-bold tabular-nums text-sm"
                                    :class="m.signed_qty >= 0 ? 'text-[#059669]' : 'text-[#DC2626]'"
                                >
                                    {{ m.signed_qty > 0 ? '+' : '' }}{{ formatQuantity(m.signed_qty) }}
                                </div>
                                <div class="text-[11px] text-[#6B7280] tabular-nums mt-0.5">
                                    Bal: <strong>{{ formatQuantity(m.balance_after) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </SideDrawer>

            <!-- CONFIRM DELETE MODAL -->
            <ConfirmDialog
                :open="showDeleteConfirm"
                title="Archive Accessory Item"
                :description="`Are you sure you want to archive '${itemToDelete?.name}'? Historical purchase and consumption records will be preserved.`"
                confirm-text="Archive Item"
                :danger="true"
                @close="showDeleteConfirm = false"
                @confirm="deleteItem"
            />
    </div>
</template>
