<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import SideDrawer from '@/components/tasa/SideDrawer.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import { formatMoney } from '@/lib/formatters';

// Chart.js setup for Trend Visual
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
} from 'chart.js';
import { Bar } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

interface StatementLine {
    code: string;
    label: string;
    amount: number;
    margin_pct?: number;
    is_excluded?: boolean;
    previous?: number;
    variance?: {
        diff: number;
        pct: number | null;
        pct_formatted: string;
        direction: 'up' | 'down' | 'flat';
    };
}

interface ReportData {
    period: {
        from: string;
        to: string;
        salaries_whole_month_notice?: boolean;
    };
    cost_includes_labour: boolean;
    revenue: {
        invoice_count: number;
        gross_sales: number;
        discounts: number;
        returns: number;
        net_sales: number;
        delivery_income: number;
        total_revenue: number;
        amount_received: number;
        amount_due: number;
    };
    cogs: number;
    gross_profit: number;
    gross_margin_pct: number;
    expenses: {
        facebook_ads: number;
        other_marketing: number;
        courier: number;
        salaries: number;
        wages: number;
        packaging: number;
        rent_utilities: number;
        other: number;
        total: number;
    };
    net_profit: number;
    net_margin_pct: number;
    marketing: {
        facebook_ads_spend: number;
        net_sales: number;
        roas: number | null;
        roas_formatted: string;
        ad_spend_pct_sales: number;
    };
    statement: {
        gross_sales: StatementLine;
        discounts: StatementLine;
        returns: StatementLine;
        net_sales: StatementLine;
        delivery_charges: StatementLine;
        total_revenue: StatementLine;
        cogs: StatementLine;
        gross_profit: StatementLine;
        expenses: Record<string, StatementLine>;
        net_profit: StatementLine;
    };
    memo: {
        leather: {
            purchased_cost: number;
            purchased_sqft: number;
            consumed_sqft: number;
            consumed_value: number;
        };
        accessories: {
            purchased_cost: number;
            purchased_qty: number;
            consumed_qty: number;
            consumed_value: number;
        };
        closing_stock: {
            finished_products: number;
            leather: number;
            accessories: number;
            total: number;
        };
        cod_receivable: number;
        customer_dues: number;
        payroll_balances: {
            owed: number;
            advances: number;
            net: number;
        };
        cash_summary: {
            cash_in: {
                customer_payments: number;
                cod_settlements: number;
                total: number;
            };
            cash_out: {
                expenses: number;
                salaries: number;
                wages: number;
                material_purchases: number;
                leather_purchases: number;
                accessory_purchases: number;
                refunds: number;
                total: number;
            };
            net_movement: number;
        };
    };
    top_products: Array<{
        product_id: number | null;
        name: string;
        sku: string;
        qty_sold: number;
        revenue: number;
        cogs: number;
        gross_profit: number;
        profit_margin: number;
    }>;
    expense_breakdown: Array<{
        key: string;
        code: string;
        label: string;
        amount: number;
        percentage: number;
        color: string;
    }>;
    monthly_trend: Array<{
        month_key: string;
        month_label: string;
        revenue: number;
        expenses: number;
        cogs: number;
        net_profit: number;
    }>;
}

interface Props {
    filters: {
        preset: string;
        from: string;
        to: string;
        label: string;
        compare: boolean;
        prev_from?: string | null;
        prev_to?: string | null;
        prev_label?: string | null;
    };
    report: ReportData;
    comparative?: {
        current: ReportData;
        previous: ReportData;
        statement: any;
        kpis: any;
    } | null;
}

const props = defineProps<Props>();

// Controls State
const selectedPreset = ref(props.filters.preset);
const customFrom = ref(props.filters.from);
const customTo = ref(props.filters.to);
const showCustomDates = ref(props.filters.preset === 'custom');
const compareWithPrevious = ref(props.filters.compare);

// Active Statement View
const statement = computed(() => {
    if (props.comparative && props.filters.compare) {
        return props.comparative.statement;
    }
    return props.report.statement;
});

// Drilldown Drawer State
const drawerOpen = ref(false);
const drawerLoading = ref(false);
const drawerTitle = ref('');
const drawerSubtitle = ref('');
const drawerType = ref('');
const drawerRecords = ref<any[]>([]);
const drawerTotal = ref<number | null>(null);

const presets = [
    { id: 'this_month', label: 'This Month' },
    { id: 'last_month', label: 'Last Month' },
    { id: 'this_quarter', label: 'This Quarter' },
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
        '/reports/profit-loss',
        {
            date_preset: presetId,
            compare: compareWithPrevious.value ? 1 : 0,
        },
        { preserveState: true, preserveScroll: true }
    );
}

const dateRangeError = ref('');

function applyCustomRange() {
    dateRangeError.value = '';
    if (!customFrom.value || !customTo.value) {
        dateRangeError.value = 'Please select both start and end dates.';
        return;
    }
    if (customFrom.value > customTo.value) {
        dateRangeError.value = 'Start date cannot be after end date.';
        return;
    }
    router.get(
        '/reports/profit-loss',
        {
            date_preset: 'custom',
            date_from: customFrom.value,
            date_to: customTo.value,
            compare: compareWithPrevious.value ? 1 : 0,
        },
        { preserveState: true, preserveScroll: true }
    );
}

function toggleCompare() {
    compareWithPrevious.value = !compareWithPrevious.value;
    router.get(
        '/reports/profit-loss',
        {
            date_preset: selectedPreset.value,
            date_from: selectedPreset.value === 'custom' ? customFrom.value : undefined,
            date_to: selectedPreset.value === 'custom' ? customTo.value : undefined,
            compare: compareWithPrevious.value ? 1 : 0,
        },
        { preserveState: true, preserveScroll: true }
    );
}

// Export URLs
const exportExcelUrl = computed(() => {
    const params = new URLSearchParams({
        date_preset: props.filters.preset,
        date_from: props.filters.from,
        date_to: props.filters.to,
        compare: props.filters.compare ? '1' : '0',
    });
    return `/reports/profit-loss/export/excel?${params.toString()}`;
});

const exportPdfUrl = computed(() => {
    const params = new URLSearchParams({
        date_preset: props.filters.preset,
        date_from: props.filters.from,
        date_to: props.filters.to,
        compare: props.filters.compare ? '1' : '0',
    });
    return `/reports/profit-loss/export/pdf?${params.toString()}`;
});

// Drilldown Trigger
async function openDrilldown(type: string, extraParams: Record<string, any> = {}) {
    drawerType.value = type;
    drawerLoading.value = true;
    drawerOpen.value = true;
    drawerRecords.value = [];
    drawerTotal.value = null;
    drawerTitle.value = 'Loading source records...';
    drawerSubtitle.value = '';

    const params = new URLSearchParams({
        type,
        from: props.filters.from,
        to: props.filters.to,
        ...extraParams,
    });

    try {
        const res = await fetch(`/reports/profit-loss/drilldown?${params.toString()}`);
        if (!res.ok) throw new Error('Failed to load drilldown records');
        const data = await res.json();
        drawerTitle.value = data.title || 'Source Records';
        drawerSubtitle.value = data.subtitle || '';
        drawerRecords.value = data.records || [];
        drawerTotal.value = data.total ?? null;
    } catch (err) {
        drawerTitle.value = 'Error loading records';
        drawerSubtitle.value = 'Could not fetch underlying records. Please retry.';
        console.error(err);
    } finally {
        drawerLoading.value = false;
    }
}

// Chart.js Data & Options for 6-Month Trend
const chartData = computed(() => {
    const trend = props.report.monthly_trend || [];
    return {
        labels: trend.map(t => t.month_label),
        datasets: [
            {
                label: 'Total Revenue',
                backgroundColor: '#0B6E5E', // Deep teal
                borderRadius: 0,
                data: trend.map(t => t.revenue),
            },
            {
                label: 'Operating Expenses',
                backgroundColor: '#7F8E8C', // Line 400 neutral
                borderRadius: 0,
                data: trend.map(t => t.expenses),
            },
            {
                label: 'Net Profit',
                backgroundColor: '#1B7F3B', // Success green
                borderRadius: 0,
                data: trend.map(t => t.net_profit),
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            labels: {
                boxWidth: 12,
                boxHeight: 12,
                font: { family: 'Hanken Grotesk', size: 11 },
                color: '#2B3A38',
            },
        },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    const val = Number(context.parsed.y || 0);
                    return ` ${context.dataset.label}: ৳ ${val.toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                color: '#5F6F6D',
                font: { family: 'Hanken Grotesk', size: 10 },
            },
        },
        y: {
            grid: { color: '#EBEFEE' },
            ticks: {
                color: '#5F6F6D',
                font: { family: 'Hanken Grotesk', size: 10 },
                callback: (val: any) => `৳${(Number(val) / 1000).toFixed(0)}k`,
            },
        },
    },
};
</script>

<template>
    <Head title="Owner Profit & Loss Statement (PRD §11)" />

    <div class="space-y-6">
        <!-- Page Header -->
        <PageHeader
            title="Profit & Loss Statement"
            :subtitle="`Owner Executive Statement · Strict PRD §11 source-row computation · ${filters.label}`"
        >
            <template #actions>
                <div class="flex items-center space-x-2">
                    <a :href="exportPdfUrl" target="_blank">
                        <AppButton variant="secondary" icon="download" size="sm">
                            Export PDF
                        </AppButton>
                    </a>
                    <a :href="exportExcelUrl">
                        <AppButton variant="secondary" icon="download" size="sm">
                            Export Excel
                        </AppButton>
                    </a>
                </div>
            </template>
        </PageHeader>

        <!-- Executive Meta Banner -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border border-teal-200 bg-teal-50 px-4 py-2.5 text-xs text-ink-700">
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center bg-teal-700 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">
                    Owner Confidential
                </span>
                <span>
                    Source of truth: Direct transaction lines. Historical cost uses snapshot cost.
                </span>
                <span class="font-semibold text-ink-900">
                    Labour in cost price (PRD O-1):
                    <span :class="report.cost_includes_labour ? 'text-amber-700 font-bold' : 'text-teal-700 font-bold'">
                        {{ report.cost_includes_labour ? 'Yes (Line L excluded)' : 'No (Line L included)' }}
                    </span>
                </span>
            </div>

            <div v-if="filters.compare && filters.prev_label" class="text-xs font-semibold text-teal-800">
                Comparing vs {{ filters.prev_label }}
            </div>
        </div>

        <!-- Whole Month Salaries Notice -->
        <div
            v-if="report.period.salaries_whole_month_notice"
            class="mb-4 flex items-center space-x-2 border border-amber-200 bg-[#FFF4DB] px-4 py-2 text-xs text-[#8A5A00]"
        >
            <AppIcon name="alert-circle" :size="16" />
            <span>
                <strong>Range notice:</strong> Salaries are counted by whole month. Salary months whose first day falls within this range are included.
            </span>
        </div>

        <!-- Controls Toolbar: Date Presets, Custom Range, Compare Toggle -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 border border-line-200 bg-surface p-4">
            <!-- Preset Buttons -->
            <div class="flex flex-wrap items-center gap-1.5">
                <button
                    v-for="p in presets"
                    :key="p.id"
                    type="button"
                    class="border px-3 py-1.5 text-xs font-semibold transition-colors cursor-pointer"
                    :class="
                        selectedPreset === p.id
                            ? 'border-teal-700 bg-teal-700 text-white'
                            : 'border-line-200 bg-surface text-ink-700 hover:border-line-400'
                    "
                    @click="applyPreset(p.id)"
                >
                    {{ p.label }}
                </button>
            </div>

            <!-- Custom Date Inputs -->
            <div v-if="showCustomDates" class="flex flex-col gap-1">
                <div class="flex items-center space-x-2">
                    <input
                        v-model="customFrom"
                        type="date"
                        class="border border-line-400 bg-surface px-2.5 py-1 text-xs text-ink-900 focus:outline-teal-700"
                    />
                    <span class="text-xs text-ink-500">to</span>
                    <input
                        v-model="customTo"
                        type="date"
                        class="border border-line-400 bg-surface px-2.5 py-1 text-xs text-ink-900 focus:outline-teal-700"
                    />
                    <AppButton variant="secondary" size="sm" @click="applyCustomRange">
                        Apply
                    </AppButton>
                </div>
                <span v-if="dateRangeError" class="text-[11px] font-semibold text-rose-600">
                    {{ dateRangeError }}
                </span>
            </div>

            <!-- Compare Toggle Switch -->
            <div class="flex items-center space-x-2 ml-auto">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="filters.compare"
                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer border border-line-400 transition-colors focus:outline-teal-700"
                    :class="filters.compare ? 'bg-teal-700' : 'bg-sunken'"
                    @click="toggleCompare"
                >
                    <span
                        class="pointer-events-none inline-block h-3.5 w-3.5 transform bg-white transition-transform"
                        :class="filters.compare ? 'translate-x-4' : 'translate-x-0.5'"
                        style="margin-top: 1px;"
                    />
                </button>
                <span class="text-xs font-semibold text-ink-700 select-none cursor-pointer" @click="toggleCompare">
                    Compare with previous period
                </span>
            </div>
        </div>

        <!-- KPI Strip (PRD §11.4) -->
        <div class="mb-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('gross_sales')">
                <StatCard
                    title="Total Revenue (F)"
                    :value="formatMoney(report.revenue.total_revenue, 0)"
                    :subtext="`Gross: ৳${formatMoney(report.revenue.gross_sales, 0)}`"
                />
            </div>

            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('cogs')">
                <StatCard
                    title="Gross Profit (H)"
                    :value="formatMoney(report.gross_profit, 0)"
                    :subtext="`Margin: ${report.gross_margin_pct.toFixed(1)}%`"
                />
            </div>

            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('facebook_ads')">
                <StatCard
                    title="Total OPEX (P)"
                    :value="formatMoney(report.expenses.total, 0)"
                    subtext="Operating expenses"
                />
            </div>

            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('gross_sales')">
                <StatCard
                    title="Net Profit (Q)"
                    :value="formatMoney(report.net_profit, 0)"
                    :subtext="`Net Margin: ${report.net_margin_pct.toFixed(1)}%`"
                    :is-primary="true"
                    :is-negative="report.net_profit < 0"
                />
            </div>

            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('facebook_ads')">
                <StatCard
                    title="Ad Spend % Sales"
                    :value="`${report.marketing.ad_spend_pct_sales.toFixed(1)}%`"
                    :subtext="`Spend: ৳${formatMoney(report.marketing.facebook_ads_spend, 0)}`"
                />
            </div>

            <div class="cursor-pointer transition-transform hover:-translate-y-0.5" @click="openDrilldown('facebook_ads')">
                <StatCard
                    title="Facebook ROAS"
                    :value="report.marketing.roas_formatted"
                    subtext="Net Sales ÷ Ads"
                />
            </div>
        </div>

        <!-- Main PRD §11.1 Statement Table -->
        <div class="mb-8 border border-line-200 bg-surface">
            <!-- Table Header Bar -->
            <div class="flex items-center justify-between border-b border-line-200 px-6 py-4">
                <div>
                    <h2 class="text-sm font-bold text-ink-900 uppercase tracking-wider">
                        Statement of Profit & Loss (PRD §11.1)
                    </h2>
                    <p class="text-xs text-ink-600 mt-0.5">
                        Click any number to open underlying source records drill-down.
                    </p>
                </div>

                <div v-if="filters.compare" class="text-xs font-bold text-teal-800 bg-teal-100 px-2.5 py-1">
                    Comparative Mode Active
                </div>
            </div>

            <!-- Desktop / Tablet Comparative Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="sticky top-0 bg-sunken z-10">
                        <tr class="bg-sunken text-ink-700 uppercase tracking-wider border-b border-line-300">
                            <th scope="col" class="py-3 px-4 w-12 font-bold text-center">Line</th>
                            <th scope="col" class="py-3 px-4 font-bold">Statement Item</th>
                            <th scope="col" class="py-3 px-4 text-right font-bold w-44">
                                {{ filters.compare ? 'Current Period' : 'Amount (BDT)' }}
                            </th>
                            <th v-if="filters.compare" scope="col" class="py-3 px-4 text-right font-bold w-44">
                                Previous Period
                            </th>
                            <th v-if="filters.compare" scope="col" class="py-3 px-4 text-right font-bold w-36">
                                Variance
                            </th>
                            <th v-if="filters.compare" scope="col" class="py-3 px-4 text-center font-bold w-28">
                                % Change
                            </th>
                            <th scope="col" class="py-3 px-4 text-ink-500 font-normal">Formula / Explanation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-200 text-ink-900">
                        <!-- SECTION 1: REVENUE -->
                        <tr class="bg-teal-50/50 font-bold text-teal-900">
                            <td colspan="7" class="py-2 px-4 uppercase tracking-wider text-[11px]">
                                1. Revenue
                            </td>
                        </tr>

                        <!-- Line A -->
                        <tr class="hover:bg-teal-50/30">
                            <td class="py-2.5 px-4 font-bold text-teal-700 text-center">A</td>
                            <td class="py-2.5 px-4 font-medium">Gross sales (invoice subtotals)</td>
                            <td
                                class="py-2.5 px-4 text-right font-bold font-mono text-teal-700 cursor-pointer underline decoration-dotted hover:text-teal-900"
                                @click="openDrilldown('gross_sales')"
                            >
                                ৳{{ formatMoney(statement.gross_sales.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-ink-600">
                                ৳{{ formatMoney(statement.gross_sales.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-xs">
                                {{ (statement.gross_sales.variance?.diff ?? 0) >= 0 ? '+' : '' }}৳{{ formatMoney(statement.gross_sales.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold" :class="(statement.gross_sales.variance?.diff ?? 0) >= 0 ? 'bg-[#E7F4EA] text-[#14602C]' : 'bg-[#FDECEA] text-[#8E1B12]'">
                                    {{ statement.gross_sales.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-ink-600 text-[11px]">Subtotals for all non-cancelled sales</td>
                        </tr>

                        <!-- Line B -->
                        <tr class="hover:bg-teal-50/30">
                            <td class="py-2.5 px-4 font-bold text-teal-700 text-center">B</td>
                            <td class="py-2.5 px-4">− Discounts</td>
                            <td
                                class="py-2.5 px-4 text-right font-mono text-ink-700 cursor-pointer underline decoration-dotted hover:text-teal-900"
                                @click="openDrilldown('discounts')"
                            >
                                ৳{{ formatMoney(statement.discounts.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-ink-600">
                                ৳{{ formatMoney(statement.discounts.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-xs">
                                ৳{{ formatMoney(statement.discounts.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold text-ink-600">
                                    {{ statement.discounts.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-ink-600 text-[11px]">Invoice promo & customer discounts</td>
                        </tr>

                        <!-- Line C -->
                        <tr class="hover:bg-teal-50/30">
                            <td class="py-2.5 px-4 font-bold text-teal-700 text-center">C</td>
                            <td class="py-2.5 px-4">− Sales returns / cancellations</td>
                            <td
                                class="py-2.5 px-4 text-right font-mono text-[#8E1B12] cursor-pointer underline decoration-dotted hover:text-red-900"
                                @click="openDrilldown('returns')"
                            >
                                ৳{{ formatMoney(statement.returns.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-ink-600">
                                ৳{{ formatMoney(statement.returns.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-xs">
                                ৳{{ formatMoney(statement.returns.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold text-ink-600">
                                    {{ statement.returns.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-ink-600 text-[11px]">Attributed in return-date period</td>
                        </tr>

                        <!-- Line D (Subtotal) -->
                        <tr class="bg-sunken/60 font-bold">
                            <td class="py-2.5 px-4 text-teal-800 text-center">D</td>
                            <td class="py-2.5 px-4 text-teal-900">NET SALES</td>
                            <td class="py-2.5 px-4 text-right font-mono text-teal-900">
                                ৳{{ formatMoney(statement.net_sales.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-ink-700">
                                ৳{{ formatMoney(statement.net_sales.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-xs">
                                {{ (statement.net_sales.variance?.diff ?? 0) >= 0 ? '+' : '' }}৳{{ formatMoney(statement.net_sales.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold" :class="(statement.net_sales.variance?.diff ?? 0) >= 0 ? 'bg-[#E7F4EA] text-[#14602C]' : 'bg-[#FDECEA] text-[#8E1B12]'">
                                    {{ statement.net_sales.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-ink-700 font-semibold text-[11px]">A − B − C</td>
                        </tr>

                        <!-- Line E -->
                        <tr class="hover:bg-teal-50/30">
                            <td class="py-2.5 px-4 font-bold text-teal-700 text-center">E</td>
                            <td class="py-2.5 px-4">+ Delivery charges collected from customers</td>
                            <td
                                class="py-2.5 px-4 text-right font-mono text-ink-700 cursor-pointer underline decoration-dotted hover:text-teal-900"
                                @click="openDrilldown('delivery_charges')"
                            >
                                ৳{{ formatMoney(statement.delivery_charges.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-ink-600">
                                ৳{{ formatMoney(statement.delivery_charges.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-xs">
                                ৳{{ formatMoney(statement.delivery_charges.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold text-ink-600">
                                    {{ statement.delivery_charges.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-ink-600 text-[11px]">Delivery fees billed to customers</td>
                        </tr>

                        <!-- Line F (Total Revenue) -->
                        <tr class="bg-teal-100/60 font-bold text-teal-900 border-t border-b border-teal-300">
                            <td class="py-3 px-4 text-center text-teal-800">F</td>
                            <td class="py-3 px-4 text-teal-900 uppercase tracking-wider">TOTAL REVENUE</td>
                            <td class="py-3 px-4 text-right font-mono text-sm text-teal-900">
                                ৳{{ formatMoney(statement.total_revenue.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-3 px-4 text-right font-mono text-ink-800">
                                ৳{{ formatMoney(statement.total_revenue.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-3 px-4 text-right font-mono text-xs">
                                {{ (statement.total_revenue.variance?.diff ?? 0) >= 0 ? '+' : '' }}৳{{ formatMoney(statement.total_revenue.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-3 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold" :class="(statement.total_revenue.variance?.diff ?? 0) >= 0 ? 'bg-[#E7F4EA] text-[#14602C]' : 'bg-[#FDECEA] text-[#8E1B12]'">
                                    {{ statement.total_revenue.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-teal-800 font-bold text-[11px]">D + E</td>
                        </tr>

                        <!-- SECTION 2: COST OF GOODS SOLD -->
                        <tr class="bg-teal-50/50 font-bold text-teal-900">
                            <td colspan="7" class="py-2 px-4 uppercase tracking-wider text-[11px]">
                                2. Cost of Goods Sold
                            </td>
                        </tr>

                        <!-- Line G -->
                        <tr class="hover:bg-teal-50/30">
                            <td class="py-2.5 px-4 font-bold text-teal-700 text-center">G</td>
                            <td class="py-2.5 px-4">Cost of items sold (COGS)</td>
                            <td
                                class="py-2.5 px-4 text-right font-mono text-[#8E1B12] cursor-pointer underline decoration-dotted hover:text-red-900"
                                @click="openDrilldown('cogs')"
                            >
                                ৳{{ formatMoney(statement.cogs.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-ink-600">
                                ৳{{ formatMoney(statement.cogs.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-xs">
                                ৳{{ formatMoney(statement.cogs.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold text-ink-600">
                                    {{ statement.cogs.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-ink-600 text-[11px]">Qty × snapshot unit cost − restocked returns</td>
                        </tr>

                        <!-- Line H (Gross Profit) -->
                        <tr class="bg-sunken font-bold text-ink-900 border-t border-b border-line-300">
                            <td class="py-3 px-4 text-center text-teal-800">H</td>
                            <td class="py-3 px-4 uppercase tracking-wider">
                                GROSS PROFIT
                                <span class="ml-2 font-normal text-xs text-ink-600">
                                    (Gross Margin: {{ report.gross_margin_pct.toFixed(1) }}%)
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-sm text-teal-800">
                                ৳{{ formatMoney(statement.gross_profit.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-3 px-4 text-right font-mono text-ink-700">
                                ৳{{ formatMoney(statement.gross_profit.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-3 px-4 text-right font-mono text-xs">
                                {{ (statement.gross_profit.variance?.diff ?? 0) >= 0 ? '+' : '' }}৳{{ formatMoney(statement.gross_profit.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-3 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold" :class="(statement.gross_profit.variance?.diff ?? 0) >= 0 ? 'bg-[#E7F4EA] text-[#14602C]' : 'bg-[#FDECEA] text-[#8E1B12]'">
                                    {{ statement.gross_profit.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-[11px]">F − G (Gross Margin: H ÷ F)</td>
                        </tr>

                        <!-- SECTION 3: OPERATING EXPENSES -->
                        <tr class="bg-teal-50/50 font-bold text-teal-900">
                            <td colspan="7" class="py-2 px-4 uppercase tracking-wider text-[11px]">
                                3. Operating Expenses
                            </td>
                        </tr>

                        <!-- Lines I1 to O -->
                        <tr
                            v-for="(exp, expKey) in [
                                { key: 'facebook_ads', code: 'I1', label: 'Facebook Ads', note: `ROAS: ${report.marketing.roas_formatted}` },
                                { key: 'other_marketing', code: 'I2', label: 'Other marketing', note: 'Promotions & offline marketing' },
                                { key: 'courier', code: 'J', label: 'Courier charges', note: 'Pathao charges & return delivery fees' },
                                { key: 'salaries', code: 'K', label: 'Salaries (earned for period)', note: 'Accrued staff payable (earned, not paid)' },
                                {
                                    key: 'wages',
                                    code: 'L',
                                    label: 'Wages (earned for period)',
                                    note: report.cost_includes_labour ? 'Excluded: Cost price includes labour = Yes' : 'Accrued wage earnings'
                                },
                                { key: 'packaging', code: 'M', label: 'Packaging', note: 'Boxes, cartons, tags, bags' },
                                { key: 'rent_utilities', code: 'N', label: 'Rent & utilities', note: 'Rent, electricity, internet, gas/water' },
                                { key: 'other', code: 'O', label: 'Other expenses', note: 'Transport, tools, maintenance, miscellaneous' },
                            ]"
                            :key="exp.key"
                            class="hover:bg-teal-50/30"
                        >
                            <td class="py-2.5 px-4 font-bold text-teal-700 text-center">{{ exp.code }}</td>
                            <td class="py-2.5 px-4">
                                {{ exp.label }}
                                <span v-if="exp.key === 'wages' && report.cost_includes_labour" class="ml-1 text-[10px] text-amber-700 font-bold">
                                    [EXCLUDED]
                                </span>
                            </td>
                            <td
                                class="py-2.5 px-4 text-right font-mono text-ink-700 cursor-pointer underline decoration-dotted hover:text-teal-900"
                                @click="openDrilldown(exp.key)"
                            >
                                ৳{{ formatMoney(statement.expenses[exp.key]?.amount ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-ink-600">
                                ৳{{ formatMoney(statement.expenses[exp.key]?.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-right font-mono text-xs">
                                ৳{{ formatMoney(statement.expenses[exp.key]?.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-2.5 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold text-ink-600">
                                    {{ statement.expenses[exp.key]?.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-ink-600 text-[11px]">{{ exp.note }}</td>
                        </tr>

                        <!-- Line P (Total OPEX) -->
                        <tr class="bg-sunken font-bold text-ink-900 border-t border-b border-line-300">
                            <td class="py-3 px-4 text-center text-teal-800">P</td>
                            <td class="py-3 px-4 uppercase tracking-wider">TOTAL OPERATING EXPENSES</td>
                            <td
                                class="py-3 px-4 text-right font-mono text-sm text-[#8E1B12] cursor-pointer hover:underline"
                                @click="openDrilldown('other_expenses')"
                            >
                                ৳{{ formatMoney(statement.expenses.total.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-3 px-4 text-right font-mono text-ink-700">
                                ৳{{ formatMoney(statement.expenses.total.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-3 px-4 text-right font-mono text-xs">
                                ৳{{ formatMoney(statement.expenses.total.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-3 px-4 text-center">
                                <span class="px-1.5 py-0.5 text-[11px] font-bold text-ink-600">
                                    {{ statement.expenses.total.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-[11px]">I1 + I2 + J + K + L + M + N + O</td>
                        </tr>

                        <!-- SECTION 4: NET PROFIT / (LOSS) (Line Q) -->
                        <tr class="bg-teal-100 font-bold border-t-2 border-b-2 border-teal-700">
                            <td class="py-4 px-4 text-center text-lg text-teal-900">Q</td>
                            <td class="py-4 px-4 text-sm uppercase tracking-wider text-teal-950">
                                NET PROFIT / (LOSS)
                                <span class="ml-2 font-semibold text-xs text-teal-800">
                                    (Net Margin: {{ report.net_margin_pct.toFixed(1) }}%)
                                </span>
                            </td>
                            <td
                                class="py-4 px-4 text-right font-mono text-base font-bold cursor-pointer"
                                :class="report.net_profit >= 0 ? 'text-[#14602C]' : 'text-[#8E1B12]'"
                                @click="openDrilldown('gross_sales')"
                            >
                                ৳{{ formatMoney(statement.net_profit.amount) }}
                            </td>
                            <td v-if="filters.compare" class="py-4 px-4 text-right font-mono text-ink-800 text-sm">
                                ৳{{ formatMoney(statement.net_profit.previous ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-4 px-4 text-right font-mono text-xs font-bold" :class="(statement.net_profit.variance?.diff ?? 0) >= 0 ? 'text-[#14602C]' : 'text-[#8E1B12]'">
                                {{ (statement.net_profit.variance?.diff ?? 0) >= 0 ? '+' : '' }}৳{{ formatMoney(statement.net_profit.variance?.diff ?? 0) }}
                            </td>
                            <td v-if="filters.compare" class="py-4 px-4 text-center">
                                <span class="px-2 py-0.5 text-xs font-bold" :class="(statement.net_profit.variance?.diff ?? 0) >= 0 ? 'bg-[#E7F4EA] text-[#14602C]' : 'bg-[#FDECEA] text-[#8E1B12]'">
                                    {{ statement.net_profit.variance?.pct_formatted }}
                                </span>
                            </td>
                            <td class="py-4 px-4 font-bold text-teal-900 text-xs">H − P (Net Margin: Q ÷ F)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Label/Amount Rows View (< md) -->
            <div class="md:hidden divide-y divide-line-200">
                <!-- Mobile Section 1: Revenue -->
                <div class="p-3 bg-teal-50 font-bold text-teal-900 uppercase tracking-wider text-[11px]">
                    1. Revenue
                </div>

                <div class="p-3 space-y-1 hover:bg-page transition-colors cursor-pointer" @click="openDrilldown('gross_sales')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-teal-700 font-mono text-xs bg-teal-50 border border-teal-200 px-1.5 py-0.5">A</span>
                            <span class="text-xs font-semibold text-ink-900">Gross sales (invoice subtotals)</span>
                        </div>
                        <span class="font-mono font-bold text-xs text-teal-700">৳{{ formatMoney(statement.gross_sales.amount) }}</span>
                    </div>
                    <div v-if="filters.compare" class="text-[10px] text-ink-500 font-mono pl-7 flex justify-between">
                        <span>Prev: ৳{{ formatMoney(statement.gross_sales.previous ?? 0) }}</span>
                        <span :class="(statement.gross_sales.variance?.diff ?? 0) >= 0 ? 'text-[#14602C]' : 'text-[#8E1B12]'" class="font-bold">
                            {{ statement.gross_sales.variance?.pct_formatted }}
                        </span>
                    </div>
                </div>

                <div class="p-3 space-y-1 hover:bg-page transition-colors cursor-pointer" @click="openDrilldown('discounts')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-teal-700 font-mono text-xs bg-teal-50 border border-teal-200 px-1.5 py-0.5">B</span>
                            <span class="text-xs font-semibold text-ink-900">− Discounts</span>
                        </div>
                        <span class="font-mono font-semibold text-xs text-ink-800">৳{{ formatMoney(statement.discounts.amount) }}</span>
                    </div>
                    <div v-if="filters.compare" class="text-[10px] text-ink-500 font-mono pl-7 flex justify-between">
                        <span>Prev: ৳{{ formatMoney(statement.discounts.previous ?? 0) }}</span>
                        <span :class="(statement.discounts.variance?.diff ?? 0) <= 0 ? 'text-[#14602C]' : 'text-[#8E1B12]'" class="font-bold">
                            {{ statement.discounts.variance?.pct_formatted }}
                        </span>
                    </div>
                </div>

                <div class="p-3 space-y-1 hover:bg-page transition-colors cursor-pointer" @click="openDrilldown('returns')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-teal-700 font-mono text-xs bg-teal-50 border border-teal-200 px-1.5 py-0.5">C</span>
                            <span class="text-xs font-semibold text-ink-900">− Sales returns / cancellations</span>
                        </div>
                        <span class="font-mono font-semibold text-xs text-ink-800">৳{{ formatMoney(statement.returns.amount) }}</span>
                    </div>
                    <div v-if="filters.compare" class="text-[10px] text-ink-500 font-mono pl-7 flex justify-between">
                        <span>Prev: ৳{{ formatMoney(statement.returns.previous ?? 0) }}</span>
                        <span :class="(statement.returns.variance?.diff ?? 0) <= 0 ? 'text-[#14602C]' : 'text-[#8E1B12]'" class="font-bold">
                            {{ statement.returns.variance?.pct_formatted }}
                        </span>
                    </div>
                </div>

                <div class="p-3 bg-sunken space-y-1 cursor-pointer font-bold" @click="openDrilldown('gross_sales')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-teal-800 font-mono text-xs bg-white border border-teal-300 px-1.5 py-0.5">D</span>
                            <span class="text-xs text-teal-900">Net sales (A − B − C)</span>
                        </div>
                        <span class="font-mono text-xs text-teal-800">৳{{ formatMoney(statement.net_sales.amount) }}</span>
                    </div>
                    <div v-if="filters.compare" class="text-[10px] text-ink-500 font-mono pl-7 flex justify-between">
                        <span>Prev: ৳{{ formatMoney(statement.net_sales.previous ?? 0) }}</span>
                        <span :class="(statement.net_sales.variance?.diff ?? 0) >= 0 ? 'text-[#14602C]' : 'text-[#8E1B12]'">
                            {{ statement.net_sales.variance?.pct_formatted }}
                        </span>
                    </div>
                </div>

                <div class="p-3 space-y-1 hover:bg-page transition-colors cursor-pointer" @click="openDrilldown('delivery_charge')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-teal-700 font-mono text-xs bg-teal-50 border border-teal-200 px-1.5 py-0.5">E</span>
                            <span class="text-xs font-semibold text-ink-900">+ Delivery charges collected</span>
                        </div>
                        <span class="font-mono font-semibold text-xs text-ink-800">৳{{ formatMoney(statement.delivery_charges?.amount ?? 0) }}</span>
                    </div>
                </div>

                <div class="p-3 bg-teal-50 space-y-1 cursor-pointer font-extrabold border-t border-b border-teal-200" @click="openDrilldown('gross_sales')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-teal-800 font-mono text-xs bg-white border border-teal-300 px-1.5 py-0.5">F</span>
                            <span class="text-xs text-teal-900">Total revenue (D + E)</span>
                        </div>
                        <span class="font-mono text-sm text-teal-900">৳{{ formatMoney(statement.total_revenue?.amount ?? 0) }}</span>
                    </div>
                </div>

                <!-- Mobile Section 2: COGS & Gross Profit -->
                <div class="p-3 bg-teal-50 font-bold text-teal-900 uppercase tracking-wider text-[11px]">
                    2. Cost of Items Sold & Margin
                </div>

                <div class="p-3 space-y-1 hover:bg-page transition-colors cursor-pointer" @click="openDrilldown('cogs')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-teal-700 font-mono text-xs bg-teal-50 border border-teal-200 px-1.5 py-0.5">G</span>
                            <span class="text-xs font-semibold text-ink-900">Cost of items sold (COGS)</span>
                        </div>
                        <span class="font-mono font-semibold text-xs text-[#8E1B12]">৳{{ formatMoney(statement.cogs?.amount ?? 0) }}</span>
                    </div>
                </div>

                <div class="p-3 bg-sunken space-y-1 cursor-pointer font-bold border-t border-b border-line-300" @click="openDrilldown('cogs')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-teal-800 font-mono text-xs bg-white border border-teal-300 px-1.5 py-0.5">H</span>
                            <div>
                                <span class="text-xs text-ink-900 font-extrabold">Gross profit (F − G)</span>
                                <div class="text-[10px] text-ink-500 font-normal">Margin: {{ (report.gross_margin_pct ?? 0).toFixed(1) }}%</div>
                            </div>
                        </div>
                        <span class="font-mono text-sm text-teal-800">৳{{ formatMoney(statement.gross_profit?.amount ?? 0) }}</span>
                    </div>
                </div>

                <!-- Mobile Section 3: OPEX -->
                <div class="p-3 bg-teal-50 font-bold text-teal-900 uppercase tracking-wider text-[11px]">
                    3. Operating Expenses (OPEX)
                </div>

                <div
                    v-for="line in [
                        { code: 'I1', label: 'Facebook Ads', key: 'facebook_ads', amount: statement.expenses?.facebook_ads?.amount ?? 0 },
                        { code: 'I2', label: 'Other Marketing', key: 'other_marketing', amount: statement.expenses?.other_marketing?.amount ?? 0 },
                        { code: 'J', label: 'Courier charges', key: 'courier', amount: statement.expenses?.courier?.amount ?? 0 },
                        { code: 'K', label: 'Salaries earned', key: 'salaries', amount: statement.expenses?.salaries?.amount ?? 0 },
                        { code: 'L', label: 'Wages earned', key: 'wages', amount: statement.expenses?.wages?.amount ?? 0 },
                        { code: 'M', label: 'Packaging', key: 'packaging', amount: statement.expenses?.packaging?.amount ?? 0 },
                        { code: 'N', label: 'Rent & Utilities', key: 'rent_utilities', amount: statement.expenses?.rent_utilities?.amount ?? 0 },
                        { code: 'O', label: 'Other Operating Expenses', key: 'other', amount: statement.expenses?.other?.amount ?? 0 },
                    ]"
                    :key="line.code"
                    class="p-3 space-y-1 hover:bg-page transition-colors cursor-pointer"
                    @click="openDrilldown(line.key)"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-teal-700 font-mono text-xs bg-teal-50 border border-teal-200 px-1.5 py-0.5">{{ line.code }}</span>
                            <span class="text-xs font-semibold text-ink-900">{{ line.label }}</span>
                        </div>
                        <span class="font-mono text-xs text-ink-800">৳{{ formatMoney(line.amount) }}</span>
                    </div>
                </div>

                <div class="p-3 bg-sunken space-y-1 cursor-pointer font-bold border-t border-b border-line-300" @click="openDrilldown('facebook_ads')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-teal-800 font-mono text-xs bg-white border border-teal-300 px-1.5 py-0.5">P</span>
                            <span class="text-xs text-teal-900">Total OPEX (I + J + K + L + M + N + O)</span>
                        </div>
                        <span class="font-mono text-xs text-teal-800">৳{{ formatMoney(statement.expenses?.total?.amount ?? 0) }}</span>
                    </div>
                </div>

                <!-- Mobile Section 4: Net Profit -->
                <div class="p-3 bg-teal-100/70 border-t-2 border-b-2 border-teal-300 font-extrabold cursor-pointer" @click="openDrilldown('gross_sales')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-teal-900 font-mono text-xs bg-white border border-teal-400 px-1.5 py-0.5">Q</span>
                            <div>
                                <span class="text-xs text-teal-950 font-black">Net profit (H − P)</span>
                                <div class="text-[10px] text-ink-600 font-normal">Net Margin: {{ report.net_margin_pct.toFixed(1) }}%</div>
                            </div>
                        </div>
                        <span
                            class="font-mono text-base font-bold"
                            :class="report.net_profit >= 0 ? 'text-[#14602C]' : 'text-[#8E1B12]'"
                        >
                            ৳{{ formatMoney(statement.net_profit.amount) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PRD §11.2 MEMO SECTION (Informational - Not in Profit Calculation) -->
        <div class="mb-8 border border-line-200 bg-surface">
            <div class="border-b border-line-200 px-6 py-4">
                <div class="flex items-center space-x-2">
                    <span class="bg-[#2B3A38] text-white text-[10px] font-bold px-2 py-0.5 uppercase tracking-wider">
                        Memo
                    </span>
                    <h2 class="text-sm font-bold text-ink-900 uppercase tracking-wider">
                        Informational Memo Section (PRD §11.2)
                    </h2>
                </div>
                <p class="text-xs text-ink-600 mt-1">
                    Recorded for financial balance oversight & asset tracking; explicitly excluded from P&L net profit calculation.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-6 border-b border-line-200">
                <!-- Leather Activity -->
                <div class="border border-line-200 bg-page p-4">
                    <div class="flex items-center justify-between border-b border-line-200 pb-2 mb-3">
                        <span class="text-xs font-bold text-ink-900">Leather Inventory</span>
                        <AppIcon name="database" :size="16" class="text-teal-700" />
                    </div>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between cursor-pointer hover:text-teal-700" @click="openDrilldown('leather_purchased')">
                            <span class="text-ink-600">Purchased in period:</span>
                            <span class="font-mono font-bold">
                                ৳{{ formatMoney(report.memo.leather.purchased_cost) }}
                                <span class="text-[10px] text-ink-500 font-normal">({{ report.memo.leather.purchased_sqft }} sqft)</span>
                            </span>
                        </div>
                        <div class="flex justify-between cursor-pointer hover:text-teal-700" @click="openDrilldown('leather_consumed')">
                            <span class="text-ink-600">Consumed in period:</span>
                            <span class="font-mono font-bold">
                                ৳{{ formatMoney(report.memo.leather.consumed_value) }}
                                <span class="text-[10px] text-ink-500 font-normal">({{ report.memo.leather.consumed_sqft }} sqft)</span>
                            </span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-line-200">
                            <span class="text-ink-700 font-semibold">Closing Leather Stock:</span>
                            <span class="font-mono font-bold text-teal-800">
                                ৳{{ formatMoney(report.memo.closing_stock.leather) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Accessories Activity -->
                <div class="border border-line-200 bg-page p-4">
                    <div class="flex items-center justify-between border-b border-line-200 pb-2 mb-3">
                        <span class="text-xs font-bold text-ink-900">Accessories Inventory</span>
                        <AppIcon name="archive" :size="16" class="text-teal-700" />
                    </div>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between cursor-pointer hover:text-teal-700" @click="openDrilldown('accessory_purchased')">
                            <span class="text-ink-600">Purchased in period:</span>
                            <span class="font-mono font-bold">
                                ৳{{ formatMoney(report.memo.accessories.purchased_cost) }}
                                <span class="text-[10px] text-ink-500 font-normal">({{ report.memo.accessories.purchased_qty }} pcs)</span>
                            </span>
                        </div>
                        <div class="flex justify-between cursor-pointer hover:text-teal-700" @click="openDrilldown('accessory_consumed')">
                            <span class="text-ink-600">Consumed in period:</span>
                            <span class="font-mono font-bold">
                                ৳{{ formatMoney(report.memo.accessories.consumed_value) }}
                                <span class="text-[10px] text-ink-500 font-normal">({{ report.memo.accessories.consumed_qty }} pcs)</span>
                            </span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-line-200">
                            <span class="text-ink-700 font-semibold">Closing Accessories:</span>
                            <span class="font-mono font-bold text-teal-800">
                                ৳{{ formatMoney(report.memo.closing_stock.accessories) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Total Closing Inventory Valuation -->
                <div class="border border-line-200 bg-page p-4">
                    <div class="flex items-center justify-between border-b border-line-200 pb-2 mb-3">
                        <span class="text-xs font-bold text-ink-900">Total Inventory Asset</span>
                        <AppIcon name="box" :size="16" class="text-teal-700" />
                    </div>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between cursor-pointer hover:text-teal-700" @click="openDrilldown('closing_stock_products')">
                            <span class="text-ink-600">Finished Products:</span>
                            <span class="font-mono font-bold">৳{{ formatMoney(report.memo.closing_stock.finished_products) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Raw Leather:</span>
                            <span class="font-mono font-bold">৳{{ formatMoney(report.memo.closing_stock.leather) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Accessories:</span>
                            <span class="font-mono font-bold">৳{{ formatMoney(report.memo.closing_stock.accessories) }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-line-200 font-bold text-teal-800">
                            <span>Total Closing Valuation:</span>
                            <span class="font-mono text-sm">৳{{ formatMoney(report.memo.closing_stock.total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Receivables & Payroll Balances -->
                <div class="border border-line-200 bg-page p-4">
                    <div class="flex items-center justify-between border-b border-line-200 pb-2 mb-3">
                        <span class="text-xs font-bold text-ink-900">Receivables & Balances</span>
                        <AppIcon name="user-check" :size="16" class="text-teal-700" />
                    </div>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between cursor-pointer hover:text-teal-700" @click="openDrilldown('cod_receivable')">
                            <span class="text-ink-600">COD with Pathao (delivered):</span>
                            <span class="font-mono font-bold text-amber-700">৳{{ formatMoney(report.memo.cod_receivable) }}</span>
                        </div>
                        <div class="flex justify-between cursor-pointer hover:text-teal-700" @click="openDrilldown('customer_dues')">
                            <span class="text-ink-600">Customer Dues:</span>
                            <span class="font-mono font-bold text-[#8E1B12]">৳{{ formatMoney(report.memo.customer_dues) }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-line-200">
                            <span class="text-ink-600">Payroll Payable (Owed):</span>
                            <span class="font-mono font-bold">৳{{ formatMoney(report.memo.payroll_balances.owed) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Payroll Advances:</span>
                            <span class="font-mono font-bold text-purple-700">(৳{{ formatMoney(report.memo.payroll_balances.advances) }})</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash Movement Summary Table (PRD §11.2 & Schema §8.7) -->
            <div class="p-6 bg-surface">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-ink-900">
                        Cash Summary: Reconciling Cash In vs Cash Out
                    </h3>
                    <span class="text-[11px] text-ink-500 italic">
                        Answers: "Where did my cash go even though P&L shows profit?"
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border border-line-200 p-4 bg-page">
                    <!-- Cash In -->
                    <div class="space-y-1.5 text-xs">
                        <div class="font-bold text-[#14602C] border-b border-line-200 pb-1">
                            Actual Cash Inflow:
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Customer Payments (non-COD):</span>
                            <span class="font-mono font-semibold">৳{{ formatMoney(report.memo.cash_summary.cash_in.customer_payments) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Pathao COD Settlements:</span>
                            <span class="font-mono font-semibold">৳{{ formatMoney(report.memo.cash_summary.cash_in.cod_settlements) }}</span>
                        </div>
                        <div class="flex justify-between pt-1 border-t border-line-200 font-bold text-[#14602C]">
                            <span>Total Cash Received:</span>
                            <span class="font-mono">৳{{ formatMoney(report.memo.cash_summary.cash_in.total) }}</span>
                        </div>
                    </div>

                    <!-- Cash Out -->
                    <div class="space-y-1.5 text-xs">
                        <div class="font-bold text-[#8E1B12] border-b border-line-200 pb-1">
                            Actual Cash Outflow:
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Expenses paid (excl COD deductions):</span>
                            <span class="font-mono">৳{{ formatMoney(report.memo.cash_summary.cash_out.expenses) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Salary payments disbursed:</span>
                            <span class="font-mono">৳{{ formatMoney(report.memo.cash_summary.cash_out.salaries) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Worker payments disbursed:</span>
                            <span class="font-mono">৳{{ formatMoney(report.memo.cash_summary.cash_out.wages) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Raw material purchases paid:</span>
                            <span class="font-mono">৳{{ formatMoney(report.memo.cash_summary.cash_out.material_purchases) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-600">Customer return refunds:</span>
                            <span class="font-mono">৳{{ formatMoney(report.memo.cash_summary.cash_out.refunds) }}</span>
                        </div>
                        <div class="flex justify-between pt-1 border-t border-line-200 font-bold text-[#8E1B12]">
                            <span>Total Cash Disbursed:</span>
                            <span class="font-mono">৳{{ formatMoney(report.memo.cash_summary.cash_out.total) }}</span>
                        </div>
                    </div>

                    <!-- Net Movement -->
                    <div class="flex flex-col justify-between border-t md:border-t-0 md:border-l border-line-200 pt-3 md:pt-0 md:pl-4 text-xs">
                        <div>
                            <div class="font-bold text-ink-900 border-b border-line-200 pb-1">
                                Net Cash Movement:
                            </div>
                            <p class="text-[11px] text-ink-600 mt-1">
                                Cash flow accounts for material purchase outlays & payroll settlements which do not hit the P&L as direct operating expenses.
                            </p>
                        </div>

                        <div class="bg-surface p-3 border border-line-300 mt-2">
                            <div class="text-[10px] uppercase font-bold text-ink-500">Net Period Cash Movement</div>
                            <div
                                class="text-xl font-bold font-mono mt-0.5"
                                :class="report.memo.cash_summary.net_movement >= 0 ? 'text-[#14602C]' : 'text-[#8E1B12]'"
                            >
                                {{ report.memo.cash_summary.net_movement >= 0 ? '+' : '' }}৳{{ formatMoney(report.memo.cash_summary.net_movement) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PRD §11.4 Visuals & Analytics Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- 1. Monthly Revenue vs Expenses vs Net Profit Bar Chart -->
            <div class="lg:col-span-2 border border-line-200 bg-surface p-5">
                <div class="flex items-center justify-between border-b border-line-200 pb-3 mb-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-ink-900">
                        Monthly Financial Trend (Last 6 Months)
                    </h3>
                    <span class="text-xs text-ink-500">Revenue vs OPEX vs Profit</span>
                </div>
                <div class="h-64">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <!-- 2. Operating Expenses Breakdown -->
            <div class="border border-line-200 bg-surface p-5">
                <div class="flex items-center justify-between border-b border-line-200 pb-3 mb-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-ink-900">
                        Expense Breakdown
                    </h3>
                    <span class="text-xs text-ink-500">Share of Total OPEX</span>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="exp in report.expense_breakdown"
                        :key="exp.key"
                        class="text-xs"
                    >
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-semibold text-ink-800">
                                <span class="font-bold text-teal-700 mr-1">[{{ exp.code }}]</span>
                                {{ exp.label }}
                            </span>
                            <span class="font-mono text-ink-900 font-bold">
                                ৳{{ formatMoney(exp.amount) }}
                                <span class="text-[10px] text-ink-500 font-normal">({{ exp.percentage }}%)</span>
                            </span>
                        </div>
                        <div class="w-full bg-sunken h-2">
                            <div
                                class="h-2 transition-all"
                                :style="{ width: `${exp.percentage}%`, backgroundColor: exp.color }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 5 Profitable Products (PRD §11.4) -->
        <div class="border border-line-200 bg-surface mb-8">
            <div class="border-b border-line-200 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-ink-900">
                        Top 5 Profitable Products (PRD §11.4)
                    </h3>
                    <p class="text-xs text-ink-600 mt-0.5">
                        Ranked strictly by Gross Profit (Revenue − Snapshot COGS) in this period.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-sunken text-ink-700 uppercase tracking-wider border-b border-line-200">
                            <th scope="col" class="py-2.5 px-4 font-bold">Product Name</th>
                            <th scope="col" class="py-2.5 px-4 font-bold">SKU</th>
                            <th scope="col" class="py-2.5 px-4 text-center font-bold">Qty Sold</th>
                            <th scope="col" class="py-2.5 px-4 text-right font-bold">Revenue</th>
                            <th scope="col" class="py-2.5 px-4 text-right font-bold">Snapshot COGS</th>
                            <th scope="col" class="py-2.5 px-4 text-right font-bold text-teal-800">Gross Profit</th>
                            <th scope="col" class="py-2.5 px-4 text-center font-bold">Margin %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-200">
                        <tr
                            v-for="p in report.top_products"
                            :key="p.product_id ?? p.name"
                            class="hover:bg-teal-50/30"
                        >
                            <td class="py-2.5 px-4 font-semibold text-ink-900">{{ p.name }}</td>
                            <td class="py-2.5 px-4 font-mono text-ink-600">{{ p.sku }}</td>
                            <td class="py-2.5 px-4 text-center font-mono">{{ p.qty_sold }}</td>
                            <td class="py-2.5 px-4 text-right font-mono">৳{{ formatMoney(p.revenue) }}</td>
                            <td class="py-2.5 px-4 text-right font-mono text-[#8E1B12]">৳{{ formatMoney(p.cogs) }}</td>
                            <td class="py-2.5 px-4 text-right font-mono font-bold text-[#14602C]">
                                ৳{{ formatMoney(p.gross_profit) }}
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <span class="bg-[#E7F4EA] text-[#14602C] px-2 py-0.5 font-bold text-[11px]">
                                    {{ p.profit_margin.toFixed(1) }}%
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!report.top_products || report.top_products.length === 0">
                            <td colspan="7" class="py-6 text-center text-ink-500">
                                No product sales recorded in the selected period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Interactive Drill-Down Drawer (SideDrawer.vue) -->
        <SideDrawer
            :open="drawerOpen"
            :title="drawerTitle"
            :subtitle="drawerSubtitle"
            width-class="sm:max-w-[560px]"
            @close="drawerOpen = false"
        >
            <div class="p-6">
                <!-- Loading State -->
                <div v-if="drawerLoading" class="py-12 text-center text-ink-600 text-xs">
                    <span class="inline-block animate-spin mr-2">⟳</span> Loading records from database...
                </div>

                <!-- Empty State -->
                <EmptyState
                    v-else-if="drawerRecords.length === 0"
                    title="No Records Found"
                    description="No matching transaction rows exist for this period."
                    icon="receipt"
                />

                <!-- Records List -->
                <div v-else class="space-y-3">
                    <div v-if="drawerTotal !== null" class="border border-teal-200 bg-teal-50 p-3 mb-4 flex justify-between items-center">
                        <span class="text-xs font-bold text-teal-900 uppercase">Filtered Total:</span>
                        <span class="text-sm font-bold font-mono text-teal-800">৳{{ formatMoney(drawerTotal) }}</span>
                    </div>

                    <!-- Individual Records -->
                    <div
                        v-for="(rec, idx) in drawerRecords"
                        :key="idx"
                        class="border border-line-200 bg-surface p-3 text-xs space-y-1.5 hover:border-line-400 transition-colors"
                    >
                        <!-- Sales / Invoices -->
                        <template v-if="rec.invoice_no">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-teal-700 font-mono">{{ rec.invoice_no }}</span>
                                <span class="text-ink-500 text-[11px]">{{ rec.date }}</span>
                            </div>
                            <div class="flex justify-between text-ink-700">
                                <span>{{ rec.customer || 'Customer' }}</span>
                                <span class="font-mono font-bold">
                                    ৳{{ formatMoney(rec.subtotal ?? rec.grand_total ?? rec.return_value ?? rec.total ?? 0) }}
                                </span>
                            </div>
                            <div v-if="rec.phone" class="text-[11px] text-ink-500 font-mono">
                                {{ rec.phone }}
                            </div>
                        </template>

                        <!-- Expenses -->
                        <template v-else-if="rec.category">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-ink-900">{{ rec.category }}</span>
                                <span class="font-mono font-bold text-[#8E1B12]">৳{{ formatMoney(rec.amount) }}</span>
                            </div>
                            <div class="flex justify-between text-ink-600 text-[11px]">
                                <span>Vendor: {{ rec.vendor }}</span>
                                <span>{{ rec.date }}</span>
                            </div>
                            <div v-if="rec.note" class="text-[11px] text-ink-500 italic">
                                {{ rec.note }}
                            </div>
                        </template>

                        <!-- Salaries -->
                        <template v-else-if="rec.employee">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-ink-900">{{ rec.employee }}</span>
                                <span class="font-mono font-bold text-teal-800">৳{{ formatMoney(rec.payable) }}</span>
                            </div>
                            <div class="flex justify-between text-ink-600 text-[11px]">
                                <span>Period: {{ rec.period }}</span>
                                <span>Gross: ৳{{ formatMoney(rec.gross) }}</span>
                            </div>
                        </template>

                        <!-- Wages -->
                        <template v-else-if="rec.worker">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-ink-900">{{ rec.worker }}</span>
                                <span class="font-mono font-bold text-ink-900">৳{{ formatMoney(rec.amount) }}</span>
                            </div>
                            <div class="flex justify-between text-ink-600 text-[11px]">
                                <span>Work Date: {{ rec.date }}</span>
                                <span class="capitalize">{{ rec.wage_basis }}</span>
                            </div>
                        </template>

                        <!-- Leather & Accessories -->
                        <template v-else-if="rec.item">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-ink-900">{{ rec.item }}</span>
                                <span class="font-mono font-bold">
                                    {{ rec.total_cost ? `৳${formatMoney(rec.total_cost)}` : `${rec.qty_sqft ?? rec.qty} units` }}
                                </span>
                            </div>
                            <div class="flex justify-between text-ink-600 text-[11px]">
                                <span>{{ rec.colour ? `Grade: ${rec.colour}` : (rec.supplier ? `Supplier: ${rec.supplier}` : '') }}</span>
                                <span>{{ rec.date }}</span>
                            </div>
                        </template>

                        <!-- Fallback / Products -->
                        <template v-else>
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-ink-900">{{ rec.name ?? 'Item' }}</span>
                                <span class="font-mono font-bold">
                                    ৳{{ formatMoney(rec.valuation ?? rec.total_cost ?? rec.amount ?? 0) }}
                                </span>
                            </div>
                            <div class="flex justify-between text-ink-600 text-[11px]">
                                <span v-if="rec.sku" class="font-mono">{{ rec.sku }}</span>
                                <span v-if="rec.stock_qty">Stock: {{ rec.stock_qty }}</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </SideDrawer>
    </div>
</template>
