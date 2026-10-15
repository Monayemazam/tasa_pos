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

interface LeatherItemOption {
    id: number;
    name: string;
    colour_grade: string | null;
    is_active: boolean;
    min_stock: number;
}

interface StockReportItem {
    id: number;
    name: string;
    colour_grade: string | null;
    min_stock: number;
    is_active: boolean;
    total_purchased_sqft: number;
    total_consumed_sqft: number;
    total_adjusted_sqft: number;
    current_stock_sqft: number;
    avg_cost: number | null;
    stock_value: number | null;
    is_low_stock: boolean;
    period_purchased_sqft: number;
    period_purchased_cost: number | null;
    period_consumed_sqft: number;
}

interface PurchaseItem {
    id: number;
    purchase_date: string;
    raw_date: string;
    item_id: number;
    item_name: string;
    colour_grade: string | null;
    supplier: string | null;
    qty_sqft: number;
    rate_per_sqft: number | null;
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
    colour_grade: string | null;
    qty_sqft: number;
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
    allItems: LeatherItemOption[];
    filters: {
        tab: string;
        from?: string;
        to?: string;
        search?: string;
        low_stock?: boolean;
        leather_item_id?: string | number;
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
        total_stock_sqft: number;
        total_stock_value: number | null;
        period_purchased_sqft: number;
        period_consumed_sqft: number;
        period_purchased_cost: number | null;
    };
    allowNegativeStock: boolean;
}>();

// --- Filter State ---
const currentTab = ref(props.activeTab);
const searchInput = ref(props.filters.search || '');
const fromDate = ref(props.filters.from || '');
const toDate = ref(props.filters.to || '');
const selectedItemId = ref(props.filters.leather_item_id || '');
const supplierFilter = ref(props.filters.supplier || '');
const lowStockOnly = ref(Boolean(props.filters.low_stock));

const switchTab = (tab: 'stock' | 'purchases' | 'consumption') => {
    currentTab.value = tab;
    applyFilters(tab);
};

const applyFilters = (targetTab = currentTab.value) => {
    router.get(
        '/inventory/leather',
        {
            tab: targetTab,
            search: searchInput.value || undefined,
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
            leather_item_id: selectedItemId.value || undefined,
            supplier: supplierFilter.value || undefined,
            low_stock: lowStockOnly.value ? '1' : undefined,
        },
        { preserveState: true, replace: true }
    );
};

const setThisMonthPreset = () => {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    fromDate.value = firstDay.toISOString().split('T')[0];
    toDate.value = lastDay.toISOString().split('T')[0];
    applyFilters();
};

const resetFilters = () => {
    searchInput.value = '';
    fromDate.value = '';
    toDate.value = '';
    selectedItemId.value = '';
    supplierFilter.value = '';
    lowStockOnly.value = false;
    router.get('/inventory/leather', { tab: currentTab.value }, { preserveState: true, replace: true });
};

// --- Create/Edit Leather Item Modal ---
const isItemModalOpen = ref(false);
const editingItem = ref<LeatherItemOption | null>(null);
const itemForm = useForm({
    name: '',
    colour_grade: '',
    min_stock: 0,
    is_active: true,
});

const openCreateItemModal = () => {
    editingItem.value = null;
    itemForm.reset();
    itemForm.clearErrors();
    itemForm.min_stock = 0;
    itemForm.is_active = true;
    isItemModalOpen.value = true;
};

const openEditItemModal = (item: StockReportItem | LeatherItemOption) => {
    editingItem.value = item;
    itemForm.reset();
    itemForm.clearErrors();
    itemForm.name = item.name;
    itemForm.colour_grade = item.colour_grade || '';
    itemForm.min_stock = item.min_stock;
    itemForm.is_active = item.is_active;
    isItemModalOpen.value = true;
};

const submitItem = () => {
    if (editingItem.value) {
        itemForm.put(`/inventory/leather/items/${editingItem.value.id}`, {
            onSuccess: () => {
                isItemModalOpen.value = false;
                showToast('Leather item updated.', 'success');
            },
        });
    } else {
        itemForm.post('/inventory/leather/items', {
            onSuccess: () => {
                isItemModalOpen.value = false;
                showToast('Leather item added to catalogue.', 'success');
            },
        });
    }
};

// --- Record Purchase Modal ---
const isPurchaseModalOpen = ref(false);
const purchaseForm = useForm({
    leather_item_id: '' as string | number,
    purchase_date: new Date().toISOString().split('T')[0],
    supplier: '',
    qty_sqft: 0,
    rate_per_sqft: 0,
    paid_amount: 0,
    payment_method: 'cash',
    note: '',
});

const calculatedTotalCost = computed(() => {
    const qty = Number(purchaseForm.qty_sqft) || 0;
    const rate = Number(purchaseForm.rate_per_sqft) || 0;
    return Math.round(qty * rate * 100) / 100;
});

const openPurchaseModal = (itemId?: number) => {
    purchaseForm.reset();
    purchaseForm.clearErrors();
    purchaseForm.leather_item_id = itemId || props.allItems[0]?.id || '';
    purchaseForm.purchase_date = new Date().toISOString().split('T')[0];
    purchaseForm.qty_sqft = 0;
    purchaseForm.rate_per_sqft = 0;
    purchaseForm.paid_amount = 0;
    purchaseForm.payment_method = 'cash';
    purchaseForm.note = '';
    isPurchaseModalOpen.value = true;
};

const onPurchaseRateOrQtyChange = () => {
    purchaseForm.paid_amount = calculatedTotalCost.value;
};

const submitPurchase = () => {
    purchaseForm.post('/inventory/leather/purchases', {
        onSuccess: () => {
            isPurchaseModalOpen.value = false;
            showToast('Leather purchase recorded.', 'success');
        },
    });
};

// --- Record Consumption Modal ---
const isConsumptionModalOpen = ref(false);
const consumptionForm = useForm({
    leather_item_id: '' as string | number,
    consumption_date: new Date().toISOString().split('T')[0],
    qty_sqft: 0,
    note: '',
});

const selectedConsumptionItem = computed(() => {
    return props.stockItems.find(i => i.id === Number(consumptionForm.leather_item_id)) || null;
});

const projectedConsumptionBalance = computed(() => {
    if (!selectedConsumptionItem.value) return 0;
    return Math.round((selectedConsumptionItem.value.current_stock_sqft - (Number(consumptionForm.qty_sqft) || 0)) * 1000) / 1000;
});

const openConsumptionModal = (itemId?: number) => {
    consumptionForm.reset();
    consumptionForm.clearErrors();
    consumptionForm.leather_item_id = itemId || props.allItems[0]?.id || '';
    consumptionForm.consumption_date = new Date().toISOString().split('T')[0];
    consumptionForm.qty_sqft = 0;
    consumptionForm.note = '';
    isConsumptionModalOpen.value = true;
};

const submitConsumption = () => {
    if (projectedConsumptionBalance.value < 0 && !props.allowNegativeStock) {
        showToast(`Consumption would result in negative stock (${projectedConsumptionBalance.value} sq ft), which is prohibited.`, 'danger');
        return;
    }

    consumptionForm.post('/inventory/leather/consumptions', {
        onSuccess: () => {
            isConsumptionModalOpen.value = false;
            showToast('Leather consumption logged.', 'success');
        },
    });
};

// --- Stock Adjustment Modal ---
const isAdjustModalOpen = ref(false);
const adjustForm = useForm({
    leather_item_id: '' as string | number,
    adjusted_on: new Date().toISOString().split('T')[0],
    qty: 0,
    reason: 'damaged',
    note: '',
});

const selectedAdjustItem = computed(() => {
    return props.stockItems.find(i => i.id === Number(adjustForm.leather_item_id)) || null;
});

const projectedAdjustBalance = computed(() => {
    if (!selectedAdjustItem.value) return 0;
    return Math.round((selectedAdjustItem.value.current_stock_sqft + (Number(adjustForm.qty) || 0)) * 1000) / 1000;
});

const openAdjustModal = (itemId?: number) => {
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.leather_item_id = itemId || props.allItems[0]?.id || '';
    adjustForm.adjusted_on = new Date().toISOString().split('T')[0];
    adjustForm.qty = 0;
    adjustForm.reason = 'damaged';
    adjustForm.note = '';
    isAdjustModalOpen.value = true;
};

const submitAdjust = () => {
    if (adjustForm.qty === 0) {
        showToast('Adjustment quantity cannot be zero.', 'danger');
        return;
    }
    if (projectedAdjustBalance.value < 0 && !props.allowNegativeStock) {
        showToast(`Adjustment would result in negative stock (${projectedAdjustBalance.value} sq ft), which is prohibited.`, 'danger');
        return;
    }

    adjustForm.post('/inventory/leather/adjust-stock', {
        onSuccess: () => {
            isAdjustModalOpen.value = false;
            showToast('Leather adjustment recorded.', 'success');
        },
    });
};

// --- Movements Drawer ---
const isMovementsOpen = ref(false);
const movementsItem = ref<any | null>(null);
const movementsList = ref<MovementLedgerItem[]>([]);
const loadingMovements = ref(false);

const openMovementsDrawer = async (item: StockReportItem | LeatherItemOption) => {
    movementsItem.value = item;
    isMovementsOpen.value = true;
    loadingMovements.value = true;
    movementsList.value = [];

    try {
        const response = await api.get(`/inventory/leather/items/${item.id}/movements`);
        movementsItem.value = response.item;
        movementsList.value = response.movements;
    } catch (err: any) {
        showToast('Failed to load leather stock movements.', 'danger');
    } finally {
        loadingMovements.value = false;
    }
};

// --- Archive Confirmation ---
const isArchiveOpen = ref(false);
const itemToArchive = ref<StockReportItem | LeatherItemOption | null>(null);

const confirmArchive = (item: StockReportItem | LeatherItemOption) => {
    itemToArchive.value = item;
    isArchiveOpen.value = true;
};

const submitArchive = () => {
    if (!itemToArchive.value) return;
    router.delete(`/inventory/leather/items/${itemToArchive.value.id}`, {
        onSuccess: () => {
            isArchiveOpen.value = false;
            showToast(`Leather item '${itemToArchive.value?.name}' archived.`, 'success');
        },
    });
};

const onPurchasesPageChange = (page: number) => {
    router.get('/inventory/leather', { ...props.filters, tab: 'purchases', page }, { preserveState: true, replace: true });
};

const onConsumptionsPageChange = (page: number) => {
    router.get('/inventory/leather', { ...props.filters, tab: 'consumption', page }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Raw Leather Stock & Ledger" />

    <div class="space-y-6">
        <!-- Page Header -->
        <PageHeader
            title="Leather Inventory"
            subtitle="PRD §8.2 · Raw material catalogue, purchases, consumption, and weighted average cost"
            icon="leather"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton
                        v-if="canAdjust"
                        variant="secondary"
                        size="sm"
                        @click="() => openAdjustModal()"
                    >
                        <AppIcon name="adjustments" :size="16" class="mr-1.5" />
                        Adjust Stock
                    </AppButton>

                    <AppButton
                        v-if="canManage"
                        variant="secondary"
                        size="sm"
                        @click="() => openConsumptionModal()"
                    >
                        <AppIcon name="edit" :size="16" class="mr-1.5" />
                        Log Consumption
                    </AppButton>

                    <AppButton
                        v-if="canManage"
                        variant="secondary"
                        size="sm"
                        @click="() => openPurchaseModal()"
                    >
                        <AppIcon name="plus" :size="16" class="mr-1.5" />
                        Add Purchase
                    </AppButton>

                    <AppButton
                        v-if="canManage"
                        variant="primary"
                        size="sm"
                        @click="openCreateItemModal"
                    >
                        <AppIcon name="plus" :size="16" class="mr-1.5" />
                        New Leather Item
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <!-- KPI Strip -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <StatCard
                title="Current Leather Stock"
                :value="formatQuantity(stats.total_stock_sqft, 'sq ft', 3)"
                icon="leather"
                caption="ΣPurchased − ΣConsumed ± Adjusted"
            />
            <StatCard
                title="Low Stock Items"
                :value="stats.low_stock_count"
                icon="warning"
                :tone="stats.low_stock_count > 0 ? 'warning' : 'neutral'"
                caption="Current stock <= minimum alert"
            />
            <StatCard
                title="Stock Asset Value"
                :value="canViewCost ? formatMoney(stats.total_stock_value) : '—'"
                icon="box"
                :caption="canViewCost ? 'Stock × weighted average cost' : 'Hidden from staff'"
            />
            <StatCard
                title="Activity in Range"
                :value="`${stats.period_purchased_sqft.toFixed(1)} / ${stats.period_consumed_sqft.toFixed(1)}`"
                icon="calendar"
                caption="Purchased / Consumed sq ft in range"
            />
        </div>

        <!-- Navigation Tabs: Stock | Purchases | Consumption -->
        <div class="flex border-b border-line-300 mb-4 bg-surface">
            <button
                type="button"
                :class="[
                    'px-5 py-3 text-sm font-bold border-b-2 transition-colors flex items-center gap-2 rounded-none',
                    currentTab === 'stock'
                        ? 'border-teal-700 text-teal-800 bg-teal-50/40'
                        : 'border-transparent text-ink-600 hover:text-ink-900 hover:border-line-300',
                ]"
                @click="switchTab('stock')"
            >
                <AppIcon name="leather" :size="16" />
                Stock & Valuation
            </button>

            <button
                type="button"
                :class="[
                    'px-5 py-3 text-sm font-bold border-b-2 transition-colors flex items-center gap-2 rounded-none',
                    currentTab === 'purchases'
                        ? 'border-teal-700 text-teal-800 bg-teal-50/40'
                        : 'border-transparent text-ink-600 hover:text-ink-900 hover:border-line-300',
                ]"
                @click="switchTab('purchases')"
            >
                <AppIcon name="plus" :size="16" />
                Purchases Log
            </button>

            <button
                type="button"
                :class="[
                    'px-5 py-3 text-sm font-bold border-b-2 transition-colors flex items-center gap-2 rounded-none',
                    currentTab === 'consumption'
                        ? 'border-teal-700 text-teal-800 bg-teal-50/40'
                        : 'border-transparent text-ink-600 hover:text-ink-900 hover:border-line-300',
                ]"
                @click="switchTab('consumption')"
            >
                <AppIcon name="edit" :size="16" />
                Consumption Log
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="bg-surface border border-line-200 p-3 sm:p-4 mb-4 space-y-3">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <!-- Search Input -->
                <div class="flex-1 relative">
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="Search leather name or grade..."
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        @keydown.enter="applyFilters()"
                    />
                    <button
                        v-if="searchInput"
                        type="button"
                        class="absolute right-2.5 top-2.5 text-ink-500 hover:text-ink-900"
                        @click="searchInput = ''; applyFilters()"
                    >
                        <AppIcon name="close" :size="14" />
                    </button>
                </div>

                <!-- Item Filter -->
                <div class="w-full sm:w-48">
                    <select
                        v-model="selectedItemId"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        @change="applyFilters()"
                    >
                        <option value="">All Leather Items</option>
                        <option v-for="item in allItems" :key="item.id" :value="item.id">
                            {{ item.name }} {{ item.colour_grade ? `(${item.colour_grade})` : '' }}
                        </option>
                    </select>
                </div>

                <!-- Supplier Filter (Purchases Tab only) -->
                <div v-if="currentTab === 'purchases'" class="w-full sm:w-44">
                    <input
                        v-model="supplierFilter"
                        type="text"
                        placeholder="Supplier..."
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        @keydown.enter="applyFilters()"
                    />
                </div>

                <!-- Date Range Filters -->
                <div class="flex items-center gap-2">
                    <input
                        v-model="fromDate"
                        type="date"
                        class="bg-white border border-line-300 px-2 py-2 text-xs text-ink-900 rounded-none focus:outline-teal-700"
                        title="From date"
                        @change="applyFilters()"
                    />
                    <span class="text-ink-400 text-xs">to</span>
                    <input
                        v-model="toDate"
                        type="date"
                        class="bg-white border border-line-300 px-2 py-2 text-xs text-ink-900 rounded-none focus:outline-teal-700"
                        title="To date"
                        @change="applyFilters()"
                    />
                    <button
                        type="button"
                        class="px-2 py-2 text-xs border border-line-300 bg-white hover:bg-teal-50 text-ink-700 font-medium whitespace-nowrap"
                        @click="setThisMonthPreset"
                    >
                        This Month
                    </button>
                </div>

                <!-- Low Stock Toggle (Stock Tab only) -->
                <button
                    v-if="currentTab === 'stock'"
                    type="button"
                    :class="[
                        'px-3 py-2 text-xs font-semibold uppercase tracking-wider flex items-center justify-center gap-1.5 rounded-none border transition-colors whitespace-nowrap',
                        lowStockOnly
                            ? 'bg-warning text-ink-900 border-warning'
                            : 'bg-white text-ink-700 border-line-300 hover:bg-teal-50',
                    ]"
                    @click="lowStockOnly = !lowStockOnly; applyFilters()"
                >
                    <AppIcon name="warning" :size="14" />
                    Low Stock Only
                </button>

                <!-- Filter & Reset -->
                <div class="flex items-center gap-2">
                    <AppButton variant="secondary" size="sm" @click="applyFilters()">
                        Filter
                    </AppButton>
                    <button
                        type="button"
                        class="text-xs text-ink-600 hover:text-teal-700 underline px-1 py-1"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 1: STOCK & VALUATION ==================== -->
        <div v-if="currentTab === 'stock'" class="w-full bg-surface border border-line-200 overflow-hidden">
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-sunken border-b border-line-300 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Leather Item
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Purchased (All)
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Consumed (All)
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Current Stock
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Min Stock
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right"
                            >
                                Avg Cost / sq ft
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right"
                            >
                                Stock Value
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-center">
                                Status
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line-200 text-sm text-ink-700">
                        <tr v-if="stockItems.length === 0">
                            <td :colspan="canViewCost ? 9 : 7" class="p-8 text-center text-ink-500">
                                <EmptyState
                                    icon="leather"
                                    title="No leather items found"
                                    description="No leather stock records match the current filter."
                                    action-label="Reset Filters"
                                    @action="resetFilters"
                                />
                            </td>
                        </tr>

                        <tr
                            v-for="item in stockItems"
                            :key="item.id"
                            class="hover:bg-teal-50 transition-colors"
                        >
                            <!-- Name & Colour/Grade -->
                            <td class="px-4 py-3">
                                <div class="font-bold text-ink-900">
                                    {{ item.name }}
                                </div>
                                <div v-if="item.colour_grade" class="text-xs text-ink-500 mt-0.5">
                                    {{ item.colour_grade }}
                                </div>
                            </td>

                            <!-- Purchased All -->
                            <td class="px-4 py-3 text-right tabular-nums text-xs">
                                <div>{{ item.total_purchased_sqft.toFixed(3) }} sq ft</div>
                                <div v-if="fromDate && toDate" class="text-[10px] text-teal-700">
                                    Range: +{{ item.period_purchased_sqft.toFixed(3) }}
                                </div>
                            </td>

                            <!-- Consumed All -->
                            <td class="px-4 py-3 text-right tabular-nums text-xs">
                                <div>{{ item.total_consumed_sqft.toFixed(3) }} sq ft</div>
                                <div v-if="fromDate && toDate" class="text-[10px] text-danger">
                                    Range: −{{ item.period_consumed_sqft.toFixed(3) }}
                                </div>
                            </td>

                            <!-- Current Stock -->
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <span
                                        :class="[
                                            'font-bold tabular-nums text-sm',
                                            item.is_low_stock ? 'text-danger font-extrabold' : 'text-ink-900',
                                        ]"
                                    >
                                        {{ item.current_stock_sqft.toFixed(3) }} sq ft
                                    </span>
                                    <span
                                        v-if="item.is_low_stock"
                                        class="bg-danger text-white text-[10px] font-bold px-1.5 py-0.2 uppercase"
                                    >
                                        Low
                                    </span>
                                </div>
                            </td>

                            <!-- Min Stock -->
                            <td class="px-4 py-3 text-right tabular-nums text-xs text-ink-600">
                                {{ item.min_stock.toFixed(3) }} sq ft
                            </td>

                            <!-- Avg Cost / sq ft (Cost protected) -->
                            <td v-if="canViewCost" class="px-4 py-3 text-right tabular-nums text-xs font-medium text-ink-900">
                                {{ formatMoney(item.avg_cost) }}
                            </td>

                            <!-- Stock Value -->
                            <td v-if="canViewCost" class="px-4 py-3 text-right tabular-nums text-xs font-bold text-ink-900">
                                {{ formatMoney(item.stock_value) }}
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3 text-center">
                                <StatusBadge
                                    :tone="item.is_active ? 'success' : 'neutral'"
                                    :label="item.is_active ? 'Active' : 'Inactive'"
                                    size="sm"
                                />
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="p-1.5 text-ink-600 hover:text-teal-700 hover:bg-teal-50 border border-transparent hover:border-line-200"
                                        title="View ledger & movement history"
                                        @click="openMovementsDrawer(item)"
                                    >
                                        <AppIcon name="box" :size="15" />
                                    </button>

                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="p-1.5 text-ink-600 hover:text-teal-700 hover:bg-teal-50 border border-transparent hover:border-line-200"
                                        title="Log purchase"
                                        @click="openPurchaseModal(item.id)"
                                    >
                                        <AppIcon name="plus" :size="15" />
                                    </button>

                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="p-1.5 text-ink-600 hover:text-ink-900 hover:bg-teal-50 border border-transparent hover:border-line-200"
                                        title="Log consumption"
                                        @click="openConsumptionModal(item.id)"
                                    >
                                        <AppIcon name="edit" :size="15" />
                                    </button>

                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="p-1.5 text-ink-600 hover:text-ink-900 hover:bg-teal-50 border border-transparent hover:border-line-200"
                                        title="Edit item catalogue details"
                                        @click="openEditItemModal(item)"
                                    >
                                        <AppIcon name="settings" :size="15" />
                                    </button>

                                    <button
                                        v-if="canDelete"
                                        type="button"
                                        class="p-1.5 text-ink-500 hover:text-danger hover:bg-danger-50 border border-transparent hover:border-line-200"
                                        title="Archive item"
                                        @click="confirmArchive(item)"
                                    >
                                        <AppIcon name="trash" :size="15" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards for Stock -->
            <div class="sm:hidden p-3 space-y-3">
                <div v-if="stockItems.length === 0" class="p-6 text-center text-ink-500 text-sm">
                    No leather items found.
                </div>

                <div
                    v-for="item in stockItems"
                    :key="item.id"
                    class="bg-surface border border-line-200 p-4 text-xs space-y-2.5"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-sm text-ink-900">{{ item.name }}</div>
                            <div v-if="item.colour_grade" class="text-ink-500">{{ item.colour_grade }}</div>
                        </div>
                        <StatusBadge :tone="item.is_active ? 'success' : 'neutral'" :label="item.is_active ? 'Active' : 'Inactive'" size="sm" />
                    </div>

                    <div class="grid grid-cols-2 gap-2 py-2 border-t border-b border-line-200">
                        <div>
                            <span class="text-ink-500 block uppercase text-[10px]">Current Stock</span>
                            <span :class="['font-extrabold text-sm tabular-nums', item.is_low_stock ? 'text-danger' : 'text-ink-900']">
                                {{ item.current_stock_sqft.toFixed(3) }} sq ft
                            </span>
                        </div>
                        <div>
                            <span class="text-ink-500 block uppercase text-[10px]">Min Stock</span>
                            <span class="tabular-nums text-sm">{{ item.min_stock.toFixed(3) }} sq ft</span>
                        </div>
                        <div v-if="canViewCost">
                            <span class="text-ink-500 block uppercase text-[10px]">Weighted Avg Cost</span>
                            <span class="font-semibold text-sm tabular-nums">{{ formatMoney(item.avg_cost) }}</span>
                        </div>
                        <div v-if="canViewCost">
                            <span class="text-ink-500 block uppercase text-[10px]">Stock Value</span>
                            <span class="font-bold text-sm tabular-nums">{{ formatMoney(item.stock_value) }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-1.5 pt-1">
                        <button type="button" class="px-2 py-1 border border-line-300 text-ink-700" @click="openMovementsDrawer(item)">
                            Ledger
                        </button>
                        <button v-if="canManage" type="button" class="px-2 py-1 border border-teal-700 text-teal-700 font-medium" @click="openPurchaseModal(item.id)">
                            + Purchase
                        </button>
                        <button v-if="canManage" type="button" class="px-2 py-1 border border-line-300 text-ink-700" @click="openConsumptionModal(item.id)">
                            − Use
                        </button>
                        <button v-if="canManage" type="button" class="p-1 border border-line-300 text-ink-700" @click="openEditItemModal(item)">
                            <AppIcon name="edit" :size="14" />
                        </button>
                        <button v-if="canDelete" type="button" class="p-1 border border-danger text-danger" @click="confirmArchive(item)">
                            <AppIcon name="trash" :size="14" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 2: PURCHASES LOG ==================== -->
        <div v-else-if="currentTab === 'purchases'" class="w-full bg-surface border border-line-200 overflow-hidden">
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-sunken border-b border-line-300 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Date
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Item / Grade
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Supplier
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Qty (sq ft)
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right"
                            >
                                Rate / sq ft
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right"
                            >
                                Total Cost
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Paid Amount
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Method / Note
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                By
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-200 text-sm text-ink-700">
                        <tr v-if="!purchases || purchases.data.length === 0">
                            <td :colspan="canViewCost ? 9 : 7" class="p-8 text-center text-ink-500">
                                <EmptyState
                                    icon="plus"
                                    title="No leather purchases recorded"
                                    description="Record purchases of raw leather hides in square feet."
                                    action-label="Log Purchase"
                                    @action="() => openPurchaseModal()"
                                />
                            </td>
                        </tr>

                        <tr
                            v-for="p in purchases?.data"
                            :key="p.id"
                            class="hover:bg-teal-50 transition-colors"
                        >
                            <td class="px-4 py-3 tabular-nums font-medium text-ink-900">
                                {{ p.purchase_date }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-ink-900">{{ p.item_name }}</div>
                                <div v-if="p.colour_grade" class="text-xs text-ink-500">{{ p.colour_grade }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-ink-700">
                                {{ p.supplier || '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-bold text-success">
                                +{{ p.qty_sqft.toFixed(3) }} sq ft
                            </td>
                            <td v-if="canViewCost" class="px-4 py-3 text-right tabular-nums text-xs">
                                {{ formatMoney(p.rate_per_sqft) }}
                            </td>
                            <td v-if="canViewCost" class="px-4 py-3 text-right tabular-nums font-bold text-ink-900">
                                {{ formatMoney(p.total_cost) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-ink-900 text-xs">
                                {{ formatMoney(p.paid_amount) }}
                            </td>
                            <td class="px-4 py-3 text-xs text-ink-600">
                                <span v-if="p.payment_method" class="uppercase font-semibold text-[10px] bg-line-100 px-1.5 py-0.5 border border-line-200 mr-1.5">
                                    {{ p.payment_method }}
                                </span>
                                <span>{{ p.note || '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-ink-500">
                                {{ p.creator }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Purchases Card View -->
            <div class="sm:hidden p-3 space-y-3">
                <div v-if="!purchases || purchases.data.length === 0" class="p-6 text-center text-ink-500 text-sm">
                    No leather purchases recorded.
                </div>
                <div
                    v-for="p in purchases?.data"
                    :key="p.id"
                    class="bg-surface border border-line-200 p-4 text-xs space-y-2"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-sm text-ink-900">{{ p.item_name }}</div>
                            <div class="text-ink-500">{{ p.purchase_date }} · {{ p.supplier || 'No supplier' }}</div>
                        </div>
                        <span class="font-bold text-success text-sm tabular-nums">+{{ p.qty_sqft.toFixed(3) }} sq ft</span>
                    </div>
                    <div v-if="canViewCost" class="flex justify-between border-t border-line-200 pt-1.5 text-ink-700">
                        <span>Rate: {{ formatMoney(p.rate_per_sqft) }}</span>
                        <span class="font-bold text-ink-900">Total: {{ formatMoney(p.total_cost) }}</span>
                    </div>
                </div>
            </div>

            <TablePagination
                v-if="purchases"
                :current-page="purchases.current_page"
                :total-items="purchases.total"
                :per-page="25"
                @update:current-page="onPurchasesPageChange"
            />
        </div>

        <!-- ==================== TAB 3: CONSUMPTION LOG ==================== -->
        <div v-else-if="currentTab === 'consumption'" class="w-full bg-surface border border-line-200 overflow-hidden">
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-sunken border-b border-line-300 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Date
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Leather Item
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Quantity Consumed
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Note / Batch Details
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Logged By
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-200 text-sm text-ink-700">
                        <tr v-if="!consumptions || consumptions.data.length === 0">
                            <td colspan="5" class="p-8 text-center text-ink-500">
                                <EmptyState
                                    icon="edit"
                                    title="No leather consumption recorded"
                                    description="Log leather usage in square feet. Note: consumption is stock-tracking only and creates no P&L expense."
                                    action-label="Log Consumption"
                                    @action="() => openConsumptionModal()"
                                />
                            </td>
                        </tr>

                        <tr
                            v-for="c in consumptions?.data"
                            :key="c.id"
                            class="hover:bg-teal-50 transition-colors"
                        >
                            <td class="px-4 py-3 tabular-nums font-medium text-ink-900">
                                {{ c.consumption_date }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-ink-900">{{ c.item_name }}</div>
                                <div v-if="c.colour_grade" class="text-xs text-ink-500">{{ c.colour_grade }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-bold text-danger">
                                −{{ c.qty_sqft.toFixed(3) }} sq ft
                            </td>
                            <td class="px-4 py-3 text-xs text-ink-600">
                                {{ c.note || '—' }}
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-ink-500">
                                {{ c.creator }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Consumptions Card View -->
            <div class="sm:hidden p-3 space-y-3">
                <div v-if="!consumptions || consumptions.data.length === 0" class="p-6 text-center text-ink-500 text-sm">
                    No leather consumption recorded.
                </div>
                <div
                    v-for="c in consumptions?.data"
                    :key="c.id"
                    class="bg-surface border border-line-200 p-4 text-xs space-y-1.5"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-sm text-ink-900">{{ c.item_name }}</div>
                            <div class="text-ink-500">{{ c.consumption_date }}</div>
                        </div>
                        <span class="font-bold text-danger text-sm tabular-nums">−{{ c.qty_sqft.toFixed(3) }} sq ft</span>
                    </div>
                    <div v-if="c.note" class="text-ink-700 pt-1">
                        {{ c.note }}
                    </div>
                </div>
            </div>

            <TablePagination
                v-if="consumptions"
                :current-page="consumptions.current_page"
                :total-items="consumptions.total"
                :per-page="25"
                @update:current-page="onConsumptionsPageChange"
            />
        </div>

        <!-- ==================== NEW / EDIT ITEM MODAL ==================== -->
        <SideDrawer
            :open="isItemModalOpen"
            :title="editingItem ? 'Edit Leather Item' : 'New Leather Item'"
            subtitle="PRD §8.2 · Raw material hide catalogue item with square feet units"
            width-class="sm:max-w-[480px]"
            @close="isItemModalOpen = false"
        >
            <form id="leather-item-form" class="space-y-4" @submit.prevent="submitItem">
                <FormField label="Leather Item Name" :error="itemForm.errors.name" required>
                    <input
                        v-model="itemForm.name"
                        type="text"
                        placeholder="e.g. Cow Nappa or মহিষের চামড়া"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        required
                    />
                </FormField>

                <FormField
                    label="Colour / Grade (Optional)"
                    :error="itemForm.errors.colour_grade"
                    help-text="e.g. Tan Grade A, Black Matte, Chocolate"
                >
                    <input
                        v-model="itemForm.colour_grade"
                        type="text"
                        placeholder="Optional colour or quality grade"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                    />
                </FormField>

                <FormField
                    label="Minimum Stock (Alert Level in sq ft)"
                    :error="itemForm.errors.min_stock"
                    help-text="Displays low-stock alert when stock falls to or below this level"
                >
                    <input
                        v-model.number="itemForm.min_stock"
                        type="number"
                        step="0.001"
                        min="0"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                    />
                </FormField>

                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            v-model="itemForm.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded-none text-teal-700 focus:ring-teal-700"
                        />
                        <span class="text-sm font-medium text-ink-900">Active item</span>
                    </label>
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isItemModalOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    variant="primary"
                    size="sm"
                    :loading="itemForm.processing"
                    @click="submitItem"
                >
                    {{ editingItem ? 'Update Item' : 'Create Item' }}
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== RECORD PURCHASE MODAL ==================== -->
        <SideDrawer
            :open="isPurchaseModalOpen"
            title="Log Leather Purchase"
            subtitle="PRD §8.2 / BR-5 · Recorded as inventory, NOT an expense (flows via COGS at sale)"
            width-class="sm:max-w-[500px]"
            @close="isPurchaseModalOpen = false"
        >
            <form id="leather-purchase-form" class="space-y-4" @submit.prevent="submitPurchase">
                <FormField label="Leather Item" :error="purchaseForm.errors.leather_item_id" required>
                    <select
                        v-model="purchaseForm.leather_item_id"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        required
                    >
                        <option value="">Select Leather Item</option>
                        <option v-for="item in allItems" :key="item.id" :value="item.id">
                            {{ item.name }} {{ item.colour_grade ? `(${item.colour_grade})` : '' }}
                        </option>
                    </select>
                </FormField>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField label="Purchase Date" :error="purchaseForm.errors.purchase_date" required>
                        <input
                            v-model="purchaseForm.purchase_date"
                            type="date"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        />
                    </FormField>

                    <FormField label="Supplier (Optional)" :error="purchaseForm.errors.supplier">
                        <input
                            v-model="purchaseForm.supplier"
                            type="text"
                            placeholder="Tannery / Trader name"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        />
                    </FormField>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField label="Quantity (sq ft)" :error="purchaseForm.errors.qty_sqft" required>
                        <input
                            v-model.number="purchaseForm.qty_sqft"
                            type="number"
                            step="0.001"
                            min="0.001"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                            required
                            @input="onPurchaseRateOrQtyChange"
                        />
                    </FormField>

                    <FormField
                        label="Rate per sq ft (৳)"
                        :error="purchaseForm.errors.rate_per_sqft"
                        help-text="Hidden from unauthorized staff"
                        required
                    >
                        <input
                            v-model.number="purchaseForm.rate_per_sqft"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                            required
                            @input="onPurchaseRateOrQtyChange"
                        />
                    </FormField>
                </div>

                <div class="bg-sunken border border-line-200 p-3 flex justify-between items-center text-xs">
                    <span class="text-ink-600 font-medium">Computed Total Cost:</span>
                    <span class="font-bold text-ink-900 text-sm tabular-nums">
                        {{ formatMoney(calculatedTotalCost) }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField label="Amount Paid Now (৳)" :error="purchaseForm.errors.paid_amount">
                        <input
                            v-model.number="purchaseForm.paid_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                        />
                    </FormField>

                    <FormField label="Payment Method" :error="purchaseForm.errors.payment_method">
                        <select
                            v-model="purchaseForm.payment_method"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
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
                        placeholder="Invoice number, hide batch details..."
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                    />
                </FormField>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isPurchaseModalOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    variant="primary"
                    size="sm"
                    :loading="purchaseForm.processing"
                    @click="submitPurchase"
                >
                    Record Purchase
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== RECORD CONSUMPTION MODAL ==================== -->
        <SideDrawer
            :open="isConsumptionModalOpen"
            title="Log Leather Consumption"
            subtitle="PRD §8.2 / BR-7 · Manual stock deduction (creates no P&L expense)"
            width-class="sm:max-w-[480px]"
            @close="isConsumptionModalOpen = false"
        >
            <form id="leather-consumption-form" class="space-y-4" @submit.prevent="submitConsumption">
                <FormField label="Leather Item" :error="consumptionForm.errors.leather_item_id" required>
                    <select
                        v-model="consumptionForm.leather_item_id"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        required
                    >
                        <option value="">Select Leather Item</option>
                        <option v-for="item in allItems" :key="item.id" :value="item.id">
                            {{ item.name }} {{ item.colour_grade ? `(${item.colour_grade})` : '' }}
                        </option>
                    </select>
                </FormField>

                <div v-if="selectedConsumptionItem" class="bg-sunken border border-line-200 p-3 space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-ink-600">Current Stock:</span>
                        <span class="font-bold tabular-nums text-ink-900">{{ selectedConsumptionItem.current_stock_sqft.toFixed(3) }} sq ft</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-600">Consuming:</span>
                        <span class="font-bold tabular-nums text-danger">−{{ (Number(consumptionForm.qty_sqft) || 0).toFixed(3) }} sq ft</span>
                    </div>
                    <div class="flex justify-between border-t border-line-200 pt-1 font-bold">
                        <span class="text-ink-900">Projected Balance:</span>
                        <span :class="['tabular-nums', projectedConsumptionBalance < 0 ? 'text-danger' : 'text-teal-800']">
                            {{ projectedConsumptionBalance.toFixed(3) }} sq ft
                        </span>
                    </div>
                    <div v-if="projectedConsumptionBalance < 0 && !allowNegativeStock" class="text-danger font-bold text-[11px] pt-1">
                         Negative stock is prohibited by system settings.
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField label="Consumption Date" :error="consumptionForm.errors.consumption_date" required>
                        <input
                            v-model="consumptionForm.consumption_date"
                            type="date"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        />
                    </FormField>

                    <FormField label="Quantity (sq ft)" :error="consumptionForm.errors.qty_sqft" required>
                        <input
                            v-model.number="consumptionForm.qty_sqft"
                            type="number"
                            step="0.001"
                            min="0.001"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        />
                    </FormField>
                </div>

                <FormField label="Note / Batch Info" :error="consumptionForm.errors.note">
                    <input
                        v-model="consumptionForm.note"
                        type="text"
                        placeholder="e.g. Wallet batch — 30 pcs"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                    />
                </FormField>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isConsumptionModalOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    variant="primary"
                    size="sm"
                    :loading="consumptionForm.processing"
                    @click="submitConsumption"
                >
                    Log Consumption
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== STOCK ADJUSTMENT MODAL ==================== -->
        <SideDrawer
            :open="isAdjustModalOpen"
            title="Adjust Leather Stock"
            subtitle="PRD §8.4 · Direct correction, damage, or loss adjustment"
            width-class="sm:max-w-[480px]"
            @close="isAdjustModalOpen = false"
        >
            <form id="leather-adjust-form" class="space-y-4" @submit.prevent="submitAdjust">
                <FormField label="Leather Item" :error="adjustForm.errors.leather_item_id" required>
                    <select
                        v-model="adjustForm.leather_item_id"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        required
                    >
                        <option value="">Select Leather Item</option>
                        <option v-for="item in allItems" :key="item.id" :value="item.id">
                            {{ item.name }} {{ item.colour_grade ? `(${item.colour_grade})` : '' }}
                        </option>
                    </select>
                </FormField>

                <div v-if="selectedAdjustItem" class="bg-sunken border border-line-200 p-3 space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-ink-600">Current Stock:</span>
                        <span class="font-bold tabular-nums text-ink-900">{{ selectedAdjustItem.current_stock_sqft.toFixed(3) }} sq ft</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-600">Adjustment:</span>
                        <span :class="['font-bold tabular-nums', adjustForm.qty < 0 ? 'text-danger' : 'text-success']">
                            {{ adjustForm.qty > 0 ? `+${adjustForm.qty}` : adjustForm.qty }} sq ft
                        </span>
                    </div>
                    <div class="flex justify-between border-t border-line-200 pt-1 font-bold">
                        <span class="text-ink-900">Projected Balance:</span>
                        <span :class="['tabular-nums', projectedAdjustBalance < 0 ? 'text-danger' : 'text-teal-800']">
                            {{ projectedAdjustBalance.toFixed(3) }} sq ft
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField label="Adjustment Date" :error="adjustForm.errors.adjusted_on" required>
                        <input
                            v-model="adjustForm.adjusted_on"
                            type="date"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        />
                    </FormField>

                    <FormField label="Reason" :error="adjustForm.errors.reason" required>
                        <select
                            v-model="adjustForm.reason"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        >
                            <option value="damaged">Damaged</option>
                            <option value="lost">Lost</option>
                            <option value="count_correction">Count Correction</option>
                            <option value="other">Other</option>
                        </select>
                    </FormField>
                </div>

                <FormField
                    label="Signed Quantity (+ to add, − to deduct in sq ft)"
                    :error="adjustForm.errors.qty"
                    required
                >
                    <input
                        v-model.number="adjustForm.qty"
                        type="number"
                        step="0.001"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                        required
                    />
                </FormField>

                <FormField
                    label="Note"
                    :error="adjustForm.errors.note"
                    :required="adjustForm.reason === 'other'"
                >
                    <input
                        v-model="adjustForm.note"
                        type="text"
                        placeholder="Adjustment explanation"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        :required="adjustForm.reason === 'other'"
                    />
                </FormField>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isAdjustModalOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    variant="primary"
                    size="sm"
                    :loading="adjustForm.processing"
                    @click="submitAdjust"
                >
                    Save Adjustment
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== MOVEMENTS LEDGER DRAWER ==================== -->
        <SideDrawer
            :open="isMovementsOpen"
            :title="movementsItem ? `Movement Ledger — ${movementsItem.name}` : 'Leather Ledger'"
            :subtitle="movementsItem ? `${movementsItem.colour_grade || 'Standard'} · Current Stock: ${Number(movementsItem.current_stock || 0).toFixed(3)} sq ft` : ''"
            width-class="sm:max-w-[620px]"
            @close="isMovementsOpen = false"
        >
            <div v-if="loadingMovements" class="p-8 text-center text-ink-500 text-sm">
                <AppIcon name="spinner" :size="24" class="animate-spin mb-2" />
                <div>Calculating chronological leather ledger...</div>
            </div>

            <div v-else-if="movementsList.length === 0" class="p-8 text-center text-ink-500 text-sm">
                No movements recorded for this leather item yet.
            </div>

            <div v-else class="space-y-3">
                <div class="text-xs text-ink-600 bg-sunken p-2.5 border border-line-200">
                    ℹ️ Running balance is calculated from all purchases (+), consumptions (−), and adjustments (±).
                </div>

                <div class="border border-line-200 overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-sunken border-b border-line-200">
                            <tr>
                                <th class="px-3 py-2 font-semibold text-ink-700">Date</th>
                                <th class="px-3 py-2 font-semibold text-ink-700">Type</th>
                                <th class="px-3 py-2 font-semibold text-ink-700 text-right">Qty (sq ft)</th>
                                <th class="px-3 py-2 font-semibold text-ink-700 text-right">Balance</th>
                                <th class="px-3 py-2 font-semibold text-ink-700">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-200">
                            <tr v-for="m in movementsList" :key="m.id" class="hover:bg-teal-50">
                                <td class="px-3 py-2 tabular-nums">
                                    <div class="font-medium text-ink-900">{{ m.display_date }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <StatusBadge
                                        :tone="m.type === 'purchase' ? 'success' : m.type === 'consumption' ? 'neutral' : 'warning'"
                                        :label="m.type.toUpperCase()"
                                        size="sm"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-bold">
                                    <span :class="m.signed_qty > 0 ? 'text-success' : 'text-danger'">
                                        {{ m.signed_qty > 0 ? `+${m.signed_qty.toFixed(3)}` : m.signed_qty.toFixed(3) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold text-ink-900">
                                    {{ m.balance_after.toFixed(3) }}
                                </td>
                                <td class="px-3 py-2 text-ink-600">
                                    <div>{{ m.note || '—' }}</div>
                                    <div class="text-[10px] text-ink-400">By {{ m.creator }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </SideDrawer>

        <!-- ==================== CONFIRM ARCHIVE DIALOG ==================== -->
        <ConfirmDialog
            :open="isArchiveOpen"
            title="Archive Leather Item"
            :description="`Are you sure you want to archive '${itemToArchive?.name}'? Historical purchase and consumption records will remain fully preserved.`"
            :danger="true"
            confirm-label="Archive Item"
            @confirm="submitArchive"
            @cancel="isArchiveOpen = false"
        />
    </div>
</template>
