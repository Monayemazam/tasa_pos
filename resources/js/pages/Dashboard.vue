<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import SideDrawer from '@/components/tasa/SideDrawer.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import { formatMoney } from '@/lib/formatters';

// Chart.js & vue-chartjs setup
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

interface BasicMetrics {
    today_sales: { count: number; amount: number };
    this_month_sales: { count: number; amount: number };
    courier_orders: { pending: number; failed: number };
    low_stock: {
        total_count: number;
        products_count: number;
        leather_count: number;
        accessories_count: number;
        products: any[];
        leather: any[];
        accessories: any[];
    };
    latest_invoices: any[];
    quick_actions: Array<{ label: string; url: string; icon: string }>;
}

interface FinancialMetrics {
    net_profit_this_month: number;
    gross_margin_pct: number;
    total_revenue_this_month: number;
    total_expenses_this_month: number;
    cod_to_receive: number;
    customer_dues: number;
    payroll_balances: {
        owed: number;
        overpaid: number;
    };
    facebook_ads: {
        ad_spend: number;
        sales: number;
        roas: number | null;
        roas_formatted: string;
        ad_spend_pct_sales: number;
    };
    six_month_trend: Array<{
        month_key: string;
        month_label: string;
        revenue: number;
        expenses: number;
        net_profit: number;
    }>;
}

interface Props {
    basic: BasicMetrics;
    financials: FinancialMetrics | null;
    canViewFinancials: boolean;
}

const props = defineProps<Props>();
const page = usePage();
const courierWarning = computed(() => (page.props as any).courier_warning);

// UI States
const chartViewMode = ref<'chart' | 'table'>('chart');
const lowStockDrawerOpen = ref(false);

// Chart Data & Options (Prompt 14 requirement 10: square bars, no gradients, <= 4 series, accessible)
const trendMonths = computed(() => props.financials?.six_month_trend ?? []);
const hasTrendData = computed(() => trendMonths.value.some(m => m.revenue > 0 || m.expenses > 0));

const chartData = computed(() => {
    const labels = trendMonths.value.map(m => m.month_label);
    const revenueData = trendMonths.value.map(m => m.revenue);
    const expenseData = trendMonths.value.map(m => m.expenses);
    const profitData = trendMonths.value.map(m => m.net_profit);

    return {
        labels,
        datasets: [
            {
                label: 'Revenue',
                data: revenueData,
                backgroundColor: '#0B6E5E', // Deep Teal
                borderRadius: 0, // Strict 0px square corners
            },
            {
                label: 'Expenses',
                data: expenseData,
                backgroundColor: '#D97706', // Amber
                borderRadius: 0,
            },
            {
                label: 'Net Profit',
                data: profitData,
                backgroundColor: '#0284C7', // Sky Blue
                borderRadius: 0,
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
        duration: 400,
    },
    plugins: {
        legend: {
            display: true,
            position: 'top' as const,
            labels: {
                boxWidth: 12,
                boxHeight: 12,
                borderRadius: 0,
                color: '#404040',
                font: {
                    family: 'Hanken Grotesk',
                    size: 11,
                    weight: 600,
                },
            },
        },
        tooltip: {
            backgroundColor: '#171717',
            titleFont: { family: 'Hanken Grotesk', size: 12, weight: 700 },
            bodyFont: { family: 'Hanken Grotesk', size: 11 },
            cornerRadius: 0,
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
                color: '#525252',
                font: { family: 'Hanken Grotesk', size: 11 },
            },
        },
        y: {
            beginAtZero: true,
            grid: { color: '#E5E7EB' },
            ticks: {
                color: '#525252',
                font: { family: 'Hanken Grotesk', size: 10 },
                callback: (val: any) => `৳${(Number(val) / 1000).toFixed(0)}k`,
            },
        },
    },
};
</script>

<template>
    <Head title="Dashboard Overview" />

    <div class="space-y-6">
        <PageHeader
            title="Dashboard Overview"
            :subtitle="canViewFinancials ? 'Live executive metrics, P&L accruals, and operational oversight' : 'Operational overview, sales tracking, and inventory status'"
        >
            <template #actions>
                <div class="flex items-center space-x-2">
                    <Link href="/sales/create">
                        <AppButton variant="primary" icon="plus">
                            New Sale
                        </AppButton>
                    </Link>
                    <Link href="/expenses">
                        <AppButton variant="secondary" icon="receipt">
                            Add Expense
                        </AppButton>
                    </Link>
                    <Link href="/inventory/leather">
                        <AppButton variant="secondary" icon="database">
                            Add Leather
                        </AppButton>
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- Courier Connection Problem Warning Banner -->
        <div
            v-if="courierWarning"
            class="mb-6 p-4 border flex items-center justify-between"
            :class="courierWarning.type === 'danger' ? 'bg-[#FCF6F6] border-[#F2BCB8] text-[#8E1B12]' : 'bg-[#FEFCE8] border-[#FEF08A] text-[#854D0E]'"
        >
            <div class="flex items-center space-x-3">
                <AppIcon name="alert-triangle" :size="20" />
                <div>
                    <span class="font-bold">Courier connection notice:</span>
                    <span class="ml-1 text-xs">{{ courierWarning.message }}</span>
                </div>
            </div>
            <Link v-if="courierWarning.link" :href="courierWarning.link">
                <AppButton size="sm" :variant="courierWarning.type === 'danger' ? 'danger' : 'secondary'">
                    Review Settings
                </AppButton>
            </Link>
        </div>

        <!-- OWNER FINANCIAL METRICS STRIP (PRD §11 & Prompt 14 requirement 8) -->
        <div v-if="canViewFinancials && financials" class="mb-6 space-y-4">
            <div class="flex items-center justify-between border-b border-line-200 pb-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-teal-800">
                    Financial Performance (Current Month)
                </h2>
                <Link href="/reports/sales" class="text-xs text-teal-700 hover:underline font-semibold">
                    Open Full Sales Report &rarr;
                </Link>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <StatCard
                    title="Net Profit (Month)"
                    :value="formatMoney(financials.net_profit_this_month, 0)"
                    :subtext="`Margin: ${financials.gross_margin_pct.toFixed(1)}%`"
                    :is-primary="true"
                    :is-negative="financials.net_profit_this_month < 0"
                />

                <StatCard
                    title="Total OPEX"
                    :value="formatMoney(financials.total_expenses_this_month, 0)"
                    subtext="Operating expenses & payroll"
                />

                <StatCard
                    title="COD To Receive"
                    :value="formatMoney(financials.cod_to_receive, 0)"
                    subtext="Delivered Pathao parcels"
                />

                <StatCard
                    title="Customer Dues"
                    :value="formatMoney(financials.customer_dues, 0)"
                    subtext="Excluding COD in transit"
                    :is-negative="financials.customer_dues > 0"
                />

                <StatCard
                    title="Payroll Balances"
                    :value="formatMoney(financials.payroll_balances.owed, 0)"
                    :subtext="`Overpaid / Advance: ${formatMoney(financials.payroll_balances.overpaid, 0)}`"
                />

                <!-- Facebook Ads Spend & ROAS (Zero ad spend yields '—', not infinity) -->
                <div class="bg-surface border border-line-200 p-5 relative overflow-hidden flex flex-col justify-between">
                    <span class="text-xs uppercase font-semibold tracking-wider text-ink-600 mb-2">
                        FB Ads & ROAS
                    </span>
                    <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-ink-900">
                        {{ financials.facebook_ads.roas_formatted }}
                    </div>
                    <div class="text-xs text-ink-500 font-medium mt-2">
                        Spend: {{ formatMoney(financials.facebook_ads.ad_spend, 0) }}
                        <span v-if="financials.facebook_ads.ad_spend > 0">
                            ({{ financials.facebook_ads.ad_spend_pct_sales }}% sales)
                        </span>
                    </div>
                </div>
            </div>

            <!-- Six-Month Trend Section with Chart & View as Table Toggle -->
            <div class="bg-surface border border-line-200 p-5 mt-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-line-200 gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-ink-900">
                            Six-Month Revenue vs Expenses Trend
                        </h3>
                        <p class="text-xs text-ink-600 mt-0.5">
                            Accrual basis financial trajectory across the last six calendar months.
                        </p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <AppButton
                            variant="secondary"
                            size="sm"
                            :icon="chartViewMode === 'chart' ? 'table' : 'bar-chart'"
                            @click="chartViewMode = chartViewMode === 'chart' ? 'table' : 'chart'"
                        >
                            {{ chartViewMode === 'chart' ? 'View as table' : 'View as chart' }}
                        </AppButton>
                    </div>
                </div>

                <!-- Empty State Check -->
                <div v-if="!hasTrendData" class="py-12">
                    <EmptyState
                        title="No historical trend data recorded yet"
                        description="Once sales and operational expenses are logged, your 6-month financial trajectory will appear here."
                    />
                </div>

                <!-- Visual Chart View -->
                <div v-else-if="chartViewMode === 'chart'" class="h-64 sm:h-72 w-full pt-4">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>

                <!-- Accessible Table View (Requirement 10) -->
                <div v-else class="overflow-x-auto pt-4">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-page text-ink-700 border-b border-line-200 uppercase font-semibold">
                            <tr>
                                <th scope="col" class="py-2.5 px-4">Month</th>
                                <th scope="col" class="py-2.5 px-4 text-right">Revenue (BDT)</th>
                                <th scope="col" class="py-2.5 px-4 text-right">Expenses (BDT)</th>
                                <th scope="col" class="py-2.5 px-4 text-right">Net Profit (BDT)</th>
                                <th scope="col" class="py-2.5 px-4 text-right">Net Margin %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-100">
                            <tr v-for="m in trendMonths" :key="m.month_key" class="hover:bg-page">
                                <td class="py-2.5 px-4 font-bold text-ink-900">{{ m.month_label }}</td>
                                <td class="py-2.5 px-4 text-right tabular-nums">{{ formatMoney(m.revenue) }}</td>
                                <td class="py-2.5 px-4 text-right tabular-nums text-ink-600">{{ formatMoney(m.expenses) }}</td>
                                <td
                                    class="py-2.5 px-4 text-right tabular-nums font-bold"
                                    :class="m.net_profit >= 0 ? 'text-teal-700' : 'text-danger'"
                                >
                                    {{ formatMoney(m.net_profit) }}
                                </td>
                                <td class="py-2.5 px-4 text-right tabular-nums">
                                    {{ m.revenue > 0 ? ((m.net_profit / m.revenue) * 100).toFixed(1) : 0 }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- BASIC OPERATIONAL KPI STRIP (Shown to all users: Staff + Owner) -->
        <div class="mb-6 space-y-4">
            <div class="flex items-center justify-between border-b border-line-200 pb-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-ink-700">
                    Store Operations & Stock Alerts
                </h2>
                <span class="text-xs text-ink-500">Live POS Counters</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard
                    title="Today's Sales"
                    :value="formatMoney(basic.today_sales.amount, 0)"
                    :subtext="`${basic.today_sales.count} sales recorded today`"
                    :is-primary="!canViewFinancials"
                />

                <StatCard
                    title="This Month"
                    :value="formatMoney(basic.this_month_sales.amount, 0)"
                    :subtext="`${basic.this_month_sales.count} invoices (current month)`"
                />

                <StatCard
                    title="Courier Orders"
                    :value="`${basic.courier_orders.pending} Pending`"
                    :subtext="`${basic.courier_orders.failed} failed shipments requiring retry`"
                    :is-negative="basic.courier_orders.failed > 0"
                />

                <div
                    class="bg-surface border border-line-200 p-5 relative overflow-hidden flex flex-col justify-between cursor-pointer hover:border-line-400 transition-colors"
                    @click="lowStockDrawerOpen = true"
                >
                    <span class="text-xs uppercase font-semibold tracking-wider text-ink-600 mb-2">
                        Low Stock Alerts
                    </span>
                    <div
                        class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight"
                        :class="basic.low_stock.total_count > 0 ? 'text-amber-600' : 'text-ink-900'"
                    >
                        {{ basic.low_stock.total_count }} Items
                    </div>
                    <div class="text-xs text-ink-500 font-medium mt-2 flex items-center justify-between">
                        <span>P: {{ basic.low_stock.products_count }} &bull; L: {{ basic.low_stock.leather_count }} &bull; A: {{ basic.low_stock.accessories_count }}</span>
                        <span class="text-teal-700 underline text-[11px]">Inspect</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECENT INVOICES TABLE (Prompt 14 requirement 6: latest 10 invoices) -->
        <div class="bg-surface border border-line-200 mb-6">
            <div class="p-4 border-b border-line-200 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-ink-900">Latest 10 Invoices</h3>
                    <p class="text-xs text-ink-600">Recently committed sales transactions across all channels.</p>
                </div>
                <Link href="/sales" class="text-xs text-teal-700 hover:underline font-semibold">
                    View All Sales &rarr;
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-page text-ink-700 border-b border-line-200 uppercase font-semibold">
                        <tr>
                            <th scope="col" class="py-2.5 px-4">Invoice #</th>
                            <th scope="col" class="py-2.5 px-4">Date</th>
                            <th scope="col" class="py-2.5 px-4">Customer</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Grand Total</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Paid</th>
                            <th scope="col" class="py-2.5 px-4 text-right">Due</th>
                            <th scope="col" class="py-2.5 px-4 text-center">Payment</th>
                            <th scope="col" class="py-2.5 px-4 text-center">Courier</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr v-for="inv in basic.latest_invoices" :key="inv.id" class="hover:bg-page transition-colors">
                            <td class="py-2.5 px-4 font-mono font-bold text-teal-700">
                                <Link :href="`/invoices?search=${inv.invoice_no}`" class="hover:underline">
                                    {{ inv.invoice_no }}
                                </Link>
                            </td>
                            <td class="py-2.5 px-4 text-ink-600">{{ inv.sale_date }}</td>
                            <td class="py-2.5 px-4 font-medium text-ink-900">
                                {{ inv.customer_name }}
                                <span class="text-[11px] text-ink-500 font-mono block">{{ inv.customer_phone }}</span>
                            </td>
                            <td class="py-2.5 px-4 text-right tabular-nums font-bold">{{ formatMoney(inv.grand_total) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums">{{ formatMoney(inv.paid_total) }}</td>
                            <td class="py-2.5 px-4 text-right tabular-nums font-semibold" :class="inv.due_amount > 0 ? 'text-danger' : 'text-ink-600'">
                                {{ formatMoney(inv.due_amount) }}
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <StatusBadge
                                    :label="inv.payment_status"
                                    :tone="inv.payment_status === 'paid' ? 'success' : (inv.payment_status === 'partial' ? 'warning' : 'danger')"
                                />
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <StatusBadge
                                    v-if="inv.courier_status !== 'none'"
                                    :label="inv.courier_status"
                                    :tone="inv.courier_status === 'delivered' ? 'success' : (inv.courier_status === 'failed' ? 'danger' : 'neutral')"
                                />
                                <span v-else class="text-[11px] text-ink-400">None</span>
                            </td>
                        </tr>
                        <tr v-if="basic.latest_invoices.length === 0">
                            <td colspan="8" class="py-8 text-center text-ink-500">
                                No sales recorded in the system yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- LOW STOCK ALERTS SIDE DRAWER -->
        <SideDrawer
            :is-open="lowStockDrawerOpen"
            title="Low Stock Alert Inventory"
            width="560px"
            @close="lowStockDrawerOpen = false"
        >
            <div class="p-4 space-y-6 text-xs">
                <!-- Products -->
                <div>
                    <h4 class="font-bold text-ink-900 border-b border-line-200 pb-1 mb-2 uppercase tracking-wider text-[11px]">
                        Finished Products ({{ basic.low_stock.products_count }})
                    </h4>
                    <div v-if="basic.low_stock.products.length === 0" class="text-ink-500 py-2">
                        All finished goods stock levels are healthy.
                    </div>
                    <div v-else class="divide-y divide-line-100">
                        <div v-for="p in basic.low_stock.products" :key="p.id" class="py-2 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-ink-900">{{ p.name }}</span>
                                <span class="text-ink-500 font-mono ml-2">({{ p.sku }})</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-danger tabular-nums">{{ p.stock_qty }} pcs</span>
                                <span class="text-ink-500 ml-1 text-[11px]">/ min {{ p.min_stock }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leather -->
                <div>
                    <h4 class="font-bold text-ink-900 border-b border-line-200 pb-1 mb-2 uppercase tracking-wider text-[11px]">
                        Leather Materials ({{ basic.low_stock.leather_count }})
                    </h4>
                    <div v-if="basic.low_stock.leather.length === 0" class="text-ink-500 py-2">
                        All raw leather inventory stock levels are healthy.
                    </div>
                    <div v-else class="divide-y divide-line-100">
                        <div v-for="l in basic.low_stock.leather" :key="l.id" class="py-2 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-ink-900">{{ l.name }}</span>
                                <span class="text-ink-500 ml-2">({{ l.colour_grade }})</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-danger tabular-nums">{{ Number(l.current_stock).toFixed(1) }} sq ft</span>
                                <span class="text-ink-500 ml-1 text-[11px]">/ min {{ l.min_stock }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accessories -->
                <div>
                    <h4 class="font-bold text-ink-900 border-b border-line-200 pb-1 mb-2 uppercase tracking-wider text-[11px]">
                        Hardware & Accessories ({{ basic.low_stock.accessories_count }})
                    </h4>
                    <div v-if="basic.low_stock.accessories.length === 0" class="text-ink-500 py-2">
                        All accessory inventory stock levels are healthy.
                    </div>
                    <div v-else class="divide-y divide-line-100">
                        <div v-for="a in basic.low_stock.accessories" :key="a.id" class="py-2 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-ink-900">{{ a.name }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-danger tabular-nums">{{ a.current_stock }} {{ a.unit }}</span>
                                <span class="text-ink-500 ml-1 text-[11px]">/ min {{ a.min_stock }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SideDrawer>
    </div>
</template>
