<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import SideDrawer from '@/components/tasa/SideDrawer.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import { formatMoney } from '@/lib/formatters';

interface SummaryData {
    invoice_count: number;
    gross_sales: number;
    discounts: number;
    returns: number;
    net_sales: number;
    delivery_charge: number;
    grand_total: number;
    amount_received: number;
    amount_due: number;
}

interface ProductRow {
    product_id: number | null;
    name: string;
    sku: string;
    qty_sold: number;
    revenue: number;
    cogs?: number;
    gross_profit?: number;
    profit_margin?: number;
}

interface DayRow {
    date: string;
    invoice_count: number;
    gross_sales: number;
    discounts: number;
    returns: number;
    net_sales: number;
    amount_received: number;
    amount_due: number;
    cogs?: number;
    gross_profit?: number;
}

interface PaymentMethodRow {
    method: string;
    method_label: string;
    payment_count: number;
    total_amount: number;
    percentage: number;
}

interface CourierStatusRow {
    status: string;
    status_label: string;
    order_count: number;
    total_sales: number;
    cod_amount: number;
}

interface CustomerRow {
    customer_id: number;
    customer_name: string;
    customer_phone: string;
    invoice_count: number;
    total_spent: number;
    amount_paid: number;
    amount_due: number;
}

interface Props {
    filters: {
        preset: string;
        from: string;
        to: string;
        label: string;
    };
    summary: SummaryData;
    products: ProductRow[];
    daily: DayRow[];
    paymentMethods: PaymentMethodRow[];
    courierStatuses: CourierStatusRow[];
    topCustomers: CustomerRow[];
    canViewCostProfit: boolean;
}

const props = defineProps<Props>();

// State
const activeTab = ref<'products' | 'daily' | 'payment_methods' | 'courier' | 'customers'>('products');
const selectedPreset = ref(props.filters.preset);
const customFrom = ref(props.filters.from);
const customTo = ref(props.filters.to);
const showCustomDates = ref(props.filters.preset === 'custom');

// Drilldown Drawer State
const drawerOpen = ref(false);
const drawerLoading = ref(false);
const drawerTitle = ref('');
const drawerType = ref('');
const drawerRecords = ref<any[]>([]);

const presets = [
    { id: 'today', label: 'Today' },
    { id: 'yesterday', label: 'Yesterday' },
    { id: 'this_week', label: 'This Week' },
    { id: 'this_month', label: 'This Month' },
    { id: 'last_month', label: 'Last Month' },
    { id: 'this_year', label: 'This Year' },
    { id: 'custom', label: 'Custom Range' },
];

function applyPreset(presetId: string) {
    selectedPreset.value = presetId;
    if (presetId === 'custom') {
        showCustomDates.value = true;
        return;
    }
    showCustomDates.value = false;
    router.get(
        '/reports/sales',
        { date_preset: presetId },
        { preserveState: true, preserveScroll: true }
    );
}

function applyCustomRange() {
    if (!customFrom.value || !customTo.value) return;
    router.get(
        '/reports/sales',
        {
            date_preset: 'custom',
            date_from: customFrom.value,
            date_to: customTo.value,
        },
        { preserveState: true, preserveScroll: true }
    );
}

// Export URLs with current filters
const exportCsvUrl = computed(() => {
    const params = new URLSearchParams({
        date_preset: props.filters.preset,
        date_from: props.filters.from,
        date_to: props.filters.to,
    });
    return `/reports/sales/export/csv?${params.toString()}`;
});

const exportExcelUrl = computed(() => {
    const params = new URLSearchParams({
        date_preset: props.filters.preset,
        date_from: props.filters.from,
        date_to: props.filters.to,
    });
    return `/reports/sales/export/excel?${params.toString()}`;
});

const exportPdfUrl = computed(() => {
    const params = new URLSearchParams({
        date_preset: props.filters.preset,
        date_from: props.filters.from,
        date_to: props.filters.to,
    });
    return `/reports/sales/export/pdf?${params.toString()}`;
});

// Drilldown Trigger
async function openDrilldown(type: string, extraParams: Record<string, any> = {}) {
    drawerType.value = type;
    drawerLoading.value = true;
    drawerOpen.value = true;
    drawerRecords.value = [];
    drawerTitle.value = 'Loading records...';

    const params = new URLSearchParams({
        type,
        from: props.filters.from,
        to: props.filters.to,
        ...extraParams,
    });

    try {
        const res = await fetch(`/reports/sales/drilldown?${params.toString()}`);
        if (!res.ok) throw new Error('Failed to load drill-down records');
        const data = await res.json();
        drawerTitle.value = data.title || 'Source Records';
        drawerRecords.value = data.records || [];
    } catch (err) {
        drawerTitle.value = 'Error loading records';
        console.error(err);
    } finally {
        drawerLoading.value = false;
    }
}
</script>

<template>
    <Head title="Sales Report & Analytics" />

    <div class="space-y-6">
        <PageHeader
            title="Sales Report"
            :subtitle="`Computed strictly from source records: ${filters.label}`"
        >
            <template #actions>
                <div class="flex items-center space-x-2">
                    <a :href="exportCsvUrl" target="_blank" rel="noopener">
                        <AppButton variant="secondary" size="sm" icon="download">
                            CSV
                        </AppButton>
                    </a>
                    <a :href="exportExcelUrl" target="_blank" rel="noopener">
                        <AppButton variant="secondary" size="sm" icon="file-text">
                            Excel
                        </AppButton>
                    </a>
                    <a :href="exportPdfUrl" target="_blank" rel="noopener">
                        <AppButton variant="primary" size="sm" icon="printer">
                            Export PDF
                        </AppButton>
                    </a>
                </div>
            </template>
        </PageHeader>

        <!-- Date Range Filter & Presets Toolbar -->
        <div class="bg-surface border border-line-200 p-4 mb-6 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs font-bold uppercase tracking-wider text-ink-500 mr-1">Period:</span>
                <button
                    v-for="p in presets"
                    :key="p.id"
                    type="button"
                    class="px-2.5 py-1 text-xs font-medium border transition-colors cursor-pointer"
                    :class="selectedPreset === p.id ? 'bg-teal-700 text-white border-teal-700' : 'bg-surface text-ink-700 border-line-200 hover:bg-page'"
                    @click="applyPreset(p.id)"
                >
                    {{ p.label }}
                </button>
            </div>

            <!-- Custom Date Inputs -->
            <div v-if="showCustomDates" class="flex items-center space-x-2 pt-2 md:pt-0 border-t md:border-t-0 border-line-200">
                <input
                    v-model="customFrom"
                    type="date"
                    class="border border-line-200 bg-page px-2 py-1 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                />
                <span class="text-xs text-ink-500">to</span>
                <input
                    v-model="customTo"
                    type="date"
                    class="border border-line-200 bg-page px-2 py-1 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                />
                <AppButton variant="secondary" size="sm" @click="applyCustomRange">
                    Apply
                </AppButton>
            </div>
        </div>

        <!-- Summary KPI Cards Strip (Clickable for drill-down) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3 mb-6">
            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('invoices')">
                <StatCard
                    title="Invoices"
                    :value="summary.invoice_count"
                    subtext="Click to inspect sales"
                />
            </div>
            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('gross_sales')">
                <StatCard
                    title="Gross Sales"
                    :value="formatMoney(summary.gross_sales, 0)"
                    subtext="Subtotal before disc."
                />
            </div>
            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('discounts')">
                <StatCard
                    title="Discounts"
                    :value="formatMoney(summary.discounts, 0)"
                    subtext="Promo & customer disc."
                />
            </div>
            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('returns')">
                <StatCard
                    title="Returns"
                    :value="formatMoney(summary.returns, 0)"
                    subtext="Attributed by date"
                />
            </div>
            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('invoices')">
                <StatCard
                    title="Net Sales"
                    :value="formatMoney(summary.net_sales, 0)"
                    subtext="Gross − Disc. − Returns"
                    :is-primary="true"
                />
            </div>
            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('amount_received')">
                <StatCard
                    title="Received"
                    :value="formatMoney(summary.amount_received, 0)"
                    subtext="Payments collected"
                />
            </div>
            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('amount_due')">
                <StatCard
                    title="Amount Due"
                    :value="formatMoney(summary.amount_due, 0)"
                    subtext="Outstanding dues"
                    :is-negative="summary.amount_due > 0"
                />
            </div>
        </div>

        <!-- View Tabs Bar -->
        <div class="border-b border-line-200 mb-6 flex space-x-6 text-xs font-semibold">
            <button
                type="button"
                class="pb-3 border-b-2 cursor-pointer transition-colors"
                :class="activeTab === 'products' ? 'border-teal-700 text-teal-700 font-bold' : 'border-transparent text-ink-600 hover:text-ink-900'"
                @click="activeTab = 'products'"
            >
                By Product ({{ products.length }})
            </button>
            <button
                type="button"
                class="pb-3 border-b-2 cursor-pointer transition-colors"
                :class="activeTab === 'daily' ? 'border-teal-700 text-teal-700 font-bold' : 'border-transparent text-ink-600 hover:text-ink-900'"
                @click="activeTab = 'daily'"
            >
                Daily Trend ({{ daily.length }})
            </button>
            <button
                type="button"
                class="pb-3 border-b-2 cursor-pointer transition-colors"
                :class="activeTab === 'payment_methods' ? 'border-teal-700 text-teal-700 font-bold' : 'border-transparent text-ink-600 hover:text-ink-900'"
                @click="activeTab = 'payment_methods'"
            >
                Payment Methods ({{ paymentMethods.length }})
            </button>
            <button
                type="button"
                class="pb-3 border-b-2 cursor-pointer transition-colors"
                :class="activeTab === 'courier' ? 'border-teal-700 text-teal-700 font-bold' : 'border-transparent text-ink-600 hover:text-ink-900'"
                @click="activeTab = 'courier'"
            >
                Courier Status ({{ courierStatuses.length }})
            </button>
            <button
                type="button"
                class="pb-3 border-b-2 cursor-pointer transition-colors"
                :class="activeTab === 'customers' ? 'border-teal-700 text-teal-700 font-bold' : 'border-transparent text-ink-600 hover:text-ink-900'"
                @click="activeTab = 'customers'"
            >
                Top Customers ({{ topCustomers.length }})
            </button>
        </div>

        <!-- TAB 1: By Product Table -->
        <div v-if="activeTab === 'products'" class="bg-surface border border-line-200">
            <div class="p-4 border-b border-line-200 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-ink-900">Product Sales Breakdown</h3>
                    <p class="text-xs text-ink-600">Quantities sold and revenue generated. Click any row for invoice drill-down.</p>
                </div>
                <span class="text-xs text-ink-500">Sorted by Qty Sold</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-page text-ink-700 border-b border-line-200 uppercase font-semibold">
                        <tr>
                            <th scope="col" class="py-2.5 px-4">Product</th>
                            <th scope="col" class="py-2.5 px-4">SKU</th>
                            <th scope="col" class="py-2.5 px-4 text-center">Qty Sold</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Revenue</th>
                            <th v-if="canViewCostProfit" scope="col" class="py-2.5 px-4 text-right">COGS</th>
                            <th v-if="canViewCostProfit" scope="col" class="py-2.5 px-4 text-right">Gross Profit</th>
                            <th v-if="canViewCostProfit" scope="col" class="py-2.5 px-4 text-right">Margin %</th>
                            <th scope="col" class="py-2.5 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr
                            v-for="p in products"
                            :key="p.product_id ?? p.name"
                            class="hover:bg-page transition-colors cursor-pointer"
                            @click="openDrilldown('product', { product_id: p.product_id, product_name: p.name })"
                        >
                            <td class="py-2.5 px-4 font-semibold text-ink-900">{{ p.name }}</td>
                            <td class="py-2.5 px-4 font-mono text-ink-600">{{ p.sku }}</td>
                            <td class="py-2.5 px-4 text-center tabular-nums font-bold">{{ p.qty_sold }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums font-semibold">{{ formatMoney(p.revenue) }}</td>
                            <td v-if="canViewCostProfit" class="py-2.5 px-4 text-right tabular-nums text-ink-600">
                                {{ formatMoney(p.cogs ?? 0) }}
                            </td>
                            <td v-if="canViewCostProfit" class="py-2.5 px-4 text-right tabular-nums font-semibold text-teal-700">
                                {{ formatMoney(p.gross_profit ?? 0) }}
                            </td>
                            <td v-if="canViewCostProfit" class="py-2.5 px-4 text-right tabular-nums">
                                {{ p.profit_margin?.toFixed(1) }}%
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <span class="text-teal-700 hover:underline text-[11px] font-semibold">Inspect &rarr;</span>
                            </td>
                        </tr>
                        <tr v-if="products.length === 0">
                            <td :colspan="canViewCostProfit ? 8 : 5" class="py-8 text-center text-ink-500">
                                No product sales recorded for this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: Daily Trend Table -->
        <div v-if="activeTab === 'daily'" class="bg-surface border border-line-200">
            <div class="p-4 border-b border-line-200 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-ink-900">Daily Sales & Returns Progression</h3>
                    <p class="text-xs text-ink-600">Chronological daily sales performance. Click any day for transactions.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-page text-ink-700 border-b border-line-200 uppercase font-semibold">
                        <tr>
                            <th scope="col" class="py-2.5 px-4">Date</th>
                            <th scope="col" class="py-2.5 px-4 text-center">Invoices</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Gross Sales</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Discounts</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Returns</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Net Sales</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Received</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Due</th>
                            <th v-if="canViewCostProfit" scope="col" class="py-2.5 px-4 text-right">COGS</th>
                            <th v-if="canViewCostProfit" scope="col" class="py-2.5 px-4 text-right">Gross Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr
                            v-for="d in daily"
                            :key="d.date"
                            class="hover:bg-page transition-colors cursor-pointer"
                            @click="openDrilldown('day', { date: d.date })"
                        >
                            <td class="py-2.5 px-4 font-bold text-ink-900">{{ d.date }}</td>
                            <td class="py-2.5 px-4 text-center tabular-nums">{{ d.invoice_count }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums">{{ formatMoney(d.gross_sales) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums text-ink-600">{{ formatMoney(d.discounts) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums text-danger">{{ formatMoney(d.returns) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums font-bold text-ink-900">{{ formatMoney(d.net_sales) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums text-ink-700">{{ formatMoney(d.amount_received) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums text-danger">{{ formatMoney(d.amount_due) }}</td>
                            <td v-if="canViewCostProfit" class="py-2.5 px-4 text-right tabular-nums text-ink-600">
                                {{ formatMoney(d.cogs ?? 0) }}
                            </td>
                            <td v-if="canViewCostProfit" class="py-2.5 px-4 text-right tabular-nums font-semibold text-teal-700">
                                {{ formatMoney(d.gross_profit ?? 0) }}
                            </td>
                        </tr>
                        <tr v-if="daily.length === 0">
                            <td :colspan="canViewCostProfit ? 10 : 8" class="py-8 text-center text-ink-500">
                                No sales activity recorded in this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: Payment Methods Table -->
        <div v-if="activeTab === 'payment_methods'" class="bg-surface border border-line-200">
            <div class="p-4 border-b border-line-200">
                <h3 class="text-sm font-bold text-ink-900">Payment Methods Breakdown</h3>
                <p class="text-xs text-ink-600">Cash, bKash, Bank Transfers, and Courier collections in period.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-page text-ink-700 border-b border-line-200 uppercase font-semibold">
                        <tr>
                            <th scope="col" class="py-2.5 px-4">Payment Method</th>
                            <th scope="col" class="py-2.5 px-4 text-center">Transactions</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Total Amount</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Share (%)</th>
                            <th scope="col" class="py-2.5 px-4 text-center">Inspect</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr
                            v-for="pm in paymentMethods"
                            :key="pm.method"
                            class="hover:bg-page transition-colors cursor-pointer"
                            @click="openDrilldown('amount_received')"
                        >
                            <td class="py-2.5 px-4 font-bold text-ink-900">{{ pm.method_label }}</td>
                            <td class="py-2.5 px-4 text-center tabular-nums">{{ pm.payment_count }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums font-bold">{{ formatMoney(pm.total_amount) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums">{{ pm.percentage.toFixed(1) }}%</td>
                            <td class="py-2.5 px-4 text-center">
                                <span class="text-teal-700 hover:underline text-[11px] font-semibold">Inspect &rarr;</span>
                            </td>
                        </tr>
                        <tr v-if="paymentMethods.length === 0">
                            <td colspan="5" class="py-8 text-center text-ink-500">
                                No payment records found in this range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: Courier Status Table -->
        <div v-if="activeTab === 'courier'" class="bg-surface border border-line-200">
            <div class="p-4 border-b border-line-200">
                <h3 class="text-sm font-bold text-ink-900">Courier Deliveries Breakdown</h3>
                <p class="text-xs text-ink-600">Distribution of orders dispatched through Pathao Courier.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-page text-ink-700 border-b border-line-200 uppercase font-semibold">
                        <tr>
                            <th scope="col" class="py-2.5 px-4">Courier Status</th>
                            <th scope="col" class="py-2.5 px-4 text-center">Orders Count</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Total Invoice Value</th>
                            <th scope="col" class="py-2.5 px-4 text-right">COD Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr v-for="cs in courierStatuses" :key="cs.status">
                            <td class="py-2.5 px-4">
                                <StatusBadge :label="cs.status_label" :tone="cs.status === 'delivered' ? 'success' : (cs.status === 'failed' ? 'danger' : 'neutral')" />
                            </td>
                            <td class="py-2.5 px-4 text-center tabular-nums font-bold">{{ cs.order_count }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums">{{ formatMoney(cs.total_sales) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums font-semibold">{{ formatMoney(cs.cod_amount) }}</td>
                        </tr>
                        <tr v-if="courierStatuses.length === 0">
                            <td colspan="4" class="py-8 text-center text-ink-500">
                                No courier orders recorded in this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 5: Top Customers Table -->
        <div v-if="activeTab === 'customers'" class="bg-surface border border-line-200">
            <div class="p-4 border-b border-line-200">
                <h3 class="text-sm font-bold text-ink-900">Top Customers</h3>
                <p class="text-xs text-ink-600">Highest spending buyers within the selected period.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-page text-ink-700 border-b border-line-200 uppercase font-semibold">
                        <tr>
                            <th scope="col" class="py-2.5 px-4">Customer Name</th>
                            <th scope="col" class="py-2.5 px-4">Phone</th>
                            <th scope="col" class="py-2.5 px-4 text-center">Invoices</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Total Spent</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Paid</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Current Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr v-for="c in topCustomers" :key="c.customer_phone">
                            <td class="py-2.5 px-4 font-bold text-ink-900">{{ c.customer_name }}</td>
                            <td class="py-2.5 px-4 font-mono text-ink-600">{{ c.customer_phone }}</td>
                            <td class="py-2.5 px-4 text-center tabular-nums font-bold">{{ c.invoice_count }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums font-bold text-teal-700">{{ formatMoney(c.total_spent) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums">{{ formatMoney(c.amount_paid) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums text-danger font-semibold">{{ formatMoney(c.amount_due) }}</td>
                        </tr>
                        <tr v-if="topCustomers.length === 0">
                            <td colspan="6" class="py-8 text-center text-ink-500">
                                No customer activity found in this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Drill-down SideDrawer Component -->
        <SideDrawer
            :is-open="drawerOpen"
            :title="drawerTitle"
            width="640px"
            @close="drawerOpen = false"
        >
            <div v-if="drawerLoading" class="p-8 text-center text-ink-500 text-xs">
                <AppIcon name="clock" class="animate-spin inline-block mr-2" />
                Querying ledger records...
            </div>

            <div v-else-if="drawerRecords.length === 0" class="p-8 text-center text-ink-500 text-xs">
                No matching primary records found for this filter.
            </div>

            <div v-else class="divide-y divide-line-100 text-xs">
                <!-- Invoices Drilldown List -->
                <div
                    v-for="rec in drawerRecords"
                    :key="rec.id ?? rec.sale_id ?? rec.invoice_no"
                    class="p-4 hover:bg-page transition-colors flex items-start justify-between gap-4"
                >
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-ink-900 font-mono">{{ rec.invoice_no }}</span>
                            <StatusBadge
                                v-if="rec.payment_status"
                                :label="rec.payment_status"
                                :tone="rec.payment_status === 'paid' ? 'success' : 'warning'"
                            />
                        </div>
                        <div class="text-[11px] text-ink-600 mt-1">
                            {{ rec.customer_name }} &bull; {{ rec.customer_phone }}
                        </div>
                        <div class="text-[11px] text-ink-500 mt-0.5">
                            Date: {{ rec.sale_date ?? rec.returned_on ?? rec.paid_on }}
                            <span v-if="rec.reason"> &bull; Reason: {{ rec.reason }}</span>
                            <span v-if="rec.method"> &bull; Method: {{ rec.method }}</span>
                            <span v-if="rec.item_name"> &bull; Item: {{ rec.item_name }} ({{ rec.net_qty }} pcs)</span>
                        </div>
                    </div>

                    <div class="text-right whitespace-nowrap">
                        <div class="font-bold text-ink-900 tabular-nums">
                            {{ formatMoney(rec.grand_total ?? rec.return_value ?? rec.amount ?? rec.line_revenue) }}
                        </div>
                        <div v-if="rec.due_amount > 0" class="text-[11px] text-danger tabular-nums">
                            Due: {{ formatMoney(rec.due_amount) }}
                        </div>
                        <div v-if="rec.line_profit !== undefined" class="text-[11px] text-teal-700 tabular-nums">
                            Profit: {{ formatMoney(rec.line_profit) }}
                        </div>
                    </div>
                </div>
            </div>
        </SideDrawer>
    </div>
</template>
