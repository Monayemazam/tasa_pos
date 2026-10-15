<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import StatusBadge, { type BadgeTone } from '@/components/tasa/StatusBadge.vue';
import { formatMoney, formatDate, formatBDPhone } from '@/lib/formatters';

interface LedgerEntry {
    id: string;
    source_id: number;
    type: 'earning' | 'payment';
    date: string;
    period_start?: string | null;
    description: string;
    note: string | null;
    qty?: number | null;
    rate?: number | null;
    earned: number;
    paid: number;
    running_balance: number;
    created_by?: string | null;
}

interface WorkerStatementDto {
    worker: {
        id: number;
        name: string;
        phone: string | null;
        role: string | null;
        wage_basis: 'per_day' | 'per_piece' | 'fixed_weekly';
        rate: number;
        is_active: boolean;
        is_archived: boolean;
    };
    opening_balance: number;
    closing_balance: number;
    current_balance: number;
    total_earned_range: number;
    total_paid_range: number;
    total_earned_all: number;
    total_paid_all: number;
    earned_this_week: number;
    earned_this_month: number;
    is_owed: boolean;
    is_overpaid: boolean;
    status_label: string;
    status_tone: BadgeTone;
    ledger: LedgerEntry[];
    filters: {
        from?: string | null;
        to?: string | null;
    };
}

const props = defineProps<{
    statement: WorkerStatementDto;
    filters: {
        from?: string | null;
        to?: string | null;
    };
    can: {
        create: boolean;
        update: boolean;
    };
}>();

const fromDate = ref(props.filters.from || '');
const toDate = ref(props.filters.to || '');

const applyFilter = () => {
    router.get(
        `/payroll/workers/${props.statement.worker.id}/statement`,
        {
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const clearFilter = () => {
    fromDate.value = '';
    toDate.value = '';
    applyFilter();
};

const printStatement = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Statement — ${statement.worker.name}`" />

    <div class="space-y-6">
        <!-- Top Action Bar (Hidden on Print) -->
        <div class="print:hidden">
            <PageHeader
                :title="`Worker Statement: ${statement.worker.name}`"
                :subtitle="`${statement.worker.role || 'Worker'} · Wage Basis: ${statement.worker.wage_basis.replace('_', ' ')} · Rate: ${formatMoney(statement.worker.rate)}`"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-2 bg-surface border border-line-300 p-1">
                            <label class="text-xs text-ink-600 pl-2">From:</label>
                            <input
                                v-model="fromDate"
                                type="date"
                                class="h-[28px] px-2 text-xs text-ink-900 bg-transparent border-0 focus:outline-none"
                            />
                            <label class="text-xs text-ink-600">To:</label>
                            <input
                                v-model="toDate"
                                type="date"
                                class="h-[28px] px-2 text-xs text-ink-900 bg-transparent border-0 focus:outline-none"
                            />
                            <button
                                type="button"
                                class="px-2.5 py-1 text-xs bg-ink-900 text-white hover:bg-ink-800 transition"
                                @click="applyFilter"
                            >
                                Filter
                            </button>
                            <button
                                v-if="fromDate || toDate"
                                type="button"
                                class="px-2 py-1 text-xs text-ink-500 hover:text-ink-800 transition"
                                @click="clearFilter"
                            >
                                Clear
                            </button>
                        </div>

                        <AppButton variant="secondary" icon="printer" @click="printStatement">
                            Print Statement
                        </AppButton>

                        <a
                            href="/payroll/workers"
                            class="inline-flex items-center gap-1.5 px-3 h-[36px] text-xs font-semibold border border-line-300 text-ink-700 bg-surface hover:bg-sunken transition"
                        >
                            <AppIcon name="chevron-left" :size="14" />
                            Back to Workers
                        </a>
                    </div>
                </template>
            </PageHeader>
        </div>

        <!-- Printable Document Container -->
        <div class="bg-surface border border-line-200 p-8 max-w-5xl mx-auto printable-document">
            <!-- Printable Header -->
            <div class="border-b-2 border-ink-900 pb-4 mb-6 flex justify-between items-start">
                <div>
                    <h1 class="text-xl font-black text-ink-900 tracking-tight uppercase">
                        Tasa Leather Goods
                    </h1>
                    <div class="text-xs text-ink-600 font-mono mt-0.5">
                        WAGE WORKER STATEMENT & RUNNING LEDGER (PRD §10.2)
                    </div>
                </div>

                <div class="text-right text-xs text-ink-600 font-mono">
                    <div>Date Printed: {{ formatDate(new Date().toISOString()) }}</div>
                    <div v-if="filters.from || filters.to">
                        Period: {{ filters.from || 'Beginning' }} to {{ filters.to || 'Present' }}
                    </div>
                    <div v-else>Statement: Full Work & Payment History</div>
                </div>
            </div>

            <!-- Worker Info Block -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 bg-sunken p-4 border border-line-200 mb-6 text-xs">
                <div>
                    <div class="text-ink-500 font-semibold uppercase text-[10px]">Worker Name</div>
                    <div class="font-bold text-ink-900 text-sm mt-0.5">{{ statement.worker.name }}</div>
                </div>
                <div>
                    <div class="text-ink-500 font-semibold uppercase text-[10px]">Role / Trade</div>
                    <div class="font-medium text-ink-900 mt-0.5">{{ statement.worker.role || 'Artisan' }}</div>
                </div>
                <div>
                    <div class="text-ink-500 font-semibold uppercase text-[10px]">Wage Basis & Standard Rate</div>
                    <div class="font-bold text-ink-900 mt-0.5 capitalize">
                        {{ statement.worker.wage_basis.replace('_', ' ') }} · {{ formatMoney(statement.worker.rate) }}
                    </div>
                </div>
                <div>
                    <div class="text-ink-500 font-semibold uppercase text-[10px]">Phone Number</div>
                    <div class="font-mono text-ink-900 mt-0.5">{{ formatBDPhone(statement.worker.phone) || '—' }}</div>
                </div>
            </div>

            <!-- Financial Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6 print:grid-cols-4">
                <StatCard
                    label="Earned This Week"
                    :value="formatMoney(statement.earned_this_week)"
                    hint="Mon–Sun accrual"
                />
                <StatCard
                    label="Total Earned (All-time)"
                    :value="formatMoney(statement.total_earned_all)"
                    hint="Cumulative gross earnings"
                />
                <StatCard
                    label="Total Cash Paid"
                    :value="formatMoney(statement.total_paid_all)"
                    hint="All disbursements"
                />
                <div class="bg-surface border border-line-200 p-4">
                    <div class="text-xs uppercase font-semibold text-ink-500">Current Balance</div>
                    <div class="text-xl font-black mt-1 tabular-nums font-mono">
                        <span
                            v-if="statement.current_balance < 0"
                            class="text-[#6B3FA0] bg-[#F1EAF8] px-2 py-0.5 border border-[#D0BAEC] inline-block"
                        >
                            {{ formatMoney(statement.current_balance) }}
                        </span>
                        <span v-else class="text-ink-900">
                            {{ formatMoney(statement.current_balance) }}
                        </span>
                    </div>
                    <div class="text-[11px] mt-1 font-medium text-ink-600">
                        {{ statement.status_label }}
                    </div>
                </div>
            </div>

            <!-- Running Ledger Table -->
            <div class="mb-10">
                <div class="flex justify-between items-center mb-2 border-b border-line-200 pb-1">
                    <h2 class="text-sm font-bold text-ink-900 uppercase tracking-wider">
                        Chronological Running Ledger (Earned − Paid)
                    </h2>
                    <div v-if="filters.from" class="text-xs text-ink-600 font-mono">
                        Opening Balance (Prior to {{ filters.from }}):
                        <strong :class="statement.opening_balance < 0 ? 'text-[#6B3FA0]' : ''">
                            {{ formatMoney(statement.opening_balance) }}
                        </strong>
                    </div>
                </div>

                <!-- Desktop / Tablet Table View -->
                <div class="hidden md:block overflow-x-auto border border-line-200">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-sunken border-b border-line-200 text-ink-700 font-bold uppercase text-[11px]">
                                <th scope="col" class="py-2.5 px-3">Date</th>
                                <th scope="col" class="py-2.5 px-3 text-center">Type</th>
                                <th scope="col" class="py-2.5 px-3">Description / Calculation</th>
                                <th scope="col" class="py-2.5 px-3">Note / Reference</th>
                                <th scope="col" class="py-2.5 px-3 text-right">Earned (+)</th>
                                <th scope="col" class="py-2.5 px-3 text-right">Paid (−)</th>
                                <th scope="col" class="py-2.5 px-3 text-right font-black">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-100">
                            <!-- Opening Balance Row if filtered -->
                            <tr v-if="filters.from" class="bg-sunken/40 italic text-ink-600">
                                <td class="py-2 px-3 font-semibold">{{ filters.from }}</td>
                                <td class="py-2 px-3 text-center">
                                    <span class="text-[10px] bg-sunken px-1.5 py-0.5 border border-line-300">
                                        Opening
                                    </span>
                                </td>
                                <td colspan="4" class="py-2 px-3">
                                    Opening balance carried forward from prior periods
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-mono font-bold">
                                    <span :class="statement.opening_balance < 0 ? 'text-[#6B3FA0]' : ''">
                                        {{ formatMoney(statement.opening_balance) }}
                                    </span>
                                </td>
                            </tr>

                            <!-- Ledger Transactions -->
                            <tr
                                v-for="item in statement.ledger"
                                :key="item.id"
                                :class="item.running_balance < 0 ? 'bg-[#FAF6FD]/60' : ''"
                            >
                                <td class="py-2 px-3 font-semibold text-ink-900 whitespace-nowrap">
                                    {{ formatDate(item.date) }}
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <span
                                        v-if="item.type === 'earning'"
                                        class="inline-block px-1.5 py-0.5 text-[10px] font-bold bg-teal-50 text-teal-800 border border-teal-200"
                                    >
                                        Earning
                                    </span>
                                    <span
                                        v-else
                                        class="inline-block px-1.5 py-0.5 text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200"
                                    >
                                        Payment
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-ink-800 font-medium">
                                    {{ item.description }}
                                </td>
                                <td class="py-2 px-3 text-ink-600 max-w-xs truncate">
                                    {{ item.note || '—' }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-semibold text-success">
                                    {{ item.earned > 0 ? `+${formatMoney(item.earned)}` : '—' }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-semibold text-danger">
                                    {{ item.paid > 0 ? `−${formatMoney(item.paid)}` : '—' }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-mono font-black text-sm">
                                    <span
                                        v-if="item.running_balance < 0"
                                        class="text-[#6B3FA0] font-bold"
                                        title="Worker is over-paid and owes Tasa"
                                    >
                                        {{ formatMoney(item.running_balance) }}
                                    </span>
                                    <span v-else-if="item.running_balance > 0" class="text-ink-900">
                                        {{ formatMoney(item.running_balance) }}
                                    </span>
                                    <span v-else class="text-success">
                                        ৳ 0.00
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="statement.ledger.length === 0">
                                <td colspan="7" class="py-6 text-center text-ink-500 italic">
                                    No ledger entries found for this time period.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-sunken border-t-2 border-line-300 font-black text-ink-900">
                            <tr>
                                <td colspan="4" class="py-2.5 px-3 text-right">
                                    Period Totals:
                                </td>
                                <td class="py-2.5 px-3 text-right tabular-nums text-success">
                                    +{{ formatMoney(statement.total_earned_range) }}
                                </td>
                                <td class="py-2.5 px-3 text-right tabular-nums text-danger">
                                    −{{ formatMoney(statement.total_paid_range) }}
                                </td>
                                <td class="py-2.5 px-3 text-right tabular-nums font-mono text-sm">
                                    <span :class="statement.closing_balance < 0 ? 'text-[#6B3FA0]' : ''">
                                        {{ formatMoney(statement.closing_balance) }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Mobile Label/Amount Rows View (< md) -->
                <div class="md:hidden border border-line-200 divide-y divide-line-200 bg-surface">
                    <!-- Mobile Opening Balance -->
                    <div v-if="filters.from" class="p-3 bg-sunken/40 flex items-center justify-between text-xs">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-ink-500">Opening Balance</span>
                            <div class="text-[11px] text-ink-600">Prior to {{ filters.from }}</div>
                        </div>
                        <div class="font-mono font-bold text-sm" :class="statement.opening_balance < 0 ? 'text-[#6B3FA0]' : 'text-ink-900'">
                            {{ formatMoney(statement.opening_balance) }}
                        </div>
                    </div>

                    <!-- Mobile Ledger Items -->
                    <div
                        v-for="item in statement.ledger"
                        :key="`m-${item.id}`"
                        class="p-3 space-y-2 text-xs"
                        :class="item.running_balance < 0 ? 'bg-[#FAF6FD]/60' : ''"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-ink-900">{{ formatDate(item.date) }}</span>
                                <span
                                    v-if="item.type === 'earning'"
                                    class="px-1.5 py-0.5 text-[10px] font-bold bg-teal-50 text-teal-800 border border-teal-200"
                                >
                                    Earning
                                </span>
                                <span
                                    v-else
                                    class="px-1.5 py-0.5 text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200"
                                >
                                    Payment
                                </span>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] uppercase text-ink-500 font-bold">Balance</div>
                                <div class="font-mono font-black text-sm" :class="item.running_balance < 0 ? 'text-[#6B3FA0]' : 'text-ink-900'">
                                    {{ formatMoney(item.running_balance) }}
                                </div>
                            </div>
                        </div>

                        <div class="text-ink-800">
                            {{ item.description }}
                        </div>

                        <div v-if="item.note" class="text-[11px] text-ink-500 italic">
                            Note: {{ item.note }}
                        </div>

                        <div class="pt-2 border-t border-line-100 flex items-center justify-between font-semibold">
                            <span class="text-ink-500">Transaction:</span>
                            <span v-if="item.earned > 0" class="text-success font-mono font-bold">
                                +{{ formatMoney(item.earned) }}
                            </span>
                            <span v-else-if="item.paid > 0" class="text-danger font-mono font-bold">
                                −{{ formatMoney(item.paid) }}
                            </span>
                        </div>
                    </div>

                    <div v-if="statement.ledger.length === 0" class="p-6 text-center text-ink-500 italic text-xs">
                        No ledger entries found for this time period.
                    </div>

                    <!-- Mobile Period Totals -->
                    <div class="p-3 bg-sunken space-y-1.5 text-xs border-t-2 border-line-300">
                        <div class="flex justify-between text-ink-700">
                            <span>Total Earned:</span>
                            <span class="text-success font-mono font-bold">+{{ formatMoney(statement.total_earned_range) }}</span>
                        </div>
                        <div class="flex justify-between text-ink-700">
                            <span>Total Paid:</span>
                            <span class="text-danger font-mono font-bold">−{{ formatMoney(statement.total_paid_range) }}</span>
                        </div>
                        <div class="flex justify-between font-black text-ink-900 pt-1 border-t border-line-200 text-sm">
                            <span>Closing Balance:</span>
                            <span class="font-mono" :class="statement.closing_balance < 0 ? 'text-[#6B3FA0]' : 'text-ink-900'">
                                {{ formatMoney(statement.closing_balance) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Signature Blocks (Visible on Print) -->
            <div class="pt-12 border-t-2 border-line-300 grid grid-cols-3 gap-8 text-center text-xs">
                <div>
                    <div class="border-t border-ink-900 pt-1.5 font-bold text-ink-900">Prepared By</div>
                    <div class="text-[10px] text-ink-500 mt-0.5">Workshop Supervisor / Accountant</div>
                </div>
                <div>
                    <div class="border-t border-ink-900 pt-1.5 font-bold text-ink-900">Approved By (Owner)</div>
                    <div class="text-[10px] text-ink-500 mt-0.5">Tasa Administration</div>
                </div>
                <div>
                    <div class="border-t border-ink-900 pt-1.5 font-bold text-ink-900">Worker Signature & Date</div>
                    <div class="text-[10px] text-ink-500 mt-0.5">{{ statement.worker.name }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    :deep(aside),
    :deep(header),
    .no-print,
    button,
    nav {
        display: none !important;
    }

    body {
        background: #fff !important;
    }

    .printable-document {
        border: none !important;
        padding: 0 !important;
        max-width: 100% !important;
    }

    table {
        border: 1px solid #333 !important;
    }

    th, td {
        border: 1px solid #ddd !important;
        padding: 4px 6px !important;
        font-size: 10px !important;
    }
}
</style>
